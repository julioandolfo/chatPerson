<?php
/**
 * Model ConversationNote
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationNote extends Model
{
    protected string $table = 'conversation_notes';
    protected string $primaryKey = 'id';
    protected array $fillable = ['conversation_id', 'user_id', 'content', 'is_private'];
    protected array $hidden = [];
    protected bool $timestamps = true;

    /**
     * Obter todas as notas de uma conversa
     */
    public static function getByConversation(int $conversationId, ?int $userId = null): array
    {
        $sql = "SELECT cn.*, 
                       u.name as user_name, u.email as user_email, u.avatar as user_avatar
                FROM conversation_notes cn
                LEFT JOIN users u ON cn.user_id = u.id
                WHERE cn.conversation_id = ?";
        
        $params = [$conversationId];
        
        // Se userId fornecido, mostrar apenas notas públicas ou do próprio usuário
        if ($userId !== null) {
            $sql .= " AND (cn.is_private = 0 OR cn.user_id = ?)";
            $params[] = $userId;
        }
        
        $sql .= " ORDER BY cn.created_at DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter nota específica
     */
    public static function findWithUser(int $noteId): ?array
    {
        $sql = "SELECT cn.*, 
                       u.name as user_name, u.email as user_email, u.avatar as user_avatar
                FROM conversation_notes cn
                LEFT JOIN users u ON cn.user_id = u.id
                WHERE cn.id = ?
                LIMIT 1";
        
        return Database::fetch($sql, [$noteId]);
    }

    /**
     * Criar nova nota
     */
    public static function createNote(int $conversationId, int $userId, string $content, bool $isPrivate = false): int
    {
        return self::create([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'content' => $content,
            'is_private' => $isPrivate ? 1 : 0
        ]);
    }

    /**
     * Atualizar nota (apenas o criador pode atualizar)
     */
    public static function updateNote(int $noteId, int $userId, string $content): bool
    {
        // Verificar se a nota pertence ao usuário
        $note = self::find($noteId);
        if (!$note || $note['user_id'] != $userId) {
            return false;
        }
        
        return self::update($noteId, ['content' => $content]);
    }

    /**
     * Deletar nota (apenas o criador pode deletar)
     */
    public static function deleteNote(int $noteId, int $userId): bool
    {
        // Verificar se a nota pertence ao usuário
        $note = self::find($noteId);
        if (!$note || $note['user_id'] != $userId) {
            return false;
        }
        
        return self::delete($noteId);
    }
}

