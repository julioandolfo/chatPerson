<?php
/**
 * Service AgentConversionService
 * Métricas de conversão Lead → Venda (WooCommerce)
 */

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
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
        
        // 4. Filtrar apenas pedidos válidos (não cancelados, não reembolsados, não falhados)
        $validStatuses = ['completed', 'processing', 'on-hold', 'pending'];
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
     */
    private static function getTotalConversations(int $agentId, string $dateFrom, string $dateTo): int
    {
        $sql = "SELECT COUNT(*) as count 
                FROM conversations 
                WHERE agent_id = ? 
                  AND created_at >= ? 
                  AND created_at <= ?";
        
        $result = Database::fetch($sql, [$agentId, $dateFrom, $dateTo]);
        return (int)($result['count'] ?? 0);
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
        
        // Processar cada pedido do cache
        $processedOrders = [];
        foreach ($cachedOrders as $cached) {
            // Decodificar JSON do pedido completo
            $orderData = json_decode($cached['order_data'], true);
            
            if (!$orderData) {
                continue;
            }
            
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
                'conversation_id' => null, // TODO: Implementar correlação com conversas
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
     * Formatar valor em reais
     */
    public static function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
