<?php
/**
 * Service VendorClientAnalyticsService
 * Análise de clientes e produtos por vendedor
 * - Produtos vendidos, clientes recorrentes, top clientes
 * - Clientes ativos/inativos, primeira compra, recompra
 */

namespace App\Services;

use App\Models\User;
use App\Helpers\Database;

class VendorClientAnalyticsService
{
    /**
     * Status válidos para considerar como venda efetiva
     */
    private static array $validStatuses = [
        'processing', 'completed', 'producao', 'designer',
        'pedido-enviado', 'pedido-entregue', 'etiqueta-gerada'
    ];

    /**
     * Obter analytics completa de um vendedor
     */
    public static function getVendorAnalytics(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $agent = User::find($agentId);
        $sellerId = $agent['woocommerce_seller_id'] ?? null;

        if (!$sellerId) {
            return self::emptyAnalytics();
        }

        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        if (!str_contains($dateTo, ':')) {
            $dateTo .= ' 23:59:59';
        }

        return [
            'client_summary' => self::getClientSummary($sellerId, $dateFrom, $dateTo),
            'top_clients' => self::getTopClients($sellerId, $dateFrom, $dateTo, 10),
            'top_products' => self::getTopProducts($sellerId, $dateFrom, $dateTo, 10),
            'client_retention' => self::getClientRetention($sellerId),
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
        ];
    }

    /**
     * Resumo de clientes no período
     */
    public static function getClientSummary(int $sellerId, string $dateFrom, string $dateTo): array
    {
        if (!str_contains($dateTo, ':')) {
            $dateTo .= ' 23:59:59';
        }

        $statusIn = self::statusPlaceholders();

        // Clientes únicos no período com pedidos válidos
        $sql = "SELECT
                    COUNT(DISTINCT oc.contact_id) AS total_clients,
                    COUNT(oc.id) AS total_orders,
                    SUM(oc.order_total) AS total_revenue
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0";

        $periodStats = Database::fetch($sql, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses
        )) ?: [];

        // Clientes que compraram pela PRIMEIRA VEZ no período
        // (não tinham pedido anterior com este vendedor)
        $sqlFirstTime = "SELECT COUNT(DISTINCT oc.contact_id) AS total
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0
                  AND NOT EXISTS (
                      SELECT 1 FROM woocommerce_order_cache prev
                      WHERE prev.seller_id = oc.seller_id
                        AND prev.contact_id = oc.contact_id
                        AND prev.order_date < ?
                        AND prev.order_status IN ({$statusIn})
                  )";

