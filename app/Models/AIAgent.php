<?php
/**
 * Model AIAgent
 * Agentes de IA para atendimento automatizado
 */

namespace App\Models;

use App\Helpers\Database;

class AIAgent extends Model
{
    protected string $table = 'ai_agents';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'description', 'agent_type', 'prompt', 'model', 'temperature', 'max_tokens', 'enabled', 'max_conversations', 'current_conversations', 'settings'];
    protected bool $timestamps = true;

    /**
     * Obter tools do agente
     */
    public static function getTools(int $agentId): array
    {
        $sql = "SELECT t.*, at.config as agent_tool_config, at.enabled as tool_enabled
                FROM ai_tools t
                INNER JOIN ai_agent_tools at ON t.id = at.ai_tool_id
                WHERE at.ai_agent_id = ? AND at.enabled = TRUE AND t.enabled = TRUE
                ORDER BY t.name ASC";
        return Database::fetchAll($sql, [$agentId]);
    }

    /**
     * Adicionar tool ao agente
     */
    public static function addTool(int $agentId, int $toolId, array $config = [], bool $enabled = true): bool
    {
        $sql = "INSERT INTO ai_agent_tools (ai_agent_id, ai_tool_id, config, enabled)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE config = ?, enabled = ?";
        $configJson = !empty($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : null;
        return Database::execute($sql, [$agentId, $toolId, $configJson, $enabled ? 1 : 0, $configJson, $enabled ? 1 : 0]);
    }

    /**
     * Remover tool do agente
     */
    public static function removeTool(int $agentId, int $toolId): bool
    {
        $sql = "DELETE FROM ai_agent_tools WHERE ai_agent_id = ? AND ai_tool_id = ?";
        return Database::execute($sql, [$agentId, $toolId]);
    }

    /**
     * Obter conversas ativas do agente
     */
    public static function getActiveConversations(int $agentId): array
    {
        $sql = "SELECT ac.*, c.status as conversation_status, ct.name as contact_name
                FROM ai_conversations ac
                INNER JOIN conversations c ON ac.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE ac.ai_agent_id = ? AND ac.status = 'active'
                ORDER BY ac.created_at DESC";
        return Database::fetchAll($sql, [$agentId]);
    }

    /**
     * Atualizar contagem de conversas
     */
    public static function updateConversationsCount(int $agentId): bool
    {
        $count = Database::fetch(
            "SELECT COUNT(*) as total FROM ai_conversations WHERE ai_agent_id = ? AND status = 'active'",
            [$agentId]
        )['total'] ?? 0;
        
        return self::update($agentId, ['current_conversations' => (int)$count]);
    }

    /**
     * Verificar se agente pode receber mais conversas
     */
    public static function canReceiveMoreConversations(int $agentId): bool
    {
        $agent = self::find($agentId);
        if (!$agent || !$agent['enabled']) {
            return false;
        }

        if ($agent['max_conversations'] === null) {
            return true; // Sem limite
        }

        return ($agent['current_conversations'] ?? 0) < $agent['max_conversations'];
    }

    /**
     * Obter agentes por tipo
     */
    public static function getByType(string $type): array
    {
        $sql = "SELECT * FROM ai_agents 
                WHERE agent_type = ? AND enabled = TRUE 
                ORDER BY name ASC";
        return Database::fetchAll($sql, [$type]);
    }

    /**
     * Obter agentes disponíveis para atribuição
     */
    public static function getAvailableAgents(?string $type = null): array
    {
        $sql = "SELECT * FROM ai_agents WHERE enabled = TRUE";
        $params = [];
        
        if ($type !== null) {
            $sql .= " AND agent_type = ?";
            $params[] = $type;
        }
        
        $sql .= " AND (max_conversations IS NULL OR current_conversations < max_conversations)";
        $sql .= " ORDER BY name ASC";
        
        return Database::fetchAll($sql, $params);
    }
}

