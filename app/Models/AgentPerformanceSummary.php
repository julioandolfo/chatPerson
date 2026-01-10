<?php
/**
 * Model AgentPerformanceSummary
 * Sumários agregados de performance
 */

namespace App\Models;

use App\Helpers\Database;

class AgentPerformanceSummary extends Model
{
    protected string $table = 'agent_performance_summary';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'agent_id',
        'period_type',
        'period_start',
        'period_end',
        'avg_proactivity',
        'avg_objection_handling',
        'avg_rapport',
        'avg_closing_techniques',
        'avg_qualification',
        'avg_clarity',
        'avg_value_proposition',
        'avg_response_time',
        'avg_follow_up',
        'avg_professionalism',
        'avg_overall_score',
        'total_conversations_analyzed',
        'total_messages_sent',
        'avg_conversation_duration',
        'total_sales_value',
        'rank_in_team',
        'rank_in_department',
        'total_cost'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter sumário de um agente em um período
     */
    public static function getAgentSummary(int $agentId, string $periodType = 'weekly'): ?array
    {
        $sql = "SELECT * FROM agent_performance_summary 
                WHERE agent_id = ? 
                AND period_type = ?
                ORDER BY period_start DESC 
                LIMIT 1";
        return Database::fetch($sql, [$agentId, $periodType]);
    }
    
    /**
     * Obter histórico de sumários
     */
    public static function getAgentHistory(int $agentId, string $periodType = 'weekly', int $limit = 12): array
    {
        $sql = "SELECT * FROM agent_performance_summary 
                WHERE agent_id = ? 
                AND period_type = ?
                ORDER BY period_start DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $periodType, $limit]);
    }
    
    /**
     * Comparar múltiplos agentes
     */
    public static function compareAgents(array $agentIds, string $periodType = 'weekly'): array
    {
        $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
        
        $sql = "SELECT aps.*, u.name as agent_name
                FROM agent_performance_summary aps
                INNER JOIN users u ON u.id = aps.agent_id
                WHERE aps.agent_id IN ({$placeholders})
                AND aps.period_type = ?
                AND aps.period_start = (
                    SELECT MAX(period_start) 
                    FROM agent_performance_summary 
                    WHERE agent_id = aps.agent_id AND period_type = ?
                )";
        
        $params = array_merge($agentIds, [$periodType, $periodType]);
        return Database::fetchAll($sql, $params);
    }
}
