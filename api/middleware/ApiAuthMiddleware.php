<?php
/**
 * Middleware de Autenticação da API
 * Valida tokens JWT e API Tokens
 */

namespace Api\Middleware;

use Api\Helpers\JWTHelper;
use Api\Helpers\ApiResponse;
use App\Models\ApiToken;
use App\Models\User;

class ApiAuthMiddleware
{
    private static ?array $currentUser = null;
    private static ?array $currentToken = null;
    
    /**
     * Validar autenticação
     */
    public static function handle(): void
    {
        // Tentar JWT primeiro
        $jwt = JWTHelper::extractFromHeader();
        
        if ($jwt) {
            self::validateJWT($jwt);
            return;
        }
        
        // Tentar API Token
        $apiToken = self::extractApiToken();
        
        if ($apiToken) {
            self::validateApiToken($apiToken);
            return;
        }
        
        // Nenhum token fornecido
        ApiResponse::unauthorized('Token de autenticação não fornecido');
    }
    
    /**
     * Validar JWT
     */
    private static function validateJWT(string $jwt): void
    {
        $payload = JWTHelper::validate($jwt);
        
        if (!$payload) {
            ApiResponse::unauthorized('Token JWT inválido ou expirado');
        }
        
        // Obter usuário
        $userId = $payload['user_id'] ?? null;
        
        if (!$userId) {
            ApiResponse::unauthorized('Token JWT inválido');
        }
        
        $user = User::find($userId);
        
        if (!$user) {
            ApiResponse::unauthorized('Usuário não encontrado');
        }
        
        if (!$user['is_active']) {
            ApiResponse::forbidden('Usuário inativo');
        }
        
        self::$currentUser = $user;
    }
    
    /**
     * Validar API Token
     */
    private static function validateApiToken(string $token): void
    {
        $apiToken = ApiToken::validate($token);
        
        if (!$apiToken) {
            ApiResponse::unauthorized('API Token inválido, expirado ou revogado');
        }
        
        // Verificar IP permitido
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        
        if (!ApiToken::canAccessFromIP($apiToken['id'], $clientIp)) {
            ApiResponse::forbidden('IP não autorizado para este token');
        }
        
        // Obter usuário
        $user = User::find($apiToken['user_id']);
        
        if (!$user) {
            ApiResponse::unauthorized('Usuário não encontrado');
        }
        
        if (!$user['is_active']) {
            ApiResponse::forbidden('Usuário inativo');
        }
        
        self::$currentUser = $user;
        self::$currentToken = $apiToken;
    }
    
    /**
     * Extrair API Token do header ou query string
     */
    private static function extractApiToken(): ?string
    {
        // Tentar do header Authorization
        $headers = getallheaders();
        
        if (!$headers) {
            // Fallback
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }
        
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if ($authHeader && preg_match('/Token\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Tentar do header X-API-Key
        if (isset($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }
        
        if (isset($headers['X-Api-Key'])) {
            return $headers['X-Api-Key'];
        }
        
        // Tentar da query string (menos seguro, mas útil para webhooks)
        if (isset($_GET['api_token'])) {
            return $_GET['api_token'];
        }
        
        return null;
    }
    
    /**
     * Obter usuário autenticado
     */
    public static function user(): ?array
    {
        return self::$currentUser;
    }
    
    /**
     * Obter ID do usuário autenticado
     */
    public static function userId(): ?int
    {
        return self::$currentUser['id'] ?? null;
    }
    
    /**
     * Obter token atual (se for API Token)
     */
    public static function token(): ?array
    {
        return self::$currentToken;
    }
    
    /**
     * Verificar se usuário tem permissão
     */
    public static function can(string $permission): bool
    {
        $user = self::user();
        
        if (!$user) {
            return false;
        }
        
        // Se houver permissões específicas no token, usar elas
        if (self::$currentToken) {
            $tokenPermissions = ApiToken::getPermissions(self::$currentToken['id']);
            
            if ($tokenPermissions !== null) {
                return in_array($permission, $tokenPermissions);
            }
        }
        
        // Senão, usar permissões do usuário
        return \App\Helpers\Permission::userHasPermission($user['id'], $permission);
    }
    
    /**
     * Abortar se não tiver permissão
     */
    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            ApiResponse::forbidden("Você não tem permissão: {$permission}");
        }
    }
}
