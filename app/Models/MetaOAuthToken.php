<?php

namespace App\Models;

use App\Helpers\Database;

/**
 * Model MetaOAuthToken
 * 
 * Gerencia tokens OAuth 2.0 da Meta (Instagram + WhatsApp)
 */
class MetaOAuthToken extends Model
{
    protected string $table = 'meta_oauth_tokens';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    
    protected array $fillable = [
        'meta_user_id',
        'app_type',
        'access_token',
        'token_type',
        'expires_at',
        'refresh_token',
        'scopes',
        'integration_account_id',
        'meta_app_id',
        'last_used_at',
        'is_valid',
        'revoked_at',
    ];
    
    /**
     * Buscar token por Meta User ID
     */
    public static function getByMetaUserId(string $metaUserId): ?array
    {
        return self::whereFirst('meta_user_id', '=', $metaUserId);
    }
    
    /**
     * Buscar token por Integration Account ID
     */
    public static function getByIntegrationAccount(int $integrationAccountId): ?array
    {
        return self::whereFirst('integration_account_id', '=', $integrationAccountId);
    }
    
    /**
     * Verificar se o token está expirado
     */
    public static function isExpired(array $token): bool
    {
        if (empty($token['expires_at'])) {
            return false; // Tokens sem expiração (não comuns na Meta)
        }
        
        $expiresAt = strtotime($token['expires_at']);
        $now = time();
        
        return $now >= $expiresAt;
    }
    
    /**
     * Verificar se o token está válido
     */
    public static function isValid(array $token): bool
    {
        if (!$token['is_valid']) {
            return false;
        }
        
        if (!empty($token['revoked_at'])) {
            return false;
        }
        
        return !self::isExpired($token);
    }
    
    /**
     * Marcar token como usado (atualizar last_used_at)
     */
    public static function markAsUsed(int $id): bool
    {
        return self::update($id, [
            'last_used_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Revogar token
     */
    public static function revoke(int $id): bool
    {
        return self::update($id, [
            'is_valid' => false,
            'revoked_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Renovar token (atualizar com novo access_token e expires_at)
     */
    public static function renew(int $id, string $newAccessToken, ?string $expiresAt = null): bool
    {
        $data = [
            'access_token' => $newAccessToken,
            'is_valid' => true,
            'revoked_at' => null,
        ];
        
        if ($expiresAt) {
            $data['expires_at'] = $expiresAt;
        }
        
        return self::update($id, $data);
    }
    
    /**
     * Obter tokens que estão prestes a expirar (nos próximos 7 dias)
     */
    public static function getExpiringSoon(int $daysAhead = 7): array
    {
        $db = Database::getInstance();
        $date = date('Y-m-d H:i:s', strtotime("+{$daysAhead} days"));
        
        $query = "SELECT * FROM {$this->table} 
                  WHERE is_valid = 1 
                  AND expires_at IS NOT NULL 
                  AND expires_at <= ? 
                  AND expires_at > NOW()
                  ORDER BY expires_at ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$date]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Limpar tokens expirados (marcar como inválidos)
     */
    public static function cleanExpired(): int
    {
        $db = Database::getInstance();
        
        $query = "UPDATE meta_oauth_tokens 
                  SET is_valid = 0 
                  WHERE expires_at IS NOT NULL 
                  AND expires_at < NOW() 
                  AND is_valid = 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
}

