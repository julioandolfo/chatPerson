<?php
/**
 * ConversationsController - API v1
 * Gerenciamento de conversas
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Services\ConversationService;
use App\Models\Conversation;
use App\Helpers\Validator;

class ConversationsController
{
    /**
     * Listar conversas
     * GET /api/v1/conversations
     */
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('conversations.view.all');
        
        // Parâmetros de filtro
        $filters = [
            'status' => $_GET['status'] ?? null,
            'agent_id' => $_GET['agent_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
            'funnel_id' => $_GET['funnel_id'] ?? null,
            'stage_id' => $_GET['stage_id'] ?? null,
            'channel' => $_GET['channel'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Paginação
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 50), 100); // Máximo 100
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');
        
        try {
            $conversations = ConversationService::list($filters, ApiAuthMiddleware::userId());
            
            // Aplicar paginação
            $total = count($conversations);
            $offset = ($page - 1) * $perPage;
            $conversations = array_slice($conversations, $offset, $perPage);
            
            ApiResponse::paginated($conversations, $total, $page, $perPage);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar conversas', $e);
        }
    }
    
    /**
     * Criar conversa
     * POST /api/v1/conversations
     */
    public function store(): void
    {
        ApiAuthMiddleware::requirePermission('conversations.create');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        try {
            $conversation = ConversationService::create($input);
            ApiResponse::created($conversation, 'Conversa criada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Obter conversa
     * GET /api/v1/conversations/:id
     */
    public function show(string $id): void
    {
        try {
            $conversation = Conversation::findWithRelations((int)$id);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            // Verificar permissão
            if (!ConversationService::canView((int)$id, ApiAuthMiddleware::userId())) {
                ApiResponse::forbidden('Você não tem permissão para visualizar esta conversa');
            }
            
            ApiResponse::success($conversation);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter conversa', $e);
        }
    }
    
    /**
     * Atualizar conversa
     * PUT /api/v1/conversations/:id
     */
    public function update(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.edit');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        try {
            $conversation = Conversation::find((int)$id);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            // Campos permitidos para atualização
            $allowedFields = ['status', 'agent_id', 'department_id', 'funnel_id', 'funnel_stage_id'];
            $updateData = array_intersect_key($input, array_flip($allowedFields));
            
            if (empty($updateData)) {
                ApiResponse::badRequest('Nenhum campo válido para atualizar');
            }
            
            Conversation::update((int)$id, $updateData);
            
            $updated = Conversation::findWithRelations((int)$id);
            ApiResponse::success($updated, 'Conversa atualizada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao atualizar conversa', $e);
        }
    }
    
    /**
     * Deletar conversa
     * DELETE /api/v1/conversations/:id
     */
    public function destroy(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.delete');
        
        try {
            $conversation = Conversation::find((int)$id);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            Conversation::delete((int)$id);
            ApiResponse::success(null, 'Conversa deletada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao deletar conversa', $e);
        }
    }
    
    /**
     * Atribuir conversa
     * POST /api/v1/conversations/:id/assign
     */
    public function assign(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.assign');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['agent_id'])) {
            ApiResponse::badRequest('agent_id é obrigatório');
        }
        
        try {
            ConversationService::assignToAgent((int)$id, (int)$input['agent_id']);
            
            $conversation = Conversation::findWithRelations((int)$id);
            ApiResponse::success($conversation, 'Conversa atribuída com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Encerrar conversa
     * POST /api/v1/conversations/:id/close
     */
    public function close(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.close');
        
        try {
            ConversationService::close((int)$id);
            
            $conversation = Conversation::findWithRelations((int)$id);
            ApiResponse::success($conversation, 'Conversa encerrada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Reabrir conversa
     * POST /api/v1/conversations/:id/reopen
     */
    public function reopen(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.reopen');
        
        try {
            ConversationService::reopen((int)$id);
            
            $conversation = Conversation::findWithRelations((int)$id);
            ApiResponse::success($conversation, 'Conversa reaberta com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Mover conversa no funil
     * POST /api/v1/conversations/:id/move-stage
     */
    public function moveStage(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.move_stage');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['funnel_id']) || empty($input['stage_id'])) {
            ApiResponse::badRequest('funnel_id e stage_id são obrigatórios');
        }
        
        try {
            ConversationService::moveToStage((int)$id, (int)$input['funnel_id'], (int)$input['stage_id']);
            
            $conversation = Conversation::findWithRelations((int)$id);
            ApiResponse::success($conversation, 'Conversa movida com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Atualizar setor
     * PUT /api/v1/conversations/:id/department
     */
    public function updateDepartment(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.edit');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (!isset($input['department_id'])) {
            ApiResponse::badRequest('department_id é obrigatório');
        }
        
        try {
            Conversation::update((int)$id, ['department_id' => $input['department_id']]);
            
            $conversation = Conversation::findWithRelations((int)$id);
            ApiResponse::success($conversation, 'Setor atualizado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao atualizar setor', $e);
        }
    }
    
    /**
     * Adicionar tag
     * POST /api/v1/conversations/:id/tags
     */
    public function addTag(string $id): void
    {
        ApiAuthMiddleware::requirePermission('conversations.edit');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['tag_id'])) {
            ApiResponse::badRequest('tag_id é obrigatório');
        }
        
        try {
            \App\Models\ConversationTag::create([
                'conversation_id' => (int)$id,
                'tag_id' => (int)$input['tag_id']
            ]);
            
            ApiResponse::success(null, 'Tag adicionada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Remover tag
     * DELETE /api/v1/conversations/:id/tags/:tagId
     */
    public function removeTag(string $id, string $tagId): void
    {
        ApiAuthMiddleware::requirePermission('conversations.edit');
        
        try {
            \App\Models\ConversationTag::deleteByConversationAndTag((int)$id, (int)$tagId);
            ApiResponse::success(null, 'Tag removida com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao remover tag', $e);
        }
    }
}
