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
        'sip_username', 'sip_password', 'status', 'metadata'
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
}

