<?php
/**
 * Helper de Autenticação
 */

namespace App\Helpers;

class Auth
{
    /**
     * Iniciar sessão se não estiver iniciada
     */
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Fazer login
     */
    public static function login(int $userId, array $userData = []): void
    {
        self::startSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user'] = $userData;
        $_SESSION['logged_in'] = true;
    }

    /**
     * Fazer logout
     */
    public static function logout(): void
    {
        self::startSession();
        session_destroy();
    }

    /**
     * Verificar se está autenticado
     */
    public static function check(): bool
    {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
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

