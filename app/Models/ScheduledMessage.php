<?php
/**
 * Model ScheduledMessage
 * Mensagens agendadas para envio futuro
 */

namespace App\Models;

use App\Helpers\Database;

class ScheduledMessage extends Model
{
    protected string $table = 'scheduled_messages';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 
        'user_id', 
        'content', 
        'attachments', 
        'scheduled_at', 
        'sent_at', 
        'status', 
        'cancel_if_resolved', 
        'cancel_if_responded', 
        'error_message'
    ];
    protected bool $timestamps = true;

    /**
     * Obter mensagens agendadas de uma conversa
     */
    public static function getByConversation(int $conversationId, ?string $status = null): array
    {
        $sql = "SELECT sm.*, 
                       u.name as user_name,
                       c.contact_id,
                       ct.name as contact_name
                FROM scheduled_messages sm
                LEFT JOIN users u ON sm.user_id = u.id
                LEFT JOIN conversations c ON sm.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE sm.conversation_id = ?";
        
        $params = [$conversationId];
        
        if ($status !== null) {
            $sql .= " AND sm.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY sm.scheduled_at ASC";
        
        $messages = Database::fetchAll($sql, $params);
        
        // Processar attachments JSON
        foreach ($messages as &$message) {
            if (!empty($message['attachments'])) {
                $message['attachments'] = json_decode($message['attachments'], true) ?? [];
            } else {
                $message['attachments'] = [];
            }
        }
        
        return $messages;
    }

    /**
     * Obter mensagens pendentes que devem ser enviadas agora
     */
    public static function getPendingToSend(int $limit = 50): array
    {
        $sql = "SELECT sm.*, 
                       c.status as conversation_status,
                       c.resolved_at as conversation_resolved_at,
                       (SELECT COUNT(*) FROM messages m 
                        WHERE m.conversation_id = sm.conversation_id 
                        AND m.sender_type = 'agent' 
                        AND m.created_at > sm.created_at) as has_response_after
                FROM scheduled_messages sm
                INNER JOIN conversations c ON sm.conversation_id = c.id
                WHERE sm.status = 'pending'
                AND sm.scheduled_at <= NOW()
                ORDER BY sm.scheduled_at ASC
                LIMIT ?";
        
        $messages = Database::fetchAll($sql, [$limit]);
        
        // Processar attachments JSON
        foreach ($messages as &$message) {
            if (!empty($message['attachments'])) {
                if (is_string($message['attachments'])) {
                    $message['attachments'] = json_decode($message['attachments'], true) ?? [];
                }
            } else {
                $message['attachments'] = [];
            }
        }
        
        return $messages;
    }

    /**
     * Contar mensagens agendadas pendentes de uma conversa
     */
    public static function countPending(int $conversationId): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM scheduled_messages 
                WHERE conversation_id = ? 
                AND status = 'pending'";
        
        $result = Database::fetch($sql, [$conversationId]);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Marcar como enviada
     */
    public static function markAsSent(int $id): bool
    {
        return self::update($id, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como falhada
     */
    public static function markAsFailed(int $id, string $errorMessage): bool
    {
        return self::update($id, [
            'status' => 'failed',
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Cancelar mensagem agendada
     */
    public static function cancel(int $id): bool
    {
        return self::update($id, [
            'status' => 'cancelled'
        ]);
    }
}

