<?php
/**
 * Model ConversationMention
 * 
 * Gerencia menções/convites de agentes em conversas.
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationMention extends Model
{
    protected string $table = 'conversation_mentions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 
        'mentioned_by', 
        'mentioned_user_id', 
        'message_id',
        'status',
        'note',
        'responded_at',
        'expires_at'
    ];
    protected bool $timestamps = true;

    // Status possíveis
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_DECLINED = 'declined';
    const STATUS_EXPIRED = 'expired';

    /**
     * Criar uma menção/convite
     */
    public static function createMention(
        int $conversationId, 
        int $mentionedBy, 
        int $mentionedUserId, 
        ?int $messageId = null,
        ?string $note = null,
        ?string $expiresAt = null
    ): int {
        $sql = "INSERT INTO conversation_mentions 
                (conversation_id, mentioned_by, mentioned_user_id, message_id, note, expires_at, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        Database::query($sql, [
            $conversationId,
            $mentionedBy,
            $mentionedUserId,
            $messageId,
            $note,
            $expiresAt
        ]);
        
        return (int) Database::lastInsertId();
    }

    /**
     * Buscar menção por ID com dados relacionados
     */
    public static function findWithDetails(int $id): ?array
    {
        $sql = "SELECT m.*,
                       c.contact_id, ct.name as contact_name, ct.phone as contact_phone,
                       mb.name as mentioned_by_name, mb.email as mentioned_by_email, mb.avatar as mentioned_by_avatar,
                       mu.name as mentioned_user_name, mu.email as mentioned_user_email, mu.avatar as mentioned_user_avatar,
                       msg.content as message_content
                FROM conversation_mentions m
                LEFT JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users mb ON m.mentioned_by = mb.id
                LEFT JOIN users mu ON m.mentioned_user_id = mu.id
                LEFT JOIN messages msg ON m.message_id = msg.id
                WHERE m.id = ?";
        
        return Database::fetch($sql, [$id]) ?: null;
    }

    /**
     * Obter menções pendentes para um usuário
     */
    public static function getPendingForUser(int $userId, int $limit = 20): array
    {
        $sql = "SELECT m.*,
                       c.contact_id, ct.name as contact_name, ct.phone as contact_phone, ct.avatar as contact_avatar,
                       c.channel, c.status as conversation_status,
                       mb.name as mentioned_by_name, mb.email as mentioned_by_email, mb.avatar as mentioned_by_avatar,
                       msg.content as message_content,
                       (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
                FROM conversation_mentions m
                LEFT JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users mb ON m.mentioned_by = mb.id
                LEFT JOIN messages msg ON m.message_id = msg.id
                WHERE m.mentioned_user_id = ?
                  AND m.status = 'pending'
                  AND (m.expires_at IS NULL OR m.expires_at > NOW())
                ORDER BY m.created_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Contar menções pendentes para um usuário
     */
    public static function countPendingForUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count
                FROM conversation_mentions
                WHERE mentioned_user_id = ?
                  AND status = 'pending'
                  AND (expires_at IS NULL OR expires_at > NOW())";
        
        $result = Database::fetch($sql, [$userId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Obter menções de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT m.*,
                       mb.name as mentioned_by_name, mb.avatar as mentioned_by_avatar,
                       mu.name as mentioned_user_name, mu.avatar as mentioned_user_avatar
                FROM conversation_mentions m
                LEFT JOIN users mb ON m.mentioned_by = mb.id
                LEFT JOIN users mu ON m.mentioned_user_id = mu.id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at DESC";
        
        return Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Aceitar convite (e adicionar como participante)
     */
    public static function accept(int $mentionId): bool
    {
        $sql = "UPDATE conversation_mentions 
                SET status = 'accepted', responded_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'pending'";
        
        return Database::query($sql, [$mentionId]) !== false;
    }

    /**
     * Recusar convite
     */
    public static function decline(int $mentionId): bool
    {
        $sql = "UPDATE conversation_mentions 
                SET status = 'declined', responded_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'pending'";
        
        return Database::query($sql, [$mentionId]) !== false;
    }

    /**
     * Cancelar convite (quem enviou pode cancelar)
     */
    public static function cancel(int $mentionId): bool
    {
        $sql = "UPDATE conversation_mentions 
                SET status = 'cancelled', responded_at = NOW(), updated_at = NOW()
                WHERE id = ? AND status = 'pending'";
        
        return Database::query($sql, [$mentionId]) !== false;
    }

    /**
     * Verificar se usuário já foi mencionado em uma conversa (pendente)
     */
    public static function hasPendingMention(int $conversationId, int $userId): bool
    {
        $sql = "SELECT id FROM conversation_mentions 
                WHERE conversation_id = ? 
                  AND mentioned_user_id = ? 
                  AND status = 'pending'
                  AND (expires_at IS NULL OR expires_at > NOW())
                LIMIT 1";
        
        return Database::fetch($sql, [$conversationId, $userId]) !== false;
    }

    /**
     * Marcar menções expiradas
     */
    public static function markExpired(): int
    {
        $sql = "UPDATE conversation_mentions 
                SET status = 'expired', updated_at = NOW()
                WHERE status = 'pending' 
                  AND expires_at IS NOT NULL 
                  AND expires_at <= NOW()";
        
        Database::query($sql);
        return Database::rowCount();
    }

    /**
     * Obter histórico de menções de um usuário (todas)
     */
    public static function getHistoryForUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT m.*,
                       c.contact_id, ct.name as contact_name, ct.avatar as contact_avatar,
                       c.channel, c.status as conversation_status,
                       mb.name as mentioned_by_name, mb.avatar as mentioned_by_avatar
                FROM conversation_mentions m
                LEFT JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users mb ON m.mentioned_by = mb.id
                WHERE m.mentioned_user_id = ?
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, [$userId, $limit, $offset]);
    }

    /**
     * Obter menções feitas por um usuário
     */
    public static function getMadeByUser(int $userId, int $limit = 50): array
    {
        $sql = "SELECT m.*,
                       c.contact_id, ct.name as contact_name, ct.avatar as contact_avatar,
                       c.channel,
                       mu.name as mentioned_user_name, mu.avatar as mentioned_user_avatar
                FROM conversation_mentions m
                LEFT JOIN conversations c ON m.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users mu ON m.mentioned_user_id = mu.id
                WHERE m.mentioned_by = ?
                ORDER BY m.created_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$userId, $limit]);
    }
}

