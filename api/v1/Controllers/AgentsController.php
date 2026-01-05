<?php
/**
 * AgentsController - API v1
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\User;

class AgentsController
{
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('users.view');
        
        try {
            $agents = User::where('is_active', 1)->get();
            ApiResponse::success($agents);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar agentes', $e);
        }
    }
    
    public function show(string $id): void
    {
        ApiAuthMiddleware::requirePermission('users.view');
        
        try {
            $agent = User::find((int)$id);
            
            if (!$agent) {
                ApiResponse::notFound('Agente não encontrado');
            }
            
            unset($agent['password']);
            ApiResponse::success($agent);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter agente', $e);
        }
    }
    
    public function stats(string $id): void
    {
        ApiAuthMiddleware::requirePermission('users.view');
        
        try {
            $stats = \App\Services\DashboardService::getAgentStats((int)$id);
            ApiResponse::success($stats);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter estatísticas', $e);
        }
    }
}
