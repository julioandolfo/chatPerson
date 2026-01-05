<?php
/**
 * AuthController - API v1
 * Autenticação e gerenciamento de tokens
 */

namespace Api\V1\Controllers;

use Api\Helpers\JWTHelper;
use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\User;
use App\Helpers\Validator;

class AuthController
{
    /**
     * Login - Obter token JWT
     * POST /api/v1/auth/login
     */
    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        // Validar dados
        $errors = Validator::validate($input, [
            'email' => 'required|email',
            'password' => 'required|string'
        ]);
        
        if (!empty($errors)) {
            ApiResponse::validationError('Dados de login inválidos', $errors);
        }
        
        // Buscar usuário
        $user = User::whereFirst('email', $input['email']);
        
        if (!$user) {
            ApiResponse::unauthorized('Credenciais inválidas');
        }
        
        // Verificar senha
        if (!password_verify($input['password'], $user['password'])) {
            ApiResponse::unauthorized('Credenciais inválidas');
        }
        
        // Verificar se está ativo
        if (!$user['is_active']) {
            ApiResponse::forbidden('Usuário inativo');
        }
        
        // Gerar tokens
        $accessToken = JWTHelper::generate([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ], 3600); // 1 hora
        
        $refreshToken = JWTHelper::generateRefreshToken($user['id']);
        
        // Remover senha do retorno
        unset($user['password']);
        
        ApiResponse::success([
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]);
    }
    
    /**
     * Renovar token
     * POST /api/v1/auth/refresh
     */
    public function refresh(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['refresh_token'])) {
            ApiResponse::badRequest('Refresh token não fornecido');
        }
        
        $accessToken = JWTHelper::refresh($input['refresh_token']);
        
        if (!$accessToken) {
            ApiResponse::unauthorized('Refresh token inválido ou expirado');
        }
        
        ApiResponse::success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ]);
    }
    
    /**
     * Logout (opcional - tokens JWT são stateless)
     * POST /api/v1/auth/logout
     */
    public function logout(): void
    {
        // Com JWT, logout é feito no client-side (descartando o token)
        // Mas podemos adicionar o token a uma blacklist se necessário
        
        ApiResponse::success(null, 200, 'Logout realizado com sucesso');
    }
    
    /**
     * Obter dados do usuário autenticado
     * GET /api/v1/auth/me
     */
    public function me(): void
    {
        $user = ApiAuthMiddleware::user();
        
        if (!$user) {
            ApiResponse::unauthorized();
        }
        
        // Remover senha
        unset($user['password']);
        
        // Adicionar permissões
        $permissions = \App\Helpers\Permission::getUserPermissions($user['id']);
        
        ApiResponse::success([
            'user' => $user,
            'permissions' => array_keys($permissions)
        ]);
    }
}
