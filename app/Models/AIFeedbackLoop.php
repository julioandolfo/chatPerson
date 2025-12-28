<?php
/**
 * Model AIFeedbackLoop
 * Sistema de feedback loop para treinamento incremental dos agentes
 */

namespace App\Models;

use App\Helpers\PostgreSQL;

class AIFeedbackLoop extends PostgreSQLModel
{
    protected string $table = 'ai_feedback_loop';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_agent_id',
        'conversation_id',
        'message_id',
        'user_question',
        'ai_response',
        'correct_answer',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'added_to_kb',
        'knowledge_base_id'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar feedbacks pendentes
     */
    public static function getPending(int $agentId = null, int $limit = 50): array
    {
        if ($agentId) {
            $sql = "SELECT * FROM ai_feedback_loop 
                    WHERE status = 'pending' AND ai_agent_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            return PostgreSQL::query($sql, [$agentId, $limit]);
        }
        
        $sql = "SELECT * FROM ai_feedback_loop 
                WHERE status = 'pending' 
                ORDER BY created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$limit]);
    }

    /**
     * Marcar como revisado
     */
    public static function markAsReviewed(int $id, int $userId, string $correctAnswer, bool $addToKB = false, int $knowledgeBaseId = null): bool
    {
        $data = [
            'status' => 'reviewed',
            'reviewed_by_user_id' => $userId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'correct_answer' => $correctAnswer,
            'added_to_kb' => $addToKB ? true : false,
            'knowledge_base_id' => $knowledgeBaseId
        ];
        
        return self::update($id, $data);
    }

    /**
     * Marcar como ignorado
     */
    public static function markAsIgnored(int $id): bool
    {
        return self::update($id, ['status' => 'ignored']);
    }

    /**
     * Contar feedbacks pendentes
     */
    public static function countPending(int $agentId = null): int
    {
        if ($agentId) {
            $sql = "SELECT COUNT(*) as total FROM ai_feedback_loop 
                    WHERE status = 'pending' AND ai_agent_id = ?";
            $result = PostgreSQL::fetch($sql, [$agentId]);
        } else {
            $sql = "SELECT COUNT(*) as total FROM ai_feedback_loop WHERE status = 'pending'";
            $result = PostgreSQL::fetch($sql);
        }
        
        return (int)($result['total'] ?? 0);
    }

    /**
     * Buscar feedbacks por agente
     */
    public static function getByAgent(int $agentId, int $limit = 100): array
    {
        $sql = "SELECT * FROM ai_feedback_loop 
                WHERE ai_agent_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?";
        return PostgreSQL::query($sql, [$agentId, $limit]);
    }

    /**
     * Buscar feedbacks por conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT * FROM ai_feedback_loop 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC";
        return PostgreSQL::query($sql, [$conversationId]);
    }
}

