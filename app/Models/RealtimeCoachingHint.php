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
        'message_id',
        'hint_type',
        'hint_text',
        'suggestions',
        'model_used',
        'tokens_used',
        'cost',
        'viewed_at',
        'feedback'
    ];
    protected bool $timestamps = false; // Tabela só tem created_at (automático)
    
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
    public static function markAsViewed(int $hintId): bool
    {
        $sql = "UPDATE realtime_coaching_hints 
                SET viewed_at = NOW() 
                WHERE id = ? AND viewed_at IS NULL";
        
        return Database::execute($sql, [$hintId]);
    }
    
    /**
     * Marcar feedback da dica
     */
    public static function setFeedback(int $hintId, string $feedback): bool
    {
        $sql = "UPDATE realtime_coaching_hints 
                SET feedback = ? 
                WHERE id = ?";
        
        return Database::execute($sql, [$feedback, $hintId]);
    }
    
    /**
     * Obter estatísticas de coaching de um agente
     */
    public static function getAgentStats(int $agentId, string $dateFrom, string $dateTo): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_hints,
                    SUM(CASE WHEN viewed_at IS NOT NULL THEN 1 ELSE 0 END) as viewed_hints,
                    SUM(CASE WHEN feedback = 'helpful' THEN 1 ELSE 0 END) as helpful_hints,
                    SUM(CASE WHEN feedback = 'not_helpful' THEN 1 ELSE 0 END) as not_helpful_hints,
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
