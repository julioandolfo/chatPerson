<?php
/**
 * Model AutomationDelay
 * Representa um delay agendado de automação
 */

namespace App\Models;

use App\Helpers\Database;

class AutomationDelay extends Model
{
    protected string $table = 'automation_delays';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'automation_id', 'execution_id', 'conversation_id', 'node_id',
        'delay_seconds', 'scheduled_at', 'executed_at', 'status',
        'node_data', 'next_nodes', 'error_message'
    ];
    protected bool $timestamps = true;

    /**
     * Criar delay agendado
     */
    public static function schedule(
        int $automationId,
        int $conversationId,
        string $nodeId,
        int $delaySeconds,
        array $nodeData = [],
        array $nextNodes = [],
        ?int $executionId = null
    ): int {
        $scheduledAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        
        $data = [
            'automation_id' => $automationId,
            'execution_id' => $executionId,
            'conversation_id' => $conversationId,
            'node_id' => $nodeId,
            'delay_seconds' => $delaySeconds,
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
            'node_data' => json_encode($nodeData),
            'next_nodes' => json_encode($nextNodes)
        ];
        
        return self::create($data);
    }

    /**
     * Obter delays pendentes para execução
     */
    public static function getPendingDelays(int $limit = 100): array
    {
        $sql = "SELECT * FROM automation_delays 
                WHERE status = 'pending' 
                AND scheduled_at <= NOW()
                ORDER BY scheduled_at ASC 
                LIMIT ?";
        
        return Database::fetchAll($sql, [$limit]);
    }

    /**
     * Marcar delay como executando
     */
    public static function markAsExecuting(int $id): bool
    {
        return self::update($id, [
            'status' => 'executing',
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar delay como concluído
     */
    public static function markAsCompleted(int $id): bool
    {
        return self::update($id, [
            'status' => 'completed',
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar delay como falhou
     */
    public static function markAsFailed(int $id, string $errorMessage): bool
    {
        return self::update($id, [
            'status' => 'failed',
            'executed_at' => date('Y-m-d H:i:s'),
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Cancelar delay
     */
    public static function cancel(int $id): bool
    {
        return self::update($id, [
            'status' => 'cancelled'
        ]);
    }

    /**
     * Cancelar todos os delays de uma conversa
     */
    public static function cancelByConversation(int $conversationId): int
    {
        $sql = "UPDATE automation_delays 
                SET status = 'cancelled' 
                WHERE conversation_id = ? 
                AND status IN ('pending', 'executing')";
        
        return Database::execute($sql, [$conversationId]);
    }

    /**
     * Cancelar todos os delays de uma execução
     */
    public static function cancelByExecution(int $executionId): int
    {
        $sql = "UPDATE automation_delays 
                SET status = 'cancelled' 
                WHERE execution_id = ? 
                AND status IN ('pending', 'executing')";
        
        return Database::execute($sql, [$executionId]);
    }

    /**
     * Limpar delays antigos (completados ou falhados há mais de 30 dias)
     */
    public static function cleanOldDelays(int $days = 30): int
    {
        $sql = "DELETE FROM automation_delays 
                WHERE status IN ('completed', 'failed', 'cancelled')
                AND executed_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return Database::execute($sql, [$days]);
    }
}

