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
            // Obter dados do body
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ApiResponse::validationError('Dados inválidos', ['body' => ['JSON inválido']]);
            }
            
            // Validar campos obrigatórios
            $errors = [];
            
            if (empty($input['to'])) {
                $errors['to'] = ['Campo obrigatório'];
            }
            
            if (empty($input['from'])) {
                $errors['from'] = ['Campo obrigatório'];
            }
            
            if (empty($input['message'])) {
                $errors['message'] = ['Campo obrigatório'];
            }
            
            if (!empty($errors)) {
                ApiResponse::validationError('Dados inválidos', $errors);
            }
            
            // Limpar números (remover caracteres não numéricos)
            $to = preg_replace('/[^0-9]/', '', $input['to']);
            $from = preg_replace('/[^0-9]/', '', $input['from']);
            $message = $input['message'];
            $contactName = $input['contact_name'] ?? '';
            
            // Validar formato dos números
            if (strlen($to) < 10) {
                ApiResponse::validationError('Número de destino inválido', ['to' => ['Número muito curto']]);
            }
            
            if (strlen($from) < 10) {
                ApiResponse::validationError('Número de origem inválido', ['from' => ['Número muito curto']]);
            }
            
            $db = \App\Helpers\Database::getInstance();
            
            // Buscar conta WhatsApp pelo número "from"
            $stmt = $db->prepare("
                SELECT id, name, api_url, api_key, provider, status, quepasa_token, quepasa_user
                FROM whatsapp_accounts 
                WHERE phone_number = ? AND status = 'active'
                LIMIT 1
            ");
            
            $stmt->execute([$from]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$account) {
                ApiResponse::validationError('Conta WhatsApp não encontrada ou inativa', [
                    'from' => ['Nenhuma conta WhatsApp ativa encontrada para o número: ' . $from]
                ]);
            }
            
            // Buscar ou criar contato
            $stmt = $db->prepare("SELECT id FROM contacts WHERE phone_number = ? LIMIT 1");
            $stmt->execute([$to]);
            $contact = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$contact) {
                // Criar novo contato
                $stmt = $db->prepare("
                    INSERT INTO contacts (phone_number, name, channel, created_at, updated_at)
                    VALUES (?, ?, 'whatsapp', NOW(), NOW())
                ");
                $stmt->execute([$to, $contactName ?: $to]);
                $contactId = $db->lastInsertId();
            } else {
                $contactId = $contact['id'];
                
                // Atualizar nome se fornecido
                if (!empty($contactName)) {
                    $stmt = $db->prepare("UPDATE contacts SET name = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$contactName, $contactId]);
                }
            }
            
            // Buscar conversa existente (incluindo fechadas) ou criar nova
            $stmt = $db->prepare("
                SELECT id, status 
                FROM conversations 
                WHERE contact_id = ? AND channel = 'whatsapp'
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $stmt->execute([$contactId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                // Criar nova conversa
                $stmt = $db->prepare("
                    INSERT INTO conversations (
                        contact_id, 
                        channel, 
                        status, 
                        contact_name,
                        contact_phone,
                        whatsapp_account_id,
                        created_at, 
                        updated_at
                    ) VALUES (?, 'whatsapp', 'open', ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$contactId, $contactName ?: $to, $to, $account['id']]);
                $conversationId = $db->lastInsertId();
            } else {
                $conversationId = $conversation['id'];
                
                // Se a conversa estava fechada, reabrir
                if ($conversation['status'] === 'closed' || $conversation['status'] === 'resolved') {
                    $stmt = $db->prepare("UPDATE conversations SET status = 'open', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$conversationId]);
                }
            }
            
            // Inserir mensagem no banco
            $stmt = $db->prepare("
                INSERT INTO messages (
                    conversation_id,
                    sender_type,
                    content,
                    message_type,
                    status,
                    created_at
                ) VALUES (?, 'agent', ?, 'text', 'sent', NOW())
            ");
            $stmt->execute([$conversationId, $message]);
            $messageId = $db->lastInsertId();
            
            // Atualizar updated_at da conversa
            $stmt = $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$conversationId]);
            
            // Enviar mensagem via provedor (Quepasa, etc)
            $messageSent = false;
            $externalMessageId = null;
            
            if ($account['provider'] === 'quepasa' && !empty($account['api_url'])) {
                try {
                    // Enviar via Quepasa
                    $quepasaUrl = rtrim($account['api_url'], '/') . '/send';
                    
                    $headers = [
                        'Content-Type: application/json',
                        'X-QUEPASA-TOKEN: ' . ($account['quepasa_token'] ?? ''),
                        'X-QUEPASA-USER: ' . ($account['quepasa_user'] ?? 'system')
                    ];
                    
                    $payload = [
                        'chatid' => $to . '@s.whatsapp.net',
                        'text' => $message
                    ];
                    
                    $ch = curl_init($quepasaUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $messageSent = true;
                        $responseData = json_decode($response, true);
                        $externalMessageId = $responseData['id'] ?? null;
                    }
                } catch (\Exception $e) {
                    // Log erro mas não falha a requisição
                    error_log("Erro ao enviar mensagem via Quepasa: " . $e->getMessage());
                }
            }
            
            // Atualizar status da mensagem
            if ($messageSent) {
                $stmt = $db->prepare("UPDATE messages SET status = 'delivered' WHERE id = ?");
                $stmt->execute([$messageId]);
            }
            
            // Retornar sucesso
            ApiResponse::created([
                'message_id' => (string) $messageId,
                'conversation_id' => (string) $conversationId,
                'status' => $messageSent ? 'sent' : 'queued',
                'external_message_id' => $externalMessageId
            ], 'Mensagem enviada com sucesso');
            
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao enviar mensagem', $e);
        }
    }
}
