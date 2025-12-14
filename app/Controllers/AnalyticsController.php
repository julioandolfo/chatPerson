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
}

