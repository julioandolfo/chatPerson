<?php
/**
 * Model CoachingAnalyticsSummary
 * Sumários agregados de analytics de coaching
 */

namespace App\Models;

use App\Helpers\Database;

class CoachingAnalyticsSummary extends Model
{
    protected string $table = 'coaching_analytics_summary';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'agent_id',
        'period_type',
        'period_start',
        'period_end',
        'total_hints_received',
        'total_hints_viewed',
        'total_hints_helpful',
        'total_hints_not_helpful',
        'total_suggestions_used',
        'hints_objection',
        'hints_opportunity',
        'hints_buying_signal',
        'hints_negative_sentiment',
        'hints_closing_opportunity',
        'hints_escalation',
        'hints_question',
        'conversations_with_hints',
        'conversations_converted',
        'conversion_rate_improvement',
        'avg_response_time_seconds',
        'avg_conversation_duration_minutes',
        'sales_value_total',
        'total_cost',
        'total_tokens'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter sumário de um agente em um período
     */
    public static function getAgentSummary(int $agentId, string $periodType = 'daily', ?string $periodStart = null): ?array
    {
        $periodStart = $periodStart ?? date('Y-m-d');
        
        $sql = "SELECT * FROM coaching_analytics_summary 
                WHERE agent_id = :agent_id 
                AND period_type = :period_type
                AND period_start = :period_start
                LIMIT 1";
        
        return Database::fetch($sql, [
            'agent_id' => $agentId,
            'period_type' => $periodType,
            'period_start' => $periodStart
        ]);
    }
    
    /**
     * Obter histórico de sumários de um agente
     */
    public static function getAgentHistory(int $agentId, string $periodType = 'daily', int $limit = 30): array
    {
        $sql = "SELECT * FROM coaching_analytics_summary 
                WHERE agent_id = :agent_id 
                AND period_type = :period_type
                ORDER BY period_start DESC 
                LIMIT :limit";
        
        return Database::fetchAll($sql, [
            'agent_id' => $agentId,
            'period_type' => $periodType,
            'limit' => $limit
        ]);
    }
    
    /**
     * Obter ranking de agentes por período
     */
    public static function getRanking(string $periodType = 'weekly', ?string $periodStart = null, int $limit = 10): array
    {
        $periodStart = $periodStart ?? date('Y-m-d', strtotime('monday this week'));
        
        $sql = "SELECT cas.*, u.name as agent_name
                FROM coaching_analytics_summary cas
                INNER JOIN users u ON cas.agent_id = u.id
                WHERE cas.period_type = :period_type
                AND cas.period_start = :period_start
                AND cas.total_hints_received > 0
                ORDER BY (cas.total_hints_helpful * 1.0 / cas.total_hints_received) DESC
                LIMIT :limit";
        
        return Database::fetchAll($sql, [
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'limit' => $limit
        ]);
    }
    
    /**
     * Obter estatísticas globais por período
     */
    public static function getGlobalStats(string $periodType = 'daily', ?string $periodStart = null): array
    {
        $periodStart = $periodStart ?? date('Y-m-d');
        
        $sql = "SELECT 
                    COUNT(DISTINCT agent_id) as total_agents,
                    SUM(total_hints_received) as total_hints,
                    SUM(total_hints_helpful) as total_helpful,
                    SUM(total_hints_not_helpful) as total_not_helpful,
                    SUM(total_suggestions_used) as total_suggestions_used,
                    SUM(conversations_converted) as total_converted,
                    SUM(sales_value_total) as total_sales,
                    SUM(total_cost) as total_cost,
                    AVG(conversion_rate_improvement) as avg_conversion_improvement
                FROM coaching_analytics_summary
                WHERE period_type = :period_type
                AND period_start = :period_start";
        
        return Database::fetch($sql, [
            'period_type' => $periodType,
            'period_start' => $periodStart
        ]) ?? [];
    }
    
    /**
     * Atualizar ou criar sumário
     */
    public static function upsert(array $data): bool
    {
        $existing = self::getAgentSummary(
            $data['agent_id'],
            $data['period_type'],
            $data['period_start']
        );
        
        if ($existing) {
            // Atualizar
            $sql = "UPDATE coaching_analytics_summary SET ";
            $updates = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if ($key !== 'agent_id' && $key !== 'period_type' && $key !== 'period_start') {
                    $updates[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            
            $sql .= implode(', ', $updates);
            $sql .= ", updated_at = NOW() WHERE id = :id";
            $params['id'] = $existing['id'];
            
            Database::execute($sql, $params);
            return true;
        } else {
            // Criar
            return self::create($data) > 0;
        }
    }
}
