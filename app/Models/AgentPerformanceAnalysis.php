<?php
/**
 * Model AgentPerformanceAnalysis
 * Análises de performance de vendedores
 */

namespace App\Models;

use App\Helpers\Database;

class AgentPerformanceAnalysis extends Model
{
    protected string $table = 'agent_performance_analysis';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id',
        'agent_id',
        'proactivity_score',
        'objection_handling_score',
        'rapport_score',
        'closing_techniques_score',
        'qualification_score',
        'clarity_score',
        'value_proposition_score',
        'response_time_score',
        'follow_up_score',
        'professionalism_score',
        'overall_score',
        'strengths',
        'weaknesses',
        'improvement_suggestions',
        'key_moments',
        'detailed_analysis',
        'messages_analyzed',
        'agent_messages_count',
        'client_messages_count',
        'conversation_duration_minutes',
        'funnel_stage',
        'conversation_value',
        'model_used',
        'tokens_used',
        'cost',
        'analyzed_at'
    ];
    protected bool $timestamps = false;
    
    /**
     * Obter análise de uma conversa específica
     */
    public static function getByConversation(int $conversationId): ?array
    {
        return self::whereFirst('conversation_id', '=', $conversationId);
    }
    
    /**
     * Obter análises de um agente
     */
    public static function getByAgent(int $agentId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM agent_performance_analysis 
                WHERE agent_id = ? 
                ORDER BY analyzed_at DESC 
                LIMIT ? OFFSET ?";
        return Database::fetchAll($sql, [$agentId, $limit, $offset]);
    }
    
    /**
     * Obter análises em um período
     */
    public static function getByPeriod(string $dateFrom, string $dateTo, ?int $agentId = null): array
    {
        if ($agentId) {
            $sql = "SELECT * FROM agent_performance_analysis 
                    WHERE agent_id = ? 
                    AND DATE(analyzed_at) BETWEEN ? AND ?
                    ORDER BY analyzed_at DESC";
            return Database::fetchAll($sql, [$agentId, $dateFrom, $dateTo]);
        } else {
            $sql = "SELECT * FROM agent_performance_analysis 
                    WHERE DATE(analyzed_at) BETWEEN ? AND ?
                    ORDER BY analyzed_at DESC";
            return Database::fetchAll($sql, [$dateFrom, $dateTo]);
        }
    }
    
    /**
     * Obter média de scores de um agente
     */
    public static function getAgentAverages(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): ?array
    {
        $sql = "SELECT 
                    AVG(proactivity_score) as avg_proactivity,
                    AVG(objection_handling_score) as avg_objection_handling,
                    AVG(rapport_score) as avg_rapport,
                    AVG(closing_techniques_score) as avg_closing_techniques,
                    AVG(qualification_score) as avg_qualification,
                    AVG(clarity_score) as avg_clarity,
                    AVG(value_proposition_score) as avg_value_proposition,
                    AVG(response_time_score) as avg_response_time,
                    AVG(follow_up_score) as avg_follow_up,
                    AVG(professionalism_score) as avg_professionalism,
                    AVG(overall_score) as avg_overall,
                    COUNT(*) as total_analyses
                FROM agent_performance_analysis 
                WHERE agent_id = ?";
        
        $params = [$agentId];
        
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(analyzed_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        return Database::fetch($sql, $params);
    }
    
    /**
     * Obter ranking de agentes
     */
    public static function getAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 50): array
    {
        $sql = "SELECT 
                    agent_id,
                    u.name as agent_name,
                    AVG(overall_score) as avg_score,
                    COUNT(*) as total_conversations,
                    MIN(overall_score) as min_score,
                    MAX(overall_score) as max_score
                FROM agent_performance_analysis apa
                INNER JOIN users u ON u.id = apa.agent_id
                WHERE 1=1";
        
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(analyzed_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY agent_id, u.name
                  ORDER BY avg_score DESC, total_conversations DESC
                  LIMIT ?";
        
        $params[] = $limit;
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter top performers em uma dimensão específica
     */
    public static function getTopPerformersInDimension(string $dimension, int $limit = 10, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $validDimensions = [
            'proactivity', 'objection_handling', 'rapport', 'closing_techniques',
            'qualification', 'clarity', 'value_proposition', 'response_time',
            'follow_up', 'professionalism'
        ];
        
        if (!in_array($dimension, $validDimensions)) {
            return [];
        }
        
        $scoreColumn = $dimension . '_score';
        
        $sql = "SELECT 
                    agent_id,
                    u.name as agent_name,
                    AVG({$scoreColumn}) as avg_score,
                    COUNT(*) as total_conversations
                FROM agent_performance_analysis apa
                INNER JOIN users u ON u.id = apa.agent_id
                WHERE {$scoreColumn} IS NOT NULL";
        
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(analyzed_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        $sql .= " GROUP BY agent_id, u.name
                  ORDER BY avg_score DESC
                  LIMIT ?";
        
        $params[] = $limit;
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Obter conversas que precisam ser analisadas
     */
    public static function getPendingConversations(int $limit = 50): array
    {
        $sql = "SELECT DISTINCT c.id, c.agent_id, c.contact_id, c.status, c.updated_at
                FROM conversations c
                LEFT JOIN agent_performance_analysis apa ON c.id = apa.conversation_id
                WHERE c.status IN ('closed', 'resolved')
                AND c.agent_id IS NOT NULL
                AND apa.id IS NULL
                AND (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_type = 'agent') >= 3
                ORDER BY c.updated_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$limit]);
    }
    
    /**
     * Obter estatísticas gerais
     */
    public static function getOverallStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_analyses,
                    COUNT(DISTINCT agent_id) as total_agents,
                    AVG(overall_score) as avg_overall_score,
                    MAX(overall_score) as max_score,
                    MIN(overall_score) as min_score,
                    SUM(cost) as total_cost,
                    SUM(tokens_used) as total_tokens
                FROM agent_performance_analysis
                WHERE 1=1";
        
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $sql .= " AND DATE(analyzed_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }
        
        return Database::fetch($sql, $params) ?: [];
    }
}
