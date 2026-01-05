<?php
/**
 * ParticipantsController - API v1
 * Gerenciamento de participantes em conversas
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\ConversationParticipant;
use App\Models\Conversation;

class ParticipantsController
{
    /**
     * Listar participantes de uma conversa
     * GET /api/v1/conversations/:id/participants
     */
    public function index(string $conversationId): void
    {
        ApiAuthMiddleware::requirePermission('conversations.view');
        
        try {
            $conversation = Conversation::find((int)$conversationId);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            $participants = ConversationParticipant::getByConversation((int)$conversationId);
            
            ApiResponse::success($participants);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar participantes', $e);
        }
    }
    
    /**
     * Adicionar participante
     * POST /api/v1/conversations/:id/participants
     */
    public function store(string $conversationId): void
    {
        ApiAuthMiddleware::requirePermission('conversations.add_participant');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['user_id'])) {
            ApiResponse::badRequest('user_id é obrigatório');
        }
        
        try {
            $conversation = Conversation::find((int)$conversationId);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            // Verificar se já é participante
            $existing = ConversationParticipant::isParticipant((int)$conversationId, (int)$input['user_id']);
            
            if ($existing) {
                ApiResponse::badRequest('Usuário já é participante desta conversa');
            }
            
            $data = [
                'conversation_id' => (int)$conversationId,
                'user_id' => (int)$input['user_id'],
                'role' => $input['role'] ?? 'observer', // observer ou collaborator
                'added_by' => ApiAuthMiddleware::userId()
            ];
            
            ConversationParticipant::create($data);
            
            ApiResponse::created(null, 'Participante adicionado com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Remover participante
     * DELETE /api/v1/conversations/:id/participants/:userId
     */
    public function destroy(string $conversationId, string $userId): void
    {
        ApiAuthMiddleware::requirePermission('conversations.remove_participant');
        
        try {
            $conversation = Conversation::find((int)$conversationId);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            ConversationParticipant::remove((int)$conversationId, (int)$userId);
            
            ApiResponse::success(null, 'Participante removido com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao remover participante', $e);
        }
    }
}
