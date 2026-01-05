<?php
/**
 * Model ApiToken
 * Gerenciamento de tokens de API
 */

namespace App\Models;

use App\Helpers\Database;

class ApiToken extends Model
{
    protected string $table = 'api_tokens';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'user_id',
        'name',
        'token',
        'permissions',
        'rate_limit',
        'allowed_ips',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'is_active'
    ];
    protected bool $timestamps = true;
    
    /**
     * Gerar token único
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caracteres
    }
    
    /**
     * Criar token de API
     */
    public static function createToken(int $userId, string $name, ?array $options = []): array
    {
        $token = self::generateToken();
        
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'token' => $token,
            'permissions' => !empty($options['permissions']) ? json_encode($options['permissions']) : null,
            'rate_limit' => $options['rate_limit'] ?? 100,
            'allowed_ips' => $options['allowed_ips'] ?? null,
            'expires_at' => $options['expires_at'] ?? null,
            'is_active' => true
        ];
        
        $id = self::create($data);
        
        return self::find($id);
    }
    
    /**
     * Validar token
     */
    public static function validate(string $token): ?array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM api_tokens 
                WHERE token = :token 
                AND is_active = 1 
                AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['token' => $token]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        // Atualizar último uso
        self::updateLastUsed($result['id']);
        
        return $result;
    }
    
    /**
     * Atualizar último uso
     */
    public static function updateLastUsed(int $tokenId): void
    {
        $db = Database::getInstance();
        
        $sql = "UPDATE api_tokens 
                SET last_used_at = NOW(),
                    last_used_ip = :ip
                WHERE id = :id";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id' => $tokenId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    /**
     * Revogar token
     */
    public static function revoke(int $tokenId): bool
    {
        $db = Database::getInstance();
        
        $sql = "UPDATE api_tokens SET is_active = 0 WHERE id = :id";
        $stmt = $db->prepare($sql);
        
        return $stmt->execute(['id' => $tokenId]);
    }
    
    /**
     * Listar tokens de um usuário
     */
    public static function getByUser(int $userId): array
    {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM api_tokens 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter permissões do token
     */
    public static function getPermissions(int $tokenId): ?array
    {
        $token = self::find($tokenId);
        
        if (!$token || empty($token['permissions'])) {
            return null;
        }
        
        return json_decode($token['permissions'], true);
    }
    
    /**
     * Verificar se token pode acessar de determinado IP
     */
    public static function canAccessFromIP(int $tokenId, string $ip): bool
    {
        $token = self::find($tokenId);
        
        if (!$token) {
            return false;
        }
        
        // Se allowed_ips está vazio, permite todos
        if (empty($token['allowed_ips'])) {
            return true;
        }
        
        $allowedIps = array_map('trim', explode(',', $token['allowed_ips']));
        
        return in_array($ip, $allowedIps);
    }
}
