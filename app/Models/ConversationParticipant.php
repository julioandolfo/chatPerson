<?php
/**
 * Model ConversationParticipant
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationParticipant extends Model
{
    protected string $table = 'conversation_participants';
    protected string $primaryKey = 'id';
    protected array $fillable = ['conversation_id', 'user_id', 'added_by'];
    protected bool $timestamps = false; // Usamos added_at manualmente

    /**
     * Adicionar participante a uma conversa
     */
    public static function addParticipant(int $conversationId, int $userId, ?int $addedBy = null): bool
    {
        // Verificar se já existe (não removido)
        $existing = Database::fetch(
            "SELECT id FROM conversation_participants 
             WHERE conversation_id = ? AND user_id = ? AND removed_at IS NULL",
            [$conversationId, $userId]
        );
        
        if ($existing) {
            return true; // Já existe, não precisa adicionar novamente
        }
        
        // Verificar se foi removido antes (soft delete) e reativar
        $removed = Database::fetch(
            "SELECT id FROM conversation_participants 
             WHERE conversation_id = ? AND user_id = ? AND removed_at IS NOT NULL",
            [$conversationId, $userId]
        );
        
        if ($removed) {
            // Reativar participante
            return Database::query(
                "UPDATE conversation_participants 
                 SET removed_at = NULL, added_by = ?, added_at = NOW() 
                 WHERE conversation_id = ? AND user_id = ?",
                [$addedBy, $conversationId, $userId]
            );
        }
        
        // Criar novo participante
        return Database::query(
            "INSERT INTO conversation_participants (conversation_id, user_id, added_by, added_at) 
             VALUES (?, ?, ?, NOW())",
            [$conversationId, $userId, $addedBy]
        );
    }

    /**
     * Remover participante de uma conversa (soft delete)
     */
    public static function removeParticipant(int $conversationId, int $userId): bool
    {
        return Database::query(
            "UPDATE conversation_participants 
             SET removed_at = NOW() 
             WHERE conversation_id = ? AND user_id = ? AND removed_at IS NULL",
            [$conversationId, $userId]
        );
    }

    /**
     * Obter participantes de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT cp.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar,
                       added_by_user.name as added_by_name
                FROM conversation_participants cp
                LEFT JOIN users u ON cp.user_id = u.id
                LEFT JOIN users added_by_user ON cp.added_by = added_by_user.id
                WHERE cp.conversation_id = ? AND cp.removed_at IS NULL
                ORDER BY cp.added_at ASC";
        
        return Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Verificar se usuário é participante de uma conversa
     */
    public static function isParticipant(int $conversationId, int $userId): bool
    {
        $result = Database::fetch(
            "SELECT id FROM conversation_participants 
             WHERE conversation_id = ? AND user_id = ? AND removed_at IS NULL",
            [$conversationId, $userId]
        );
        
        return !empty($result);
    }

    /**
     * Obter conversas onde o usuário é participante
     */
    public static function getConversationsByParticipant(int $userId): array
    {
        $sql = "SELECT DISTINCT conversation_id 
                FROM conversation_participants 
                WHERE user_id = ? AND removed_at IS NULL";
        
        $results = Database::fetchAll($sql, [$userId]);
        return array_column($results, 'conversation_id');
    }
}

