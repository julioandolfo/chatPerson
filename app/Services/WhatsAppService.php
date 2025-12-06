<?php
/**
 * Service WhatsAppService
 * Integração com Quepasa API (self-hosted) e Evolution API
 */

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Helpers\Logger;

class WhatsAppService
{
    /**
     * Criar conta WhatsApp
     */
    public static function createAccount(array $data): int
    {
        $errors = \App\Helpers\Validator::validate($data, [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
            'provider' => 'required|string|in:quepasa,evolution',
            'api_url' => 'required|string|max:500',
            'quepasa_user' => 'nullable|string|max:255',
            'quepasa_trackid' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'instance_id' => 'nullable|string|max:255'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se número já existe
        $existing = WhatsAppAccount::findByPhone($data['phone_number']);
        if ($existing) {
            throw new \InvalidArgumentException('Número já cadastrado');
        }

        $data['status'] = 'inactive';
        $data['config'] = json_encode($data['config'] ?? []);

        // Se for Quepasa e não tiver quepasa_user, usar um padrão
        if ($data['provider'] === 'quepasa' && empty($data['quepasa_user'])) {
            $data['quepasa_user'] = strtolower(str_replace(' ', '_', $data['name']));
        }

        // Se não tiver trackid, usar o nome da conta
        if (empty($data['quepasa_trackid'])) {
            $data['quepasa_trackid'] = $data['name'];
        }

        // Token obrigatória para identificar o servidor na Quepasa
        if (empty($data['quepasa_token'])) {
            $data['quepasa_token'] = self::generateQuepasaToken();
        }

        return WhatsAppAccount::create($data);
    }

    /**
     * Obter QR Code para conexão (Quepasa self-hosted)
     * Endpoint: POST /scan
     */
    public static function getQRCode(int $accountId): ?array
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            Logger::quepasa("getQRCode - Conta não encontrada: {$accountId}");
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        if ($account['provider'] !== 'quepasa') {
            Logger::quepasa("getQRCode - Provider inválido: {$account['provider']}");
            throw new \InvalidArgumentException('QR Code disponível apenas para Quepasa');
        }

        try {
            $apiUrl = rtrim($account['api_url'], '/');
            
            // Quepasa self-hosted: POST /scan
            $url = "{$apiUrl}/scan";
            
            $quepasaUser = $account['quepasa_user'] ?? 'default';
            $quepasaToken = $account['quepasa_token'] ?? '';
            
            // Garantir que exista um token registrado para esta conta
            if (empty($quepasaToken)) {
                $quepasaToken = self::generateQuepasaToken();
                WhatsAppAccount::update($accountId, ['quepasa_token' => $quepasaToken]);
                $account['quepasa_token'] = $quepasaToken;
            }

            // Montar headers (token deve ser enviado sempre que tivermos um)
            $headers = [
                'Accept: application/json',
                'X-QUEPASA-USER: ' . $quepasaUser,
                'X-QUEPASA-TOKEN: ' . $quepasaToken
            ];
            
            Logger::quepasa("getQRCode - Headers finais: " . json_encode($headers));
            Logger::quepasa("getQRCode - Header X-QUEPASA-TOKEN: '" . ($headers[2] ?? 'não definido') . "'");
            
            Logger::quepasa("getQRCode - Iniciando requisição");
            Logger::quepasa("getQRCode - URL: {$url}");
            Logger::quepasa("getQRCode - Headers: " . json_encode($headers));
            Logger::quepasa("getQRCode - Account ID: {$accountId}");
            Logger::quepasa("getQRCode - Quepasa User: {$quepasaUser}");
            Logger::quepasa("getQRCode - Token presente: " . (!empty($quepasaToken) ? 'Sim (' . substr($quepasaToken, 0, 20) . '...)' : 'Não (vazio)'));
            
            // Conforme exemplo do curl fornecido: POST com --data ''
            // Mas os logs anteriores mostraram que GET funciona (POST retorna 405)
            // Tentar POST primeiro (conforme documentação), se falhar, tentar GET
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => '', // --data '' conforme exemplo
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_VERBOSE => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            $error = curl_error($ch);
            $errorNo = curl_errno($ch);
            
            Logger::quepasa("getQRCode - Tentativa POST - HTTP Code: {$httpCode}");
            
            // Se POST retornar 405, tentar GET
            if ($httpCode === 405) {
                Logger::quepasa("getQRCode - POST retornou 405, tentando GET");
                curl_close($ch);
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTPGET => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_VERBOSE => false
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
                $error = curl_error($ch);
                $errorNo = curl_errno($ch);
                
                Logger::quepasa("getQRCode - Tentativa GET - HTTP Code: {$httpCode}");
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
            $error = curl_error($ch);
            $errorNo = curl_errno($ch);
            
            Logger::quepasa("getQRCode - Tentativa GET - HTTP Code: {$httpCode}");
            Logger::quepasa("getQRCode - Effective URL: {$effectiveUrl}");
            Logger::quepasa("getQRCode - Redirect Count: {$redirectCount}");
            Logger::quepasa("getQRCode - Error No: {$errorNo}");
            Logger::quepasa("getQRCode - Error: " . ($error ?: 'Nenhum'));
            Logger::quepasa("getQRCode - Response Length: " . strlen($response));
            Logger::quepasa("getQRCode - Response Preview: " . substr($response, 0, 500));
            
            curl_close($ch);

            if ($error) {
                Logger::quepasa("getQRCode - Erro cURL: {$error} (Code: {$errorNo})");
                Logger::error("WhatsApp QR Code Error: {$error}");
                throw new \Exception("Erro ao obter QR Code: {$error}");
            }

            // Se for redirecionamento, logar
            if ($httpCode >= 300 && $httpCode < 400) {
                Logger::quepasa("getQRCode - Redirecionamento detectado: HTTP {$httpCode}");
                Logger::quepasa("getQRCode - URL original: {$url}");
                Logger::quepasa("getQRCode - URL efetiva: {$effectiveUrl}");
                
                if ($redirectCount > 0 && $httpCode !== 200) {
                    Logger::quepasa("getQRCode - Redirecionamento seguido mas ainda com erro HTTP {$httpCode}");
                    throw new \Exception("Erro ao obter QR Code após redirecionamento (HTTP {$httpCode}). URL efetiva: {$effectiveUrl}");
                }
            }

            if ($httpCode !== 200) {
                Logger::quepasa("getQRCode - HTTP Error {$httpCode}: {$response}");
                Logger::error("WhatsApp QR Code HTTP {$httpCode}: {$response}");
                throw new \Exception("Erro ao obter QR Code (HTTP {$httpCode}). URL: {$effectiveUrl}. Resposta: " . substr($response, 0, 200));
            }

            // A API retorna imagem PNG, não JSON
            $base64 = 'data:image/png;base64,' . base64_encode($response);
            Logger::quepasa("getQRCode - PNG recebido com " . strlen($response) . " bytes");

            return [
                'qrcode' => $base64,
                'base64' => $base64,
                'expires_in' => 60
            ];
        } catch (\Exception $e) {
            Logger::quepasa("getQRCode - Exception: " . $e->getMessage());
            Logger::quepasa("getQRCode - Stack trace: " . $e->getTraceAsString());
            Logger::error("WhatsApp getQRCode Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar status da conexão
     */
    public static function getConnectionStatus(int $accountId): array
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        // Se tiver chatid, provavelmente está conectado
        if (!empty($account['quepasa_chatid'])) {
            return [
                'connected' => true,
                'status' => 'connected',
                'phone_number' => $account['phone_number'],
                'chatid' => $account['quepasa_chatid'],
                'message' => 'Conectado'
            ];
        }

        return [
            'connected' => false,
            'status' => 'disconnected',
            'message' => 'Não conectado - escaneie o QR Code'
        ];
    }

    /**
     * Desconectar WhatsApp
     */
    public static function disconnect(int $accountId): bool
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        // Limpar token e chatid no banco
        WhatsAppAccount::update($accountId, [
            'status' => 'disconnected',
            'quepasa_token' => null,
            'quepasa_chatid' => null
        ]);

        return true;
    }

    /**
     * Enviar mensagem via WhatsApp (Quepasa self-hosted)
     * Endpoint: POST /send
     */
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            Logger::quepasa("sendMessage - Conta não encontrada: {$accountId}");
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        if (empty($account['quepasa_token'])) {
            Logger::quepasa("sendMessage - Token não encontrado para conta {$accountId}");
            throw new \Exception('Conta não está conectada. Escaneie o QR Code primeiro.');
        }

        try {
            $apiUrl = rtrim($account['api_url'], '/');
            
            if ($account['provider'] === 'quepasa') {
                // Quepasa self-hosted: POST /send
                $url = "{$apiUrl}/send";
                
                $headers = [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-QUEPASA-TOKEN: ' . $account['quepasa_token'],
                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name']),
                    'X-QUEPASA-CHATID: ' . ($to . '@s.whatsapp.net')
                ];
                
                $payload = [
                    'text' => $message
                ];
                
                if (!empty($options['media_url'])) {
                    $payload['media'] = [
                        'url' => $options['media_url'],
                        'type' => $options['media_type'] ?? 'image'
                    ];
                }
                
                Logger::quepasa("sendMessage - Iniciando envio");
                Logger::quepasa("sendMessage - URL: {$url}");
                Logger::quepasa("sendMessage - To: {$to}");
                Logger::quepasa("sendMessage - Payload: " . json_encode($payload));
            } else {
                throw new \InvalidArgumentException('Provider não suportado');
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $error = curl_error($ch);
            curl_close($ch);

            Logger::quepasa("sendMessage - HTTP Code: {$httpCode}");
            Logger::quepasa("sendMessage - Effective URL: {$effectiveUrl}");
            Logger::quepasa("sendMessage - Response: " . substr($response, 0, 500));

            if ($error) {
                Logger::quepasa("sendMessage - Erro cURL: {$error}");
                Logger::error("WhatsApp sendMessage Error: {$error}");
                throw new \Exception("Erro ao enviar mensagem: {$error}");
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                Logger::quepasa("sendMessage - HTTP Error {$httpCode}: {$response}");
                Logger::error("WhatsApp sendMessage HTTP {$httpCode}: {$response}");
                throw new \Exception("Erro ao enviar mensagem (HTTP {$httpCode}): {$response}");
            }

            $data = json_decode($response, true);
            
            Logger::quepasa("sendMessage - Mensagem enviada com sucesso");
            
            return [
                'success' => true,
                'message_id' => $data['id'] ?? $data['message_id'] ?? null,
                'status' => $data['status'] ?? 'sent'
            ];
        } catch (\Exception $e) {
            Logger::quepasa("sendMessage - Exception: " . $e->getMessage());
            Logger::error("WhatsApp sendMessage Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Configurar webhook no Quepasa
     * Endpoint: POST /webhook
     */
    public static function configureWebhook(int $accountId, string $webhookUrl, array $options = []): bool
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            Logger::quepasa("configureWebhook - Conta não encontrada: {$accountId}");
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        if (empty($account['quepasa_token'])) {
            Logger::quepasa("configureWebhook - Token não encontrado para conta {$accountId}");
            throw new \Exception('Conta não está conectada. Escaneie o QR Code primeiro.');
        }

        try {
            $apiUrl = rtrim($account['api_url'], '/');
            $url = "{$apiUrl}/webhook";
            
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-QUEPASA-TOKEN: ' . $account['quepasa_token']
            ];
            
            $payload = [
                'url' => $webhookUrl,
                'forwardinternal' => $options['forwardinternal'] ?? true,
                'trackid' => $account['quepasa_trackid'] ?? $account['name'],
                'extra' => $options['extra'] ?? []
            ];

            Logger::quepasa("configureWebhook - Iniciando configuração");
            Logger::quepasa("configureWebhook - URL: {$url}");
            Logger::quepasa("configureWebhook - Webhook URL: {$webhookUrl}");
            Logger::quepasa("configureWebhook - Payload: " . json_encode($payload));

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $error = curl_error($ch);
            curl_close($ch);

            Logger::quepasa("configureWebhook - HTTP Code: {$httpCode}");
            Logger::quepasa("configureWebhook - Effective URL: {$effectiveUrl}");
            Logger::quepasa("configureWebhook - Response: " . substr($response, 0, 500));

            if ($error) {
                Logger::quepasa("configureWebhook - Erro cURL: {$error}");
                Logger::error("WhatsApp configureWebhook Error: {$error}");
                throw new \Exception("Erro ao configurar webhook: {$error}");
            }

            if ($httpCode !== 200 && $httpCode !== 201) {
                Logger::quepasa("configureWebhook - HTTP Error {$httpCode}: {$response}");
                Logger::error("WhatsApp configureWebhook HTTP {$httpCode}: {$response}");
                throw new \Exception("Erro ao configurar webhook (HTTP {$httpCode}): {$response}");
            }

            Logger::quepasa("configureWebhook - Webhook configurado com sucesso");
            Logger::log("Webhook configurado para conta {$accountId}: {$webhookUrl}");
            return true;
        } catch (\Exception $e) {
            Logger::quepasa("configureWebhook - Exception: " . $e->getMessage());
            Logger::error("WhatsApp configureWebhook Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Processar webhook de mensagem recebida
     */
    public static function processWebhook(array $payload): void
    {
        try {
            // Identificar conta pelo trackid ou chatid
            $trackid = $payload['trackid'] ?? null;
            $chatid = $payload['chatid'] ?? null;
            $from = $payload['from'] ?? $payload['phone'] ?? null;
            
            if (!$trackid && !$chatid && !$from) {
                Logger::error("WhatsApp webhook sem identificação: " . json_encode($payload));
                return;
            }

            // Buscar conta
            $account = null;
            if ($trackid) {
                $accounts = WhatsAppAccount::where('quepasa_trackid', '=', $trackid);
                $account = !empty($accounts) ? $accounts[0] : null;
            }
            
            if (!$account && $chatid) {
                $accounts = WhatsAppAccount::where('quepasa_chatid', '=', $chatid);
                $account = !empty($accounts) ? $accounts[0] : null;
            }
            
            if (!$account && $from) {
                // Remover @s.whatsapp.net se presente
                $phone = str_replace('@s.whatsapp.net', '', $from);
                $account = WhatsAppAccount::findByPhone($phone);
            }

            if (!$account) {
                Logger::error("WhatsApp webhook: conta não encontrada (trackid: {$trackid}, chatid: {$chatid}, from: {$from})");
                return;
            }

            // Processar mensagem recebida
            $fromPhone = $payload['from'] ?? $payload['phone'] ?? null;
            if ($fromPhone) {
                $fromPhone = str_replace('@s.whatsapp.net', '', $fromPhone);
            }
            
            $message = $payload['text'] ?? $payload['message'] ?? $payload['caption'] ?? '';
            $messageId = $payload['id'] ?? $payload['message_id'] ?? null;
            $timestamp = $payload['timestamp'] ?? time();
            
            // Processar tipo de mensagem e metadados
            $messageType = $payload['type'] ?? 'text';
            $isGroup = $payload['isGroup'] ?? false;
            $groupName = $payload['groupName'] ?? null;
            $forwarded = $payload['forwarded'] ?? false;
            $quotedMessageId = $payload['quoted'] ?? $payload['quoted_message_id'] ?? null;
            $quotedMessageText = $payload['quoted_text'] ?? null;
            
            // Processar anexos/mídia do WhatsApp (será processado depois de criar a conversa)
            $mediaUrl = $payload['media_url'] ?? $payload['mediaUrl'] ?? $payload['url'] ?? null;
            $mediaType = $payload['media_type'] ?? $messageType;
            $filename = $payload['filename'] ?? $payload['media_name'] ?? null;
            $mimetype = $payload['mimetype'] ?? $payload['mime_type'] ?? null;
            $size = $payload['size'] ?? null;
            
            // Processar localização se houver
            $location = null;
            if (isset($payload['location'])) {
                $location = [
                    'latitude' => $payload['location']['latitude'] ?? $payload['latitude'] ?? null,
                    'longitude' => $payload['location']['longitude'] ?? $payload['longitude'] ?? null,
                    'name' => $payload['location']['name'] ?? $payload['location_name'] ?? null,
                    'address' => $payload['location']['address'] ?? $payload['location_address'] ?? null
                ];
            }
            
            // Se não tem texto mas tem caption, usar caption como conteúdo
            if (empty($message) && !empty($payload['caption'])) {
                $message = $payload['caption'];
            }

            if (!$fromPhone || (empty($message) && !$mediaUrl)) {
                Logger::error("WhatsApp webhook: dados incompletos (sem telefone ou conteúdo)");
                return;
            }

            // Criar ou atualizar contato
            $contact = \App\Models\Contact::findByPhone($fromPhone);
            if (!$contact) {
                $whatsappId = ($payload['from'] ?? $payload['phone'] ?? $fromPhone);
                if (!str_ends_with($whatsappId, '@s.whatsapp.net')) {
                    $whatsappId .= '@s.whatsapp.net';
                }
                
                $contactId = \App\Models\Contact::create([
                    'name' => $fromPhone,
                    'phone' => $fromPhone,
                    'whatsapp_id' => $whatsappId,
                    'email' => null
                ]);
                $contact = \App\Models\Contact::find($contactId);
                
                // Tentar buscar avatar do WhatsApp
                try {
                    \App\Services\ContactService::fetchWhatsAppAvatar($contact['id'], $account['id']);
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao buscar avatar: " . $e->getMessage());
                }
            } elseif (empty($contact['avatar'])) {
                // Se contato existe mas não tem avatar, tentar buscar
                try {
                    \App\Services\ContactService::fetchWhatsAppAvatar($contact['id'], $account['id']);
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao buscar avatar: " . $e->getMessage());
                }
            }

            // Criar ou buscar conversa
            $conversation = \App\Models\Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
            $isNewConversation = false;
            
            if (!$conversation) {
                // Usar ConversationService para criar conversa (com todas as integrações)
                try {
                    $conversation = \App\Services\ConversationService::create([
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'whatsapp_account_id' => $account['id']
                    ]);
                    $isNewConversation = true;
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao criar conversa via ConversationService: " . $e->getMessage());
                    // Fallback: criar diretamente se ConversationService falhar
                    $conversationId = \App\Models\Conversation::create([
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'whatsapp_account_id' => $account['id'],
                        'status' => 'open'
                    ]);
                    $conversation = \App\Models\Conversation::find($conversationId);
                }
            }

            // Processar anexos/mídia do WhatsApp agora que temos a conversa
            $attachments = [];
            if ($mediaUrl) {
                try {
                    $attachment = \App\Services\AttachmentService::saveFromUrl(
                        $mediaUrl, 
                        $conversation['id'], 
                        $payload['filename'] ?? $payload['media_name'] ?? null
                    );
                    $attachments[] = $attachment;
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao salvar mídia do WhatsApp: " . $e->getMessage());
                }
            }

            // Criar mensagem usando ConversationService (com todas as integrações)
            try {
                $messageId = \App\Services\ConversationService::sendMessage(
                    $conversation['id'],
                    $message ?: '',
                    'contact',
                    $contact['id'],
                    $attachments
                );
                
                // Se mensagem foi criada com sucesso, automações já foram executadas pelo ConversationService
                // Não precisa chamar AutomationService novamente
            } catch (\Exception $e) {
                Logger::quepasa("Erro ao criar mensagem via ConversationService: " . $e->getMessage());
                // Fallback: criar mensagem diretamente se ConversationService falhar
                $messageData = [
                    'conversation_id' => $conversation['id'],
                    'sender_type' => 'contact',
                    'sender_id' => $contact['id'],
                    'content' => $message ?: '',
                    'message_type' => !empty($attachments) ? ($attachments[0]['type'] ?? 'text') : 'text',
                    'external_id' => $messageId
                ];
                
                if (!empty($attachments)) {
                    $messageData['attachments'] = $attachments;
                }
                
                $messageId = \App\Models\Message::createMessage($messageData);
                
                // Disparar automações manualmente (fallback)
                try {
                    \App\Services\AutomationService::executeForMessageReceived($messageId);
                } catch (\Exception $autoError) {
                    Logger::quepasa("Erro ao executar automações: " . $autoError->getMessage());
                }
            }

            Logger::log("WhatsApp mensagem processada: {$fromPhone} -> {$message}");
        } catch (\Exception $e) {
            Logger::error("WhatsApp processWebhook Error: " . $e->getMessage());
        }
    }

    /**
     * Processar webhook de status de mensagem (delivered, read, failed)
     */
    public static function processStatusWebhook(array $payload): void
    {
        try {
            $messageId = $payload['id'] ?? $payload['message_id'] ?? null;
            $status = $payload['status'] ?? null;
            $errorMessage = $payload['error'] ?? $payload['error_message'] ?? null;
            $timestamp = $payload['timestamp'] ?? time();
            
            if (!$messageId || !$status) {
                Logger::quepasa("processStatusWebhook - Dados incompletos: " . json_encode($payload));
                return;
            }

            // Buscar mensagem pelo external_id (ID do WhatsApp)
            $message = \App\Models\Message::findByExternalId($messageId);
            
            if (!$message) {
                Logger::quepasa("processStatusWebhook - Mensagem não encontrada: {$messageId}");
                return;
            }

            // Converter timestamp para datetime se necessário
            $deliveredAt = null;
            $readAt = null;
            
            if ($status === 'delivered') {
                $deliveredAt = isset($payload['delivered_at']) 
                    ? date('Y-m-d H:i:s', strtotime($payload['delivered_at']))
                    : date('Y-m-d H:i:s', $timestamp);
            } elseif ($status === 'read') {
                $readAt = isset($payload['read_at'])
                    ? date('Y-m-d H:i:s', strtotime($payload['read_at']))
                    : date('Y-m-d H:i:s', $timestamp);
            }

            // Atualizar status da mensagem
            \App\Models\Message::updateStatus(
                $message['id'],
                $status,
                $errorMessage,
                $deliveredAt,
                $readAt
            );

            // Notificar via WebSocket se necessário
            try {
                \App\Helpers\WebSocket::notifyMessageStatusUpdated(
                    $message['conversation_id'],
                    $message['id'],
                    $status
                );
            } catch (\Exception $e) {
                Logger::quepasa("Erro ao notificar WebSocket: " . $e->getMessage());
            }

            Logger::quepasa("processStatusWebhook - Status atualizado: {$messageId} -> {$status}");
        } catch (\Exception $e) {
            Logger::error("WhatsApp processStatusWebhook Error: " . $e->getMessage());
        }
    }

    /**
     * Gera um token seguro para identificar a conexão na Quepasa
     */
    protected static function generateQuepasaToken(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            // Fallback caso random_bytes não esteja disponível
            return bin2hex(openssl_random_pseudo_bytes(16));
        }
    }
}
