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
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        // Log também no external_sources para debug
        if (strpos($uri, 'external-sources') !== false) {
            self::logExternal("=== MIDDLEWARE AUTH - URI: {$uri} ===");
            self::logExternal("isAjax: " . (self::isAjaxRequest() ? 'SIM' : 'NAO'));
        }
        
        self::logAuth("Verificando autenticação...");
        
        if (!Auth::check()) {
            self::logAuth("Usuário NÃO autenticado - redirecionando para /login");
            
            if (strpos($uri, 'external-sources') !== false) {
                self::logExternal("USUARIO NAO AUTENTICADO!");
            }
            
            // Se for requisição AJAX/API, retornar JSON em vez de redirecionar
            if (self::isAjaxRequest()) {
                if (strpos($uri, 'external-sources') !== false) {
                    self::logExternal("Retornando JSON 401 (sessao expirada)");
                }
                Response::json([
                    'success' => false,
                    'message' => 'Sessão expirada. Por favor, faça login novamente.',
                    'redirect' => '/login'
                ], 401);
                exit;
            }
            
            Response::redirect('/login');
        } else {
            $userId = Auth::id();
            self::logAuth("Usuário autenticado: userId={$userId}");
            
            if (strpos($uri, 'external-sources') !== false) {
                self::logExternal("Usuario autenticado: ID={$userId}");
            }
        }
    }
    
    /**
     * Log para external_sources.log
     */
    private static function logExternal(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/external_sources.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [Middleware] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Verificar se é requisição AJAX
     */
    private static function isAjaxRequest(): bool
    {
        // Verificar header X-Requested-With
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        
        // Verificar se Accept header indica JSON
        if (!empty($_SERVER['HTTP_ACCEPT']) && 
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            return true;
        }
        
        // Verificar Content-Type
        if (!empty($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
            return true;
        }
        
        // Verificar se a URL começa com /api/
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/') === 0) {
            return true;
        }
        
        return false;
    }
}

