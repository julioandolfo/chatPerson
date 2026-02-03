<?php

namespace App\Services;

use App\Models\WhatsAppPhone;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\WebSocket;
use App\Services\AvatarService;
use App\Services\AutomationService;

/**
 * WhatsAppCloudService
 * 
 * Service para integração com WhatsApp Cloud API
 * Gerencia mensagens, templates, mídia, etc.
 */
class WhatsAppCloudService extends MetaIntegrationService
{
    private static string $apiVersion = 'v21.0';
    private static string $baseUrl = 'https://graph.facebook.com';
    
    /**
     * Enviar mensagem de texto
     */
    public static function sendTextMessage(
        string $phoneNumberId,
        string $to,
        string $text,
        string $accessToken
    ): array {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text
            ]
        ];
        
        self::logInfo("Enviando mensagem WhatsApp para: {$to}");
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $data);
            
            self::logInfo("Mensagem WhatsApp enviada com sucesso", [
                'to' => $to,
                'message_id' => $response['messages'][0]['id'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar mensagem WhatsApp: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Enviar template message (para iniciar conversa)
     */
    public static function sendTemplateMessage(
        string $phoneNumberId,
        string $to,
        string $templateName,
        string $languageCode,
        array $parameters,
        string $accessToken
    ): array {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/messages";
        
        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ],
                'components' => $components
            ]
        ];
        
        self::logInfo("Enviando template WhatsApp '{$templateName}' para: {$to}");
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $data);
            
            self::logInfo("Template WhatsApp enviado com sucesso", [
                'to' => $to,
                'template' => $templateName,
                'message_id' => $response['messages'][0]['id'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar template WhatsApp: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Enviar mídia (imagem, vídeo, áudio, documento)
     */
    public static function sendMedia(
        string $phoneNumberId,
        string $to,
        string $mediaType,
        string $mediaUrl,
        ?string $caption,
        string $accessToken
    ): array {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/messages";
        
        $mediaData = [
            'link' => $mediaUrl
        ];
        
        if ($caption && in_array($mediaType, ['image', 'video', 'document'])) {
            $mediaData['caption'] = $caption;
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $mediaType,
            $mediaType => $mediaData
        ];
        
        self::logInfo("Enviando mídia WhatsApp ({$mediaType}) para: {$to}");
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $data);
            
            self::logInfo("Mídia WhatsApp enviada com sucesso", [
                'to' => $to,
                'type' => $mediaType,
                'message_id' => $response['messages'][0]['id'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar mídia WhatsApp: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Marcar mensagem como lida
     */
    public static function markAsRead(string $phoneNumberId, string $messageId, string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];
        
        return self::makeRequest($url, $accessToken, 'POST', $data);
    }
    
    /**
     * Listar templates aprovados
     */
    public static function listTemplates(string $wabaId, string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$wabaId}/message_templates";
        
        return self::makeRequest($url, $accessToken, 'GET', [
            'fields' => 'name,status,language,category,components'
        ]);
    }
    
    /**
     * Obter informações do perfil do número WhatsApp
     */
    public static function getBusinessProfile(string $phoneNumberId, string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/whatsapp_business_profile";
        
        return self::makeRequest($url, $accessToken, 'GET');
    }
    
    /**
     * Sincronizar dados do número WhatsApp no banco
     */
    public static function syncPhone(string $phoneNumberId, string $accessToken): ?array
    {
        try {
            self::initConfig();
            
            $config = self::$config['whatsapp'] ?? [];
            $apiVersion = $config['api_version'] ?? self::$apiVersion;
            
            // Obter informações do número
            $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}";
            $phoneData = self::makeRequest($url, $accessToken, 'GET', [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,account_mode'
            ]);
            
            $phone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
            
            $data = [
                'phone_number_id' => $phoneData['id'],
                'display_phone_number' => $phoneData['display_phone_number'] ?? null,
                'verified_name' => $phoneData['verified_name'] ?? null,
                'quality_rating' => $phoneData['quality_rating'] ?? 'UNKNOWN',
                'account_mode' => $phoneData['account_mode'] ?? 'SANDBOX',
            ];
            
            if ($phone) {
                WhatsAppPhone::update($phone['id'], $data);
                $phoneId = $phone['id'];
            } else {
                // Precisa do waba_id e phone_number para criar
                throw new \Exception("Número WhatsApp não encontrado. Use o método de configuração inicial.");
            }
            
            self::logInfo("Número WhatsApp sincronizado: {$phoneData['display_phone_number']} ({$phoneNumberId})");
            
            return WhatsAppPhone::find($phoneId);
            
        } catch (\Exception $e) {
            self::logError("Erro ao sincronizar número WhatsApp: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Processar webhook do WhatsApp (mensagens recebidas)
     */
    public static function processWebhook(array $payload): void
    {
        self::logInfo("WhatsApp webhook recebido", ['payload' => $payload]);
        
        try {
            // Estrutura do webhook WhatsApp:
            // {
            //   "object": "whatsapp_business_account",
            //   "entry": [
            //     {
            //       "id": "WABA_ID",
            //       "changes": [
            //         {
            //           "value": {
            //             "messaging_product": "whatsapp",
            //             "metadata": {
            //               "display_phone_number": "15551234567",
            //               "phone_number_id": "PHONE_NUMBER_ID"
            //             },
            //             "contacts": [
            //               {
            //                 "profile": { "name": "CONTACT_NAME" },
            //                 "wa_id": "CONTACT_WA_ID"
            //               }
            //             ],
            //             "messages": [
            //               {
            //                 "from": "CONTACT_WA_ID",
            //                 "id": "MESSAGE_ID",
            //                 "timestamp": "1234567890",
            //                 "text": { "body": "MESSAGE_TEXT" },
            //                 "type": "text"
            //               }
            //             ]
            //           },
            //           "field": "messages"
            //         }
            //       ]
            //     }
            //   ]
            // }
            
            if (!isset($payload['entry'])) {
                self::logWarning("Webhook WhatsApp inválido: sem 'entry'");
                return;
            }
            
            foreach ($payload['entry'] as $entry) {
                $changes = $entry['changes'] ?? [];
                
                foreach ($changes as $change) {
                    if (($change['field'] ?? '') !== 'messages') {
                        continue;
                    }
                    
                    $value = $change['value'] ?? [];
                    self::processMessagesChange($value);
                }
            }
            
        } catch (\Exception $e) {
            self::logError("Erro ao processar webhook WhatsApp: {$e->getMessage()}");
        }
    }
    
    /**
     * Processar change de mensagens
     */
    protected static function processMessagesChange(array $value): void
    {
        $metadata = $value['metadata'] ?? [];
        $phoneNumberId = $metadata['phone_number_id'] ?? null;
        
        if (!$phoneNumberId) {
            self::logWarning("Webhook WhatsApp sem phone_number_id");
            return;
        }
        
        // Buscar número WhatsApp
        $whatsappPhone = WhatsAppPhone::findByPhoneNumberId($phoneNumberId);
        if (!$whatsappPhone) {
            self::logWarning("Número WhatsApp não encontrado: {$phoneNumberId}");
            return;
        }
        
        // Processar contatos
        $contacts = $value['contacts'] ?? [];
        $contactsMap = [];
        foreach ($contacts as $contact) {
            $waId = $contact['wa_id'] ?? null;
            if ($waId) {
                $contactsMap[$waId] = $contact;
            }
        }
        
        // Processar mensagens
        $messages = $value['messages'] ?? [];
        foreach ($messages as $messageData) {
            self::processMessage($messageData, $contactsMap, $whatsappPhone);
        }
        
        // Processar status de mensagens
        $statuses = $value['statuses'] ?? [];
        foreach ($statuses as $status) {
            self::processMessageStatus($status, $whatsappPhone);
        }
    }
    
    /**
     * Processar mensagem recebida
     */
    protected static function processMessage(array $messageData, array $contactsMap, array $whatsappPhone): void
    {
        $from = $messageData['from'] ?? null;
        $messageId = $messageData['id'] ?? null;
        $timestamp = $messageData['timestamp'] ?? time();
        $type = $messageData['type'] ?? 'text';
        
        if (!$from || !$messageId) {
            self::logWarning("Mensagem WhatsApp incompleta", ['message' => $messageData]);
            return;
        }
        
        // Extrair conteúdo baseado no tipo
        $content = '';
        $mediaUrl = null;
        
        switch ($type) {
            case 'text':
                $content = $messageData['text']['body'] ?? '';
                break;
            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $content = $messageData[$type]['caption'] ?? "[{$type}]";
                $mediaUrl = $messageData[$type]['id'] ?? null; // Media ID
                break;
            case 'location':
                $location = $messageData['location'] ?? [];
                $content = "[Localização: {$location['latitude']}, {$location['longitude']}]";
                break;
            default:
                $content = "[Tipo não suportado: {$type}]";
        }
        
        self::logInfo("Mensagem WhatsApp recebida", [
            'from' => $from,
            'message_id' => $messageId,
            'type' => $type,
        ]);
        
        // Buscar ou criar contato
        $contactInfo = $contactsMap[$from] ?? [];
        $contactName = $contactInfo['profile']['name'] ?? "WhatsApp {$from}";
        
        // ✅ CORRIGIDO: Buscar por identifier OU por telefone normalizado
        $contact = Contact::findByIdentifier($from);
        if (!$contact) {
            // Tentar buscar por telefone normalizado (evita duplicatas)
            $contact = Contact::findByPhoneNormalized($from);
        }
        
        if (!$contact) {
            // Normalizar telefone antes de salvar
            $normalizedPhone = Contact::normalizePhoneNumber($from);
            
            $contactData = [
                'name' => $contactName,
                'phone' => $normalizedPhone,
                'identifier' => $from,
                'whatsapp_wa_id' => $from,
                'channel' => 'whatsapp',
            ];
            
            // Gerar avatar com iniciais
            $avatar = AvatarService::generateInitialsAvatar($contactName, $from);
            if ($avatar) {
                $contactData['avatar'] = $avatar;
            }
            
            $contactId = Contact::create($contactData);
            $contact = Contact::find($contactId);
            
            self::logInfo("Contato WhatsApp criado: {$contactId}");
        }
        
        // ⚠️ VALIDAÇÃO: Não criar conversa se contato tiver phone = 'system'
        if (isset($contact['phone']) && ($contact['phone'] === 'system' || $contact['phone'] === '0')) {
            self::logInfo("⚠️ Abortando: Contato com phone do sistema", [
                'phone' => $contact['phone'],
                'contact_id' => $contact['id']
            ]);
            return;
        }
        
        // Buscar ou criar conversa
        $conversation = Conversation::whereFirst('contact_id', '=', $contact['id'], [
            ['channel', '=', 'whatsapp'],
            ['status', '!=', 'closed']
        ]);

        // Trava: não criar conversa se a primeira mensagem for exatamente o nome do contato (caso sem mídia)
        if (
            !$conversation &&
            $type === 'text' &&
            empty($mediaUrl) &&
            ConversationService::isFirstMessageContactName($content, $contact['name'] ?? null)
        ) {
            self::logInfo("Ignorando criação de conversa: primeira mensagem igual ao nome do contato", [
                'contact_id' => $contact['id'],
                'contact_name' => $contact['name'] ?? null,
            ]);
            return;
        }
        
        if (!$conversation) {
            $conversationData = [
                'contact_id' => $contact['id'],
                'channel' => 'whatsapp',
                'status' => 'open',
                'integration_account_id' => $whatsappPhone['integration_account_id'] ?? null,
                'started_at' => date('Y-m-d H:i:s', (int)$timestamp),
            ];
            
            $conversationId = Conversation::create($conversationData);
            $conversation = Conversation::find($conversationId);
            
            self::logInfo("Conversa WhatsApp criada: {$conversationId}");
            
            // Notificar nova conversa
            WebSocket::notifyNewConversation($conversation);
            
            // Executar automações
            AutomationService::executeForNewConversation($conversationId);
        }
        
        // Criar mensagem
        $messageCreateData = [
            'conversation_id' => $conversation['id'],
            'content' => $content,
            'sender_type' => 'contact',
            'sender_id' => $contact['id'],
            'message_type' => $type,
            'external_id' => $messageId,
            'media_url' => $mediaUrl,
            'sent_at' => date('Y-m-d H:i:s', (int)$timestamp),
            'status' => 'received',
        ];
        
        $newMessageId = Message::create($messageCreateData);
        $newMessage = Message::find($newMessageId);
        
        self::logInfo("Mensagem WhatsApp salva no DB: {$newMessageId}");
        
        // Registrar atividade no número
        WhatsAppPhone::recordMessage($whatsappPhone['id']);
        
        // Notificar via WebSocket
        WebSocket::notifyNewMessage($conversation['id'], $newMessage);
        
        // Executar automações
        AutomationService::executeForNewMessage($newMessageId);
    }
    
    /**
     * Processar status de mensagem enviada
     */
    protected static function processMessageStatus(array $status, array $whatsappPhone): void
    {
        $messageId = $status['id'] ?? null;
        $statusValue = $status['status'] ?? null;
        
        if (!$messageId || !$statusValue) {
            return;
        }
        
        self::logInfo("Status de mensagem WhatsApp atualizado", [
            'message_id' => $messageId,
            'status' => $statusValue,
        ]);
        
        // Buscar mensagem no DB pelo external_id
        $message = Message::whereFirst('external_id', '=', $messageId);
        if ($message) {
            // Mapear status WhatsApp para nosso sistema
            $statusMap = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'failed' => 'failed',
            ];
            
            $newStatus = $statusMap[$statusValue] ?? $statusValue;
            
            Message::update($message['id'], [
                'status' => $newStatus
            ]);
            
            // Notificar atualização via WebSocket
            WebSocket::notifyMessageStatusUpdate($message['conversation_id'], $message['id'], $newStatus);
        }
    }
}

