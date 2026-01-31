<?php
/**
 * Model WhatsAppAccount
 */

namespace App\Models;

class WhatsAppAccount extends Model
{
    protected string $table = 'whatsapp_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 
        'phone_number', 
        'provider', 
        'api_url', 
        'api_key', 
        'instance_id', 
        'status', 
        'config', 
        'quepasa_user', 
        'quepasa_token', 
        'quepasa_trackid', 
        'quepasa_chatid', 
        'default_funnel_id', 
        'default_stage_id', 
        'wavoip_token', 
        'wavoip_enabled',
        // Campos de monitoramento de conexão
        'last_connection_check',
        'last_connection_result',
        'last_connection_message',
        'consecutive_failures',
        // Campos de limite de novas conversas (rate limit)
        'new_conv_limit_enabled',
        'new_conv_limit_count',
        'new_conv_limit_period',
        'new_conv_limit_period_value'
    ];
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

