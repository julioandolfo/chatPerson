<?php
/**
 * Model AgentPerformanceGoal
 * Objetivos e metas de performance
 */

namespace App\Models;

use App\Helpers\Database;

class AgentPerformanceGoal extends Model
{
    protected string $table = 'agent_performance_goals';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'agent_id',
        'dimension',
        'current_score',
        'target_score',
        'start_date',
        'end_date',
        'status',
        'created_by',
        'feedback',
        'completed_at'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter metas ativas de um agente
     */
    public static function getActiveGoals(int $agentId): array
    {
        $sql = "SELECT * FROM agent_performance_goals 
                WHERE agent_id = ? 
                AND status = 'active'
                ORDER BY end_date ASC";
        return Database::fetchAll($sql, [$agentId]);
    }
    
    /**
     * Obter todas as metas de um agente
     */
    public static function getAgentGoals(int $agentId, ?string $status = null): array
    {
        $sql = "SELECT g.*, u.name as created_by_name
                FROM agent_performance_goals g
                LEFT JOIN users u ON u.id = g.created_by
                WHERE g.agent_id = ?";
        
        $params = [$agentId];
        
        if ($status) {
            $sql .= " AND g.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY g.created_at DESC";
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Verificar progresso das metas
     */
    public static function checkProgress(int $agentId): array
    {
        // Buscar metas ativas
        $goals = self::getActiveGoals($agentId);
        
        if (empty($goals)) {
            return [];
        }
        
        // Buscar scores atuais do agente (últimos 30 dias)
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-d');
        
        $averages = AgentPerformanceAnalysis::getAgentAverages($agentId, $dateFrom, $dateTo);
        
        $progress = [];
        
        foreach ($goals as $goal) {
            $dimension = $goal['dimension'];
            $currentScore = $averages['avg_' . $dimension] ?? null;
            $targetScore = (float)$goal['target_score'];
            $initialScore = (float)($goal['current_score'] ?? 0);
            
            if ($currentScore !== null) {
                $progressPercent = 0;
                if ($targetScore > $initialScore) {
                    $progressPercent = (($currentScore - $initialScore) / ($targetScore - $initialScore)) * 100;
                }
                
                $progress[] = array_merge($goal, [
                    'current_score_now' => round($currentScore, 2),
                    'progress_percent' => max(0, min(100, round($progressPercent, 1))),
                    'is_on_track' => $currentScore >= $targetScore
                ]);
            }
        }
        
        return $progress;
    }
}
