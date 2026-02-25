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
    
    /**
     * Enviar mensagem via WhatsApp (endpoint direto para Personizi)
     * 
     * POST /api/v1/messages/send
     * 
     * Body:
     * {
     *   "to": "5511999999999",
     *   "from": "5511916127354",
     *   "message": "Texto da mensagem",
     *   "contact_name": "Nome do Contato" (opcional)
     * }
     */
    public function send(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ApiResponse::validationError('Dados inválidos', ['body' => ['JSON inválido']]);
            }
            
            $errors = [];
            if (empty($input['to'])) $errors['to'] = ['Campo obrigatório'];
            if (empty($input['from'])) $errors['from'] = ['Campo obrigatório'];
            if (empty($input['message'])) $errors['message'] = ['Campo obrigatório'];
            if (!empty($errors)) ApiResponse::validationError('Dados inválidos', $errors);
            
            $to = preg_replace('/[^0-9]/', '', $input['to']);
            $from = preg_replace('/[^0-9]/', '', $input['from']);
            $message = $input['message'];
            $contactName = trim($input['contact_name'] ?? '');
            
            if (strlen($to) < 10) ApiResponse::validationError('Número de destino inválido', ['to' => ['Número muito curto']]);
            if (strlen($from) < 10) ApiResponse::validationError('Número de origem inválido', ['from' => ['Número muito curto']]);
            
            // Buscar conta WhatsApp via Model
            $account = \App\Models\IntegrationAccount::findWhatsAppByPhone($from);
            if (!$account) {
                ApiResponse::validationError('Conta WhatsApp não encontrada ou inativa', [
                    'from' => ['Nenhuma conta WhatsApp ativa encontrada para o número: ' . $from]
                ]);
            }
            
            if ($account['status'] !== 'active' && $account['status'] !== 'connected') {
                ApiResponse::validationError('Integração não está ativa', [
                    'from' => ["Integração WhatsApp não está ativa (status: {$account['status']})"]
                ]);
            }
            
            // Buscar ou criar contato via Model
            $contact = \App\Models\Contact::findByPhoneNormalized($to);
            $isNewContact = false;
            
            if (!$contact) {
                $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($to);
                $contactId = \App\Models\Contact::create([
                    'name' => !empty($contactName) ? $contactName : $normalizedPhone,
                    'phone' => $normalizedPhone
                ]);
                $contact = \App\Models\Contact::find($contactId);
                $isNewContact = true;
            } else {
                if (!empty($contactName) && (empty($contact['name']) || $contact['name'] === $contact['phone'])) {
                    \App\Models\Contact::update($contact['id'], ['name' => $contactName]);
                    $contact['name'] = $contactName;
                }
            }
            
            // Buscar ou criar conversa via Model
            $conversation = Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
            $isNewConversation = false;
            
            if (!$conversation) {
                $conversationId = Conversation::create([
                    'contact_id' => $contact['id'],
                    'channel' => 'whatsapp',
                    'integration_account_id' => $account['id'],
                    'status' => 'open',
                    'funnel_id' => $account['default_funnel_id'] ?? null,
                    'funnel_stage_id' => $account['default_stage_id'] ?? null
                ]);
                $conversation = Conversation::find($conversationId);
                $isNewConversation = true;
            } else {
                $conversationId = $conversation['id'];
                if ($conversation['status'] === 'closed' || $conversation['status'] === 'resolved') {
                    Conversation::reopen($conversationId);
                    $conversation['status'] = 'open';
                }
            }
            
            // Enviar mensagem via WhatsAppService (com retry, error handling, etc.)
            $sendResult = \App\Services\WhatsAppService::sendMessage(
                $account['id'],
                $to,
                $message,
                []
            );
            
            // Salvar mensagem no banco APÓS envio (com status real)
            $messageData = [
                'conversation_id' => $conversation['id'],
                'sender_type' => 'agent',
                'sender_id' => ApiAuthMiddleware::userId(),
                'content' => $message,
                'message_type' => 'text',
                'external_id' => $sendResult['message_id'] ?? null,
                'status' => ($sendResult['success'] ?? false) ? 'sent' : 'error'
            ];
            
            $messageId = Message::createMessage($messageData);
            
            // Atualizar last_message_at da conversa
            Conversation::update($conversation['id'], [
                'last_message_at' => date('Y-m-d H:i:s')
            ]);
            
            $success = $sendResult['success'] ?? false;
            
            if (!$success) {
                ApiResponse::error(
                    'Mensagem salva mas falhou ao enviar via WhatsApp: ' . ($sendResult['error'] ?? 'Erro desconhecido'),
                    502,
                    'WHATSAPP_SEND_FAILED',
                    [
                        'message_id' => (string) $messageId,
                        'conversation_id' => (string) $conversation['id'],
                        'status' => 'error'
                    ]
                );
                return;
            }
            
            ApiResponse::created([
                'message_id' => (string) $messageId,
                'external_id' => $sendResult['message_id'] ?? null,
                'conversation_id' => (string) $conversation['id'],
                'contact_id' => (string) $contact['id'],
                'status' => 'sent',
                'is_new_contact' => $isNewContact,
                'is_new_conversation' => $isNewConversation
            ], 'Mensagem enviada com sucesso');
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("[API-V1-SEND] Exception: " . $e->getMessage());
            ApiResponse::serverError('Erro ao enviar mensagem', $e);
        }
    }
}
