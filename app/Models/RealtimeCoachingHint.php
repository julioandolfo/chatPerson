<?php
/**
 * Model RealtimeCoachingHint
 */

namespace App\Models;

use App\Helpers\Database;

class RealtimeCoachingHint extends Model
{
    protected string $table = 'realtime_coaching_hints';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id',
        'agent_id',
        'client_message',
        'hint_type',
        'hint_title',
        'hint_message',
        'suggestions',
        'context_summary',
        'model_used',
        'tokens_used',
        'cost',
        'shown_at',
        'dismissed_at',
        'used_suggestion'
    ];
    protected bool $timestamps = true;
    
    /**
     * Obter dicas de uma conversa
     */
    public static function getByConversation(int $conversationId, int $limit = 10): array
    {
        $sql = "SELECT * FROM realtime_coaching_hints 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        
        return Database::fetchAll($sql, [$conversationId, $limit]);
    }
    
    /**
     * Obter última dica de um agente
     */
    public static function getLastByAgent(int $agentId): ?array
    {
        $sql = "SELECT * FROM realtime_coaching_hints 
                WHERE agent_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = Database::fetchAll($sql, [$agentId]);
        return $result[0] ?? null;
    }
    
    /**
     * Marcar dica como visualizada
     */
    public static function markAsShown(int $hintId): bool
    {
        $sql = "UPDATE realtime_coaching_hints 
                SET shown_at = NOW() 
                WHERE id = ? AND shown_at IS NULL";
        
        return Database::execute($sql, [$hintId]);
    }
    
    /**
     * Marcar dica como descartada
     */
    public static function markAsDismissed(int $hintId): bool
    {
        $sql = "UPDATE realtime_coaching_hints 
                SET dismissed_at = NOW() 
                WHERE id = ?";
        
        return Database::execute($sql, [$hintId]);
    }
    
    /**
     * Marcar que sugestão foi usada
     */
    public static function markAsUsed(int $hintId): bool
    {
        $sql = "UPDATE realtime_coaching_hints 
                SET used_suggestion = 1 
                WHERE id = ?";
        
        return Database::execute($sql, [$hintId]);
    }
    
    /**
     * Obter estatísticas de coaching de um agente
     */
    public static function getAgentStats(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    SUM(CASE WHEN shown_at IS NOT NULL THEN 1 ELSE 0 END) as shown_hints,
                    SUM(CASE WHEN used_suggestion = 1 THEN 1 ELSE 0 END) as used_hints,
                    SUM(CASE WHEN dismissed_at IS NOT NULL THEN 1 ELSE 0 END) as dismissed_hints,
                    SUM(cost) as total_cost,
                    hint_type,
                    COUNT(*) as type_count
                FROM realtime_coaching_hints 
                WHERE agent_id = ? 
                AND created_at BETWEEN ? AND ?
                GROUP BY hint_type";
        
        return Database::fetchAll($sql, [$agentId, $dateFrom, $dateTo]);
    }
    
    /**
     * Limpar dicas antigas
     */
    public static function cleanOld(int $daysOld = 30): int
    {
        $sql = "DELETE FROM realtime_coaching_hints 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return Database::execute($sql, [$daysOld]);
    }
}
