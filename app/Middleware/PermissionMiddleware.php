<?php
/**
 * Middleware de Permissões
 */

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Response;
use App\Helpers\Permission;

class PermissionMiddleware
{
    /**
     * Verificar permissão antes de executar rota
     */
    public static function handle(string $permission): void
    {
        // Verificar se está autenticado
        if (!Auth::check()) {
            Response::redirect('/login');
            exit;
        }

        // Verificar permissão
        if (!Permission::can($permission)) {
            Response::forbidden('Você não tem permissão para acessar este recurso.');
            exit;
        }
    }

    /**
     * Verificar qualquer uma das permissões
     */
    public static function handleAny(array $permissions): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
            exit;
        }

        if (!Permission::canAny($permissions)) {
            Response::forbidden('Você não tem permissão para acessar este recurso.');
            exit;
        }
    }

    /**
     * Verificar todas as permissões
     */
    public static function handleAll(array $permissions): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
            exit;
        }

        if (!Permission::canAll($permissions)) {
            Response::forbidden('Você não tem permissão para acessar este recurso.');
            exit;
        }
    }
}

