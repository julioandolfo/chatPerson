<?php
/**
 * Service RememberMeService
 *
 * "Manter conectado" (essencial para o app instalado/PWA: no iPhone o cookie de sessão
 * é apagado quando o app é fechado).
 *
 * Cookie "selector:validator". No banco ficam só o selector e o SHA-256 do validator.
 * A cada uso o validator é trocado (rotação); o anterior continua aceito por alguns
 * segundos para não derrubar requisições paralelas que saíram com o cookie antigo.
 */

namespace App\Services;

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Helpers\Logger;

class RememberMeService
{
    public const COOKIE = 'cp_remember';
    private const LIFETIME_DAYS = 30;
    private const ROTATION_GRACE_SECONDS = 60;

    /**
     * Criar token para o aparelho atual e gravar o cookie
     */
    public static function issue(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        Database::insert(
            "INSERT INTO user_remember_tokens (user_id, selector, validator_hash, user_agent, ip_address, expires_at, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $selector,
                hash('sha256', $validator),
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $_SERVER['REMOTE_ADDR'] ?? null,
                self::expiresAt(),
                date('Y-m-d H:i:s'),
            ]
        );

        self::setCookie($selector . ':' . $validator);
    }

    /**
     * Restaurar a sessão a partir do cookie. Retorna true se logou.
     * Chamado por Auth::check() quando não há sessão.
     */
    public static function restoreFromCookie(): bool
    {
        $parsed = self::parseCookie();
        if (!$parsed) {
            return false;
        }
        [$selector, $validator] = $parsed;

        try {
            $token = Database::fetch(
                "SELECT t.*, u.name, u.email, u.role, u.status
                 FROM user_remember_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.selector = ?",
                [$selector]
            );
        } catch (\PDOException $e) {
            // Migration 160 ainda não executada
            return false;
        }

        if (!$token || strtotime($token['expires_at']) < time() || ($token['status'] ?? 'active') !== 'active') {
            self::clearCookie();
            return false;
        }

        $hash = hash('sha256', $validator);
        $matchesCurrent = hash_equals($token['validator_hash'], $hash);
        $matchesPrevious = !$matchesCurrent
            && !empty($token['previous_validator_hash'])
            && hash_equals($token['previous_validator_hash'], $hash)
            && !empty($token['rotated_at'])
            && (time() - strtotime($token['rotated_at'])) <= self::ROTATION_GRACE_SECONDS;

        if (!$matchesCurrent && !$matchesPrevious) {
            // Validator errado para um selector válido: possível cookie roubado já usado.
            // Revoga o token deste aparelho por segurança.
            Database::execute("DELETE FROM user_remember_tokens WHERE id = ?", [(int)$token['id']]);
            self::clearCookie();
            Logger::warning("RememberMe: validator inválido para o token {$token['id']} (user {$token['user_id']}) — token revogado");
            return false;
        }

        if ($matchesCurrent) {
            // Rotacionar e estender (janela deslizante de 30 dias)
            $newValidator = bin2hex(random_bytes(32));
            Database::execute(
                "UPDATE user_remember_tokens
                 SET previous_validator_hash = validator_hash, validator_hash = ?, rotated_at = ?, expires_at = ?, last_used_at = ?,
                     user_agent = ?, ip_address = ?
                 WHERE id = ?",
                [
                    hash('sha256', $newValidator),
                    date('Y-m-d H:i:s'),
                    self::expiresAt(),
                    date('Y-m-d H:i:s'),
                    mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    (int)$token['id'],
                ]
            );
            self::setCookie($selector . ':' . $newValidator);
        }

        Auth::login((int)$token['user_id'], [
            'id' => (int)$token['user_id'],
            'name' => $token['name'],
            'email' => $token['email'],
            'role' => $token['role'],
        ]);

        Logger::info("RememberMe: sessão restaurada para o usuário {$token['user_id']}");
        return true;
    }

    /**
     * Revogar o token do aparelho atual (logout)
     */
    public static function revokeCurrent(): void
    {
        $parsed = self::parseCookie();
        if ($parsed) {
            try {
                Database::execute("DELETE FROM user_remember_tokens WHERE selector = ?", [$parsed[0]]);
            } catch (\PDOException $e) {
                // tabela pode não existir
            }
        }
        self::clearCookie();
    }

    public static function revokeAllForUser(int $userId): void
    {
        try {
            Database::execute("DELETE FROM user_remember_tokens WHERE user_id = ?", [$userId]);
        } catch (\PDOException $e) {
            // tabela pode não existir
        }
    }

    public static function revoke(int $userId, int $tokenId): bool
    {
        return Database::execute("DELETE FROM user_remember_tokens WHERE id = ? AND user_id = ?", [$tokenId, $userId]) > 0;
    }

    /**
     * Aparelhos conectados do usuário (para a tela de perfil)
     */
    public static function listForUser(int $userId): array
    {
        $currentSelector = self::parseCookie()[0] ?? null;

        $rows = Database::fetchAll(
            "SELECT id, selector, user_agent, ip_address, last_used_at, created_at, expires_at
             FROM user_remember_tokens WHERE user_id = ? AND expires_at > ? ORDER BY last_used_at DESC",
            [$userId, date('Y-m-d H:i:s')]
        );

        return array_map(function ($row) use ($currentSelector) {
            return [
                'id' => (int)$row['id'],
                'device' => self::describeUserAgent($row['user_agent'] ?? ''),
                'ip_address' => $row['ip_address'],
                'last_used_at' => $row['last_used_at'],
                'created_at' => $row['created_at'],
                'is_current' => $currentSelector !== null && hash_equals($row['selector'], $currentSelector),
            ];
        }, $rows);
    }

    public static function describeUserAgent(string $ua): string
    {
        $os = 'Outro';
        if (stripos($ua, 'iPhone') !== false) $os = 'iPhone';
        elseif (stripos($ua, 'iPad') !== false) $os = 'iPad';
        elseif (stripos($ua, 'Android') !== false) $os = 'Android';
        elseif (stripos($ua, 'Windows') !== false) $os = 'Windows';
        elseif (stripos($ua, 'Mac OS') !== false) $os = 'Mac';
        elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

        $browser = 'Navegador';
        if (stripos($ua, 'Edg/') !== false) $browser = 'Edge';
        elseif (stripos($ua, 'OPR/') !== false) $browser = 'Opera';
        elseif (stripos($ua, 'Firefox/') !== false) $browser = 'Firefox';
        elseif (stripos($ua, 'Chrome/') !== false || stripos($ua, 'CriOS/') !== false) $browser = 'Chrome';
        elseif (stripos($ua, 'Safari/') !== false) $browser = 'Safari';

        return "{$browser} · {$os}";
    }

    // =====================================================================

    private static function parseCookie(): ?array
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if (!is_string($raw) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $raw, $m)) {
            return null;
        }
        return [$m[1], $m[2]];
    }

    private static function expiresAt(): string
    {
        return date('Y-m-d H:i:s', time() + self::LIFETIME_DAYS * 86400);
    }

    private static function setCookie(string $value): void
    {
        $_COOKIE[self::COOKIE] = $value;
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        setcookie(self::COOKIE, $value, self::cookieOptions(time() + self::LIFETIME_DAYS * 86400));
    }

    private static function clearCookie(): void
    {
        unset($_COOKIE[self::COOKIE]);
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        setcookie(self::COOKIE, '', self::cookieOptions(time() - 3600));
    }

    private static function cookieOptions(int $expires): array
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        return [
            'expires' => $expires,
            'path' => \App\Helpers\Url::basePath() ?: '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
