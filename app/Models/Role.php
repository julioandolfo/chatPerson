<?php
/**
 * Model Role
 */

namespace App\Models;

class Role extends Model
{
    protected string $table = 'roles';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'slug', 'description', 'level', 'is_system'];
    protected bool $timestamps = true;

    /**
     * Buscar role por slug
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::whereFirst('slug', '=', $slug);
    }

    /**
     * Obter permissões da role
     */
    public static function getPermissions(int $roleId): array
    {
        $sql = "SELECT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
                ORDER BY p.module, p.name";
        return \App\Helpers\Database::fetchAll($sql, [$roleId]);
    }

    /**
     * Verificar se role tem permissão (com herança hierárquica)
     */
    public static function hasPermission(int $roleId, string $permissionSlug): bool
    {
        // Verificar permissão direta
        $sql = "SELECT COUNT(*) as count FROM role_permissions rp
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ? AND p.slug = ?";
        $result = \App\Helpers\Database::fetch($sql, [$roleId, $permissionSlug]);
        
        if (($result['count'] ?? 0) > 0) {
            return true;
        }
        
        // Verificar herança hierárquica
        $role = self::find($roleId);
        if (!$role) {
            return false;
        }
        
        $roleLevel = (int)($role['level'] ?? 0);
        
        // Se for nível 0 (Super Admin), tem todas as permissões
        if ($roleLevel <= 0) {
            return true;
        }
        
        // Verificar se alguma role de nível superior tem a permissão
        // Roles de nível menor (mais alto na hierarquia) herdam permissões
        $sql = "SELECT COUNT(*) as count FROM role_permissions rp
                INNER JOIN permissions p ON rp.permission_id = p.id
                INNER JOIN roles r ON rp.role_id = r.id
                WHERE r.level < ? AND p.slug = ?";
        $result = \App\Helpers\Database::fetch($sql, [$roleLevel, $permissionSlug]);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Obter todas as permissões da role (incluindo herdadas)
     */
    public static function getAllPermissions(int $roleId): array
    {
        $role = self::find($roleId);
        if (!$role) {
            return [];
        }
        
        $roleLevel = (int)($role['level'] ?? 0);
        
        // Se for nível 0, retornar todas as permissões
        if ($roleLevel <= 0) {
            return \App\Models\Permission::all();
        }
        
        // Obter permissões diretas e herdadas
        $sql = "SELECT DISTINCT p.* FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN roles r ON rp.role_id = r.id
                WHERE r.level >= ?
                ORDER BY p.module, p.name";
        return \App\Helpers\Database::fetchAll($sql, [$roleLevel]);
    }

    /**
     * Adicionar permissão à role e limpar cache
     */
    public static function addPermission(int $roleId, int $permissionId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
            \App\Helpers\Database::execute($sql, [$roleId, $permissionId]);
            
            // Limpar cache de todos os usuários com esta role
            self::clearUsersCacheByRole($roleId);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remover permissão da role e limpar cache
     */
    public static function removePermission(int $roleId, int $permissionId): bool
    {
        try {
            $sql = "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?";
            \App\Helpers\Database::execute($sql, [$roleId, $permissionId]);
            
            // Limpar cache de todos os usuários com esta role
            self::clearUsersCacheByRole($roleId);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Limpar cache de usuários que têm esta role
     */
    private static function clearUsersCacheByRole(int $roleId): void
    {
        $sql = "SELECT DISTINCT user_id FROM user_roles WHERE role_id = ?";
        $users = \App\Helpers\Database::fetchAll($sql, [$roleId]);
        
        foreach ($users as $user) {
            \App\Services\PermissionService::clearUserCache($user['user_id']);
        }
    }

    /**
     * Hook após criar role
     */
    public static function afterCreate(int $id, array $data): void
    {
        // Limpar cache global de permissões
        \App\Services\PermissionService::clearAllCache();
    }

    /**
     * Hook após atualizar role
     */
    public static function afterUpdate(int $id, array $data): void
    {
        // Limpar cache de usuários com esta role
        self::clearUsersCacheByRole($id);
    }
}

