<?php
/**
 * Model IntegrationAccount
 * Model unificado para todas as integrações (Notificame, WhatsApp Official, Quepasa, Evolution)
 */

namespace App\Models;

class IntegrationAccount extends Model
{
    protected string $table = 'integration_accounts';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name', 'provider', 'channel', 'api_token', 'api_url', 
        'account_id', 'phone_number', 'username', 'status', 
        'config', 'webhook_url', 'webhook_secret', 
        'default_funnel_id', 'default_stage_id', 
        'last_sync_at', 'error_message'
    ];
    protected bool $timestamps = true;

    /**
     * Buscar contas por provider e channel
     */
    public static function findByProviderChannel(string $provider, string $channel): array
    {
        return self::where('provider', '=', $provider)
            ->where('channel', '=', $channel)
            ->get();
    }

    /**
     * Buscar conta por provider, channel e phone_number
     */
    public static function findByProviderChannelPhone(string $provider, string $channel, string $phoneNumber): ?array
    {
        return self::where('provider', '=', $provider)
            ->where('channel', '=', $channel)
            ->where('phone_number', '=', $phoneNumber)
            ->first();
    }

    /**
     * Buscar por número de telefone
     */
    public static function findByPhone(string $phoneNumber, string $channel = 'whatsapp'): ?array
    {
        return self::where('phone_number', '=', $phoneNumber)
            ->where('channel', '=', $channel)
            ->first();
    }

    /**
     * Obter contas ativas
     */
    public static function getActive(string $channel = null): array
    {
        $query = self::where('status', '=', 'active');
        
        if ($channel) {
            $query = $query->where('channel', '=', $channel);
        }
        
        return $query->get();
    }

    /**
     * Obter contas por canal
     */
    public static function getByChannel(string $channel): array
    {
        return self::where('channel', '=', $channel)->get();
    }

    /**
     * Obter primeira conta ativa
     */
    public static function getFirstActive(string $channel = null): ?array
    {
        $accounts = self::getActive($channel);
        return !empty($accounts) ? $accounts[0] : null;
    }

    /**
     * Obter contas por provider
     */
    public static function getByProvider(string $provider): array
    {
        return self::where('provider', '=', $provider)->get();
    }

    /**
     * Buscar conta por webhook URL
     */
    public static function findByWebhookUrl(string $webhookUrl): ?array
    {
        return self::where('webhook_url', '=', $webhookUrl)->first();
    }

    /**
     * Obter configuração JSON decodificada
     */
    public function getConfig(): array
    {
        $config = $this->attributes['config'] ?? null;
        if (is_string($config)) {
            return json_decode($config, true) ?? [];
        }
        return is_array($config) ? $config : [];
    }

    /**
     * Definir configuração JSON
     */
    public function setConfig(array $config): void
    {
        $this->attributes['config'] = json_encode($config);
    }
}

