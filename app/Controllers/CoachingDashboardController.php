<?php
/**
 * Controller CoachingDashboardController
 * Dashboard de Analytics e Métricas de Coaching
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Auth;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Database;
use App\Services\CoachingMetricsService;
use App\Services\CoachingLearningService;
use App\Models\CoachingAnalyticsSummary;
use App\Models\CoachingConversationImpact;
use App\Models\User;

class CoachingDashboardController
{
    /**
     * Dashboard Principal - Visão Geral
     */
    public function index(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        // Se não for admin/supervisor, mostrar apenas seus próprios dados
        $agentId = null;
        if (!in_array($userRole, [1, 2, 3])) { // 1=Super Admin, 2=Admin, 3=Supervisor
            $agentId = $userId;
        }
        
        // Período selecionado
        $period = Request::get('period', 'week');
        $agentFilter = Request::get('agent_id');
        
        if ($agentFilter && in_array($userRole, [1, 2, 3])) {
            $agentId = (int)$agentFilter;
        }
        
        // Buscar dashboard completo
        $dashboard = CoachingMetricsService::getDashboardSummary($agentId, $period);
        
        // Buscar ranking de agentes (apenas para admins/supervisores)
        $ranking = [];
        if (in_array($userRole, [1, 2, 3])) {
            $ranking = CoachingAnalyticsSummary::getRanking('weekly', null, 10);
        }
        
        // Buscar top conversas com impacto
        $topConversations = CoachingConversationImpact::getTopImpact(10, $agentId);
        
        // Buscar todos os agentes para filtro (apenas admins)
        $agents = [];
        if (in_array($userRole, [1, 2, 3])) {
            $sql = "SELECT id, name FROM users WHERE role_id >= 4 AND status = 'active' ORDER BY name ASC";
            $agents = Database::fetchAll($sql);
        }
        
        // Estatísticas globais do período
        $periodStart = match($period) {
            'today' => date('Y-m-d'),
            'week' => date('Y-m-d', strtotime('monday this week')),
            'month' => date('Y-m-01'),
            default => date('Y-m-d', strtotime('monday this week'))
        };
        
        $globalStats = CoachingAnalyticsSummary::getGlobalStats(
            $period === 'today' ? 'daily' : ($period === 'month' ? 'monthly' : 'weekly'),
            $periodStart
        );
        
        // Buscar conversas analisadas (primeiras 10)
        $analyzedConversations = CoachingMetricsService::getAnalyzedConversations($agentId, $period, 1, 10);
        
        Response::view('coaching/dashboard', [
            'title' => 'Dashboard de Coaching',
            'dashboard' => $dashboard,
            'ranking' => $ranking,
            'topConversations' => $topConversations,
            'agents' => $agents,
            'globalStats' => $globalStats,
            'analyzedConversations' => $analyzedConversations,
            'selectedPeriod' => $period,
            'selectedAgent' => $agentId,
            'canViewAll' => in_array($userRole, [1, 2, 3])
        ]);
    }
    
    /**
     * API: Buscar mais conversas analisadas (paginação AJAX)
     * Também suporta buscar uma conversa específica via conversation_id
     */
    public function getAnalyzedConversationsAjax(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        // Se buscar conversa específica
        $conversationId = Request::get('conversation_id');
        if ($conversationId) {
            // Buscar conversa específica com todas as métricas
            $sql = "SELECT 
                c.id,
                c.agent_id,
                c.contact_id,
                c.status,
                c.channel,
                c.created_at,
                c.updated_at,
                c.resolved_at,
                
                -- Dados do agente
                u.name as agent_name,
                u.avatar as agent_avatar,
                
                -- Dados do contato
                ct.name as contact_name,
                ct.phone as contact_phone,
                
                -- Coaching Impact
                cci.total_hints,
                cci.hints_helpful,
                cci.hints_not_helpful,
                cci.suggestions_used,
                cci.conversation_outcome,
                cci.sales_value,
                cci.performance_improvement_score,
                cci.avg_response_time_before,
                cci.avg_response_time_after,
                
                -- Performance Analysis
                apa.overall_score,
                apa.detailed_analysis,
                apa.improvement_suggestions,
                apa.strengths,
                apa.weaknesses,
                apa.proactivity_score,
                apa.objection_handling_score,
                apa.rapport_score,
                apa.closing_techniques_score,
                apa.qualification_score,
                apa.clarity_score,
                apa.value_proposition_score,
                apa.response_time_score,
                apa.follow_up_score,
                apa.professionalism_score
                
            FROM conversations c
            LEFT JOIN users u ON c.agent_id = u.id
            LEFT JOIN contacts ct ON c.contact_id = ct.id
            LEFT JOIN coaching_conversation_impact cci ON c.id = cci.conversation_id
            LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
            WHERE c.id = ?";
            
            $conversation = Database::fetch($sql, [$conversationId]);
            
            if ($conversation) {
                // Buscar hints
                $hintsSql = "SELECT 
                        id,
                        hint_type,
                        hint_text,
                        suggestions,
                        feedback,
                        viewed_at,
                        created_at
                    FROM realtime_coaching_hints
                    WHERE conversation_id = ?
                    ORDER BY created_at DESC";
                
                $conversation['coaching_hints'] = Database::fetchAll($hintsSql, [$conversationId]);
                
                // Parse JSON fields
                $conversation['strengths'] = $conversation['strengths'] 
                    ? json_decode($conversation['strengths'], true) 
                    : [];
                $conversation['weaknesses'] = $conversation['weaknesses'] 
                    ? json_decode($conversation['weaknesses'], true) 
                    : [];
                $conversation['improvement_suggestions'] = $conversation['improvement_suggestions'] 
                    ? json_decode($conversation['improvement_suggestions'], true) 
                    : [];
                
                // Formatar valores
                $conversation['sales_value_formatted'] = number_format((float)($conversation['sales_value'] ?? 0), 2, ',', '.');
                $conversation['overall_score_formatted'] = number_format((float)($conversation['overall_score'] ?? 0), 2);
                $conversation['performance_improvement_score_formatted'] = number_format((float)($conversation['performance_improvement_score'] ?? 0), 2);
                
                // Status badge
                $conversation['status_badge'] = match($conversation['status']) {
                    'open' => ['class' => 'success', 'text' => 'Aberta'],
                    'pending' => ['class' => 'warning', 'text' => 'Pendente'],
                    'resolved' => ['class' => 'primary', 'text' => 'Resolvida'],
                    'closed' => ['class' => 'secondary', 'text' => 'Fechada'],
                    default => ['class' => 'light', 'text' => ucfirst($conversation['status'])]
                };
                
                // Outcome badge
                $conversation['outcome_badge'] = match($conversation['conversation_outcome']) {
                    'converted' => ['class' => 'success', 'text' => '✓ Convertida', 'icon' => 'check-circle'],
                    'closed' => ['class' => 'primary', 'text' => 'Fechada', 'icon' => 'check'],
                    'escalated' => ['class' => 'warning', 'text' => 'Escalada', 'icon' => 'arrow-up'],
                    'abandoned' => ['class' => 'danger', 'text' => 'Abandonada', 'icon' => 'cross-circle'],
                    default => ['class' => 'light', 'text' => $conversation['conversation_outcome'] ?? 'N/A', 'icon' => 'question']
                };
                
                Response::json([
                    'success' => true,
                    'data' => [
                        'conversations' => [$conversation],
                        'total' => 1,
                        'page' => 1,
                        'per_page' => 1,
                        'total_pages' => 1,
                        'has_more' => false
                    ]
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }
            return;
        }
        
        // Busca normal com paginação
        $agentId = null;
        if (!in_array($userRole, [1, 2, 3])) {
            $agentId = $userId;
        }
        
        $agentFilter = Request::get('agent_id');
        if ($agentFilter && in_array($userRole, [1, 2, 3])) {
            $agentId = (int)$agentFilter;
        }
        
        $period = Request::get('period', 'week');
        $page = (int)Request::get('page', 1);
        $perPage = (int)Request::get('per_page', 10);
        
        $result = CoachingMetricsService::getAnalyzedConversations($agentId, $period, $page, $perPage);
        
        Response::json([
            'success' => true,
            'data' => $result
        ]);
    }
    
    /**
     * Performance Detalhada de um Agente
     */
    public function agentPerformance(?int $agentId = null): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        // Se não forneceu agentId, usar o próprio
        if (!$agentId) {
            $agentId = $userId;
        }
        
        // Verificar permissão (apenas admin/supervisor pode ver outros agentes)
        if ($agentId !== $userId && !in_array($userRole, [1, 2, 3])) {
            Response::json(['error' => 'Sem permissão para ver outros agentes'], 403);
            return;
        }
        
        // Buscar dados do agente
        $agent = User::find($agentId);
        if (!$agent) {
            Response::json(['error' => 'Agente não encontrado'], 404);
            return;
        }
        
        // Período
        $period = Request::get('period', 'week');
        
        // Calcular datas baseado no período
        $periodStart = match($period) {
            'today' => date('Y-m-d'),
            'week' => date('Y-m-d', strtotime('monday this week')),
            'month' => date('Y-m-01'),
            default => date('Y-m-d', strtotime('monday this week'))
        };
        $periodEnd = date('Y-m-d') . ' 23:59:59';
        
        // Dashboard do agente (coaching)
        $dashboard = CoachingMetricsService::getDashboardSummary($agentId, $period);
        
        // Histórico de performance (últimas 4 semanas)
        $periodType = $period === 'today' ? 'daily' : ($period === 'month' ? 'monthly' : 'weekly');
        $history = CoachingAnalyticsSummary::getAgentHistory($agentId, $periodType, 30);
        
        // Velocidade de aprendizado
        $learningSpeed = CoachingMetricsService::getLearningSpeed($agentId);
        
        // Conversas com impacto deste agente
        $conversations = CoachingConversationImpact::getByAgent($agentId, null, null, 20);
        
        // Estatísticas de impacto
        $impactStats = CoachingConversationImpact::getAgentImpactStats($agentId);
        
        // ==========================================
        // NOVAS MÉTRICAS - Dashboard Completo
        // ==========================================
        
        // Métricas de atendimento (conversas, SLA, tempos)
        $agentMetrics = \App\Services\DashboardService::getAgentMetrics($agentId, $periodStart, $periodEnd);
        
        // Performance do agente (AgentPerformanceService)
        $performanceStats = \App\Services\AgentPerformanceService::getPerformanceStats($agentId, $periodStart, $periodEnd);
        
        // Estatísticas de disponibilidade
        $availabilityStats = [];
        try {
            $availabilityStats = \App\Services\AvailabilityService::getAvailabilityStats($agentId, $periodStart, $periodEnd);
        } catch (\Exception $e) {
            error_log("Erro ao obter estatísticas de disponibilidade: " . $e->getMessage());
        }
        
        // Métricas de conversão (WooCommerce - se for vendedor)
        $conversionMetrics = [];
        try {
            $conversionMetrics = \App\Services\AgentConversionService::getConversionMetrics($agentId, $periodStart, str_replace(' 23:59:59', '', $periodEnd));
        } catch (\Exception $e) {
            error_log("Erro ao obter métricas de conversão: " . $e->getMessage());
        }
        
        // Configurações de SLA
        $slaSettings = \App\Services\ConversationSettingsService::getSettings()['sla'] ?? [];
        
        Response::view('coaching/agent-performance', [
            'title' => "Performance - {$agent['name']}",
            'agent' => $agent,
            'dashboard' => $dashboard,
            'history' => $history,
            'learningSpeed' => $learningSpeed,
            'conversations' => $conversations,
            'impactStats' => $impactStats,
            'selectedPeriod' => $period,
            // Novas métricas
            'agentMetrics' => $agentMetrics,
            'performanceStats' => $performanceStats,
            'availabilityStats' => $availabilityStats,
            'conversionMetrics' => $conversionMetrics,
            'slaSettings' => $slaSettings,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd
        ]);
    }
    
    /**
     * Conversas com Maior Impacto
     */
    public function topConversations(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        $agentId = null;
        if (!in_array($userRole, [1, 2, 3])) {
            $agentId = $userId;
        }
        
        $agentFilter = Request::get('agent_id');
        if ($agentFilter && in_array($userRole, [1, 2, 3])) {
            $agentId = (int)$agentFilter;
        }
        
        $limit = (int)Request::get('limit', 50);
        
        $conversations = CoachingConversationImpact::getTopImpact($limit, $agentId);
        
        Response::view('coaching/top-conversations', [
            'title' => 'Conversas com Maior Impacto',
            'conversations' => $conversations,
            'selectedAgent' => $agentId
        ]);
    }
    
    /**
     * API: Obter dados do dashboard (JSON)
     */
    public function getDashboardData(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        $agentId = Request::get('agent_id');
        $period = Request::get('period', 'week');
        
        // Verificar permissão
        if ($agentId && $agentId != $userId && !in_array($userRole, [1, 2, 3])) {
            Response::json(['error' => 'Sem permissão'], 403);
            return;
        }
        
        $agentId = $agentId ? (int)$agentId : null;
        
        $dashboard = CoachingMetricsService::getDashboardSummary($agentId, $period);
        
        Response::json([
            'success' => true,
            'data' => $dashboard
        ]);
    }
    
    /**
     * API: Obter histórico de performance (para gráficos)
     */
    public function getPerformanceHistory(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        $agentId = Request::get('agent_id');
        $period = Request::get('period', 'weekly');
        $limit = (int)Request::get('limit', 30);
        
        // Verificar permissão
        if ($agentId && $agentId != $userId && !in_array($userRole, [1, 2, 3])) {
            Response::json(['error' => 'Sem permissão'], 403);
            return;
        }
        
        $agentId = $agentId ? (int)$agentId : null;
        
        if (!$agentId) {
            Response::json(['error' => 'agent_id obrigatório'], 400);
            return;
        }
        
        $history = CoachingAnalyticsSummary::getAgentHistory($agentId, $period, $limit);
        
        // Formatar para gráficos
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Taxa de Aceitação (%)',
                    'data' => [],
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension' => 0.1
                ],
                [
                    'label' => 'Hints Recebidos',
                    'data' => [],
                    'borderColor' => 'rgb(54, 162, 235)',
                    'tension' => 0.1
                ]
            ]
        ];
        
        foreach (array_reverse($history) as $item) {
            $chartData['labels'][] = date('d/m', strtotime($item['period_start']));
            
            $rate = $item['total_hints_received'] > 0 
                ? round(($item['total_hints_helpful'] / $item['total_hints_received']) * 100, 1) 
                : 0;
            
            $chartData['datasets'][0]['data'][] = $rate;
            $chartData['datasets'][1]['data'][] = (int)$item['total_hints_received'];
        }
        
        Response::json([
            'success' => true,
            'data' => $chartData
        ]);
    }
    
    /**
     * Export CSV
     */
    public function exportCSV(): void
    {
        Permission::abortIfCannot('coaching.view');
        
        $user = Auth::user();
        $userId = $user['id'];
        $userRole = $user['role_id'] ?? $user['role'] ?? 4;
        
        $agentId = Request::get('agent_id');
        $period = Request::get('period', 'week');
        
        if (!in_array($userRole, [1, 2, 3])) {
            $agentId = $userId;
        }
        
        $agentId = $agentId ? (int)$agentId : null;
        
        // Buscar dados
        $dashboard = CoachingMetricsService::getDashboardSummary($agentId, $period);
        
        // Gerar CSV
        $filename = "coaching-metrics-{$period}-" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        
        $output = fopen('php://output', 'w');
        
        // Cabeçalho
        fputcsv($output, ['Métrica', 'Valor', 'Status', 'Meta']);
        
        // Taxa de Aceitação
        $acceptance = $dashboard['acceptance_rate'];
        fputcsv($output, [
            'Taxa de Aceitação',
            $acceptance['acceptance_rate'] . '%',
            $acceptance['status'],
            $acceptance['target'] . '%'
        ]);
        
        fputcsv($output, [
            'Total de Hints',
            $acceptance['total_hints'],
            '',
            ''
        ]);
        
        fputcsv($output, [
            'Hints Úteis',
            $acceptance['helpful_hints'],
            '',
            ''
        ]);
        
        // ROI
        $roi = $dashboard['roi'];
        fputcsv($output, [
            'ROI',
            $roi['roi_percentage'] . '%',
            $roi['status'],
            $roi['target'] . '%'
        ]);
        
        fputcsv($output, [
            'Custo Total',
            'R$ ' . number_format($roi['total_cost'], 2, ',', '.'),
            '',
            ''
        ]);
        
        fputcsv($output, [
            'Retorno Total',
            'R$ ' . number_format($roi['total_return'], 2, ',', '.'),
            '',
            ''
        ]);
        
        // Impacto na Conversão
        $conversion = $dashboard['conversion_impact'];
        fputcsv($output, [
            'Melhoria na Conversão',
            $conversion['improvement_percentage'] . '%',
            $conversion['status'],
            $conversion['target'] . '%'
        ]);
        
        fputcsv($output, [
            'Taxa com Coaching',
            $conversion['with_coaching']['conversion_rate'] . '%',
            '',
            ''
        ]);
        
        fputcsv($output, [
            'Taxa sem Coaching',
            $conversion['without_coaching']['conversion_rate'] . '%',
            '',
            ''
        ]);
        
        fclose($output);
        exit;
    }
}
