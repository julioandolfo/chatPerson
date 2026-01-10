<?php
/**
 * Model AgentPerformanceBadge
 * Badges e conquistas dos agentes
 */

namespace App\Models;

use App\Helpers\Database;

class AgentPerformanceBadge extends Model
{
    protected string $table = 'agent_performance_badges';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'agent_id',
        'badge_type',
        'badge_name',
        'badge_description',
        'badge_icon',
        'badge_level',
        'earned_at',
        'related_data'
    ];
    protected bool $timestamps = false;
    
    /**
     * Obter badges de um agente
     */
    public static function getAgentBadges(int $agentId, int $limit = 50): array
    {
        $sql = "SELECT * FROM agent_performance_badges 
                WHERE agent_id = ? 
                ORDER BY earned_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$agentId, $limit]);
    }
    
    /**
     * Verificar se agente já tem um badge específico
     */
    public static function hasBadge(int $agentId, string $badgeType): bool
    {
        $sql = "SELECT COUNT(*) as count FROM agent_performance_badges 
                WHERE agent_id = ? AND badge_type = ?";
        $result = Database::fetch($sql, [$agentId, $badgeType]);
        return ($result['count'] ?? 0) > 0;
    }
    
    /**
     * Contar badges por nível
     */
    public static function countByLevel(int $agentId): array
    {
        $sql = "SELECT badge_level, COUNT(*) as count 
                FROM agent_performance_badges 
                WHERE agent_id = ? 
                GROUP BY badge_level";
        return Database::fetchAll($sql, [$agentId]);
    }
}
