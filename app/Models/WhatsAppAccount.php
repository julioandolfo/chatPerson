<?php
/**
 * Model WhatsAppAccount
 */

namespace App\Models;

class WhatsAppAccount extends Model
{
    protected string $table = 'whatsapp_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'phone_number', 'provider', 'api_url', 'api_key', 'instance_id', 'status', 'config', 'quepasa_user', 'quepasa_token', 'quepasa_trackid', 'quepasa_chatid', 'default_funnel_id', 'default_stage_id'];
    protected bool $timestamps = true;

    /**
     * Buscar por número
     */
    public static function findByPhone(string $phoneNumber): ?array
    {
        return self::whereFirst('phone_number', '=', $phoneNumber);
    }

    /**
     * Obter contas ativas
     */
    public static function getActive(): array
    {
        return self::where('status', '=', 'active');
    }

    /**
     * Obter primeira conta ativa
     */
    public static function getFirstActive(): ?array
    {
        $accounts = self::getActive();
        return !empty($accounts) ? $accounts[0] : null;
    }
}

