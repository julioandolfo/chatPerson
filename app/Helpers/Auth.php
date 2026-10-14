<?php
/**
 * Helper de Autenticação
 */

namespace App\Helpers;

class Auth
{
    private static bool $sessionReleased = false;
    private static ?array $cachedSessionData = null;
    private static bool $rememberAttempted = false;

    /**
     * Iniciar sessão se não estiver iniciada
     */
    private static function startSession(): void
    {
        if (self::$sessionReleased) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Cachear dados da sessão em memória e liberar o lock do arquivo de sessão.
     * Após chamar este método, Auth continua retornando os dados corretos
     * mas não mantém mais o lock exclusivo no arquivo de sessão,
     * permitindo que outras requisições do mesmo usuário sejam processadas.
     */
    public static function cacheSessionAndRelease(): void
    {
        if (self::$sessionReleased) {
            return;
        }
        self::startSession();
        self::$cachedSessionData = $_SESSION ?? [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        self::$sessionReleased = true;
    }

    /**
     * Reabrir sessão para escrita (necessário para login/logout)
     */
    private static function reopenForWrite(): void
    {
        if (self::$sessionReleased) {
            session_start();
            self::$sessionReleased = false;
            self::$cachedSessionData = null;
        } else {
            self::startSession();
        }
    }

    /**
     * Fazer login
     */
    public static function login(int $userId, array $userData = []): void
    {
        self::reopenForWrite();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = $userData;
        $_SESSION['logged_in'] = true;
    }

    /**
     * Fazer logout
     */
    public static function logout(): void
    {
        \App\Services\RememberMeService::revokeCurrent();
        self::reopenForWrite();
        session_destroy();
        self::$cachedSessionData = null;
    }

    /**
     * Verificar se está autenticado
     */
    public static function check(): bool
    {
        if (self::$sessionReleased && self::$cachedSessionData !== null) {
            $logged = isset(self::$cachedSessionData['logged_in']) && self::$cachedSessionData['logged_in'] === true;
        } else {
            self::startSession();
            $logged = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        }

        // Sem sessão, mas com o cookie "Manter conectado": restaura o login (app instalado/PWA)
        if (!$logged && !self::$rememberAttempted && !empty($_COOKIE[\App\Services\RememberMeService::COOKIE])) {
            self::$rememberAttempted = true;
            try {
                return \App\Services\RememberMeService::restoreFromCookie();
            } catch (\Throwable $e) {
                \App\Helpers\Logger::error("RememberMe: erro ao restaurar sessão: " . $e->getMessage());
            }
        }

        return $logged;
    }

    /**
     * Guardar a página que o usuário tentava abrir, para voltar a ela após o login
     */
    public static function setIntendedUrl(string $url): void
    {
        self::reopenForWrite();
        $_SESSION['intended_url'] = $url;
    }

    /**
     * Retirar (e limpar) a página pretendida. Só aceita caminhos internos.
     */
    public static function pullIntendedUrl(): ?string
    {
        self::reopenForWrite();
        $url = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);

        if (!is_string($url) || $url === '' || $url[0] !== '/' || str_starts_with($url, '//') || str_contains($url, '\\')) {
            return null;
        }
        if (preg_match('#/(login|logout)(\?|$)#', $url)) {
            return null;
        }
        return $url;
    }

    /**
     * Obter ID do usuário
     */
    public static function id(): ?int
    {
        if (self::$sessionReleased && self::$cachedSessionData !== null) {
            return self::$cachedSessionData['user_id'] ?? null;
        }
        self::startSession();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Obter dados do usuário
     */
    public static function user(): ?array
    {
        if (self::$sessionReleased && self::$cachedSessionData !== null) {
            return self::$cachedSessionData['user'] ?? null;
        }
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
