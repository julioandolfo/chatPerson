<?php

namespace App\Models;

use App\Helpers\Database;

/**
 * Model WhatsAppPhone
 * 
 * Gerencia números WhatsApp conectados via Cloud API
 */
class WhatsAppPhone extends Model
{
    protected string $table = 'whatsapp_phones';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    
    protected array $fillable = [
        'phone_number_id',
        'phone_number',
        'display_phone_number',
        'waba_id',
        'verified_name',
        'quality_rating',
        'account_mode',
        'messaging_limit_tier',
        'integration_account_id',
        'meta_oauth_token_id',
        'is_active',
        'is_connected',
        'last_message_at',
        'webhook_url',
        'webhook_verified',
        'templates_count',
        'last_template_sync_at',
        'disconnected_at',
    ];
    
    /**
     * Buscar por Phone Number ID (Meta)
     */
    public static function findByPhoneNumberId(string $phoneNumberId): ?array
    {
        return self::whereFirst('phone_number_id', '=', $phoneNumberId);
    }
    
    /**
     * Buscar por número de telefone
     */
    public static function findByPhoneNumber(string $phoneNumber): ?array
    {
        return self::whereFirst('phone_number', '=', $phoneNumber);
    }
    
    /**
     * Buscar por WABA ID
     */
    public static function findByWabaId(string $wabaId): array
    {
        $all = self::all();
        return array_filter($all, function($phone) use ($wabaId) {
            return $phone['waba_id'] === $wabaId;
        });
    }
    
    /**
     * Buscar por Integration Account ID
     */
    public static function findByIntegrationAccount(int $integrationAccountId): ?array
    {
        return self::whereFirst('integration_account_id', '=', $integrationAccountId);
    }
    
    /**
     * Listar números ativos
     */
    public static function getActive(): array
    {
        $all = self::all();
        return array_filter($all, function($phone) {
            return $phone['is_active'] && $phone['is_connected'];
        });
    }
    
    /**
     * Atualizar qualidade do número
     */
    public static function updateQuality(int $id, string $qualityRating, ?string $messagingLimitTier = null): bool
    {
        $data = [
            'quality_rating' => $qualityRating,
        ];
        
        if ($messagingLimitTier) {
            $data['messaging_limit_tier'] = $messagingLimitTier;
        }
        
        return self::update($id, $data);
    }
    
    /**
     * Registrar envio/recebimento de mensagem
     */
    public static function recordMessage(int $id): bool
    {
        return self::update($id, [
            'last_message_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Desconectar número
     */
    public static function disconnect(int $id): bool
    {
        return self::update($id, [
            'is_connected' => false,
            'is_active' => false,
            'disconnected_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Reconectar número
     */
    public static function reconnect(int $id, int $metaOAuthTokenId): bool
    {
        return self::update($id, [
            'is_connected' => true,
            'is_active' => true,
            'meta_oauth_token_id' => $metaOAuthTokenId,
            'disconnected_at' => null
        ]);
    }
    
    /**
     * Verificar webhook
     */
    public static function verifyWebhook(int $id): bool
    {
        return self::update($id, [
            'webhook_verified' => true
        ]);
    }
    
    /**
     * Atualizar contagem de templates
     */
    public static function updateTemplatesCount(int $id, int $count): bool
    {
        return self::update($id, [
            'templates_count' => $count,
            'last_template_sync_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Obter token OAuth associado
     */
    public static function getOAuthToken(int $id): ?array
    {
        $phone = self::find($id);
        if (!$phone || !$phone['meta_oauth_token_id']) {
            return null;
        }
        
        return MetaOAuthToken::find($phone['meta_oauth_token_id']);
    }
    
    /**
     * Verificar se o número tem token válido
     */
    public static function hasValidToken(int $id): bool
    {
        $token = self::getOAuthToken($id);
        if (!$token) {
            return false;
        }
        
        return MetaOAuthToken::isValid($token);
    }
    
    /**
     * Verificar se o número está em modo sandbox
     */
    public static function isSandbox(array $phone): bool
    {
        return $phone['account_mode'] === 'SANDBOX';
    }
    
    /**
     * Verificar se o número tem boa qualidade
     */
    public static function hasGoodQuality(array $phone): bool
    {
        return in_array($phone['quality_rating'], ['GREEN', 'UNKNOWN']);
    }
}

