<?php
/**
 * Model WooCommerceIntegration
 * Integrações com WooCommerce
 */

namespace App\Models;

class WooCommerceIntegration extends Model
{
    protected string $table = 'woocommerce_integrations';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'name',
        'woocommerce_url',
        'consumer_key',
        'consumer_secret',
        'contact_field_mapping',
        'search_settings',
        'status',
        'last_sync_at',
        'last_error',
        'sync_frequency_minutes',
        'cache_enabled',
        'cache_ttl_minutes'
    ];
    protected bool $timestamps = true;

    /**
     * Obter integrações ativas
     */
    public static function getActive(): array
    {
        return self::where('status', '=', 'active');
    }

    /**
     * Obter configuração de mapeamento de campos
     */
    public static function getFieldMapping(int $id): array
    {
        $integration = self::find($id);
        if (!$integration) {
            return [];
        }

        $mapping = $integration['contact_field_mapping'] ?? '{}';
        if (is_string($mapping)) {
            return json_decode($mapping, true) ?? [];
        }
        return is_array($mapping) ? $mapping : [];
    }

    /**
     * Obter configurações de busca
     */
    public static function getSearchSettings(int $id): array
    {
        $integration = self::find($id);
        if (!$integration) {
            return [];
        }

        $settings = $integration['search_settings'] ?? '{}';
        if (is_string($settings)) {
            return json_decode($settings, true) ?? [];
        }
        return is_array($settings) ? $settings : [];
    }

    /**
     * Atualizar última sincronização
     */
    public static function updateLastSync(int $id, ?string $error = null): bool
    {
        $data = [
            'last_sync_at' => date('Y-m-d H:i:s')
        ];

        if ($error !== null) {
            $data['last_error'] = $error;
            $data['status'] = 'error';
        } else {
            $data['last_error'] = null;
            if (self::find($id)['status'] === 'error') {
                $data['status'] = 'active';
            }
        }

        return self::update($id, $data);
    }
}

