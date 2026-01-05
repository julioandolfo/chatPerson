<?php
/**
 * ApiTokenController
 * Gerenciamento de tokens de API (interface web)
 */

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Response;
use App\Helpers\Permission;
use App\Helpers\Request;
use App\Models\ApiToken;
use App\Models\ApiLog;

class ApiTokenController
{
    /**
     * Listar tokens
     */
    public function index(): void
    {
        Permission::abortIfCannot('settings.manage');
        
        $userId = Auth::id();
        $tokens = ApiToken::getByUser($userId);
        
        // Obter estatísticas de cada token
        foreach ($tokens as &$token) {
            $token['stats'] = ApiLog::getStats($token['id']);
        }
        
        Response::view('settings/api-tokens/index', [
            'tokens' => $tokens
        ]);
    }
    
    /**
     * Criar token
     */
    public function store(): void
    {
        Permission::abortIfCannot('settings.manage');
        
        try {
            $input = Request::input();
            
            $options = [
                'rate_limit' => (int)($input['rate_limit'] ?? 100),
                'allowed_ips' => $input['allowed_ips'] ?? null,
                'expires_at' => !empty($input['expires_at']) ? $input['expires_at'] : null
            ];
            
            $token = ApiToken::createToken(
                Auth::id(),
                $input['name'] ?? 'Token de API',
                $options
            );
            
            // Retornar token em JSON para exibir no modal
            Response::json([
                'success' => true,
                'message' => 'Token criado com sucesso!',
                'data' => $token
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar token: ' . $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Revogar token
     */
    public function revoke(int $id): void
    {
        Permission::abortIfCannot('settings.manage');
        
        try {
            // Verificar se token pertence ao usuário
            $token = ApiToken::find($id);
            
            if (!$token) {
                Response::json(['success' => false, 'message' => 'Token não encontrado'], 404);
            }
            
            if ($token['user_id'] !== Auth::id() && !Permission::can('settings.manage.all')) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
            }
            
            ApiToken::revoke($id);
            
            Response::json([
                'success' => true,
                'message' => 'Token revogado com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao revogar token: ' . $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Logs de API
     */
    public function logs(): void
    {
        Permission::abortIfCannot('settings.manage');
        
        $tokenId = Request::get('token_id') ? (int)Request::get('token_id') : null;
        $limit = Request::get('limit') ? (int)Request::get('limit') : 100;
        
        if ($tokenId) {
            // Verificar se token pertence ao usuário
            $token = ApiToken::find($tokenId);
            
            if (!$token || ($token['user_id'] !== Auth::id() && !Permission::can('settings.manage.all'))) {
                Permission::abortIfCannot('settings.manage.all');
            }
            
            $logs = ApiLog::getByToken($tokenId, $limit);
        } else {
            $logs = ApiLog::getByUser(Auth::id(), $limit);
        }
        
        // Se for requisição AJAX, retornar JSON
        if (Request::isAjax()) {
            Response::json([
                'success' => true,
                'data' => $logs
            ]);
            return;
        }
        
        // Obter todos os tokens do usuário para filtro
        $tokens = ApiToken::getByUser(Auth::id());
        
        Response::view('settings/api-tokens/logs', [
            'logs' => $logs,
            'tokens' => $tokens,
            'selectedTokenId' => $tokenId
        ]);
    }
    
    /**
     * Estatísticas
     */
    public function stats(): void
    {
        Permission::abortIfCannot('settings.manage');
        
        $userId = Auth::id();
        
        // Stats globais do usuário
        $globalStats = ApiLog::getStats(null, $userId);
        
        // Stats por token
        $tokens = ApiToken::getByUser($userId);
        $tokenStats = [];
        
        foreach ($tokens as $token) {
            $tokenStats[] = [
                'token' => $token,
                'stats' => ApiLog::getStats($token['id'])
            ];
        }
        
        Response::json([
            'success' => true,
            'data' => [
                'global' => $globalStats,
                'tokens' => $tokenStats
            ]
        ]);
    }
}
