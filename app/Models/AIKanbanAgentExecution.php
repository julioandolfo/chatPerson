<?php
/**
 * Model AIKanbanAgentExecution
 * Histórico de execuções dos agentes Kanban
 */

namespace App\Models;

use App\Helpers\Database;

class AIKanbanAgentExecution extends Model
{
    protected string $table = 'ai_kanban_agent_executions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'ai_kanban_agent_id',
        'execution_type',
        'started_at',
        'completed_at',
        'status',
        'conversations_analyzed',
        'conversations_acted_upon',
        'actions_executed',
        'errors_count',
        'results',
        'error_message'
    ];
    protected bool $timestamps = true;

    /**
     * Criar nova execução
     */
    public static function createExecution(int $agentId, string $executionType = 'scheduled'): int
    {
        return self::create([
            'ai_kanban_agent_id' => $agentId,
            'execution_type' => $executionType,
            'status' => 'running',
            'conversations_analyzed' => 0,
            'conversations_acted_upon' => 0,
            'actions_executed' => 0,
            'errors_count' => 0
        ]);
    }

    /**
     * Finalizar execução
     */
    public static function completeExecution(int $executionId, array $stats = [], ?string $errorMessage = null): bool
    {
        $data = [
            'completed_at' => date('Y-m-d H:i:s'),
            'status' => $errorMessage ? 'failed' : 'completed'
        ];

        if (!empty($stats)) {
            $data['conversations_analyzed'] = $stats['conversations_analyzed'] ?? 0;
            $data['conversations_acted_upon'] = $stats['conversations_acted_upon'] ?? 0;
            $data['actions_executed'] = $stats['actions_executed'] ?? 0;
            $data['errors_count'] = $stats['errors_count'] ?? 0;
            $data['results'] = !empty($stats['results']) ? json_encode($stats['results'], JSON_UNESCAPED_UNICODE) : null;
        }

        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        return self::update($executionId, $data);
    }

    /**
     * Obter execuções recentes
     */
    public static function getRecent(int $limit = 20): array
    {
        $sql = "SELECT e.*, a.name as agent_name
                FROM ai_kanban_agent_executions e
                INNER JOIN ai_kanban_agents a ON e.ai_kanban_agent_id = a.id
                ORDER BY e.started_at DESC 
                LIMIT ?";
        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Decodificar JSON fields
     */
    public static function find(int $id): ?array
    {
        $execution = parent::find($id);
        if ($execution && $execution['results']) {
            $execution['results'] = json_decode($execution['results'], true);
        }
        return $execution;
    }
}

