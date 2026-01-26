<?php
/**
 * Model SystemAlert
 * Alertas do sistema para administradores
 */

namespace App\Models;

class SystemAlert extends Model
{
    protected string $table = 'system_alerts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'type', 
        'severity', 
        'title', 
        'message', 
        'action_url', 
        'is_read', 
        'is_resolved',
        'read_by',
        'read_at',
        'resolved_by',
        'resolved_at'
    ];
    protected bool $timestamps = true;

    // Tipos de alertas
    const TYPE_WHATSAPP_DISCONNECTED = 'whatsapp_disconnected';
    const TYPE_OPENAI_QUOTA_EXCEEDED = 'openai_quota_exceeded';
    const TYPE_INTEGRATION_ERROR = 'integration_error';
    const TYPE_SYSTEM_ERROR = 'system_error';

    // Níveis de severidade
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * Criar alerta
     */
    public static function createAlert(array $data): int
    {
        return self::create($data);
    }

    /**
     * Buscar alertas não lidos
     */
    public static function getUnread(int $limit = 50): array
    {
        return self::where('is_read', '=', 0, 'ORDER BY created_at DESC LIMIT ' . $limit);
    }

    /**
     * Buscar alertas não resolvidos
     */
    public static function getUnresolved(int $limit = 50): array
    {
        return self::where('is_resolved', '=', 0, 'ORDER BY severity DESC, created_at DESC LIMIT ' . $limit);
    }

    /**
     * Buscar alertas por tipo
     */
    public static function getByType(string $type, bool $onlyUnresolved = true): array
    {
        $sql = "SELECT * FROM system_alerts WHERE type = ?";
        if ($onlyUnresolved) {
            $sql .= " AND is_resolved = 0";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = \App\Helpers\Database::getInstance()->prepare($sql);
        $stmt->execute([$type]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Verificar se já existe alerta ativo para um recurso específico
     */
    public static function hasActiveAlert(string $type, ?string $resourceKey = null): bool
    {
        $sql = "SELECT COUNT(*) FROM system_alerts WHERE type = ? AND is_resolved = 0";
        $params = [$type];
        
        if ($resourceKey) {
            $sql .= " AND message LIKE ?";
            $params[] = "%{$resourceKey}%";
        }
        
        $stmt = \App\Helpers\Database::getInstance()->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Marcar alerta como lido
     */
    public static function markAsRead(int $alertId, int $userId): bool
    {
        return self::update($alertId, [
            'is_read' => 1,
            'read_by' => $userId,
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Marcar alerta como resolvido
     */
    public static function markAsResolved(int $alertId, int $userId): bool
    {
        return self::update($alertId, [
            'is_resolved' => 1,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Resolver alertas por tipo e recurso
     */
    public static function resolveByTypeAndResource(string $type, string $resourceKey): int
    {
        $sql = "UPDATE system_alerts SET is_resolved = 1, resolved_at = NOW() 
                WHERE type = ? AND message LIKE ? AND is_resolved = 0";
        
        $stmt = \App\Helpers\Database::getInstance()->prepare($sql);
        $stmt->execute([$type, "%{$resourceKey}%"]);
        return $stmt->rowCount();
    }

    /**
     * Contar alertas não lidos
     */
    public static function countUnread(): int
    {
        $sql = "SELECT COUNT(*) FROM system_alerts WHERE is_read = 0";
        $stmt = \App\Helpers\Database::getInstance()->query($sql);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Contar alertas críticos não resolvidos
     */
    public static function countCriticalUnresolved(): int
    {
        $sql = "SELECT COUNT(*) FROM system_alerts WHERE severity = 'critical' AND is_resolved = 0";
        $stmt = \App\Helpers\Database::getInstance()->query($sql);
        return (int)$stmt->fetchColumn();
    }
}
