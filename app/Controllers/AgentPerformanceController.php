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
        $dateFrom = Request::get('date_from', date('Y-m-01'));
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
        
        $dateFrom = Request::get('date_from', date('Y-m-01'));
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
        
        // Métricas de conversão (se habilitado WooCommerce) - com detalhes de iniciador
        $conversionMetrics = [];
        try {
            $conversionMetrics = AgentConversionService::getDetailedConversionMetrics($agentId, $dateFrom, $dateTo);
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
        
        $dateFrom = Request::get('date_from', date('Y-m-01'));
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
        
        $dateFrom = Request::get('date_from', date('Y-m-01'));
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
        $dateFrom = Request::get('date_from', date('Y-m-01'));
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
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d'));
            $type = Request::get('type', 'first'); // first | ongoing
            $page = max(1, (int)(Request::get('page') ?? 1));
            $perPage = (int)(Request::get('per_page') ?? 20);
            if ($perPage < 5) $perPage = 5;
            if ($perPage > 100) $perPage = 100;
            $offset = ($page - 1) * $perPage;
            
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

            // Verificar se working hours está habilitado
            $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
            
            // Enriquecer dados com informações adicionais
            // IMPORTANTE: Recalcula usando working hours e filtra conversas dentro do SLA
            // TAMBÉM: Considera quando o agente foi desatribuído (transferiu conversa)
            $filteredConversations = [];
            
            foreach ($conversations as &$conv) {
                $convId = (int)$conv['id'];
                
                // Verificar se cliente respondeu ao BOT
                // Se o cliente não respondeu após a última mensagem do bot, não conta SLA
                if (!$this->hasClientRespondedToBot($convId)) {
                    continue; // Cliente não interagiu após bot - não conta SLA
                }
                
                // Buscar período em que o agente estava atribuído a esta conversa
                $assignmentPeriod = $this->getAgentAssignmentPeriod($convId, $agentId);
                $agentAssignedAt = $assignmentPeriod['assigned_at'] ?? null;
                $agentUnassignedAt = $assignmentPeriod['unassigned_at'] ?? null; // null = ainda atribuído
                
                if ($type === 'ongoing') {
                    $elapsedMinutes = $this->calculateOngoingMaxResponseMinutes(
                        $convId,
                        $settings,
                        $agentId,
                        $conv['status'] ?? null,
                        $agentUnassignedAt // Passar limite de tempo
                    );
                    $slaBaseMinutes = $slaOngoingMinutes;
                } else {
                    // Para tipo 'first', também considerar período de atribuição
                    $startTime = !empty($conv['first_contact_at']) ? new \DateTime($conv['first_contact_at']) : null;
                    
                    if (!$startTime) {
                        continue; // Sem mensagem do cliente
                    }
                    
                    // Se agente foi atribuído após a primeira mensagem do cliente, usar momento da atribuição
                    if ($agentAssignedAt) {
                        $assignedTime = new \DateTime($agentAssignedAt);
                        if ($assignedTime > $startTime) {
                            $startTime = $assignedTime;
                        }
                    }
                    
                    // Determinar fim: resposta do agente, transferência, ou agora
                    if (!empty($conv['first_agent_at'])) {
                        $endTime = new \DateTime($conv['first_agent_at']);
                    } elseif ($agentUnassignedAt) {
                        // Agente transferiu sem responder - usar momento da transferência
                        $endTime = new \DateTime($agentUnassignedAt);
                    } else {
                        // Ainda atribuído, sem resposta
                        $endTime = new \DateTime();
                    }
                    
                    // Calcular minutos
                    if ($useWorkingHours) {
                        $elapsedMinutes = (float)\App\Helpers\WorkingHoursCalculator::calculateMinutes($startTime, $endTime);
                    } else {
                        $elapsedMinutes = max(0, ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60);
                    }
                    
                    $slaBaseMinutes = $slaMinutes;
                    
                    // Se o agente transferiu antes de exceder o SLA, não mostrar
                    if ($agentUnassignedAt && empty($conv['first_agent_at'])) {
                        if ($elapsedMinutes <= $slaBaseMinutes) {
                            continue; // Transferiu dentro do SLA
                        }
                        // Marcar como transferido
                        $conv['status_label'] = 'Transferido s/ resposta';
                        $conv['status_class'] = 'warning';
                    }
                }

                // Filtrar: só incluir se realmente excedeu o SLA
                if ($elapsedMinutes <= $slaBaseMinutes) {
                    continue; // Dentro do SLA quando considerando working hours
                }

                $conv['elapsed_minutes'] = round($elapsedMinutes, 1);
                $conv['sla_minutes'] = $slaBaseMinutes;
                $conv['exceeded_by'] = max(0, round($elapsedMinutes - $slaBaseMinutes, 1));
                $conv['percentage'] = $slaBaseMinutes > 0 
                    ? round(($elapsedMinutes / $slaBaseMinutes) * 100, 1)
                    : 0;
                
                // Status visual (se não já definido)
                if (!isset($conv['status_label'])) {
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
                
                $filteredConversations[] = $conv;
            }
            
            $conversations = $filteredConversations;
            
            // Nota: O total é aproximado porque o filtro com working hours acontece no PHP
            // Para uma contagem exata, seria necessário processar todas as conversas
            $actualTotal = count($conversations);
            
            Response::json([
                'success' => true,
                'conversations' => $conversations,
                'total' => $actualTotal,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => max(1, (int)ceil($actualTotal / $perPage)),
                'sla_minutes' => $type === 'ongoing' ? $slaOngoingMinutes : $slaMinutes,
                'type' => $type,
                'working_hours_enabled' => $useWorkingHours,
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
     * @param string|null $untilTime Limite de tempo (quando agente foi desatribuído)
     */
    private function calculateOngoingMaxResponseMinutes(int $conversationId, array $settings, int $agentId, ?string $status = null, ?string $untilTime = null): float
    {
        $intervals = $this->buildOngoingIntervals($conversationId, $settings, $agentId, $status, $untilTime);
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
     * IMPORTANTE: Considera apenas mensagens dentro dos períodos de atribuição do agente
     * @param string|null $untilTime Limite de tempo (quando agente foi desatribuído) - deprecated, agora usa períodos
     */
    private function buildOngoingIntervals(int $conversationId, array $settings, int $agentId, ?string $status = null, ?string $untilTime = null): array
    {
        $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
        $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
        $useWorkingHours = $settings['sla']['working_hours_enabled'] ?? false;
        
        if (!$delayEnabled) {
            $delayMinutes = 0;
        }
        
        // Buscar todos os períodos de atribuição deste agente
        $assignmentPeriods = $this->getAllAgentAssignmentPeriods($conversationId, $agentId);
        
        // Se não há períodos de atribuição, não há SLA para calcular
        if (empty($assignmentPeriods)) {
            return [];
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
        $pendingContactPeriodEnd = null; // Fim do período quando a msg do cliente foi recebida
        
        foreach ($messages as $msg) {
            $currentAt = new \DateTime($msg['created_at']);
            $msgTimeStr = $msg['created_at'];
            
            if ($msg['sender_type'] === 'agent') {
                // Só considerar mensagens do agente específico
                if ($agentId > 0 && (int)($msg['sender_id'] ?? 0) !== $agentId) {
                    continue;
                }
                
                // Agente respondeu - fechar intervalo pendente
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
                    $pendingContactPeriodEnd = null;
                }
                
                $lastAgentAt = $currentAt;
                $lastAgentId = (int)($msg['sender_id'] ?? 0);
                continue;
            }
            
            if ($msg['sender_type'] === 'contact') {
                // Verificar se mensagem está dentro de um período de atribuição do agente
                if (!$this->isMessageInAgentPeriod($msgTimeStr, $assignmentPeriods)) {
                    continue; // Mensagem fora do período - não conta para este agente
                }
                
                if (!$lastAgentAt) {
                    continue;
                }
                
                // Verificar delay mínimo desde última resposta do agente
                $diffSinceAgent = ($currentAt->getTimestamp() - $lastAgentAt->getTimestamp()) / 60;
                if ($delayMinutes > 0 && $diffSinceAgent < $delayMinutes) {
                    continue;
                }
                
                if (!$pendingContactAt) {
                    $pendingContactAt = $currentAt;
                    $pendingContactContent = $msg['content'] ?? '';
                    $pendingContactPeriodEnd = $this->getPeriodEndForMessage($msgTimeStr, $assignmentPeriods);
                }
            }
        }
        
        // Se ficou pendente, calcular até:
        // 1. Momento da transferência (fim do período) se agente foi desatribuído
        // 2. Agora, se conversa ainda está aberta e agente ainda atribuído
        if ($pendingContactAt) {
            $shouldCalculate = false;
            $endTime = null;
            $wasTransferred = false;
            
            if ($pendingContactPeriodEnd) {
                // Agente foi desatribuído - calcular até momento da transferência
                $endTime = new \DateTime($pendingContactPeriodEnd);
                $shouldCalculate = true;
                $wasTransferred = true;
            } elseif (in_array($status, ['open', 'pending'], true)) {
                // Conversa aberta e agente ainda atribuído - calcular até agora
                $endTime = new \DateTime();
                $shouldCalculate = true;
            }
            
            if ($shouldCalculate && $endTime) {
                $minutes = $this->calculateMinutesDiff($pendingContactAt, $endTime, $useWorkingHours);
                $agentName = $this->getUserNameById($lastAgentId ?? 0);
                
                $intervals[] = [
                    'contact_time' => $pendingContactAt->format('Y-m-d H:i:s'),
                    'contact_preview' => mb_substr($pendingContactContent ?? '', 0, 120),
                    'agent_time' => null,
                    'agent_name' => $agentName,
                    'agent_preview' => null,
                    'minutes' => round($minutes, 1),
                    'pending' => true,
                    'transferred' => $wasTransferred
                ];
            }
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
    
    /**
     * Verificar se o cliente respondeu ao BOT/agente
     * Retorna true se existe mensagem do cliente APÓS uma mensagem do bot/agente
     * Isso evita contar SLA para conversas onde o cliente ainda não interagiu após o menu do bot
     */
    private function hasClientRespondedToBot(int $conversationId): bool
    {
        // Buscar a última mensagem do bot/agente
        $lastAgentMessage = \App\Helpers\Database::fetch(
            "SELECT created_at 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'agent'
             ORDER BY created_at DESC 
             LIMIT 1",
            [$conversationId]
        );
        
        // Se não há mensagem de agente/bot, verificar se há mensagem de cliente
        if (!$lastAgentMessage) {
            // Sem resposta de bot ainda - verificar se há pelo menos uma msg de cliente
            $hasContact = \App\Helpers\Database::fetch(
                "SELECT 1 FROM messages WHERE conversation_id = ? AND sender_type = 'contact' LIMIT 1",
                [$conversationId]
            );
            return (bool)$hasContact;
        }
        
        // Verificar se existe mensagem do cliente APÓS a última do agente
        $clientAfterAgent = \App\Helpers\Database::fetch(
            "SELECT 1 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'contact'
             AND created_at > ?
             LIMIT 1",
            [$conversationId, $lastAgentMessage['created_at']]
        );
        
        return (bool)$clientAfterAgent;
    }
    
    /**
     * Obter período em que um agente estava atribuído a uma conversa
     * Retorna o ÚLTIMO período de atribuição (mais relevante para SLA atual)
     */
    private function getAgentAssignmentPeriod(int $conversationId, int $agentId): array
    {
        $periods = $this->getAllAgentAssignmentPeriods($conversationId, $agentId);
        
        if (empty($periods)) {
            return ['assigned_at' => null, 'unassigned_at' => null];
        }
        
        // Retornar o último período (mais recente)
        return end($periods);
    }
    
    /**
     * Obter TODOS os períodos em que um agente estava atribuído a uma conversa
     * Retorna array de ['assigned_at' => datetime, 'unassigned_at' => datetime|null]
     */
    private function getAllAgentAssignmentPeriods(int $conversationId, int $agentId): array
    {
        // Buscar todos os assignments ordenados por data
        $allAssignments = \App\Helpers\Database::fetchAll(
            "SELECT agent_id, assigned_at 
             FROM conversation_assignments 
             WHERE conversation_id = ?
             ORDER BY assigned_at ASC",
            [$conversationId]
        );
        
        if (empty($allAssignments)) {
            return [];
        }
        
        $periods = [];
        $currentPeriodStart = null;
        
        foreach ($allAssignments as $i => $assignment) {
            $isTargetAgent = ((int)$assignment['agent_id'] === $agentId);
            
            if ($isTargetAgent && $currentPeriodStart === null) {
                // Início de um período do agente
                $currentPeriodStart = $assignment['assigned_at'];
            } elseif (!$isTargetAgent && $currentPeriodStart !== null) {
                // Fim de um período do agente (outro agente assumiu)
                $periods[] = [
                    'assigned_at' => $currentPeriodStart,
                    'unassigned_at' => $assignment['assigned_at']
                ];
                $currentPeriodStart = null;
            }
            // Se é o mesmo agente consecutivamente, continua o mesmo período
        }
        
        // Se ainda está em um período aberto, adicionar sem data de fim
        if ($currentPeriodStart !== null) {
            $periods[] = [
                'assigned_at' => $currentPeriodStart,
                'unassigned_at' => null
            ];
        }
        
        return $periods;
    }
    
    /**
     * Verificar se uma mensagem está dentro de algum período de atribuição do agente
     */
    private function isMessageInAgentPeriod(string $messageTime, array $periods): bool
    {
        $msgTime = strtotime($messageTime);
        
        foreach ($periods as $period) {
            $start = strtotime($period['assigned_at']);
            $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
            
            if ($msgTime >= $start && $msgTime <= $end) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obter o fim do período de atribuição para uma mensagem específica
     * Retorna null se o agente ainda está atribuído nesse período
     */
    private function getPeriodEndForMessage(string $messageTime, array $periods): ?string
    {
        $msgTime = strtotime($messageTime);
        
        foreach ($periods as $period) {
            $start = strtotime($period['assigned_at']);
            $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
            
            if ($msgTime >= $start && $msgTime <= $end) {
                return $period['unassigned_at']; // null se ainda atribuído
            }
        }
        
        return null;
    }
}
