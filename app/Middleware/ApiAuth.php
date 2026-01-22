<?php
/**
 * Middleware de Autenticação via API Token
 * 
 * Valida tokens da API REST (Bearer token ou X-API-Key)
 */

namespace App\Middleware;

use App\Helpers\Response;
use App\Models\ApiToken;
use App\Models\ApiLog;

class ApiAuth
{
    /**
     * Token validado (disponível para os controllers)
     */
    private static ?array $authenticatedToken = null;
    
    /**
     * Usuário autenticado via token
     */
    private static ?int $authenticatedUserId = null;
    
    /**
     * Verificar autenticação via API Token
     */
    public function handle(): void
    {
        $token = self::extractToken();
        
        if (!$token) {
            self::respondUnauthorized('Token de autenticação não fornecido');
            return;
        }
        
        // Validar token
        $tokenData = ApiToken::validate($token);
        
        if (!$tokenData) {
            self::respondUnauthorized('Token inválido ou expirado');
            return;
        }
        
        // Verificar IP se configurado
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!ApiToken::canAccessFromIP($tokenData['id'], $clientIp)) {
            self::respondUnauthorized('Acesso não permitido deste IP');
            return;
        }
        
        // Verificar rate limit
        if (!self::checkRateLimit($tokenData)) {
            self::respondRateLimited($tokenData['rate_limit']);
            return;
        }
        
        // Armazenar dados do token autenticado
        self::$authenticatedToken = $tokenData;
        self::$authenticatedUserId = (int)$tokenData['user_id'];
    }
    
    /**
     * Extrair token do header Authorization ou X-API-Key
     */
    private static function extractToken(): ?string
    {
        // Tentar Authorization: Bearer <token>
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        
        // Tentar X-API-Key
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return trim($_SERVER['HTTP_X_API_KEY']);
        }
        
        return null;
    }
    
    /**
     * Verificar rate limit
     */
    private static function checkRateLimit(array $tokenData): bool
    {
        $limit = (int)($tokenData['rate_limit'] ?? 100);
        $tokenId = $tokenData['id'];
        
        // Contar requisições no último minuto
        $count = ApiLog::countRecentRequests($tokenId, 60);
        
        // Definir headers de rate limit
        $remaining = max(0, $limit - $count - 1);
        $reset = time() + 60;
        
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$reset}");
        
        return $count < $limit;
    }
    
    /**
     * Responder 401 Unauthorized
     */
    private static function respondUnauthorized(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Responder 429 Too Many Requests
     */
    private static function respondRateLimited(int $limit): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: 60');
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'TOO_MANY_REQUESTS',
                'message' => "Limite de {$limit} requisições por minuto excedido"
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Obter token autenticado
     */
    public static function getToken(): ?array
    {
        return self::$authenticatedToken;
    }
    
    /**
     * Obter ID do usuário autenticado via token
     */
    public static function getUserId(): ?int
    {
        return self::$authenticatedUserId;
    }
    
    /**
     * Verificar se está autenticado via API
     */
    public static function isAuthenticated(): bool
    {
        return self::$authenticatedToken !== null;
    }
}
