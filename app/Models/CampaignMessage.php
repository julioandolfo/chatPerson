<?php
/**
 * Model CampaignMessage
 * Mensagens individuais de campanha
 */

namespace App\Models;

use App\Helpers\Database;

class CampaignMessage extends Model
{
    protected string $table = 'campaign_messages';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'campaign_id', 'contact_id', 'conversation_id', 'message_id', 'integration_account_id',
        'content', 'attachments',
        'status', 'error_message', 'skip_reason',
        'scheduled_at', 'sent_at', 'delivered_at', 'read_at', 'replied_at', 'failed_at'
    ];
    protected bool $timestamps = true;

    /**
     * Obter todas as mensagens de uma campanha com detalhes do contato
     */
    public static function getAllWithContacts(int $campaignId, int $limit = 100, int $offset = 0, ?string $statusFilter = null): array
    {
        $params = [$campaignId];
        $statusCondition = '';
        
        if ($statusFilter) {
            $statusCondition = 'AND cm.status = ?';
            $params[] = $statusFilter;
        }
        
        $params[] = $limit;
        $params[] = $offset;
        
        $sql = "SELECT cm.*, 
                       c.name as contact_name, 
                       c.phone as contact_phone, 
                       c.email as contact_email,
                       c.avatar as contact_avatar,
                       ia.name as account_name
                FROM campaign_messages cm
                INNER JOIN contacts c ON cm.contact_id = c.id
                LEFT JOIN integration_accounts ia ON cm.integration_account_id = ia.id
                WHERE cm.campaign_id = ? 
                {$statusCondition}
                ORDER BY 
                    CASE cm.status 
                        WHEN 'pending' THEN 1 
                        WHEN 'sent' THEN 2 
                        WHEN 'delivered' THEN 3 
                        WHEN 'read' THEN 4 
                        WHEN 'replied' THEN 5 
                        WHEN 'failed' THEN 6 
                        WHEN 'skipped' THEN 7 
                        ELSE 8 
                    END,
                    cm.id ASC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Contar total de mensagens de uma campanha
     */
    public static function countAll(int $campaignId, ?string $statusFilter = null): int
    {
        $params = [$campaignId];
        $statusCondition = '';
        
        if ($statusFilter) {
            $statusCondition = 'AND status = ?';
            $params[] = $statusFilter;
        }
        
        $sql = "SELECT COUNT(*) as total FROM campaign_messages WHERE campaign_id = ? {$statusCondition}";
        $result = Database::fetch($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter mensagens pendentes de uma campanha
     */
    public static function getPending(int $campaignId, int $limit = 50): array
    {
        $sql = "SELECT cm.*, c.name as contact_name, c.phone as contact_phone
                FROM campaign_messages cm
                INNER JOIN contacts c ON cm.contact_id = c.id
                WHERE cm.campaign_id = ? 
                AND cm.status = 'pending'
                AND (cm.scheduled_at IS NULL OR cm.scheduled_at <= NOW())
                ORDER BY cm.id ASC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$campaignId, $limit]);
    }

    /**
     * Obter mensagens por status
     */
    public static function getByStatus(int $campaignId, string $status): array
    {
        $sql = "SELECT cm.*, c.name as contact_name, c.phone as contact_phone
                FROM campaign_messages cm
                INNER JOIN contacts c ON cm.contact_id = c.id
                WHERE cm.campaign_id = ? AND cm.status = ?
                ORDER BY cm.created_at DESC";
        
        return Database::fetchAll($sql, [$campaignId, $status]);
    }

    /**
     * Marcar como enviada
     */
    public static function markAsSent(int $id, int $messageId, int $integrationAccountId, ?int $conversationId = null): bool
    {
        return self::update($id, [
            'status' => 'sent',
            'message_id' => $messageId,
            'integration_account_id' => $integrationAccountId,
            'conversation_id' => $conversationId,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como entregue
     */
    public static function markAsDelivered(int $id): bool
    {
        return self::update($id, [
            'status' => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como lida
     */
    public static function markAsRead(int $id): bool
    {
        return self::update($id, [
            'status' => 'read',
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como respondida
     */
    public static function markAsReplied(int $id): bool
    {
        // Só marca como replied se ainda não foi
        $message = self::find($id);
        if ($message && empty($message['replied_at'])) {
            return self::update($id, [
                'status' => 'replied',
                'replied_at' => date('Y-m-d H:i:s')
            ]);
        }
        return false;
    }

    /**
     * Marcar como falha
     */
    public static function markAsFailed(int $id, string $errorMessage): bool
    {
        return self::update($id, [
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar como pulada
     */
    public static function markAsSkipped(int $id, string $reason): bool
    {
        return self::update($id, [
            'status' => 'skipped',
            'skip_reason' => $reason
        ]);
    }

    /**
     * Buscar por ID de mensagem (messages table)
     */
    public static function findByMessageId(int $messageId): ?array
    {
        $sql = "SELECT * FROM campaign_messages WHERE message_id = ? LIMIT 1";
        return Database::fetch($sql, [$messageId]);
    }

    /**
     * Buscar por conversa
     */
    public static function findByConversation(int $conversationId): ?array
    {
        $sql = "SELECT * FROM campaign_messages WHERE conversation_id = ? LIMIT 1";
        return Database::fetch($sql, [$conversationId]);
    }

    /**
     * Contar por status
     */
    public static function countByStatus(int $campaignId, string $status): int
    {
        $sql = "SELECT COUNT(*) as total 
                FROM campaign_messages 
                WHERE campaign_id = ? AND status = ?";
        
        $result = Database::fetch($sql, [$campaignId, $status]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Verificar se contato já recebeu mensagem desta campanha
     */
    public static function hasContactReceived(int $campaignId, int $contactId): bool
    {
        $sql = "SELECT COUNT(*) as total 
                FROM campaign_messages 
                WHERE campaign_id = ? AND contact_id = ?
                AND status IN ('sent', 'delivered', 'read', 'replied')";
        
        $result = Database::fetch($sql, [$campaignId, $contactId]);
        return ((int)($result['total'] ?? 0)) > 0;
    }
}
