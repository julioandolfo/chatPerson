<?php
/**
 * Model Department
 */

namespace App\Models;

class Department extends Model
{
    protected string $table = 'departments';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'description', 'parent_id'];
    protected bool $timestamps = true;

    /**
     * Obter setor pai
     */
    public static function getParent(int $departmentId): ?array
    {
        $department = self::find($departmentId);
        if ($department && $department['parent_id']) {
            return self::find($department['parent_id']);
        }
        return null;
    }

    /**
     * Obter setores filhos
     */
    public static function getChildren(int $departmentId): array
    {
        return self::where('parent_id', '=', $departmentId);
    }

    /**
     * Obter árvore completa (pai + filhos)
     */
    public static function getTree(int $departmentId): array
    {
        $tree = [];
        $department = self::find($departmentId);
        
        if ($department) {
            $tree[] = $department;
            $children = self::getChildren($departmentId);
            foreach ($children as $child) {
                $tree = array_merge($tree, self::getTree($child['id']));
            }
        }
        
        return $tree;
    }

    /**
     * Obter agentes do setor
     */
    public static function getAgents(int $departmentId): array
    {
        $sql = "SELECT u.* FROM users u
                INNER JOIN agent_departments ad ON u.id = ad.user_id
                WHERE ad.department_id = ?
                ORDER BY u.name ASC";
        return \App\Helpers\Database::fetchAll($sql, [$departmentId]);
    }

    /**
     * Adicionar agente ao setor
     */
    public static function addAgent(int $departmentId, int $userId): bool
    {
        try {
            $sql = "INSERT IGNORE INTO agent_departments (department_id, user_id) VALUES (?, ?)";
            \App\Helpers\Database::execute($sql, [$departmentId, $userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Remover agente do setor
     */
    public static function removeAgent(int $departmentId, int $userId): bool
    {
        try {
            $sql = "DELETE FROM agent_departments WHERE department_id = ? AND user_id = ?";
            \App\Helpers\Database::execute($sql, [$departmentId, $userId]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

