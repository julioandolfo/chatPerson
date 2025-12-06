<?php
/**
 * Model AgentFunnelPermission
 */

namespace App\Models;

use App\Helpers\Database;

class AgentFunnelPermission extends Model
{
    protected string $table = 'agent_funnel_permissions';
    protected string $primaryKey = 'id';
    protected array $fillable = ['user_id', 'funnel_id', 'stage_id', 'permission_type'];
    protected bool $timestamps = true;

    /**
     * Verificar se agente pode ver funil
     */
    public static function canViewFunnel(int $userId, int $funnelId): bool
    {
        // Admin e super admin podem ver tudo
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return true;
        }
        
        // Verificar permissão específica
        $sql = "SELECT COUNT(*) as count FROM agent_funnel_permissions 
                WHERE user_id = ? AND permission_type = 'view' 
                AND (funnel_id = ? OR funnel_id IS NULL)";
        $result = Database::fetch($sql, [$userId, $funnelId]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Verificar se agente pode ver estágio
     */
    public static function canViewStage(int $userId, int $stageId): bool
    {
        // Admin e super admin podem ver tudo
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return true;
        }
        
        // Obter funil do estágio
        $stage = \App\Models\FunnelStage::find($stageId);
        if (!$stage) {
            return false;
        }
        
        // Verificar permissão no funil primeiro
        if (!self::canViewFunnel($userId, $stage['funnel_id'])) {
            return false;
        }
        
        // Verificar permissão específica no estágio
        $sql = "SELECT COUNT(*) as count FROM agent_funnel_permissions 
                WHERE user_id = ? AND permission_type = 'view' 
                AND (stage_id = ? OR stage_id IS NULL) 
                AND (funnel_id = ? OR funnel_id IS NULL)";
        $result = Database::fetch($sql, [$userId, $stageId, $stage['funnel_id']]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obter permissões do agente
     */
    public static function getUserPermissions(int $userId): array
    {
        $sql = "SELECT afp.*, f.name as funnel_name, fs.name as stage_name
                FROM agent_funnel_permissions afp
                LEFT JOIN funnels f ON afp.funnel_id = f.id
                LEFT JOIN funnel_stages fs ON afp.stage_id = fs.id
                WHERE afp.user_id = ?
                ORDER BY f.name, fs.name";
        return Database::fetchAll($sql, [$userId]);
    }

    /**
     * Adicionar permissão
     */
    public static function addPermission(int $userId, ?int $funnelId, ?int $stageId, string $permissionType = 'view'): bool
    {
        try {
            $sql = "INSERT INTO agent_funnel_permissions (user_id, funnel_id, stage_id, permission_type) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE permission_type = VALUES(permission_type)";
            Database::execute($sql, [$userId, $funnelId, $stageId, $permissionType]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar se agente pode mover conversas para estágio
     */
    public static function canMoveToStage(int $userId, int $stageId): bool
    {
        // Admin e super admin podem mover para qualquer estágio
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return true;
        }
        
        // Verificar permissão de edição geral
        if (\App\Services\PermissionService::hasPermission($userId, 'kanban.drag_drop.all')) {
            return true;
        }
        
        // Obter estágio
        $stage = \App\Models\FunnelStage::find($stageId);
        if (!$stage) {
            return false;
        }
        
        // Verificar permissão específica no estágio
        $sql = "SELECT COUNT(*) as count FROM agent_funnel_permissions 
                WHERE user_id = ? AND permission_type IN ('move', 'edit') 
                AND (stage_id = ? OR stage_id IS NULL) 
                AND (funnel_id = ? OR funnel_id IS NULL)";
        $result = Database::fetch($sql, [$userId, $stageId, $stage['funnel_id']]);
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Remover permissão
     */
    public static function removePermission(int $userId, ?int $funnelId, ?int $stageId, string $permissionType = 'view'): bool
    {
        try {
            $sql = "DELETE FROM agent_funnel_permissions 
                    WHERE user_id = ? AND funnel_id <=> ? AND stage_id <=> ? AND permission_type = ?";
            Database::execute($sql, [$userId, $funnelId, $stageId, $permissionType]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

