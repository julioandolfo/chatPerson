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
                Logger::quepasa("getQRCode - Effective URL: {$effectiveUrl}");
                Logger::quepasa("getQRCode - Redirect Count: {$redirectCount}");
                Logger::quepasa("getQRCode - Error No: {$errorNo}");
                Logger::quepasa("getQRCode - Error: " . ($error ?: 'Nenhum'));
                Logger::quepasa("getQRCode - Response Length: " . strlen($response));
                Logger::quepasa("getQRCode - Response Preview: " . substr($response, 0, 500));
                
                curl_close($ch);
            } else {
                // POST funcionou, já temos a resposta
                Logger::quepasa("getQRCode - POST bem-sucedido - HTTP Code: {$httpCode}");
                Logger::quepasa("getQRCode - Effective URL: {$effectiveUrl}");
                Logger::quepasa("getQRCode - Response Length: " . strlen($response));
                curl_close($ch);
            }

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
            // Verificar se a resposta é realmente uma imagem PNG
            if (empty($response) || strlen($response) < 100) {
                Logger::quepasa("getQRCode - Resposta muito pequena ou vazia: " . strlen($response) . " bytes");
                throw new \Exception('QR Code inválido ou vazio recebido da API');
            }
            
            // Verificar se começa com PNG signature
            $pngSignature = substr($response, 0, 8);
            $expectedSignature = "\x89PNG\r\n\x1a\n";
            
            if ($pngSignature !== $expectedSignature) {
                Logger::quepasa("getQRCode - Assinatura PNG inválida. Recebido: " . bin2hex(substr($response, 0, 8)));
                // Mesmo assim, tentar converter para base64 (pode ser que a API retorne diferente)
            }
            
            $base64 = base64_encode($response);
            if (empty($base64)) {
                Logger::quepasa("getQRCode - Erro ao codificar base64");
                throw new \Exception('Erro ao processar QR Code');
            }
            
            $base64DataUri = 'data:image/png;base64,' . $base64;
            Logger::quepasa("getQRCode - PNG recebido com " . strlen($response) . " bytes, base64: " . strlen($base64) . " caracteres");

            return [
                'qrcode' => $base64DataUri,
                'base64' => $base64DataUri,
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
     * Verifica no banco e também tenta consultar a API Quepasa diretamente
     */
    public static function getConnectionStatus(int $accountId): array
    {
        $account = WhatsAppAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        // Se tiver chatid no banco, está conectado
        if (!empty($account['quepasa_chatid'])) {
            // Verificar se precisa configurar webhook (se não foi configurado ainda)
            // Verificar se há uma flag indicando que o webhook já foi configurado
            $config = json_decode($account['config'] ?? '{}', true);
            $webhookConfigured = $config['webhook_configured'] ?? false;
            
            // Se o webhook não foi configurado ainda, tentar configurar
            if (!$webhookConfigured) {
                try {
                    self::configureWebhookAutomatically($accountId);
                } catch (\Exception $e) {
                    Logger::quepasa("getConnectionStatus - Erro ao configurar webhook automaticamente: " . $e->getMessage());
                    // Não falhar a conexão se o webhook não puder ser configurado
                }
            }
            
            // Atualizar status para active se ainda não estiver
            if ($account['status'] !== 'active') {
                WhatsAppAccount::update($accountId, ['status' => 'active']);
            }
            
            return [
                'connected' => true,
                'status' => 'connected',
                'phone_number' => $account['phone_number'],
                'chatid' => $account['quepasa_chatid'],
                'message' => 'Conectado'
            ];
        }

        // Se for Quepasa, tentar verificar status diretamente na API
        if ($account['provider'] === 'quepasa' && !empty($account['quepasa_token'])) {
            try {
                $apiUrl = rtrim($account['api_url'], '/');
                
                // Tentar diferentes endpoints para verificar status
                $endpoints = ['/status', '/info', '/me', '/scan'];
                
                foreach ($endpoints as $endpoint) {
                    try {
                        $url = "{$apiUrl}{$endpoint}";
                        
                        $headers = [
                            'Accept: application/json',
                            'X-QUEPASA-USER: ' . ($account['quepasa_user'] ?? 'default'),
                            'X-QUEPASA-TOKEN: ' . $account['quepasa_token']
                        ];
                        if (!empty($account['quepasa_trackid'])) {
                            $headers[] = 'X-QUEPASA-TRACKID: ' . $account['quepasa_trackid'];
                        }
                        
                        $ch = curl_init($url);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 3,
                            CURLOPT_HTTPGET => true, // Tentar GET primeiro
                            CURLOPT_HTTPHEADER => $headers,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_FOLLOWLOCATION => true
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        Logger::quepasa("getConnectionStatus - {$endpoint} HTTP {$httpCode}");
                        
                        // Se retornar 200 e for JSON, verificar se tem chatid
                        if ($httpCode === 200 && !empty($response)) {
                            $jsonResponse = @json_decode($response, true);
                            if ($jsonResponse !== null) {
                                Logger::quepasa("getConnectionStatus - {$endpoint} response: " . substr($response, 0, 400));

                                // Preferência: pegar chatid ou wid quando existir
                                $chatid = $jsonResponse['chatid'] ?? $jsonResponse['chat_id'] ?? $jsonResponse['id'] ?? null;

                                // Em algumas respostas (/info) vem dentro de server.wid
                                if (!$chatid && isset($jsonResponse['server']['wid'])) {
                                    $chatid = $jsonResponse['server']['wid'];
                                }

                                // Se vier wid com sufixo após ":", manter completo; ainda é identificador válido

                                if ($chatid) {
                                    // Chatid encontrado - atualizar no banco
                                    $wasConnected = !empty($account['quepasa_chatid']); // Verificar se já estava conectado antes
                                    
                                    WhatsAppAccount::update($accountId, [
                                        'quepasa_chatid' => $chatid,
                                        'status' => 'active'
                                    ]);
                                    
                                    Logger::quepasa("getConnectionStatus - Chatid/WID encontrado via {$endpoint}: {$chatid}");
                                    
                                    // Se não estava conectado antes, configurar webhook automaticamente
                                    if (!$wasConnected) {
                                        try {
                                            self::configureWebhookAutomatically($accountId);
                                        } catch (\Exception $e) {
                                            Logger::quepasa("getConnectionStatus - Erro ao configurar webhook automaticamente: " . $e->getMessage());
                                            // Não falhar a conexão se o webhook não puder ser configurado
                                        }
                                    }
                                    
                                    return [
                                        'connected' => true,
                                        'status' => 'connected',
                                        'phone_number' => $account['phone_number'],
                                        'chatid' => $chatid,
                                        'message' => 'Conectado'
                                    ];
                                }
                                
                                // Verificar se tem status "connected" ou similar
                                $status = $jsonResponse['status'] ?? $jsonResponse['state'] ?? null;
                                if ($status === 'connected' || $status === 'ready' || $status === 'authenticated' || $status === 'follow server information') {
                                    // Está conectado mas não temos chatid ainda - marcar active e tentar webhook
                                    Logger::quepasa("getConnectionStatus - Status conectado via {$endpoint} mas sem chatid");
                                    
                                    // Atualizar status para active mesmo sem chatid
                                    WhatsAppAccount::update($accountId, ['status' => 'active']);
                                    
                                    // Tentar configurar webhook automaticamente
                                    try {
                                        self::configureWebhookAutomatically($accountId);
                                    } catch (\Exception $e) {
                                        Logger::quepasa("getConnectionStatus - Erro ao configurar webhook automaticamente (sem chatid): " . $e->getMessage());
                                    }
                                    
                                    return [
                                        'connected' => true,
                                        'status' => 'connected',
                                        'phone_number' => $account['phone_number'],
                                        'chatid' => $account['quepasa_chatid'] ?? null,
                                        'message' => 'Conectado (aguardando chatid)'
                                    ];
                                }
                            } else {
                                Logger::quepasa("getConnectionStatus - {$endpoint} resposta não JSON: " . substr($response, 0, 200));
                            }
                        }
                    } catch (\Exception $e) {
                        // Continuar para próximo endpoint
                        continue;
                    }
                }
            } catch (\Exception $e) {
                // Ignorar erros na consulta à API e continuar com verificação do banco
                Logger::quepasa("getConnectionStatus - Erro ao consultar API: " . $e->getMessage());
            }
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
     * Configurar webhook automaticamente após conexão
     */
    private static function configureWebhookAutomatically(int $accountId): void
    {
        // Obter URL do webhook (da configuração ou gerar automaticamente)
        $webhookUrl = self::getWebhookUrl();
        
        if (empty($webhookUrl)) {
            Logger::quepasa("configureWebhookAutomatically - URL do webhook não configurada, pulando configuração automática");
            return;
        }
        
        try {
            // Configurar webhook
            self::configureWebhook($accountId, $webhookUrl, [
                'forwardinternal' => true
            ]);
            
            // Marcar como configurado no banco
            $account = WhatsAppAccount::find($accountId);
            if ($account) {
                $config = json_decode($account['config'] ?? '{}', true);
                $config['webhook_configured'] = true;
                $config['webhook_url'] = $webhookUrl;
                WhatsAppAccount::update($accountId, ['config' => json_encode($config)]);
            }
            
            Logger::quepasa("configureWebhookAutomatically - Webhook configurado automaticamente para conta {$accountId}: {$webhookUrl}");
        } catch (\Exception $e) {
            Logger::quepasa("configureWebhookAutomatically - Erro: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Obter URL do webhook (da configuração ou gerar automaticamente)
     */
    private static function getWebhookUrl(): string
    {
        // Tentar obter da configuração primeiro
        $webhookUrl = \App\Services\SettingService::get('whatsapp_webhook_url', '');
        
        if (!empty($webhookUrl)) {
            return $webhookUrl;
        }
        
        // Se não tiver configuração, gerar automaticamente baseado na URL atual
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $basePath = \App\Helpers\Url::basePath();
        
        $webhookUrl = "{$protocol}://{$host}{$basePath}/whatsapp-webhook";
        
        return $webhookUrl;
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
            // Identificar conta pelo trackid, chatid, wid ou phone
            $trackid = $payload['trackid'] ?? null;
            $chatid = $payload['chatid'] ?? $payload['wid'] ?? null; // wid é o WhatsApp ID do bot
            $from = $payload['from'] ?? $payload['phone'] ?? null;
            
            // Formato novo do Quepasa: chat.id ou chat.phone
            if (!$from && isset($payload['chat'])) {
                $from = $payload['chat']['id'] ?? $payload['chat']['phone'] ?? null;
            }
            
            // Se ainda não tem from, tentar extrair do chat.id
            if (!$from && isset($payload['chat']['id'])) {
                $from = $payload['chat']['id'];
            }
            
            // Extrair número de telefone do from (remover @s.whatsapp.net)
            $fromPhone = null;
            if ($from) {
                $fromPhone = str_replace('@s.whatsapp.net', '', $from);
                // Remover + se presente
                $fromPhone = ltrim($fromPhone, '+');
            }
            
            Logger::quepasa("processWebhook - Payload recebido: trackid={$trackid}, chatid={$chatid}, from={$from}, fromPhone={$fromPhone}");
            
            if (!$trackid && !$chatid && !$fromPhone) {
                Logger::error("WhatsApp webhook sem identificação: " . json_encode($payload));
                return;
            }

            // Buscar conta
            $account = null;
            
            // 1. Tentar por trackid
            if ($trackid) {
                $accounts = WhatsAppAccount::where('quepasa_trackid', '=', $trackid);
                $account = !empty($accounts) ? $accounts[0] : null;
                if ($account) {
                    Logger::quepasa("processWebhook - Conta encontrada por trackid: {$trackid}");
                }
            }
            
            // 2. Tentar por chatid/wid (WhatsApp ID do bot)
            if (!$account && $chatid) {
                // Remover @s.whatsapp.net se presente
                $chatidClean = str_replace('@s.whatsapp.net', '', $chatid);
                // Extrair número antes dos dois pontos (ex: "553591970289:86@s.whatsapp.net" -> "553591970289")
                $chatidNumber = explode(':', $chatidClean)[0];
                
                $accounts = WhatsAppAccount::where('quepasa_chatid', 'LIKE', $chatidNumber . '%');
                if (empty($accounts)) {
                    // Tentar buscar pelo número do WhatsApp
                    $account = WhatsAppAccount::findByPhone($chatidNumber);
                } else {
                    $account = $accounts[0];
                }
                
                if ($account) {
                    Logger::quepasa("processWebhook - Conta encontrada por chatid/wid: {$chatid}");
                }
            }
            
            // 3. Tentar por wid do payload (WhatsApp ID do bot que recebeu)
            if (!$account && isset($payload['wid'])) {
                $widClean = str_replace('@s.whatsapp.net', '', $payload['wid']);
                $widNumber = explode(':', $widClean)[0];
                $account = WhatsAppAccount::findByPhone($widNumber);
                if ($account) {
                    Logger::quepasa("processWebhook - Conta encontrada por wid: {$payload['wid']}");
                }
            }
            
            // 4. Tentar por número do remetente (último recurso - pode não ser confiável)
            if (!$account && $fromPhone) {
                // Buscar todas as contas WhatsApp ativas e tentar encontrar pela conta que recebeu
                // Como não sabemos qual conta recebeu, vamos tentar todas
                $allAccounts = WhatsAppAccount::where('status', '=', 'active');
                foreach ($allAccounts as $acc) {
                    // Verificar se o wid da conta corresponde ao wid do payload
                    if (!empty($acc['quepasa_chatid'])) {
                        $accWid = str_replace('@s.whatsapp.net', '', $acc['quepasa_chatid']);
                        $accWidNumber = explode(':', $accWid)[0];
                        if (isset($payload['wid'])) {
                            $payloadWid = str_replace('@s.whatsapp.net', '', $payload['wid']);
                            $payloadWidNumber = explode(':', $payloadWid)[0];
                            if ($accWidNumber === $payloadWidNumber) {
                                $account = $acc;
                                Logger::quepasa("processWebhook - Conta encontrada por comparação de wid: {$acc['id']}");
                                break;
                            }
                        }
                    }
                }
            }

            if (!$account) {
                Logger::error("WhatsApp webhook: conta não encontrada (trackid: {$trackid}, chatid: {$chatid}, wid: " . ($payload['wid'] ?? 'N/A') . ", from: {$from})");
                Logger::error("WhatsApp webhook: Tentando buscar conta pelo número do wid...");
                // Última tentativa: buscar pelo número do wid
                if (isset($payload['wid'])) {
                    $widNumber = explode(':', $payload['wid'])[0];
                    $widNumber = str_replace('@s.whatsapp.net', '', $widNumber);
                    Logger::error("WhatsApp webhook: Número extraído do wid: {$widNumber}");
                    $account = WhatsAppAccount::findByPhone($widNumber);
                    if ($account) {
                        Logger::quepasa("processWebhook - Conta encontrada por número do wid: {$widNumber}");
                    }
                }
                
                if (!$account) {
                    Logger::error("WhatsApp webhook: CONTA NÃO ENCONTRADA - Verifique se o número do WhatsApp está correto no cadastro");
                    return;
                }
            }
            
            Logger::quepasa("processWebhook - Conta encontrada: ID={$account['id']}, Nome={$account['name']}, Phone={$account['phone_number']}");

            // Atualizar chatid/wid se necessário
            $wid = $payload['wid'] ?? null;
            if ($wid && empty($account['quepasa_chatid'])) {
                Logger::quepasa("processWebhook - Atualizando chatid da conta {$account['id']}: {$wid}");
                WhatsAppAccount::update($account['id'], [
                    'quepasa_chatid' => $wid,
                    'status' => 'active'
                ]);
                $account['quepasa_chatid'] = $wid;
                $account['status'] = 'active';
                
                // Se acabou de conectar, tentar configurar webhook automaticamente
                try {
                    self::configureWebhookAutomatically($account['id']);
                } catch (\Exception $e) {
                    Logger::quepasa("processWebhook - Erro ao configurar webhook automaticamente: " . $e->getMessage());
                }
            } elseif ($wid && $account['quepasa_chatid'] !== $wid) {
                Logger::quepasa("processWebhook - Chatid mudou para conta {$account['id']}: {$wid}");
                WhatsAppAccount::update($account['id'], [
                    'quepasa_chatid' => $wid,
                    'status' => 'active'
                ]);
            }

            // Processar mensagem recebida
            // Formato novo do Quepasa: chat.id ou chat.phone
            $from = $payload['from'] ?? $payload['phone'] ?? null;
            if (!$from && isset($payload['chat'])) {
                $from = $payload['chat']['id'] ?? $payload['chat']['phone'] ?? null;
            }
            
            $fromPhone = null;
            if ($from) {
                $fromPhone = str_replace('@s.whatsapp.net', '', $from);
                $fromPhone = ltrim($fromPhone, '+'); // Remover + se presente
            }
            
            $message = $payload['text'] ?? $payload['message'] ?? $payload['caption'] ?? '';
            $messageId = $payload['id'] ?? $payload['message_id'] ?? null;
            
            // Processar timestamp (pode vir em formato ISO ou Unix timestamp)
            $timestamp = time();
            if (isset($payload['timestamp'])) {
                if (is_numeric($payload['timestamp'])) {
                    $timestamp = (int)$payload['timestamp'];
                } else {
                    $timestamp = strtotime($payload['timestamp']);
                    if ($timestamp === false) {
                        $timestamp = time();
                    }
                }
            }
            
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

            Logger::quepasa("processWebhook - Processando mensagem: fromPhone={$fromPhone}, message={$message}, messageId={$messageId}");
            
            if (!$fromPhone || (empty($message) && !$mediaUrl)) {
                Logger::error("WhatsApp webhook: dados incompletos (fromPhone: " . ($fromPhone ?? 'NULL') . ", message: " . ($message ?? 'NULL') . ", mediaUrl: " . ($mediaUrl ?? 'NULL') . ")");
                return;
            }

            // Criar ou atualizar contato
            Logger::quepasa("processWebhook - Buscando contato pelo telefone: {$fromPhone}");
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
            Logger::quepasa("processWebhook - Buscando conversa existente: contact_id={$contact['id']}, channel=whatsapp, account_id={$account['id']}");
            $conversation = \App\Models\Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
            $isNewConversation = false;
            
            if (!$conversation) {
                Logger::quepasa("processWebhook - Conversa não encontrada, criando nova...");
                // Usar ConversationService para criar conversa (com todas as integrações)
                try {
                    $conversation = \App\Services\ConversationService::create([
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'whatsapp_account_id' => $account['id']
                    ]);
                    $isNewConversation = true;
                    Logger::quepasa("processWebhook - Conversa criada via ConversationService: ID={$conversation['id']}");
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao criar conversa via ConversationService: " . $e->getMessage());
                    Logger::quepasa("Stack trace: " . $e->getTraceAsString());
                    // Fallback: criar diretamente se ConversationService falhar
                    try {
                        $conversationId = \App\Models\Conversation::create([
                            'contact_id' => $contact['id'],
                            'channel' => 'whatsapp',
                            'whatsapp_account_id' => $account['id'],
                            'status' => 'open'
                        ]);
                        $conversation = \App\Models\Conversation::find($conversationId);
                        Logger::quepasa("processWebhook - Conversa criada via fallback: ID={$conversationId}");
                    } catch (\Exception $e2) {
                        Logger::error("Erro ao criar conversa via fallback: " . $e2->getMessage());
                        throw $e2;
                    }
                }
            } else {
                Logger::quepasa("processWebhook - Conversa existente encontrada: ID={$conversation['id']}");
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

            Logger::quepasa("processWebhook - Mensagem processada com sucesso: fromPhone={$fromPhone}, message={$message}, conversationId={$conversation['id']}");
            Logger::log("WhatsApp mensagem processada: {$fromPhone} -> {$message}");
        } catch (\Exception $e) {
            Logger::error("WhatsApp processWebhook Error: " . $e->getMessage());
            Logger::error("WhatsApp processWebhook Stack: " . $e->getTraceAsString());
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
