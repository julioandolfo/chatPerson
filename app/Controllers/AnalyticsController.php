<?php
/**
 * Controller AnalyticsController
 * Analytics e relatórios do sistema
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\ConversationSentiment;
use App\Models\Department;
use App\Models\User;
use App\Helpers\Database;
use App\Services\DashboardService;
use App\Services\AgentPerformanceService;
use App\Models\Funnel;
use App\Models\Tag;
use App\Models\AutomationExecution;
use App\Models\Automation;
use App\Models\AIAssistantLog;
use App\Models\AIConversation;

class AnalyticsController
{
    /**
     * Página principal de Analytics (com abas)
     */
    public function index(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        // Obter filtros padrão
        $filters = [
            'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
            'end_date' => Request::get('end_date') ?: date('Y-m-d'),
            'department_id' => Request::get('department_id'),
            'agent_id' => Request::get('agent_id'),
            'channel' => Request::get('channel'),
            'funnel_id' => Request::get('funnel_id'),
            'stage_id' => Request::get('stage_id'),
        ];
        
        // Obter dados para filtros
        $departments = Department::all();
        $agents = Database::fetchAll(
            "SELECT id, name FROM users WHERE status = 'active' AND role IN ('agent', 'admin', 'supervisor') ORDER BY name ASC"
        );
        $funnels = Funnel::whereActive();
        $tags = Tag::all();
        
        Response::view('analytics/index', [
            'filters' => $filters,
            'departments' => $departments,
            'agents' => $agents,
            'funnels' => $funnels,
            'tags' => $tags
        ]);
    }

    /**
     * Página de Analytics de Sentimento (mantida para compatibilidade)
     */
    public function sentiment(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        // Obter filtros
        $filters = [
            'start_date' => Request::get('start_date'),
            'end_date' => Request::get('end_date'),
            'department_id' => Request::get('department_id'),
            'agent_id' => Request::get('agent_id'),
        ];
        
        // Obter dados para filtros
        $departments = Department::all();
        $agents = Database::fetchAll(
            "SELECT id, name FROM users WHERE status = 'active' AND role IN ('agent', 'admin', 'supervisor') ORDER BY name ASC"
        );
        
        Response::view('analytics/sentiment', [
            'filters' => $filters,
            'departments' => $departments,
            'agents' => $agents
        ]);
    }

    /**
     * API: Obter dados de analytics de sentimento (JSON)
     */
    public function getSentimentData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date'),
                'end_date' => Request::get('end_date'),
                'department_id' => Request::get('department_id'),
                'agent_id' => Request::get('agent_id'),
            ];
            
            // Estatísticas gerais
            $stats = ConversationSentiment::getAnalytics($filters);
            
            // Evolução ao longo do tempo (últimos 30 dias)
            $endDate = $filters['end_date'] ? date('Y-m-d', strtotime($filters['end_date'])) : date('Y-m-d');
            $startDate = $filters['start_date'] ? date('Y-m-d', strtotime($filters['start_date'])) : date('Y-m-d', strtotime('-30 days', strtotime($endDate)));
            
            $where = ["DATE(cs.analyzed_at) >= ?", "DATE(cs.analyzed_at) <= ?"];
            $params = [$startDate, $endDate];
            
            if (!empty($filters['department_id'])) {
                $where[] = "c.department_id = ?";
                $params[] = $filters['department_id'];
            }
            
            if (!empty($filters['agent_id'])) {
                $where[] = "c.agent_id = ?";
                $params[] = $filters['agent_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Evolução diária
            $sql = "SELECT 
                        DATE(cs.analyzed_at) as date,
                        COUNT(*) as count,
                        AVG(cs.sentiment_score) as avg_score,
                        SUM(CASE WHEN cs.sentiment_label = 'positive' THEN 1 ELSE 0 END) as positive,
                        SUM(CASE WHEN cs.sentiment_label = 'neutral' THEN 1 ELSE 0 END) as neutral,
                        SUM(CASE WHEN cs.sentiment_label = 'negative' THEN 1 ELSE 0 END) as negative
                    FROM conversation_sentiments cs
                    INNER JOIN conversations c ON cs.conversation_id = c.id
                    WHERE {$whereClause}
                    GROUP BY DATE(cs.analyzed_at)
                    ORDER BY date ASC";
            
            $evolution = Database::fetchAll($sql, $params);
            
            // Top conversas negativas
            $sql = "SELECT 
                        cs.id,
                        cs.conversation_id,
                        cs.sentiment_score,
                        cs.sentiment_label,
                        cs.urgency_level,
                        cs.analyzed_at,
                        c.contact_id,
                        co.name as contact_name,
                        c.agent_id,
                        u.name as agent_name,
                        c.department_id,
                        d.name as department_name
                    FROM conversation_sentiments cs
                    INNER JOIN conversations c ON cs.conversation_id = c.id
                    INNER JOIN contacts co ON c.contact_id = co.id
                    LEFT JOIN users u ON c.agent_id = u.id
                    LEFT JOIN departments d ON c.department_id = d.id
                    WHERE {$whereClause}
                    AND cs.sentiment_label = 'negative'
                    ORDER BY cs.sentiment_score ASC, cs.analyzed_at DESC
                    LIMIT 20";
            
            $negativeConversations = Database::fetchAll($sql, $params);
            
            // Distribuição por sentimento
            $sql = "SELECT 
                        cs.sentiment_label,
                        COUNT(*) as count,
                        AVG(cs.sentiment_score) as avg_score
                    FROM conversation_sentiments cs
                    INNER JOIN conversations c ON cs.conversation_id = c.id
                    WHERE {$whereClause}
                    GROUP BY cs.sentiment_label";
            
            $distribution = Database::fetchAll($sql, $params);
            
            // Distribuição por urgência
            $sql = "SELECT 
                        cs.urgency_level,
                        COUNT(*) as count
                    FROM conversation_sentiments cs
                    INNER JOIN conversations c ON cs.conversation_id = c.id
                    WHERE {$whereClause}
                    AND cs.urgency_level IS NOT NULL
                    GROUP BY cs.urgency_level";
            
            $urgencyDistribution = Database::fetchAll($sql, $params);
            
            Response::json([
                'success' => true,
                'stats' => $stats,
                'evolution' => $evolution,
                'negative_conversations' => $negativeConversations,
                'distribution' => $distribution,
                'urgency_distribution' => $urgencyDistribution
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de conversas (JSON)
     */
    public function getConversationsData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
                'department_id' => Request::get('department_id'),
                'agent_id' => Request::get('agent_id'),
                'channel' => Request::get('channel'),
            ];
            
            // Estatísticas gerais usando DashboardService
            $stats = DashboardService::getGeneralStats(null, $filters['start_date'], $filters['end_date'] . ' 23:59:59');
            
            // Evolução de conversas ao longo do tempo
            $evolution = DashboardService::getConversationsOverTime(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59',
                'day'
            );
            
            // Conversas por status
            $byStatus = DashboardService::getConversationsByStatusChart(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59'
            );
            
            // Conversas por canal
            $byChannel = DashboardService::getConversationsByChannelChart(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59'
            );
            
            // Mensagens ao longo do tempo
            $messagesEvolution = DashboardService::getMessagesOverTime(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59',
                'day'
            );
            
            // SLA Metrics
            $slaMetrics = DashboardService::getSLAMetrics(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59'
            );
            
            // Top agentes
            $topAgents = DashboardService::getTopAgents(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59',
                10
            );
            
            // Estatísticas por setor
            $departmentStats = DashboardService::getDepartmentStats();
            
            Response::json([
                'success' => true,
                'stats' => $stats,
                'evolution' => $evolution,
                'by_status' => $byStatus,
                'by_channel' => $byChannel,
                'messages_evolution' => $messagesEvolution,
                'sla_metrics' => $slaMetrics,
                'top_agents' => $topAgents,
                'department_stats' => $departmentStats
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de agentes (JSON)
     */
    public function getAgentsData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
                'agent_id' => Request::get('agent_id'),
            ];
            
            // Ranking de agentes
            $ranking = AgentPerformanceService::getAgentsRanking(
                $filters['start_date'],
                $filters['end_date'] . ' 23:59:59',
                20
            );
            
            // Se um agente específico foi solicitado, obter stats detalhadas
            $agentDetails = null;
            if (!empty($filters['agent_id'])) {
                $agentDetails = AgentPerformanceService::getPerformanceStats(
                    (int)$filters['agent_id'],
                    $filters['start_date'],
                    $filters['end_date'] . ' 23:59:59'
                );
            }
            
            // Performance comparativa (top 10)
            $top10 = array_slice($ranking, 0, 10);
            
            Response::json([
                'success' => true,
                'ranking' => $ranking,
                'top_10' => $top10,
                'agent_details' => $agentDetails
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de tags (JSON)
     */
    public function getTagsData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
                'department_id' => Request::get('department_id'),
                'agent_id' => Request::get('agent_id'),
            ];
            
            $where = ["c.created_at >= ?", "c.created_at <= ?"];
            $params = [$filters['start_date'], $filters['end_date'] . ' 23:59:59'];
            
            if (!empty($filters['department_id'])) {
                $where[] = "c.department_id = ?";
                $params[] = $filters['department_id'];
            }
            
            if (!empty($filters['agent_id'])) {
                $where[] = "c.agent_id = ?";
                $params[] = $filters['agent_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Tags mais utilizadas
            $sql = "SELECT 
                        t.id,
                        t.name,
                        t.color,
                        COUNT(DISTINCT ct.conversation_id) as usage_count,
                        COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN ct.conversation_id END) as closed_count,
                        AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END) as avg_resolution_hours
                    FROM tags t
                    INNER JOIN conversation_tags ct ON t.id = ct.tag_id
                    INNER JOIN conversations c ON ct.conversation_id = c.id
                    WHERE {$whereClause}
                    GROUP BY t.id, t.name, t.color
                    ORDER BY usage_count DESC
                    LIMIT 20";
            
            $topTags = Database::fetchAll($sql, $params);
            
            // Distribuição de tags ao longo do tempo
            $sql = "SELECT 
                        DATE(c.created_at) as date,
                        t.id as tag_id,
                        t.name as tag_name,
                        COUNT(DISTINCT ct.conversation_id) as count
                    FROM tags t
                    INNER JOIN conversation_tags ct ON t.id = ct.tag_id
                    INNER JOIN conversations c ON ct.conversation_id = c.id
                    WHERE {$whereClause}
                    GROUP BY DATE(c.created_at), t.id, t.name
                    ORDER BY date ASC, count DESC";
            
            $tagsOverTime = Database::fetchAll($sql, $params);
            
            // Tags por status de conversa
            $sql = "SELECT 
                        t.id,
                        t.name,
                        t.color,
                        c.status,
                        COUNT(DISTINCT ct.conversation_id) as count
                    FROM tags t
                    INNER JOIN conversation_tags ct ON t.id = ct.tag_id
                    INNER JOIN conversations c ON ct.conversation_id = c.id
                    WHERE {$whereClause}
                    GROUP BY t.id, t.name, t.color, c.status
                    ORDER BY t.name ASC, c.status ASC";
            
            $tagsByStatus = Database::fetchAll($sql, $params);
            
            // Normalizar valores de topTags
            foreach ($topTags as &$tag) {
                $tag['usage_count'] = (int)($tag['usage_count'] ?? 0);
                $tag['closed_count'] = (int)($tag['closed_count'] ?? 0);
                $tag['avg_resolution_hours'] = round((float)($tag['avg_resolution_hours'] ?? 0), 2);
            }
            
            // Normalizar tags_over_time
            foreach ($tagsOverTime as &$item) {
                $item['count'] = (int)($item['count'] ?? 0);
            }
            
            // Normalizar tags_by_status
            foreach ($tagsByStatus as &$item) {
                $item['count'] = (int)($item['count'] ?? 0);
            }
            
            Response::json([
                'success' => true,
                'top_tags' => $topTags,
                'tags_over_time' => $tagsOverTime,
                'tags_by_status' => $tagsByStatus
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de funil (JSON)
     */
    public function getFunnelData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
                'funnel_id' => Request::get('funnel_id'),
            ];
            
            $where = ["c.created_at >= ?", "c.created_at <= ?"];
            $params = [$filters['start_date'], $filters['end_date'] . ' 23:59:59'];
            
            if (!empty($filters['funnel_id'])) {
                $where[] = "c.funnel_id = ?";
                $params[] = $filters['funnel_id'];
            }
            
            $whereClause = implode(' AND ', $where);
            
            // Conversas por estágio
            $funnelWhere = [];
            $funnelParams = [];
            
            if (!empty($filters['funnel_id'])) {
                $funnelWhere[] = "fs.funnel_id = ?";
                $funnelParams[] = $filters['funnel_id'];
            }
            
            $funnelWhereClause = !empty($funnelWhere) ? 'WHERE ' . implode(' AND ', $funnelWhere) : '';
            
            $sql = "SELECT 
                        fs.id,
                        fs.name,
                        fs.color,
                        fs.position,
                        COUNT(DISTINCT c.id) as conversations_count,
                        COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_count,
                        AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
                            THEN TIMESTAMPDIFF(HOUR, c.created_at, c.resolved_at) END) as avg_time_hours
                    FROM funnel_stages fs
                    LEFT JOIN conversations c ON fs.id = c.funnel_stage_id AND {$whereClause}
                    {$funnelWhereClause}
                    GROUP BY fs.id, fs.name, fs.color, fs.position
                    ORDER BY fs.position ASC";
            
            $stagesData = Database::fetchAll($sql, array_merge($params, $funnelParams));
            
            // Normalizar valores
            foreach ($stagesData as &$stage) {
                $stage['conversations_count'] = (int)($stage['conversations_count'] ?? 0);
                $stage['closed_count'] = (int)($stage['closed_count'] ?? 0);
                $stage['avg_time_hours'] = round((float)($stage['avg_time_hours'] ?? 0), 2);
                $stage['position'] = (int)($stage['position'] ?? 0);
            }
            
            // Taxa de conversão entre estágios (simplificado - apenas estágios com conversas)
            $stageMovements = [];
            
            // Se houver funil específico, podemos adicionar lógica de movimentação aqui
            // Por enquanto, retornamos array vazio pois requer análise de activities
            
            Response::json([
                'success' => true,
                'stages_data' => $stagesData,
                'stage_movements' => $stageMovements
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de automações (JSON)
     */
    public function getAutomationsData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
            ];
            
            // Estatísticas gerais de automações
            $sql = "SELECT 
                        COUNT(*) as total_executions,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                        SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_execution_time_seconds
                    FROM automation_executions
                    WHERE created_at >= ? AND created_at <= ?";
            
            $generalStats = Database::fetch($sql, [$filters['start_date'], $filters['end_date'] . ' 23:59:59']);
            
            // Top automações mais executadas
            $sql = "SELECT 
                        a.id,
                        a.name,
                        COUNT(ae.id) as execution_count,
                        SUM(CASE WHEN ae.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                        SUM(CASE WHEN ae.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                        AVG(TIMESTAMPDIFF(SECOND, ae.started_at, ae.completed_at)) as avg_time_seconds
                    FROM automations a
                    LEFT JOIN automation_executions ae ON a.id = ae.automation_id 
                        AND ae.created_at >= ? AND ae.created_at <= ?
                    GROUP BY a.id, a.name
                    HAVING execution_count > 0
                    ORDER BY execution_count DESC
                    LIMIT 20";
            
            $topAutomations = Database::fetchAll($sql, [$filters['start_date'], $filters['end_date'] . ' 23:59:59']);
            
            // Evolução de execuções ao longo do tempo
            $sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM automation_executions
                    WHERE created_at >= ? AND created_at <= ?
                    GROUP BY DATE(created_at)
                    ORDER BY date ASC";
            
            $evolution = Database::fetchAll($sql, [$filters['start_date'], $filters['end_date'] . ' 23:59:59']);
            
            // Calcular taxa de sucesso e normalizar valores
            $successRate = 0;
            if ($generalStats && $generalStats['total_executions'] > 0) {
                $successRate = round(($generalStats['completed'] / $generalStats['total_executions']) * 100, 2);
            }
            
            // Normalizar stats
            if ($generalStats) {
                $generalStats['total_executions'] = (int)($generalStats['total_executions'] ?? 0);
                $generalStats['completed'] = (int)($generalStats['completed'] ?? 0);
                $generalStats['failed'] = (int)($generalStats['failed'] ?? 0);
                $generalStats['running'] = (int)($generalStats['running'] ?? 0);
                $generalStats['pending'] = (int)($generalStats['pending'] ?? 0);
                $generalStats['avg_execution_time_seconds'] = round((float)($generalStats['avg_execution_time_seconds'] ?? 0), 2);
            }
            
            // Normalizar top automations
            foreach ($topAutomations as &$auto) {
                $auto['execution_count'] = (int)($auto['execution_count'] ?? 0);
                $auto['completed_count'] = (int)($auto['completed_count'] ?? 0);
                $auto['failed_count'] = (int)($auto['failed_count'] ?? 0);
                $auto['avg_time_seconds'] = round((float)($auto['avg_time_seconds'] ?? 0), 2);
            }
            
            // Normalizar evolution
            foreach ($evolution as &$ev) {
                $ev['total'] = (int)($ev['total'] ?? 0);
                $ev['completed'] = (int)($ev['completed'] ?? 0);
                $ev['failed'] = (int)($ev['failed'] ?? 0);
            }
            
            Response::json([
                'success' => true,
                'general_stats' => $generalStats ?: [
                    'total_executions' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'running' => 0,
                    'pending' => 0,
                    'avg_execution_time_seconds' => 0
                ],
                'top_automations' => $topAutomations,
                'evolution' => $evolution,
                'success_rate' => $successRate
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter dados de analytics de IA (JSON)
     */
    public function getAIData(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
            ];
            
            $startDate = $filters['start_date'];
            $endDate = $filters['end_date'] . ' 23:59:59';
            
            // ========================================
            // MÉTRICAS CONSOLIDADAS DE IA
            // ========================================
            
            // 1. AI Conversations (Agentes de IA)
            $aiConvMetrics = Database::fetch("
                SELECT 
                    COUNT(DISTINCT ac.conversation_id) as total,
                    COALESCE(SUM(ac.tokens_used), 0) as tokens,
                    COALESCE(SUM(ac.cost), 0) as cost
                FROM ai_conversations ac
                INNER JOIN conversations c ON c.id = ac.conversation_id
                WHERE c.created_at >= ? AND c.created_at <= ?
            ", [$startDate, $endDate]);
            
            // 2. Sentiment Analysis
            $sentimentMetrics = Database::fetch("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(cs.tokens_used), 0) as tokens,
                    COALESCE(SUM(cs.cost), 0) as cost
                FROM conversation_sentiments cs
                WHERE cs.analyzed_at >= ? AND cs.analyzed_at <= ?
            ", [$startDate, $endDate]);
            
            // 3. Performance Analysis
            $perfMetrics = Database::fetch("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(apa.tokens_used), 0) as tokens,
                    COALESCE(SUM(apa.cost), 0) as cost,
                    AVG(apa.overall_score) as avg_score
                FROM agent_performance_analysis apa
                WHERE apa.analyzed_at >= ? AND apa.analyzed_at <= ?
            ", [$startDate, $endDate]);
            
            // 4. Realtime Coaching
            $coachingMetrics = Database::fetch("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(rch.tokens_used), 0) as tokens,
                    COALESCE(SUM(rch.cost), 0) as cost
                FROM realtime_coaching_hints rch
                WHERE rch.created_at >= ? AND rch.created_at <= ?
            ", [$startDate, $endDate]);
            
            // 5. Audio Transcription (se existir)
            $audioMetrics = ['total' => 0, 'tokens' => 0, 'cost' => 0];
            try {
                $audioMetrics = Database::fetch("
                    SELECT 
                        COUNT(*) as total,
                        COALESCE(SUM(at.tokens_used), 0) as tokens,
                        COALESCE(SUM(at.cost), 0) as cost
                    FROM audio_transcriptions at
                    WHERE at.created_at >= ? AND at.created_at <= ?
                ", [$startDate, $endDate]);
            } catch (\Exception $e) {
                // Tabela não existe - ignorar
            }
            
            // Totais consolidados
            $totalTokens = ($aiConvMetrics['tokens'] ?? 0) + ($sentimentMetrics['tokens'] ?? 0) + 
                          ($perfMetrics['tokens'] ?? 0) + ($coachingMetrics['tokens'] ?? 0) + 
                          ($audioMetrics['tokens'] ?? 0);
                          
            $totalCost = ($aiConvMetrics['cost'] ?? 0) + ($sentimentMetrics['cost'] ?? 0) + 
                        ($perfMetrics['cost'] ?? 0) + ($coachingMetrics['cost'] ?? 0) + 
                        ($audioMetrics['cost'] ?? 0);
            
            // ========================================
            // EVOLUÇÃO DIÁRIA
            // ========================================
            $evolution = Database::fetchAll("
                SELECT 
                    DATE(c.created_at) as date,
                    COUNT(DISTINCT CASE WHEN ac.id IS NOT NULL THEN c.id END) as ai_conversations,
                    COUNT(DISTINCT cs.id) as sentiment_analyses,
                    COUNT(DISTINCT apa.id) as performance_analyses,
                    COUNT(DISTINCT rch.id) as coaching_hints
                FROM conversations c
                LEFT JOIN ai_conversations ac ON c.id = ac.conversation_id
                LEFT JOIN conversation_sentiments cs ON c.id = cs.conversation_id 
                    AND DATE(cs.analyzed_at) = DATE(c.created_at)
                LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
                    AND DATE(apa.analyzed_at) = DATE(c.created_at)
                LEFT JOIN messages m ON c.id = m.conversation_id
                LEFT JOIN realtime_coaching_hints rch ON m.id = rch.message_id
                    AND DATE(rch.created_at) = DATE(c.created_at)
                WHERE c.created_at >= ? AND c.created_at <= ?
                GROUP BY DATE(c.created_at)
                ORDER BY date ASC
            ", [$startDate, $endDate]);
            
            // ========================================
            // TOP AGENTES DE IA
            // ========================================
            $aiAgents = Database::fetchAll("
                SELECT 
                    a.id,
                    a.name,
                    a.model,
                    COUNT(ac.id) as conversations,
                    COALESCE(SUM(ac.tokens_used), 0) as total_tokens,
                    COALESCE(SUM(ac.cost), 0) as total_cost
                FROM ai_agents a
                LEFT JOIN ai_conversations ac ON a.id = ac.ai_agent_id
                    AND ac.created_at >= ? AND ac.created_at <= ?
                GROUP BY a.id, a.name, a.model
                HAVING conversations > 0
                ORDER BY conversations DESC
                LIMIT 10
            ", [$startDate, $endDate]);
            
            // ========================================
            // COACHING HINTS POR TIPO
            // ========================================
            $coachingHints = Database::fetchAll("
                SELECT 
                    rch.hint_type,
                    COUNT(*) as count,
                    COALESCE(SUM(rch.tokens_used), 0) as total_tokens,
                    COALESCE(SUM(rch.cost), 0) as total_cost
                FROM realtime_coaching_hints rch
                WHERE rch.created_at >= ? AND rch.created_at <= ?
                GROUP BY rch.hint_type
                ORDER BY count DESC
            ", [$startDate, $endDate]);
            
            Response::json([
                'success' => true,
                'metrics' => [
                    'total_ai_conversations' => (int)($aiConvMetrics['total'] ?? 0),
                    'sentiment_analyses' => (int)($sentimentMetrics['total'] ?? 0),
                    'performance_analyses' => (int)($perfMetrics['total'] ?? 0),
                    'coaching_hints' => (int)($coachingMetrics['total'] ?? 0),
                    'audio_transcriptions' => (int)($audioMetrics['total'] ?? 0),
                    'total_tokens' => (int)$totalTokens,
                    'total_cost' => round($totalCost, 4),
                    'breakdown' => [
                        'ai_agents' => [
                            'count' => (int)($aiConvMetrics['total'] ?? 0),
                            'tokens' => (int)($aiConvMetrics['tokens'] ?? 0),
                            'cost' => round((float)($aiConvMetrics['cost'] ?? 0), 4)
                        ],
                        'sentiment_analysis' => [
                            'count' => (int)($sentimentMetrics['total'] ?? 0),
                            'tokens' => (int)($sentimentMetrics['tokens'] ?? 0),
                            'cost' => round((float)($sentimentMetrics['cost'] ?? 0), 4)
                        ],
                        'performance_analysis' => [
                            'count' => (int)($perfMetrics['total'] ?? 0),
                            'tokens' => (int)($perfMetrics['tokens'] ?? 0),
                            'cost' => round((float)($perfMetrics['cost'] ?? 0), 4)
                        ],
                        'realtime_coaching' => [
                            'count' => (int)($coachingMetrics['total'] ?? 0),
                            'tokens' => (int)($coachingMetrics['tokens'] ?? 0),
                            'cost' => round((float)($coachingMetrics['cost'] ?? 0), 4)
                        ],
                        'audio_transcription' => [
                            'count' => (int)($audioMetrics['total'] ?? 0),
                            'tokens' => (int)($audioMetrics['tokens'] ?? 0),
                            'cost' => round((float)($audioMetrics['cost'] ?? 0), 4)
                        ]
                    ]
                ],
                'evolution' => $evolution,
                'ai_agents' => $aiAgents,
                'coaching_hints' => $coachingHints,
                'performance_stats' => [
                    'total' => (int)($perfMetrics['total'] ?? 0),
                    'avg_overall_score' => round((float)($perfMetrics['avg_score'] ?? 0), 2),
                    'total_cost' => round((float)($perfMetrics['cost'] ?? 0), 4),
                    'total_tokens' => (int)($perfMetrics['tokens'] ?? 0)
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obter comparações temporais (mês atual vs anterior)
     */
    public function getTimeComparison(): void
    {
        Permission::abortIfCannot('admin.settings');
        
        try {
            $filters = [
                'start_date' => Request::get('start_date') ?: date('Y-m-d', strtotime('-30 days')),
                'end_date' => Request::get('end_date') ?: date('Y-m-d'),
            ];
            
            // Calcular período anterior (mesmo número de dias)
            $daysDiff = (strtotime($filters['end_date']) - strtotime($filters['start_date'])) / 86400;
            $previousStartDate = date('Y-m-d', strtotime($filters['start_date'] . " -{$daysDiff} days"));
            $previousEndDate = date('Y-m-d', strtotime($filters['start_date'] . ' -1 day'));
            
            // Estatísticas do período atual
            $currentStats = DashboardService::getGeneralStats(null, $filters['start_date'], $filters['end_date'] . ' 23:59:59');
            
            // Estatísticas do período anterior
            $previousStats = DashboardService::getGeneralStats(null, $previousStartDate, $previousEndDate . ' 23:59:59');
            
            // Calcular variações percentuais
            $comparison = [
                'conversations' => [
                    'current' => $currentStats['conversations']['total'] ?? 0,
                    'previous' => $previousStats['conversations']['total'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['conversations']['total'] ?? 0,
                        $currentStats['conversations']['total'] ?? 0
                    )
                ],
                'open_conversations' => [
                    'current' => $currentStats['conversations']['open'] ?? 0,
                    'previous' => $previousStats['conversations']['open'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['conversations']['open'] ?? 0,
                        $currentStats['conversations']['open'] ?? 0
                    )
                ],
                'closed_conversations' => [
                    'current' => $currentStats['conversations']['closed'] ?? 0,
                    'previous' => $previousStats['conversations']['closed'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['conversations']['closed'] ?? 0,
                        $currentStats['conversations']['closed'] ?? 0
                    )
                ],
                'resolution_rate' => [
                    'current' => $currentStats['metrics']['resolution_rate'] ?? 0,
                    'previous' => $previousStats['metrics']['resolution_rate'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['metrics']['resolution_rate'] ?? 0,
                        $currentStats['metrics']['resolution_rate'] ?? 0
                    )
                ],
                'avg_response_time' => [
                    'current' => $currentStats['metrics']['avg_first_response_time'] ?? 0,
                    'previous' => $previousStats['metrics']['avg_first_response_time'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['metrics']['avg_first_response_time'] ?? 0,
                        $currentStats['metrics']['avg_first_response_time'] ?? 0,
                        true // Invertido: menor tempo é melhor
                    )
                ],
                'messages' => [
                    'current' => $currentStats['messages']['total'] ?? 0,
                    'previous' => $previousStats['messages']['total'] ?? 0,
                    'change' => $this->calculatePercentageChange(
                        $previousStats['messages']['total'] ?? 0,
                        $currentStats['messages']['total'] ?? 0
                    )
                ]
            ];
            
            Response::json([
                'success' => true,
                'current_period' => [
                    'start' => $filters['start_date'],
                    'end' => $filters['end_date']
                ],
                'previous_period' => [
                    'start' => $previousStartDate,
                    'end' => $previousEndDate
                ],
                'comparison' => $comparison
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calcular variação percentual
     */
    private function calculatePercentageChange(float $previous, float $current, bool $inverted = false): array
    {
        if ($previous == 0) {
            return [
                'percentage' => $current > 0 ? 100 : 0,
                'value' => $current,
                'is_positive' => $current > 0
            ];
        }
        
        $change = (($current - $previous) / $previous) * 100;
        
        if ($inverted) {
            // Para métricas onde menor é melhor (tempo de resposta)
            $isPositive = $change < 0; // Redução é positiva
        } else {
            // Para métricas onde maior é melhor
            $isPositive = $change > 0;
        }
        
        return [
            'percentage' => round(abs($change), 2),
            'value' => round($current - $previous, 2),
            'is_positive' => $isPositive
        ];
    }
}

