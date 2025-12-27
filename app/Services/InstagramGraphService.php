<?php

namespace App\Services;

use App\Models\InstagramAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\WebSocket;
use App\Services\AvatarService;
use App\Services\AutomationService;

/**
 * InstagramGraphService
 * 
 * Service para integração com Instagram Graph API
 * Gerencia Direct Messages, perfis, etc.
 */
class InstagramGraphService extends MetaIntegrationService
{
    private static string $apiVersion = 'v21.0';
    private static string $baseUrl = 'https://graph.instagram.com';
    
    /**
     * Obter dados do perfil Instagram
     */
    public static function getProfile(string $instagramUserId, string $accessToken): array
    {
        $url = self::$baseUrl . "/{$instagramUserId}";
        
        $fields = [
            'id',
            'username',
            'name',
            'profile_picture_url',
            'biography',
            'website',
            'followers_count',
            'follows_count',
            'media_count',
        ];
        
        return self::makeRequest($url, $accessToken, 'GET', [
            'fields' => implode(',', $fields)
        ]);
    }
    
    /**
     * Sincronizar dados do perfil Instagram no banco
     */
    public static function syncProfile(string $instagramUserId, string $accessToken): ?array
    {
        try {
            $profileData = self::getProfile($instagramUserId, $accessToken);
            
            $account = InstagramAccount::findByInstagramUserId($instagramUserId);
            
            $data = [
                'instagram_user_id' => $profileData['id'],
                'username' => $profileData['username'] ?? null,
                'name' => $profileData['name'] ?? null,
                'profile_picture_url' => $profileData['profile_picture_url'] ?? null,
                'biography' => $profileData['biography'] ?? null,
                'website' => $profileData['website'] ?? null,
                'followers_count' => $profileData['followers_count'] ?? 0,
                'follows_count' => $profileData['follows_count'] ?? 0,
                'media_count' => $profileData['media_count'] ?? 0,
                'is_active' => true,
                'is_connected' => true,
                'last_synced_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($account) {
                InstagramAccount::update($account['id'], $data);
                $accountId = $account['id'];
            } else {
                $accountId = InstagramAccount::create($data);
            }
            
            self::logInfo("Perfil Instagram sincronizado: @{$profileData['username']} ({$instagramUserId})");
            
            return InstagramAccount::find($accountId);
            
        } catch (\Exception $e) {
            self::logError("Erro ao sincronizar perfil Instagram: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Enviar mensagem via Instagram Direct
     */
    public static function sendMessage(string $recipientId, string $message, string $accessToken): array
    {
        self::initConfig();
        
        // Obter configuração do Instagram
        $config = self::$config['instagram'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        // URL da Graph API para enviar mensagens
        $url = "https://graph.facebook.com/{$apiVersion}/me/messages";
        
        $data = [
            'recipient' => [
                'id' => $recipientId
            ],
            'message' => [
                'text' => $message
            ]
        ];
        
        self::logInfo("Enviando mensagem Instagram Direct para: {$recipientId}");
        
        try {
            $response = self::makeRequest($url, $accessToken, 'POST', $data);
            
            self::logInfo("Mensagem Instagram enviada com sucesso", [
                'recipient_id' => $recipientId,
                'message_id' => $response['message_id'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            self::logError("Erro ao enviar mensagem Instagram: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * Marcar mensagem como lida
     */
    public static function markAsRead(string $messageId, string $accessToken): array
    {
        self::initConfig();
        
        $config = self::$config['instagram'] ?? [];
        $apiVersion = $config['api_version'] ?? self::$apiVersion;
        
        $url = "https://graph.facebook.com/{$apiVersion}/me/messages";
        
        $data = [
            'message_id' => $messageId
        ];
        
        return self::makeRequest($url, $accessToken, 'POST', $data);
    }
    
    /**
     * Processar webhook do Instagram (mensagens recebidas)
     */
    public static function processWebhook(array $payload): void
    {
        self::logInfo("Instagram webhook recebido", ['payload' => $payload]);
        
        try {
            // Estrutura do webhook Instagram:
            // {
            //   "object": "instagram",
            //   "entry": [
            //     {
            //       "id": "INSTAGRAM_USER_ID",
            //       "time": 1234567890,
            //       "messaging": [
            //         {
            //           "sender": { "id": "SENDER_INSTAGRAM_USER_ID" },
            //           "recipient": { "id": "RECIPIENT_INSTAGRAM_USER_ID" },
            //           "timestamp": 1234567890,
            //           "message": {
            //             "mid": "MESSAGE_ID",
            //             "text": "MESSAGE_TEXT"
            //           }
            //         }
            //       ]
            //     }
            //   ]
            // }
            
            if (!isset($payload['entry'])) {
                self::logWarning("Webhook Instagram inválido: sem 'entry'");
                return;
            }
            
            foreach ($payload['entry'] as $entry) {
                $instagramUserId = $entry['id'] ?? null;
                $messaging = $entry['messaging'] ?? [];
                
                if (!$instagramUserId) {
                    self::logWarning("Entry sem instagram_user_id");
                    continue;
                }
                
                // Buscar conta Instagram
                $instagramAccount = InstagramAccount::findByInstagramUserId($instagramUserId);
                if (!$instagramAccount) {
                    self::logWarning("Conta Instagram não encontrada: {$instagramUserId}");
                    continue;
                }
                
                foreach ($messaging as $event) {
                    self::processMessagingEvent($event, $instagramAccount);
                }
            }
            
        } catch (\Exception $e) {
            self::logError("Erro ao processar webhook Instagram: {$e->getMessage()}");
        }
    }
    
    /**
     * Processar evento de mensagem
     */
    protected static function processMessagingEvent(array $event, array $instagramAccount): void
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $messageData = $event['message'] ?? null;
        
        if (!$senderId || !$recipientId || !$messageData) {
            self::logWarning("Evento de mensagem incompleto", ['event' => $event]);
            return;
        }
        
        // Verificar se é mensagem ENVIADA ou RECEBIDA
        $isIncoming = $senderId !== $instagramAccount['instagram_user_id'];
        
        if (!$isIncoming) {
            self::logInfo("Ignorando mensagem enviada pelo próprio sistema");
            return;
        }
        
        // Extrair dados da mensagem
        $messageId = $messageData['mid'] ?? null;
        $text = $messageData['text'] ?? '';
        $timestamp = $event['timestamp'] ?? time();
        
        self::logInfo("Mensagem Instagram recebida", [
            'sender_id' => $senderId,
            'message_id' => $messageId,
            'text_length' => strlen($text),
        ]);
        
        // Buscar ou criar contato
        $contact = Contact::findByIdentifier($senderId);
        if (!$contact) {
            // Tentar obter informações do perfil (se tivermos permissão)
            $contactData = [
                'name' => "Instagram User {$senderId}",
                'identifier' => $senderId,
                'instagram_user_id' => $senderId,
                'channel' => 'instagram',
            ];
            
            // Tentar gerar avatar com iniciais
            $avatar = AvatarService::generateInitialsAvatar($contactData['name'], $senderId);
            if ($avatar) {
                $contactData['avatar'] = $avatar;
            }
            
            $contactId = Contact::create($contactData);
            $contact = Contact::find($contactId);
            
            self::logInfo("Contato Instagram criado: {$contactId}");
        }
        
        // Buscar ou criar conversa
        $conversation = Conversation::whereFirst('contact_id', '=', $contact['id'], [
            ['channel', '=', 'instagram'],
            ['status', '!=', 'closed']
        ]);
        
        if (!$conversation) {
            $conversationData = [
                'contact_id' => $contact['id'],
                'channel' => 'instagram',
                'status' => 'open',
                'integration_account_id' => $instagramAccount['integration_account_id'] ?? null,
                'started_at' => date('Y-m-d H:i:s', $timestamp / 1000),
            ];
            
            $conversationId = Conversation::create($conversationData);
            $conversation = Conversation::find($conversationId);
            
            self::logInfo("Conversa Instagram criada: {$conversationId}");
            
            // Notificar nova conversa
            WebSocket::notifyNewConversation($conversation);
            
            // Executar automações
            AutomationService::executeForNewConversation($conversationId);
        }
        
        // Criar mensagem
        $messageCreateData = [
            'conversation_id' => $conversation['id'],
            'content' => $text,
            'sender_type' => 'contact',
            'sender_id' => $contact['id'],
            'message_type' => 'text',
            'external_id' => $messageId,
            'sent_at' => date('Y-m-d H:i:s', $timestamp / 1000),
            'status' => 'received',
        ];
        
        $newMessageId = Message::create($messageCreateData);
        $newMessage = Message::find($newMessageId);
        
        self::logInfo("Mensagem Instagram salva no DB: {$newMessageId}");
        
        // Notificar via WebSocket
        WebSocket::notifyNewMessage($conversation['id'], $newMessage);
        
        // Executar automações
        AutomationService::executeForNewMessage($newMessageId);
    }
}

