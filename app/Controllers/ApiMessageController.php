<?php
/**
 * ApiMessageController
 * 
 * Endpoint de API REST para envio de mensagens via WhatsApp.
 * 
 * Uso:
 * POST /api/v1/messages/send
 * Authorization: Bearer <token>
 * 
 * Body:
 * {
 *   "to": "5535991970289",           // Telefone do destinatário
 *   "from": "5535991970289",          // Telefone da integração WhatsApp
 *   "message": "Olá, tudo bem?",      // Mensagem a enviar
 *   "contact_name": "João Silva"      // Nome do contato (opcional, usado se for novo)
 * }
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Middleware\ApiAuth;
use App\Models\ApiLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppService;

class ApiMessageController
{
    /**
     * Enviar mensagem via API
     * 
     * POST /api/v1/messages/send
     */
    public function send(): void
    {
        $startTime = microtime(true);
        $tokenData = ApiAuth::getToken();
        $userId = ApiAuth::getUserId();
        
        try {
            // Obter dados do body
            $input = Request::input();
            
            // Validar campos obrigatórios
            $errors = $this->validateSendRequest($input);
            if (!empty($errors)) {
                $this->logRequest($tokenData, $userId, 'messages/send', 422, $input, $errors, $startTime);
                Response::json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Dados inválidos',
                        'details' => $errors
                    ]
                ], 422);
                return;
            }
            
            $to = $this->normalizePhone($input['to']);
            $from = $this->normalizePhone($input['from']);
            $message = trim($input['message']);
            $contactName = trim($input['contact_name'] ?? '');
            
            // 1. Buscar conta WhatsApp pelo número "from"
            $account = WhatsAppAccount::findByPhone($from);
            
            if (!$account) {
                $this->logRequest($tokenData, $userId, 'messages/send', 404, $input, [
                    'from' => "Número de integração não encontrado: {$from}"
                ], $startTime);
                
                Response::json([
                    'success' => false,
                    'error' => [
                        'code' => 'INTEGRATION_NOT_FOUND',
                        'message' => "Número de integração WhatsApp não encontrado: {$from}",
                        'hint' => 'Verifique se o número está cadastrado e conectado em Integrações > WhatsApp'
                    ]
                ], 404);
                return;
            }
            
            if ($account['status'] !== 'active' && $account['status'] !== 'connected') {
                $this->logRequest($tokenData, $userId, 'messages/send', 400, $input, [
                    'from' => "Integração não está ativa (status: {$account['status']})"
                ], $startTime);
                
                Response::json([
                    'success' => false,
                    'error' => [
                        'code' => 'INTEGRATION_NOT_ACTIVE',
                        'message' => "Integração WhatsApp não está ativa (status: {$account['status']})",
                        'hint' => 'Verifique se o WhatsApp está conectado escaneando o QR Code'
                    ]
                ], 400);
                return;
            }
            
            // 2. Buscar ou criar contato
            \App\Helpers\Logger::info("[ApiMessageController] Buscando contato por telefone: '{$to}'");
            $contact = Contact::findByPhoneNormalized($to);
            
            if (!$contact) {
                \App\Helpers\Logger::info("[ApiMessageController] Contato NÃO encontrado, criando novo...");
                
                // Normalizar telefone antes de salvar
                $normalizedPhone = Contact::normalizePhoneNumber($to);
                \App\Helpers\Logger::info("[ApiMessageController] Telefone normalizado: '{$to}' -> '{$normalizedPhone}'");
                
                // Criar novo contato
                $contactData = [
                    'name' => !empty($contactName) ? $contactName : $normalizedPhone,
                    'phone' => $normalizedPhone
                ];
                
                $contactId = Contact::create($contactData);
                $contact = Contact::find($contactId);
                
                \App\Helpers\Logger::info("[ApiMessageController] Contato criado: ID={$contactId}");
                $isNewContact = true;
            } else {
                \App\Helpers\Logger::info("[ApiMessageController] Contato ENCONTRADO: ID={$contact['id']}, Nome={$contact['name']}, Phone={$contact['phone']}");
                $isNewContact = false;
                
                // ✅ NÃO atualizar nome se contato já existe com nome definido
                // Apenas atualizar se o contato não tinha nome ou nome era o telefone
                if (!empty($contactName) && (empty($contact['name']) || $contact['name'] === $contact['phone'])) {
                    \App\Helpers\Logger::info("[ApiMessageController] Atualizando nome do contato: '{$contact['name']}' -> '{$contactName}'");
                    Contact::update($contact['id'], ['name' => $contactName]);
                    $contact['name'] = $contactName;
                }
            }
            
            // 3. Buscar ou criar conversa
            $conversation = Conversation::findByContactAndChannel(
                $contact['id'], 
                'whatsapp', 
                $account['id']
            );
            
            if (!$conversation) {
                // Criar nova conversa
                $conversationData = [
                    'contact_id' => $contact['id'],
                    'channel' => 'whatsapp',
                    'whatsapp_account_id' => $account['id'],
                    'status' => 'open',
                    'funnel_id' => $account['default_funnel_id'] ?? null,
                    'funnel_stage_id' => $account['default_stage_id'] ?? null
                ];
                
                $conversationId = Conversation::create($conversationData);
                $conversation = Conversation::find($conversationId);
                
                $isNewConversation = true;
            } else {
                $isNewConversation = false;
                
                // Se a conversa estava fechada, reabrir
                if ($conversation['status'] === 'closed') {
                    Conversation::reopen($conversation['id']);
                    $conversation['status'] = 'open';
                }
            }
            
            // 4. Enviar mensagem via WhatsApp
            $sendResult = WhatsAppService::sendMessage(
                $account['id'],
                $to,
                $message,
                []
            );
            
            // 5. Salvar mensagem no banco
            $messageData = [
                'conversation_id' => $conversation['id'],
                'sender_type' => 'agent',
                'sender_id' => $userId,
                'content' => $message,
                'message_type' => 'text',
                'external_id' => $sendResult['message_id'] ?? null,
                'status' => $sendResult['status'] ?? 'sent'
            ];
            
            $messageId = Message::create($messageData);
            
            // 6. Atualizar last_message_at da conversa
            Conversation::update($conversation['id'], [
                'last_message_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log de sucesso
            $responseData = [
                'success' => true,
                'data' => [
                    'message_id' => $messageId,
                    'external_id' => $sendResult['message_id'] ?? null,
                    'status' => $sendResult['status'] ?? 'sent',
                    'conversation_id' => $conversation['id'],
                    'contact_id' => $contact['id'],
                    'is_new_contact' => $isNewContact,
                    'is_new_conversation' => $isNewConversation,
                    'warning' => $sendResult['warning'] ?? null
                ]
            ];
            
            $this->logRequest($tokenData, $userId, 'messages/send', 200, $input, null, $startTime, $responseData);
            
            Response::json($responseData, 200);
            
        } catch (\Exception $e) {
            $this->logRequest($tokenData, $userId, 'messages/send', 500, $input ?? [], [
                'exception' => $e->getMessage()
            ], $startTime);
            
            Response::json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Listar integrações WhatsApp disponíveis
     * 
     * GET /api/v1/whatsapp/accounts
     */
    public function listAccounts(): void
    {
        $startTime = microtime(true);
        $tokenData = ApiAuth::getToken();
        $userId = ApiAuth::getUserId();
        
        try {
            $accounts = WhatsAppAccount::getActive();
            
            // Retornar apenas dados públicos (sem tokens)
            $safeAccounts = array_map(function($account) {
                return [
                    'id' => $account['id'],
                    'name' => $account['name'],
                    'phone_number' => $account['phone_number'],
                    'status' => $account['status'],
                    'provider' => $account['provider']
                ];
            }, $accounts);
            
            $responseData = [
                'success' => true,
                'data' => [
                    'accounts' => $safeAccounts,
                    'total' => count($safeAccounts)
                ]
            ];
            
            $this->logRequest($tokenData, $userId, 'whatsapp/accounts', 200, [], null, $startTime, $responseData);
            
            Response::json($responseData, 200);
            
        } catch (\Exception $e) {
            $this->logRequest($tokenData, $userId, 'whatsapp/accounts', 500, [], [
                'exception' => $e->getMessage()
            ], $startTime);
            
            Response::json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Erro ao listar contas: ' . $e->getMessage()
                ]
            ], 500);
        }
    }
    
    /**
     * Validar requisição de envio
     */
    private function validateSendRequest(array $input): array
    {
        $errors = [];
        
        if (empty($input['to'])) {
            $errors['to'] = 'Campo obrigatório: número do destinatário';
        } elseif (!preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/\D/', '', $input['to']))) {
            $errors['to'] = 'Número de telefone inválido (use formato: 5535991970289)';
        }
        
        if (empty($input['from'])) {
            $errors['from'] = 'Campo obrigatório: número da integração WhatsApp';
        } elseif (!preg_match('/^\+?[0-9]{10,15}$/', preg_replace('/\D/', '', $input['from']))) {
            $errors['from'] = 'Número de integração inválido (use formato: 5535991970289)';
        }
        
        if (empty($input['message'])) {
            $errors['message'] = 'Campo obrigatório: mensagem a enviar';
        } elseif (strlen($input['message']) > 4096) {
            $errors['message'] = 'Mensagem muito longa (máximo 4096 caracteres)';
        }
        
        return $errors;
    }
    
    /**
     * Normalizar número de telefone
     */
    private function normalizePhone(string $phone): string
    {
        // Remover tudo que não é número
        $phone = preg_replace('/\D/', '', $phone);
        
        // Remover + do início se existir
        $phone = ltrim($phone, '+');
        
        return $phone;
    }
    
    /**
     * Registrar log da requisição
     */
    private function logRequest(
        ?array $tokenData, 
        ?int $userId, 
        string $endpoint, 
        int $responseCode, 
        array $requestBody, 
        ?array $errors,
        float $startTime,
        ?array $responseData = null
    ): void {
        $executionTime = round((microtime(true) - $startTime) * 1000);
        
        try {
            ApiLog::logRequest([
                'token_id' => $tokenData['id'] ?? null,
                'user_id' => $userId,
                'endpoint' => '/api/v1/' . $endpoint,
                'method' => 'POST',
                'request_body' => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
                'request_headers' => json_encode($this->getSafeHeaders(), JSON_UNESCAPED_UNICODE),
                'response_code' => $responseCode,
                'response_body' => $responseData ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : null,
                'error_message' => $errors ? json_encode($errors, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'execution_time_ms' => $executionTime
            ]);
        } catch (\Exception $e) {
            // Ignorar erros de log para não afetar a resposta
            error_log("Erro ao registrar log de API: " . $e->getMessage());
        }
    }
    
    /**
     * Obter headers seguros (sem tokens sensíveis)
     */
    private function getSafeHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = str_replace('_', '-', $headerName);
                
                // Mascarar Authorization
                if ($headerName === 'AUTHORIZATION') {
                    $value = preg_replace('/Bearer\s+\S+/', 'Bearer [REDACTED]', $value);
                }
                
                // Mascarar X-API-Key
                if ($headerName === 'X-API-KEY') {
                    $value = '[REDACTED]';
                }
                
                $headers[$headerName] = $value;
            }
        }
        
        return $headers;
    }
}
