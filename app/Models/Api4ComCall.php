<?php
/**
 * Model Api4ComCall
 */

namespace App\Models;

class Api4ComCall extends Model
{
    protected string $table = 'api4com_calls';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'conversation_id', 'contact_id', 'agent_id', 'api4com_account_id',
        'api4com_extension_id', 'api4com_call_id', 'direction', 'status',
        'duration', 'started_at', 'answered_at', 'ended_at',
        'from_number', 'to_number', 'recording_url', 'error_message', 'metadata'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar chamadas por conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        return self::where('conversation_id', '=', $conversationId);
    }

    /**
     * Buscar chamadas por contato
     */
    public static function getByContact(int $contactId): array
    {
        return self::where('contact_id', '=', $contactId);
    }

    /**
     * Buscar chamadas por agente
     */
    public static function getByAgent(int $agentId): array
    {
        return self::where('agent_id', '=', $agentId);
    }

    /**
     * Buscar por ID da Api4Com
     */
    public static function findByApi4ComId(string $api4comCallId): ?array
    {
        return self::whereFirst('api4com_call_id', '=', $api4comCallId);
    }

    /**
     * Buscar chamadas ativas (não finalizadas)
     */
    public static function getActive(): array
    {
        return self::where('status', 'IN', ['initiated', 'ringing', 'answered']);
    }

    /**
     * Atualizar status da chamada
     */
    public static function updateStatus(int $callId, string $status, ?array $additionalData = null): bool
    {
        $data = ['status' => $status];
        
        if ($status === 'answered' && !empty($additionalData['answered_at'])) {
            $data['answered_at'] = $additionalData['answered_at'];
        }
        if ($status === 'ended' && !empty($additionalData['ended_at'])) {
            $data['ended_at'] = $additionalData['ended_at'];
            if (!empty($additionalData['duration'])) {
                $data['duration'] = $additionalData['duration'];
            }
        }
        if ($status === 'ringing' && !empty($additionalData['started_at'])) {
            $data['started_at'] = $additionalData['started_at'];
        }
        
        if (!empty($additionalData['recording_url'])) {
            $data['recording_url'] = $additionalData['recording_url'];
        }
        if (!empty($additionalData['error_message'])) {
            $data['error_message'] = $additionalData['error_message'];
        }
        if (!empty($additionalData['metadata'])) {
            $data['metadata'] = json_encode($additionalData['metadata']);
        }
        
        return self::update($callId, $data);
    }

    /**
     * Buscar chamadas com dados do agente
     */
    public static function getByConversationWithAgent(int $conversationId): array
    {
        $sql = "SELECT c.*, u.name as agent_name 
                FROM api4com_calls c 
                LEFT JOIN users u ON c.agent_id = u.id 
                WHERE c.conversation_id = ? 
                ORDER BY c.created_at DESC";
        return \App\Helpers\Database::fetchAll($sql, [$conversationId]);
    }

    /**
     * Estatísticas gerais de chamadas
     */
    public static function getStats(?string $dateFrom = null, ?string $dateTo = null, ?array $agentIds = null): array
    {
        $params = [];
        $whereConditions = [];
        
        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        if ($agentIds && count($agentIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
            $whereConditions[] = "agent_id IN ({$placeholders})";
            $params = array_merge($params, $agentIds);
        }
        
        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'answered' OR status = 'ended' THEN 1 ELSE 0 END) as answered_calls,
                    SUM(CASE WHEN status = 'failed' OR status = 'no_answer' OR status = 'busy' THEN 1 ELSE 0 END) as failed_calls,
                    SUM(CASE WHEN status = 'initiated' OR status = 'ringing' THEN 1 ELSE 0 END) as pending_calls,
                    AVG(CASE WHEN duration > 0 THEN duration ELSE NULL END) as avg_duration,
                    SUM(duration) as total_duration,
                    COUNT(DISTINCT agent_id) as unique_agents,
                    COUNT(DISTINCT contact_id) as unique_contacts
                FROM api4com_calls {$whereClause}";
        
        $result = \App\Helpers\Database::fetch($sql, $params);
        
        return [
            'total_calls' => (int)($result['total_calls'] ?? 0),
            'answered_calls' => (int)($result['answered_calls'] ?? 0),
            'failed_calls' => (int)($result['failed_calls'] ?? 0),
            'pending_calls' => (int)($result['pending_calls'] ?? 0),
            'avg_duration' => round((float)($result['avg_duration'] ?? 0)),
            'total_duration' => (int)($result['total_duration'] ?? 0),
            'unique_agents' => (int)($result['unique_agents'] ?? 0),
            'unique_contacts' => (int)($result['unique_contacts'] ?? 0),
            'success_rate' => ($result['total_calls'] ?? 0) > 0 
                ? round(($result['answered_calls'] ?? 0) / $result['total_calls'] * 100, 1) 
                : 0
        ];
    }

    /**
     * Estatísticas por agente
     */
    public static function getStatsByAgent(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = [$agentId];
        $whereConditions = ['agent_id = ?'];
        
        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        
        $sql = "SELECT 
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN status = 'answered' OR status = 'ended' THEN 1 ELSE 0 END) as answered_calls,
                    SUM(CASE WHEN status = 'failed' OR status = 'no_answer' OR status = 'busy' THEN 1 ELSE 0 END) as failed_calls,
                    AVG(CASE WHEN duration > 0 THEN duration ELSE NULL END) as avg_duration,
                    SUM(duration) as total_duration
                FROM api4com_calls {$whereClause}";
        
        $result = \App\Helpers\Database::fetch($sql, $params);
        
        return [
            'total_calls' => (int)($result['total_calls'] ?? 0),
            'answered_calls' => (int)($result['answered_calls'] ?? 0),
            'failed_calls' => (int)($result['failed_calls'] ?? 0),
            'avg_duration' => round((float)($result['avg_duration'] ?? 0)),
            'total_duration' => (int)($result['total_duration'] ?? 0),
            'success_rate' => ($result['total_calls'] ?? 0) > 0 
                ? round(($result['answered_calls'] ?? 0) / $result['total_calls'] * 100, 1) 
                : 0
        ];
    }

    /**
     * Histórico de chamadas por agente
     */
    public static function getHistoryByAgent(int $agentId, int $limit = 50): array
    {
        $sql = "SELECT c.*, 
                       ct.name as contact_name, 
                       ct.phone as contact_phone,
                       cv.id as conversation_id
                FROM api4com_calls c 
                LEFT JOIN contacts ct ON c.contact_id = ct.id 
                LEFT JOIN conversations cv ON c.conversation_id = cv.id
                WHERE c.agent_id = ? 
                ORDER BY c.created_at DESC 
                LIMIT ?";
        return \App\Helpers\Database::fetchAll($sql, [$agentId, $limit]);
    }

    /**
     * Chamadas recentes (para dashboard)
     */
    public static function getRecent(int $limit = 10, ?array $agentIds = null): array
    {
        $params = [];
        $whereClause = '';
        
        if ($agentIds && count($agentIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
            $whereClause = "WHERE agent_id IN ({$placeholders})";
            $params = $agentIds;
        }
        
        $params[] = $limit;
        
        $sql = "SELECT c.*, 
                       u.name as agent_name, 
                       ct.name as contact_name,
                       ct.phone as contact_phone
                FROM api4com_calls c 
                LEFT JOIN users u ON c.agent_id = u.id 
                LEFT JOIN contacts ct ON c.contact_id = ct.id 
                {$whereClause}
                ORDER BY c.created_at DESC 
                LIMIT ?";
        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Chamadas por hora (para gráfico)
     */
    public static function getCallsByHour(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = [];
        $whereConditions = [];
        
        if ($dateFrom) {
            $whereConditions[] = "created_at >= ?";
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $whereConditions[] = "created_at <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        $sql = "SELECT HOUR(created_at) as hour, COUNT(*) as count 
                FROM api4com_calls {$whereClause} 
                GROUP BY HOUR(created_at) 
                ORDER BY hour";
        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Formatar duração em string legível
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) return '0s';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || count($parts) === 0) $parts[] = "{$secs}s";
        
        return implode(' ', $parts);
    }

    /**
     * Obter label de status em português
     */
    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            'initiated' => 'Iniciada',
            'ringing' => 'Chamando',
            'answered' => 'Atendida',
            'ended' => 'Finalizada',
            'failed' => 'Falhou',
            'no_answer' => 'Não Atendida',
            'busy' => 'Ocupado',
            'cancelled' => 'Cancelada',
            default => ucfirst($status)
        };
    }

    /**
     * Obter cor CSS do status
     */
    public static function getStatusColor(string $status): string
    {
        return match($status) {
            'initiated', 'ringing' => 'warning',
            'answered', 'ended' => 'success',
            'failed', 'cancelled' => 'danger',
            'no_answer', 'busy' => 'secondary',
            default => 'primary'
        };
    }
}

