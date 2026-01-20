<?php
/**
 * Model GoalAchievement
 * Conquistas de metas (quando completadas)
 */

namespace App\Models;

use App\Helpers\Database;

class GoalAchievement extends Model
{
    protected string $table = 'goal_achievements';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'achieved_at',
        'final_value',
        'percentage',
        'days_to_achieve',
        'points_awarded',
        'badge_awarded',
        'notification_sent',
        'notification_sent_at'
    ];
    protected bool $timestamps = false;
    
    /**
     * Verificar se meta já foi conquistada
     */
    public static function isAchieved(int $goalId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM goal_achievements WHERE goal_id = ?";
        $result = Database::fetch($sql, [$goalId]);
        return $result && $result['count'] > 0;
    }
    
    /**
     * Registrar conquista
     */
    public static function record(int $goalId, float $finalValue, float $percentage, int $daysToAchieve, int $points = 0, ?string $badge = null): int
    {
        // Verificar se já existe
        if (self::isAchieved($goalId)) {
            return 0;
        }
        
        $sql = "INSERT INTO goal_achievements 
                (goal_id, achieved_at, final_value, percentage, days_to_achieve, points_awarded, badge_awarded)
                VALUES (?, NOW(), ?, ?, ?, ?, ?)";
        
        Database::execute($sql, [$goalId, $finalValue, $percentage, $daysToAchieve, $points, $badge]);
        return Database::lastInsertId();
    }
    
    /**
     * Obter conquistas de um agente
     */
    public static function getAgentAchievements(int $agentId): array
    {
        $sql = "SELECT ga.*, g.name as goal_name, g.type, g.target_value
                FROM goal_achievements ga
                INNER JOIN goals g ON ga.goal_id = g.id
                WHERE g.target_type = 'individual' AND g.target_id = ?
                ORDER BY ga.achieved_at DESC";
        
        return Database::fetchAll($sql, [$agentId]);
    }
    
    /**
     * Obter conquistas recentes (todas)
     */
    public static function getRecent(int $limit = 10): array
    {
        $sql = "SELECT ga.*, 
                       g.name as goal_name, 
                       g.type, 
                       g.target_type,
                       g.target_id,
                       CASE 
                           WHEN g.target_type = 'individual' THEN (SELECT name FROM users WHERE id = g.target_id)
                           WHEN g.target_type = 'team' THEN (SELECT name FROM teams WHERE id = g.target_id)
                           WHEN g.target_type = 'department' THEN (SELECT name FROM departments WHERE id = g.target_id)
                           ELSE 'Empresa'
                       END as target_name
                FROM goal_achievements ga
                INNER JOIN goals g ON ga.goal_id = g.id
                ORDER BY ga.achieved_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$limit]);
    }
}
