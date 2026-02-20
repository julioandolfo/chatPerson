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
