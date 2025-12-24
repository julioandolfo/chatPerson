<?php
/**
 * Model AIConversation
 * Logs e histórico de conversas com agentes de IA
 */

namespace App\Models;

use App\Helpers\Database;

class AIConversation extends Model
{
    protected string $table = 'ai_conversations';
    protected string $primaryKey = 'id';
    protected array $fillable = ['conversation_id', 'ai_agent_id', 'messages', 'tools_used', 'tokens_used', 'tokens_prompt', 'tokens_completion', 'cost', 'status', 'escalated_to_user_id', 'metadata'];
    protected bool $timestamps = true;

    /**
     * Obter conversa de IA por conversation_id
     */
    public static function getByConversationId(int $conversationId): ?array
    {
        // Primeiro, tentar encontrar uma conversa ativa
        $sql = "SELECT * FROM ai_conversations 
                WHERE conversation_id = ? AND status = 'active' 
                ORDER BY created_at DESC 
                LIMIT 1";
        $activeConversation = \App\Helpers\Database::fetch($sql, [$conversationId]);
        
        if ($activeConversation) {
            return $activeConversation;
        }
        
        // Se não houver ativa, retornar a mais recente (para histórico)
        $sql = "SELECT * FROM ai_conversations 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$conversationId]);
    }

    /**
     * Obter conversas de um agente
     */
    public static function getByAgent(int $agentId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT ac.*, c.status as conversation_status, ct.name as contact_name, ct.phone as contact_phone
                FROM ai_conversations ac
                INNER JOIN conversations c ON ac.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE ac.ai_agent_id = ?
                ORDER BY ac.created_at DESC
                LIMIT ? OFFSET ?";
        return Database::fetchAll($sql, [$agentId, $limit, $offset]);
    }

    /**
     * Atualizar status
     */
    public static function updateStatus(int $id, string $status, ?int $escalatedToUserId = null): bool
    {
        $data = ['status' => $status];
        if ($escalatedToUserId !== null) {
            $data['escalated_to_user_id'] = $escalatedToUserId;
        }
        return self::update($id, $data);
    }

    /**
     * Adicionar mensagem
     */
    public static function addMessage(int $id, array $message): bool
    {
        $conversation = self::find($id);
        if (!$conversation) {
            return false;
        }
        
        $messages = is_string($conversation['messages']) 
            ? json_decode($conversation['messages'], true) 
            : ($conversation['messages'] ?? []);
        
        $messages[] = $message;
        
        return self::update($id, ['messages' => json_encode($messages, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Registrar uso de tool
     */
    public static function logToolUsage(int $id, string $toolName, array $toolCall, array $toolResult): bool
    {
        $conversation = self::find($id);
        if (!$conversation) {
            return false;
        }
        
        $toolsUsed = is_string($conversation['tools_used']) 
            ? json_decode($conversation['tools_used'], true) 
            : ($conversation['tools_used'] ?? []);
        
        $toolsUsed[] = [
            'tool' => $toolName,
            'call' => $toolCall,
            'result' => $toolResult,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        return self::update($id, ['tools_used' => json_encode($toolsUsed, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Atualizar estatísticas de tokens e custo
     */
    public static function updateStats(int $id, int $tokensUsed, int $tokensPrompt, int $tokensCompletion, float $cost): bool
    {
        return self::update($id, [
            'tokens_used' => $tokensUsed,
            'tokens_prompt' => $tokensPrompt,
            'tokens_completion' => $tokensCompletion,
            'cost' => $cost
        ]);
    }

    /**
     * Obter estatísticas de um agente
     */
    public static function getAgentStats(int $agentId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_conversations,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    AVG(tokens_used) as avg_tokens,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_conversations,
                    COUNT(CASE WHEN status = 'escalated' THEN 1 END) as escalated_conversations
                FROM ai_conversations
                WHERE ai_agent_id = ?";
        $params = [$agentId];
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
            $params[] = $startDate . " 00:00:00";
        }
        if ($endDate) {
            $sql .= " AND created_at <= ?";
            $params[] = $endDate . " 23:59:59";
        }
        
        return Database::fetch($sql, $params) ?? [];
    }

    /**
     * Contar total de conversas de um agente
     */
    public static function countByAgent(int $agentId): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM ai_conversations ac
                INNER JOIN conversations c ON ac.conversation_id = c.id
                WHERE ac.ai_agent_id = ?";
        $result = Database::fetch($sql, [$agentId]);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Obter histórico completo de uma conversa de IA
     */
    public static function getHistory(int $id): ?array
    {
        $sql = "SELECT ac.*, 
                       c.status as conversation_status, 
                       c.channel,
                       ct.name as contact_name, 
                       ct.phone as contact_phone,
                       ct.email as contact_email,
                       u.name as escalated_to_name
                FROM ai_conversations ac
                INNER JOIN conversations c ON ac.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                LEFT JOIN users u ON ac.escalated_to_user_id = u.id
                WHERE ac.id = ?";
        
        $conversation = Database::fetch($sql, [$id]);
        
        if (!$conversation) {
            return null;
        }
        
        // Decodificar JSON fields
        if (is_string($conversation['messages'])) {
            $conversation['messages'] = json_decode($conversation['messages'], true) ?? [];
        }
        if (is_string($conversation['tools_used'])) {
            $conversation['tools_used'] = json_decode($conversation['tools_used'], true) ?? [];
        }
        if (is_string($conversation['metadata'])) {
            $conversation['metadata'] = json_decode($conversation['metadata'], true) ?? [];
        }
        
        // Buscar mensagens da conversa relacionada
        $messagesSql = "SELECT m.*, 
                               CASE 
                                   WHEN m.sender_type = 'contact' THEN ct.name
                                   WHEN m.sender_type = 'agent' AND m.user_id IS NOT NULL THEN u.name
                                   WHEN m.sender_type = 'agent' AND m.ai_agent_id IS NOT NULL THEN aia.name
                                   ELSE 'Sistema'
                               END as sender_name
                        FROM messages m
                        LEFT JOIN contacts ct ON m.contact_id = ct.id
                        LEFT JOIN users u ON m.user_id = u.id
                        LEFT JOIN ai_agents aia ON m.ai_agent_id = aia.id
                        WHERE m.conversation_id = ?
                        ORDER BY m.created_at ASC";
        
        $conversation['conversation_messages'] = Database::fetchAll($messagesSql, [$conversation['conversation_id']]);
        
        return $conversation;
    }
}

