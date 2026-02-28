<?php
/**
 * Service AgentConversionService
 * Métricas de conversão Lead → Venda (WooCommerce)
 */

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Contact;
use App\Helpers\Database;

class AgentConversionService
{
    /**
     * Obter métricas de conversão de um agente
     */
    public static function getConversionMetrics(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // 1. Buscar informações do agente
        $agent = User::find($agentId);
        if (!$agent) {
            return self::getEmptyMetrics($agentId, $dateFrom, $dateTo);
        }
        
        $sellerId = $agent['woocommerce_seller_id'] ?? null;
        if (!$sellerId) {
            return self::getEmptyMetrics($agentId, $dateFrom, $dateTo);
        }
        
        // 2. Total de conversas do agente no período
        $totalConversations = self::getTotalConversations($agentId, $dateFrom, $dateTo);
        
        // 3. Buscar pedidos do CACHE (sincronizados pelo CRON)
        $orders = self::getOrdersFromCache($sellerId, $dateFrom, $dateTo);
        
        // 4. Filtrar apenas pedidos válidos para conversão
        // Valem: processing, completed, producao, designer, pedido-enviado, pedido-entregue, etiqueta-gerada
        $validStatuses = ['processing', 'completed', 'producao', 'designer', 'pedido-enviado', 'pedido-entregue', 'etiqueta-gerada'];
        $validOrders = array_filter($orders, function($order) use ($validStatuses) {
            $status = $order['order_status'] ?? 'pending';
            return in_array($status, $validStatuses);
        });
        
        // 5. Calcular métricas
        $totalOrders = count($validOrders);
        $totalRevenue = 0;
        $ordersByStatus = [];
        
        foreach ($validOrders as $order) {
            $total = floatval($order['order_total'] ?? 0);
            $totalRevenue += $total;
            
            $status = $order['order_status'] ?? 'unknown';
            $ordersByStatus[$status] = ($ordersByStatus[$status] ?? 0) + 1;
        }
        
        $conversionRate = $totalConversations > 0 
            ? round(($totalOrders / $totalConversations) * 100, 2) 
            : 0;
        
        $avgTicket = $totalOrders > 0 
            ? round($totalRevenue / $totalOrders, 2) 
            : 0;
        
        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['name'],
            'seller_id' => $sellerId,
            'total_conversations' => $totalConversations,
            'total_orders' => $totalOrders,
            'conversion_rate' => $conversionRate,
            'total_revenue' => $totalRevenue,
            'avg_ticket' => $avgTicket,
            'orders_by_status' => $ordersByStatus,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Obter métricas vazias
     */
    private static function getEmptyMetrics(int $agentId, string $dateFrom, string $dateTo): array
    {
        $agent = User::find($agentId);
        return [
            'agent_id' => $agentId,
            'agent_name' => $agent['name'] ?? 'Desconhecido',
            'seller_id' => null,
            'total_conversations' => 0,
            'total_orders' => 0,
            'conversion_rate' => 0,
            'total_revenue' => 0,
            'avg_ticket' => 0,
            'orders_by_status' => [],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ];
    }
    
    /**
     * Total de conversas do agente no período
     * Baseado na data de criação da conversa, não na atribuição
     */
    private static function getTotalConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }

