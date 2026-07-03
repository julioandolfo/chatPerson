<?php
/**
 * StatsController - API v1
 * Estatísticas e métricas do dashboard
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Services\DashboardService;

class StatsController
{
    /**
     * Visão geral — totais do dashboard
     * GET /api/v1/stats/overview
     *
     * Query params:
     *   date_from  (Y-m-d) — padrão: primeiro dia do mês
     *   date_to    (Y-m-d) — padrão: hoje
     */
    public function overview(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;

        try {
            $stats = DashboardService::getGeneralStats(
                userId: ApiAuthMiddleware::userId(),
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );

            ApiResponse::success($stats);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter estatísticas gerais', $e);
        }
    }

    /**
     * Conversas ao longo do tempo
     * GET /api/v1/stats/conversations
     *
     * Query params:
     *   date_from  (Y-m-d)              — padrão: primeiro dia do mês
     *   date_to    (Y-m-d)              — padrão: hoje
     *   group_by   (hour|day|week|month) — padrão: day
     *   department_id (int)
     *   agent_id      (int)
     *   channel       (string)
     */
    public function conversations(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom     = $_GET['date_from']     ?? null;
        $dateTo       = $_GET['date_to']       ?? null;
        $groupBy      = $_GET['group_by']      ?? 'day';
        $departmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : null;
        $agentId      = isset($_GET['agent_id'])      ? (int)$_GET['agent_id']      : null;
        $channel      = $_GET['channel']              ?? null;

        $allowedGroupBy = ['hour', 'day', 'week', 'month'];
        if (!in_array($groupBy, $allowedGroupBy)) {
            ApiResponse::badRequest('group_by inválido. Use: ' . implode(', ', $allowedGroupBy));
        }

        $filters = array_filter([
            'department_id' => $departmentId,
            'agent_ids'     => $agentId ? [$agentId] : [],
            'channel'       => $channel,
        ], fn($v) => $v !== null && $v !== []);

        try {
            $data = DashboardService::getConversationsOverTime(
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                groupBy: $groupBy,
                filters: $filters
            );

            ApiResponse::success([
                'group_by' => $groupBy,
                'period'   => [
                    'from' => $dateFrom ?? date('Y-m-01'),
                    'to'   => $dateTo   ?? date('Y-m-d'),
                ],
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter conversas por período', $e);
        }
    }

    /**
     * Ranking de agentes
     * GET /api/v1/stats/agents
     *
     * Query params:
     *   date_from (Y-m-d) — padrão: primeiro dia do mês
     *   date_to   (Y-m-d) — padrão: hoje
     *   limit     (int)   — padrão: 10, máximo: 50
     */
    public function agents(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;
        $limit    = min((int)($_GET['limit'] ?? 10), 50);

        try {
            $data = DashboardService::getTopAgents(
                dateFrom: $dateFrom,
                dateTo: $dateTo,
                limit: $limit
            );

            ApiResponse::success([
                'period' => [
                    'from' => $dateFrom ?? date('Y-m-01'),
                    'to'   => $dateTo   ?? date('Y-m-d'),
                ],
                'limit'  => $limit,
                'agents' => $data,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter ranking de agentes', $e);
        }
    }

    /**
     * Estatísticas por setor
     * GET /api/v1/stats/departments
     */
    public function departments(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        try {
            $data = DashboardService::getDepartmentStats();
            ApiResponse::success($data);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter estatísticas de setores', $e);
        }
    }

    /**
     * Estatísticas por funil
     * GET /api/v1/stats/funnels
     */
    public function funnels(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        try {
            $data = DashboardService::getFunnelStats();
            ApiResponse::success($data);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter estatísticas de funis', $e);
        }
    }

    /**
     * Métricas detalhadas de um agente específico
     * GET /api/v1/stats/agents/:id
     *
     * Query params:
     *   date_from (Y-m-d)
     *   date_to   (Y-m-d)
     */
    public function agentDetail(string $id): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;

        try {
            $data = DashboardService::getAgentMetrics(
                agentId: (int)$id,
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );

            ApiResponse::success($data);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter métricas do agente', $e);
        }
    }

    /**
     * Conversão comercial (Lead → Venda WooCommerce) de todos os vendedores
     * GET /api/v1/stats/conversion
     *
     * Vendedor = usuário ativo com woocommerce_seller_id preenchido.
     * Retorna as três variantes da taxa de conversão (receptivas,
     * receptivas+ativas e interativas) para consumo por sistemas externos.
     *
     * Query params:
     *   date_from (Y-m-d) — padrão: primeiro dia do mês
     *   date_to   (Y-m-d) — padrão: hoje
     */
    public function conversion(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;

        try {
            $sellers = \App\Models\User::getSellers();

            $data = [];
            foreach ($sellers as $seller) {
                $metrics = \App\Services\AgentConversionService::getDetailedConversionMetrics(
                    (int)$seller['id'],
                    $dateFrom,
                    $dateTo
                );
                $data[] = self::formatConversionMetrics($seller, $metrics);
            }

            ApiResponse::success([
                'period' => [
                    'from' => $dateFrom ?? date('Y-m-01'),
                    'to'   => $dateTo   ?? date('Y-m-d'),
                ],
                'sellers' => $data,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter métricas de conversão', $e);
        }
    }

    /**
     * Conversão comercial de um vendedor específico
     * GET /api/v1/stats/agents/:id/conversion
     *
     * Query params:
     *   date_from (Y-m-d)
     *   date_to   (Y-m-d)
     */
    public function agentConversion(string $id): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;

        try {
            $agent = \App\Models\User::find((int)$id);
            if (!$agent) {
                ApiResponse::notFound('Agente não encontrado');
            }

            $metrics = \App\Services\AgentConversionService::getDetailedConversionMetrics(
                (int)$id,
                $dateFrom,
                $dateTo
            );

            ApiResponse::success(self::formatConversionMetrics($agent, $metrics));
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter métricas de conversão do agente', $e);
        }
    }

    /**
     * Payload padronizado de conversão para consumo externo
     */
    private static function formatConversionMetrics(array $agent, array $metrics): array
    {
        return [
            'agent_id'               => (int)$agent['id'],
            'agent_name'             => $agent['name'] ?? null,
            'agent_email'            => $agent['email'] ?? null,
            'woocommerce_seller_id'  => isset($agent['woocommerce_seller_id']) && $agent['woocommerce_seller_id'] !== null
                ? (int)$agent['woocommerce_seller_id']
                : null,
            'period'                 => $metrics['period'] ?? null,

            // Vendas (pedidos WooCommerce válidos do período)
            'total_orders'           => (int)($metrics['total_orders'] ?? 0),
            'total_revenue'          => (float)($metrics['total_revenue'] ?? 0),
            'avg_ticket'             => (float)($metrics['avg_ticket'] ?? 0),

            // Leads (conversas) por variante
            'leads' => [
                'total_conversations'      => (int)($metrics['total_conversations'] ?? 0),
                'client_initiated'         => (int)($metrics['conversations_client_initiated'] ?? 0),
                'agent_initiated'          => (int)($metrics['conversations_agent_initiated'] ?? 0),
                'receptivas_ativas'        => (int)($metrics['conversations_receptivas_ativas'] ?? 0),
                'interactive'              => (int)($metrics['interactive_conversations'] ?? 0),
            ],

            // Taxas de conversão por variante (%)
            'conversion_rates' => [
                'total'             => (float)($metrics['conversion_rate'] ?? 0),
                'client_only'       => (float)($metrics['conversion_rate_client_only'] ?? 0),
                'receptivas_ativas' => (float)($metrics['conversion_rate_receptivas_ativas'] ?? 0),
                'interactive'       => (float)($metrics['conversion_rate_interactive'] ?? 0),
            ],
        ];
    }

    /**
     * Métricas de SLA
     * GET /api/v1/stats/sla
     *
     * Query params:
     *   date_from (Y-m-d)
     *   date_to   (Y-m-d)
     */
    public function sla(): void
    {
        ApiAuthMiddleware::requirePermission('reports.view');

        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to']   ?? null;

        try {
            $data = DashboardService::getSLAMetrics(
                dateFrom: $dateFrom,
                dateTo: $dateTo
            );

            ApiResponse::success([
                'period' => [
                    'from' => $dateFrom ?? date('Y-m-01'),
                    'to'   => $dateTo   ?? date('Y-m-d'),
                ],
                'sla' => $data,
            ]);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter métricas de SLA', $e);
        }
    }
}
