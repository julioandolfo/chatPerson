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
     * Verificar se permissão específica já existe
     */
    public static function hasPermission(int $userId, ?int $funnelId, ?int $stageId, string $permissionType = 'view'): bool
    {
        $sql = "SELECT COUNT(*) as count FROM agent_funnel_permissions 
                WHERE user_id = ? AND funnel_id <=> ? AND stage_id <=> ? AND permission_type = ?";
        $result = Database::fetch($sql, [$userId, $funnelId, $stageId, $permissionType]);
        return ($result['count'] ?? 0) > 0;
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

    /**
     * Obter IDs de funis que o agente pode visualizar
     * Retorna array de IDs ou NULL se pode ver todos (admin/super admin)
     */
    public static function getAllowedFunnelIds(int $userId): ?array
    {
        // Admin e super admin podem ver todos os funis
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return null; // NULL = todos os funis
        }

        // Se existir permissão de visualizar TODOS os funis (funnel_id NULL), conceder acesso total
        $sqlAll = "SELECT COUNT(*) AS count 
                   FROM agent_funnel_permissions 
                   WHERE user_id = ? 
                     AND permission_type = 'view' 
                     AND funnel_id IS NULL";
        $allResult = Database::fetch($sqlAll, [$userId]);
        if (($allResult['count'] ?? 0) > 0) {
            return null; // NULL = todos os funis
        }
        
        // Buscar funis com permissão 'view'
        $sql = "SELECT DISTINCT funnel_id 
                FROM agent_funnel_permissions 
                WHERE user_id = ? 
                AND permission_type = 'view'
                AND funnel_id IS NOT NULL";
        $results = Database::fetchAll($sql, [$userId]);
        
        $funnelIds = array_column($results, 'funnel_id');
        
        // Se não tem permissões específicas, retornar array vazio (sem acesso)
        return !empty($funnelIds) ? array_map('intval', $funnelIds) : [];
    }

    /**
     * Obter IDs de etapas que o agente pode visualizar
     * Retorna array de IDs ou NULL se pode ver todas (admin/super admin)
     */
    public static function getAllowedStageIds(int $userId): ?array
    {
        // Admin e super admin podem ver todas as etapas
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return null; // NULL = todas as etapas
        }
        
        // Buscar etapas com permissão 'view'
        $sql = "SELECT DISTINCT stage_id 
                FROM agent_funnel_permissions 
                WHERE user_id = ? 
                AND permission_type = 'view'
                AND stage_id IS NOT NULL";
        $results = Database::fetchAll($sql, [$userId]);
        
        $stageIds = array_column($results, 'stage_id');
        
        // Se não tem permissões específicas de etapa, buscar todas as etapas dos funis permitidos
        if (empty($stageIds)) {
            $allowedFunnels = self::getAllowedFunnelIds($userId);
            if ($allowedFunnels === null) {
                return null; // Admin
            }
            if (empty($allowedFunnels)) {
                return []; // Sem acesso
            }
            
            // Buscar todas as etapas dos funis permitidos
            $placeholders = implode(',', array_fill(0, count($allowedFunnels), '?'));
            $sql = "SELECT id FROM funnel_stages WHERE funnel_id IN ($placeholders)";
            $results = Database::fetchAll($sql, $allowedFunnels);
            $stageIds = array_column($results, 'id');
        }
        
        return !empty($stageIds) ? array_map('intval', $stageIds) : [];
    }

    /**
     * Verificar se agente pode visualizar conversa baseado no funil/etapa
     */
    public static function canViewConversation(int $userId, array $conversation): bool
    {
        // Admin e super admin podem ver tudo
        $user = \App\Models\User::find($userId);
        if ($user && ($user['role'] === 'admin' || \App\Services\PermissionService::isSuperAdmin($userId))) {
            return true;
        }
        
        // Se a conversa não tem funil, permitir (conversas antigas)
        if (empty($conversation['funnel_id'])) {
            return true;
        }
        
        $funnelId = (int)$conversation['funnel_id'];
        $stageId = !empty($conversation['funnel_stage_id']) ? (int)$conversation['funnel_stage_id'] : null;
        
        // 🐛 DEBUG - Verificação de permissão
        $hasFunnelPermission = self::canViewFunnel($userId, $funnelId);
        \App\Helpers\Log::debug("🔍 [AgentFunnelPermission::canViewConversation] convId={$conversation['id']}, userId={$userId}, funnelId={$funnelId}, stageId={$stageId}, canViewFunnel=" . ($hasFunnelPermission ? 'true' : 'false'), 'conversas.log');
        
        // Verificar permissão de funil
        if (!$hasFunnelPermission) {
            return false;
        }
        
        // Se tem etapa, verificar permissão de etapa
        if ($stageId !== null) {
            $hasStagePermission = self::canViewStage($userId, $stageId);
            \App\Helpers\Log::debug("🔍 [AgentFunnelPermission::canViewConversation] convId={$conversation['id']}, stageId={$stageId}, canViewStage=" . ($hasStagePermission ? 'true' : 'false'), 'conversas.log');
            return $hasStagePermission;
        }
        
        // Tem permissão no funil e não tem etapa específica
        return true;
    }
}

