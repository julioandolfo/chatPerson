<?php
/**
 * Model ConversationAssignment
 * Histórico de atribuições de conversas a agentes
 */

namespace App\Models;

use App\Helpers\Database;

class ConversationAssignment extends Model
{
    protected string $table = 'conversation_assignments';
    protected string $primaryKey = 'id';
    protected array $fillable = ['conversation_id', 'agent_id', 'assigned_by', 'assigned_at'];
    protected bool $timestamps = false;

    /**
     * Registrar atribuição de conversa
     */
    public static function recordAssignment(
        int $conversationId,
        ?int $agentId,
        ?int $assignedBy = null
    ): int {
        $data = [
            'conversation_id' => $conversationId,
            'agent_id' => $agentId,
            'assigned_by' => $assignedBy,
            'assigned_at' => date('Y-m-d H:i:s')
        ];
        
        return self::create($data);
    }

    /**
     * Obter histórico de atribuições de uma conversa
     */
    public static function getConversationHistory(int $conversationId): array
    {
        $sql = "SELECT ca.*, 
                       u.name as agent_name,
                       assigned.name as assigned_by_name
                FROM conversation_assignments ca
                LEFT JOIN users u ON ca.agent_id = u.id
                LEFT JOIN users assigned ON ca.assigned_by = assigned.id
                WHERE ca.conversation_id = ?
                ORDER BY ca.assigned_at ASC";
        
        return Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Obter todas as conversas atribuídas a um agente no período (incluindo histórico)
     */
    public static function getAgentConversations(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $sql = "SELECT DISTINCT ca.conversation_id
                FROM conversation_assignments ca
                WHERE ca.agent_id = ?";
        
        $params = [$agentId];
        
        if ($dateFrom) {
            $sql .= " AND ca.assigned_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND ca.assigned_at <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY ca.assigned_at DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Contar conversas únicas atribuídas a um agente no período
     */
    public static function countAgentConversations(
        int $agentId,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        $sql = "SELECT COUNT(DISTINCT ca.conversation_id) as count
                FROM conversation_assignments ca
                WHERE ca.agent_id = ?";
        
        $params = [$agentId];
        
        if ($dateFrom) {
            $sql .= " AND ca.assigned_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND ca.assigned_at <= ?";
            $params[] = $dateTo;
        }
        
        $result = Database::fetch($sql, $params);
        return (int)($result['count'] ?? 0);
    }

    /**
     * Verificar se um agente já foi atribuído a uma conversa
     */
    public static function wasAgentAssigned(int $conversationId, int $agentId): bool
    {
        $sql = "SELECT COUNT(*) as count 
                FROM conversation_assignments 
                WHERE conversation_id = ? AND agent_id = ?";
        
        $result = Database::fetch($sql, [$conversationId, $agentId]);
        return (int)($result['count'] ?? 0) > 0;
    }

    /**
     * Obter último agente atribuído a uma conversa
     */
    public static function getLastAssignedAgent(int $conversationId): ?array
    {
        $sql = "SELECT ca.*, u.name as agent_name
                FROM conversation_assignments ca
                LEFT JOIN users u ON ca.agent_id = u.id
                WHERE ca.conversation_id = ?
                ORDER BY ca.assigned_at DESC
                LIMIT 1";
        
        return Database::fetch($sql, [$conversationId]);
    }
}
