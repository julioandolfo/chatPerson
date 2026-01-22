<?php
/**
 * Controller AgentPerformanceController
 * Análise de performance de vendedores
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Helpers\Auth;
use App\Services\AgentPerformanceAnalysisService;
use App\Services\PerformanceReportService;
use App\Services\GamificationService;
use App\Services\CoachingService;
use App\Services\BestPracticesService;
use App\Services\DashboardService;
use App\Services\AvailabilityService;
use App\Services\AgentConversionService;
use App\Services\AgentPerformanceService;
use App\Services\GoalService;
use App\Services\CoachingMetricsService;
use App\Models\User;
use App\Models\Setting;

class AgentPerformanceController
{
    /**
     * Dashboard principal
     */
    public function index(): void
    {
        Permission::abortIfCannot('agent_performance.view.all');
        
        $user = Auth::user();
        $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        // Ranking de agentes
        $ranking = AgentPerformanceAnalysisService::getAgentsRanking($dateFrom, $dateTo, 10);
        
        // Estatísticas gerais
        $stats = AgentPerformanceAnalysisService::getOverallStats($dateFrom, $dateTo);
        
        // Relatório do time
        $teamReport = PerformanceReportService::generateTeamReport($dateFrom, $dateTo);
        
        Response::view('agent-performance/index', [
            'ranking' => $ranking,
            'stats' => $stats,
            'teamReport' => $teamReport,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }
    
    /**
     * Performance de um agente específico
     */
    public function agent(): void
    {
        $agentId = (int)Request::get('id');
        $user = Auth::user();
        
        // Se não forneceu agentId, usar o próprio
        if (!$agentId) {
            $agentId = $user['id'];
        }
        
        // Verificar permissão: pode ver o próprio OU ser admin para ver outros
        if ($agentId !== $user['id'] && !Permission::can('agent_performance.view.all')) {
            Permission::abortIfCannot('agent_performance.view.all');
        }
        
        // Buscar dados do agente
        $agent = User::find($agentId);
        if (!$agent) {
            Response::redirect('/agent-performance', 'Agente não encontrado', 'error');
            return;
        }
        
        $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        // Relatório do agente (dimensões de coaching)
        $report = PerformanceReportService::generateAgentReport($agentId, $dateFrom, $dateTo);
        
        // Badges
        $badges = GamificationService::getAgentBadges($agentId);
        $badgeStats = GamificationService::getBadgeStats($agentId);
        
        // Metas
        $goals = CoachingService::checkGoalsProgress($agentId);
        
        // ====== MÉTRICAS ADICIONAIS DO DASHBOARD ======
        
        // Métricas de atendimento do agente
        $agentMetrics = DashboardService::getAgentDetailedMetrics($agentId, $dateFrom, $dateTo);
        
        // Estatísticas de performance do AgentPerformanceService
        $performanceStats = AgentPerformanceService::getPerformanceStats($agentId, $dateFrom, $dateTo);
        
        // Estatísticas de disponibilidade
        $availabilityStats = AvailabilityService::getAvailabilityStats($agentId, $dateFrom, $dateTo);
        
        // Métricas de conversão (se habilitado WooCommerce)
        $conversionMetrics = [];
        try {
            $conversionMetrics = AgentConversionService::getConversionMetrics($agentId, $dateFrom, $dateTo);
        } catch (\Exception $e) {
            // WooCommerce pode não estar configurado
        }
        
        // Configurações de SLA
        $slaSettings = [];
        $conversationSettings = Setting::get('conversation_settings');
        if ($conversationSettings) {
            if (is_array($conversationSettings)) {
                $slaSettings = $conversationSettings;
            } else {
                $slaSettings = json_decode($conversationSettings, true) ?: [];
            }
        }
        
        // Conversas analisadas com métricas de coaching
        $analyzedConversations = [];
        try {
            $analyzedConversations = CoachingMetricsService::getAnalyzedConversations(
                $agentId, 
                'month', // período baseado no filtro
                1, 
                10
            );
        } catch (\Exception $e) {
            // Tabelas de coaching podem não existir
        }
        
        // Metas e alertas do agente
        $goalsSummary = [];
        $goalAlerts = [];
        try {
            $goalsSummary = GoalService::getAgentGoalsDetailed($agentId);
            $goalAlerts = GoalService::getGoalAlerts($agentId);
        } catch (\Exception $e) {
            // Sistema de metas pode não estar configurado
        }
        
        Response::view('agent-performance/agent', [
            'report' => $report,
            'badges' => $badges,
            'badgeStats' => $badgeStats,
            'goals' => $goals,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            // Novas métricas
            'agent' => $agent,
            'agentMetrics' => $agentMetrics,
            'performanceStats' => $performanceStats,
            'availabilityStats' => $availabilityStats,
            'conversionMetrics' => $conversionMetrics,
            'slaSettings' => $slaSettings,
            'analyzedConversations' => $analyzedConversations,
            'goalsSummary' => $goalsSummary,
            'goalAlerts' => $goalAlerts
        ]);
    }
    
    /**
     * Ranking completo
     */
    public function ranking(): void
    {
        Permission::abortIfCannot('agent_performance.view.all');
        
        $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        $dimension = Request::get('dimension', 'overall');
        
        if ($dimension === 'overall') {
            $ranking = AgentPerformanceAnalysisService::getAgentsRanking($dateFrom, $dateTo, 50);
        } else {
            $ranking = \App\Models\AgentPerformanceAnalysis::getTopPerformersInDimension($dimension, 50, $dateFrom, $dateTo);
        }
        
        Response::view('agent-performance/ranking', [
            'ranking' => $ranking,
            'dimension' => $dimension,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }
    
    /**
     * Análise de conversa específica
     */
    public function conversation(): void
    {
        $conversationId = (int)Request::get('id');
        $user = Auth::user();
        
        // Buscar análise
        $report = PerformanceReportService::generateConversationReport($conversationId);
        
        if (!$report) {
            Response::redirect('/agent-performance', 'Análise não encontrada', 'error');
            return;
        }
        
        // Verificar permissão: pode ver o próprio OU ser admin para ver outros
        if ($report['analysis']['agent_id'] !== $user['id'] && !Permission::can('agent_performance.view.all')) {
            Permission::abortIfCannot('agent_performance.view.all');
        }
        
        Response::view('agent-performance/conversation', [
            'report' => $report
        ]);
    }
    
    /**
     * Analisar conversa (sob demanda)
     */
    public function analyze(): void
    {
        Permission::abortIfCannot('agent_performance.analyze');
        
        $conversationId = (int)Request::post('conversation_id');
        $force = (bool)Request::post('force', false);
        
        try {
            $analysis = AgentPerformanceAnalysisService::analyzeConversation($conversationId, $force);
            
            if ($analysis) {
                Response::json([
                    'success' => true,
                    'message' => 'Análise concluída com sucesso!',
                    'analysis' => $analysis
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Não foi possível analisar esta conversa'
                ], 400);
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger::sla("getSLABreachedConversations:done agent_id={$agentId} type={$type} total=" . ($totalRows ?? count($conversations)));
            Response::json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Biblioteca de melhores práticas
     */
    public function bestPractices(): void
    {
        Permission::abortIfCannot('agent_performance.best_practices');
        
        $category = Request::get('category', 'all');
        
        if ($category === 'all') {
            $practices = BestPracticesService::getFeatured(20);
        } else {
            $practices = BestPracticesService::getByCategory($category, 20);
        }
        
        $categories = BestPracticesService::getCategories();
        
        Response::view('agent-performance/best-practices', [
            'practices' => $practices,
            'categories' => $categories,
            'selectedCategory' => $category
        ]);
    }
    
    /**
     * Ver prática específica
     */
    public function viewPractice(): void
    {
        Permission::abortIfCannot('agent_performance.best_practices');
        
        $practiceId = (int)Request::get('id');
        $practice = \App\Models\AgentPerformanceBestPractice::find($practiceId);
        
        if (!$practice) {
            Response::redirect('/agent-performance/best-practices', 'Prática não encontrada', 'error');
            return;
        }
        
        // Marcar como visualizado
        BestPracticesService::markAsViewed($practiceId);
        
        // Buscar análise completa
        $analysis = \App\Models\AgentPerformanceAnalysis::find($practice['analysis_id']);
        $conversation = \App\Models\Conversation::find($practice['conversation_id']);
        $agent = \App\Models\User::find($practice['agent_id']);
        
        Response::view('agent-performance/practice-detail', [
            'practice' => $practice,
            'analysis' => $analysis,
            'conversation' => $conversation,
            'agent' => $agent
        ]);
    }
    
    /**
     * Votar em prática
     */
    public function voteHelpful(): void
    {
        Permission::abortIfCannot('agent_performance.best_practices');
        
        $practiceId = (int)Request::post('practice_id');
        
        try {
            BestPracticesService::addHelpfulVote($practiceId);
            Response::json(['success' => true]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::sla("getSLABreachedConversations:error msg=" . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Metas e coaching
     */
    public function goals(): void
    {
        $agentId = (int)Request::get('agent_id', Auth::user()['id']);
        $user = Auth::user();
        
        // Verificar permissão
        if ($agentId !== $user['id']) {
            Permission::abortIfCannot('agent_performance.goals.manage');
        } else {
            Permission::abortIfCannot('agent_performance.goals.view');
        }
        
        $goals = \App\Models\AgentPerformanceGoal::getAgentGoals($agentId);
        $progress = CoachingService::checkGoalsProgress($agentId);
        $agent = \App\Models\User::find($agentId);
        
        Response::view('agent-performance/goals', [
            'goals' => $goals,
            'progress' => $progress,
            'agent' => $agent,
            'canManage' => Permission::can('agent_performance.goals.manage')
        ]);
    }
    
    /**
     * Criar meta
     */
    public function createGoal(): void
    {
        Permission::abortIfCannot('agent_performance.goals.manage');
        
        $agentId = (int)Request::post('agent_id');
        $dimension = Request::post('dimension');
        $targetScore = (float)Request::post('target_score');
        $startDate = Request::post('start_date');
        $endDate = Request::post('end_date');
        $feedback = Request::post('feedback');
        
        // Validações
        if (!$agentId || !$dimension || !$targetScore || !$endDate) {
            Response::json([
                'success' => false,
                'message' => 'Preencha todos os campos obrigatórios'
            ], 400);
            return;
        }
        
        if ($targetScore < 0 || $targetScore > 5) {
            Response::json([
                'success' => false,
                'message' => 'A nota alvo deve estar entre 0 e 5'
            ], 400);
            return;
        }
        
        try {
            $goalId = CoachingService::createGoal(
                $agentId,
                $dimension,
                $targetScore,
                $endDate,
                Auth::user()['id'],
                $feedback,
                $startDate
            );
            
            if ($goalId) {
                Response::json([
                    'success' => true,
                    'message' => 'Meta criada com sucesso!',
                    'goal_id' => $goalId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Não foi possível criar a meta'
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Comparar agentes
     */
    public function compare(): void
    {
        Permission::abortIfCannot('agent_performance.view.all');
        
        $agentIds = Request::get('agents', []);
        if (is_string($agentIds)) {
            $agentIds = explode(',', $agentIds);
        }
        $agentIds = array_map('intval', array_filter($agentIds));
        
        $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        $comparison = [];
        if (!empty($agentIds)) {
            $comparison = PerformanceReportService::compareAgents($agentIds, $dateFrom, $dateTo);
        }
        
        // Listar todos os agentes para seleção
        $allAgents = \App\Helpers\Database::fetchAll(
            "SELECT id, name FROM users WHERE role IN ('agent', 'supervisor', 'admin') AND status = 'active' ORDER BY name"
        );
        
        Response::view('agent-performance/compare', [
            'comparison' => $comparison,
            'selectedAgents' => $agentIds,
            'allAgents' => $allAgents,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }
    
    /**
     * API: Obter dados para gráficos
     */
    public function chartData(): void
    {
        Permission::abortIfCannot('agent_performance.view.all');
        
        $type = Request::get('type');
        $agentId = (int)Request::get('agent_id', 0);
        $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Request::get('date_to', date('Y-m-d'));
        
        try {
            $data = [];
            
            switch ($type) {
                case 'agent_evolution':
                    $report = PerformanceReportService::generateAgentReport($agentId, $dateFrom, $dateTo);
                    $data = $report['evolution'] ?? [];
                    break;
                    
                case 'team_averages':
                    $teamReport = PerformanceReportService::generateTeamReport($dateFrom, $dateTo);
                    $data = $teamReport['team_averages'] ?? [];
                    break;
                    
                default:
                    Response::json(['success' => false, 'message' => 'Tipo inválido'], 400);
                    return;
            }
            
            Response::json(['success' => true, 'data' => $data]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Obter conversas com SLA excedido do agente
     */
    public function getSLABreachedConversations(): void
    {
        try {
            $agentId = (int)(Request::get('agent_id') ?? 0);
            $dateFrom = Request::get('date_from', date('Y-m-d', strtotime('-30 days')));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            $type = Request::get('type', 'first'); // first | ongoing
            $page = max(1, (int)(Request::get('page') ?? 1));
            $perPage = (int)(Request::get('per_page') ?? 20);
            if ($perPage < 5) $perPage = 5;
            if ($perPage > 100) $perPage = 100;
            $offset = ($page - 1) * $perPage;
            
            \App\Helpers\Logger::sla("getSLABreachedConversations:start agent_id={$agentId} type={$type} page={$page} per_page={$perPage}");
            
            if (!$agentId) {
                Response::json(['success' => false, 'message' => 'ID do agente não fornecido'], 400);
                return;
            }
            
            // Buscar configurações de SLA
            $settings = \App\Services\ConversationSettingsService::getSettings();
            $slaMinutes = $settings['sla']['first_response_time'] ?? 15;
            $slaOngoingMinutes = $settings['sla']['ongoing_response_time'] ?? $slaMinutes;
            
            // Buscar conversas do agente com SLA excedido
            // ⚠️ IMPORTANTE: esta lista usa o mesmo critério do DashboardService::getAgentMetrics
            // (primeira mensagem do cliente -> primeira resposta do agente), sem working hours.
            if ($type === 'ongoing') {
                $slaOngoingSeconds = $slaOngoingMinutes * 60;
                $countSql = "SELECT COUNT(DISTINCT c.id) as total
                        FROM conversations c
                        INNER JOIN (
                            SELECT 
                                m1.conversation_id,
                                MAX(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at)) as max_response_seconds
                            FROM messages m1
                            INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                                AND m2.sender_type = 'agent'
                                AND m2.sender_id = ?
                                AND m2.created_at > m1.created_at
                                AND m2.created_at = (
                                    SELECT MIN(m3.created_at)
                                    FROM messages m3
                                    WHERE m3.conversation_id = m1.conversation_id
                                    AND m3.sender_type = 'agent'
                                    AND m3.sender_id = ?
                                    AND m3.created_at > m1.created_at
                                )
                            WHERE m1.sender_type = 'contact'
                            GROUP BY m1.conversation_id
                        ) mr ON mr.conversation_id = c.id
                        WHERE EXISTS (
                            SELECT 1 FROM conversation_assignments ca
                            WHERE ca.conversation_id = c.id
                            AND ca.agent_id = ?
                            AND ca.assigned_at >= ?
                            AND ca.assigned_at <= ?
                        )
                        AND mr.max_response_seconds > ?";
                $countResult = \App\Helpers\Database::fetch($countSql, [
                    $agentId,
                    $agentId,
                    $agentId,
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                    $slaOngoingSeconds
                ]);
                $totalRows = (int)($countResult['total'] ?? 0);
                
                $sql = "SELECT 
                            c.id, c.created_at, c.first_response_at, c.first_human_response_at,
                            c.status, c.priority, c.reassignment_count, c.updated_at,
                            ct.name as contact_name, ct.phone as contact_phone,
                            u.name as agent_name,
                            mr.max_response_seconds
                        FROM conversations c
                        LEFT JOIN contacts ct ON c.contact_id = ct.id
                        LEFT JOIN users u ON c.agent_id = u.id
                        INNER JOIN (
                            SELECT 
                                m1.conversation_id,
                                MAX(TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at)) as max_response_seconds
                            FROM messages m1
                            INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                                AND m2.sender_type = 'agent'
                                AND m2.sender_id = ?
                                AND m2.created_at > m1.created_at
                                AND m2.created_at = (
                                    SELECT MIN(m3.created_at)
                                    FROM messages m3
                                    WHERE m3.conversation_id = m1.conversation_id
                                    AND m3.sender_type = 'agent'
                                    AND m3.sender_id = ?
                                    AND m3.created_at > m1.created_at
                                )
                            WHERE m1.sender_type = 'contact'
                            GROUP BY m1.conversation_id
                        ) mr ON mr.conversation_id = c.id
                        WHERE EXISTS (
                            SELECT 1 FROM conversation_assignments ca
                            WHERE ca.conversation_id = c.id
                            AND ca.agent_id = ?
                            AND ca.assigned_at >= ?
                            AND ca.assigned_at <= ?
                        )
                        AND mr.max_response_seconds > ?
                        ORDER BY mr.max_response_seconds DESC
                        LIMIT {$perPage} OFFSET {$offset}";
                
                $conversations = \App\Helpers\Database::fetchAll($sql, [
                    $agentId,
                    $agentId,
                    $agentId,
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                    $slaOngoingSeconds
                ]);
            } else {
                $slaFirstResponseSeconds = $slaMinutes * 60;
                $countSql = "SELECT COUNT(DISTINCT c.id) as total
                        FROM conversations c
                        LEFT JOIN (
                            SELECT conversation_id, MIN(created_at) as first_contact_at
                            FROM messages
                            WHERE sender_type = 'contact'
                            GROUP BY conversation_id
                        ) mc ON mc.conversation_id = c.id
                        LEFT JOIN (
                            SELECT conversation_id, MIN(created_at) as first_agent_at
                            FROM messages
                            WHERE sender_type = 'agent'
                            AND sender_id = ?
                            GROUP BY conversation_id
                        ) ma ON ma.conversation_id = c.id
                        WHERE EXISTS (
                            SELECT 1 FROM conversation_assignments ca
                            WHERE ca.conversation_id = c.id
                            AND ca.agent_id = ?
                            AND ca.assigned_at >= ?
                            AND ca.assigned_at <= ?
                        )
                        AND mc.first_contact_at IS NOT NULL
                        AND (
                            ma.first_agent_at IS NULL
                            OR TIMESTAMPDIFF(SECOND, mc.first_contact_at, ma.first_agent_at) > ?
                        )";
                $countResult = \App\Helpers\Database::fetch($countSql, [
                    $agentId,
                    $agentId,
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                    $slaFirstResponseSeconds
                ]);
                $totalRows = (int)($countResult['total'] ?? 0);
                
                $sql = "SELECT 
                            c.id, c.created_at, c.first_response_at, c.first_human_response_at,
                            c.status, c.priority, c.reassignment_count, c.updated_at,
                            ct.name as contact_name, ct.phone as contact_phone,
                            u.name as agent_name,
                            mc.first_contact_at,
                            ma.first_agent_at,
                            TIMESTAMPDIFF(SECOND, mc.first_contact_at, COALESCE(ma.first_agent_at, NOW())) as response_seconds
                        FROM conversations c
                        LEFT JOIN contacts ct ON c.contact_id = ct.id
                        LEFT JOIN users u ON c.agent_id = u.id
                        LEFT JOIN (
                            SELECT conversation_id, MIN(created_at) as first_contact_at
                            FROM messages
                            WHERE sender_type = 'contact'
                            GROUP BY conversation_id
                        ) mc ON mc.conversation_id = c.id
                        LEFT JOIN (
                            SELECT conversation_id, MIN(created_at) as first_agent_at
                            FROM messages
                            WHERE sender_type = 'agent'
                            AND sender_id = ?
                            GROUP BY conversation_id
                        ) ma ON ma.conversation_id = c.id
                        WHERE EXISTS (
                            SELECT 1 FROM conversation_assignments ca
                            WHERE ca.conversation_id = c.id
                            AND ca.agent_id = ?
                            AND ca.assigned_at >= ?
                            AND ca.assigned_at <= ?
                        )
                        AND mc.first_contact_at IS NOT NULL
                        AND (
                            ma.first_agent_at IS NULL
                            OR TIMESTAMPDIFF(SECOND, mc.first_contact_at, ma.first_agent_at) > ?
                        )
                        ORDER BY mc.first_contact_at DESC
                        LIMIT {$perPage} OFFSET {$offset}";

                $conversations = \App\Helpers\Database::fetchAll($sql, [
                    $agentId,
                    $agentId,
                    $dateFrom . ' 00:00:00',
                    $dateTo . ' 23:59:59',
                    $slaFirstResponseSeconds
                ]);
            }

            // Enriquecer dados com informações adicionais (mesma base do dashboard)
            foreach ($conversations as &$conv) {
                if ($type === 'ongoing') {
                    $elapsedMinutes = $this->calculateOngoingMaxResponseMinutes(
                        (int)$conv['id'],
                        $settings,
                        $agentId,
                        $conv['status'] ?? null
                    );
                    $slaBaseMinutes = $slaOngoingMinutes;
                } else {
                    $responseSeconds = (int)($conv['response_seconds'] ?? 0);
                    $elapsedMinutes = $responseSeconds > 0 ? round($responseSeconds / 60, 1) : 0;
                    $slaBaseMinutes = $slaMinutes;
                }

                $conv['elapsed_minutes'] = $elapsedMinutes;
                $conv['sla_minutes'] = $slaBaseMinutes;
                $conv['exceeded_by'] = max(0, round($elapsedMinutes - $slaBaseMinutes, 1));
                $conv['percentage'] = $slaBaseMinutes > 0 
                    ? round(($elapsedMinutes / $slaBaseMinutes) * 100, 1)
                    : 0;
                
                // Status visual
                if ($type === 'first' && empty($conv['first_agent_at'])) {
                    $conv['status_label'] = 'Sem resposta';
                    $conv['status_class'] = 'danger';
                } elseif ($conv['status'] === 'closed' || $conv['status'] === 'resolved') {
                    $conv['status_label'] = 'Fechada';
                    $conv['status_class'] = 'secondary';
                } else {
                    $conv['status_label'] = $type === 'ongoing' ? 'Resposta lenta' : 'Respondida fora do SLA';
                    $conv['status_class'] = 'warning';
                }
            }
            
            Response::json([
                'success' => true,
                'conversations' => $conversations,
                'total' => $totalRows ?? count($conversations),
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => isset($totalRows) ? (int)ceil($totalRows / $perPage) : 1,
                'sla_minutes' => $type === 'ongoing' ? $slaOngoingMinutes : $slaMinutes,
                'type' => $type,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Detalhes do SLA excedido (intervalos e mensagens)
     */
    public function getSLABreachedDetails(): void
    {
        try {
            $conversationId = (int)(Request::get('conversation_id') ?? 0);
            $type = Request::get('type', 'first'); // first | ongoing
            $agentId = (int)(Request::get('agent_id') ?? 0);
            
            if (!$conversationId) {
                Response::json(['success' => false, 'message' => 'ID da conversa não fornecido'], 400);
                return;
            }
            
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            $settings = \App\Services\ConversationSettingsService::getSettings();
            $slaMinutes = $settings['sla']['first_response_time'] ?? 15;
            $slaOngoingMinutes = $settings['sla']['ongoing_response_time'] ?? $slaMinutes;
            
            if ($type === 'ongoing') {
                $intervals = $this->buildOngoingIntervals($conversationId, $settings, $agentId, $conversation['status'] ?? null);
                $exceeded = array_values(array_filter($intervals, function ($item) use ($slaOngoingMinutes) {
                    return ($item['minutes'] ?? 0) > $slaOngoingMinutes;
                }));
                
                Response::json([
                    'success' => true,
                    'conversation_id' => $conversationId,
                    'type' => 'ongoing',
                    'sla_minutes' => $slaOngoingMinutes,
                    'intervals' => $exceeded,
                    'total' => count($exceeded),
                    'delay_enabled' => $settings['sla']['message_delay_enabled'] ?? true,
                    'delay_minutes' => $settings['sla']['message_delay_minutes'] ?? 1,
                    'working_hours_enabled' => $settings['sla']['working_hours_enabled'] ?? false
                ]);
                return;
            }
            
            // SLA de 1ª resposta
            $firstContact = \App\Helpers\Database::fetch(
                "SELECT id, created_at, content FROM messages
                 WHERE conversation_id = ? AND sender_type = 'contact'
                 ORDER BY created_at ASC LIMIT 1",
                [$conversationId]
            );
            
            if ($agentId > 0) {
                $firstAgent = \App\Helpers\Database::fetch(
                    "SELECT id, created_at, content, sender_id FROM messages
                     WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?
                     ORDER BY created_at ASC LIMIT 1",
                    [$conversationId, $agentId]
                );
            } else {
                $firstAgent = \App\Helpers\Database::fetch(
                    "SELECT id, created_at, content, sender_id FROM messages
                     WHERE conversation_id = ? AND sender_type = 'agent'
                     ORDER BY created_at ASC LIMIT 1",
                    [$conversationId]
                );
            }
            
            $intervals = [];
            if ($firstContact && $firstAgent) {
                $start = new \DateTime($firstContact['created_at']);
                $end = new \DateTime($firstAgent['created_at']);
                $minutes = $this->calculateMinutesDiff(
                    $start,
                    $end,
                    $settings['sla']['working_hours_enabled'] ?? false
                );
                
                $agentName = $this->getUserNameById((int)($firstAgent['sender_id'] ?? 0));
                
                $intervals[] = [
                    'contact_time' => $firstContact['created_at'],
                    'contact_preview' => mb_substr($firstContact['content'] ?? '', 0, 120),
                    'agent_time' => $firstAgent['created_at'],
                    'agent_name' => $agentName,
                    'agent_preview' => mb_substr($firstAgent['content'] ?? '', 0, 120),
                    'minutes' => round($minutes, 1),
                    'exceeded_by' => max(0, round($minutes - $slaMinutes, 1))
                ];
            }
            
            Response::json([
                'success' => true,
                'conversation_id' => $conversationId,
                'type' => 'first',
                'sla_minutes' => $slaMinutes,
                'intervals' => $intervals,
                'total' => count($intervals),
                'working_hours_enabled' => $settings['sla']['working_hours_enabled'] ?? false
            ]);
            
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Calcular o maior tempo de resposta (SLA de respostas) por conversa.
     * Considera delay configurado e horário de atendimento quando habilitado.
     */
    private function calculateOngoingMaxResponseMinutes(int $conversationId, array $settings, int $agentId, ?string $status = null): float
    {
        $intervals = $this->buildOngoingIntervals($conversationId, $settings, $agentId, $status);
        if (!$intervals) {
            return 0;
        }
        
        $max = 0.0;
        foreach ($intervals as $item) {
            $max = max($max, (float)($item['minutes'] ?? 0));
        }
        
        return round($max, 1);
    }

    /**
     * Construir intervalos de resposta (cliente -> agente) para SLA contínuo.
     */
    private function buildOngoingIntervals(int $conversationId, array $settings, int $agentId, ?string $status = null): array
    {
        $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
        $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
        $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
        
        if (!$delayEnabled) {
            $delayMinutes = 0;
        }
        
        $messages = \App\Helpers\Database::fetchAll(
            "SELECT sender_type, sender_id, created_at, content
             FROM messages
             WHERE conversation_id = ?
             AND sender_type IN ('contact', 'agent')
             ORDER BY created_at ASC",
            [$conversationId]
        );
        
        if (!$messages) {
            return [];
        }
        
        $intervals = [];
        $lastAgentAt = null;
        $lastAgentId = null;
        $pendingContactAt = null;
        $pendingContactContent = null;
        
        foreach ($messages as $msg) {
            $currentAt = new \DateTime($msg['created_at']);
            
            if ($msg['sender_type'] === 'agent') {
                if ($agentId > 0 && (int)($msg['sender_id'] ?? 0) !== $agentId) {
                    continue;
                }
                if ($pendingContactAt) {
                    $minutes = $this->calculateMinutesDiff($pendingContactAt, $currentAt, $useWorkingHours);
                    $agentName = $this->getUserNameById((int)($msg['sender_id'] ?? 0));
                    
                    $intervals[] = [
                        'contact_time' => $pendingContactAt->format('Y-m-d H:i:s'),
                        'contact_preview' => mb_substr($pendingContactContent ?? '', 0, 120),
                        'agent_time' => $msg['created_at'],
                        'agent_name' => $agentName,
                        'agent_preview' => mb_substr($msg['content'] ?? '', 0, 120),
                        'minutes' => round($minutes, 1),
                    ];
                    
                    $pendingContactAt = null;
                    $pendingContactContent = null;
                }
                
                $lastAgentAt = $currentAt;
                $lastAgentId = (int)($msg['sender_id'] ?? 0);
                continue;
            }
            
            if ($msg['sender_type'] === 'contact') {
                if (!$lastAgentAt) {
                    continue;
                }
                
                $diffSinceAgent = ($currentAt->getTimestamp() - $lastAgentAt->getTimestamp()) / 60;
                if ($delayMinutes > 0 && $diffSinceAgent < $delayMinutes) {
                    continue;
                }
                
                if (!$pendingContactAt) {
                    $pendingContactAt = $currentAt;
                    $pendingContactContent = $msg['content'] ?? '';
                }
            }
        }
        
        // Se ficou pendente e a conversa está aberta, calcular até agora
        if ($pendingContactAt && in_array($status, ['open', 'pending'], true)) {
            $now = new \DateTime();
            $minutes = $this->calculateMinutesDiff($pendingContactAt, $now, $useWorkingHours);
            $agentName = $this->getUserNameById($lastAgentId ?? 0);
            
            $intervals[] = [
                'contact_time' => $pendingContactAt->format('Y-m-d H:i:s'),
                'contact_preview' => mb_substr($pendingContactContent ?? '', 0, 120),
                'agent_time' => null,
                'agent_name' => $agentName,
                'agent_preview' => null,
                'minutes' => round($minutes, 1),
                'pending' => true
            ];
        }
        
        return $intervals;
    }

    /**
     * Obter nome do usuário por ID (cache simples em memória)
     */
    private function getUserNameById(int $userId): string
    {
        static $cache = [];
        if ($userId <= 0) {
            return 'Agente';
        }
        if (isset($cache[$userId])) {
            return $cache[$userId];
        }
        
        $row = \App\Helpers\Database::fetch("SELECT name FROM users WHERE id = ? LIMIT 1", [$userId]);
        $cache[$userId] = $row['name'] ?? 'Agente';
        return $cache[$userId];
    }

    /**
     * Diferença em minutos entre duas datas, usando horário comercial se habilitado.
     */
    private function calculateMinutesDiff(\DateTime $start, \DateTime $end, bool $useWorkingHours): float
    {
        if ($useWorkingHours) {
            return (float)\App\Helpers\WorkingHoursCalculator::calculateMinutes($start, $end);
        }
        
        return max(0, ($end->getTimestamp() - $start->getTimestamp()) / 60);
    }
}
