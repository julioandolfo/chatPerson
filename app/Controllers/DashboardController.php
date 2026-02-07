<?php
/**
 * Controller do Dashboard
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Database;
use App\Helpers\Permission;
use App\Services\GoalService;

class DashboardController
{
    /**
     * Mostrar dashboard
     */
    public function index(): void
    {
        self::logDash("===== DASHBOARD INDEX INICIADO =====");
        
        // Dashboard é acessível a todos os usuários autenticados
        // Mas podemos verificar permissão específica se necessário
        // Permission::abortIfCannot('dashboard.view');
        
        $userId = \App\Helpers\Auth::id();
        self::logDash("userId={$userId}");
        
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        
        // Lista de agentes com role 'agent' (para filtro padrão e select)
        $agentsList = \App\Models\User::getAgentsWithRoleAgent();
        $defaultAgentIds = array_map(fn($a) => $a['id'], $agentsList);
        
        // Filtro de agentes (array de IDs) - por padrão usa todos os agentes com role 'agent'
        $agentsFilter = \App\Helpers\Request::get('agents', '');
        if (!empty($agentsFilter)) {
            // Usuário selecionou agentes específicos
            $agentIds = array_map('intval', explode(',', $agentsFilter));
        } else {
            // Padrão: todos os agentes com role 'agent'
            $agentIds = $defaultAgentIds;
        }
        
        // Garantir que dateTo inclui o dia inteiro (até 23:59:59)
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        self::logDash("Período: dateFrom={$dateFrom}, dateTo={$dateTo}, agents=" . implode(',', $agentIds));
        
        try {
            // Estatísticas gerais (com filtro de agentes)
            self::logDash("Carregando stats para userId={$userId}, dateFrom={$dateFrom}, dateTo={$dateTo}");
            $generalStats = \App\Services\DashboardService::getGeneralStats($userId, $dateFrom, $dateTo, $agentIds);
            self::logDash("generalStats = " . json_encode($generalStats));
            
            // Estatísticas por setor
            $departmentStats = \App\Services\DashboardService::getDepartmentStats();
            
            // Estatísticas por funil
            $funnelStats = \App\Services\DashboardService::getFunnelStats();
            
            // Top agentes
            $topAgents = \App\Services\DashboardService::getTopAgents($dateFrom, $dateTo, 5);
            self::logDash("topAgents_count=" . count($topAgents));
            
            // Métricas individuais de todos os agentes (para cards)
            $allAgentsMetrics = \App\Services\DashboardService::getAllAgentsMetrics($dateFrom, $dateTo);
            self::logDash("allAgentsMetrics_count=" . count($allAgentsMetrics));
            
            // Métricas de times
            $teamsMetrics = [];
            try {
                if (\App\Helpers\Permission::can('teams.view')) {
                    // Admin: ver todos os times
                    $teamsMetrics = \App\Services\TeamPerformanceService::getTeamsRanking($dateFrom, $dateTo, 10);
                } else {
                    // Agente: ver apenas times aos quais pertence
                    $userTeams = \App\Models\Team::getUserTeams($userId);
                    
                    if (!empty($userTeams)) {
                        foreach ($userTeams as $userTeam) {
                            $teamStats = \App\Services\TeamPerformanceService::getPerformanceStats($userTeam['id'], $dateFrom, $dateTo);
                            if ($teamStats) {
                                $teamsMetrics[] = [
                                    'team_id' => $userTeam['id'],
                                    'team_name' => $userTeam['name'],
                                    'team_color' => $userTeam['color'] ?? '#3F4254',
                                    'total_conversations' => $teamStats['total_conversations'] ?? 0,
                                    'resolved_conversations' => $teamStats['resolved_conversations'] ?? 0,
                                    'avg_first_response_time' => $teamStats['avg_first_response_time'] ?? null,
                                    'avg_resolution_time' => $teamStats['avg_resolution_time'] ?? null,
                                    'satisfaction_rate' => $teamStats['satisfaction_rate'] ?? 0
                                ];
                            }
                        }
                    }
                }
                
                // Adicionar métricas de conversão WooCommerce por time
                foreach ($teamsMetrics as &$team) {
                    $conversionMetrics = self::getTeamConversionMetrics($team['team_id'], $dateFrom, str_replace(' 23:59:59', '', $dateTo));
                    $team['conversion_rate_sales'] = $conversionMetrics['conversion_rate'];
                    $team['total_revenue'] = $conversionMetrics['total_revenue'];
                    $team['avg_ticket'] = $conversionMetrics['avg_ticket'];
                    $team['total_orders'] = $conversionMetrics['total_orders'];
                }
                self::logDash("teamsMetrics_count=" . count($teamsMetrics));
            } catch (\Exception $e) {
                error_log("Erro ao carregar métricas de times: " . $e->getMessage());
            }
            
            // Métricas de conversão WooCommerce
            $conversionRanking = [];
            try {
                $ranking = [];
                
                if (\App\Helpers\Permission::can('conversion.view')) {
                    // Admin: ver ranking completo de todos os vendedores
                    $sellers = \App\Models\User::getSellers();
                    
                    foreach ($sellers as $seller) {
                        $metrics = \App\Services\AgentConversionService::getDetailedConversionMetrics(
                            $seller['id'],
                            $dateFrom,
                            str_replace(' 23:59:59', '', $dateTo)
                        );
                        
                        if ($metrics['total_conversations'] > 0 || $metrics['total_orders'] > 0) {
                            $ranking[] = [
                                'agent_id' => $seller['id'],
                                'agent_name' => $seller['name'],
                                'seller_id' => $seller['woocommerce_seller_id'],
                                'total_conversations' => $metrics['total_conversations'],
                                'conversations_agent_initiated' => $metrics['conversations_agent_initiated'] ?? 0,
                                'conversations_client_initiated' => $metrics['conversations_client_initiated'] ?? 0,
                                'total_orders' => $metrics['total_orders'],
                                'conversion_rate' => $metrics['conversion_rate'],
                                'conversion_rate_client_only' => $metrics['conversion_rate_client_only'] ?? 0,
                                'total_revenue' => $metrics['total_revenue'],
                                'avg_ticket' => $metrics['avg_ticket']
                            ];
                        }
                    }
                } else {
                    // Agente: ver apenas membros dos seus times
                    $userTeams = \App\Models\Team::getUserTeams($userId);
                    $teamMemberIds = [];
                    
                    foreach ($userTeams as $userTeam) {
                        $memberIds = \App\Models\Team::getMemberIds($userTeam['id']);
                        $teamMemberIds = array_merge($teamMemberIds, $memberIds);
                    }
                    
                    // Remover duplicados e incluir o próprio usuário
                    $teamMemberIds = array_unique(array_merge($teamMemberIds, [$userId]));
                    
                    // Buscar métricas apenas dos membros dos times
                    $sellers = \App\Models\User::getSellers();
                    
                    foreach ($sellers as $seller) {
                        // Somente se for membro de algum time do usuário
                        if (in_array($seller['id'], $teamMemberIds)) {
                            $metrics = \App\Services\AgentConversionService::getDetailedConversionMetrics(
                                $seller['id'],
                                $dateFrom,
                                str_replace(' 23:59:59', '', $dateTo)
                            );
                            
                            if ($metrics['total_conversations'] > 0 || $metrics['total_orders'] > 0) {
                                $ranking[] = [
                                    'agent_id' => $seller['id'],
                                    'agent_name' => $seller['name'],
                                    'seller_id' => $seller['woocommerce_seller_id'],
                                    'total_conversations' => $metrics['total_conversations'],
                                    'conversations_agent_initiated' => $metrics['conversations_agent_initiated'] ?? 0,
                                    'conversations_client_initiated' => $metrics['conversations_client_initiated'] ?? 0,
                                    'total_orders' => $metrics['total_orders'],
                                    'conversion_rate' => $metrics['conversion_rate'],
                                    'conversion_rate_client_only' => $metrics['conversion_rate_client_only'] ?? 0,
                                    'total_revenue' => $metrics['total_revenue'],
                                    'avg_ticket' => $metrics['avg_ticket']
                                ];
                            }
                        }
                    }
                }
                
                // Ordenar por taxa de conversão (decrescente)
                usort($ranking, function($a, $b) {
                    return $b['conversion_rate'] <=> $a['conversion_rate'];
                });
                
                // Criar 3 rankings diferentes
                $conversionRanking = $ranking; // Todos para criar os rankings
                self::logDash("conversionRanking_count=" . count($conversionRanking));
            } catch (\Exception $e) {
                error_log("Erro ao carregar métricas de conversão: " . $e->getMessage());
            }
            
            // Rankings de vendas
            $rankingByRevenue = [];
            $rankingByConversion = [];
            $rankingByTicket = [];
            
            if (!empty($conversionRanking)) {
                // Ranking por Faturamento
                $rankingByRevenue = $conversionRanking;
                usort($rankingByRevenue, function($a, $b) {
                    return $b['total_revenue'] <=> $a['total_revenue'];
                });
                $rankingByRevenue = array_slice($rankingByRevenue, 0, 5);
                
                // Ranking por Taxa de Conversão
                $rankingByConversion = $conversionRanking;
                usort($rankingByConversion, function($a, $b) {
                    return $b['conversion_rate'] <=> $a['conversion_rate'];
                });
                $rankingByConversion = array_slice($rankingByConversion, 0, 5);
                
                // Ranking por Ticket Médio
                $rankingByTicket = $conversionRanking;
                usort($rankingByTicket, function($a, $b) {
                    return $b['avg_ticket'] <=> $a['avg_ticket'];
                });
                $rankingByTicket = array_slice($rankingByTicket, 0, 5);
            }
            self::logDash("rankingByRevenue_count=" . count($rankingByRevenue));
            self::logDash("rankingByConversion_count=" . count($rankingByConversion));
            self::logDash("rankingByTicket_count=" . count($rankingByTicket));
            
            // Conversas recentes (apenas 5)
            $recentConversations = \App\Services\DashboardService::getRecentConversations(5);
            
            // Atividade recente
            $recentActivity = \App\Services\DashboardService::getRecentActivity(10);
            
            // Metas do usuário / visão geral
            $canViewAllGoals = \App\Helpers\Permission::can('goals.view');
            $goalsSummary = GoalService::getDashboardSummary($userId);
            $goalsOverview = GoalService::getDashboardGoalsOverview($userId, $canViewAllGoals);
            
            // Métricas de atendimento por agente (padrão: hoje)
            $agentAttendanceMetrics = [];
            try {
                $today = date('Y-m-d');
                $agentAttendanceMetrics = \App\Services\DashboardService::getAgentAttendanceMetrics($today, $today);
                self::logDash("agentAttendanceMetrics_agents_count=" . count($agentAttendanceMetrics['agents'] ?? []));
            } catch (\Exception $e) {
                error_log("Erro ao carregar métricas de atendimento: " . $e->getMessage());
            }
            
            // Estatísticas de ligações API4Com (sem filtro de agente para ver todas)
            $callStats = [];
            $recentCalls = [];
            $callAnalysisStats = [];
            $recentCallAnalyses = [];
            try {
                if (class_exists('\App\Models\Api4ComCall')) {
                    // Não filtrar por agente - mostrar todas as chamadas do período
                    $callStats = \App\Models\Api4ComCall::getStats($dateFrom, str_replace(' 23:59:59', '', $dateTo), null);
                    $recentCalls = \App\Models\Api4ComCall::getRecent(10, null);
                    self::logDash("callStats=" . json_encode($callStats));
                }
                
                // Estatísticas de análise de chamadas
                if (class_exists('\App\Models\Api4ComCallAnalysis')) {
                    $callAnalysisStats = \App\Models\Api4ComCallAnalysis::getStats($dateFrom, str_replace(' 23:59:59', '', $dateTo));
                    $recentCallAnalyses = \App\Models\Api4ComCallAnalysis::getRecent(5);
                    self::logDash("callAnalysisStats=" . json_encode($callAnalysisStats));
                }
            } catch (\Exception $e) {
                error_log("Erro ao carregar estatísticas de ligações: " . $e->getMessage());
            }
            
            self::logDash("Passando dados para view");
            Response::view('dashboard/index', [
                'stats' => $generalStats,
                'departmentStats' => $departmentStats,
                'funnelStats' => $funnelStats,
                'topAgents' => $topAgents,
                'allAgentsMetrics' => $allAgentsMetrics,
                'teamsMetrics' => $teamsMetrics,
                'conversionRanking' => $conversionRanking,
                'rankingByRevenue' => $rankingByRevenue,
                'rankingByConversion' => $rankingByConversion,
                'rankingByTicket' => $rankingByTicket,
                'agentsList' => $agentsList,
                'recentConversations' => $recentConversations,
                'recentActivity' => $recentActivity,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'goalsSummary' => $goalsSummary,
                'goalsOverview' => $goalsOverview,
                'agentAttendanceMetrics' => $agentAttendanceMetrics,
                'callStats' => $callStats,
                'recentCalls' => $recentCalls,
                'callAnalysisStats' => $callAnalysisStats,
                'recentCallAnalyses' => $recentCallAnalyses
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO CRÍTICO: " . $e->getMessage());
            self::logDash("Stack trace: " . $e->getTraceAsString());
            // Fallback para estatísticas básicas
            $stats = [
                'conversations' => [
                    'total' => Database::fetch("SELECT COUNT(*) as total FROM conversations")['total'] ?? 0,
                    'open' => Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE status = 'open'")['total'] ?? 0,
                    'my_total' => Database::fetch("SELECT COUNT(*) as total FROM conversations WHERE agent_id = ?", [$userId])['total'] ?? 0,
                ],
                'agents' => ['total' => 0, 'active' => 0, 'online' => 0],
                'contacts' => ['total' => 0],
                'messages' => ['total' => 0],
                'metrics' => ['resolution_rate' => 0, 'avg_first_response_time' => null]
            ];
            
            Response::view('dashboard/index', [
                'stats' => $stats,
                'departmentStats' => [],
                'funnelStats' => [],
                'topAgents' => [],
                'allAgentsMetrics' => [],
                'teamsMetrics' => [],
                'conversionRanking' => [],
                'rankingByRevenue' => [],
                'rankingByConversion' => [],
                'rankingByTicket' => [],
                'recentConversations' => [],
                'recentActivity' => [],
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'goalsSummary' => [
                    'total_goals' => 0,
                    'achieved' => 0,
                    'in_progress' => 0,
                    'at_risk' => 0,
                    'goals_by_level' => []
                ],
                'goalsOverview' => [],
                'agentAttendanceMetrics' => ['agents' => [], 'totals' => []],
                'callStats' => [],
                'recentCalls' => []
            ]);
        }
    }

    /**
     * Dashboard específico para Inteligência Artificial
     */
    public function aiDashboard(): void
    {
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        
        // Garantir que dateTo inclui o dia inteiro
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        try {
            // Estatísticas gerais (inclui métricas separadas)
            $generalStats = \App\Services\DashboardService::getGeneralStats(null, $dateFrom, $dateTo);
            
            // Métricas de IA extraídas do generalStats
            $aiMetrics = $generalStats['ai_metrics'] ?? [];
            
            // Ranking de agentes de IA
            $aiAgentsRanking = \App\Services\AIAgentPerformanceService::getAIAgentsRanking($dateFrom, $dateTo, 10);
            
            // Comparação IA vs Humanos
            $comparison = \App\Services\AIAgentPerformanceService::getComparisonStats($dateFrom, $dateTo);
            
            // Taxa de cumprimento de SLA separada
            $slaCompliance = \App\Services\SLAMonitoringService::getSLAComplianceRates($dateFrom, $dateTo);
            
            // Métricas de fallback de IA
            $fallbackStats = \App\Services\AIFallbackMonitoringService::getFallbackStats($dateFrom, $dateTo);
            
            // ✨ NOVO: Estatísticas do Assistente IA
            $assistantStats = self::getAIAssistantStats($dateFrom, $dateTo);
            
            Response::view('dashboard/ai-dashboard', [
                'stats' => $generalStats,
                'aiMetrics' => $aiMetrics,
                'aiAgentsRanking' => $aiAgentsRanking,
                'comparison' => $comparison,
                'slaCompliance' => $slaCompliance,
                'fallbackStats' => $fallbackStats,
                'assistantStats' => $assistantStats,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO CRÍTICO AI Dashboard: " . $e->getMessage());
            self::logDash("Stack trace: " . $e->getTraceAsString());
            
            // Fallback com dados vazios
            Response::view('dashboard/ai-dashboard', [
                'stats' => ['metrics' => [], 'ai_metrics' => []],
                'aiMetrics' => [],
                'aiAgentsRanking' => [],
                'comparison' => ['ai' => [], 'human' => []],
                'slaCompliance' => ['general' => [], 'ai' => [], 'human' => []],
                'assistantStats' => [],
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo
            ]);
        }
    }

    /**
     * Obter estatísticas do Assistente IA
     */
    private static function getAIAssistantStats(string $dateFrom, string $dateTo): array
    {
        try {
            $db = \App\Helpers\Database::getInstance();
            
            // Calcular dias para o período
            $days = max(1, ceil((strtotime($dateTo) - strtotime($dateFrom)) / 86400));
            
            // Estatísticas gerais
            $generalSql = "SELECT 
                            COUNT(*) as total_uses,
                            SUM(tokens_used) as total_tokens,
                            SUM(cost) as total_cost,
                            AVG(execution_time_ms) as avg_execution_time,
                            SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_uses,
                            SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_uses,
                            COUNT(DISTINCT user_id) as unique_users,
                            COUNT(DISTINCT conversation_id) as unique_conversations
                        FROM ai_assistant_logs
                        WHERE created_at BETWEEN ? AND ?";
            
            $stmt = $db->prepare($generalSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $general = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Taxa de sucesso
            $totalUses = (int)($general['total_uses'] ?? 0);
            $successRate = $totalUses > 0 ? (((int)$general['successful_uses'] / $totalUses) * 100) : 0;
            
            // Estatísticas por funcionalidade
            $featuresSql = "SELECT 
                                l.feature_key,
                                f.name as feature_name,
                                COUNT(*) as uses,
                                SUM(l.tokens_used) as tokens,
                                SUM(l.cost) as cost,
                                AVG(l.execution_time_ms) as avg_time,
                                SUM(CASE WHEN l.success = 1 THEN 1 ELSE 0 END) as successful,
                                SUM(CASE WHEN l.success = 0 THEN 1 ELSE 0 END) as failed
                            FROM ai_assistant_logs l
                            LEFT JOIN ai_assistant_features f ON l.feature_key = f.feature_key
                            WHERE l.created_at BETWEEN ? AND ?
                            GROUP BY l.feature_key, f.name
                            ORDER BY uses DESC
                            LIMIT 10";
            
            $stmt = $db->prepare($featuresSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $byFeature = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Estatísticas por agente especializado
            $agentsSql = "SELECT 
                            a.id,
                            a.name,
                            a.model,
                            COUNT(*) as uses,
                            SUM(l.tokens_used) as tokens,
                            SUM(l.cost) as cost,
                            AVG(l.execution_time_ms) as avg_time
                        FROM ai_assistant_logs l
                        INNER JOIN ai_agents a ON l.ai_agent_id = a.id
                        WHERE l.created_at BETWEEN ? AND ?
                        AND a.agent_type = 'assistant'
                        GROUP BY a.id, a.name, a.model
                        ORDER BY uses DESC
                        LIMIT 10";
            
            $stmt = $db->prepare($agentsSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $byAgent = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Uso ao longo do tempo (últimos 7 dias)
            $timelineSql = "SELECT 
                                DATE(created_at) as date,
                                COUNT(*) as uses,
                                SUM(tokens_used) as tokens,
                                SUM(cost) as cost
                            FROM ai_assistant_logs
                            WHERE created_at BETWEEN ? AND ?
                            GROUP BY DATE(created_at)
                            ORDER BY date ASC";
            
            $stmt = $db->prepare($timelineSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $timeline = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Top usuários
            $topUsersSql = "SELECT 
                                u.id,
                                u.name,
                                COUNT(*) as uses,
                                SUM(l.cost) as total_cost
                            FROM ai_assistant_logs l
                            INNER JOIN users u ON l.user_id = u.id
                            WHERE l.created_at BETWEEN ? AND ?
                            GROUP BY u.id, u.name
                            ORDER BY uses DESC
                            LIMIT 10";
            
            $stmt = $db->prepare($topUsersSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $topUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Custo por modelo
            $costByModelSql = "SELECT 
                                    a.model,
                                    COUNT(*) as uses,
                                    SUM(l.tokens_used) as tokens,
                                    SUM(l.cost) as cost
                                FROM ai_assistant_logs l
                                INNER JOIN ai_agents a ON l.ai_agent_id = a.id
                                WHERE l.created_at BETWEEN ? AND ?
                                GROUP BY a.model
                                ORDER BY cost DESC";
            
            $stmt = $db->prepare($costByModelSql);
            $stmt->execute([$dateFrom, $dateTo]);
            $costByModel = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return [
                'general' => [
                    'total_uses' => $totalUses,
                    'total_tokens' => (int)($general['total_tokens'] ?? 0),
                    'total_cost' => (float)($general['total_cost'] ?? 0),
                    'avg_execution_time' => (float)($general['avg_execution_time'] ?? 0),
                    'successful_uses' => (int)($general['successful_uses'] ?? 0),
                    'failed_uses' => (int)($general['failed_uses'] ?? 0),
                    'success_rate' => round($successRate, 2),
                    'unique_users' => (int)($general['unique_users'] ?? 0),
                    'unique_conversations' => (int)($general['unique_conversations'] ?? 0),
                    'avg_cost_per_use' => $totalUses > 0 ? round((float)($general['total_cost'] ?? 0) / $totalUses, 4) : 0
                ],
                'by_feature' => $byFeature,
                'by_agent' => $byAgent,
                'timeline' => $timeline,
                'top_users' => $topUsers,
                'cost_by_model' => $costByModel
            ];
        } catch (\Exception $e) {
            \App\Helpers\Logger::error('[Dashboard AI] Erro ao obter estatísticas do Assistente IA: ' . $e->getMessage());
            return [
                'general' => [],
                'by_feature' => [],
                'by_agent' => [],
                'timeline' => [],
                'top_users' => [],
                'cost_by_model' => []
            ];
        }
    }

    /**
     * Obter dados de gráficos (AJAX)
     * ✅ ATUALIZADO: Suporta filtros avançados para conversations_over_time
     */
    public function getChartData(): void
    {
        $chartType = \App\Helpers\Request::get('type', 'conversations_over_time');
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        $groupBy = \App\Helpers\Request::get('group_by', 'day');
        
        // Garantir que dateTo inclui o dia inteiro (até 23:59:59)
        if (!str_contains($dateTo, ':')) {
            $dateTo = $dateTo . ' 23:59:59';
        }
        
        // ✅ NOVO: Capturar filtros adicionais
        $filters = [];
        
        // Filtro por setor
        if ($departmentId = \App\Helpers\Request::get('department_id')) {
            $filters['department_id'] = (int)$departmentId;
        }
        
        // Filtro por times (array)
        if ($teamIdsRaw = \App\Helpers\Request::get('team_ids')) {
            if (is_string($teamIdsRaw)) {
                $teamIdsRaw = json_decode($teamIdsRaw, true) ?: explode(',', $teamIdsRaw);
            }
            $filters['team_ids'] = array_map('intval', array_filter($teamIdsRaw));
        }
        
        // Filtro por agentes (array)
        if ($agentIdsRaw = \App\Helpers\Request::get('agent_ids')) {
            if (is_string($agentIdsRaw)) {
                $agentIdsRaw = json_decode($agentIdsRaw, true) ?: explode(',', $agentIdsRaw);
            }
            $filters['agent_ids'] = array_map('intval', array_filter($agentIdsRaw));
        }
        
        // Filtro por canal
        if ($channel = \App\Helpers\Request::get('channel')) {
            $filters['channel'] = $channel;
        }
        
        // Filtro por funil
        if ($funnelId = \App\Helpers\Request::get('funnel_id')) {
            $filters['funnel_id'] = (int)$funnelId;
        }
        
        // ✅ NOVO: Modo de visualização (aggregated ou comparative)
        $viewMode = \App\Helpers\Request::get('view_mode', 'aggregated');
        $filters['view_mode'] = $viewMode;
        
        self::logDash("getChartData: type={$chartType}, dateFrom={$dateFrom}, dateTo={$dateTo}, viewMode={$viewMode}, filters=" . json_encode($filters));
        
        try {
            $data = [];
            
            switch ($chartType) {
                case 'conversations_over_time':
                    $data = \App\Services\DashboardService::getConversationsOverTime($dateFrom, $dateTo, $groupBy, $filters);
                    self::logDash("getChartData conversations_over_time items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                case 'conversations_by_channel':
                    $data = \App\Services\DashboardService::getConversationsByChannelChart($dateFrom, $dateTo);
                    self::logDash("getChartData conversations_by_channel items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                case 'conversations_by_status':
                    $data = \App\Services\DashboardService::getConversationsByStatusChart($dateFrom, $dateTo);
                    self::logDash("getChartData conversations_by_status items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                case 'agents_performance':
                    $limit = (int)\App\Helpers\Request::get('limit', 10);
                    $data = \App\Services\DashboardService::getAgentsPerformanceChart($dateFrom, $dateTo, $limit);
                    self::logDash("getChartData agents_performance items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                case 'messages_over_time':
                    $data = \App\Services\DashboardService::getMessagesOverTime($dateFrom, $dateTo, $groupBy);
                    self::logDash("getChartData messages_over_time items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                case 'sla_metrics':
                    $data = \App\Services\DashboardService::getSLAMetrics($dateFrom, $dateTo);
                    self::logDash("getChartData sla_metrics items=" . (is_array($data) ? count($data) : 0));
                    break;
                    
                default:
                    self::logDash("getChartData: Tipo inválido - {$chartType}");
                    Response::json(['error' => 'Tipo de gráfico inválido'], 400);
                    return;
            }
            
            self::logDash("getChartData: {$chartType} retornou " . count($data) . " registros");
            
            Response::json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO getChartData: {$chartType} - " . $e->getMessage());
            self::logDash("TRACE getChartData: " . $e->getTraceAsString());
            Response::json([
                'success' => false,
                'error' => 'Erro ao carregar dados do gráfico',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter métricas de atendimento por agente (AJAX)
     * Retorna dados filtrados por período para atualização dinâmica
     */
    public function getAttendanceMetrics(): void
    {
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-d'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d'));
        
        self::logDash("getAttendanceMetrics: dateFrom={$dateFrom}, dateTo={$dateTo}");
        
        try {
            $metrics = \App\Services\DashboardService::getAgentAttendanceMetrics($dateFrom, $dateTo);
            
            self::logDash("getAttendanceMetrics: agents_count=" . count($metrics['agents'] ?? []));
            
            Response::json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            self::logDash("ERRO getAttendanceMetrics: " . $e->getMessage());
            Response::json([
                'success' => false,
                'error' => 'Erro ao carregar métricas de atendimento',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log para arquivo logs/dash.log
     */
    /**
     * Obter métricas de conversão de um time
     */
    private static function getTeamConversionMetrics(int $teamId, string $dateFrom, string $dateTo): array
    {
        try {
            // Buscar membros do time que são vendedores
            $members = \App\Models\Team::getMembers($teamId);
            $sellers = array_filter($members, function($member) {
                return !empty($member['woocommerce_seller_id']);
            });
            
            if (empty($sellers)) {
                return [
                    'total_orders' => 0,
                    'conversion_rate' => 0,
                    'total_revenue' => 0,
                    'avg_ticket' => 0
                ];
            }
            
            $totalOrders = 0;
            $totalRevenue = 0;
            
            foreach ($sellers as $seller) {
                $metrics = \App\Services\AgentConversionService::getConversionMetrics(
                    $seller['id'],
                    $dateFrom,
                    $dateTo
                );
                
                $totalOrders += $metrics['total_orders'];
                $totalRevenue += $metrics['total_revenue'];
            }
            
            $avgTicket = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
            
            // Taxa de conversão baseada no histórico de conversas do time
            $memberIds = array_column($members, 'id');
            $totalConversations = 0;
            
            foreach ($memberIds as $memberId) {
                $totalConversations += \App\Models\ConversationAssignment::countAgentConversations(
                    $memberId,
                    $dateFrom,
                    $dateTo . ' 23:59:59'
                );
            }
            $conversionRate = $totalConversations > 0 
                ? round(($totalOrders / $totalConversations) * 100, 2) 
                : 0;
            
            return [
                'total_orders' => $totalOrders,
                'conversion_rate' => $conversionRate,
                'total_revenue' => $totalRevenue,
                'avg_ticket' => $avgTicket
            ];
        } catch (\Exception $e) {
            error_log("Erro ao calcular métricas de conversão do time: " . $e->getMessage());
            return [
                'total_orders' => 0,
                'conversion_rate' => 0,
                'total_revenue' => 0,
                'avg_ticket' => 0
            ];
        }
    }
    
    private static function logDash(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/dash.log';
        
        // Verificar se pode escrever
        if (!is_writable($logDir)) {
            @chmod($logDir, 0777);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        // Tentar escrever, mas não falhar se não conseguir
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    /**
     * Exportar relatório (PDF/Excel)
     */
    public function exportReport(): void
    {
        $format = \App\Helpers\Request::get('format', 'pdf'); // pdf, excel, csv
        $dateFrom = \App\Helpers\Request::get('date_from', date('Y-m-01'));
        $dateTo = \App\Helpers\Request::get('date_to', date('Y-m-d H:i:s'));
        
        // Por enquanto, retornamos JSON. Implementação completa de PDF/Excel requer bibliotecas adicionais
        $stats = \App\Services\DashboardService::getGeneralStats(null, $dateFrom, $dateTo);
        $topAgents = \App\Services\DashboardService::getTopAgents($dateFrom, $dateTo, 20);
        $departmentStats = \App\Services\DashboardService::getDepartmentStats();
        $funnelStats = \App\Services\DashboardService::getFunnelStats();
        
        if ($format === 'csv') {
            // Exportar CSV simples
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="relatorio_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // BOM para UTF-8
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Cabeçalho
            fputcsv($output, ['Relatório de Dashboard', 'Período: ' . $dateFrom . ' até ' . $dateTo], ';');
            fputcsv($output, [], ';');
            
            // Estatísticas gerais
            fputcsv($output, ['Estatísticas Gerais'], ';');
            fputcsv($output, ['Métrica', 'Valor'], ';');
            fputcsv($output, ['Total de Conversas', $stats['conversations']['total']], ';');
            fputcsv($output, ['Conversas Abertas', $stats['conversations']['open']], ';');
            fputcsv($output, ['Conversas Fechadas', $stats['conversations']['closed']], ';');
            fputcsv($output, ['Taxa de Resolução', $stats['metrics']['resolution_rate'] . '%'], ';');
            fputcsv($output, ['Agentes Online', $stats['agents']['online']], ';');
            fputcsv($output, [], ';');
            
            // Top Agentes
            if (!empty($topAgents)) {
                fputcsv($output, ['Top Agentes'], ';');
                fputcsv($output, ['Nome', 'Total Conversas', 'Fechadas', 'Taxa Resolução'], ';');
                foreach ($topAgents as $agent) {
                    fputcsv($output, [
                        $agent['name'] ?? 'Sem nome',
                        $agent['total_conversations'] ?? 0,
                        $agent['closed_conversations'] ?? 0,
                        ($agent['resolution_rate'] ?? 0) . '%'
                    ], ';');
                }
                fputcsv($output, [], ';');
            }
            
            fclose($output);
            exit;
        } else {
            // Para PDF e Excel, retornamos JSON por enquanto
            // Implementação completa requer bibliotecas como TCPDF/FPDF ou PhpSpreadsheet
            Response::json([
                'success' => true,
                'message' => 'Exportação em ' . strtoupper($format) . ' será implementada em breve',
                'data' => [
                    'stats' => $stats,
                    'top_agents' => $topAgents,
                    'departments' => $departmentStats,
                    'funnels' => $funnelStats
                ]
            ]);
        }
    }
}

