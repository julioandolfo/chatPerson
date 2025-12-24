<?php
/**
 * Model Api4ComAccount
 */

namespace App\Models;

class Api4ComAccount extends Model
{
    protected string $table = 'api4com_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'api_url', 'api_token', 'domain', 'enabled', 
        'webhook_url', 'config'
    ];
    protected bool $timestamps = true;

    /**
     * Obter contas habilitadas
     */
    public static function getEnabled(): array
    {
        return self::where('enabled', '=', 1);
    }

    /**
     * Obter primeira conta habilitada
     */
    public static function getFirstEnabled(): ?array
    {
        $accounts = self::getEnabled();
        return !empty($accounts) ? $accounts[0] : null;
    }
}

