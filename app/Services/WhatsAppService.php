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
     * Normalizar número de telefone do WhatsApp
     * Remove sufixos como @s.whatsapp.net, @lid, @c.us, @g.us, etc.
     * Remove caracteres especiais e deixa apenas dígitos
     * 
     * @param string $phone Número no formato do WhatsApp (ex: "553588987594@s.whatsapp.net", "276553028132899@lid")
     * @return string Número normalizado (ex: "553588987594", "276553028132899")
     */
    public static function normalizePhoneNumber(string $phone): string
    {
        if (empty($phone)) {
            return '';
        }
        
        // Remover sufixos comuns do WhatsApp
        $phone = str_replace('@s.whatsapp.net', '', $phone);
        $phone = str_replace('@lid', '', $phone); // Linked ID (contatos não salvos)
        $phone = str_replace('@c.us', '', $phone); // Contato comum
        $phone = str_replace('@g.us', '', $phone); // Grupo
        
        // Remover caracteres especiais comuns
        $phone = str_replace(['+', '-', ' ', '(', ')', '.', '_'], '', $phone);
        
        // Extrair apenas dígitos (pode ter : para separar device ID)
        // Ex: "553591970289:86" -> extrair apenas "553591970289"
        if (strpos($phone, ':') !== false) {
            $phone = explode(':', $phone)[0];
        }
        
        // Remover + se ainda estiver presente
        $phone = ltrim($phone, '+');
        
        return $phone;
    }

    /**
     * Criar conta WhatsApp
     */
    public static function createAccount(array $data): int
    {
        $errors = \App\Helpers\Validator::validate($data, [
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string',
            'provider' => 'required|string|in:quepasa,evolution',
            'api_url' => 'required|string|max:500',
            'quepasa_user' => 'nullable|string|max:255',
            'quepasa_trackid' => 'nullable|string|max:255',
            'api_key' => 'nullable|string|max:255',
            'instance_id' => 'nullable|string|max:255',
            'default_funnel_id' => 'nullable|integer',
            'default_stage_id' => 'nullable|integer'
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
                CURLOPT_TIMEOUT => 60, // ✅ Aumentado de 30s para 60s
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
                    CURLOPT_TIMEOUT => 60, // ✅ Aumentado de 30s para 60s
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
                                    
                                    // Tentar extrair instance_id da resposta se ainda não tiver
                                    $instanceId = $account['instance_id'] ?? null;
                                    if (empty($instanceId) && isset($jsonResponse['instanceId'])) {
                                        $instanceId = $jsonResponse['instanceId'];
                                    } elseif (empty($instanceId) && isset($jsonResponse['instance_id'])) {
                                        $instanceId = $jsonResponse['instance_id'];
                                    } elseif (empty($instanceId) && isset($jsonResponse['id'])) {
                                        // Às vezes o ID da instância vem como 'id'
                                        $instanceId = $jsonResponse['id'];
                                    }
                                    
                                    // Se ainda não tiver instance_id, tentar extrair da URL da API
                                    // Muitas vezes no Quepasa self-hosted a URL é tipo: https://servidor/bot123
                                    if (empty($instanceId) && !empty($account['api_url'])) {
                                        $urlParts = parse_url($account['api_url']);
                                        $path = $urlParts['path'] ?? '';
                                        // Extrair última parte do path como instance_id
                                        $pathSegments = array_filter(explode('/', trim($path, '/')));
                                        if (!empty($pathSegments)) {
                                            $instanceId = end($pathSegments);
                                            Logger::quepasa("getConnectionStatus - Instance ID extraído da URL: {$instanceId}");
                                        }
                                    }
                                    
                                    // Se ainda não tiver, usar o quepasa_user como fallback
                                    if (empty($instanceId) && !empty($account['quepasa_user'])) {
                                        $instanceId = $account['quepasa_user'];
                                        Logger::quepasa("getConnectionStatus - Instance ID usando quepasa_user: {$instanceId}");
                                    }
                                    
                                    $updateData = [
                                        'quepasa_chatid' => $chatid,
                                        'status' => 'active'
                                    ];
                                    
                                    if (!empty($instanceId)) {
                                        $updateData['instance_id'] = $instanceId;
                                        Logger::quepasa("getConnectionStatus - Instance ID salvo: {$instanceId}");
                                    }
                                    
                                    WhatsAppAccount::update($accountId, $updateData);
                                    
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

            // Resolver chatId correto (inclui casos @lid)
            $chatId = $to . '@s.whatsapp.net';
            Logger::quepasa("sendMessage - Resolvendo chatId para: {$to}");
            
            // Se existir contato com whatsapp_id, usar
            try {
                $contact = \App\Models\Contact::findByPhoneNormalized($to);
                if ($contact) {
                    Logger::quepasa("sendMessage - Contato encontrado: ID={$contact['id']}, whatsapp_id=" . ($contact['whatsapp_id'] ?? 'NULL'));
                    if (!empty($contact['whatsapp_id'])) {
                        $chatId = $contact['whatsapp_id'];
                        Logger::quepasa("sendMessage - ✅ Usando whatsapp_id do contato: {$chatId}");
                    } else {
                        Logger::quepasa("sendMessage - ⚠️ Contato sem whatsapp_id, usando padrão");
                    }
                } else {
                    Logger::quepasa("sendMessage - ⚠️ Contato não encontrado para: {$to}");
                }
            } catch (\Throwable $e) {
                Logger::quepasa("sendMessage - ⚠️ Erro ao buscar contato: " . $e->getMessage());
            }
            
            // Normalizar chatId se vier só números
            if (!str_contains($chatId, '@')) {
                $chatId .= '@s.whatsapp.net';
            }
            
            Logger::quepasa("sendMessage - ChatId final: {$chatId}");
            
            if ($account['provider'] === 'quepasa') {
                // Quepasa self-hosted: POST /send (usa campo media para anexos)
                $url = "{$apiUrl}/send";
                
                $headers = [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-QUEPASA-TOKEN: ' . $account['quepasa_token'],
                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name']),
                    'X-QUEPASA-CHATID: ' . $chatId
                ];

                // Logs adicionais para debug (sem expor token completo)
                $maskedToken = self::maskToken($account['quepasa_token'] ?? '');
                Logger::quepasa("sendMessage - Provider=quepasa | apiUrl={$apiUrl} | chatId={$chatId} | trackId=" . ($account['quepasa_trackid'] ?? $account['name']) . " | token=" . $maskedToken);
                Logger::quepasa("sendMessage - Provider headers raw (sem token completo): " . json_encode([
                    'X-QUEPASA-TOKEN' => $maskedToken,
                    'X-QUEPASA-TRACKID' => ($account['quepasa_trackid'] ?? $account['name']),
                    'X-QUEPASA-CHATID' => $chatId
                ]));
                
                // Garantir texto/caption (somente se houver texto de fato)
                $caption = $options['caption'] ?? $message ?? '';
                $captionTrim = trim((string)$caption);
                
            $payload = [
                'chatId' => $chatId,
                'text' => $captionTrim === '' ? null : $captionTrim
            ];
                
                // Reply: usar external_id da mensagem citada (se disponível)
                // Quepasa espera o campo "inreply" no nível raiz do JSON
                $quotedExternalId = $options['quoted_message_external_id'] ?? ($options['quoted_message_id'] ?? null);
                if (!empty($quotedExternalId)) {
                    $payload['inreply'] = $quotedExternalId;
                    Logger::quepasa("sendMessage - Reply debug: quoted_external={$quotedExternalId}");
                }
                
                // Incluir mídia se houver
                if (!empty($options['media_url'])) {
                    $mediaType = $options['media_type'] ?? 'document';
                    $mediaMime = $options['media_mime'] ?? null;
                    $mediaName = $options['media_name'] ?? null;
                    
                    Logger::quepasa("sendMessage - MÍDIA DETECTADA:");
                    Logger::quepasa("sendMessage - media_type: {$mediaType}");
                    Logger::quepasa("sendMessage - media_mime: " . ($mediaMime ?? 'NULL'));
                    Logger::quepasa("sendMessage - media_name: " . ($mediaName ?? 'NULL'));
                    Logger::quepasa("sendMessage - media_url: {$options['media_url']}");
                    
                    if ($mediaType === 'audio') {
                        Logger::quepasa("sendMessage - ✅ É ÁUDIO/VOZ! Preparando envio via BASE64 (OGG/Opus)...");
                        
                        // Se veio como video/webm mas é áudio, alinhar o mimetype para audio/webm
                        if (!empty($mediaMime) && str_contains($mediaMime, 'video/webm')) {
                            Logger::quepasa("sendMessage - Ajustando mimetype de video/webm para audio/webm (áudio detectado)");
                            $mediaMime = 'audio/webm';
                        }

                        // Obter caminho local do arquivo
                        $mediaUrl = $options['media_url'];
                        $publicBase = realpath(__DIR__ . '/../../public');
                        $pathFromUrl = parse_url($mediaUrl, PHP_URL_PATH) ?: '';
                        $absolutePath = $publicBase . $pathFromUrl;
                        
                        Logger::quepasa("sendMessage - Caminho do áudio: {$absolutePath}");
                        
                        // ✅ NOVA ABORDAGEM: Enviar áudio via BASE64, preferindo MP3 para compatibilidade iOS
                        if (file_exists($absolutePath)) {
                            $audioSize = filesize($absolutePath);
                            Logger::quepasa("sendMessage - Arquivo encontrado: {$audioSize} bytes");
                            
                            // Detectar mimetype real do arquivo
                            $detectedMime = null;
                            if (function_exists('finfo_open')) {
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                $detectedMime = $finfo ? finfo_file($finfo, $absolutePath) : null;
                                if ($finfo) {
                                    finfo_close($finfo);
                                }
                            } elseif (function_exists('mime_content_type')) {
                                $detectedMime = mime_content_type($absolutePath) ?: null;
                            }
                            
                            if (!empty($detectedMime)) {
                                $mediaMime = self::normalizeMimeType($detectedMime);
                                Logger::quepasa("sendMessage - Mime detectado: {$mediaMime}");
                            }
                            
                            // Verificar tamanho máximo (WhatsApp limita a ~16MB para áudio)
                            $maxAudioSize = 16 * 1024 * 1024; // 16MB
                            
                            if ($audioSize > $maxAudioSize) {
                                Logger::quepasa("sendMessage - ⚠️ AVISO: Áudio muito grande ({$audioSize} bytes > 16MB), tentando enviar via URL");
                                
                                // Fallback: usar URL se arquivo for muito grande
                                $payload['url'] = str_starts_with($mediaUrl, 'http://') 
                                    ? preg_replace('/^http:/i', 'https:', $mediaUrl) 
                                    : $mediaUrl;
                                $payload['fileName'] = $mediaName ?: 'audio.ogg';
                                $payload['text'] = $captionTrim !== '' ? $captionTrim : ' ';
                                
                                Logger::quepasa("sendMessage - Usando URL como fallback (arquivo grande)");
                            } else {
                                $sourcePath = $absolutePath;
                                $convertedPath = null;
                                $finalMime = self::normalizeMimeType($mediaMime ?: 'audio/ogg');
                                $finalFileName = $mediaName ?: 'audio.ogg';
                                
                                // Preferir OGG/Opus (voz do WhatsApp) para compatibilidade iOS
                                if ($finalMime !== 'audio/ogg') {
                                    $conversion = self::convertAudioToOpus($absolutePath);
                                    if (!empty($conversion['success'])) {
                                        $sourcePath = $conversion['filepath'];
                                        $convertedPath = $conversion['filepath'];
                                        $finalMime = 'audio/ogg';
                                        $finalFileName = preg_replace('/\.[^.]+$/', '.ogg', $finalFileName);
                                        
                                        Logger::quepasa("sendMessage - ✅ Áudio convertido para OGG/Opus: {$sourcePath}");
                                    } else {
                                        Logger::quepasa("sendMessage - ⚠️ Falha ao converter para OGG/Opus: " . ($conversion['error'] ?? 'desconhecido'));
                                    }
                                }
                                
                                $audioContent = file_get_contents($sourcePath);
                                $audioBase64 = base64_encode($audioContent);
                                
                                Logger::quepasa("sendMessage - Base64 gerado: " . strlen($audioBase64) . " caracteres");
                                
                                // ✅ Usar campo 'content' com data URI (recomendado pela API Quepasa)
                                $contentMime = $finalMime === 'audio/ogg' ? 'audio/ogg; codecs=opus' : $finalMime;
                                $payload['content'] = "data:{$contentMime};base64,{$audioBase64}";
                                $payload['fileName'] = $finalFileName;
                                
                                // Campo text continua obrigatório
                                if ($captionTrim !== '') {
                                    $payload['text'] = $captionTrim;
                                } else {
                                    $payload['text'] = ' ';
                                }
                                
                                Logger::quepasa("sendMessage - ✅ Payload ÁUDIO/VOZ via BASE64 configurado:");
                                Logger::quepasa("sendMessage -   mimetype: {$contentMime}");
                                Logger::quepasa("sendMessage -   fileName: {$finalFileName}");
                                Logger::quepasa("sendMessage -   tamanho original: {$audioSize} bytes");
                                Logger::quepasa("sendMessage -   tamanho base64: " . strlen($audioBase64) . " caracteres");
                                Logger::quepasa("sendMessage -   content: data:{$contentMime};base64,[" . strlen($audioBase64) . " chars]");
                                Logger::quepasa("sendMessage -   text: '" . ($payload['text'] === ' ' ? '(espaço)' : substr($payload['text'], 0, 50)) . "'");
                                
                                if ($convertedPath && file_exists($convertedPath)) {
                                    @unlink($convertedPath);
                                }
                            }
                        } else {
                            Logger::quepasa("sendMessage - ⚠️ ERRO: Arquivo de áudio não encontrado: {$absolutePath}");
                            Logger::quepasa("sendMessage - Tentando enviar via URL como fallback...");
                            
                            // Fallback: usar URL se arquivo não existir localmente
                            $payload['url'] = str_starts_with($mediaUrl, 'http://') 
                                ? preg_replace('/^http:/i', 'https:', $mediaUrl) 
                                : $mediaUrl;
                            $payload['fileName'] = $mediaName ?: 'audio.ogg';
                            $payload['text'] = $captionTrim !== '' ? $captionTrim : ' ';
                            
                            Logger::quepasa("sendMessage - Usando URL como fallback (arquivo não encontrado localmente)");
                        }
                    } else {
                        Logger::quepasa("sendMessage - Não é áudio, enviando como mídia normal (imagem/vídeo/documento)");
                        
                        // ✅ CORREÇÃO: Para vídeo/documento também precisa do campo text obrigatório
                        $payload['url'] = $options['media_url'];
                        
                        if (!empty($mediaName)) {
                            $payload['fileName'] = $mediaName;
                        }
                        
                        // Campo text é OBRIGATÓRIO mesmo para mídia (conforme doc Quepasa)
                        if ($captionTrim !== '') {
                            $payload['text'] = $captionTrim;
                        } else {
                            $payload['text'] = ' '; // Espaço obrigatório quando não há caption
                        }
                        
                        Logger::quepasa("sendMessage - Payload {$mediaType} configurado:");
                        Logger::quepasa("sendMessage -   url: {$payload['url']}");
                        Logger::quepasa("sendMessage -   fileName: " . ($payload['fileName'] ?? 'NULL'));
                        Logger::quepasa("sendMessage -   text: '" . ($payload['text'] === ' ' ? '(espaço)' : substr($payload['text'], 0, 50)) . "'");
                    }
                } else {
                    Logger::quepasa("sendMessage - Nenhuma mídia detectada nas opções");
                }
                
                Logger::quepasa("sendMessage - Iniciando envio");
                Logger::quepasa("sendMessage - URL: {$url}");
                Logger::quepasa("sendMessage - To: {$to}");
                Logger::quepasa("sendMessage - Payload: " . json_encode($payload));
                if (!empty($options['quoted_message_external_id']) || !empty($options['quoted_message_id'])) {
                    Logger::quepasa("sendMessage - Reply debug: quoted_external=" . ($options['quoted_message_external_id'] ?? 'null') . " | quoted_id=" . ($options['quoted_message_id'] ?? 'null') . " | quotedChatId=" . ($payload['quotedChatId'] ?? 'null'));
                }
                Logger::quepasa("sendMessage - Headers: " . json_encode([
                    'X-QUEPASA-TOKEN' => $maskedToken,
                    'X-QUEPASA-TRACKID' => ($account['quepasa_trackid'] ?? $account['name']),
                    'X-QUEPASA-CHATID' => ($to . '@s.whatsapp.net')
                ]));
            } else {
                throw new \InvalidArgumentException('Provider não suportado');
            }

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60, // ✅ Aumentado de 30s para 60s
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
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $responseLen = strlen($response ?? '');
            $error = curl_error($ch);
            curl_close($ch);

            Logger::quepasa("sendMessage - HTTP Code: {$httpCode} | Content-Type: " . ($contentType ?: 'null') . " | RespLen: {$responseLen}");
            Logger::quepasa("sendMessage - Effective URL: {$effectiveUrl}");
            Logger::quepasa("sendMessage - Response Preview: " . substr($response ?? '', 0, 500));

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
            
            // Extrair ID da mensagem retornada (alguns provedores retornam em message.id)
            $returnedMessageId = $data['id']
                ?? $data['message_id']
                ?? ($data['message']['id'] ?? null);
            
            if (empty($returnedMessageId)) {
                Logger::quepasa("sendMessage - ⚠️ message_id não retornado no payload: " . substr($response ?? '', 0, 300));
            } else {
                Logger::quepasa("sendMessage - message_id retornado: {$returnedMessageId}");
            }
            
            Logger::quepasa("sendMessage - Mensagem enviada com sucesso");
            
            return [
                'success' => true,
                'message_id' => $returnedMessageId,
                'status' => $data['status'] ?? 'sent'
            ];
        } catch (\Exception $e) {
            Logger::quepasa("sendMessage - Exception: " . $e->getMessage());
            Logger::error("WhatsApp sendMessage Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mascarar token para logs (deixa início e fim visíveis)
     */
    private static function maskToken(?string $token): string
    {
        if (empty($token)) {
            return '';
        }
        $len = strlen($token);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        $start = substr($token, 0, 2);
        $end = substr($token, -2);
        return $start . str_repeat('*', max(0, $len - 4)) . $end;
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
                CURLOPT_TIMEOUT => 60, // ✅ Aumentado de 30s para 60s
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
        \App\Helpers\Logger::info("═══ WhatsAppService::processWebhook INÍCIO ═══ Keys: " . implode(', ', array_keys($payload)));
        
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
            
            // Extrair número de telefone do from (normalizar)
            $fromPhone = null;
            if ($from) {
                $fromPhone = self::normalizePhoneNumber($from);
            }
            
            // Log bruto do webhook (truncado para 8000 chars para evitar log excessivo)
            $rawInput = file_get_contents('php://input');
            if ($rawInput !== false) {
                $rawPreview = mb_substr($rawInput, 0, 8000);
                Logger::quepasa("processWebhook - Raw body (trunc 8000): " . $rawPreview);
            }
            
            // Log JSON decodificado (truncado) para facilitar inspeção
            Logger::quepasa("processWebhook - Payload recebido: trackid={$trackid}, chatid={$chatid}, from={$from}, fromPhone={$fromPhone}");
            Logger::quepasa("processWebhook - Payload decoded (trunc 4000): " . mb_substr(json_encode($payload), 0, 4000));
            
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
            
            // Tentar extrair instance_id do payload se não tiver ainda
            if (empty($account['instance_id'])) {
                $instanceId = $payload['instanceId'] ?? $payload['instance_id'] ?? null;
                
                // Se não veio no payload, tentar extrair da URL
                if (empty($instanceId) && !empty($account['api_url'])) {
                    $urlParts = parse_url($account['api_url']);
                    $path = $urlParts['path'] ?? '';
                    $pathSegments = array_filter(explode('/', trim($path, '/')));
                    if (!empty($pathSegments)) {
                        $instanceId = end($pathSegments);
                        Logger::quepasa("processWebhook - Instance ID extraído da URL: {$instanceId}");
                    }
                }
                
                // Se ainda não tiver, usar quepasa_user
                if (empty($instanceId) && !empty($account['quepasa_user'])) {
                    $instanceId = $account['quepasa_user'];
                    Logger::quepasa("processWebhook - Instance ID usando quepasa_user: {$instanceId}");
                }
                
                if (!empty($instanceId)) {
                    WhatsAppAccount::update($account['id'], ['instance_id' => $instanceId]);
                    $account['instance_id'] = $instanceId;
                    Logger::quepasa("processWebhook - Instance ID salvo: {$instanceId}");
                }
            }

            // Verificar se mensagem foi enviada DO número conectado (não recebida)
            // Critérios confiáveis: flags fromme/frominternal ou participant == número conectado
            $isMessageFromConnectedNumber = false;
            $connectedNumberNormalized = self::normalizePhoneNumber($account['phone_number']);
            
            Logger::quepasa("processWebhook - 🔍 VERIFICANDO SE É MENSAGEM ENVIADA: connectedNumber={$connectedNumberNormalized}, chatid={$chatid}");
            
            // 1) Flags explícitas do provedor
            $fromme = $payload['fromme'] ?? $payload['from_internal'] ?? $payload['frominternal'] ?? $payload['from_me'] ?? $payload['fromMe'] ?? false;
            if ($fromme === true || $fromme === 'true' || $fromme === 1 || $fromme === '1') {
                $isMessageFromConnectedNumber = true;
                Logger::quepasa("processWebhook - Mensagem ENVIADA detectada via flag fromme/frominternal");
            }
            
            // 2) Participant explicitamente igual ao número conectado
            if (!$isMessageFromConnectedNumber && isset($payload['participant'])) {
                $participant = $payload['participant'];
                $participantPhone = null;
                if (is_array($participant) && isset($participant['phone'])) {
                    $participantPhone = self::normalizePhoneNumber($participant['phone']);
                } elseif (is_string($participant)) {
                    $participantPhone = self::normalizePhoneNumber($participant);
                }
                
                if ($participantPhone && $participantPhone === $connectedNumberNormalized) {
                    $isMessageFromConnectedNumber = true;
                    Logger::quepasa("processWebhook - Mensagem ENVIADA detectada via campo participant");
                }
            }
            
            // 3) Campo from igual ao número conectado (fallback leve)
            if (!$isMessageFromConnectedNumber && isset($payload['from'])) {
                $fromCandidate = self::normalizePhoneNumber($payload['from']);
                if ($fromCandidate && $fromCandidate === $connectedNumberNormalized) {
                    $isMessageFromConnectedNumber = true;
                    Logger::quepasa("processWebhook - Mensagem ENVIADA detectada via campo from == número conectado");
                }
            }
            
            // Observação: NÃO usamos mais chatid/wid sozinho para inferir outbound,
            // pois provedores podem enviar chatid com o número da instância mesmo em mensagens recebidas.
            
            // Se detectou que foi enviada do número conectado, mas não consegue identificar destinatário,
            // simplesmente ignorar (não processar como mensagem recebida)
            if ($isMessageFromConnectedNumber) {
                Logger::quepasa("processWebhook - Mensagem ENVIADA do número conectado detectada. Verificando se pode identificar destinatário...");
            }
            
            // Processar mensagem recebida ou enviada
            // Formato novo do Quepasa: chat.id ou chat.phone
            $from = $payload['from'] ?? $payload['phone'] ?? null;
            if (!$from && isset($payload['chat'])) {
                $from = $payload['chat']['id'] ?? $payload['chat']['phone'] ?? null;
            }
            
            // Se mensagem foi ENVIADA do número conectado, 'from' é o DESTINATÁRIO
            // Se mensagem foi RECEBIDA, 'from' é o REMETENTE
            $fromPhone = null;
            if ($from) {
                $fromPhone = self::normalizePhoneNumber($from);
            }
            
            $message = $payload['text'] ?? $payload['message'] ?? $payload['caption'] ?? '';
            // Capturar messageId de múltiplas fontes possíveis (payload direto ou dentro de data/message)
            $messageId = $payload['id'] 
                ?? $payload['message_id'] 
                ?? ($payload['message']['id'] ?? null)
                ?? ($payload['data']['id'] ?? null)
                ?? null;
            
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
            $forwarded = $payload['forwarded'] ?? false;
            $quotedMessageId = $payload['quoted'] ?? $payload['quoted_message_id'] ?? null;
            $quotedMessageText = $payload['quoted_text'] ?? null;
            
            // Suportar formato do Quepasa (event/data) e campos completos
            $quepasaData = $payload;
            if (isset($payload['event']) && isset($payload['data'])) {
                $quepasaData = $payload['data'];
            }
            
            // Priorizar campos do formato Quepasa
            $messageType = $quepasaData['type'] ?? $payload['type'] ?? $messageType;
            $message = $quepasaData['body'] ?? $payload['text'] ?? $payload['message'] ?? $payload['caption'] ?? $message;
            $from = $quepasaData['from'] ?? ($payload['from'] ?? $payload['chat']['id'] ?? null);
            $to = $quepasaData['to'] ?? null;
            
            // Detectar grupo corretamente: flag isGroup ou sufixo @g.us
            $isGroup = (bool)($quepasaData['isGroup'] ?? $payload['isGroup'] ?? ($payload['chat']['isGroup'] ?? false));
            $author = $quepasaData['author'] ?? null;
            $groupName = $quepasaData['groupName'] ?? $payload['groupName'] ?? ($payload['chat']['groupName'] ?? null);
            
            // Verificar se é grupo pelo formato do ID (@g.us) em vários campos possíveis
            if (!$isGroup) {
                // Verificar no campo 'from'
                if ($from && strpos($from, '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'from': {$from}");
                }
                
                // Verificar no campo 'chatid' (variável local)
                if (!$isGroup && isset($chatid) && $chatid && strpos($chatid, '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'chatid': {$chatid}");
                }
                
                // Verificar no campo 'wid' do payload
                $wid = $payload['wid'] ?? null;
                if (!$isGroup && $wid && strpos($wid, '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'wid': {$wid}");
                }
                
                // Verificar no campo 'chat.id'
                if (!$isGroup && isset($payload['chat']['id']) && strpos($payload['chat']['id'], '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'chat.id': {$payload['chat']['id']}");
                }
                
                // Verificar no campo 'chat.chatId' (formato Quepasa)
                if (!$isGroup && isset($quepasaData['chat']['chatId']) && strpos($quepasaData['chat']['chatId'], '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'chat.chatId': {$quepasaData['chat']['chatId']}");
                }
                
                // Verificar no campo 'chat.chatid' (alternativo)
                if (!$isGroup && isset($quepasaData['chat']['chatid']) && strpos($quepasaData['chat']['chatid'], '@g.us') !== false) {
                    $isGroup = true;
                    Logger::quepasa("processWebhook - Grupo detectado pelo formato @g.us no campo 'chat.chatid': {$quepasaData['chat']['chatid']}");
                }
            }
            
            // Verificar se mensagens de grupo são permitidas (APÓS determinar corretamente se é grupo)
            $allowGroupMessages = \App\Services\SettingService::get('whatsapp_allow_group_messages', true);
            Logger::quepasa("processWebhook - Verificação de grupos: isGroup=" . ($isGroup ? 'true' : 'false') . ", allowGroupMessages=" . ($allowGroupMessages ? 'true' : 'false') . ", from={$from}, chatid={$chatid}");
            
            if ($isGroup && !$allowGroupMessages) {
                Logger::quepasa("processWebhook - ❌ Mensagem de grupo IGNORADA (grupos desabilitados): from={$from}, chatid={$chatid}, groupName={$groupName}");
                return;
            }
            
            if ($isGroup && $allowGroupMessages) {
                Logger::quepasa("processWebhook - ✅ Mensagem de grupo PERMITIDA: from={$from}, groupName={$groupName}");
            }
            // Se for grupo, usar author como remetente
            if ($isGroup && $author) {
                $from = $author;
            }
            
            // Reprocessar fromPhone com novos dados (normalizar)
            if ($from) {
                $fromPhone = self::normalizePhoneNumber($from);
            }
            
            // Media / arquivos (vários formatos possíveis)
            // ⚠️ IMPORTANTE: $payload['url'] pode ser um array com metadados de link, não uma URL de mídia!
            // Primeiro, tentar extrair de campos específicos de mídia
            $mediaUrl = $quepasaData['url'] ?? $payload['media_url'] ?? $payload['mediaUrl'] ?? null;
            
            // Se ainda não encontrou, verificar $payload['url'] mas APENAS se for string (não array de metadados de link)
            if (!$mediaUrl && isset($payload['url']) && is_string($payload['url'])) {
                $mediaUrl = $payload['url'];
            }
            
            $mimetype = $quepasaData['mimeType'] ?? $payload['mimetype'] ?? $payload['mime_type'] ?? null;
            $filename = $quepasaData['fileName'] ?? $quepasaData['filename'] ?? $payload['filename'] ?? $payload['media_name'] ?? null;
            $size = $quepasaData['size'] ?? $payload['size'] ?? null;

            // Possíveis contêineres de mídia (media/audio/attachment/extra)
            $candidates = [
                $quepasaData['media'] ?? null,
                $quepasaData['audio'] ?? null,
                $quepasaData['attachment'] ?? null,
                $quepasaData['extra'] ?? null,
                $payload['media'] ?? null,
                $payload['audio'] ?? null,
                $payload['attachment'] ?? null,
                $payload['extra'] ?? null,
                $payload['data']['media'] ?? null,
                $payload['data']['audio'] ?? null,
                $payload['data']['attachment'] ?? null,
                $payload['data']['extra'] ?? null,
                $payload['message']['media'] ?? null,
                $payload['message']['audio'] ?? null,
                $payload['message']['attachment'] ?? null,
                $payload['message']['extra'] ?? null,
            ];
            foreach ($candidates as $cand) {
                if (isset($cand) && is_array($cand)) {
                    $mediaUrl = $cand['url'] ?? $mediaUrl;
                    $mimetype = $cand['mimeType'] ?? $cand['mimetype'] ?? $mimetype;
                    $filename = $cand['fileName'] ?? $cand['filename'] ?? $filename;
                    $size = $cand['size'] ?? $size;
                }
            }

            // Se mídia (áudio/imagem/vídeo/documento) vier sem URL mas com attachment, tentar baixar via API
            $downloadedFile = null; // Inicializar variável para armazenar arquivo já baixado
            $needsDownload = in_array($messageType, ['audio', 'image', 'video', 'document', 'ptt', 'sticker']) && !$mediaUrl && isset($payload['attachment']);
            
            if ($needsDownload) {
                $attachmentKeys = isset($payload['attachment']) && is_array($payload['attachment']) ? implode(',', array_keys($payload['attachment'])) : 'NULL';
                $extraKeys = isset($payload['extra']) && is_array($payload['extra']) ? implode(',', array_keys($payload['extra'])) : 'NULL';
                $topKeys = implode(',', array_keys($payload));
                $dataKeys = isset($payload['data']) && is_array($payload['data']) ? implode(',', array_keys($payload['data'])) : 'NULL';
                Logger::quepasa("processWebhook - {$messageType} sem mediaUrl - topKeys={$topKeys} | dataKeys={$dataKeys} | attachmentKeys={$attachmentKeys} | extraKeys={$extraKeys}");
                Logger::quepasa("processWebhook - attachment content: " . json_encode($payload['attachment']));
                if (isset($payload['extra']) && is_array($payload['extra'])) {
                    Logger::quepasa("processWebhook - extra content: " . json_encode($payload['extra']));
                }
                
                // Tentar baixar áudio via API Quepasa/Orbichat
                // Endpoints possíveis: /attachment/{id}, /download/{wid}, /messages/{id}/download
                try {
                    // Logar campos disponíveis para identificar o ID correto da mensagem
                    Logger::quepasa("processWebhook - Campos disponíveis no payload: id=" . ($payload['id'] ?? 'null') . ", wid=" . ($payload['wid'] ?? 'null') . ", messageId=" . ($messageId ?? 'null'));
                    
                    // Usar o ID da mensagem correto (não o wid da conta)
                    $messageWid = $payload['id'] ?? $messageId;
                    $apiUrl = rtrim($account['api_url'], '/');
                    
                    Logger::quepasa("processWebhook - Tentando baixar {$messageType} via API: messageWid={$messageWid}");
                    
                    // Extrair apenas o ID se vier com sufixo @s.whatsapp.net
                    $messageIdOnly = $messageWid;
                    if (strpos($messageIdOnly, '@') !== false) {
                        $messageIdOnly = explode('@', $messageIdOnly)[0];
                    }
                    
                    // Tentar endpoints comuns (Quepasa/Orbichat)
                    $endpoints = [
                        "/attachment/{$messageWid}",
                        "/attachment/{$messageIdOnly}",
                        "/download/{$messageWid}",
                        "/download/{$messageIdOnly}",
                        "/messages/{$messageWid}/download",
                        "/messages/{$messageIdOnly}/download",
                        "/messages/{$messageWid}/attachment",
                        "/messages/{$messageIdOnly}/attachment",
                        "/{$messageWid}/download",
                        "/{$messageIdOnly}/download"
                    ];
                    
                    $downloaded = false;
                    foreach ($endpoints as $endpoint) {
                        $downloadUrl = $apiUrl . $endpoint;
                        Logger::quepasa("processWebhook - Tentando endpoint: {$downloadUrl}");
                        
                        $ch = curl_init($downloadUrl);
                        $headers = [
                            'Accept: */*',
                            'X-QUEPASA-TOKEN: ' . ($account['quepasa_token'] ?? ''),
                            'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name'])
                        ];
                        
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 60, // ✅ Aumentado de 30s para 60s
                            CURLOPT_HTTPHEADER => $headers,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false
                        ]);
                        
                        $mediaData = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                        curl_close($ch);
                        
                        Logger::quepasa("processWebhook - Endpoint {$endpoint}: HTTP {$httpCode}, Content-Type: " . ($contentType ?: 'null') . ", Size: " . strlen($mediaData) . " bytes");
                        
                        if ($httpCode === 200 && $mediaData && strlen($mediaData) > 100) {
                            // Verificar se é mídia válida (não é JSON de erro)
                            $isJson = @json_decode($mediaData);
                            $isValidMedia = ($isJson === null && (
                                strpos($contentType, 'audio') !== false ||
                                strpos($contentType, 'image') !== false ||
                                strpos($contentType, 'video') !== false ||
                                strpos($contentType, 'application') !== false ||
                                strpos($contentType, 'octet-stream') !== false ||
                                strpos($contentType, 'binary') !== false ||
                                empty($contentType)
                            ));
                            
                            if ($isValidMedia) {
                                Logger::quepasa("processWebhook - ✅ {$messageType} baixado com sucesso: " . strlen($mediaData) . " bytes");
                                
                                // Salvar mídia temporariamente
                                $tempDir = __DIR__ . '/../../public/assets/media/attachments/temp';
                                if (!is_dir($tempDir)) {
                                    mkdir($tempDir, 0755, true);
                                }
                                
                                // Determinar extensão baseada no mime type
                                $extension = 'bin';
                                $attachmentMime = $payload['attachment']['mime'] ?? $mimetype ?? $contentType;
                                if ($attachmentMime) {
                                    if (strpos($attachmentMime, 'opus') !== false || strpos($attachmentMime, 'ogg') !== false) $extension = 'ogg';
                                    elseif (strpos($attachmentMime, 'mpeg') !== false) $extension = 'mp3';
                                    elseif (strpos($attachmentMime, 'mp4') !== false) $extension = 'mp4';
                                    elseif (strpos($attachmentMime, 'webm') !== false) $extension = 'webm';
                                    elseif (strpos($attachmentMime, 'jpeg') !== false || strpos($attachmentMime, 'jpg') !== false) $extension = 'jpg';
                                    elseif (strpos($attachmentMime, 'png') !== false) $extension = 'png';
                                    elseif (strpos($attachmentMime, 'gif') !== false) $extension = 'gif';
                                    elseif (strpos($attachmentMime, 'webp') !== false) $extension = 'webp';
                                    elseif (strpos($attachmentMime, 'pdf') !== false) $extension = 'pdf';
                                    elseif (strpos($attachmentMime, 'document') !== false || strpos($attachmentMime, 'msword') !== false) $extension = 'doc';
                                    elseif (strpos($attachmentMime, 'sheet') !== false || strpos($attachmentMime, 'excel') !== false) $extension = 'xls';
                                }
                                
                                $tempFile = $tempDir . '/' . $messageType . '_' . $messageIdOnly . '_' . time() . '.' . $extension;
                                file_put_contents($tempFile, $mediaData);
                                
                                // Criar caminho relativo para salvar no banco (sem /public/)
                                $relativePath = 'assets/media/attachments/temp/' . basename($tempFile);
                                
                                // Criar URL absoluta completa com protocolo e domínio
                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                                $basePath = \App\Helpers\Url::to('/' . $relativePath);
                                $mediaUrl = $protocol . '://' . $host . $basePath;
                                
                                $mimetype = $payload['attachment']['mime'] ?? $attachmentMime ?? $contentType;
                                $filename = basename($tempFile);
                                $size = strlen($mediaData);
                                
                                Logger::quepasa("processWebhook - {$messageType} salvo em: {$tempFile}");
                                Logger::quepasa("processWebhook - Caminho relativo: {$relativePath}");
                                Logger::quepasa("processWebhook - URL pública absoluta: {$mediaUrl}");
                                
                                // Marcar que o arquivo já está salvo localmente (não precisa baixar novamente)
                                $downloadedFile = [
                                    'path' => $relativePath,
                                    'local_path' => $tempFile,
                                    'url' => $mediaUrl,
                                    'mime_type' => $mimetype,
                                    'filename' => $filename,
                                    'size' => $size
                                ];
                                
                                $downloaded = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$downloaded) {
                        Logger::quepasa("processWebhook - ❌ Não foi possível baixar o {$messageType}. Tentativas: " . count($endpoints));
                    }
                } catch (\Exception $e) {
                    Logger::quepasa("processWebhook - Erro ao baixar {$messageType}: " . $e->getMessage());
                }
            }

            // Formatar $mediaUrl para log (pode ser array se for metadados de link)
            $mediaUrlForLog = is_string($mediaUrl) ? $mediaUrl : (is_array($mediaUrl) ? 'ARRAY[' . implode(',', array_keys($mediaUrl)) . ']' : 'NULL');
            Logger::quepasa("processWebhook - media detect: url=" . $mediaUrlForLog . ", mimetype=" . ($mimetype ?: 'NULL') . ", filename=" . ($filename ?: 'NULL') . ", size=" . ($size ?: 'NULL') . ", messageType={$messageType}");
            $quotedMsg = $quepasaData['quotedMsg'] ?? null;
            $quotedExternalId = $quotedMsg['id']
                ?? ($payload['quoted'] ?? $payload['quoted_message_id'] ?? null)
                ?? ($payload['quotedMsgId'] ?? $payload['quotedMessageId'] ?? null)
                ?? ($quepasaData['quotedMsgId'] ?? $quepasaData['quotedMessageId'] ?? null)
                ?? (($quepasaData['contextInfo']['stanzaId'] ?? null) ?? ($payload['contextInfo']['stanzaId'] ?? null))
                ?? ($payload['inreply'] ?? null); // campo observado no log
            $quotedMessageText = $quotedMsg['body'] ?? ($payload['quoted_text'] ?? $quepasaData['quotedText'] ?? null);
            $quotedMessageId = null;
            $quotedSenderName = null;
            if ($quotedExternalId) {
                $quotedLocal = \App\Models\Message::findByExternalId($quotedExternalId);
                Logger::quepasa("processWebhook - Reply detectado: quotedExternalId={$quotedExternalId}, localFound=" . ($quotedLocal ? 'yes' : 'no'));
                if ($quotedLocal) {
                    $quotedMessageId = $quotedLocal['id'];
                    // Se texto citado não veio no payload, usar texto local
                    if (empty($quotedMessageText) && !empty($quotedLocal['content'])) {
                        $quotedMessageText = $quotedLocal['content'];
                    }
                    // Definir remetente citado
                    if ($quotedLocal['sender_type'] === 'agent') {
                        $sender = \App\Models\User::find($quotedLocal['sender_id']);
                        $quotedSenderName = $sender['name'] ?? 'Agente';
                    } else {
                        $contactQuoted = \App\Models\Contact::find($quotedLocal['sender_id']);
                        $quotedSenderName = $contactQuoted['name'] ?? 'Contato';
                    }
                }
            }
            $location = null;
            if (isset($quepasaData['latitude']) && isset($quepasaData['longitude'])) {
                $location = [
                    'latitude' => $quepasaData['latitude'],
                    'longitude' => $quepasaData['longitude'],
                    'name' => $quepasaData['body'] ?? null,
                    'address' => $quepasaData['body'] ?? null
                ];
            }
            
            // Se não tem texto mas tem caption, usar caption como conteúdo
            if (empty($message) && !empty($payload['caption'])) {
                $message = $payload['caption'];
            }
            
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

            Logger::quepasa("processWebhook - Processando mensagem: fromPhone={$fromPhone}, message=" . substr($message, 0, 100) . ", messageId={$messageId}, isGroup=" . ($isGroup ? 'true' : 'false') . ", isMessageFromConnectedNumber=" . ($isMessageFromConnectedNumber ? 'true' : 'false'));
            Logger::quepasa("processWebhook - Campos de detecção: chatid={$chatid}, from={$from}, fromme=" . ($payload['fromme'] ?? 'null') . ", participant=" . ($payload['participant'] ?? 'null'));
            
            if (!$fromPhone || (empty($message) && !$mediaUrl && empty($location))) {
                Logger::error("WhatsApp webhook: dados incompletos (fromPhone: " . ($fromPhone ?? 'NULL') . ", message: " . ($message ?? 'NULL') . ", mediaUrl: " . ($mediaUrl ?? 'NULL') . ")");
                return;
            }

            // Se mensagem foi ENVIADA do número conectado, tratar como mensagem outgoing
            if ($isMessageFromConnectedNumber) {
                Logger::quepasa("processWebhook - Mensagem ENVIADA do número conectado detectada. Tratando como mensagem outgoing.");
                
                // Log completo do payload para debug
                Logger::quepasa("processWebhook - Payload completo (mensagem enviada): " . json_encode($payload, JSON_PRETTY_PRINT));
                
                // Tentar obter número real do destinatário de vários campos possíveis
                $realRecipientPhone = null;
                
                // 1. Verificar campo 'to' (destinatário)
                $to = $payload['to'] ?? $quepasaData['to'] ?? null;
                if ($to) {
                    $realRecipientPhone = self::normalizePhoneNumber($to);
                    Logger::quepasa("processWebhook - Número real encontrado em 'to': {$realRecipientPhone}");
                }
                
                // 2. Verificar campo 'chat.phone' (pode ter número real)
                if (!$realRecipientPhone && isset($payload['chat']['phone'])) {
                    $realRecipientPhone = self::normalizePhoneNumber($payload['chat']['phone']);
                    Logger::quepasa("processWebhook - Número real encontrado em 'chat.phone': {$realRecipientPhone}");
                }
                
                // 3. Verificar campo 'participant.phone' (em grupos ou conversas)
                if (!$realRecipientPhone && isset($payload['participant']['phone'])) {
                    $realRecipientPhone = self::normalizePhoneNumber($payload['participant']['phone']);
                    Logger::quepasa("processWebhook - Número real encontrado em 'participant.phone': {$realRecipientPhone}");
                }
                
                // 4. Se from é @lid, tentar buscar número real via API do Quepasa
                if (!$realRecipientPhone && str_ends_with($from, '@lid')) {
                    Logger::quepasa("processWebhook - 'from' é @lid ({$from}), tentando buscar número real via API Quepasa...");
                    try {
                        $realRecipientPhone = self::getPhoneFromLinkedId($account, $from);
                        if ($realRecipientPhone) {
                            Logger::quepasa("processWebhook - Número real obtido via API: {$realRecipientPhone}");
                        } else {
                            Logger::quepasa("processWebhook - Não foi possível obter número real via API para {$from}");
                        }
                    } catch (\Exception $e) {
                        Logger::quepasa("processWebhook - Erro ao buscar número real via API: " . $e->getMessage());
                    }
                }
                
                // 5. Se ainda não tem número real, verificar se fromPhone é válido (não é só @lid ou @g.us)
                if (!$realRecipientPhone) {
                    // Se fromPhone termina com @lid ou @g.us e não conseguimos obter número real, ignorar
                    if (str_ends_with($from, '@lid') || str_ends_with($from, '@g.us') || str_ends_with($from, '@c.us')) {
                        Logger::quepasa("processWebhook - Não foi possível identificar número real do destinatário (from={$from}). Ignorando mensagem enviada do número conectado.");
                        return; // Ignorar mensagem se não conseguir identificar destinatário
                    }
                    // Se não é @lid/@g.us, usar o fromPhone normalizado
                    $realRecipientPhone = $fromPhone;
                    Logger::quepasa("processWebhook - Usando fromPhone normalizado como fallback: {$realRecipientPhone}");
                }
                
                // Validar se número real é válido (deve ter pelo menos 10 dígitos)
                if (strlen($realRecipientPhone) < 10 || !preg_match('/^\d+$/', $realRecipientPhone)) {
                    Logger::quepasa("processWebhook - Número destinatário inválido ({$realRecipientPhone}). Ignorando mensagem enviada do número conectado.");
                    return; // Ignorar se número não é válido
                }
                
                // Usar número real encontrado
                $recipientPhone = $realRecipientPhone;
                Logger::quepasa("processWebhook - Número destinatário final: {$recipientPhone} (original from: {$from})");
                
                // Buscar contato pelo destinatário (número real)
                Logger::quepasa("processWebhook - Buscando contato destinatário pelo telefone normalizado: {$recipientPhone}");
                $contact = \App\Models\Contact::findByPhoneNormalized($recipientPhone);
                
                if (!$contact) {
                    // Criar contato para o destinatário se não existir
                    $contactName = $payload['chat']['title'] ?? $payload['chat']['name'] ?? $payload['name'] ?? $recipientPhone;
                    $whatsappId = $payload['from'] ?? $from; // Manter o @lid original no whatsapp_id
                    if (!str_ends_with($whatsappId, '@s.whatsapp.net') && !str_ends_with($whatsappId, '@lid') && !str_ends_with($whatsappId, '@c.us')) {
                        $whatsappId .= '@s.whatsapp.net';
                    }
                    
                    $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($recipientPhone);
                    Logger::quepasa("processWebhook - Criando contato destinatário: name={$contactName}, phone={$normalizedPhone}, whatsapp_id={$whatsappId}");
                    $contactId = \App\Models\Contact::create([
                        'name' => $contactName,
                        'phone' => $normalizedPhone,
                        'whatsapp_id' => $whatsappId,
                        'email' => null
                    ]);
                    $contact = \App\Models\Contact::find($contactId);
                }
                
                // Buscar conversa existente com esse contato
                Logger::quepasa("processWebhook - Buscando conversa existente: contact_id={$contact['id']}, channel=whatsapp, account_id={$account['id']}");
                $conversation = \App\Models\Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
                
                if (!$conversation) {
                    Logger::quepasa("processWebhook - Conversa não encontrada, criando nova para mensagem enviada...");
                    try {
                        $conversationData = [
                            'contact_id' => $contact['id'],
                            'channel' => 'whatsapp',
                            'whatsapp_account_id' => $account['id']
                        ];
                        
                        // Adicionar funil e estágio padrão da integração, se configurados
                        if (!empty($account['default_funnel_id'])) {
                            $conversationData['funnel_id'] = $account['default_funnel_id'];
                        }
                        if (!empty($account['default_stage_id'])) {
                            $conversationData['stage_id'] = $account['default_stage_id'];
                        }
                        
                        $conversation = \App\Services\ConversationService::create($conversationData);
                    } catch (\Exception $e) {
                        Logger::quepasa("Erro ao criar conversa via ConversationService: " . $e->getMessage());
                        $conversationId = \App\Models\Conversation::create([
                            'contact_id' => $contact['id'],
                            'channel' => 'whatsapp',
                            'whatsapp_account_id' => $account['id'],
                            'status' => 'open'
                        ]);
                        $conversation = \App\Models\Conversation::find($conversationId);
                    }
                }
                
                // Processar anexos/mídia
                $attachments = [];
                if ($mediaUrl) {
                    try {
                        // Se o arquivo já foi baixado via API, preparar attachment como array
                        if (isset($downloadedFile) && !empty($downloadedFile['local_path'])) {
                            Logger::quepasa("processWebhook - Preparando attachment OUTGOING de arquivo já baixado: {$downloadedFile['local_path']}");
                            
                            // Detectar tipo de mídia baseado no mime type
                            $attachmentType = 'document';
                            $attachmentMimeType = $downloadedFile['mime_type'] ?? $mimetype;
                            if ($attachmentMimeType) {
                                if (strpos($attachmentMimeType, 'audio') !== false) $attachmentType = 'audio';
                                elseif (strpos($attachmentMimeType, 'image') !== false) $attachmentType = 'image';
                                elseif (strpos($attachmentMimeType, 'video') !== false) $attachmentType = 'video';
                            }
                            
                            // Extrair extensão do filename
                            $attachmentFilename = $downloadedFile['filename'] ?? $filename;
                            $attachmentExtension = pathinfo($attachmentFilename, PATHINFO_EXTENSION) ?: 'bin';
                            
                            // Attachments são arrays que vão no campo JSON da mensagem
                            $attachment = [
                                'path' => $downloadedFile['path'],
                                'type' => $attachmentType,
                                'mime_type' => $attachmentMimeType,
                                'mimetype' => $attachmentMimeType,
                                'size' => $downloadedFile['size'] ?? $size,
                                'filename' => $attachmentFilename,
                                'extension' => $attachmentExtension,
                                'url' => $downloadedFile['url']
                            ];
                            Logger::quepasa("processWebhook - ✅ Attachment OUTGOING preparado ({$attachmentType}): " . json_encode($attachment));
                            $attachments[] = $attachment;
                        } else {
                            $attachment = \App\Services\AttachmentService::saveFromUrl(
                                $mediaUrl, 
                                $conversation['id'], 
                                $filename
                            );
                            if (!empty($attachment)) {
                                if ($mimetype) $attachment['mime_type'] = $mimetype;
                                if ($size) $attachment['size'] = $size;
                            }
                            $attachments[] = $attachment;
                        }
                    } catch (\Exception $e) {
                        Logger::quepasa("Erro ao salvar mídia do WhatsApp: " . $e->getMessage());
                    }
                }
                
                // Criar mensagem como OUTGOING (enviada pelo sistema/agente)
                // Usar usuário atual ou usuário padrão do sistema
                $userId = \App\Helpers\Auth::id();
                if (!$userId) {
                    // Se não tem usuário logado, buscar primeiro admin/agente ativo
                    $defaultUser = \App\Helpers\Database::fetch(
                        "SELECT id FROM users WHERE status = 'active' AND role IN ('admin', 'super_admin', 'agent') ORDER BY id ASC LIMIT 1"
                    );
                    $userId = $defaultUser['id'] ?? null;
                }
                
                Logger::quepasa("processWebhook - Criando mensagem OUTGOING: conversation_id={$conversation['id']}, sender_type=agent, sender_id={$userId}");
                
                try {
                    $messageId = \App\Services\ConversationService::sendMessage(
                        $conversation['id'],
                        $message ?: '',
                        'agent', // sender_type = agent (outgoing)
                        $userId, // sender_id = usuário atual ou padrão
                        $attachments
                    );
                    
                    Logger::quepasa("processWebhook - Mensagem OUTGOING criada com sucesso: messageId={$messageId}");
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao criar mensagem outgoing via ConversationService: " . $e->getMessage());
                    // Fallback: criar mensagem diretamente
                    $messageData = [
                        'conversation_id' => $conversation['id'],
                        'sender_type' => 'agent',
                        'sender_id' => $userId,
                        'content' => $message ?: '',
                        'message_type' => !empty($attachments) ? ($attachments[0]['type'] ?? $messageType ?? 'text') : ($messageType ?? 'text'),
                        'external_id' => $messageId,
                        'quoted_message_id' => $quotedMessageId ?? null,
                        'quoted_text' => $quotedMessageText ?? null,
                        'quoted_sender_name' => $quotedSenderName ?? null
                    ];
                    
                    if ($location) {
                        $messageData['content'] = json_encode($location);
                        $messageData['message_type'] = 'location';
                    }
                    
                    if (!empty($attachments)) {
                        $messageData['attachments'] = $attachments;
                    }
                    
                    $messageId = \App\Models\Message::createMessage($messageData);
                }
                
                Logger::quepasa("processWebhook - Mensagem ENVIADA processada com sucesso: fromPhone={$fromPhone}, message={$message}, conversationId={$conversation['id']}");
                return; // Sair aqui, não processar como mensagem recebida
            }

            // Se chegou aqui, é mensagem RECEBIDA (não enviada do número conectado)
            // Criar ou atualizar contato
            Logger::quepasa("processWebhook - Buscando contato pelo telefone normalizado: {$fromPhone}");
            
            // Verificar se é um número real (não é LID) e se há conversas recentes com LID deste remetente
            $contact = null;
            $isRealNumber = !str_ends_with($from, '@lid');
            
            if ($isRealNumber && strlen($fromPhone) >= 10) {
                // É um número real, buscar normalmente
                Logger::quepasa("processWebhook - É número real (não LID), buscando por phone={$fromPhone}");
                $contact = \App\Models\Contact::findByPhoneNormalized($fromPhone);
                
                // Se não encontrou por número, verificar se há contato com LID para esta conta
                if (!$contact) {
                    Logger::quepasa("processWebhook - Contato com número real não encontrado, verificando se há LID ativo...");
                    
                    // Buscar conversas recentes (últimas 24h) desta conta WhatsApp que tenham contatos com @lid
                    try {
                        $sql = "SELECT DISTINCT c.*, MAX(conv.updated_at) as last_conversation 
                                FROM contacts c
                                INNER JOIN conversations conv ON conv.contact_id = c.id
                                WHERE conv.whatsapp_account_id = :account_id
                                AND c.whatsapp_id LIKE '%@lid'
                                AND conv.updated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                                GROUP BY c.id
                                ORDER BY last_conversation DESC
                                LIMIT 10";
                        
                        $lidContacts = \App\Helpers\Database::fetchAll($sql, [
                            $account['id']
                        ]);
                        
                        if (!empty($lidContacts)) {
                            Logger::quepasa("processWebhook - Encontrados " . count($lidContacts) . " contatos com LID nas últimas 24h");
                            
                            // Para cada contato LID, verificar se o nome é o mesmo
                            $payloadName = $payload['chat']['title'] ?? $payload['chat']['name'] ?? $payload['name'] ?? null;
                            
                            foreach ($lidContacts as $lidContact) {
                                // Comparar nomes (case-insensitive)
                                if ($payloadName && strcasecmp($lidContact['name'], $payloadName) === 0) {
                                    Logger::quepasa("processWebhook - ✅ Encontrado contato LID com mesmo nome: ID={$lidContact['id']}, name={$lidContact['name']}, whatsapp_id={$lidContact['whatsapp_id']}");
                                    Logger::quepasa("processWebhook - 🔄 Atualizando contato LID com número real: {$fromPhone}");
                                    
                                    // Atualizar contato LID com número real
                                    \App\Models\Contact::update($lidContact['id'], [
                                        'phone' => $fromPhone,
                                        'whatsapp_id' => $from
                                    ]);
                                    
                                    $contact = \App\Models\Contact::find($lidContact['id']);
                                    Logger::quepasa("processWebhook - ✅ Contato atualizado de LID para número real");
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::quepasa("processWebhook - Erro ao buscar contatos LID: " . $e->getMessage());
                    }
                }
            } else {
                // É um LID, buscar normalmente
                Logger::quepasa("processWebhook - É LID, buscando por phone={$fromPhone}");
                $contact = \App\Models\Contact::findByPhoneNormalized($fromPhone);
            }
            
            // Extrair nome do contato do payload (chat.title)
            $contactName = $payload['chat']['title'] ?? $payload['chat']['name'] ?? $payload['name'] ?? null;
            if (!$contactName && isset($payload['chat']['phone'])) {
                // Se não tem nome, usar telefone formatado
                $contactName = $payload['chat']['phone'];
            }
            if (!$contactName) {
                $contactName = $fromPhone; // Fallback para telefone
            }
            
            if (!$contact) {
                // Usar o $from que já foi processado anteriormente (linha ~1176), que preserva o sufixo correto (@lid, @s.whatsapp.net, etc)
                $whatsappId = $from;
                
                Logger::quepasa("processWebhook - whatsapp_id do campo 'from' processado: '{$whatsappId}'");
                
                // Se é um LID (@lid), SEMPRE tentar resolver para número real primeiro
                $realPhone = null;
                $resolvedFromLid = false;
                
                if (str_ends_with($whatsappId, '@lid')) {
                    Logger::quepasa("processWebhook - ⚠️ Detectado LID: {$whatsappId}, TENTANDO RESOLVER para número real...");
                    
                    try {
                        $realPhone = self::getPhoneFromLinkedId($account, $whatsappId);
                        
                        if ($realPhone && strlen($realPhone) >= 10) {
                            Logger::quepasa("processWebhook - ✅ Número real RESOLVIDO via API: {$realPhone}");
                            $resolvedFromLid = true;
                            
                            // Buscar se já existe contato com esse número real
                            $existingContact = \App\Models\Contact::findByPhoneNormalized($realPhone);
                            if ($existingContact) {
                                Logger::quepasa("processWebhook - ✅ Contato já existe com número real: ID={$existingContact['id']}, phone={$existingContact['phone']}");
                                
                                // Atualizar whatsapp_id do contato existente se necessário
                                // Preferir @s.whatsapp.net sobre @lid
                                $realWhatsappId = $realPhone . '@s.whatsapp.net';
                                if ($existingContact['whatsapp_id'] !== $realWhatsappId) {
                                    Logger::quepasa("processWebhook - Atualizando whatsapp_id do contato existente para: {$realWhatsappId}");
                                    \App\Models\Contact::update($existingContact['id'], ['whatsapp_id' => $realWhatsappId]);
                                }
                                
                                // Usar o contato existente ao invés de criar um novo
                                $contact = $existingContact;
                                $contact['whatsapp_id'] = $realWhatsappId;
                            } else {
                                Logger::quepasa("processWebhook - Contato não existe, CRIAR COM NÚMERO REAL: {$realPhone}");
                                $fromPhone = $realPhone; // Usar o número real ao invés do LID
                                $whatsappId = $realPhone . '@s.whatsapp.net'; // Usar formato padrão
                            }
                        } else {
                            Logger::quepasa("processWebhook - ⚠️ API não retornou número real válido. Tentando extrair do payload...");
                            
                            // Tentar extrair número de outros campos do payload
                            $possiblePhone = null;
                            
                            // Verificar se há número no chat.phone
                            if (isset($payload['chat']['phone'])) {
                                $possiblePhone = self::normalizePhoneNumber($payload['chat']['phone']);
                                Logger::quepasa("processWebhook - Encontrado phone em chat.phone: {$possiblePhone}");
                            }
                            
                            // Verificar chat.jid
                            if (!$possiblePhone && isset($payload['chat']['jid'])) {
                                $possiblePhone = self::normalizePhoneNumber($payload['chat']['jid']);
                                if (!str_contains($possiblePhone, '@lid')) {
                                    Logger::quepasa("processWebhook - Encontrado phone em chat.jid: {$possiblePhone}");
                                } else {
                                    $possiblePhone = null;
                                }
                            }
                            
                            if ($possiblePhone && strlen($possiblePhone) >= 10 && !str_contains($possiblePhone, '@lid')) {
                                Logger::quepasa("processWebhook - ✅ Número real extraído do payload: {$possiblePhone}");
                                $realPhone = $possiblePhone;
                                $resolvedFromLid = true;
                                $fromPhone = $realPhone;
                                $whatsappId = $realPhone . '@s.whatsapp.net';
                                
                                // Verificar se já existe contato
                                $existingContact = \App\Models\Contact::findByPhoneNormalized($realPhone);
                                if ($existingContact) {
                                    Logger::quepasa("processWebhook - ✅ Contato já existe: ID={$existingContact['id']}");
                                    $contact = $existingContact;
                                }
                            } else {
                                Logger::quepasa("processWebhook - ❌ NÃO FOI POSSÍVEL resolver LID. Criando contato temporário com LID.");
                                Logger::quepasa("processWebhook - ⚠️ ATENÇÃO: Este contato será atualizado quando o número real for revelado.");
                            }
                        }
                    } catch (\Exception $e) {
                        Logger::quepasa("processWebhook - Erro ao resolver LID: " . $e->getMessage());
                    }
                }
                
                // Se ainda não encontrou o contato, criar um novo
                if (!$contact) {
                    // Só adicionar sufixo se não tiver nenhum
                    if (!str_ends_with($whatsappId, '@s.whatsapp.net') && 
                        !str_ends_with($whatsappId, '@lid') && 
                        !str_ends_with($whatsappId, '@g.us') &&
                        !str_ends_with($whatsappId, '@c.us')) {
                        $whatsappId .= '@s.whatsapp.net';
                        Logger::quepasa("processWebhook - Adicionado sufixo @s.whatsapp.net ao whatsapp_id");
                    }
                    
                    Logger::quepasa("processWebhook - whatsapp_id final: '{$whatsappId}'");
                    
                    // Garantir que salvamos o número normalizado
                    // ⚠️ Ignorar contatos do sistema (ex: mensagens automáticas do WhatsApp)
                    if ($whatsappId === 'system' || $whatsappId === '0' || empty($whatsappId)) {
                        Logger::quepasa("processWebhook - Ignorando contato do sistema: whatsapp_id={$whatsappId}");
                        return;
                    }
                    
                    $normalizedPhone = \App\Models\Contact::normalizePhoneNumber($fromPhone);
                    
                    // ⚠️ Ignorar se o telefone normalizado for 'system' ou inválido
                    if ($normalizedPhone === 'system' || $normalizedPhone === '0' || empty($normalizedPhone)) {
                        Logger::quepasa("processWebhook - Ignorando contato do sistema: phone={$normalizedPhone}");
                        return;
                    }
                    
                    Logger::quepasa("processWebhook - Criando novo contato: name={$contactName}, phone={$normalizedPhone} (normalizado de {$fromPhone}), whatsapp_id={$whatsappId}");
                    $contactId = \App\Models\Contact::create([
                        'name' => $contactName,
                        'phone' => $normalizedPhone, // Salvar sempre normalizado
                        'whatsapp_id' => $whatsappId,
                        'email' => null
                    ]);
                    $contact = \App\Models\Contact::find($contactId);
                }
                
                Logger::quepasa("processWebhook - Contato criado: ID={$contactId}");
                
                // Tentar buscar avatar usando Quepasa (rota instances/{instanceId}/contacts/{number}/photo), depois fallback
                try {
                    $chatId = $payload['chat']['id'] ?? null;
                    $avatarUrl = $payload['chat']['picture'] ?? $payload['chat']['avatar'] ?? $payload['avatar'] ?? null;
                    
                    // Primeira tentativa: rota Quepasa com instance_id + phone/chat
                    \App\Helpers\Logger::quepasa("processWebhook - Tentando avatar via Quepasa (novo contato): chatId={$chatId}, phone={$fromPhone}");
                    $avatarFetched = \App\Services\ContactService::fetchQuepasaAvatar($contact['id'], $account, $chatId, $fromPhone);
                    
                    if ($avatarFetched) {
                        \App\Helpers\Logger::quepasa("processWebhook - Avatar via Quepasa obtido para contato {$contact['id']}");
                    } elseif ($avatarUrl) {
                        Logger::quepasa("processWebhook - Avatar encontrado no payload: {$avatarUrl}");
                        \App\Services\ContactService::downloadAvatarFromUrl($contact['id'], $avatarUrl);
                    } elseif ($chatId) {
                        \App\Services\ContactService::fetchWhatsAppAvatarByChatId($contact['id'], $account['id'], $chatId);
                    } else {
                        \App\Services\ContactService::fetchWhatsAppAvatar($contact['id'], $account['id']);
                    }
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao buscar avatar: " . $e->getMessage());
                }
            } else {
                // Atualizar whatsapp_id se vier diferente do armazenado (ex: @lid vs @s.whatsapp.net)
                // Usar o $from que já foi processado anteriormente, que preserva o sufixo correto
                $currentWhatsappId = $from;
                Logger::quepasa("processWebhook - Verificando whatsapp_id: atual='{$contact['whatsapp_id']}', from='{$currentWhatsappId}'");
                
                // Se vier whatsapp_id diferente, atualizar (importante para @lid)
                // SEMPRE atualizar se vier do payload e for diferente
                if (!empty($currentWhatsappId) && $currentWhatsappId !== $contact['whatsapp_id']) {
                    Logger::quepasa("processWebhook - ✅ Atualizando whatsapp_id do contato {$contact['id']}: '{$contact['whatsapp_id']}' -> '{$currentWhatsappId}'");
                    \App\Models\Contact::update($contact['id'], ['whatsapp_id' => $currentWhatsappId]);
                    $contact['whatsapp_id'] = $currentWhatsappId;
                } elseif (empty($contact['whatsapp_id']) && !empty($currentWhatsappId)) {
                    // Se o contato não tem whatsapp_id mas veio no payload, adicionar
                    Logger::quepasa("processWebhook - ✅ Adicionando whatsapp_id ao contato {$contact['id']}: '{$currentWhatsappId}'");
                    \App\Models\Contact::update($contact['id'], ['whatsapp_id' => $currentWhatsappId]);
                    $contact['whatsapp_id'] = $currentWhatsappId;
                } else {
                    Logger::quepasa("processWebhook - whatsapp_id já está correto");
                }
                
                // Atualizar nome APENAS se:
                // 1. Veio no payload (chat.title)
                // 2. O contato não tem nome cadastrado (nome igual ao telefone)
                if ($contactName && !empty($payload['chat']['title'])) {
                    // Verificar se o nome atual é igual ao telefone (nome padrão/não cadastrado)
                    $currentNameIsPhone = ($contact['name'] === $contact['phone'] || 
                                          $contact['name'] === $fromPhone ||
                                          preg_match('/^[0-9\s\+\-\(\)]+$/', $contact['name']) === 1);
                    
                    if ($currentNameIsPhone && $contact['name'] !== $contactName) {
                        Logger::quepasa("processWebhook - Atualizando nome do contato (sem nome cadastrado): {$contact['name']} -> {$contactName}");
                        \App\Models\Contact::update($contact['id'], ['name' => $contactName]);
                        $contact['name'] = $contactName;
                    }
                }
                
                // Se contato existe mas não tem avatar, tentar buscar usando Quepasa (rota instance/contact/photo) ou chat.id
                if (empty($contact['avatar'])) {
                    try {
                        $chatId = $payload['chat']['id'] ?? null;
                        $avatarUrl = $payload['chat']['picture'] ?? $payload['chat']['avatar'] ?? $payload['avatar'] ?? null;
                        
                        \App\Helpers\Logger::quepasa("processWebhook - Tentando avatar via Quepasa (contato existente): chatId={$chatId}, phone={$fromPhone}");
                        $avatarFetched = \App\Services\ContactService::fetchQuepasaAvatar($contact['id'], $account, $chatId, $fromPhone);
                        
                        if ($avatarFetched) {
                            \App\Helpers\Logger::quepasa("processWebhook - Avatar via Quepasa obtido para contato {$contact['id']}");
                        } elseif ($avatarUrl) {
                            Logger::quepasa("processWebhook - Avatar encontrado no payload: {$avatarUrl}");
                            \App\Services\ContactService::downloadAvatarFromUrl($contact['id'], $avatarUrl);
                        } elseif ($chatId) {
                            \App\Services\ContactService::fetchWhatsAppAvatarByChatId($contact['id'], $account['id'], $chatId);
                        } else {
                            \App\Services\ContactService::fetchWhatsAppAvatar($contact['id'], $account['id']);
                        }
                    } catch (\Exception $e) {
                        Logger::quepasa("Erro ao buscar avatar: " . $e->getMessage());
                    }
                }
            }

            // ⚠️ VALIDAÇÃO FINAL: Não criar conversa se contato tiver phone = 'system'
            if (isset($contact['phone']) && ($contact['phone'] === 'system' || $contact['phone'] === '0')) {
                Logger::quepasa("processWebhook - ⚠️ Abortando: Contato com phone do sistema (phone={$contact['phone']}, id={$contact['id']})");
                return;
            }

            // Criar ou buscar conversa (proteção contra duplicatas; se falhar lock, usa fallback sem transação)
            $conversation = null;
            $isNewConversation = false;
            $shouldReopenAsNew = false;
            $db = \App\Helpers\Database::getInstance();
            $usedLock = false;
            
            Logger::quepasa("🔍 DEBUG: Iniciando criação/busca de conversa para fromPhone={$fromPhone}, message length=" . strlen($message));
            try {
                if (method_exists($db, 'beginTransaction')) {
                    $db->beginTransaction();
                    $usedLock = true;
                    
                    // Lock no contato para serializar criação de conversas desse contato
                    $db->query("SELECT id FROM contacts WHERE id = :id FOR UPDATE", ['id' => $contact['id']]);
                    
                    Logger::quepasa("processWebhook - Buscando conversa existente (com lock): contact_id={$contact['id']}, channel=whatsapp, account_id={$account['id']}");
                    $conversation = \App\Models\Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
                    Logger::quepasa("processWebhook - Resultado da busca: " . ($conversation ? "Encontrada (ID={$conversation['id']}, status={$conversation['status']})" : "Não encontrada"));
                }
            } catch (\Throwable $e) {
                Logger::quepasa("processWebhook - ⚠️ Falha ao aplicar lock no contato, seguindo sem transação. Erro: " . $e->getMessage());
                $usedLock = false;
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
            }
            
            // Se não encontrou (ou não conseguiu lock), buscar normalmente
            if (!$conversation) {
                if (!$usedLock) {
                    Logger::quepasa("processWebhook - Buscando conversa existente (sem lock): contact_id={$contact['id']}, channel=whatsapp, account_id={$account['id']}");
                    $conversation = \App\Models\Conversation::findByContactAndChannel($contact['id'], 'whatsapp', $account['id']);
                }
            }
            
            // Se ainda não existir, criar (usar transação se lock ativo, senão criar direto)
            if (!$conversation) {
                // Trava: não criar conversa se a primeira mensagem for exatamente o nome do contato (caso sem mídia)
                Logger::quepasa("🔍 DEBUG: Verificando se deve ignorar mensagem... messageType={$messageType}, mediaUrl=" . ($mediaUrl ? 'EXISTS' : 'NULL') . ", message='" . substr($message, 0, 50) . "', contact_name='" . ($contact['name'] ?? 'NULL') . "'");
                $shouldIgnoreFirstMessage = $messageType === 'text'
                    && empty($mediaUrl)
                    && \App\Services\ConversationService::isFirstMessageContactName((string)$message, $contact['name'] ?? null);

                Logger::quepasa("🔍 DEBUG: shouldIgnoreFirstMessage=" . ($shouldIgnoreFirstMessage ? 'TRUE' : 'FALSE'));
                
                if ($shouldIgnoreFirstMessage) {
                    $contactName = trim((string)($contact['name'] ?? ''));
                    Logger::quepasa("processWebhook - ❌ Ignorando criação de conversa: primeira mensagem igual ao nome do contato ({$contactName})");
                    if ($usedLock && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    return;
                }

                $logMessage = $shouldReopenAsNew 
                    ? "processWebhook - Criando NOVA conversa (reabertura após período de graça)..."
                    : "processWebhook - Conversa não encontrada, criando nova...";
                Logger::quepasa($logMessage);
                
                try {
                    $conversationData = [
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'whatsapp_account_id' => $account['id']
                    ];
                    
                    if (!empty($account['default_funnel_id'])) {
                        $conversationData['funnel_id'] = $account['default_funnel_id'];
                        Logger::quepasa("processWebhook - Usando funil padrão da integração: {$account['default_funnel_id']}");
                    }
                    if (!empty($account['default_stage_id'])) {
                        $conversationData['stage_id'] = $account['default_stage_id'];
                        Logger::quepasa("processWebhook - Usando estágio padrão da integração: {$account['default_stage_id']}");
                    }
                    
                    $conversation = \App\Services\ConversationService::create($conversationData, false);
                    $isNewConversation = true;
                    Logger::quepasa("processWebhook - 🆕 CONVERSA CRIADA via ConversationService: ID={$conversation['id']}, isNewConversation=TRUE (automações serão executadas após salvar mensagem)");
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao criar conversa via ConversationService: " . $e->getMessage());
                    Logger::quepasa("Stack trace: " . $e->getTraceAsString());
                    try {
                        $conversationId = \App\Models\Conversation::create([
                            'contact_id' => $contact['id'],
                            'channel' => 'whatsapp',
                            'whatsapp_account_id' => $account['id'],
                            'status' => 'open'
                        ]);
                        $conversation = \App\Models\Conversation::find($conversationId);
                        $isNewConversation = true;
                        Logger::quepasa("processWebhook - Conversa criada via fallback: ID={$conversationId}");
                    } catch (\Exception $e2) {
                        Logger::error("Erro ao criar conversa via fallback: " . $e2->getMessage());
                        if ($usedLock && $db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $e2;
                    }
                }
            }
            
            if ($usedLock && $db->inTransaction()) {
                $db->commit();
            }

            // Trava: não criar conversa se a primeira mensagem for exatamente o nome do contato (caso sem mídia)
            $shouldIgnoreFirstMessage = !$conversation
                && $messageType === 'text'
                && empty($mediaUrl)
                && \App\Services\ConversationService::isFirstMessageContactName((string)$message, $contact['name'] ?? null);

            if ($shouldIgnoreFirstMessage) {
                $contactName = trim((string)($contact['name'] ?? ''));
                Logger::quepasa("processWebhook - Ignorando criação de conversa: primeira mensagem igual ao nome do contato ({$contactName})");
                return;
            }
            
            // Verificar se conversa está fechada/resolvida e se deve reabrir
            if ($conversation && in_array($conversation['status'], ['closed', 'resolved'])) {
                Logger::quepasa("========================================");
                Logger::quepasa("🔄 REABERTURA AUTOMÁTICA - INÍCIO");
                Logger::quepasa("========================================");
                Logger::quepasa("📋 Conversa ID: {$conversation['id']}");
                Logger::quepasa("📊 Status atual: {$conversation['status']}");
                Logger::quepasa("🕐 Updated_at: {$conversation['updated_at']}");
                
                // Obter período de graça das configurações (padrão: 10 minutos)
                // ATENÇÃO: Período de graça agora é o tempo MÍNIMO para reabrir
                $gracePeriodMinutes = (int)\App\Models\Setting::get('conversation_reopen_grace_period_minutes', 10);
                Logger::quepasa("⚙️  Período mínimo para reabertura: {$gracePeriodMinutes} minutos");
                
                // Calcular tempo desde última atualização
                $updatedAt = strtotime($conversation['updated_at']);
                $now = time();
                $minutesSinceClosure = ($now - $updatedAt) / 60;
                
                Logger::quepasa("⏱️  Tempo desde fechamento: " . round($minutesSinceClosure, 2) . " minutos");
                Logger::quepasa("🔢 Cálculo: {$minutesSinceClosure} >= {$gracePeriodMinutes} ?");
                
                if ($minutesSinceClosure >= $gracePeriodMinutes) {
                    // Passou do período mínimo - REABRIR como NOVA conversa
                    Logger::quepasa("✅ SIM → Tempo suficiente passou");
                    Logger::quepasa("🔄 Ação: REABRIR como NOVA conversa (aplicar regras completas)");
                    Logger::quepasa("   - Auto-atribuição: SIM");
                    Logger::quepasa("   - Funil/Etapa padrão: SIM");
                    Logger::quepasa("   - Automações: SIM");
                    Logger::quepasa("========================================");
                    $conversation = null; // Forçar criação de nova conversa
                    $shouldReopenAsNew = true;
                } else {
                    // Dentro do período mínimo - NÃO reabrir (mensagem rápida tipo "ok")
                    Logger::quepasa("❌ NÃO → Dentro do período mínimo");
                    Logger::quepasa("🚫 Ação: NÃO reabrir conversa (ignorar reabertura)");
                    Logger::quepasa("   - Conversa continua: {$conversation['status']}");
                    Logger::quepasa("   - Mensagem será salva mas conversa NÃO reabre");
                    Logger::quepasa("   - Ideal para: 'Ok', 'Obrigado', confirmações rápidas");
                    Logger::quepasa("========================================");
                    // NÃO fazer nada - conversa continua fechada
                    // A mensagem será salva normalmente no processo abaixo
                }
            }
            
            if (!$conversation) {
                $logMessage = $shouldReopenAsNew 
                    ? "processWebhook - Criando NOVA conversa (reabertura após período de graça)..."
                    : "processWebhook - Conversa não encontrada, criando nova...";
                Logger::quepasa($logMessage);
                
                // Usar ConversationService para criar conversa (com todas as integrações)
                try {
                    $conversationData = [
                        'contact_id' => $contact['id'],
                        'channel' => 'whatsapp',
                        'whatsapp_account_id' => $account['id']
                    ];
                    
                    // Adicionar funil e estágio padrão da integração, se configurados
                    if (!empty($account['default_funnel_id'])) {
                        $conversationData['funnel_id'] = $account['default_funnel_id'];
                        Logger::quepasa("processWebhook - Usando funil padrão da integração: {$account['default_funnel_id']}");
                    }
                    if (!empty($account['default_stage_id'])) {
                        $conversationData['stage_id'] = $account['default_stage_id'];
                        Logger::quepasa("processWebhook - Usando estágio padrão da integração: {$account['default_stage_id']}");
                    }
                    
                    // Criar conversa mas NÃO executar automações ainda (executar depois de salvar mensagem)
                    $conversation = \App\Services\ConversationService::create($conversationData, false);
                    $isNewConversation = true;
                    Logger::quepasa("processWebhook - Conversa criada via ConversationService: ID={$conversation['id']} (automações serão executadas após salvar mensagem)");
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
                    // Se o arquivo já foi baixado via API, criar attachment diretamente
                    if (isset($downloadedFile) && !empty($downloadedFile['local_path'])) {
                        Logger::quepasa("processWebhook - Criando attachment a partir de arquivo já baixado: {$downloadedFile['local_path']}");
                        Logger::quepasa("processWebhook - downloadedFile: " . json_encode($downloadedFile));
                        
                        // Detectar tipo de mídia baseado no mime type
                        $attachmentType = 'document';
                        $attachmentMimeType = $downloadedFile['mime_type'] ?? $mimetype;
                        if ($attachmentMimeType) {
                            if (strpos($attachmentMimeType, 'audio') !== false) $attachmentType = 'audio';
                            elseif (strpos($attachmentMimeType, 'image') !== false) $attachmentType = 'image';
                            elseif (strpos($attachmentMimeType, 'video') !== false) $attachmentType = 'video';
                        }
                        
                        // Extrair extensão do filename
                        $attachmentFilename = $downloadedFile['filename'] ?? $filename;
                        $attachmentExtension = pathinfo($attachmentFilename, PATHINFO_EXTENSION) ?: 'bin';
                        
                        // Attachments não são salvos em tabela separada, são arrays que vão no campo JSON da mensagem
                        $attachment = [
                            'path' => $downloadedFile['path'],
                            'type' => $attachmentType,
                            'mime_type' => $attachmentMimeType,
                            'mimetype' => $attachmentMimeType,
                            'size' => $downloadedFile['size'] ?? $size,
                            'filename' => $attachmentFilename,
                            'extension' => $attachmentExtension,
                            'url' => $downloadedFile['url']
                        ];
                        Logger::quepasa("processWebhook - ✅ Attachment preparado para mensagem ({$attachmentType}): " . json_encode($attachment));
                        $attachments[] = $attachment;
                    } else {
                        // Arquivo externo (não baixado ainda), usar saveFromUrl
                        // ⚠️ Verificar se $mediaUrl é uma string válida (não array de metadados de link)
                        if (is_string($mediaUrl) && !empty($mediaUrl)) {
                            $attachment = \App\Services\AttachmentService::saveFromUrl(
                                $mediaUrl, 
                                $conversation['id'], 
                                $filename
                            );
                            // Enriquecer metadados do attachment se possível
                            if (!empty($attachment)) {
                                if ($mimetype) $attachment['mime_type'] = $mimetype;
                                if ($size) $attachment['size'] = $size;
                            }
                            $attachments[] = $attachment;
                        } else {
                            Logger::quepasa("processWebhook - ⚠️ mediaUrl inválido (array ou vazio), não é possível criar attachment: " . json_encode($mediaUrl));
                        }
                    }
                } catch (\Exception $e) {
                    Logger::quepasa("Erro ao salvar mídia do WhatsApp: " . $e->getMessage());
                }
            }

            // Log final de attachments antes de criar mensagem
            Logger::quepasa("processWebhook - Attachments preparados: " . count($attachments));
            if (!empty($attachments)) {
                foreach ($attachments as $idx => $att) {
                    Logger::quepasa("processWebhook - Attachment[{$idx}]: path=" . ($att['path'] ?? 'null') . ", type=" . ($att['type'] ?? 'null') . ", size=" . ($att['size'] ?? 'null'));
                }
            }
            
            // Se não há texto mas há anexos (ex: áudio), usar caractere invisível para não falhar validação
            if (empty($message) && !empty($attachments)) {
                $message = "\u{200B}";
                Logger::quepasa("processWebhook - Sem texto, usando caractere invisível. Attachments: " . count($attachments));
            }

            // Extrair external_id do payload antes de criar mensagem
            $externalId = $payload['id'] 
                ?? ($payload['message']['id'] ?? null)
                ?? ($payload['data']['id'] ?? null)
                ?? null;
            
            // Verificar se mensagem já existe (evitar duplicatas)
            if ($externalId) {
                Logger::quepasa("processWebhook - Verificando se mensagem já existe: external_id={$externalId}");
                $existingMessage = \App\Models\Message::findByExternalId($externalId);
                if ($existingMessage) {
                    Logger::quepasa("processWebhook - ⚠️ Mensagem já existe no banco (ID: {$existingMessage['id']}). Ignorando webhook duplicado.");
                    return; // Ignorar mensagem duplicada
                }
            } else {
                Logger::quepasa("processWebhook - ⚠️ Webhook sem external_id. Não é possível verificar duplicatas.");
            }
            
            // Criar mensagem usando ConversationService (com todas as integrações)
            Logger::quepasa("processWebhook - Preparando criação de mensagem: conversationId={$conversation['id']}, contactId={$contact['id']}, message='" . substr($message, 0, 50) . "', attachmentsCount=" . count($attachments) . ", timestamp=" . date('Y-m-d H:i:s', $timestamp));
            
            try {
                // Passar quoted_message_id para registrar reply corretamente
                // E passar timestamp original do WhatsApp para preservar ordem correta
                \App\Helpers\Logger::info("WhatsAppService::processWebhook - CHAMANDO ConversationService::sendMessage (conv={$conversation['id']}, contact={$contact['id']}, msgLen=" . strlen($message) . ", attachments=" . count($attachments) . ", quotedMessageId=" . ($quotedMessageId ?? 'NULL') . ")");
                
                $messageId = \App\Services\ConversationService::sendMessage(
                    $conversation['id'],
                    $message ?: '',
                    'contact',
                    $contact['id'],
                    $attachments,
                    null,              // messageType
                    $quotedMessageId,  // quoted_message_id
                    null,              // aiAgentId
                    $timestamp         // timestamp original da mensagem do WhatsApp
                );
                
                \App\Helpers\Logger::info("WhatsAppService::processWebhook - ConversationService::sendMessage RETORNOU messageId={$messageId}");
                
                if (!empty($externalId) && $messageId) {
                    Logger::quepasa("processWebhook - Salvando external_id: externalId={$externalId}, messageId={$messageId}");
                    \App\Models\Message::update($messageId, [
                        'external_id' => $externalId
                    ]);
                    Logger::quepasa("processWebhook - ✅ external_id salvo com sucesso");
                } else {
                    Logger::quepasa("processWebhook - ⚠️ Não foi possível salvar external_id: externalId=" . ($externalId ?? 'NULL') . ", messageId=" . ($messageId ?? 'NULL'));
                }
                
                Logger::quepasa("processWebhook - ✅ Mensagem criada com sucesso: messageId={$messageId}");
                
                // Se foi uma nova conversa, executar automações AGORA (após mensagem estar salva)
                Logger::quepasa("🔍 DEBUG: Verificando se deve executar automações... isNewConversation=" . ($isNewConversation ? 'TRUE' : 'FALSE'));
                if ($isNewConversation) {
                    try {
                        Logger::quepasa("processWebhook - 🚀 Executando automações para nova conversa (após salvar mensagem)...");
                        \App\Services\AutomationService::executeForNewConversation($conversation['id']);
                        Logger::quepasa("processWebhook - ✅ Automações executadas com sucesso");
                    } catch (\Exception $e) {
                        Logger::quepasa("processWebhook - ⚠️ Erro ao executar automações: " . $e->getMessage());
                    }
                } else {
                    Logger::quepasa("🔍 DEBUG: Não vai executar automações (conversa existente ou erro)");
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("WhatsAppService::processWebhook - EXCEÇÃO: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                Logger::quepasa("Erro ao criar mensagem via ConversationService: " . $e->getMessage());
                Logger::quepasa("processWebhook - Stack trace: " . $e->getTraceAsString());
                // Fallback: criar mensagem diretamente se ConversationService falhar
                // Capturar external_id do payload
                $externalId = $payload['id'] 
                    ?? ($payload['message']['id'] ?? null)
                    ?? ($payload['data']['id'] ?? null)
                    ?? null;
                
                $messageData = [
                    'conversation_id' => $conversation['id'],
                    'sender_type' => 'contact',
                    'sender_id' => $contact['id'],
                    'content' => $message ?: '',
                    'message_type' => !empty($attachments) ? ($attachments[0]['type'] ?? $messageType ?? 'text') : ($messageType ?? 'text'),
                    'external_id' => $externalId, // Usar external_id do payload, não messageId interno
                    'quoted_message_id' => $quotedMessageId ?? null,
                    'quoted_text' => $quotedMessageText ?? null,
                    'quoted_sender_name' => $quotedSenderName ?? null
                ];
                
                // Se for localização, armazenar como JSON no content
                if ($location) {
                    $messageData['content'] = json_encode($location);
                    $messageData['message_type'] = 'location';
                }
                
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
                
                // Notificar WebSocket sobre nova mensagem com dados de reply (para exibição imediata)
                try {
                    $fullMessage = \App\Models\Message::find($messageId);
                    if ($fullMessage) {
                        // Converter attachments JSON para array se necessário
                        if (isset($fullMessage['attachments']) && is_string($fullMessage['attachments'])) {
                            $decodedAtt = json_decode($fullMessage['attachments'], true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $fullMessage['attachments'] = $decodedAtt;
                            }
                        }
                        $fullMessage['sender_name'] = $contact['name'] ?? 'Contato';
                        $fullMessage['quoted_message_id'] = $quotedMessageId ?? null;
                        $fullMessage['quoted_text'] = $quotedMessageText ?? null;
                        $fullMessage['quoted_sender_name'] = $quotedSenderName ?? null;
                        \App\Helpers\WebSocket::notifyNewMessage($conversation['id'], $fullMessage);
                    }
                } catch (\Exception $e) {
                    Logger::quepasa("processWebhook - Erro ao notificar WebSocket com reply: " . $e->getMessage());
                }
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

    /**
     * Processar webhook de ACK (message.updated) do Quepasa
     * ack: 0 (enviado), 1 (enviado_whatsapp), 2 (entregue), 3 (lido), 4 (falha)
     */
    public static function processAckWebhook(array $payload): void
    {
        try {
            $event = $payload['event'] ?? null;
            if ($event !== 'message.updated') {
                return;
            }

            $data = $payload['data'] ?? [];
            $messageId = $data['id'] ?? null;
            $ack = isset($data['ack']) ? (int)$data['ack'] : null;

            if (!$messageId || $ack === null) {
                Logger::quepasa("processAckWebhook - Dados incompletos: " . json_encode($payload));
                return;
            }

            $message = \App\Models\Message::findByExternalId($messageId);
            if (!$message) {
                Logger::quepasa("processAckWebhook - Mensagem não encontrada: {$messageId}");
                return;
            }

            $status = 'sent';
            $deliveredAt = null;
            $readAt = null;

            switch ($ack) {
                case 0:
                case 1:
                    $status = 'sent';
                    break;
                case 2:
                    $status = 'delivered';
                    $deliveredAt = date('Y-m-d H:i:s');
                    break;
                case 3:
                    $status = 'read';
                    $readAt = date('Y-m-d H:i:s');
                    break;
                case 4:
                    $status = 'failed';
                    break;
            }

            \App\Models\Message::updateStatus(
                $message['id'],
                $status,
                null,
                $deliveredAt,
                $readAt
            );

            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyMessageStatusUpdated(
                    $message['conversation_id'],
                    $message['id'],
                    $status
                );
            } catch (\Exception $e) {
                Logger::quepasa("processAckWebhook - Erro ao notificar WS: " . $e->getMessage());
            }

            Logger::quepasa("processAckWebhook - ACK atualizado: {$messageId} -> ack={$ack}, status={$status}");
        } catch (\Exception $e) {
            Logger::error("WhatsApp processAckWebhook Error: " . $e->getMessage());
        }
    }

    /**
     * Obter número de telefone real a partir de um Linked ID (@lid)
     * Tenta buscar via API do Quepasa usando o chat.id
     */
    private static function getPhoneFromLinkedId(array $account, string $linkedId): ?string
    {
        try {
            if ($account['provider'] !== 'quepasa' || empty($account['quepasa_token'])) {
                return null;
            }

            $apiUrl = rtrim($account['api_url'], '/');
            $token = $account['quepasa_token'];
            
            // Tentar endpoints possíveis para obter informações do contato
            $endpoints = [
                "/v4/bot/{$token}/contact/{$linkedId}",
                "/v3/bot/{$token}/contact/{$linkedId}",
                "/contact/{$linkedId}",
                "/chat/{$linkedId}"
            ];

            foreach ($endpoints as $endpoint) {
                $url = $apiUrl . $endpoint;
                $headers = [
                    'Accept: application/json',
                    'X-QUEPASA-TOKEN: ' . $token,
                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name'])
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && !empty($response)) {
                    $data = json_decode($response, true);
                    if ($data) {
                        // Tentar extrair número de vários campos possíveis
                        $phone = $data['phone'] ?? $data['number'] ?? $data['jid'] ?? $data['id'] ?? null;
                        if ($phone) {
                            $normalized = self::normalizePhoneNumber($phone);
                            if ($normalized && !str_ends_with($normalized, '@lid')) {
                                Logger::quepasa("getPhoneFromLinkedId - Número encontrado via {$endpoint}: {$normalized}");
                                return $normalized;
                            }
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            Logger::quepasa("getPhoneFromLinkedId - Erro: " . $e->getMessage());
            return null;
        }
    }

    private static function normalizeMimeType(?string $mime): string
    {
        $mime = strtolower(trim((string)$mime));
        if ($mime === '') {
            return '';
        }
        $parts = explode(';', $mime, 2);
        return trim($parts[0]);
    }

    private static function canUseExec(): bool
    {
        if (!function_exists('exec') || !function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $disabled = array_filter($disabled);

        if (in_array('exec', $disabled, true) || in_array('shell_exec', $disabled, true)) {
            return false;
        }

        return true;
    }

    private static function resolveFfmpegPath(): ?string
    {
        $ffmpegPath = '';
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $where = trim((string) shell_exec('where ffmpeg 2>NUL'));
            if (!empty($where)) {
                $lines = preg_split('/\r\n|\r|\n/', $where);
                $ffmpegPath = trim($lines[0] ?? '');
            }
        } else {
            $ffmpegPath = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        }

        if (!empty($ffmpegPath)) {
            return $ffmpegPath;
        }

        $candidates = [
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe'
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function convertAudioToMp3(string $sourcePath): array
    {
        if (!self::canUseExec()) {
            return ['success' => false, 'error' => 'exec desabilitado'];
        }

        $ffmpegPath = self::resolveFfmpegPath();
        if (empty($ffmpegPath)) {
            return ['success' => false, 'error' => 'ffmpeg não encontrado'];
        }

        $targetPath = dirname($sourcePath) . DIRECTORY_SEPARATOR . uniqid('audio_', true) . '.mp3';
        $cmd = escapeshellcmd($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath) . ' -vn -c:a libmp3lame -b:a 128k ' . escapeshellarg($targetPath) . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($targetPath) || filesize($targetPath) === 0) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            return [
                'success' => false,
                'error' => 'ffmpeg falhou (exit ' . $exitCode . '): ' . implode("\n", array_slice($output, 0, 5))
            ];
        }

        return [
            'success' => true,
            'filepath' => $targetPath,
            'size' => filesize($targetPath)
        ];
    }

    private static function convertAudioToOpus(string $sourcePath): array
    {
        if (!self::canUseExec()) {
            return ['success' => false, 'error' => 'exec desabilitado'];
        }

        $ffmpegPath = self::resolveFfmpegPath();
        if (empty($ffmpegPath)) {
            return ['success' => false, 'error' => 'ffmpeg não encontrado'];
        }

        $targetPath = dirname($sourcePath) . DIRECTORY_SEPARATOR . uniqid('audio_', true) . '.ogg';
        $cmd = escapeshellcmd($ffmpegPath) . ' -y -i ' . escapeshellarg($sourcePath) . ' -vn -c:a libopus -b:a 96k ' . escapeshellarg($targetPath) . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($targetPath) || filesize($targetPath) === 0) {
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            return [
                'success' => false,
                'error' => 'ffmpeg falhou (exit ' . $exitCode . '): ' . implode("\n", array_slice($output, 0, 5))
            ];
        }

        return [
            'success' => true,
            'filepath' => $targetPath,
            'size' => filesize($targetPath)
        ];
    }
}
