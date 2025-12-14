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

class AnalyticsController
{
    /**
     * Página de Analytics de Sentimento
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
}

