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
     * Verificar se é requisição AJAX/JSON
     */
    private static function isAjaxRequest(): bool
    {
        $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptsJson = !empty($_SERVER['HTTP_ACCEPT']) && 
                       strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        $isJsonContent = !empty($_SERVER['CONTENT_TYPE']) && 
                         strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
        
        return $isXhr || $acceptsJson || $isJsonContent;
    }
    
    /**
     * Verificar se usuário está autenticado
     */
    public function handle(): void
    {
        self::logAuth("Verificando autenticação...");
        
        if (!Auth::check()) {
            $isAjax = self::isAjaxRequest();
            self::logAuth("Usuário NÃO autenticado (ajax=" . ($isAjax ? 'sim' : 'nao') . ")");
            
            if ($isAjax) {
                // Para AJAX, retornar JSON 401 em vez de redirect HTML
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Sessão expirada. Faça login novamente.',
                    'redirect' => '/login',
                    'code' => 'SESSION_EXPIRED'
                ]);
                exit;
            }
            
            self::logAuth("Redirecionando para /login");
            Response::redirect('/login');
        } else {
            $userId = Auth::id();
            self::logAuth("Usuário autenticado: userId={$userId}");
        }
    }
}

