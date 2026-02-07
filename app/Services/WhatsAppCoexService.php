<?php

namespace App\Services;

use App\Models\WhatsAppPhone;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\WebSocket;
use App\Helpers\Logger;
use App\Services\ConversationMergeService;

/**
 * WhatsAppCoexService
 * 
 * Gerencia a funcionalidade de Coexistence (CoEx) do WhatsApp Cloud API.
 * CoEx permite usar simultaneamente o app WhatsApp Business e a API Cloud
 * no mesmo número de telefone.
 * 
 * Responsabilidades:
 * - Embedded Signup v4 com flag CoEx
 * - Processamento de webhooks específicos do CoEx
 * - Sincronização de estado entre app e API
 * - Importação de histórico
 */
class WhatsAppCoexService extends MetaIntegrationService
{
    private static string $apiVersion = 'v21.0';
    private static string $baseUrl = 'https://graph.facebook.com';
    
    // ==================== EMBEDDED SIGNUP ====================
    
    /**
     * Processar callback do Embedded Signup (troca de code por token)
     * 
     * @param string $code Código retornado pelo Embedded Signup
     * @return array Dados da conta (waba_id, phone_number_id, access_token)
     */
    public static function processEmbeddedSignupCallback(string $code): array
    {
        self::initConfig();
        
        $config = self::$config;
        $apiVersion = $config['whatsapp']['api_version'] ?? self::$apiVersion;
        
        // 1. Trocar code por access token
        $tokenUrl = self::$baseUrl . "/{$apiVersion}/oauth/access_token";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenUrl . '?' . http_build_query([
                'client_id' => $config['app_id'],
                'client_secret' => $config['app_secret'],
                'code' => $code,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $tokenData = json_decode($response, true);
        
        if ($httpCode !== 200 || empty($tokenData['access_token'])) {
            $error = $tokenData['error']['message'] ?? 'Erro ao trocar código por token';
            throw new \Exception("Falha no OAuth: {$error}");
        }
        
        self::logInfo("Embedded Signup: Token obtido com sucesso");
        
        return [
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'expires_in' => $tokenData['expires_in'] ?? null,
        ];
    }
    
    /**
     * Obter WABA ID e Phone Number ID de um token de Embedded Signup
     * via debug_token ou busca direta
     */
    public static function getSignupAccountInfo(string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config;
        $apiVersion = $config['whatsapp']['api_version'] ?? self::$apiVersion;
        
        // Buscar WABAs do Business
        $url = self::$baseUrl . "/{$apiVersion}/me/whatsapp_business_accounts";
        
        try {
            $response = self::makeRequest($url, $accessToken, 'GET', [
                'fields' => 'id,name,account_review_status,message_template_namespace'
            ]);
            
            $wabas = $response['data'] ?? [];
            
            if (empty($wabas)) {
                throw new \Exception("Nenhuma conta WhatsApp Business encontrada");
            }
            
            $result = [];
            
            foreach ($wabas as $waba) {
                // Buscar números de cada WABA
                $phonesUrl = self::$baseUrl . "/{$apiVersion}/{$waba['id']}/phone_numbers";
                $phonesResponse = self::makeRequest($phonesUrl, $accessToken, 'GET', [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,account_mode,code_verification_status'
                ]);
                
                $phones = $phonesResponse['data'] ?? [];
                
                $result[] = [
                    'waba_id' => $waba['id'],
                    'waba_name' => $waba['name'] ?? '',
                    'review_status' => $waba['account_review_status'] ?? 'PENDING',
                    'phones' => $phones,
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            self::logError("Erro ao obter info do signup: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Registrar número do Embedded Signup CoEx no sistema
     */
    public static function registerCoexPhone(array $data): int
    {
        $requiredFields = ['phone_number_id', 'phone_number', 'waba_id'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório: {$field}");
            }
        }
        
        // Verificar se já existe
        $existing = WhatsAppPhone::findByPhoneNumberId($data['phone_number_id']);
        if ($existing) {
            // Atualizar para CoEx
            WhatsAppPhone::update($existing['id'], [
                'coex_enabled' => true,
                'coex_status' => 'onboarding',
                'is_active' => true,
                'is_connected' => true,
            ]);
            
            self::logInfo("Número existente atualizado para CoEx: {$data['phone_number']}");
            return $existing['id'];
        }
        
        // Criar novo
        $phoneData = [
            'phone_number_id' => $data['phone_number_id'],
            'phone_number' => $data['phone_number'],
            'display_phone_number' => $data['display_phone_number'] ?? $data['phone_number'],
            'waba_id' => $data['waba_id'],
            'verified_name' => $data['verified_name'] ?? null,
            'quality_rating' => $data['quality_rating'] ?? 'UNKNOWN',
            'account_mode' => $data['account_mode'] ?? 'SANDBOX',
            'is_active' => true,
            'is_connected' => true,
            'coex_enabled' => true,
            'coex_status' => 'onboarding',
            'meta_oauth_token_id' => $data['meta_oauth_token_id'] ?? null,
        ];
        
        $phoneId = WhatsAppPhone::create($phoneData);
        
        self::logInfo("Número CoEx registrado: #{$phoneId} - {$data['phone_number']}");
        
        return $phoneId;
    }
    
    /**
     * Subscrever webhook para campos CoEx
     */
    public static function subscribeCoexWebhookFields(string $wabaId, string $accessToken): bool
    {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$wabaId}/subscribed_apps";
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', [
                'override_callback_uri' => null, // Usar webhook principal
                'verify_token' => self::$config['webhooks']['verify_token'] ?? '',
            ]);
            
            self::logInfo("Webhook CoEx subscrito para WABA: {$wabaId}");
            return true;
            
        } catch (\Exception $e) {
            self::logError("Erro ao subscrever webhook CoEx: {$e->getMessage()}");
            return false;
        }
    }
    
    // ==================== WEBHOOKS CoEx ====================
    
    /**
     * Processar webhook business_capability_update
     * Chamado quando o CoEx é ativado e as capacidades mudam
     */
    public static function processBusinessCapabilityUpdate(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        foreach ($entries as $entry) {
            $wabaId = $entry['id'] ?? null;
            $changes = $entry['changes'] ?? [];
            
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'business_capability_update') {
                    continue;
                }
                
                $value = $change['value'] ?? [];
                $maxDailyConversation = $value['max_daily_conversation_per_phone'] ?? null;
                $maxPhoneNumbers = $value['max_phone_numbers_per_business'] ?? null;
                
                self::logInfo("Business capability update para WABA: {$wabaId}", $value);
                
                // Atualizar todos os números desta WABA
                $phones = WhatsAppPhone::findByWabaId($wabaId);
                foreach ($phones as $phone) {
                    WhatsAppPhone::update($phone['id'], [
                        'coex_status' => 'active',
                        'coex_enabled' => true,
                        'coex_capabilities' => json_encode($value),
                        'coex_activated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }
    
    /**
     * Processar webhook account_update
     * Chamado quando o Embedded Signup é completado
     */
    public static function processAccountUpdate(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        foreach ($entries as $entry) {
            $wabaId = $entry['id'] ?? null;
            $changes = $entry['changes'] ?? [];
            
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'account_update') {
                    continue;
                }
                
                $value = $change['value'] ?? [];
                $event = $value['event'] ?? '';
                
                self::logInfo("Account update para WABA: {$wabaId}", [
                    'event' => $event,
                    'value' => $value,
                ]);
                
                // Se foi associação de phone number, atualizar status
                if ($event === 'PHONE_NUMBER_REGISTERED') {
                    $phoneNumberId = $value['phone_number_id'] ?? null;
                    if ($phoneNumberId) {
                        $phone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
                        if ($phone) {
                            WhatsAppPhone::update($phone['id'], [
                                'coex_status' => 'syncing',
                                'is_connected' => true,
                            ]);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Processar webhook smb_message_echoes
     * Mensagens enviadas pelo app WhatsApp Business (eco)
     * Permite ver no painel mensagens que o usuário enviou pelo celular
     */
    public static function processSmbMessageEchoes(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'smb_message_echoes') {
                    continue;
                }
                
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? null;
                
                if (!$phoneNumberId) {
                    continue;
                }
                
                $whatsappPhone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
                if (!$whatsappPhone) {
                    self::logWarning("smb_message_echoes: Número não encontrado: {$phoneNumberId}");
                    continue;
                }
                
                // Processar mensagens ecoadas (enviadas pelo app)
                $messages = $value['messages'] ?? [];
                foreach ($messages as $messageData) {
                    self::processEchoMessage($messageData, $whatsappPhone);
                }
                
                // Processar status de mensagens
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    self::processEchoStatus($status, $whatsappPhone);
                }
            }
        }
    }
    
    /**
     * Processar mensagem ecoada do app WhatsApp Business
     */
    private static function processEchoMessage(array $messageData, array $whatsappPhone): void
    {
        $to = $messageData['to'] ?? null;
        $messageId = $messageData['id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? time();
        $type = $messageData['type'] ?? 'text';
        
        if (!$to || !$messageId) {
            return;
        }
        
        // Verificar se já existe
        $existing = Message::whereFirst('external_id', '=', $messageId);
        if ($existing) {
            return; // Já processada
        }
        
        // Extrair conteúdo
        $content = '';
        switch ($type) {
            case 'text':
                $content = $messageData['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $content = $messageData[$type]['caption'] ?? "[{$type} enviado pelo app]";
                break;
            default:
                $content = "[{$type} enviado pelo app]";
        }
        
        // Buscar contato pelo número de destino
        $contact = Contact::findByIdentifier($to);
        if (!$contact) {
            $contact = Contact::findByPhoneNormalized($to);
        }
        
        if (!$contact) {
            self::logInfo("smb_echo: Contato não encontrado para {$to}, ignorando");
            return;
        }
        
        // Buscar conversa - priorizar mesclada, depois por integration_account_id
        $integrationAccountId = $whatsappPhone['integration_account_id'] ?? null;
        $conversation = null;
        
        // 1. Buscar conversa mesclada
        if ($integrationAccountId) {
            $conversation = ConversationMergeService::findMergedConversation($contact['id'], $integrationAccountId);
        }
        
        // 2. Buscar por integration_account_id específico
        if (!$conversation && $integrationAccountId) {
            $conversation = \App\Helpers\Database::fetch(
                "SELECT * FROM conversations 
                 WHERE contact_id = ? AND channel = 'whatsapp' AND status != 'closed'
                 AND integration_account_id = ?
                 ORDER BY updated_at DESC LIMIT 1",
                [$contact['id'], $integrationAccountId]
            );
        }
        
        // 3. Fallback genérico
        if (!$conversation) {
            $conversation = Conversation::whereFirst('contact_id', '=', $contact['id'], [
                ['channel', '=', 'whatsapp'],
                ['status', '!=', 'closed']
            ]);
        }
        
        if (!$conversation) {
            self::logInfo("smb_echo: Conversa não encontrada para contato #{$contact['id']}, ignorando");
            return;
        }
        
        // Criar mensagem (marcada como vinda do app)
        $newMessageId = Message::create([
            'conversation_id' => $conversation['id'],
            'content' => $content,
            'sender_type' => 'agent',
            'sender_id' => null,
            'message_type' => $type,
            'external_id' => $messageId,
            'sent_at' => date('Y-m-d H:i:s', (int)$timestamp),
            'status' => 'sent',
            'metadata' => json_encode(['source' => 'whatsapp_app', 'coex_echo' => true]),
        ]);
        
        $newMessage = Message::find($newMessageId);
        
        self::logInfo("smb_echo: Mensagem ecoada salva: #{$newMessageId}", [
            'to' => $to,
            'type' => $type,
        ]);
        
        // Notificar via WebSocket
        WebSocket::notifyNewMessage($conversation['id'], $newMessage);
    }
    
    /**
     * Processar status de mensagem ecoada
     */
    private static function processEchoStatus(array $status, array $whatsappPhone): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        
        if (!$messageId || !$statusValue) {
            return;
        }
        
        $message = Message::whereFirst('external_id', '=', $messageId);
        if ($message) {
            $statusMap = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed' => 'failed',
            ];
            
            $newStatus = $statusMap[$statusValue] ?? $statusValue;
            Message::update($message['id'], ['status' => $newStatus]);
            
            WebSocket::notifyMessageStatusUpdate($message['conversation_id'], $message['id'], $newStatus);
        }
    }
    
    /**
     * Processar webhook smb_app_state_sync
     * Sincronização de estado entre app e API (ex: contatos marcados como lidos no app)
     */
    public static function processSmbAppStateSync(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'smb_app_state_sync') {
                    continue;
                }
                
                $value = $change['value'] ?? [];
                $metadata = $value['metadata'] ?? [];
                $phoneNumberId = $metadata['phone_number_id'] ?? null;
                
                self::logInfo("smb_app_state_sync recebido", [
                    'phone_number_id' => $phoneNumberId,
                    'value' => $value,
                ]);
                
                // Processar read receipts do app
                $statuses = $value['statuses'] ?? [];
                foreach ($statuses as $status) {
                    $conversationId = $status['conversation_id'] ?? null;
                    $statusValue = $status['status'] ?? null;
                    
                    if ($statusValue === 'read' && !empty($status['id'])) {
                        $message = Message::whereFirst('external_id', '=', $status['id']);
                        if ($message) {
                            Message::update($message['id'], ['status' => 'read']);
                            WebSocket::notifyMessageStatusUpdate(
                                $message['conversation_id'],
                                $message['id'],
                                'read'
                            );
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Processar webhook history
     * Importação de histórico de conversas (até 6 meses)
     */
    public static function processHistoryWebhook(array $payload): void
    {
        $entries = $payload['entry'] ?? [];
        
        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'history') {
                    continue;
                }
                
                $value = $change['value'] ?? [];
                $event = $value['event'] ?? '';
                
                self::logInfo("History webhook recebido", [
                    'event' => $event,
                    'value' => $value,
                ]);
                
                switch ($event) {
                    case 'HISTORY_SYNC_STARTED':
                        // Marcar que a sincronização começou
                        self::updateHistorySyncStatus($value, 'syncing');
                        break;
                        
                    case 'HISTORY_SYNC_COMPLETE':
                        // Marcar que a sincronização completou
                        self::updateHistorySyncStatus($value, 'completed');
                        break;
                        
                    case 'HISTORY_SYNC_FAILED':
                        self::updateHistorySyncStatus($value, 'failed');
                        self::logError("History sync failed", $value);
                        break;
                }
            }
        }
    }
    
    /**
     * Atualizar status de sincronização do histórico
     */
    private static function updateHistorySyncStatus(array $value, string $status): void
    {
        $phoneNumberId = $value['phone_number_id'] ?? null;
        
        if ($phoneNumberId) {
            $phone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
            if ($phone) {
                WhatsAppPhone::update($phone['id'], [
                    'coex_history_synced' => ($status === 'completed') ? 1 : 0,
                ]);
            }
        }
    }
    
    // ==================== STATUS / INFO ====================
    
    /**
     * Obter status do CoEx para um número
     */
    public static function getCoexStatus(int $phoneId): array
    {
        $phone = WhatsAppPhone::find($phoneId);
        if (!$phone) {
            throw new \Exception("Número #{$phoneId} não encontrado");
        }
        
        $capabilities = [];
        if (!empty($phone['coex_capabilities'])) {
            $capabilities = is_string($phone['coex_capabilities'])
                ? json_decode($phone['coex_capabilities'], true) ?? []
                : $phone['coex_capabilities'];
        }
        
        return [
            'enabled' => (bool)($phone['coex_enabled'] ?? false),
            'status' => $phone['coex_status'] ?? 'inactive',
            'capabilities' => $capabilities,
            'activated_at' => $phone['coex_activated_at'] ?? null,
            'history_synced' => (bool)($phone['coex_history_synced'] ?? false),
            'phone' => $phone,
        ];
    }
    
    /**
     * Desativar CoEx para um número
     */
    public static function disableCoex(int $phoneId): bool
    {
        return WhatsAppPhone::update($phoneId, [
            'coex_enabled' => false,
            'coex_status' => 'inactive',
            'coex_capabilities' => null,
        ]);
    }
}
