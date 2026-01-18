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
        $sql = "SELECT * FROM integration_accounts WHERE provider = ? AND channel = ?";
        return \App\Helpers\Database::fetchAll($sql, [$provider, $channel]);
    }

    /**
     * Buscar conta por provider, channel e phone_number
     */
    public static function findByProviderChannelPhone(string $provider, string $channel, string $phoneNumber): ?array
    {
        $sql = "SELECT * FROM integration_accounts 
                WHERE provider = ? AND channel = ? AND phone_number = ? 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$provider, $channel, $phoneNumber]);
    }

    /**
     * Buscar por número de telefone
     */
    public static function findByPhone(string $phoneNumber, string $channel = 'whatsapp'): ?array
    {
        $sql = "SELECT * FROM integration_accounts 
                WHERE phone_number = ? AND channel = ? 
                LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$phoneNumber, $channel]);
    }

    /**
     * Obter contas ativas
     */
    public static function getActive(string $channel = null): array
    {
        if ($channel) {
            $sql = "SELECT * FROM integration_accounts WHERE status = 'active' AND channel = ?";
            return \App\Helpers\Database::fetchAll($sql, [$channel]);
        }

        $sql = "SELECT * FROM integration_accounts WHERE status = 'active'";
        return \App\Helpers\Database::fetchAll($sql, []);
    }

    /**
     * Obter contas por canal
     */
    public static function getByChannel(string $channel): array
    {
        $sql = "SELECT * FROM integration_accounts WHERE channel = ?";
        return \App\Helpers\Database::fetchAll($sql, [$channel]);
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
        $sql = "SELECT * FROM integration_accounts WHERE provider = ?";
        return \App\Helpers\Database::fetchAll($sql, [$provider]);
    }

    /**
     * Buscar conta por webhook URL
     */
    public static function findByWebhookUrl(string $webhookUrl): ?array
    {
        $sql = "SELECT * FROM integration_accounts WHERE webhook_url = ? LIMIT 1";
        return \App\Helpers\Database::fetch($sql, [$webhookUrl]);
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