        $firstTime = Database::fetch($sqlFirstTime, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses,
            [$dateFrom],
            self::$validStatuses
        )) ?: [];

        // Clientes que RECOMPRARAM no período (tinham pedido anterior)
        $sqlRepeat = "SELECT COUNT(DISTINCT oc.contact_id) AS total
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0
                  AND EXISTS (
                      SELECT 1 FROM woocommerce_order_cache prev
                      WHERE prev.seller_id = oc.seller_id
                        AND prev.contact_id = oc.contact_id
                        AND prev.order_date < ?
                        AND prev.order_status IN ({$statusIn})
                  )";

        $repeat = Database::fetch($sqlRepeat, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses,
            [$dateFrom],
            self::$validStatuses
        )) ?: [];

        // Clientes recorrentes no período (compraram 2+ vezes no período)
        $sqlRecurring = "SELECT COUNT(*) AS total FROM (
                SELECT oc.contact_id
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0
                GROUP BY oc.contact_id
                HAVING COUNT(oc.id) >= 2
            ) t";

        $recurring = Database::fetch($sqlRecurring, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses
        )) ?: [];

        $totalClients = (int)($periodStats['total_clients'] ?? 0);
        $firstTimeCount = (int)($firstTime['total'] ?? 0);
        $repeatCount = (int)($repeat['total'] ?? 0);
        $recurringCount = (int)($recurring['total'] ?? 0);

        return [
            'total_clients' => $totalClients,
            'total_orders' => (int)($periodStats['total_orders'] ?? 0),
            'total_revenue' => (float)($periodStats['total_revenue'] ?? 0),
            'first_time_buyers' => $firstTimeCount,
            'repeat_buyers' => $repeatCount,
            'recurring_in_period' => $recurringCount,
            'first_time_pct' => $totalClients > 0 ? round(($firstTimeCount / $totalClients) * 100, 1) : 0,
            'repeat_pct' => $totalClients > 0 ? round(($repeatCount / $totalClients) * 100, 1) : 0,
        ];
    }

    /**
     * Retenção de clientes (ativos vs inativos) - visão global do vendedor
     */
    public static function getClientRetention(int $sellerId): array
    {
        $statusIn = self::statusPlaceholders();
        $oneYearAgo = date('Y-m-d H:i:s', strtotime('-1 year'));

        // Total de clientes únicos que já compraram com este vendedor
        $sqlTotal = "SELECT COUNT(DISTINCT contact_id) AS total
                FROM woocommerce_order_cache
                WHERE seller_id = ?
                  AND order_status IN ({$statusIn})
                  AND contact_id IS NOT NULL AND contact_id > 0";

        $total = Database::fetch($sqlTotal, array_merge([$sellerId], self::$validStatuses)) ?: [];

        // Clientes ativos (compraram no último ano)
        $sqlActive = "SELECT COUNT(DISTINCT contact_id) AS total
                FROM woocommerce_order_cache
                WHERE seller_id = ?
                  AND order_date >= ?
                  AND order_status IN ({$statusIn})
                  AND contact_id IS NOT NULL AND contact_id > 0";

        $active = Database::fetch($sqlActive, array_merge([$sellerId, $oneYearAgo], self::$validStatuses)) ?: [];

        $totalAll = (int)($total['total'] ?? 0);
        $activeCount = (int)($active['total'] ?? 0);
        $inactiveCount = $totalAll - $activeCount;

        return [
            'total_all_time' => $totalAll,
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'active_pct' => $totalAll > 0 ? round(($activeCount / $totalAll) * 100, 1) : 0,
            'inactive_pct' => $totalAll > 0 ? round(($inactiveCount / $totalAll) * 100, 1) : 0,
        ];
    }

    /**
     * Top clientes por faturamento no período
     */
    public static function getTopClients(
        int $sellerId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10
    ): array {
        if (!str_contains($dateTo, ':')) {
            $dateTo .= ' 23:59:59';
        }

        $statusIn = self::statusPlaceholders();
        $oneYearAgo = date('Y-m-d H:i:s', strtotime('-1 year'));

        $sql = "SELECT
                    oc.contact_id,
                    c.name AS contact_name,
                    c.last_name AS contact_last_name,
                    c.phone AS contact_phone,
                    c.email AS contact_email,
                    COUNT(oc.id) AS order_count,
                    SUM(oc.order_total) AS total_spent,
                    MAX(oc.order_date) AS last_order_date,
                    MIN(oc.order_date) AS first_order_date
                FROM woocommerce_order_cache oc
                LEFT JOIN contacts c ON c.id = oc.contact_id
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})
                  AND oc.contact_id IS NOT NULL AND oc.contact_id > 0
                GROUP BY oc.contact_id, c.name, c.last_name, c.phone, c.email
                ORDER BY total_spent DESC
                LIMIT ?";

        $rows = Database::fetchAll($sql, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses,
            [$limit]
        ));

        // Enriquecer com status ativo/inativo e se é recorrente geral
        foreach ($rows as &$row) {
            $row['total_spent'] = (float)$row['total_spent'];
            $row['order_count'] = (int)$row['order_count'];
            $row['full_name'] = trim(($row['contact_name'] ?? '') . ' ' . ($row['contact_last_name'] ?? ''));

            // Ativo = comprou no último ano (qualquer vendedor)
            $row['is_active'] = !empty($row['last_order_date']) && $row['last_order_date'] >= $oneYearAgo;

            // Total de pedidos históricos com este vendedor
            $histSql = "SELECT COUNT(id) AS cnt FROM woocommerce_order_cache
                        WHERE seller_id = ? AND contact_id = ? AND order_status IN ({$statusIn})";
            $hist = Database::fetch($histSql, array_merge([$sellerId, $row['contact_id']], self::$validStatuses));
            $row['total_orders_all_time'] = (int)($hist['cnt'] ?? 0);
            $row['is_recurring'] = $row['total_orders_all_time'] >= 2;
        }
        unset($row);

        return $rows;
    }

    /**
     * Top produtos vendidos no período
     */
    public static function getTopProducts(
        int $sellerId,
        string $dateFrom,
        string $dateTo,
        int $limit = 10
    ): array {
        if (!str_contains($dateTo, ':')) {
            $dateTo .= ' 23:59:59';
        }

        $statusIn = self::statusPlaceholders();

        // Buscar pedidos válidos e extrair line_items do JSON
        $sql = "SELECT oc.order_data
                FROM woocommerce_order_cache oc
                WHERE oc.seller_id = ?
                  AND oc.order_date BETWEEN ? AND ?
                  AND oc.order_status IN ({$statusIn})";

        $orders = Database::fetchAll($sql, array_merge(
            [$sellerId, $dateFrom, $dateTo],
            self::$validStatuses
        ));

        // Agregar produtos
        $products = [];
        foreach ($orders as $order) {
            $data = json_decode($order['order_data'] ?? '{}', true);
            foreach ($data['line_items'] ?? [] as $item) {
                $productId = $item['product_id'] ?? ($item['id'] ?? 0);
                $name = $item['name'] ?? 'Produto desconhecido';
                $sku = $item['sku'] ?? '';
                $qty = (int)($item['quantity'] ?? 1);
                $total = (float)($item['total'] ?? $item['price'] ?? 0);

                $key = $productId ?: md5($name);
                if (!isset($products[$key])) {
                    $products[$key] = [
                        'product_id' => $productId,
                        'name' => $name,
                        'sku' => $sku,
                        'quantity_sold' => 0,
                        'total_revenue' => 0,
                        'order_count' => 0,
                    ];
                }
                $products[$key]['quantity_sold'] += $qty;
                $products[$key]['total_revenue'] += $total;
                $products[$key]['order_count']++;
            }
        }

        // Ordenar por receita
        usort($products, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);

        return array_slice($products, 0, $limit);
    }

    /**
     * Analytics consolidada de todos os vendedores (para dashboard admin)
     */
    public static function getAllVendorsClientSummary(
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $dateFrom = $dateFrom ?? date('Y-m-01');
        $dateTo = $dateTo ?? date('Y-m-d H:i:s');
        if (!str_contains($dateTo, ':')) {
            $dateTo .= ' 23:59:59';
        }

        $sellers = User::getSellers();
        if (empty($sellers)) {
            return [];
        }

        $results = [];
        foreach ($sellers as $seller) {
            $sellerId = $seller['woocommerce_seller_id'] ?? null;
            if (!$sellerId) continue;

            $summary = self::getClientSummary($sellerId, $dateFrom, $dateTo);
            $retention = self::getClientRetention($sellerId);

            $results[] = [
                'agent_id' => $seller['id'],
                'agent_name' => $seller['name'],
                'seller_id' => $sellerId,
                'client_summary' => $summary,
                'client_retention' => $retention,
            ];
        }

        // Ordenar por receita total
        usort($results, fn($a, $b) =>
            ($b['client_summary']['total_revenue'] ?? 0) <=> ($a['client_summary']['total_revenue'] ?? 0)
        );

        return $results;
    }

    /**
     * Gerar placeholders para status válidos
     */
    private static function statusPlaceholders(): string
    {
        return implode(',', array_fill(0, count(self::$validStatuses), '?'));
    }

    /**
     * Analytics vazia
     */
    private static function emptyAnalytics(): array
    {
        return [
            'client_summary' => [
                'total_clients' => 0, 'total_orders' => 0, 'total_revenue' => 0,
                'first_time_buyers' => 0, 'repeat_buyers' => 0, 'recurring_in_period' => 0,
                'first_time_pct' => 0, 'repeat_pct' => 0,
            ],
            'top_clients' => [],
            'top_products' => [],
            'client_retention' => [
                'total_all_time' => 0, 'active' => 0, 'inactive' => 0,
                'active_pct' => 0, 'inactive_pct' => 0,
            ],
            'period' => ['from' => '', 'to' => ''],
        ];
    }
}
