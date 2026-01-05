<?php
/**
 * Model ApiLog
 * Logs de requisições da API
 */

namespace App\Models;

use App\Helpers\Database;

class ApiLog extends Model
{
    protected string $table = 'api_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'token_id',
        'user_id',
        'endpoint',
        'method',
        'request_body',
        'request_headers',
        'response_code',
        'response_body',
        'error_message',
        'ip_address',
        'user_agent',
        'execution_time_ms'
    ];
    protected bool $timestamps = false; // Usa apenas created_at
    
    /**
     * Criar log de requisição
     */
    public static function logRequest(array $data): int
    {
        // Limitar tamanho dos campos para não estourar o banco
        if (isset($data['request_body']) && strlen($data['request_body']) > 65000) {
            $data['request_body'] = substr($data['request_body'], 0, 65000) . '... [truncated]';
        }
        
        if (isset($data['response_body']) && strlen($data['response_body']) > 65000) {
            $data['response_body'] = substr($data['response_body'], 0, 65000) . '... [truncated]';
        }
        
        return self::create($data);
    }
    
    /**
     * Obter logs por token
     */
    public static function getByToken(int $tokenId, int $limit = 100): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM api_logs 
                WHERE token_id = :token_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue('token_id', $tokenId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter logs por usuário
     */
    public static function getByUser(int $userId, int $limit = 100): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM api_logs 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $db->prepare($sql);
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter estatísticas de uso
     */
    public static function getStats(?int $tokenId = null, ?int $userId = null): array
    {
        $db = Database::getInstance();
        
        $where = [];
        $params = [];
        
        if ($tokenId) {
            $where[] = "token_id = :token_id";
            $params['token_id'] = $tokenId;
        }
        
        if ($userId) {
            $where[] = "user_id = :user_id";
            $params['user_id'] = $userId;
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as requests_today,
                    COUNT(CASE WHEN response_code >= 200 AND response_code < 300 THEN 1 END) as success_requests,
                    COUNT(CASE WHEN response_code >= 400 THEN 1 END) as error_requests,
                    AVG(execution_time_ms) as avg_execution_time,
                    MAX(execution_time_ms) as max_execution_time
                FROM api_logs
                {$whereClause}";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Limpar logs antigos
     */
    public static function cleanOldLogs(int $daysToKeep = 30): int
    {
        $db = Database::getInstance();
        
        $sql = "DELETE FROM api_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }
}
