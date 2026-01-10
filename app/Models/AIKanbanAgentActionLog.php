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
        \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Iniciando criação de log", 'kanban_agents.log');
        \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Dados recebidos: " . json_encode($data), 'kanban_agents.log');
        
        try {
            // Codificar campos JSON
            if (isset($data['conditions_details'])) {
                \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Codificando conditions_details", 'kanban_agents.log');
                $data['conditions_details'] = !empty($data['conditions_details']) ? json_encode($data['conditions_details'], JSON_UNESCAPED_UNICODE) : null;
            }
            if (isset($data['actions_executed'])) {
                \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Codificando actions_executed", 'kanban_agents.log');
                $data['actions_executed'] = !empty($data['actions_executed']) ? json_encode($data['actions_executed'], JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
            }

            if (!isset($data['executed_at'])) {
                $data['executed_at'] = date('Y-m-d H:i:s');
            }

            \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Dados após codificação: " . json_encode($data), 'kanban_agents.log');
            \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Chamando parent::create()", 'kanban_agents.log');
            
            $id = parent::create($data);
            
            \App\Helpers\Logger::info("AIKanbanAgentActionLog::createLog - Log criado com sucesso (ID: $id)", 'kanban_agents.log');
            
            return $id;
        } catch (\Throwable $e) {
            \App\Helpers\Logger::error("AIKanbanAgentActionLog::createLog - ERRO ao criar log", 'kanban_agents.log');
            \App\Helpers\Logger::error("AIKanbanAgentActionLog::createLog - Tipo: " . get_class($e), 'kanban_agents.log');
            \App\Helpers\Logger::error("AIKanbanAgentActionLog::createLog - Mensagem: " . $e->getMessage(), 'kanban_agents.log');
            \App\Helpers\Logger::error("AIKanbanAgentActionLog::createLog - Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")", 'kanban_agents.log');
            \App\Helpers\Logger::error("AIKanbanAgentActionLog::createLog - Stack trace: " . $e->getTraceAsString(), 'kanban_agents.log');
            throw $e;
        }
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

