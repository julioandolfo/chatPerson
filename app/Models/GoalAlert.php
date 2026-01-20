<?php
/**
 * Model GoalAlert
 * Alertas de metas (off-track, em risco, etc)
 */

namespace App\Models;

use App\Helpers\Database;

class GoalAlert extends Model
{
    protected string $table = 'goal_alerts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'goal_id',
        'alert_type',
        'severity',
        'message',
        'details',
        'is_read',
        'is_resolved',
        'resolved_at'
    ];
    protected bool $timestamps = false;
    
    /**
     * Criar novo alerta
     */
    public static function createAlert(int $goalId, string $type, string $severity, string $message, ?array $details = null): int
    {
        // Verificar se já existe alerta similar recente (últimas 24h)
        $existing = Database::fetch(
            "SELECT id FROM goal_alerts 
             WHERE goal_id = ? AND alert_type = ? AND is_resolved = 0 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC LIMIT 1",
            [$goalId, $type]
        );
        
        if ($existing) {
            // Já existe alerta similar, não duplicar
            return $existing['id'];
        }
        
        $sql = "INSERT INTO goal_alerts (goal_id, alert_type, severity, message, details)
                VALUES (?, ?, ?, ?, ?)";
        
        Database::execute($sql, [
            $goalId, 
            $type, 
            $severity, 
            $message, 
            $details ? json_encode($details) : null
        ]);
        
        return Database::lastInsertId();
    }
    
    /**
     * Obter alertas não lidos de uma meta
     */
    public static function getUnreadForGoal(int $goalId): array
    {
        $sql = "SELECT * FROM goal_alerts 
                WHERE goal_id = ? AND is_read = 0 AND is_resolved = 0
                ORDER BY created_at DESC";
        
        return Database::fetchAll($sql, [$goalId]);
    }
    
    /**
     * Obter alertas de um agente
     */
    public static function getAlertsForAgent(int $agentId, bool $onlyUnread = true): array
    {
        $sql = "SELECT ga.*, g.name as goal_name, g.type, g.target_value
                FROM goal_alerts ga
                INNER JOIN goals g ON ga.goal_id = g.id
                WHERE g.target_type = 'individual' AND g.target_id = ?";
        
        if ($onlyUnread) {
            $sql .= " AND ga.is_read = 0 AND ga.is_resolved = 0";
        }
        
        $sql .= " ORDER BY ga.created_at DESC LIMIT 20";
        
        return Database::fetchAll($sql, [$agentId]);
    }
    
    /**
     * Marcar como lido
     */
    public static function markAsRead(int $alertId): bool
    {
        $sql = "UPDATE goal_alerts SET is_read = 1 WHERE id = ?";
        Database::execute($sql, [$alertId]);
        return true;
    }
    
    /**
     * Resolver alerta
     */
    public static function resolve(int $alertId): bool
    {
        $sql = "UPDATE goal_alerts SET is_resolved = 1, resolved_at = NOW() WHERE id = ?";
        Database::execute($sql, [$alertId]);
        return true;
    }
}
