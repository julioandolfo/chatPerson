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
            $slaSettings = json_decode($conversationSettings, true) ?: [];
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
            'slaSettings' => $slaSettings
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
}
