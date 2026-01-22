<?php
/**
 * Helper de Autenticação
 */

namespace App\Helpers;

class Auth
{
    private static bool $sessionDebugLogged = false;
    
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
        $logMessage = "[{$timestamp}] [Auth] {$message}\n";
        
        @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Iniciar sessão se não estiver iniciada
     */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configurar cookie de sessão antes de iniciar
            $cookieParams = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => 7200, // 2 horas
                'path' => '/',
                'domain' => $cookieParams['domain'] ?: '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            
            session_start();
        }
        
        // Log de debug de sessão (apenas uma vez por requisição)
        if (!self::$sessionDebugLogged) {
            self::$sessionDebugLogged = true;
            $sessionId = session_id();
            $hasLoggedIn = isset($_SESSION['logged_in']) ? 'sim' : 'nao';
            $userId = $_SESSION['user_id'] ?? 'null';
            $cookie = $_COOKIE[session_name()] ?? 'nao-enviado';
            self::logAuth("startSession: session_id={$sessionId}, cookie={$cookie}, logged_in={$hasLoggedIn}, user_id={$userId}");
        }
    }

    /**
     * Fazer login
     */
    public static function login(int $userId, array $userData = []): void
    {
        self::startSession();
        
        // Regenerar ID da sessão para prevenir session fixation
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = $userData;
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        self::logAuth("login: userId={$userId}, new_session_id=" . session_id());
    }

    /**
     * Fazer logout
     */
    public static function logout(): void
    {
        self::startSession();
        $userId = $_SESSION['user_id'] ?? 'null';
        self::logAuth("logout: userId={$userId}");
        
        // Limpar variáveis de sessão
        $_SESSION = [];
        
        // Destruir cookie de sessão
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Verificar se está autenticado
     */
    public static function check(): bool
    {
        self::startSession();
        $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        
        // Log de debug em caso de falha de autenticação (uma vez por requisição)
        if (!$isLoggedIn && !self::$sessionDebugLogged) {
            self::$sessionDebugLogged = true;
            $sessionId = session_id();
            $cookieName = session_name();
            $cookieValue = $_COOKIE[$cookieName] ?? 'NAO_ENVIADO';
            $sessionStatus = session_status();
            $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            
            self::logAuth("check() FALHOU: uri={$uri}, session_id={$sessionId}, cookie={$cookieName}={$cookieValue}, session_status={$sessionStatus}");
            self::logAuth("check() _SESSION keys: " . implode(', ', array_keys($_SESSION)));
        }
        
        return $isLoggedIn;
    }

    /**
     * Obter ID do usuário
     */
    public static function id(): ?int
    {
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Obter dados do usuário
     */
    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Obter campo específico do usuário
     */
    public static function userField(string $field, $default = null)
    {
        $user = self::user();
        return $user[$field] ?? $default;
    }
}

