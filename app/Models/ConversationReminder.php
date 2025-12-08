<?php
/**
 * Model ConversationReminder
 * Lembretes para conversas
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationReminder extends Model
{
    protected string $table = 'conversation_reminders';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 
        'user_id', 
        'reminder_at', 
        'note', 
        'is_resolved', 
        'resolved_at'
    ];
    protected bool $timestamps = true;

    /**
     * Obter lembretes de uma conversa
     */
    public static function getByConversation(int $conversationId, bool $onlyActive = false): array
    {
        $sql = "SELECT cr.*, 
                       u.name as user_name
                FROM conversation_reminders cr
                LEFT JOIN users u ON cr.user_id = u.id
                WHERE cr.conversation_id = ?";
        
        $params = [$conversationId];
        
        if ($onlyActive) {
            $sql .= " AND cr.is_resolved = 0";
        }
        
        $sql .= " ORDER BY cr.reminder_at ASC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter lembretes pendentes que devem ser notificados agora
     */
    public static function getPendingToNotify(int $limit = 50): array
    {
        $sql = "SELECT cr.*, 
                       c.contact_id,
                       ct.name as contact_name,
                       c.status as conversation_status
                FROM conversation_reminders cr
                INNER JOIN conversations c ON cr.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE cr.is_resolved = 0
                AND cr.reminder_at <= NOW()
                ORDER BY cr.reminder_at ASC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Contar lembretes ativos de uma conversa
     */
    public static function countActive(int $conversationId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM conversation_reminders 
                WHERE conversation_id = ? 
                AND is_resolved = 0";
        
        $result = Database::fetch($sql, [$conversationId]);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Marcar lembrete como resolvido
     */
    public static function markAsResolved(int $id): bool
    {
        return self::update($id, [
            'is_resolved' => 1,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obter lembretes ativos de um usuário
     */
    public static function getActiveByUser(int $userId, int $limit = 20): array
    {
        $sql = "SELECT cr.*, 
                       c.contact_id,
                       ct.name as contact_name,
                       c.status as conversation_status
                FROM conversation_reminders cr
                INNER JOIN conversations c ON cr.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE cr.user_id = ?
                AND cr.is_resolved = 0
                ORDER BY cr.reminder_at ASC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$userId, $limit]);
    }
}

