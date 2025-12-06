<?php
/**
 * Model AIAssistantLog
 * Logs de uso do Assistente IA
 */

namespace App\Models;

use App\Helpers\Database;

class AIAssistantLog extends Model
{
    protected string $table = 'ai_assistant_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'conversation_id', 'feature_key', 'ai_agent_id',
        'input_data', 'output_data', 'tokens_used', 'cost',
        'execution_time_ms', 'success', 'error_message'
    ];
    protected bool $timestamps = false; // Usa created_at manualmente

    /**
     * Criar log de uso
     */
    public static function log(
        int $userId,
        int $conversationId,
        string $featureKey,
        ?int $aiAgentId,
        array $inputData = [],
        array $outputData = [],
        int $tokensUsed = 0,
        float $cost = 0.0,
        int $executionTimeMs = 0,
        bool $success = true,
        ?string $errorMessage = null
    ): int {
        $data = [
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'feature_key' => $featureKey,
            'ai_agent_id' => $aiAgentId,
            'input_data' => !empty($inputData) ? json_encode($inputData, JSON_UNESCAPED_UNICODE) : null,
            'output_data' => !empty($outputData) ? json_encode($outputData, JSON_UNESCAPED_UNICODE) : null,
            'tokens_used' => $tokensUsed,
            'cost' => $cost,
            'execution_time_ms' => $executionTimeMs,
            'success' => $success ? 1 : 0,
            'error_message' => $errorMessage,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return self::create($data);
    }

    /**
     * Obter estatísticas de uso por funcionalidade
     */
    public static function getStatsByFeature(?string $featureKey = null, ?int $days = 30): array
    {
        $sql = "SELECT 
                    feature_key,
                    COUNT(*) as total_uses,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    AVG(execution_time_ms) as avg_execution_time,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_uses,
                    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_uses
                FROM ai_assistant_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $params = [$days];
        
        if ($featureKey) {
            $sql .= " AND feature_key = ?";
            $params[] = $featureKey;
        }
        
        $sql .= " GROUP BY feature_key ORDER BY total_uses DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter estatísticas por agente
     */
    public static function getStatsByAgent(?int $agentId = null, ?int $days = 30): array
    {
        $sql = "SELECT 
                    ai_agent_id,
                    COUNT(*) as total_uses,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    AVG(execution_time_ms) as avg_execution_time
                FROM ai_assistant_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND ai_agent_id IS NOT NULL";
        
        $params = [$days];
        
        if ($agentId) {
            $sql .= " AND ai_agent_id = ?";
            $params[] = $agentId;
        }
        
        $sql .= " GROUP BY ai_agent_id ORDER BY total_uses DESC";
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter logs recentes
     */
    public static function getRecent(int $limit = 50, ?int $userId = null, ?int $conversationId = null): array
    {
        $sql = "SELECT l.*, u.name as user_name, c.id as conversation_id, a.name as agent_name
                FROM ai_assistant_logs l
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN conversations c ON l.conversation_id = c.id
                LEFT JOIN ai_agents a ON l.ai_agent_id = a.id
                WHERE 1=1";
        
        $params = [];
        
        if ($userId) {
            $sql .= " AND l.user_id = ?";
            $params[] = $userId;
        }
        
        if ($conversationId) {
            $sql .= " AND l.conversation_id = ?";
            $params[] = $conversationId;
        }
        
        $sql .= " ORDER BY l.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Obter estatísticas de uso ao longo do tempo (para gráficos)
     */
    public static function getUsageOverTime(?int $days = 30, string $groupBy = 'day'): array
    {
        // Determinar formato de data baseado no agrupamento
        $dateFormat = '%Y-%m-%d'; // padrão
        switch ($groupBy) {
            case 'hour':
                $dateFormat = '%Y-%m-%d %H:00:00';
                break;
            case 'day':
                $dateFormat = '%Y-%m-%d';
                break;
            case 'week':
                $dateFormat = '%Y-%u';
                break;
            case 'month':
                $dateFormat = '%Y-%m';
                break;
        }
        
        $sql = "SELECT 
                    DATE_FORMAT(created_at, ?) as period,
                    COUNT(*) as uses,
                    SUM(tokens_used) as tokens,
                    SUM(cost) as cost,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_uses
                FROM ai_assistant_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY period
                ORDER BY period ASC";
        
        return Database::fetchAll($sql, [$dateFormat, $days]);
    }

    /**
     * Obter estatísticas de custo por modelo/agente
     */
    public static function getCostByModel(?int $days = 30): array
    {
        $sql = "SELECT 
                    a.model,
                    COUNT(*) as uses,
                    SUM(l.tokens_used) as total_tokens,
                    SUM(l.cost) as total_cost,
                    AVG(l.cost) as avg_cost_per_use
                FROM ai_assistant_logs l
                INNER JOIN ai_agents a ON l.ai_agent_id = a.id
                WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND a.model IS NOT NULL
                GROUP BY a.model
                ORDER BY total_cost DESC";
        
        return Database::fetchAll($sql, [$days]);
    }
}

