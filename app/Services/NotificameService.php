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
     * Helpers privados para logs Notificame
     */
    private static function logInfo(string $message): void {
        Logger::notificame("[INFO] " . $message);
    }
    
    private static function logError(string $message): void {
        Logger::notificame("[ERROR] " . $message);
    }
    
    private static function logWarning(string $message): void {
        Logger::notificame("[WARNING] " . $message);
    }
    
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
        return \App\Models\Contact::normalizePhoneNumber($phone);
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
        self::logInfo("Notificame API Request: {$method} {$url}");
        
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
        self::logInfo("Notificame API Response: HTTP {$httpCode}, Content-Type: {$contentType}");
        
        if ($error) {
            self::logError("Notificame API cURL error: {$error}");
            throw new \Exception("Erro na requisição Notificame: {$error}");
        }
        
        // Verificar se a resposta é vazia (DELETE/204 pode retornar vazio)
        if (empty($response)) {
            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'http_code' => $httpCode];
            }
            throw new \Exception("Resposta vazia da API Notificame (HTTP {$httpCode}). URL: {$url}");
        }
        
        // Verificar se é JSON válido
        $responseData = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            // Se não for JSON, pode ser HTML ou texto simples (ex: "200")
            $responsePreview = substr(strip_tags($response), 0, 300);
            self::logError("Notificame API resposta não-JSON: {$responsePreview}");
            
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
            self::logError("Notificame API error: {$errorMsg}");
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
            self::logError("Erro ao verificar conexão Notificame: " . $e->getMessage());
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
        
        self::logInfo("Verificando conexão Notificame - Account ID: {$accountId}, API URL: " . ($apiUrl ?: self::BASE_URL));
        
        // Tentar diferentes endpoints para verificar status (seguindo docs Notificame)
        // Referência: https://app.notificame.com.br/docs/#/api
        $endpoints = ['user', 'me', 'account', 'health', 'status', 'ping'];
        $lastError = null;
        $errorMessages = [];
        
        foreach ($endpoints as $endpoint) {
            try {
                self::logInfo("Tentando endpoint: {$endpoint}");
                $result = self::makeRequest($endpoint, $token, 'GET', [], $apiUrl);
                
                IntegrationAccount::update($accountId, [
                    'status' => 'active',
                    'error_message' => null,
                    'last_sync_at' => date('Y-m-d H:i:s')
                ]);
                
                self::logInfo("Conexão OK usando endpoint: {$endpoint}");
                
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
                self::logWarning("Endpoint {$endpoint} falhou: " . $e->getMessage());
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
        
        self::logError("Falha ao conectar Notificame: " . $errorDetail);
        
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
        $allAccounts = IntegrationAccount::all();
        $account = null;
        foreach ($allAccounts as $acc) {
            if ($acc['provider'] === 'notificame' && $acc['status'] === 'active') {
                $account = $acc;
                break;
            }
        }
        
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
        $channelId = $account['account_id'] ?? null;

        if (empty($channelId)) {
            throw new \Exception("Notificame: campo 'account_id' (sender identifier) é obrigatório para envio. Conta ID={$accountId}, Channel={$channel}");
        }

        // Endpoint padrão conforme SDK NotificameHub: channels/{channel}/messages
        $endpoint = "channels/{$channel}/messages";

        // Payload padrão: from + to + contents[]
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

        // Mídia/arquivo (áudio, imagem, documento, vídeo)
        if (!empty($options['media_url'])) {
            $mediaContent = [
                'type' => 'file',
                'fileUrl' => $options['media_url'],
                'fileMimeType' => $options['media_mime'] ?? $options['media_type'] ?? 'application/octet-stream'
            ];
            if (!empty($options['media_name'])) {
                $mediaContent['fileName'] = $options['media_name'];
            }
            if (!empty($options['caption'])) {
                $mediaContent['fileCaption'] = $options['caption'];
            }

            if (!empty($message) && empty($options['caption'])) {
                // Se há texto E mídia sem caption, enviar ambos como conteúdos separados
                $payload['contents'] = [
                    $mediaContent,
                    ['type' => 'text', 'text' => $message]
                ];
            } else {
                $payload['contents'] = [$mediaContent];
            }
        }
        
        try {
            self::logInfo("========== Notificame sendMessage INÍCIO ==========");
            self::logInfo("Notificame sendMessage - Account: {$account['name']} (ID: {$accountId})");
            self::logInfo("Notificame sendMessage - Channel: {$channel}");
            self::logInfo("Notificame sendMessage - Endpoint: {$endpoint}");
            self::logInfo("Notificame sendMessage - API URL: " . ($apiUrl ?: self::BASE_URL));
            self::logInfo("Notificame sendMessage - To (destinatário): {$to}");
            self::logInfo("Notificame sendMessage - Message length: " . strlen($message));
            self::logInfo("Notificame sendMessage - Has options: " . (empty($options) ? 'NO' : 'YES (' . implode(', ', array_keys($options)) . ')'));
            self::logInfo("Notificame sendMessage - Payload completo:");
            self::logInfo(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            self::logInfo("Notificame sendMessage - ✅ Resposta API (sucesso):");
            self::logInfo(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            self::logInfo("========== Notificame sendMessage FIM (Sucesso) ==========");
            
            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            self::logError("========== Notificame sendMessage FIM (Erro) ==========");
            self::logError("Erro ao enviar mensagem Notificame: " . $e->getMessage());
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
    public static function sendTemplate(int $accountId, string $to, string $templateName, array $params = [], string $language = 'pt_BR'): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }

        $channel = $account['channel'];
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = $account['account_id'] ?? null;

        if (empty($channelId)) {
            throw new \Exception("Notificame: campo 'account_id' é obrigatório para envio de template. Conta ID={$accountId}");
        }

        // Conforme docs NotificaMe: POST channels/{channel}/messages
        // Payload: {from, to, contents: [{type: "template", template: {name, components, language: {code}}}]}
        $endpoint = "channels/{$channel}/messages";

        $components = [];
        if (!empty($params)) {
            $parameters = [];
            foreach ($params as $value) {
                $parameters[] = ['type' => 'text', 'text' => (string)$value];
            }
            $components[] = [
                'type' => 'BODY',
                'parameters' => $parameters,
            ];
        }

        $payload = [
            'from' => $channelId,
            'to' => $to,
            'contents' => [
                [
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'components' => $components,
                        'language' => [
                            'code' => $language,
                        ],
                    ]
                ]
            ]
        ];

        try {
            self::logInfo("Notificame sendTemplate endpoint={$endpoint} channel={$channel} to={$to} template={$templateName}");
            self::logInfo("Notificame sendTemplate payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);

            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            self::logError("Erro ao enviar template Notificame: " . $e->getMessage());
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
        $channelId = $account['account_id'] ?? null;

        if (empty($channelId)) {
            throw new \Exception("Notificame: campo 'account_id' é obrigatório para envio interativo. Conta ID={$accountId}");
        }
        
        // Conforme docs NotificaMe: POST channels/{channel}/messages
        // Payload: {from, to, contents: [{type: "interactive", interactive: {...}}]}
        $endpoint = "channels/{$channel}/messages";
        $payload = [
            'from' => $channelId,
            'to' => $to,
            'contents' => [
                [
                    'type' => 'interactive',
                    'interactive' => $interactiveData,
                ]
            ]
        ];
        
        try {
            self::logInfo("Notificame sendInteractive endpoint={$endpoint} channel={$channel} to={$to}");
            self::logInfo("Notificame sendInteractive payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            return [
                'success' => true,
                'message_id' => $result['id'] ?? $result['message_id'] ?? null,
                'data' => $result
            ];
        } catch (\Exception $e) {
            self::logError("Erro ao enviar mensagem interativa Notificame: " . $e->getMessage());
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
            self::logInfo("Notificame configureWebhook endpoint={$endpoint} channel={$channel} url={$webhookUrl} channelId={$accountChannelId}");
            self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            
            // Salvar webhook URL na conta
            IntegrationAccount::update($accountId, [
                'webhook_url' => $webhookUrl
            ]);
            
            return true;
        } catch (\Exception $e) {
            self::logError("Erro ao configurar webhook Notificame: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Processar webhook Notificame
     */
    public static function processWebhook(array $payload, string $channel): void
    {
        self::logInfo("========== Notificame Webhook INÍCIO ==========");
        self::logInfo("Notificame webhook recebido - Channel: {$channel}");
        self::logInfo("Notificame webhook payload completo: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // ── Tratar eventos de status de mensagem (MESSAGE_STATUS) ──────────────
        // O Notificame envia este tipo quando uma mensagem enviada muda de status
        // (SENT, DELIVERED, READ, REJECTED, FAILED, etc.)
        $eventType = $payload['type'] ?? null;
        if ($eventType === 'MESSAGE_STATUS') {
            self::processMessageStatusEvent($payload, $channel);
            self::logInfo("========== Notificame Webhook FIM (MESSAGE_STATUS tratado) ==========");
            return;
        }

        // ── Tratar eventos de conexão/conta para manter status sincronizado ──
        if ($eventType && in_array(strtoupper($eventType), ['CONNECTION_STATUS', 'ACCOUNT_STATUS'])) {
            self::processConnectionStatusEvent($payload, $channel);
            self::logInfo("========== Notificame Webhook FIM ({$eventType} tratado) ==========");
            return;
        }

        // ── Ignorar outros eventos de controle que não são mensagens recebidas ──
        $ignoredTypes = ['SUBSCRIPTION_STATUS', 'PING'];
        if ($eventType && in_array(strtoupper($eventType), $ignoredTypes)) {
            self::logInfo("Notificame webhook: Evento de controle ignorado - type={$eventType}");
            self::logInfo("========== Notificame Webhook FIM (Evento de controle ignorado) ==========");
            return;
        }

        if ($eventType) {
            self::logInfo("Notificame webhook: Tipo de evento detectado: {$eventType} — prosseguindo como mensagem");
        }
        
        // Identificar conta pelo webhook URL ou outros dados do payload
        $account = self::findAccountByWebhook($payload, $channel);
        
        if (!$account) {
            self::logError("Conta Notificame não encontrada para webhook - Channel: {$channel}");
            self::logInfo("========== Notificame Webhook FIM (Erro: Conta não encontrada) ==========");
            return;
        }
        
        self::logInfo("Notificame conta identificada: ID={$account['id']}, Name={$account['name']}, Channel={$account['channel']}");
        
        // Extrair dados da mensagem
        $messageData = self::extractMessageData($payload, $channel);
        
        if (!$messageData) {
            self::logWarning("Notificame webhook: Não foi possível extrair dados da mensagem");
            self::logInfo("========== Notificame Webhook FIM (Erro: Dados inválidos) ==========");
            return;
        }
        
        self::logInfo("Notificame webhook: Dados da mensagem extraídos:");
        self::logInfo("  - From (destinatário para resposta): {$messageData['from']}");
        self::logInfo("  - Name: {$messageData['name']}");
        self::logInfo("  - Avatar: " . ($messageData['avatar'] ?? 'NULL'));
        self::logInfo("  - Content: " . substr($messageData['content'], 0, 100));
        self::logInfo("  - Type: {$messageData['type']}");
        
        // ⚠️ VALIDAÇÃO: Ignorar mensagens do próprio perfil (mensagens enviadas, não recebidas)
        $messageDirection = $payload['message']['direction'] ?? $payload['direction'] ?? null;
        $messageTo = $payload['message']['to'] ?? $payload['to'] ?? null;
        $accountIdentifier = $account['account_id'] ?? null;
        
        self::logInfo("Notificame webhook: Validando remetente:");
        self::logInfo("  - Direction: " . ($messageDirection ?? 'NULL'));
        self::logInfo("  - To: " . ($messageTo ?? 'NULL'));
        self::logInfo("  - Account ID: " . ($accountIdentifier ?? 'NULL'));
        self::logInfo("  - From: {$messageData['from']}");
        
        // Ignorar se:
        // 1. Direção é OUT (mensagem enviada por nós)
        // 2. Remetente é o mesmo que o destinatário (enviando para si mesmo)
        // 3. Remetente é o mesmo que o account_id da conta integrada
        if (
            $messageDirection === 'OUT' || 
            $messageDirection === 'out' ||
            ($messageTo && $messageData['from'] === $messageTo) ||
            ($accountIdentifier && $messageData['from'] === $accountIdentifier)
        ) {
            self::logInfo("Notificame webhook: ⚠️ IGNORANDO - Mensagem do próprio perfil (não deve criar conversa)");
            self::logInfo("  - Motivo: " . (
                $messageDirection === 'OUT' || $messageDirection === 'out' ? 'Direction=OUT' :
                ($messageTo && $messageData['from'] === $messageTo ? 'From=To' : 'From=AccountID')
            ));
            self::logInfo("========== Notificame Webhook FIM (Ignorado: próprio perfil) ==========");
            return;
        }
        
        self::logInfo("Notificame webhook: ✅ Validação OK - Mensagem de contato externo");
        
        // Criar/encontrar contato
        $contact = null;
        $contactName = $messageData['name'] ?? null;
        if (empty($contactName)) {
            $contactName = 'Contato Notificame';
        }
        $contactData = [
            'name' => $contactName
        ];
        
        // Identificar contato baseado no canal
        if ($channel === 'whatsapp') {
            $phone = self::normalizePhoneNumber($messageData['from']);
            $contact = \App\Models\Contact::findByPhoneNormalized($phone);
            if (!$contact) {
                $contactId = \App\Models\Contact::create(array_merge($contactData, [
                    'phone' => $phone,
                    'whatsapp_id' => $messageData['from'],
                    'identifier' => $messageData['from']
                ]));
                $contact = \App\Models\Contact::find($contactId);
            }
        } elseif ($channel === 'email') {
            $email = $messageData['from'];
            // Buscar contato por email (compativel com Model que retorna array)
            $allContacts = \App\Models\Contact::all();
            $contact = null;
            foreach ($allContacts as $c) {
                if ($c['email'] === $email) {
                    $contact = $c;
                    break;
                }
            }
            if (!$contact) {
                $contactId = \App\Models\Contact::create(array_merge($contactData, [
                    'email' => $email,
                    'identifier' => $messageData['from']
                ]));
                $contact = \App\Models\Contact::find($contactId);
            }
        } else {
            // Para outros canais (Instagram, Facebook, etc), usar identifier genérico
            self::logInfo("Notificame webhook: Criando/buscando contato com identifier={$messageData['from']} (canal={$channel})");
            
            $contactCreateData = array_merge($contactData, [
                'identifier' => $messageData['from']
            ]);
            
            self::logInfo("Notificame webhook: Dados para findOrCreate: " . json_encode($contactCreateData, JSON_UNESCAPED_UNICODE));
            
            try {
                $contact = \App\Models\Contact::findOrCreate($contactCreateData);
                self::logInfo("Notificame webhook: findOrCreate retornou: " . ($contact ? 'SUCESSO' : 'NULL'));
            } catch (\Exception $e) {
                self::logError("Notificame webhook: Erro em findOrCreate: " . $e->getMessage());
                self::logError("Notificame webhook: Trace: " . $e->getTraceAsString());
                self::logInfo("========== Notificame Webhook FIM (Erro: findOrCreate falhou) ==========");
                return;
            }
        }
        
        if (!$contact) {
            self::logError("Notificame webhook: Não foi possível criar/encontrar contato (retornou NULL)");
            self::logInfo("========== Notificame Webhook FIM (Erro: Contato inválido) ==========");
            return;
        }
        
        self::logInfo("Notificame webhook: Contato encontrado/criado:");
        self::logInfo("  - ContactID: {$contact['id']}");
        self::logInfo("  - Name: {$contact['name']}");
        self::logInfo("  - Phone: " . ($contact['phone'] ?? 'NULL'));
        self::logInfo("  - Identifier: " . ($contact['identifier'] ?? 'NULL'));
        self::logInfo("  - Avatar: " . (isset($contact['avatar']) ? (strlen($contact['avatar']) > 50 ? substr($contact['avatar'], 0, 50) . '...' : $contact['avatar']) : 'NULL'));
        self::logInfo("  - Email: " . ($contact['email'] ?? 'NULL'));
        
        // ⚠️ VALIDAÇÃO: Não criar conversa se contato tiver phone = 'system'
        if (isset($contact['phone']) && ($contact['phone'] === 'system' || $contact['phone'] === '0')) {
            self::logInfo("⚠️ Abortando: Contato com phone do sistema (phone={$contact['phone']}, id={$contact['id']})");
            self::logInfo("========== Notificame Webhook FIM (Contato do sistema) ==========");
            return;
        }
        
        // 🔥 NOVO: Detectar se é comentário do Instagram
        $finalChannel = $channel;
        if ($channel === 'instagram' && ($messageData['type'] ?? 'text') === 'comment') {
            $finalChannel = 'instagram_comment';
            self::logInfo("Notificame webhook: 📷💬 COMENTÁRIO INSTAGRAM detectado! Canal: instagram_comment");
            
            // Adicionar informações do comentário aos metadados
            if (!empty($messageData['metadata']['media'])) {
                self::logInfo("Notificame webhook: Link do post: " . ($messageData['metadata']['media']['link'] ?? 'N/A'));
            }
        }
        
        // Criar/encontrar conversa
        $conversationData = [
            'contact_id' => $contact['id'],
            'channel' => $finalChannel,
            'integration_account_id' => $account['id']
        ];
        
        self::logInfo("Notificame webhook: Buscando conversa existente...");
        self::logInfo("  - ContactID: {$contact['id']}");
        self::logInfo("  - Channel: {$finalChannel}");
        self::logInfo("  - IntegrationAccountID: {$account['id']}");
        
        // Buscar conversa existente (compativel com Model que retorna array)
        try {
            $allConversations = \App\Models\Conversation::all();
            self::logInfo("Notificame webhook: Total de conversas no sistema: " . count($allConversations));
            
            $conversation = null;
            foreach ($allConversations as $conv) {
                if (
                    $conv['contact_id'] == $contact['id'] && 
                    $conv['channel'] == $finalChannel && 
                    $conv['integration_account_id'] == $account['id']
                ) {
                    $conversation = $conv;
                    break;
                }
            }
            
            if ($conversation) {
                self::logInfo("Notificame webhook: Conversa EXISTENTE encontrada - ConversationID={$conversation['id']}");
            } else {
                self::logInfo("Notificame webhook: Nenhuma conversa existente encontrada, criando NOVA conversa...");
            }
        } catch (\Exception $e) {
            self::logError("Notificame webhook: Erro ao buscar conversas: " . $e->getMessage());
            self::logInfo("========== Notificame Webhook FIM (Erro: Busca de conversa falhou) ==========");
            return;
        }
        
        $isNewConversation = false;
        if (!$conversation) {
            // Criar nova conversa
            self::logInfo("Notificame webhook: Criando NOVA conversa - ContactID={$contact['id']}, Channel={$finalChannel}");
            self::logInfo("Notificame webhook: Dados para criar conversa: " . json_encode($conversationData, JSON_UNESCAPED_UNICODE));
            
            try {
                $conversation = ConversationService::create($conversationData, false);
                $isNewConversation = true;
                self::logInfo("Notificame webhook: Nova conversa criada com SUCESSO - ConversationID={$conversation['id']}");
                // Nota: ConversationService::create já envia notifyNewConversation via WebSocket
                // com dados completos (findWithRelations). Não duplicar aqui.
            } catch (\Exception $e) {
                self::logError("Notificame webhook: ERRO ao criar conversa: " . $e->getMessage());
                self::logError("Notificame webhook: Trace: " . $e->getTraceAsString());
                self::logInfo("========== Notificame Webhook FIM (Erro: Criação de conversa falhou) ==========");
                return;
            }
        }
        
        // Salvar mensagem
        self::logInfo("Notificame webhook: Salvando mensagem - ConversationID={$conversation['id']}, Type={$messageData['type']}");
        
        $messageCreateData = [
            'conversation_id' => $conversation['id'],
            'contact_id' => $contact['id'],
            'sender_id' => $contact['id'],
            'sender_type' => 'contact',
            'content' => $messageData['content'],
            'message_type' => $messageData['type'],
            'type' => $messageData['type'],
            'external_id' => $messageData['external_id'],
            'direction' => 'inbound',
            'status' => 'received',
            'metadata' => json_encode($messageData['metadata'] ?? [])
        ];
        
        self::logInfo("Notificame webhook: Dados da mensagem: " . json_encode([
            'conversation_id' => $conversation['id'],
            'contact_id' => $contact['id'],
            'content_length' => strlen($messageData['content']),
            'type' => $messageData['type']
        ], JSON_UNESCAPED_UNICODE));
        
        try {
            $messageId = Message::create($messageCreateData);
            self::logInfo("Notificame webhook: Mensagem salva com SUCESSO - MessageID={$messageId}");
        } catch (\Exception $e) {
            self::logError("Notificame webhook: ERRO ao salvar mensagem: " . $e->getMessage());
            self::logError("Notificame webhook: Trace: " . $e->getTraceAsString());
            self::logInfo("========== Notificame Webhook FIM (Erro: Salvamento de mensagem falhou) ==========");
            return;
        }
        
        // Buscar mensagem criada para notificar
        self::logInfo("Notificame webhook: Buscando mensagem criada para notificação WebSocket...");
        $message = Message::find($messageId);
        
        if ($message) {
            self::logInfo("Notificame webhook: Mensagem encontrada, preparando notificação WebSocket");
            // Adicionar campos necessários para o frontend
            $message['type'] = ($message['message_type'] ?? 'text') === 'note' ? 'note' : 'message';
            $message['direction'] = 'incoming';
            
            // Notificar via WebSocket sobre nova mensagem (atualiza painel de chat)
            try {
                \App\Helpers\WebSocket::notifyNewMessage($conversation['id'], $message);
                self::logInfo("Notificame webhook: WebSocket notificado sobre nova mensagem");
            } catch (\Exception $e) {
                self::logError("Notificame webhook: Erro ao notificar WebSocket de mensagem: " . $e->getMessage());
            }

            // Notificar atualização da conversa na lista (atualiza last_message, unread_count e posição)
            // Isso corrige o preview em branco na lista de conversas para todos os canais Notificame
            try {
                $convFull = \App\Models\Conversation::findWithRelations($conversation['id']);
                if ($convFull) {
                    // Adicionar last_message manualmente (findWithRelations não inclui esse campo)
                    $convFull['last_message']    = $messageData['content'];
                    $convFull['last_message_at'] = $message['created_at'] ?? date('Y-m-d H:i:s');
                    $convFull['unread_count']     = ($convFull['unread_count'] ?? 0) + 1;
                    \App\Helpers\WebSocket::notifyConversationUpdated($conversation['id'], $convFull);
                    self::logInfo("Notificame webhook: Lista de conversas atualizada via WebSocket (last_message incluído)");
                }
            } catch (\Exception $e) {
                self::logError("Notificame webhook: Erro ao notificar atualização de conversa: " . $e->getMessage());
            }
        } else {
            self::logWarning("Notificame webhook: Mensagem não encontrada após criação (MessageID={$messageId})");
        }
        
        // Executar automações
        self::logInfo("Notificame webhook: Executando automações...");
        try {
            // Se é nova conversa, disparar trigger de conversation.created
            if ($isNewConversation) {
                self::logInfo("Notificame webhook: Disparando automação de nova conversa - ConversationID={$conversation['id']}");
                AutomationService::executeForNewConversation($conversation['id']);
                self::logInfo("Notificame webhook: Automação de nova conversa executada");
            }
            
            // Disparar trigger de message.received
            if (isset($messageId)) {
                self::logInfo("Notificame webhook: Disparando automação de nova mensagem - MessageID={$messageId}");
                AutomationService::executeForMessageReceived($messageId);
                self::logInfo("Notificame webhook: Automação de nova mensagem executada");
            }
        } catch (\Exception $e) {
            self::logError("Notificame webhook: Erro ao executar automações: " . $e->getMessage());
            self::logError("Notificame webhook: Trace: " . $e->getTraceAsString());
        }
        
        self::logInfo("Notificame webhook processado com sucesso!");
        self::logInfo("========== Notificame Webhook FIM (Sucesso) ==========");
    }
    
    /**
     * Processar evento MESSAGE_STATUS do Notificame
     * 
     * Processar evento de status de conexão (CONNECTION_STATUS / ACCOUNT_STATUS)
     * Atualiza o campo `status` em integration_accounts para refletir o estado real da conexão.
     */
    private static function processConnectionStatusEvent(array $payload, string $channel): void
    {
        $eventType = $payload['type'] ?? 'CONNECTION_STATUS';
        self::logInfo("Processando evento {$eventType} para channel={$channel}");
        self::logInfo("Payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Extrair status: pode vir em vários formatos do Notificame
        $connectionStatus = $payload['status']
            ?? $payload['connectionStatus']['status']
            ?? $payload['connection']['status']
            ?? $payload['state']
            ?? null;

        $accountIdentifier = $payload['subscriptionId']
            ?? $payload['account_id']
            ?? $payload['channelId']
            ?? null;

        if (!$connectionStatus) {
            self::logWarning("{$eventType}: Status não encontrado no payload");
            return;
        }

        $normalizedStatus = strtolower($connectionStatus);
        $isConnected = in_array($normalizedStatus, ['connected', 'active', 'online', 'open', 'ready']);
        $newStatus = $isConnected ? 'active' : 'disconnected';

        self::logInfo("{$eventType}: connectionStatus={$connectionStatus} -> {$newStatus}");

        // Encontrar a conta correspondente
        $account = self::findAccountByWebhook($payload, $channel);

        if (!$account) {
            self::logWarning("{$eventType}: Conta não encontrada para channel={$channel}, accountId={$accountIdentifier}");
            return;
        }

        $currentStatus = $account['status'] ?? '';
        if ($currentStatus !== $newStatus) {
            IntegrationAccount::update($account['id'], [
                'status' => $newStatus,
                'error_message' => $isConnected ? null : "Desconectado: {$connectionStatus}",
                'last_sync_at' => date('Y-m-d H:i:s')
            ]);
            self::logInfo("{$eventType}: Conta ID={$account['id']} atualizada: {$currentStatus} -> {$newStatus}");
        } else {
            self::logInfo("{$eventType}: Status já é {$newStatus}, sem mudança");
        }
    }

    /**
     * Atualiza o status da mensagem no banco quando o Notificame informa
     * que uma mensagem enviada foi entregue, lida ou rejeitada.
     * 
     * Códigos conhecidos: SENT, DELIVERED, READ, REJECTED, FAILED, ERROR
     * Erro Instagram code 10 = "outside of allowed window" (janela 24h expirada)
     */
    private static function processMessageStatusEvent(array $payload, string $channel): void
    {
        $messageId    = $payload['messageId'] ?? null;
        $statusData   = $payload['messageStatus'] ?? [];
        $statusCode   = strtoupper($statusData['code'] ?? '');
        $description  = $statusData['description'] ?? '';
        $errorCode    = $statusData['error']['code'] ?? null;
        $errorMsg     = $statusData['error']['message'] ?? null;
        $visitorName  = $payload['visitor']['name'] ?? $payload['visitor']['firstName'] ?? null;

        self::logInfo("MESSAGE_STATUS recebido:");
        self::logInfo("  - messageId: " . ($messageId ?? 'NULL'));
        self::logInfo("  - statusCode: {$statusCode}");
        self::logInfo("  - description: {$description}");
        self::logInfo("  - channel: {$channel}");
        if ($errorCode !== null) {
            self::logInfo("  - error.code: {$errorCode}");
            self::logInfo("  - error.message: {$errorMsg}");
        }
        if ($visitorName) {
            self::logInfo("  - visitor: {$visitorName}");
        }

        // Traduzir código para status interno
        $internalStatus = match($statusCode) {
            'SENT'       => 'sent',
            'DELIVERED'  => 'delivered',
            'READ'       => 'read',
            'REJECTED', 'FAILED', 'ERROR' => 'failed',
            default      => null,
        };

        if (!$messageId) {
            self::logWarning("MESSAGE_STATUS sem messageId — impossível atualizar mensagem no banco");
            return;
        }

        // Buscar mensagem pelo external_id
        $message = Message::findByExternalId($messageId);

        if (!$message) {
            self::logWarning("MESSAGE_STATUS: Mensagem não encontrada no banco com external_id={$messageId}");
            // Pode ser mensagem enviada cujo external_id não foi salvo — logar e ignorar
            if ($statusCode === 'REJECTED' || $statusCode === 'FAILED') {
                // Logar detalhadamente para facilitar diagnóstico
                if ($errorCode == 10) {
                    self::logWarning("⚠️  INSTAGRAM JANELA 24H: Mensagem rejeitada — O Instagram só permite enviar DMs dentro de 24h após a última mensagem do usuário.");
                    self::logWarning("    Solução: Aguarde o usuário enviar uma nova mensagem antes de responder.");
                } else {
                    self::logError("Mensagem rejeitada pelo Instagram. Código: {$errorCode}. Motivo: {$errorMsg}");
                }
            }
            return;
        }

        self::logInfo("MESSAGE_STATUS: Mensagem encontrada no banco — ID={$message['id']}, status atual={$message['status']}");

        // Atualizar status se temos um mapeamento
        if ($internalStatus) {
            $updateData = ['status' => $internalStatus];

            if ($internalStatus === 'delivered') {
                $updateData['delivered_at'] = date('Y-m-d H:i:s');
            } elseif ($internalStatus === 'read') {
                $updateData['read_at'] = date('Y-m-d H:i:s');
                $updateData['delivered_at'] = $updateData['delivered_at'] ?? date('Y-m-d H:i:s');
            } elseif ($internalStatus === 'failed') {
                $errorDetail = $errorMsg ?? $description;
                if ($errorCode == 10) {
                    $errorDetail = "Janela de 24h expirada: o Instagram não permite responder após 24h sem interação do usuário. ({$errorMsg})";
                    self::logWarning("⚠️  INSTAGRAM JANELA 24H: " . $errorDetail);
                } else {
                    self::logError("Mensagem rejeitada — código: {$errorCode}, motivo: {$errorMsg}");
                }
                $updateData['error_message'] = substr($errorDetail, 0, 500);
            }

            Message::update($message['id'], $updateData);
            self::logInfo("MESSAGE_STATUS: Status da mensagem ID={$message['id']} atualizado para '{$internalStatus}'");

            // Notificar frontend via WebSocket sobre mudança de status
            try {
                \App\Helpers\WebSocket::notifyMessageStatusUpdated($message['conversation_id'], $message['id'], $internalStatus);
            } catch (\Exception $e) {
                self::logWarning("MESSAGE_STATUS: Erro ao notificar WebSocket: " . $e->getMessage());
            }
        } else {
            self::logInfo("MESSAGE_STATUS: Código '{$statusCode}' sem mapeamento interno — nenhuma ação no banco");
        }
    }

    /**
     * Encontrar conta por webhook
     */
    private static function findAccountByWebhook(array $payload, string $channel): ?array
    {
        // Helper para filtrar contas
        $all = IntegrationAccount::all();
        $filtered = function(callable $fn) use ($all) {
            $arr = array_values(array_filter($all, $fn));
            return !empty($arr) ? $arr[0] : null;
        };

        // 1) Por account_id no payload
        if (!empty($payload['account_id'])) {
            $acc = $filtered(function($a) use ($channel, $payload) {
                return ($a['provider'] ?? '') === 'notificame'
                    && ($a['channel'] ?? '') === $channel
                    && ($a['account_id'] ?? '') === $payload['account_id'];
            });
            if ($acc) return $acc;
        }

        // 2) Por subscriptionId (id do canal vindo no webhook)
        if (!empty($payload['subscriptionId'])) {
            $acc = $filtered(function($a) use ($channel, $payload) {
                return ($a['provider'] ?? '') === 'notificame'
                    && ($a['channel'] ?? '') === $channel
                    && ($a['account_id'] ?? '') === $payload['subscriptionId'];
            });
            if ($acc) return $acc;
        }

        // 3) Para WhatsApp, tentar por telefone
        if ($channel === 'whatsapp' && !empty($payload['from'])) {
            $phone = self::normalizePhoneNumber($payload['from']);
            $acc = IntegrationAccount::findByPhone($phone, 'whatsapp');
            if ($acc && ($acc['provider'] ?? '') === 'notificame') {
                return $acc;
            }
        }

        // 4) fallback: primeira ativa do canal/provider notificame
        $acc = $filtered(function($a) use ($channel) {
            return ($a['provider'] ?? '') === 'notificame'
                && ($a['channel'] ?? '') === $channel
                && ($a['status'] ?? '') === 'active';
        });

        return $acc;
    }
    
    /**
     * Extrair dados da mensagem do payload
     */
    private static function extractMessageData(array $payload, string $channel): ?array
    {
        $data = [
            'from' => null,
            'to' => null,
            'content' => '',
            'type' => 'text',
            'direction' => null,
            'external_id' => null,
            'name' => null,
            'avatar' => null,
            'metadata' => []
        ];
        
        // Estrutura padrão Notificame (exemplo: { message: { from, to, contents: [...] } })
        if (isset($payload['message'])) {
            $msg = $payload['message'];
            $data['from'] = $msg['from'] ?? $msg['sender'] ?? null;
            $data['to'] = $msg['to'] ?? null;
            $data['direction'] = $msg['direction'] ?? null;
            $data['external_id'] = $msg['id'] ?? $msg['message_id'] ?? null;
            $data['name'] = $msg['visitor']['name'] ?? $msg['visitor']['firstName'] ?? $msg['sender_name'] ?? null;
            $data['avatar'] = $msg['visitor']['picture'] ?? $msg['visitor']['avatar'] ?? null;
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
                } elseif (($contentItem['type'] ?? '') === 'comment') {
                    // 🔥 NOVO: Comentário do Instagram
                    $data['content'] = $contentItem['text'] ?? '';
                    $data['type'] = 'comment';
                    $data['metadata']['comment'] = $contentItem;
                    $data['metadata']['media'] = $contentItem['media'] ?? null;
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
            $data['to'] = $payload['to'] ?? null;
            $data['direction'] = $payload['direction'] ?? null;
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
     * Obter o token/ID do canal a partir da conta
     */
    private static function getChannelToken(array $account): string
    {
        $channelId = $account['account_id'] ?? '';
        if (empty($channelId)) {
            throw new \Exception("account_id (token do canal) não configurado para a conta #{$account['id']}. Configure o ID da conta nas configurações.");
        }
        return $channelId;
    }

    /**
     * Listar templates
     * Docs: GET https://api.notificame.com.br/v1/templates/{{token_do_canal}}
     */
    public static function listTemplates(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = self::getChannelToken($account);
        
        $endpoint = "templates/{$channelId}";
        
        try {
            self::logInfo("Listando templates Notificame - Account: {$accountId}, Endpoint: {$endpoint}");
            $result = self::makeRequest($endpoint, $token, 'GET', [], $apiUrl);
            return $result['templates'] ?? $result['data'] ?? [];
        } catch (\Exception $e) {
            self::logError("Erro ao listar templates Notificame: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Criar template conforme documentação NotificaMe
     * Docs: POST https://api.notificame.com.br/v1/templates/{{token_do_canal}}
     * Payload: {from, contents: [{template: {name, language, category, components: [...]}}]}
     */
    public static function createTemplate(int $accountId, array $templateData): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = self::getChannelToken($account);
        
        $endpoint = "templates/{$channelId}";
        
        $payload = self::buildTemplatePayload($channelId, $templateData);
        
        try {
            self::logInfo("Criando template Notificame - Account: {$accountId}, Endpoint: {$endpoint}");
            self::logInfo("Template payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            self::logInfo("Template criado com sucesso: " . json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        } catch (\Exception $e) {
            self::logError("Erro ao criar template Notificame: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Monta o payload conforme a documentação da API NotificaMe
     */
    private static function buildTemplatePayload(string $channelId, array $data): array
    {
        $name = $data['name'] ?? '';
        $language = $data['language'] ?? 'pt_BR';
        $category = strtoupper($data['category'] ?? 'UTILITY');
        $bodyText = $data['body_text'] ?? $data['body'] ?? '';
        $headerType = strtoupper($data['header_type'] ?? 'NONE');
        $headerText = $data['header_text'] ?? '';
        $footerText = $data['footer_text'] ?? '';
        $buttons = $data['buttons'] ?? [];

        if (empty($name)) {
            throw new \Exception('Nome do template é obrigatório');
        }
        if (empty($bodyText)) {
            throw new \Exception('Corpo da mensagem é obrigatório');
        }

        $components = [];

        // HEADER component
        if ($headerType !== 'NONE' && !empty($headerText)) {
            $headerComponent = [
                'type' => 'HEADER',
                'format' => $headerType,
            ];
            if ($headerType === 'TEXT') {
                $headerComponent['text'] = $headerText;
                $headerExamples = self::extractVariableExamples($headerText);
                if (!empty($headerExamples)) {
                    $headerComponent['example'] = ['header_text' => $headerExamples];
                }
            }
            $components[] = $headerComponent;
        } elseif ($headerType !== 'NONE' && $headerType !== 'TEXT') {
            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
            ];
        }

        // BODY component
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];
        $bodyExamples = self::extractVariableExamples($bodyText);
        if (!empty($bodyExamples)) {
            $bodyComponent['example'] = ['body_text' => $bodyExamples];
        }
        $components[] = $bodyComponent;

        // FOOTER component
        if (!empty($footerText)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $footerText,
            ];
        }

        // BUTTONS component
        if (!empty($buttons) && is_array($buttons)) {
            $buttonsList = [];
            foreach ($buttons as $btn) {
                $buttonDef = [
                    'type' => strtoupper($btn['type'] ?? 'QUICK_REPLY'),
                    'text' => $btn['text'] ?? '',
                ];
                if ($buttonDef['type'] === 'URL' && !empty($btn['url'])) {
                    $buttonDef['url'] = $btn['url'];
                }
                if ($buttonDef['type'] === 'PHONE_NUMBER' && !empty($btn['phone_number'])) {
                    $buttonDef['phone_number'] = $btn['phone_number'];
                }
                $buttonsList[] = $buttonDef;
            }
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttonsList,
            ];
        }

        return [
            'from' => $channelId,
            'contents' => [
                [
                    'template' => [
                        'name' => $name,
                        'language' => $language,
                        'category' => $category,
                        'components' => $components,
                    ]
                ]
            ]
        ];
    }

    /**
     * Extrai exemplos de variáveis {{1}}, {{2}} de um texto para o campo "example"
     */
    private static function extractVariableExamples(string $text): array
    {
        $examples = [];
        if (preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
            $placeholders = [
                1 => 'João', 2 => '12345', 3 => 'Exemplo', 4 => 'Valor',
                5 => 'Item', 6 => 'Data', 7 => 'Hora', 8 => 'Info',
            ];
            foreach ($matches[1] as $num) {
                $examples[] = $placeholders[(int)$num] ?? "Exemplo{$num}";
            }
        }
        return $examples;
    }

    /**
     * Atualizar template
     * Docs: PATCH/POST templates/{{token_do_canal}}
     */
    public static function updateTemplate(int $accountId, string $templateId, array $templateData): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = self::getChannelToken($account);
        
        $endpoint = "templates/{$channelId}";
        
        $payload = self::buildTemplatePayload($channelId, $templateData);
        
        try {
            self::logInfo("Atualizando template Notificame - Account: {$accountId}, TemplateId: {$templateId}");
            self::logInfo("Update payload: " . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $result = self::makeRequest($endpoint, $token, 'POST', $payload, $apiUrl);
            self::logInfo("Template atualizado: " . json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        } catch (\Exception $e) {
            self::logError("Erro ao atualizar template Notificame: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deletar template
     */
    public static function deleteTemplate(int $accountId, string $templateId): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account || $account['provider'] !== 'notificame') {
            throw new \Exception("Conta Notificame não encontrada: {$accountId}");
        }
        
        $token = $account['api_token'];
        $apiUrl = $account['api_url'] ?? null;
        $channelId = self::getChannelToken($account);
        
        $endpoint = "templates/{$channelId}/{$templateId}";
        
        try {
            self::logInfo("Deletando template Notificame - Account: {$accountId}, TemplateId: {$templateId}, Endpoint: {$endpoint}");
            self::makeRequest($endpoint, $token, 'DELETE', [], $apiUrl);
            self::logInfo("Template deletado com sucesso");
            return true;
        } catch (\Exception $e) {
            self::logError("Erro ao deletar template Notificame: " . $e->getMessage());
            throw $e;
        }
    }
}

