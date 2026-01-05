<?php
/**
 * Helper JWT
 * Geração e validação de tokens JWT
 */

namespace Api\Helpers;

class JWTHelper
{
    /**
     * Chave secreta para assinar tokens (deve estar em .env em produção)
     */
    private static function getSecretKey(): string
    {
        // Tentar obter do .env ou configuração
        $secret = getenv('JWT_SECRET') ?: (\App\Models\Setting::get('jwt_secret') ?? null);
        
        if (!$secret) {
            // Gerar e salvar se não existir
            $secret = bin2hex(random_bytes(32));
            \App\Models\Setting::set('jwt_secret', $secret);
        }
        
        return $secret;
    }
    
    /**
     * Codificar para Base64 URL-safe
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Decodificar de Base64 URL-safe
     */
    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
    
    /**
     * Gerar token JWT
     * 
     * @param array $payload Dados a incluir no token
     * @param int $expiresIn Tempo de validade em segundos (padrão: 1 hora)
     */
    public static function generate(array $payload, int $expiresIn = 3600): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $issuedAt = time();
        $expire = $issuedAt + $expiresIn;
        
        $payload['iat'] = $issuedAt;  // Issued at
        $payload['exp'] = $expire;     // Expiration time
        
        // Codificar header e payload
        $base64UrlHeader = self::base64UrlEncode(json_encode($header));
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        
        // Criar assinatura
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignature = self::base64UrlEncode($signature);
        
        // Montar token
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Validar e decodificar token JWT
     * 
     * @return array|null Payload do token ou null se inválido
     */
    public static function validate(string $token): ?array
    {
        // Dividir token em partes
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;
        
        // Verificar assinatura
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecretKey(), true);
        $base64UrlSignatureExpected = self::base64UrlEncode($signature);
        
        if ($base64UrlSignature !== $base64UrlSignatureExpected) {
            return null; // Assinatura inválida
        }
        
        // Decodificar payload
        $payload = json_decode(self::base64UrlDecode($base64UrlPayload), true);
        
        if (!$payload) {
            return null;
        }
        
        // Verificar expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Token expirado
        }
        
        return $payload;
    }
    
    /**
     * Extrair token do header Authorization
     */
    public static function extractFromHeader(): ?string
    {
        $headers = getallheaders();
        
        if (!$headers) {
            // Fallback para Apache/FastCGI
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader) {
            return null;
        }
        
        // Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Gerar token de refresh
     */
    public static function generateRefreshToken(int $userId): string
    {
        return self::generate([
            'user_id' => $userId,
            'type' => 'refresh'
        ], 86400 * 30); // 30 dias
    }
    
    /**
     * Renovar token (refresh)
     */
    public static function refresh(string $refreshToken): ?string
    {
        $payload = self::validate($refreshToken);
        
        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            return null;
        }
        
        // Gerar novo token de acesso
        return self::generate([
            'user_id' => $payload['user_id']
        ]);
    }
}
