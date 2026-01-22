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
     * Log para arquivo logs/auth.log
     */
    private static function logAuth(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/auth.log';
        $timestamp = date('Y-m-d H:i:s');
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $logMessage = "[{$timestamp}] [Middleware:Auth] [{$uri}] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Verificar se usuário está autenticado
     */
    public function handle(): void
    {
        self::logAuth("Verificando autenticação...");
        
        if (!Auth::check()) {
            self::logAuth("Usuário NÃO autenticado - redirecionando para /login");
            Response::redirect('/login');
        } else {
            $userId = Auth::id();
            self::logAuth("Usuário autenticado: userId={$userId}");
        }
    }
}

