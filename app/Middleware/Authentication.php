<?php
/**
 * Middleware de Autenticação
 */

namespace App\Middleware;

use App\Helpers\Auth;
use App\Helpers\Response;

class Authentication
{
    /**
     * Verificar se usuário está autenticado
     */
    public function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }
}

