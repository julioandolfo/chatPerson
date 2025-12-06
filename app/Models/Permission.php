<?php
/**
 * Model Permission
 */

namespace App\Models;

class Permission extends Model
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'slug', 'description', 'module'];
    protected bool $timestamps = true;

    /**
     * Buscar permissão por slug
     */
    public static function findBySlug(string $slug): ?array
    {
        return self::whereFirst('slug', '=', $slug);
    }

    /**
     * Buscar permissões por módulo
     */
    public static function getByModule(string $module): array
    {
        return self::where('module', '=', $module);
    }

    /**
     * Obter todas as permissões agrupadas por módulo
     */
    public static function getAllGroupedByModule(): array
    {
        $permissions = self::all();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $module = $permission['module'] ?? 'other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $permission;
        }
        
        return $grouped;
    }
}

