<?php
/**
 * Model DeviceToken
 * Tokens de push (Expo Push Tokens) dos dispositivos móveis
 */

namespace App\Models;

use App\Helpers\Database;

class DeviceToken extends Model
{
    protected string $table = 'device_tokens';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'app_version',
        'last_used_at',
        'revoked_at'
    ];
    protected bool $timestamps = false;

    /**
     * Registrar (ou reativar) um token de dispositivo para o usuário
     */
    public static function register(int $userId, string $token, string $platform, ?string $deviceName = null, ?string $appVersion = null): int
    {
        $existing = Database::fetch("SELECT * FROM device_tokens WHERE token = ?", [$token]);

        if ($existing) {
            Database::execute(
                "UPDATE device_tokens
                 SET user_id = ?, platform = ?, device_name = ?, app_version = ?, last_used_at = NOW(), revoked_at = NULL
                 WHERE id = ?",
                [$userId, $platform, $deviceName, $appVersion, $existing['id']]
            );
            return (int)$existing['id'];
        }

        Database::execute(
            "INSERT INTO device_tokens (user_id, token, platform, device_name, app_version, last_used_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $token, $platform, $deviceName, $appVersion]
        );

        $row = Database::fetch("SELECT id FROM device_tokens WHERE token = ?", [$token]);
        return (int)($row['id'] ?? 0);
    }

    /**
     * Revogar um token (logout / token inválido)
     */
    public static function revoke(string $token): bool
    {
        Database::execute("UPDATE device_tokens SET revoked_at = NOW() WHERE token = ?", [$token]);
        return true;
    }

    /**
     * Obter tokens ativos de um usuário
     */
    public static function getActiveByUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT * FROM device_tokens WHERE user_id = ? AND revoked_at IS NULL",
            [$userId]
        );
    }

    /**
     * Atualizar last_used_at de um token
     */
    public static function touch(string $token): void
    {
        Database::execute("UPDATE device_tokens SET last_used_at = NOW() WHERE token = ?", [$token]);
    }
}
