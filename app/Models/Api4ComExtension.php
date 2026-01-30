<?php
/**
 * Model Api4ComExtension
 */

namespace App\Models;

class Api4ComExtension extends Model
{
    protected string $table = 'api4com_extensions';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id', 'api4com_account_id', 'extension_id', 'extension_number',
        'sip_username', 'sip_password', 'sip_password_encrypted', 'webphone_enabled',
        'status', 'metadata'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar ramal por usuário e conta
     */
    public static function findByUserAndAccount(int $userId, int $accountId): ?array
    {
        $sql = "SELECT * FROM api4com_extensions WHERE user_id = ? AND api4com_account_id = ? AND status = 'active' LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$userId, $accountId]);
    }

    /**
     * Buscar ramais por usuário
     */
    public static function getByUser(int $userId): array
    {
        return self::where('user_id', '=', $userId);
    }

    /**
     * Buscar ramais por conta
     */
    public static function getByAccount(int $accountId): array
    {
        return self::where('api4com_account_id', '=', $accountId);
    }

    /**
     * Buscar ramais ativos
     */
    public static function getActive(): array
    {
        return self::where('status', '=', 'active');
    }

    /**
     * Buscar ramal pelo extension_id da API
     */
    public static function findByExtensionId($extensionId, int $accountId): ?array
    {
        $sql = "SELECT * FROM api4com_extensions WHERE extension_id = ? AND api4com_account_id = ? LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$extensionId, $accountId]);
    }

    /**
     * Buscar ramais com dados do usuário
     */
    public static function getByAccountWithUser(int $accountId): array
    {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email 
                FROM api4com_extensions e
                LEFT JOIN users u ON e.user_id = u.id
                WHERE e.api4com_account_id = ?
                ORDER BY e.extension_number ASC";
        return \App\Helpers\Database::fetchAll($sql, [$accountId]);
    }
}

