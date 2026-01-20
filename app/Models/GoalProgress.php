<?php
/**
 * Model GoalProgress
 * Histórico de progresso das metas
 */

namespace App\Models;

use App\Helpers\Database;

class GoalProgress extends Model
{
    protected string $table = 'goal_progress';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'date',
        'current_value',
        'percentage',
        'status'
    ];
    protected bool $timestamps = false;
    
    /**
     * Obter histórico de progresso de uma meta
     */
    public static function getHistory(int $goalId, int $days = 30): array
    {
        $sql = "SELECT * FROM goal_progress 
                WHERE goal_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY date ASC";
        return Database::fetchAll($sql, [$goalId, $days]);
    }
    
    /**
     * Obter último progresso
     */
    public static function getLatest(int $goalId): ?array
    {
        $sql = "SELECT * FROM goal_progress WHERE goal_id = ? ORDER BY date DESC LIMIT 1";
        return Database::fetch($sql, [$goalId]);
    }
    
    /**
     * Atualizar ou criar progresso do dia
     */
    public static function upsert(int $goalId, float $currentValue, float $percentage, string $status): void
    {
        $date = date('Y-m-d');
        
        $existing = Database::fetch(
            "SELECT id FROM goal_progress WHERE goal_id = ? AND date = ?",
            [$goalId, $date]
        );
        
        if ($existing) {
            // Atualizar
            Database::execute(
                "UPDATE goal_progress SET current_value = ?, percentage = ?, status = ?, calculated_at = NOW() 
                 WHERE goal_id = ? AND date = ?",
                [$currentValue, $percentage, $status, $goalId, $date]
            );
        } else {
            // Inserir
            Database::execute(
                "INSERT INTO goal_progress (goal_id, date, current_value, percentage, status) 
                 VALUES (?, ?, ?, ?, ?)",
                [$goalId, $date, $currentValue, $percentage, $status]
            );
        }
    }
    
    /**
     * Obter estatísticas de uma meta
     */
    public static function getStats(int $goalId): array
    {
        $sql = "SELECT 
                    MIN(current_value) as min_value,
                    MAX(current_value) as max_value,
                    AVG(current_value) as avg_value,
                    MIN(percentage) as min_percentage,
                    MAX(percentage) as max_percentage,
                    AVG(percentage) as avg_percentage,
                    COUNT(*) as days_tracked
                FROM goal_progress 
                WHERE goal_id = ?";
        
        $stats = Database::fetch($sql, [$goalId]);
        return $stats ?: [
            'min_value' => 0,
            'max_value' => 0,
            'avg_value' => 0,
            'min_percentage' => 0,
            'max_percentage' => 0,
            'avg_percentage' => 0,
            'days_tracked' => 0
        ];
    }
}
