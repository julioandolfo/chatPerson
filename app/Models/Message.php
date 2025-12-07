<?php
/**
 * Model Message
 */

namespace App\Models;

use App\Helpers\Database;

class Message extends Model
{
    protected string $table = 'messages';
    protected string $primaryKey = 'id';
    protected array $fillable = ['conversation_id', 'sender_id', 'sender_type', 'content', 'message_type', 'attachments', 'status', 'delivered_at', 'read_at', 'error_message', 'external_id', 'quoted_message_id', 'quoted_sender_name', 'quoted_text', 'ai_agent_id'];
    protected array $hidden = [];
    protected bool $timestamps = false; // Tabela messages não tem updated_at

    /**
     * Obter mensagens de uma conversa
     */
    public static function getByConversation(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT m.*, 
                       u.name as sender_name, u.avatar as sender_avatar
                FROM messages m
                LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'agent'
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC
                LIMIT ? OFFSET ?";
        
        $messages = Database::fetchAll($sql, [$conversationId, $limit, $offset]);
        
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
     * Obter mensagens com detalhes do remetente (agent ou contact)
     */
    public static function getMessagesWithSenderDetails(int $conversationId, ?int $limit = null, ?int $offset = null, ?int $beforeId = null): array
    {
        $params = [$conversationId];
        $whereConditions = ["m.conversation_id = ?"];
        
        // Se beforeId for fornecido, buscar apenas mensagens anteriores a esse ID
        if ($beforeId !== null) {
            $whereConditions[] = "m.id < ?";
            $params[] = $beforeId;
        }
        
        $sql = "SELECT m.*, 
                       CASE 
                           WHEN m.sender_type = 'agent' THEN u.name
                           ELSE ct.name
                       END as sender_name,
                       CASE 
                           WHEN m.sender_type = 'agent' THEN u.avatar
                           ELSE ct.avatar
                       END as sender_avatar,
                       m.ai_agent_id,
                       CASE 
                           WHEN m.ai_agent_id IS NOT NULL THEN aia.name
                           ELSE NULL
                       END as ai_agent_name
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'agent' AND m.sender_id = u.id AND m.ai_agent_id IS NULL
                LEFT JOIN contacts ct ON m.sender_type = 'contact' AND m.sender_id = ct.id
                LEFT JOIN ai_agents aia ON m.ai_agent_id = aia.id
                WHERE " . implode(' AND ', $whereConditions) . "
                ORDER BY m.created_at DESC";
        
        // Adicionar LIMIT e OFFSET se fornecidos
        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
            
            if ($offset !== null) {
                $sql .= " OFFSET ?";
                $params[] = $offset;
            }
        }
        
        $messages = Database::fetchAll($sql, $params);
        
        // Reverter ordem para ASC (mais antigas primeiro) já que buscamos DESC para paginação
        $messages = array_reverse($messages);
        
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
     * Contar total de mensagens em uma conversa
     */
    public static function countByConversation(int $conversationId): int
    {
        $sql = "SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?";
        $result = Database::fetchOne($sql, [$conversationId]);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Criar mensagem
     */
    public static function createMessage(array $data): int
    {
        // Garantir valores padrão
        $data['message_type'] = $data['message_type'] ?? 'text';
        $data['status'] = $data['status'] ?? 'sent';
        
        // Adicionar created_at se não estiver presente (timestamps está desabilitado)
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        
        // Serializar attachments se for array
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $data['attachments'] = json_encode($data['attachments']);
        }
        
        // Usar SQL direto para evitar problemas com updated_at
        $instance = new static();
        $fields = [];
        $values = [];
        $placeholders = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $instance->fillable) || $field === 'created_at') {
                $fields[] = "`{$field}`";
                $values[] = $value;
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO `{$instance->table}` (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        return Database::insert($sql, $values);
    }

    /**
     * Marcar mensagens como lidas
     */
    public static function markAsRead(int $conversationId, int $userId): bool
    {
        // Marcar como lidas apenas mensagens do contato que ainda não foram lidas
        $sql = "UPDATE messages 
                SET read_at = NOW() 
                WHERE conversation_id = ? 
                AND sender_type = 'contact'
                AND read_at IS NULL";
        
        $affected = Database::execute($sql, [$conversationId]);
        
        // Log para debug (remover em produção se necessário)
        if ($affected > 0) {
            error_log("Marcadas {$affected} mensagens como lidas na conversa {$conversationId}");

            // Invalidar cache de conversas para refletir unread_count atualizado
            try {
                \App\Services\ConversationService::invalidateCache($conversationId);
            } catch (\Throwable $e) {
                // Evitar quebra; apenas logar
                error_log("Erro ao invalidar cache após marcar como lida: " . $e->getMessage());
            }
        }
        
        return $affected > 0;
    }

    /**
     * Contar mensagens não lidas
     */
    public static function countUnread(int $conversationId, int $userId): int
    {
        // Contar apenas mensagens do contato que não foram lidas
        $sql = "SELECT COUNT(*) as total 
                FROM messages 
                WHERE conversation_id = ? 
                AND sender_type = 'contact'
                AND read_at IS NULL";
        
        $result = Database::fetch($sql, [$conversationId]);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Obter última mensagem da conversa
     */
    public static function getLastMessage(int $conversationId): ?array
    {
        $sql = "SELECT * FROM messages 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        return Database::fetch($sql, [$conversationId]);
    }

    /**
     * Atualizar status de mensagem
     */
    public static function updateStatus(int $messageId, string $status, ?string $errorMessage = null, ?string $deliveredAt = null, ?string $readAt = null): bool
    {
        $updates = ['status' => $status];
        
        if ($errorMessage !== null) {
            $updates['error_message'] = $errorMessage;
        }
        
        if ($deliveredAt !== null) {
            $updates['delivered_at'] = $deliveredAt;
        } elseif ($status === 'delivered' && empty($updates['delivered_at'])) {
            $updates['delivered_at'] = date('Y-m-d H:i:s');
        }
        
        if ($readAt !== null) {
            $updates['read_at'] = $readAt;
        } elseif ($status === 'read' && empty($updates['read_at'])) {
            $updates['read_at'] = date('Y-m-d H:i:s');
        }
        
        return self::update($messageId, $updates);
    }

    /**
     * Buscar mensagem por external_id (ID externo do WhatsApp)
     */
    public static function findByExternalId(string $externalId): ?array
    {
        $sql = "SELECT * FROM messages WHERE external_id = ? LIMIT 1";
        return Database::fetch($sql, [$externalId]);
    }
    
    /**
     * Buscar mensagens dentro de uma conversa por conteúdo com filtros avançados
     */
    /**
     * Obter novas mensagens desde uma data específica
     */
    public static function getNewMessagesSince(int $conversationId, ?string $since = null): array
    {
        $sql = "SELECT m.*, 
                       CASE 
                           WHEN m.sender_type = 'agent' THEN u.name
                           ELSE ct.name
                       END as sender_name,
                       CASE 
                           WHEN m.sender_type = 'agent' THEN u.avatar
                           ELSE ct.avatar
                       END as sender_avatar,
                       m.ai_agent_id,
                       aia.name as ai_agent_name
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'agent' AND m.sender_id = u.id AND m.ai_agent_id IS NULL
                LEFT JOIN contacts ct ON m.sender_type = 'contact' AND m.sender_id = ct.id
                LEFT JOIN ai_agents aia ON m.ai_agent_id = aia.id
                WHERE m.conversation_id = ?";
        
        $params = [$conversationId];
        
        if ($since) {
            $sql .= " AND m.created_at > ?";
            $params[] = $since;
        }
        
        $sql .= " ORDER BY m.created_at ASC";
        
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

    public static function searchInConversation(int $conversationId, string $search, array $filters = []): array
    {
        $sql = "SELECT m.*, 
                       CASE 
                           WHEN m.ai_agent_id IS NOT NULL THEN aia.name
                           WHEN m.sender_type = 'agent' THEN u.name
                           ELSE ct.name
                       END as sender_name,
                       CASE 
                           WHEN m.sender_type = 'agent' THEN u.avatar
                           ELSE ct.avatar
                       END as sender_avatar,
                       m.ai_agent_id,
                       CASE 
                           WHEN m.ai_agent_id IS NOT NULL THEN aia.name
                           ELSE NULL
                       END as ai_agent_name
                FROM messages m
                LEFT JOIN users u ON m.sender_type = 'agent' AND m.sender_id = u.id AND m.ai_agent_id IS NULL
                LEFT JOIN contacts ct ON m.sender_type = 'contact' AND m.sender_id = ct.id
                LEFT JOIN ai_agents aia ON m.ai_agent_id = aia.id
                WHERE m.conversation_id = ?";
        
        $params = [$conversationId];
        
        // Filtro de busca por conteúdo (se fornecido)
        if (!empty($search)) {
            $sql .= " AND m.content LIKE ?";
            $params[] = "%{$search}%";
        }
        
        // Filtro por tipo de mensagem
        if (!empty($filters['message_type'])) {
            $sql .= " AND m.message_type = ?";
            $params[] = $filters['message_type'];
        }
        
        // Filtro por remetente (sender_type)
        if (!empty($filters['sender_type'])) {
            $sql .= " AND m.sender_type = ?";
            $params[] = $filters['sender_type'];
        }
        
        // Filtro por remetente específico (sender_id)
        if (!empty($filters['sender_id'])) {
            $sql .= " AND m.sender_id = ?";
            $params[] = $filters['sender_id'];
        }
        
        // Filtro por data (from)
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(m.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        // Filtro por data (to)
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(m.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        // Filtro por mensagens com anexos
        if (isset($filters['has_attachments']) && $filters['has_attachments'] === true) {
            $sql .= " AND m.attachments IS NOT NULL AND m.attachments != '' AND m.attachments != '[]'";
        }
        
        $sql .= " ORDER BY m.created_at DESC LIMIT 100";
        
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
}

