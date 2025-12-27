<?php

namespace App\Models;

use App\Helpers\Database;

/**
 * Model InstagramAccount
 * 
 * Gerencia contas Instagram conectadas via Graph API
 */
class InstagramAccount extends Model
{
    protected string $table = 'instagram_accounts';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    
    protected array $fillable = [
        'instagram_user_id',
        'username',
        'name',
        'profile_picture_url',
        'biography',
        'website',
        'followers_count',
        'follows_count',
        'media_count',
        'account_type',
        'integration_account_id',
        'meta_oauth_token_id',
        'facebook_page_id',
        'is_active',
        'is_connected',
        'last_synced_at',
        'auto_reply',
        'welcome_message',
        'disconnected_at',
    ];
    
    /**
     * Buscar por Instagram User ID
     */
    public static function findByInstagramUserId(string $instagramUserId): ?array
    {
        return self::whereFirst('instagram_user_id', '=', $instagramUserId);
    }
    
    /**
     * Buscar por username
     */
    public static function findByUsername(string $username): ?array
    {
        return self::whereFirst('username', '=', $username);
    }
    
    /**
     * Buscar por Integration Account ID
     */
    public static function findByIntegrationAccount(int $integrationAccountId): ?array
    {
        return self::whereFirst('integration_account_id', '=', $integrationAccountId);
    }
    
    /**
     * Listar contas ativas
     */
    public static function getActive(): array
    {
        $all = self::all();
        return array_filter($all, function($account) {
            return $account['is_active'] && $account['is_connected'];
        });
    }
    
    /**
     * Atualizar estatísticas da conta
     */
    public static function updateStats(int $id, array $stats): bool
    {
        $data = [];
        
        if (isset($stats['followers_count'])) {
            $data['followers_count'] = $stats['followers_count'];
        }
        if (isset($stats['follows_count'])) {
            $data['follows_count'] = $stats['follows_count'];
        }
        if (isset($stats['media_count'])) {
            $data['media_count'] = $stats['media_count'];
        }
        
        $data['last_synced_at'] = date('Y-m-d H:i:s');
        
        return self::update($id, $data);
    }
    
    /**
     * Desconectar conta
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
     * Reconectar conta
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
     * Obter token OAuth associado
     */
    public static function getOAuthToken(int $id): ?array
    {
        $account = self::find($id);
        if (!$account || !$account['meta_oauth_token_id']) {
            return null;
        }
        
        return MetaOAuthToken::find($account['meta_oauth_token_id']);
    }
    
    /**
     * Verificar se a conta tem token válido
     */
    public static function hasValidToken(int $id): bool
    {
        $token = self::getOAuthToken($id);
        if (!$token) {
            return false;
        }
        
        return MetaOAuthToken::isValid($token);
    }
}

