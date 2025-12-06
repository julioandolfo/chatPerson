<?php
/**
 * Helper Permission
 * Facilita verificação de permissões nas views e controllers
 */

namespace App\Helpers;

use App\Services\PermissionService;

class Permission
{
    /**
     * Verificar se usuário logado tem permissão
     */
    public static function can(string $permissionSlug, ?array $context = null): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::hasPermission($userId, $permissionSlug, $context);
    }

    /**
     * Verificar se usuário logado tem qualquer uma das permissões
     */
    public static function canAny(array $permissionSlugs, ?array $context = null): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::hasAnyPermission($userId, $permissionSlugs, $context);
    }

    /**
     * Verificar se usuário logado tem todas as permissões
     */
    public static function canAll(array $permissionSlugs, ?array $context = null): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::hasAllPermissions($userId, $permissionSlugs, $context);
    }

    /**
     * Verificar se usuário logado é super admin
     */
    public static function isSuperAdmin(): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::isSuperAdmin($userId);
    }

    /**
     * Verificar se usuário logado é admin
     */
    public static function isAdmin(): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::isAdmin($userId);
    }

    /**
     * Verificar se pode ver conversa
     */
    public static function canViewConversation(array $conversation): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::canViewConversation($userId, $conversation);
    }

    /**
     * Verificar se pode editar conversa
     */
    public static function canEditConversation(array $conversation): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::canEditConversation($userId, $conversation);
    }

    /**
     * Verificar se pode enviar mensagem
     */
    public static function canSendMessage(array $conversation): bool
    {
        $userId = Auth::id();
        if (!$userId) {
            return false;
        }
        
        return PermissionService::canSendMessage($userId, $conversation);
    }

    /**
     * Abortar se não tiver permissão
     */
    public static function abortIfCannot(string $permissionSlug, string $message = 'Acesso negado'): void
    {
        if (!self::can($permissionSlug)) {
            Response::forbidden($message);
        }
    }

    /**
     * Abortar se não tiver qualquer uma das permissões
     */
    public static function abortIfCannotAny(array $permissionSlugs, string $message = 'Acesso negado'): void
    {
        if (!self::canAny($permissionSlugs)) {
            Response::forbidden($message);
        }
    }
}

