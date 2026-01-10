<?php
/**
 * Model AIKanbanAgentActionLog
 * Log detalhado de ações executadas pelos agentes Kanban
 */

namespace App\Models;

use App\Helpers\Database;

class AIKanbanAgentActionLog extends Model
{
    protected string $table = 'ai_kanban_agent_actions_log';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_kanban_agent_id',
        'execution_id',
        'conversation_id',
        'analysis_summary',
        'analysis_score',
        'conditions_met',
        'conditions_details',
        'actions_executed',
        'success',
        'error_message',
        'executed_at'
    ];
    protected bool $timestamps = false; // Usa executed_at ao invés de created_at/updated_at

    /**
     * Criar log de ação
     */
    public static function createLog(array $data): int
    {
        // Codificar campos JSON
        if (isset($data['conditions_details'])) {
            $data['conditions_details'] = !empty($data['conditions_details']) ? json_encode($data['conditions_details'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (isset($data['actions_executed'])) {
            $data['actions_executed'] = !empty($data['actions_executed']) ? json_encode($data['actions_executed'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
        }

        if (!isset($data['executed_at'])) {
            $data['executed_at'] = date('Y-m-d H:i:s');
        }

        return parent::create($data);
    }

    /**
     * Obter logs de uma conversa
     */
    public static function getByConversation(int $conversationId, int $limit = 50): array
    {
        $sql = "SELECT al.*, a.name as agent_name, e.execution_type
                FROM ai_kanban_agent_actions_log al
                INNER JOIN ai_kanban_agents a ON al.ai_kanban_agent_id = a.id
                LEFT JOIN ai_kanban_agent_executions e ON al.execution_id = e.id
                WHERE al.conversation_id = ? 
                ORDER BY al.executed_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$conversationId, $limit]);
    }

    /**
     * Decodificar JSON fields
     */
    public static function find(int $id): ?array
    {
        $log = parent::find($id);
        if ($log) {
            $log['conditions_details'] = $log['conditions_details'] ? json_decode($log['conditions_details'], true) : null;
            $log['actions_executed'] = $log['actions_executed'] ? json_decode($log['actions_executed'], true) : [];
        }
        return $log;
    }

    /**
     * Obter todos os logs (com decodificação JSON)
     */
    public static function all(): array
    {
        $logs = parent::all();
        foreach ($logs as &$log) {
            $log['conditions_details'] = $log['conditions_details'] ? json_decode($log['conditions_details'], true) : null;
            $log['actions_executed'] = $log['actions_executed'] ? json_decode($log['actions_executed'], true) : [];
        }
        return $logs;
    }
}

