<?php
/**
 * Model Notification
 */

namespace App\Models;

class Notification extends Model
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'type', 'title', 'message', 'link', 'data', 'is_read', 'read_at'];
    protected bool $timestamps = true;

    /**
     * Obter notificações não lidas de um usuário
     */
    public static function getUnread(int $userId, int $limit = 20): array
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? AND is_read = FALSE 
                ORDER BY created_at DESC 
                LIMIT ?";
        return \App\Helpers\Database::fetchAll($sql, [$userId, $limit]);
    }

    /**
     * Obter todas as notificações de um usuário
     */
    public static function getByUser(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        return \App\Helpers\Database::fetchAll($sql, [$userId, $limit, $offset]);
    }

    /**
     * Contar notificações não lidas
     */
    public static function countUnread(int $userId): int
    {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = ? AND is_read = FALSE";
        $result = \App\Helpers\Database::fetch($sql, [$userId]);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Marcar notificação como lida
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        $sql = "UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE id = ? AND user_id = ?";
        return \App\Helpers\Database::execute($sql, [$notificationId, $userId]) > 0;
    }

    /**
     * Marcar todas as notificações do usuário como lidas
     */
    public static function markAllAsRead(int $userId): bool
    {
        $sql = "UPDATE notifications 
                SET is_read = TRUE, read_at = NOW() 
                WHERE user_id = ? AND is_read = FALSE";
        return \App\Helpers\Database::execute($sql, [$userId]) > 0;
    }

    /**
     * Criar notificação
     */
    public static function createNotification(array $data): int
    {
        // Garantir valores padrão (converter boolean para int)
        $data['is_read'] = isset($data['is_read']) ? (int)$data['is_read'] : 0;
        $data['read_at'] = $data['read_at'] ?? null;
        
        // Serializar data se for array
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }
        
        return self::create($data);
    }

    /**
     * Deletar notificações antigas (mais de X dias)
     */
    public static function deleteOld(int $days = 30): int
    {
        $sql = "DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) 
                AND is_read = TRUE";
        return \App\Helpers\Database::execute($sql, [$days]);
    }
}

