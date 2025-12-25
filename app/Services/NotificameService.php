<?php
/**
 * Service NotificameService
 * Integração com Notificame API - Suporte a múltiplos canais
 * Canais: WhatsApp, Instagram, Facebook, Telegram, Mercado Livre, WebChat, Email, OLX, LinkedIn, Google Business, Youtube, TikTok
 */

namespace App\Services;

use App\Models\IntegrationAccount;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Helpers\Logger;
use App\Helpers\Validator;

class NotificameService
{
    // URL base padrão da API Notificame
    const BASE_URL = 'https://api.notificame.com.br/v1/';
    
    const CHANNELS = [
        'whatsapp', 'instagram', 'facebook', 'telegram', 
        'mercadolivre', 'webchat', 'email', 'olx', 
        'linkedin', 'google_business', 'youtube', 'tiktok'
    ];
    
    /**
     * Validar canal
     */
    public static function validateChannel(string $channel): bool
    {
        return in_array($channel, self::CHANNELS);
    }
    
    /**
     * Normalizar número de telefone
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remover caracteres especiais
        $phone = str_replace(['+', '-', ' ', '(', ')', '.', '_'], '', $phone);
        
        // Remover sufixos comuns
        $phone = str_replace('@s.whatsapp.net', '', $phone);
        $phone = str_replace('@lid', '', $phone);
        $phone = str_replace('@c.us', '', $phone);
        $phone = str_replace('@g.us', '', $phone);
        
        // Extrair apenas dígitos (pode ter : para separar device ID)
        if (strpos($phone, ':') !== false) {
            $phone = explode(':', $phone)[0];
        }
        
        return ltrim($phone, '+');
    }
    
    /**
     * Obter token da conta
     */
    private static function getAccountToken(int $accountId): string
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \Exception("Conta de integração não encontrada: {$accountId}");
        }
        
        if ($account['provider'] !== 'notificame') {
            throw new \Exception("Conta não é do provider Notificame: {$accountId}");
        }
        
        if (empty($account['api_token'])) {
            throw new \Exception("Token da API não configurado para conta: {$accountId}");
        }
        
        return $account['api_token'];
    }
    
    /**
     * Fazer requisição à API Notificame
     */
    private static function makeRequest(string $endpoint, string $token, string $method = 'GET', array $data = [], ?string $apiUrl = null): array
    {
        if (empty($token)) {
            throw new \Exception("Token da API não informado para a requisição");
        }
        // Usar URL da conta se fornecida, senão usar URL base padrão
        $baseUrl = $apiUrl ? rtrim($apiUrl, '/') . '/' : self::BASE_URL;
        $url = $baseUrl . ltrim($endpoint, '/');
        
        // Log da requisição
        Logger::info("Notificame API Request: {$method} {$url}");
        
        $ch = curl_init();
        
        // Conforme docs, usar X-Api-Token; remover Authorization para evitar conflito
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Api-Token: ' . $token
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log da resposta
        Logger::info("Notificame API Response: HTTP {$httpCode}, Content-Type: {$contentType}");
        
        if ($error) {
            Logger::error("Notificame API cURL error: {$error}");
            throw new \Exception("Erro na requisição Notificame: {$error}");
        }
        
        // Verificar se a resposta é vazia
        if (empty($response)) {
            throw new \Exception("Resposta vazia da API Notificame (HTTP {$httpCode}). URL: {$url}");
        }
        
        // Verificar se é JSON válido
        $responseData = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            // Se não for JSON, pode ser HTML ou texto simples (ex: "200")
            $responsePreview = substr(strip_tags($response), 0, 300);
            Logger::error("Notificame API resposta não-JSON: {$responsePreview}");
            
            throw new \Exception("A API retornou resposta não-JSON (HTTP {$httpCode}). URL: {$url}. Preview: " . substr($responsePreview, 0, 100));
        }
        
        // Garantir retorno array
        if (!is_array($responseData)) {
            $responseData = ['data' => $responseData];
        }
        
        if ($httpCode >= 400) {
            $errorMsg = $responseData['message'] ?? $responseData['error'] ?? $responseData['detail'] ?? "Erro HTTP {$httpCode}";
            if (is_array($errorMsg)) {
                $errorMsg = json_encode($errorMsg);
            }
            Logger::error("Notificame API error: {$errorMsg}");
            throw new \Exception("Erro na API Notificame: {$errorMsg}");
        }
        
        return $responseData ?? [];
    }
    
    /**
     * Criar conta Notificame
     */
    public static function createAccount(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'channel' => 'required|string|in:' . implode(',', self::CHANNELS),
            'api_token' => 'required|string'
        ]);
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }
        
        if (!self::validateChannel($data['channel'])) {
            throw new \InvalidArgumentException("Canal inválido: {$data['channel']}");
        }
        
        $accountData = [
            'name' => $data['name'],
            'provider' => 'notificame',
            'channel' => $data['channel'],
            'api_token' => $data['api_token'],
            'api_url' => $data['api_url'] ?? self::BASE_URL,
            'account_id' => $data['account_id'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'username' => $data['username'] ?? null,
            'status' => 'active',
            'config' => json_encode($data['config'] ?? []),
            'default_funnel_id' => $data['default_funnel_id'] ?? null,
            'default_stage_id' => $data['default_stage_id'] ?? null
        ];
        
        $accountId = IntegrationAccount::create($accountData);
        
        // Verificar conexão
        try {
            self::checkConnection($accountId);
        } catch (\Exception $e) {
            Logger::error("Erro ao verificar conexão Notificame: " . $e->getMessage());
            IntegrationAccount::update($accountId, [
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
        }
        
        return $accountId;
    }
    
    /**
     * Atualizar conta Notificame
     */
    public static function updateAccount(int $accountId, array $data): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['api_token']) && trim((string)$data['api_token']) !== '') {
            $updateData['api_token'] = $data['api_token'];
        }
        if (isset($data['account_id']) && trim((string)$data['account_id']) !== '') {
            $updateData['account_id'] = $data['account_id'];
        }
        if (isset($data['api_url']) && trim((string)$data['api_url']) !== '') {
            $updateData['api_url'] = $data['api_url'];
        }
        if (isset($data['phone_number'])) {
            $updateData['phone_number'] = $data['phone_number'];
        }
        if (isset($data['username'])) {
            $updateData['username'] = $data['username'];
        }
        if (isset($data['config'])) {
            $updateData['config'] = json_encode($data['config']);
        }
        if (isset($data['default_funnel_id'])) {
            $updateData['default_funnel_id'] = $data['default_funnel_id'];
        }
        if (isset($data['default_stage_id'])) {
            $updateData['default_stage_id'] = $data['default_stage_id'];
        }
        
        return IntegrationAccount::update($accountId, $updateData);
    }
    
    /**
     * Deletar conta Notificame
     */
    public static function deleteAccount(int $accountId): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        return IntegrationAccount::delete($accountId);
    }
    
    /**
     * Obter conta Notificame
     */
    public static function getAccount(int $accountId): ?array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            return null;
        }
        
        return $account;
    }
    
    /**
     * Listar contas Notificame
     */
    public static function listAccounts(string $channel = null): array
    {
        $accounts = IntegrationAccount::where('provider', '=', 'notificame');
        
        // Filtrar por canal se especificado
        if ($channel) {
            $accounts = array_filter($accounts, function($account) use ($channel) {
                return $account['channel'] === $channel;
            });
            $accounts = array_values($accounts); // Reindexar array
        }
        
        // Ordenar por nome
        usort($accounts, function($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        return $accounts;
    }
    
    /**
     * Verificar conexão/status
     */
    public static function checkConnection(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $token = self::getAccountToken($accountId);
        if (empty($token)) {
            throw new \Exception("Token da API não configurado para esta conta");
        }
        
        // Obter URL da API da conta se configurada
        $apiUrl = $account['api_url'] ?? null;
        
        Logger::info("Verificando conexão Notificame - Account ID: {$accountId}, API URL: " . ($apiUrl ?: self::BASE_URL));
        
        // Tentar diferentes endpoints para verificar status (seguindo docs Notificame)
        // Referência: https://app.notificame.com.br/docs/#/api
        $endpoints = ['user', 'me', 'account', 'health', 'status', 'ping'];
        $lastError = null;
        $errorMessages = [];
        
        foreach ($endpoints as $endpoint) {
            try {
                Logger::info("Tentando endpoint: {$endpoint}");
                $result = self::makeRequest($endpoint, $token, 'GET', [], $apiUrl);
                
                IntegrationAccount::update($accountId, [
                    'status' => 'active',
                    'error_message' => null,
                    'last_sync_at' => date('Y-m-d H:i:s')
                ]);
                
                Logger::info("Conexão OK usando endpoint: {$endpoint}");
                
                return [
                    'status' => 'active',
                    'connected' => true,
                    'message' => 'Conexão OK',
                    'data' => $result,
                    'endpoint_used' => $endpoint,
                    'api_url' => $apiUrl ?: self::BASE_URL
                ];
            } catch (\Exception $e) {
                $errorMessages[$endpoint] = $e->getMessage();
                Logger::warning("Endpoint {$endpoint} falhou: " . $e->getMessage());
                $lastError = $e;
                // Continuar tentando outros endpoints
                continue;
            }
        }
        
        // Se nenhum endpoint funcionou, retornar erro detalhado
        $errorDetail = "Nenhum endpoint respondeu. Tentativas:\n";
        foreach ($errorMessages as $ep => $msg) {
            $errorDetail .= "- {$ep}: " . substr($msg, 0, 100) . "\n";
        }
        
        Logger::error("Falha ao conectar Notificame: " . $errorDetail);
        
        IntegrationAccount::update($accountId, [
            'status' => 'error',
            'error_message' => 'API URL inválida ou token incorreto'
        ]);
        
        return [
            'status' => 'error',
            'connected' => false,
            'message' => 'Não foi possível conectar. Verifique se a URL da API e o Token estão corretos.',
            'details' => $errorMessages,
            'api_url' => $apiUrl ?: self::BASE_URL
        ];
    }
    
    /**
     * Verificar status de saúde da API
     */
    public static function getHealthStatus(): array
    {
        // Tentar com primeira conta ativa para verificar API
        $account = IntegrationAccount::where('provider', '=', 'notificame')
            ->where('status', '=', 'active')
            ->first();
        
        if (!$account) {
            return [
                'status' => 'unknown',
                'message' => 'Nenhuma conta ativa encontrada'
            ];
        }
        
        try {
            $token = $account['api_token'];
            $result = self::makeRequest('health', $token);
            
            return [
                'status' => 'ok',
                'message' => 'API funcionando',
                'data' => $result
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensagem de texto
     */
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = $account['account_id'] ?? null; // usado como "from" para Instagram
        
        $endpoint = "{$channel}/send"; // default genérico
        $payload = [
            'to' => $to,
            'message' => $message
        ];
        
        if ($channel === 'instagram') {
            if (empty($channelId)) {
                throw new \Exception("Para Instagram, é obrigatório preencher o ID do canal (account_id) que vai em 'from'.");
            }
            // Endpoint e payload conforme docs: https://api.notificame.com.br/v1/channels/instagram/messages
            $endpoint = 'channels/instagram/messages';
            $payload = [
                'from' => $channelId,
                'to' => $to,
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $message
                    ]
                ]
            ];
            // Suporte simples a mídia se fornecido
            if (!empty($options['media_url'])) {
                $payload['contents'] = [
                    [
                        'type' => 'file',
                        'fileMimeType' => $options['media_type'] ?? 'image',
                        'fileUrl' => $options['media_url'],
                        'fileCaption' => $options['caption'] ?? ''
                    ]
                ];
            }
        } else {
            // Genérico: mantém compatibilidade
            if (!empty($options['media_url'])) {
                $payload['media'] = [
                    'url' => $options['media_url'],
                    'type' => $options['media_type'] ?? 'image'
                ];
            }
            if (!empty($options['caption'])) {
                $payload['caption'] = $options['caption'];
            }
        }
        
        try {
            Logger::info("Notificame sendMessage endpoint={$endpoint} channel={$channel} to={$to}");
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            Logger::error("Erro ao enviar mensagem Notificame: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Listar subcontas (resale)
     * Endpoint: GET /resale/
     */
    public static function listSubaccounts(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;

        // Chama /resale/ para obter lista
        return self::makeRequest('resale/', $token, 'GET', [], $apiUrl);
    }
    
    /**
     * Enviar mídia
     */
    public static function sendMedia(int $accountId, string $to, string $mediaUrl, string $type, array $options = []): array
    {
        return self::sendMessage($accountId, $to, '', [
            'media_url' => $mediaUrl,
            'media_type' => $type,
            'caption' => $options['caption'] ?? ''
        ]);
    }
    
    /**
     * Enviar template
     */
    public static function sendTemplate(int $accountId, string $to, string $templateName, array $params = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        
        $payload = [
            'to' => $to,
            'template' => $templateName,
            'params' => $params
        ];
        
        $endpoint = "{$channel}/template";
        
        try {
            Logger::info("Notificame sendTemplate endpoint={$endpoint} channel={$channel} to={$to} template={$templateName}");
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            Logger::error("Erro ao enviar template Notificame: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Enviar mensagem interativa (botões, listas)
     */
    public static function sendInteractive(int $accountId, string $to, array $interactiveData): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        
        $payload = [
            'to' => $to,
            'interactive' => $interactiveData
        ];
        
        $endpoint = "{$channel}/interactive";
        
        try {
            Logger::info("Notificame sendInteractive endpoint={$endpoint} channel={$channel} to={$to}");
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            Logger::error("Erro ao enviar mensagem interativa Notificame: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Configurar webhook
     */
    public static function configureWebhook(int $accountId, string $webhookUrl, array $events = []): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $accountChannelId = $account['account_id'] ?? null; // id do canal, obrigatório para subscriptions
        
        if (empty($accountChannelId)) {
            throw new \Exception("Para configurar webhook no Notificame, o ID do canal (account_id) é obrigatório.");
        }
        
        // Conforme doc: POST /subscriptions/ com criteria.channel = token_do_canal
        $payload = [
            'criteria' => [
                'channel' => $accountChannelId
            ],
            'webhook' => [
                'url' => $webhookUrl
            ]
        ];
        // Se quiser eventos específicos, o Notificame não documenta aqui; manter compat.
        if (!empty($events)) {
            $payload['events'] = $events;
        }
        
        $endpoint = "subscriptions/";
        
        try {
            Logger::info("Notificame configureWebhook endpoint={$endpoint} channel={$channel} url={$webhookUrl} channelId={$accountChannelId}");
            self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            // Salvar webhook URL na conta
            IntegrationAccount::update($accountId, [
                'webhook_url' => $webhookUrl
            ]);
            
            return true;
        } catch (\Exception $e) {
            Logger::error("Erro ao configurar webhook Notificame: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Processar webhook Notificame
     */
    public static function processWebhook(array $payload, string $channel): void
    {
        Logger::info("Notificame webhook recebido - Channel: {$channel}");
        
        // Identificar conta pelo webhook URL ou outros dados do payload
        $account = self::findAccountByWebhook($payload, $channel);
        
        if (!$account) {
            Logger::error("Conta Notificame não encontrada para webhook - Channel: {$channel}");
            return;
        }
        
        // Extrair dados da mensagem
        $messageData = self::extractMessageData($payload, $channel);
        
        if (!$messageData) {
            Logger::warning("Não foi possível extrair dados da mensagem do webhook Notificame");
            return;
        }
        
        // Criar/encontrar contato
        $contact = null;
        $contactData = [
            'name' => $messageData['name'] ?? null
        ];
        
        // Identificar contato baseado no canal
        if ($channel === 'whatsapp') {
            $phone = self::normalizePhoneNumber($messageData['from']);
            $contact = \App\Models\Contact::findByPhoneNormalized($phone);
            if (!$contact) {
                $contactId = \App\Models\Contact::create(array_merge($contactData, [
                    'phone' => $phone,
                    'whatsapp_id' => $messageData['from']
                ]));
                $contact = \App\Models\Contact::find($contactId);
            }
        } elseif ($channel === 'email') {
            $email = $messageData['from'];
            $contact = \App\Models\Contact::where('email', '=', $email)->first();
            if (!$contact) {
                $contactId = \App\Models\Contact::create(array_merge($contactData, [
                    'email' => $email
                ]));
                $contact = \App\Models\Contact::find($contactId);
            }
        } else {
            // Para outros canais, usar identifier genérico
            $contact = \App\Models\Contact::findOrCreate(array_merge($contactData, [
                'identifier' => $messageData['from']
            ]));
        }
        
        if (!$contact) {
            Logger::error("Não foi possível criar/encontrar contato para Notificame webhook");
            return;
        }
        
        // Criar/encontrar conversa
        $conversationData = [
            'contact_id' => $contact['id'],
            'channel' => $channel,
            'integration_account_id' => $account['id']
        ];
        
        // Buscar conversa existente
        $conversation = \App\Models\Conversation::where('contact_id', '=', $contact['id'])
            ->where('channel', '=', $channel)
            ->where('integration_account_id', '=', $account['id'])
            ->first();
        
        if (!$conversation) {
            // Criar nova conversa
            $conversation = ConversationService::create($conversationData, false);
        }
        
        // Salvar mensagem
        Message::create([
            'conversation_id' => $conversation['id'],
            'contact_id' => $contact['id'],
            'content' => $messageData['content'],
            'type' => $messageData['type'],
            'external_id' => $messageData['external_id'],
            'direction' => 'inbound',
            'metadata' => json_encode($messageData['metadata'] ?? [])
        ]);
        
        // Notificar via WebSocket
        try {
            \App\Helpers\WebSocket::notifyNewMessage($conversation['id']);
        } catch (\Exception $e) {
            Logger::error("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações
        try {
            AutomationService::trigger('message.received', [
                'conversation_id' => $conversation['id'],
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            Logger::error("Erro ao executar automações: " . $e->getMessage());
        }
    }
    
    /**
     * Encontrar conta por webhook
     */
    private static function findAccountByWebhook(array $payload, string $channel): ?array
    {
        // Tentar encontrar por account_id no payload
        if (isset($payload['account_id'])) {
            $account = IntegrationAccount::where('provider', '=', 'notificame')
                ->where('channel', '=', $channel)
                ->where('account_id', '=', $payload['account_id'])
                ->first();
            
            if ($account) {
                return $account;
            }
        }

        // Tentar por subscriptionId (vem no webhook do Notificame e é o id do canal)
        if (isset($payload['subscriptionId'])) {
            $account = IntegrationAccount::where('provider', '=', 'notificame')
                ->where('channel', '=', $channel)
                ->where('account_id', '=', $payload['subscriptionId'])
                ->first();
            if ($account) {
                return $account;
            }
        }
        
        // Tentar encontrar por phone_number (WhatsApp)
        if ($channel === 'whatsapp' && isset($payload['from'])) {
            $phone = self::normalizePhoneNumber($payload['from']);
            $account = IntegrationAccount::findByPhone($phone, 'whatsapp');
            
            if ($account && $account['provider'] === 'notificame') {
                return $account;
            }
        }
        
        // Tentar encontrar primeira conta ativa do canal
        $account = IntegrationAccount::where('provider', '=', 'notificame')
            ->where('channel', '=', $channel)
            ->where('status', '=', 'active')
            ->first();
        
        return $account;
    }
    
    /**
     * Extrair dados da mensagem do payload
     */
    private static function extractMessageData(array $payload, string $channel): ?array
    {
        $data = [
            'from' => null,
            'content' => '',
            'type' => 'text',
            'external_id' => null,
            'name' => null,
            'metadata' => []
        ];
        
        // Estrutura padrão Notificame (exemplo: { message: { from, to, contents: [...] } })
        if (isset($payload['message'])) {
            $msg = $payload['message'];
            $data['from'] = $msg['from'] ?? $msg['sender'] ?? null;
            $data['external_id'] = $msg['id'] ?? $msg['message_id'] ?? null;
            $data['name'] = $msg['visitor']['name'] ?? $msg['visitor']['firstName'] ?? $msg['sender_name'] ?? null;
            $data['metadata'] = $msg['metadata'] ?? $msg;

            // Conteúdo: se houver contents[], usar o primeiro item
            if (!empty($msg['contents']) && is_array($msg['contents'])) {
                $contentItem = $msg['contents'][0];
                $data['type'] = $contentItem['type'] ?? 'text';
                if (($contentItem['type'] ?? '') === 'text') {
                    $data['content'] = $contentItem['text'] ?? '';
                } elseif (($contentItem['type'] ?? '') === 'file') {
                    $data['content'] = $contentItem['fileUrl'] ?? ($contentItem['fileCaption'] ?? '');
                    $data['metadata']['file'] = $contentItem;
                } else {
                    $data['content'] = $contentItem['text'] ?? $contentItem['fileUrl'] ?? '';
                }
            } else {
                // fallback texto simples
                $data['content'] = $msg['text'] ?? $msg['content'] ?? '';
                $data['type'] = $msg['type'] ?? 'text';
            }
        } else {
            // Fallback: usar payload direto
            $data['from'] = $payload['from'] ?? $payload['sender'] ?? null;
            $data['content'] = $payload['text'] ?? $payload['content'] ?? $payload['message'] ?? '';
            $data['type'] = $payload['type'] ?? 'text';
            $data['external_id'] = $payload['id'] ?? $payload['message_id'] ?? null;
            $data['name'] = $payload['name'] ?? $payload['sender_name'] ?? null;
            $data['metadata'] = $payload;
        }
        
        // Processar mídia se houver
        if (isset($payload['media']) || isset($payload['attachment'])) {
            $media = $payload['media'] ?? $payload['attachment'];
            $data['type'] = $media['type'] ?? 'image';
            $data['content'] = $media['url'] ?? $media['caption'] ?? '';
            $data['metadata']['media'] = $media;
        }
        
        if (!$data['from']) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Listar templates
     */
    public static function listTemplates(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        
        $endpoint = "{$channel}/templates";
        
        try {
            $result = self::makeRequest($endpoint, $token);
            return $result['templates'] ?? $result['data'] ?? [];
        } catch (\Exception $e) {
            Logger::error("Erro ao listar templates Notificame: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Criar template
     */
    public static function createTemplate(int $accountId, array $templateData): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $channel = $account['channel'];
        $token = $account['api_token'];
        
        $endpoint = "{$channel}/templates";
        
        try {
            $result = self::makeRequest($endpoint, 'POST', $templateData, $token);
            return $result;
        } catch (\Exception $e) {
            Logger::error("Erro ao criar template Notificame: " . $e->getMessage());
            throw $e;
        }
    }
}

