<?php
/**
 * Model AutomationExecution
 * Logs de execução das automações
 */

namespace App\Models;

use App\Helpers\Database;

class AutomationExecution extends Model
{
    protected string $table = 'automation_executions';
    protected string $primaryKey = 'id';
    protected array $fillable = ['automation_id', 'conversation_id', 'node_id', 'status', 'execution_data', 'error_message', 'started_at', 'completed_at'];
    protected bool $timestamps = true;

    /**
     * Criar log de execução
     */
    public static function createLog(int $automationId, ?int $conversationId = null, string $status = 'pending', array $executionData = []): int
    {
        $data = [
            'automation_id' => $automationId,
            'conversation_id' => $conversationId,
            'status' => $status,
            'started_at' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($executionData)) {
            $data['execution_data'] = json_encode($executionData);
        }
        
        return self::create($data);
    }

    /**
     * Atualizar status da execução
     */
    public static function updateStatus(int $executionId, string $status, ?string $errorMessage = null, ?int $nodeId = null): bool
    {
        $data = [
            'status' => $status,
            'node_id' => $nodeId
        ];
        
        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }
        
        if ($status === 'completed' || $status === 'failed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        
        return self::update($executionId, $data);
    }

    /**
     * Obter execuções de uma automação
     */
    public static function getByAutomation(int $automationId, int $limit = 50): array
    {
        $sql = "SELECT ae.*, 
                       c.id as conversation_id,
                       CONCAT('Conversa #', c.id) as conversation_subject,
                       ct.name as contact_name, ct.phone as contact_phone
                FROM automation_executions ae
                LEFT JOIN conversations c ON ae.conversation_id = c.id
                LEFT JOIN contacts ct ON c.contact_id = ct.id
                WHERE ae.automation_id = ?
                ORDER BY ae.created_at DESC
                LIMIT ?";
        
        $results = Database::fetchAll($sql, [$automationId, $limit]);
        
        // Decodificar execution_data se for JSON
        foreach ($results as &$result) {
            if (!empty($result['execution_data'])) {
                $decoded = json_decode($result['execution_data'], true);
                if ($decoded !== null) {
                    $result['execution_data'] = $decoded;
                }
            }
        }
        
        return $results;
    }

    /**
     * Obter execuções de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        $sql = "SELECT ae.*, a.name as automation_name
                FROM automation_executions ae
                INNER JOIN automations a ON ae.automation_id = a.id
                WHERE ae.conversation_id = ?
                ORDER BY ae.created_at DESC";
        
        return Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Obter estatísticas de execução
     */
    public static function getStats(int $automationId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM automation_executions
                WHERE automation_id = ?";
        
        $params = [$automationId];
        
        if ($dateFrom) {
            $sql .= " AND created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $sql .= " AND created_at <= ?";
            $params[] = $dateTo;
        }
        
        return Database::fetch($sql, $params);
    }
}

