<?php
/**
 * FunnelsController - API v1
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Conversation;

class FunnelsController
{
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('funnels.view');
        
        try {
            $funnels = Funnel::all();
            ApiResponse::success($funnels);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar funis', $e);
        }
    }
    
    public function show(string $id): void
    {
        ApiAuthMiddleware::requirePermission('funnels.view');
        
        try {
            $funnel = Funnel::find((int)$id);
            
            if (!$funnel) {
                ApiResponse::notFound('Funil não encontrado');
            }
            
            ApiResponse::success($funnel);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter funil', $e);
        }
    }
    
    public function stages(string $id): void
    {
        ApiAuthMiddleware::requirePermission('funnels.view');
        
        try {
            $stages = FunnelStage::getByFunnel((int)$id);
            ApiResponse::success($stages);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar etapas', $e);
        }
    }
    
    public function conversations(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.view.all');
        
        try {
            $conversations = Conversation::where('funnel_id', (int)$id)->get();
            ApiResponse::success($conversations);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar conversas', $e);
        }
    }
}
