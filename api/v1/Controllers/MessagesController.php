<?php
/**
 * MessagesController - API v1
 * Gerenciamento de mensagens
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Services\MessageService;
use App\Services\ConversationService;
use App\Models\Message;
use App\Models\Conversation;

class MessagesController
{
    /**
     * Listar mensagens de uma conversa
     * GET /api/v1/conversations/:id/messages
     */
    public function index(string $conversationId): void
    {
        try {
            // Verificar se conversa existe e se usuário tem permissão
            $conversation = Conversation::find((int)$conversationId);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            if (!ConversationService::canView((int)$conversationId, ApiAuthMiddleware::userId())) {
                ApiResponse::forbidden('Você não tem permissão para visualizar esta conversa');
            }
            
            // Paginação
            $page = (int)($_GET['page'] ?? 1);
            $perPage = min((int)($_GET['per_page'] ?? 50), 100);
            
            $messages = Message::getByConversation((int)$conversationId);
            
            // Aplicar paginação
            $total = count($messages);
            $offset = ($page - 1) * $perPage;
            $messages = array_slice($messages, $offset, $perPage);
            
            ApiResponse::paginated($messages, $total, $page, $perPage);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar mensagens', $e);
        }
    }
    
    /**
     * Enviar mensagem
     * POST /api/v1/conversations/:id/messages
     */
    public function store(string $conversationId): void
    {
        ApiAuthMiddleware::requirePermission('messages.send');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        if (empty($input['body'])) {
            ApiResponse::badRequest('Campo body é obrigatório');
        }
        
        try {
            // Verificar se conversa existe
            $conversation = Conversation::find((int)$conversationId);
            
            if (!$conversation) {
                ApiResponse::notFound('Conversa não encontrada');
            }
            
            // Preparar dados da mensagem
            $messageData = [
                'conversation_id' => (int)$conversationId,
                'body' => $input['body'],
                'type' => $input['type'] ?? 'text',
                'direction' => 'outbound',
                'sender_id' => ApiAuthMiddleware::userId(),
                'sender_type' => 'user'
            ];
            
            $message = MessageService::send($messageData);
            
            ApiResponse::created($message, 'Mensagem enviada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    /**
     * Obter mensagem específica
     * GET /api/v1/messages/:id
     */
    public function show(string $id): void
    {
        try {
            $message = Message::find((int)$id);
            
            if (!$message) {
                ApiResponse::notFound('Mensagem não encontrada');
            }
            
            // Verificar permissão na conversa
            if (!ConversationService::canView($message['conversation_id'], ApiAuthMiddleware::userId())) {
                ApiResponse::forbidden('Você não tem permissão para visualizar esta mensagem');
            }
            
            ApiResponse::success($message);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter mensagem', $e);
        }
    }
}
