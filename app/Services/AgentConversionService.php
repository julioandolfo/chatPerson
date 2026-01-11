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
        
        // 3. Buscar pedidos do WooCommerce
        $orders = WooCommerceIntegrationService::getOrdersBySeller($sellerId, null, $dateFrom, $dateTo);
        
        // 4. Calcular métricas
        $totalOrders = count($orders);
        $totalRevenue = 0;
        $ordersByStatus = [];
        
        foreach ($orders as $order) {
            $total = floatval($order['total'] ?? 0);
            $totalRevenue += $total;
            
            $status = $order['status'] ?? 'unknown';
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
        
        return WooCommerceIntegrationService::getOrdersBySeller($sellerId, null, $dateFrom, $dateTo);
    }
    
    /**
     * Formatar valor em reais
     */
    public static function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
