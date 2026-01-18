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
    protected array $fillable = ['conversation_id', 'agent_id', 'assigned_by', 'assigned_at', 'removed_at'];
    protected bool $timestamps = false;

    /**
     * Verificar se a tabela existe
     */
    private static function tableExists(): bool
    {
        static $exists = null;
        
        if ($exists === null) {
            try {
                $result = Database::fetch("SHOW TABLES LIKE 'conversation_assignments'");
                $exists = !empty($result);
                \App\Helpers\Logger::info("ConversationAssignment::tableExists - Tabela " . ($exists ? 'EXISTE' : 'NÃO EXISTE'));
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("ConversationAssignment::tableExists - Erro ao verificar: " . $e->getMessage());
                $exists = false;
            }
        }
        
        return $exists;
    }

    /**
     * Registrar atribuição de conversa
     */
    public static function recordAssignment(
        int $conversationId,
        ?int $agentId,
        ?int $assignedBy = null
    ): int {
        try {
            \App\Helpers\Logger::info("ConversationAssignment::recordAssignment - INÍCIO: conversation_id={$conversationId}, agent_id={$agentId}, assigned_by={$assignedBy}");
            
            // Verificar se a tabela existe
            if (!self::tableExists()) {
                \App\Helpers\Logger::warning("ConversationAssignment::recordAssignment - Tabela não existe, pulando registro");
                return 0;
            }
            
            // Se não há agente, não registrar
            if (!$agentId) {
                \App\Helpers\Logger::info("ConversationAssignment::recordAssignment - Agente vazio, pulando registro");
                return 0;
            }
            
            // ✅ PROTEÇÃO CONTRA DUPLICATAS: Verificar se já existe registro recente (últimos 10 segundos)
            $recentAssignment = Database::fetch(
                "SELECT id, assigned_at FROM conversation_assignments 
                 WHERE conversation_id = ? 
                 AND agent_id = ? 
                 AND removed_at IS NULL
                 AND assigned_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
                 ORDER BY assigned_at DESC 
                 LIMIT 1",
                [$conversationId, $agentId]
            );
            
            if ($recentAssignment) {
                \App\Helpers\Logger::warning("ConversationAssignment::recordAssignment - Registro duplicado detectado (menos de 10s), pulando. Último registro ID: {$recentAssignment['id']} em {$recentAssignment['assigned_at']}");
                return (int)$recentAssignment['id'];
            }
            
            $data = [
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'assigned_by' => $assignedBy,
                'assigned_at' => date('Y-m-d H:i:s')
            ];
            
            \App\Helpers\Logger::info("ConversationAssignment::recordAssignment - Dados preparados: " . json_encode($data));
            
            $id = self::create($data);
            
            \App\Helpers\Logger::info("ConversationAssignment::recordAssignment - Registro criado com ID: {$id}");
            
            return $id;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("ConversationAssignment::recordAssignment - EXCEÇÃO CAPTURADA: " . $e->getMessage());
            \App\Helpers\Logger::error("ConversationAssignment::recordAssignment - Stack trace: " . $e->getTraceAsString());
            // NÃO re-lançar - apenas logar e retornar 0 para não quebrar o fluxo
            return 0;
        }
    }

    /**
     * Marcar atribuição como removida
     */
    public static function recordRemoval(int $conversationId, int $agentId): bool
    {
        try {
            \App\Helpers\Logger::info("ConversationAssignment::recordRemoval - INÍCIO: conversation_id={$conversationId}, agent_id={$agentId}");
            
            // Verificar se a tabela existe
            if (!self::tableExists()) {
                \App\Helpers\Logger::warning("ConversationAssignment::recordRemoval - Tabela não existe, pulando remoção");
                return false;
            }
            
            $result = Database::query(
                "UPDATE conversation_assignments SET removed_at = NOW() 
                 WHERE conversation_id = ? AND agent_id = ? AND removed_at IS NULL",
                [$conversationId, $agentId]
            );
            
            \App\Helpers\Logger::info("ConversationAssignment::recordRemoval - Resultado: " . ($result !== false ? 'sucesso' : 'falha'));
            
            return $result !== false;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("ConversationAssignment::recordRemoval - ERRO: " . $e->getMessage());
            \App\Helpers\Logger::error("ConversationAssignment::recordRemoval - Stack trace: " . $e->getTraceAsString());
            // NÃO re-lançar - apenas logar e retornar false
            return false;
        }
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
