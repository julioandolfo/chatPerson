<?php

namespace App\Services;

use App\Models\WhatsAppPhone;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\WebSocket;
use App\Services\AvatarService;
use App\Services\AutomationService;
use App\Services\ConversationMergeService;

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
     * Enviar mensagem interativa (botões de resposta rápida)
     */
    public static function sendInteractiveMessage(
        string $phoneNumberId,
        string $to,
        string $bodyText,
        array $buttons,
        string $accessToken,
        ?string $headerText = null,
        ?string $footerText = null
    ): array {
        self::initConfig();
        
        $config = self::$config['whatsapp'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = self::$baseUrl . "/{$apiVersion}/{$phoneNumberId}/messages";
        
        $interactiveButtons = [];
        foreach ($buttons as $i => $btn) {
            $interactiveButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'] ?? 'btn_' . $i,
                    'title' => mb_substr($btn['text'] ?? $btn['title'] ?? '', 0, 20),
                ],
            ];
        }
        
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $interactiveButtons],
        ];
        
        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ];
        
        self::logInfo("Enviando mensagem interativa WhatsApp para: {$to}");
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $data);
            
            self::logInfo("Mensagem interativa enviada com sucesso", [
                'to' => $to,
                'message_id' => $response['messages'][0]['id'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar mensagem interativa: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Enviar template a partir do banco local (usando WhatsAppTemplate model)
     * 
     * @param string $phoneNumberId Phone Number ID da Meta
     * @param string $to Número de destino
     * @param int $localTemplateId ID do template no banco local
     * @param array $parameters Parâmetros para variáveis {{1}}, {{2}}, etc.
     * @param string $accessToken Token de acesso
     * @return array Resposta da API
     */
    public static function sendLocalTemplate(
        string $phoneNumberId,
        string $to,
        int $localTemplateId,
        array $parameters,
        string $accessToken
    ): array {
        $template = \App\Models\WhatsAppTemplate::find($localTemplateId);
        
        if (!$template) {
            throw new \Exception("Template #{$localTemplateId} não encontrado");
        }
        
        if ($template['status'] !== 'APPROVED') {
            throw new \Exception("Template '{$template['name']}' não está aprovado (status: {$template['status']})");
        }
        
        $response = self::sendTemplateMessage(
            $phoneNumberId,
            $to,
            $template['name'],
            $template['language'],
            $parameters,
            $accessToken
        );
        
        // Incrementar contagem de envio
        \App\Models\WhatsAppTemplate::incrementSent($localTemplateId);
        
        return $response;
    }
    
    /**
     * Verificar se a conversa está na janela de 24h
     * (Baseado na última mensagem recebida do contato)
     * 
     * @param int $conversationId ID da conversa
     * @param int|null $viaAccountId Se informado, verifica janela apenas para mensagens 
     *                               recebidas por este número específico (importante para conversas mescladas)
     */
    public static function isWithin24hWindow(int $conversationId, ?int $viaAccountId = null): bool
    {
        // Para conversas mescladas: verificar janela por número específico
        if ($viaAccountId) {
            $lastContactMessage = \App\Helpers\Database::fetch(
                "SELECT sent_at FROM messages 
                 WHERE conversation_id = ? AND sender_type = 'contact' AND via_account_id = ?
                 ORDER BY sent_at DESC LIMIT 1",
                [$conversationId, $viaAccountId]
            );
            
            // Se tem mensagem nesse número, verificar janela
            if ($lastContactMessage && !empty($lastContactMessage['sent_at'])) {
                $lastMessageTime = strtotime($lastContactMessage['sent_at']);
                $windowEnd = $lastMessageTime + (24 * 60 * 60);
                return time() < $windowEnd;
            }
            
            // Se não tem mensagem desse número, a janela NÃO está aberta para esse número
            return false;
        }
        
        // Fallback: verificar qualquer mensagem do contato
        $lastContactMessage = \App\Helpers\Database::fetch(
            "SELECT sent_at FROM messages 
             WHERE conversation_id = ? AND sender_type = 'contact' 
             ORDER BY sent_at DESC LIMIT 1",
            [$conversationId]
        );
        
        if (!$lastContactMessage || empty($lastContactMessage['sent_at'])) {
            return false;
        }
        
        $lastMessageTime = strtotime($lastContactMessage['sent_at']);
        $windowEnd = $lastMessageTime + (24 * 60 * 60); // +24 horas
        
        return time() < $windowEnd;
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
        
        // ==================== BUSCAR OU CRIAR CONVERSA ====================
        $integrationAccountId = $whatsappPhone['integration_account_id'] ?? null;
        $conversation = null;
        
        // 1. PRIMEIRO: Verificar se existe conversa MESCLADA para este contato
        //    (mesmo contato falou por outro número, conversas foram mescladas)
        if ($integrationAccountId) {
            $conversation = ConversationMergeService::findMergedConversation(
                $contact['id'],
                $integrationAccountId
            );
            
            if ($conversation) {
                self::logInfo("Conversa MESCLADA encontrada para Cloud API: #{$conversation['id']}", [
                    'contact_id' => $contact['id'],
                    'integration_account_id' => $integrationAccountId,
                ]);
                // Atualizar último número usado pelo cliente (para responder pelo número certo)
                ConversationMergeService::updateLastCustomerAccount($conversation['id'], $integrationAccountId);
            }
        }
        
        // 2. Buscar conversa aberta DESTE número específico (integration_account_id)
        if (!$conversation && $integrationAccountId) {
            $conversation = \App\Helpers\Database::fetch(
                "SELECT * FROM conversations 
                 WHERE contact_id = ? AND channel = 'whatsapp' AND status != 'closed'
                 AND integration_account_id = ?
                 ORDER BY updated_at DESC LIMIT 1",
                [$contact['id'], $integrationAccountId]
            );
        }
        
        // 3. Fallback: buscar qualquer conversa aberta do contato no WhatsApp
        if (!$conversation) {
            $conversation = Conversation::whereFirst('contact_id', '=', $contact['id'], [
                ['channel', '=', 'whatsapp'],
                ['status', '!=', 'closed']
            ]);
            
            // Se encontrou conversa de OUTRO número, sincronizar o integration_account_id
            if ($conversation && $integrationAccountId && 
                !empty($conversation['integration_account_id']) && 
                $conversation['integration_account_id'] != $integrationAccountId) {
                self::logInfo("Cliente mudou de número na Cloud API", [
                    'conversation_id' => $conversation['id'],
                    'old_account' => $conversation['integration_account_id'],
                    'new_account' => $integrationAccountId,
                ]);
                // Atualizar last_customer_account_id para responder pelo número certo
                ConversationMergeService::updateLastCustomerAccount($conversation['id'], $integrationAccountId);
            }
        }

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
                'integration_account_id' => $integrationAccountId,
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
            'via_account_id' => $integrationAccountId, // Rastrear por qual número a mensagem chegou
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