        // Contar conversas criadas no período onde o agente está atribuído
        // Isso alinha com os badges de iniciador (agente/cliente)
        $sql = "SELECT COUNT(DISTINCT c.id) as total
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?";

        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Contar conversas iniciadas pelo agente (agente fez primeiro contato)
     * Verifica se a primeira mensagem da conversa foi do agente
     */
    public static function getAgentInitiatedConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT COUNT(DISTINCT c.id) as total
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.sender_type = 'agent' 
                    AND m.sender_id > 0
                    AND m.created_at = (
                        SELECT MIN(m2.created_at) 
                        FROM messages m2 
                        WHERE m2.conversation_id = c.id
                        AND m2.sender_id > 0
                    )
                )";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Contar conversas iniciadas pelo cliente (cliente fez primeiro contato)
     * Verifica se a primeira mensagem da conversa foi do cliente/contato
     */
    public static function getClientInitiatedConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT COUNT(DISTINCT c.id) as total
                FROM conversations c
                WHERE c.agent_id = ?
                AND c.created_at >= ?
                AND c.created_at <= ?
                AND EXISTS (
                    SELECT 1 FROM messages m 
                    WHERE m.conversation_id = c.id 
                    AND m.sender_type = 'contact' 
                    AND m.sender_id > 0
                    AND m.created_at = (
                        SELECT MIN(m2.created_at) 
                        FROM messages m2 
                        WHERE m2.conversation_id = c.id
                        AND m2.sender_id > 0
                    )
                )";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Obter métricas de conversão detalhadas com separação por iniciador
     */
    public static function getDetailedConversionMetrics(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // Métricas básicas
        $basicMetrics = self::getConversionMetrics($agentId, $dateFrom, $dateTo);
        
        // Conversas por iniciador
        $agentInitiated = self::getAgentInitiatedConversations($agentId, $dateFrom, $dateTo);
        $clientInitiated = self::getClientInitiatedConversations($agentId, $dateFrom, $dateTo);
        
        // Calcular taxas de conversão separadas
        $totalOrders = $basicMetrics['total_orders'] ?? 0;
        
        // Taxa de conversão geral (já calculada)
        $conversionRateTotal = $basicMetrics['conversion_rate'] ?? 0;
        
        // Taxa de conversão apenas clientes (conversas iniciadas pelo cliente)
        // Considera que a maioria das vendas vem de clientes que entraram em contato
        $conversionRateClientOnly = $clientInitiated > 0 
            ? round(($totalOrders / $clientInitiated) * 100, 2) 
            : 0;
        
        return array_merge($basicMetrics, [
            'conversations_agent_initiated' => $agentInitiated,
            'conversations_client_initiated' => $clientInitiated,
            'conversion_rate_total' => $conversionRateTotal,
            'conversion_rate_client_only' => $conversionRateClientOnly,
        ]);
    }
    
    /**
     * Obter ranking de agentes por conversão
     */
    public static function getRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Buscar todos os vendedores
        $sellers = User::getSellers();
        
        if (empty($sellers)) {
            return [];
        }
        
        $ranking = [];
        
        foreach ($sellers as $seller) {
            $metrics = self::getConversionMetrics($seller['id'], $dateFrom, $dateTo);
            if ($metrics['total_orders'] > 0 || $metrics['total_conversations'] > 0) {
                $ranking[] = $metrics;
            }
        }
        
        // Ordenar por taxa de conversão (maior para menor)
        usort($ranking, function($a, $b) {
            // Primeiro por conversão, depois por total de vendas
            if ($b['conversion_rate'] != $a['conversion_rate']) {
                return $b['conversion_rate'] <=> $a['conversion_rate'];
            }
            return $b['total_orders'] <=> $a['total_orders'];
        });
        
        return array_slice($ranking, 0, $limit);
    }
    
    /**
     * Obter pedidos de um agente com detalhes
     */
    public static function getAgentOrders(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array
    {
        $agent = User::find($agentId);
        if (!$agent) {
            return [];
        }
        
        $sellerId = $agent['woocommerce_seller_id'] ?? null;
        if (!$sellerId) {
            return [];
        }
        
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        
        // Buscar do cache e processar para formato compatível com a view
        $cachedOrders = self::getOrdersFromCache($sellerId, $dateFrom, $dateTo);
        
        // Status válidos para conversão
        $validStatuses = ['processing', 'completed', 'producao', 'designer', 'pedido-enviado', 'pedido-entregue', 'etiqueta-gerada'];
        
        // Processar cada pedido do cache
        $processedOrders = [];
        foreach ($cachedOrders as $cached) {
            // Filtrar apenas pedidos com status válido
            if (!in_array($cached['order_status'], $validStatuses)) {
                continue; // Pular pedidos cancelados, pendentes, etc
            }
            
            // Decodificar JSON do pedido completo
            $orderData = json_decode($cached['order_data'], true);
            
            if (!$orderData) {
                continue;
            }

            // Tentar correlacionar conversa pelo contact_id do cache ou telefone do pedido
            $conversationId = self::findConversationForOrder($cached, $orderData, $agentId);
            
            // Montar estrutura compatível com a view
            $processedOrders[] = [
                'order_id' => $cached['order_id'],
                'status' => $cached['order_status'],
                'total' => $cached['order_total'],
                'order_date' => $cached['order_date'],
                'order_status' => $cached['order_status'],
                'order_total' => $cached['order_total'],
                'customer_name' => trim(($orderData['billing']['first_name'] ?? '') . ' ' . ($orderData['billing']['last_name'] ?? '')),
                'customer_email' => $orderData['billing']['email'] ?? '',
                'customer_phone' => $orderData['billing']['phone'] ?? '',
                'woocommerce_url' => $orderData['_links']['self'][0]['href'] ?? '#',
                'conversation_id' => $conversationId,
                'full_data' => $orderData // Dados completos do pedido
            ];
        }
        
        return $processedOrders;
    }
    
    /**
     * Buscar pedidos do cache por seller_id
     */
    private static function getOrdersFromCache(int $sellerId, string $dateFrom, string $dateTo): array
    {
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        $sql = "SELECT * FROM woocommerce_order_cache 
                WHERE seller_id = ? 
                AND order_date BETWEEN ? AND ?
                ORDER BY order_date DESC";
        
        return Database::fetchAll($sql, [$sellerId, $dateFrom, $dateTo]);
    }

    /**
     * Encontrar conversa relacionada ao pedido:
     * 1) Usa contact_id salvo no cache, se existir
     * 2) Caso contrário, tenta pelo telefone do billing (normalizado) e busca contato e conversa mais recente
     */
    private static function findConversationForOrder(array $cached, array $orderData, int $agentId): ?int
    {
        // 1) Se o cache já tem contact_id, buscar conversa mais recente desse contato (priorizando agente)
        if (!empty($cached['contact_id'])) {
            $conv = self::findLatestConversation((int)$cached['contact_id'], $agentId);
            if ($conv) {
                return $conv;
            }
        }

        // 2) Tentar pelo telefone do pedido
        $phone = $orderData['billing']['phone'] ?? '';
        if (!empty($phone)) {
            $contact = Contact::findByPhoneNormalized($phone);
            if ($contact && !empty($contact['id'])) {
                $conv = self::findLatestConversation((int)$contact['id'], $agentId);
                if ($conv) {
                    return $conv;
                }
            }
        }

        return null;
    }

    /**
     * Buscar a conversa mais recente de um contato, priorizando o agente atual
     */
    private static function findLatestConversation(int $contactId, int $agentId): ?int
    {
        // Priorizar conversa do mesmo agente, senão pegar qualquer uma do contato
        $sql = "SELECT id FROM conversations 
                WHERE contact_id = ? AND agent_id = ? 
                ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 1";
        $row = Database::fetch($sql, [$contactId, $agentId]);
        if (!empty($row['id'])) {
            return (int)$row['id'];
        }

        $sql = "SELECT id FROM conversations 
                WHERE contact_id = ? 
                ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT 1";
        $row = Database::fetch($sql, [$contactId]);
        return !empty($row['id']) ? (int)$row['id'] : null;
    }
    
    /**
     * Formatar valor em reais
     */
    public static function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
