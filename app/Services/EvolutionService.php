<?php
/**
 * Service EvolutionService
 * Integração com Evolution API v2 para WhatsApp
 * 
 * Endpoints base:
 * - POST   /instance/create                    - Criar instância
 * - GET    /instance/connect/{instance}         - Obter QR Code
 * - GET    /instance/connectionState/{instance} - Status da conexão
 * - GET    /instance/fetchInstances             - Listar instâncias
 * - DELETE /instance/logout/{instance}          - Logout/desconectar
 * - DELETE /instance/delete/{instance}          - Deletar instância
 * - POST   /message/sendText/{instance}         - Enviar texto
 * - POST   /message/sendMedia/{instance}        - Enviar mídia
 * - POST   /message/sendWhatsAppAudio/{instance}- Enviar áudio
 * - POST   /webhook/set/{instance}              - Configurar webhook
 */

namespace App\Services;

use App\Models\IntegrationAccount;
use App\Helpers\Logger;

class EvolutionService
{
    /**
     * Fazer requisição HTTP à Evolution API
     */
    private static function request(string $method, string $url, ?string $apiKey, array $body = null, int $timeout = 30): array
    {
        $bodyJson = $body !== null ? json_encode($body) : null;
        Logger::info("EvolutionService::request - {$method} {$url}");
        if ($bodyJson) {
            Logger::info("EvolutionService::request - Body: " . mb_substr($bodyJson, 0, 2000));
        }

        $ch = curl_init($url);
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($apiKey) {
            $headers[] = 'apikey: ' . $apiKey;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $bodyJson ?? '{}';
        } elseif ($method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $opts[CURLOPT_POSTFIELDS] = $bodyJson ?? '{}';
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        Logger::info("EvolutionService::request - HTTP {$httpCode} | Effective URL: {$effectiveUrl}");
        Logger::info("EvolutionService::request - Response: " . mb_substr($response ?: '(vazio)', 0, 2000));

        if ($error) {
            Logger::error("EvolutionService::request - cURL error: {$error}");
            throw new \Exception("Erro na comunicação com Evolution API: {$error}");
        }

        $data = json_decode($response, true);

        // Se a resposta contém HTML (ex: "Cannot POST /..."), tratar como erro claro
        if ($data === null && !empty($response)) {
            if (str_contains($response, 'Cannot') || str_contains($response, '<!DOCTYPE')) {
                Logger::error("EvolutionService::request - Resposta HTML/texto inesperada: " . substr($response, 0, 300));
                $cleanMsg = strip_tags($response);
                throw new \Exception("Evolution API retornou erro: " . trim(substr($cleanMsg, 0, 200)) . " (HTTP {$httpCode}). Verifique se a URL está correta.");
            }
        }

        return [
            'httpCode' => $httpCode,
            'data'     => $data ?? [],
            'raw'      => $response,
        ];
    }

    /**
     * Obter URL base e API key a partir da conta
     */
    private static function getApiConfig(array $account): array
    {
        $apiUrl = trim($account['api_url'] ?? '');
        $apiKey = trim($account['api_key'] ?? '');

        if (empty($apiUrl)) {
            $services = require __DIR__ . '/../../config/services.php';
            $apiUrl = trim($services['evolution']['api_url'] ?? '');
        }
        if (empty($apiKey)) {
            $services = $services ?? require __DIR__ . '/../../config/services.php';
            $apiKey = trim($services['evolution']['api_key'] ?? '');
        }

        if (empty($apiUrl)) {
            throw new \Exception('URL da Evolution API não configurada. Preencha o campo "URL da Evolution API" na conta ou defina EVOLUTION_API_URL no .env');
        }

        // Sanitizar URL: remover /manager, barras finais, barras duplas
        $apiUrl = rtrim($apiUrl, '/');
        $apiUrl = preg_replace('#/manager$#i', '', $apiUrl);
        $apiUrl = rtrim($apiUrl, '/');
        // Corrigir barras duplas no path (mas não no protocolo)
        $apiUrl = preg_replace('#(?<!:)//+#', '/', $apiUrl);

        if (empty($apiKey)) {
            throw new \Exception(
                'API Key da Evolution API não configurada. '
                . 'Preencha o campo "API Key" na conta ou defina EVOLUTION_API_KEY no .env. '
                . 'A API Key é a chave AUTHENTICATION_API_KEY configurada no seu servidor Evolution API.'
            );
        }

        Logger::info("EvolutionService::getApiConfig - URL: {$apiUrl} | Key: " . substr($apiKey, 0, 8) . '...');

        return ['api_url' => $apiUrl, 'api_key' => $apiKey];
    }

    /**
     * Resolver o nome da instância a partir da conta
     * Sanitiza para garantir que seja compatível com a Evolution API (alfanumérico + hífens/underscores)
     */
    private static function getInstanceName(array $account): string
    {
        $name = $account['instance_id'] ?? '';
        if (!empty($name)) {
            $name = self::sanitizeInstanceName($name);
        }
        if (empty($name)) {
            $name = 'chat_' . ($account['id'] ?? 'unknown');
        }
        return $name;
    }

    /**
     * Sanitizar nome de instância para formato válido na Evolution API
     */
    private static function sanitizeInstanceName(string $name): string
    {
        $name = strtolower(trim($name));
        // Remover tudo que não seja alfanumérico, hífen ou underscore
        $name = preg_replace('/[^a-z0-9_-]/', '_', $name);
        // Remover underscores múltiplos
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_-');
        return $name;
    }

    // ================================================================
    // MÉTODOS PÚBLICOS
    // ================================================================

    /**
     * Criar instância na Evolution API
     */
    public static function createInstance(array $account, string $webhookUrl = null): array
    {
        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);

        $body = [
            'instanceName' => $instanceName,
            'integration'  => 'WHATSAPP-BAILEYS',
            'qrcode'       => true,
            'number'       => $account['phone_number'] ?? '',
            'rejectCall'   => false,
            'groupsIgnore' => true,
            'alwaysOnline'  => false,
            'readMessages' => false,
            'readStatus'   => false,
            'syncFullHistory' => false,
        ];

        if ($webhookUrl) {
            $body['webhook'] = [
                'url'           => $webhookUrl,
                'byEvents'      => false,
                'base64'        => true,
                'events'        => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'CONNECTION_UPDATE',
                    'QRCODE_UPDATED',
                    'SEND_MESSAGE',
                ],
            ];
        }

        if (!empty($account['token'])) {
            $body['token'] = $account['token'];
        }

        $url = $config['api_url'] . '/instance/create';
        $result = self::request('POST', $url, $config['api_key'], $body);

        if ($result['httpCode'] !== 201 && $result['httpCode'] !== 200) {
            $errorMsg = self::extractErrorMessage($result);
            throw new \Exception("Falha ao criar instância: {$errorMsg} (HTTP {$result['httpCode']})");
        }

        Logger::info("EvolutionService::createInstance - Instância criada: {$instanceName}");

        return [
            'instanceName' => $result['data']['instance']['instanceName'] ?? $instanceName,
            'instanceId'   => $result['data']['instance']['instanceId'] ?? null,
            'status'       => $result['data']['instance']['status'] ?? 'created',
            'apikey'       => $result['data']['hash']['apikey'] ?? null,
            'qrcode'       => $result['data']['qrcode'] ?? null,
        ];
    }

    /**
     * Obter QR Code para conexão
     * GET /instance/connect/{instance}
     */
    public static function getQRCode(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);

        // Se o instance_id salvo no banco era inválido (ex: email), corrigir
        $rawInstanceId = $account['instance_id'] ?? '';
        if (!empty($rawInstanceId) && $rawInstanceId !== $instanceName) {
            Logger::info("EvolutionService::getQRCode - Corrigindo instance_id: '{$rawInstanceId}' -> '{$instanceName}'");
            IntegrationAccount::updateWithSync($accountId, ['instance_id' => $instanceName]);
        }

        Logger::info("EvolutionService::getQRCode - accountId={$accountId}, instance={$instanceName}, apiUrl={$config['api_url']}");

        // Verificar se a instância já existe; se não, criar
        $needsCreate = false;
        try {
            $state = self::fetchConnectionState($config, $instanceName);
            $connectionState = $state['data']['state'] ?? ($state['data']['instance']['state'] ?? 'unknown');
            Logger::info("EvolutionService::getQRCode - Estado atual: {$connectionState}");

            if ($connectionState === 'open') {
                IntegrationAccount::updateWithSync($accountId, ['status' => 'active']);
                return [
                    'status'  => 'connected',
                    'message' => 'Já conectado ao WhatsApp',
                    'qrcode'  => null,
                ];
            }
        } catch (\Exception $e) {
            Logger::info("EvolutionService::getQRCode - Instância '{$instanceName}' não encontrada: {$e->getMessage()}");
            $needsCreate = true;
        }

        // Criar instância se necessário
        if ($needsCreate) {
            Logger::info("EvolutionService::getQRCode - Criando instância '{$instanceName}'...");
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $webhookUrl = "{$protocol}://{$host}/whatsapp-webhook";

            $createResult = self::createInstance($account, $webhookUrl);

            // Salvar instanceName e apikey no banco
            $updateData = ['instance_id' => $createResult['instanceName']];
            if (!empty($createResult['apikey'])) {
                $updateData['api_key'] = $createResult['apikey'];
                $config['api_key'] = $createResult['apikey'];
            }
            IntegrationAccount::updateWithSync($accountId, $updateData);
            $instanceName = $createResult['instanceName'];

            // Se já retornou QR code na criação (Evolution pode retornar em vários formatos)
            $qr = $createResult['qrcode'] ?? null;
            if ($qr) {
                $base64 = null;
                if (is_array($qr)) {
                    $base64 = $qr['base64'] ?? $qr['code'] ?? null;
                } elseif (is_string($qr)) {
                    $base64 = $qr;
                }

                if (!empty($base64)) {
                    $base64 = self::normalizeBase64Image($base64);
                    Logger::info("EvolutionService::getQRCode - QR Code recebido na criação da instância");
                    return [
                        'qrcode'     => $base64,
                        'base64'     => $base64,
                        'expires_in' => 60,
                    ];
                }
            }
        }

        // GET /instance/connect/{instance} para obter QR code
        $url = $config['api_url'] . '/instance/connect/' . urlencode($instanceName);
        $result = self::request('GET', $url, $config['api_key'], null, 60);

        if ($result['httpCode'] !== 200) {
            $errorMsg = self::extractErrorMessage($result);
            throw new \Exception("Erro ao obter QR Code: {$errorMsg} (HTTP {$result['httpCode']})");
        }

        $responseData = $result['data'];
        Logger::info("EvolutionService::getQRCode - Connect response keys: " . implode(', ', array_keys($responseData)));

        // A resposta pode conter base64 diretamente ou um campo code/pairingCode
        $base64 = $responseData['base64'] ?? null;
        $code = $responseData['code'] ?? null;
        $pairingCode = $responseData['pairingCode'] ?? null;

        if (!empty($base64)) {
            $base64 = self::normalizeBase64Image($base64);
            return [
                'qrcode'      => $base64,
                'base64'      => $base64,
                'pairingCode' => $pairingCode,
                'expires_in'  => 60,
            ];
        }

        if (!empty($code)) {
            // code pode ser o QR code em formato texto (precisa gerar imagem)
            // ou já em base64
            $normalizedCode = self::normalizeBase64Image($code);
            return [
                'qrcode'      => $normalizedCode,
                'base64'      => $normalizedCode,
                'pairingCode' => $pairingCode,
                'expires_in'  => 60,
            ];
        }

        throw new \Exception('QR Code não disponível. Verifique se a instância está correta e tente novamente.');
    }

    /**
     * Verificar status da conexão
     * GET /instance/connectionState/{instance}
     */
    public static function getConnectionStatus(int $accountId, bool $forceRealCheck = false): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        if (!$forceRealCheck && $account['status'] === 'active') {
            return [
                'connected'    => true,
                'status'       => 'connected',
                'phone_number' => $account['phone_number'],
                'message'      => 'Conectado',
            ];
        }

        try {
            $config = self::getApiConfig($account);
            $instanceName = self::getInstanceName($account);
            $state = self::fetchConnectionState($config, $instanceName);

            $connectionState = $state['data']['state'] ?? 'unknown';

            if ($connectionState === 'open') {
                if ($account['status'] !== 'active') {
                    IntegrationAccount::updateWithSync($accountId, ['status' => 'active']);
                }
                return [
                    'connected'    => true,
                    'status'       => 'connected',
                    'phone_number' => $account['phone_number'],
                    'message'      => 'Conectado',
                ];
            }

            if ($connectionState === 'connecting') {
                return [
                    'connected' => false,
                    'status'    => 'connecting',
                    'message'   => 'Conectando... Escaneie o QR Code',
                ];
            }

            // close ou outro estado
            if ($account['status'] === 'active') {
                IntegrationAccount::updateWithSync($accountId, ['status' => 'disconnected']);
            }
            return [
                'connected' => false,
                'status'    => 'disconnected',
                'message'   => 'Desconectado - Escaneie o QR Code',
            ];
        } catch (\Exception $e) {
            Logger::error("EvolutionService::getConnectionStatus - {$e->getMessage()}");
            return [
                'connected' => false,
                'status'    => 'error',
                'message'   => 'Erro ao verificar conexão: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar conexão (alias para IntegrationService::checkStatus)
     */
    public static function checkConnection(int $accountId): array
    {
        return self::getConnectionStatus($accountId, true);
    }

    /**
     * Desconectar (logout) da instância
     * DELETE /instance/logout/{instance}
     */
    public static function disconnect(int $accountId): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        try {
            $config = self::getApiConfig($account);
            $instanceName = self::getInstanceName($account);

            $url = $config['api_url'] . '/instance/logout/' . urlencode($instanceName);
            self::request('DELETE', $url, $config['api_key']);
        } catch (\Exception $e) {
            Logger::error("EvolutionService::disconnect - {$e->getMessage()}");
        }

        IntegrationAccount::updateWithSync($accountId, [
            'status' => 'disconnected',
        ]);

        Logger::info("EvolutionService::disconnect - Conta {$accountId} desconectada");
        return true;
    }

    /**
     * Enviar mensagem de texto
     * POST /message/sendText/{instance}
     */
    public static function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);

        // Se tiver mídia, enviar como mídia
        if (!empty($options['media_url'])) {
            return self::sendMedia($accountId, $to, $message, $options);
        }

        // Resolver número do destinatário
        $number = self::normalizeNumber($to);

        $body = [
            'number' => $number,
            'text'   => $message,
        ];

        if (!empty($options['delay'])) {
            $body['delay'] = (int)$options['delay'];
        }

        // Resposta a mensagem citada
        if (!empty($options['quoted_message_external_id']) || !empty($options['quoted_message_id'])) {
            $quotedId = $options['quoted_message_external_id'] ?? $options['quoted_message_id'];
            $body['quoted'] = [
                'key' => [
                    'id' => $quotedId,
                ],
            ];
        }

        $url = $config['api_url'] . '/message/sendText/' . urlencode($instanceName);
        $result = self::request('POST', $url, $config['api_key'], $body);

        if ($result['httpCode'] !== 201 && $result['httpCode'] !== 200) {
            $errorMsg = self::extractErrorMessage($result);
            throw new \Exception("Falha ao enviar mensagem: {$errorMsg} (HTTP {$result['httpCode']})");
        }

        Logger::info("EvolutionService::sendMessage - Mensagem enviada para {$to}");

        return [
            'success'    => true,
            'message_id' => $result['data']['key']['id'] ?? null,
            'status'     => $result['data']['status'] ?? 'sent',
            'data'       => $result['data'],
        ];
    }

    /**
     * Enviar mídia (imagem, vídeo, documento)
     * POST /message/sendMedia/{instance}
     */
    public static function sendMedia(int $accountId, string $to, string $caption, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);
        $number = self::normalizeNumber($to);

        $mediaUrl = $options['media_url'] ?? '';
        $mediaType = $options['media_type'] ?? 'document';
        $mediaMime = $options['media_mime'] ?? 'application/octet-stream';
        $mediaName = $options['media_name'] ?? 'file';

        // Se for áudio, usar endpoint específico
        if ($mediaType === 'audio') {
            return self::sendAudio($accountId, $to, $mediaUrl, $options);
        }

        // Mapear media_type para mediatype da Evolution API
        $evolutionMediaType = match ($mediaType) {
            'image'    => 'image',
            'video'    => 'video',
            'document' => 'document',
            'sticker'  => 'image',
            default    => 'document',
        };

        // Se a media_url for local, converter para URL absoluta ou base64
        $media = $mediaUrl;
        if (!str_starts_with($mediaUrl, 'http')) {
            $publicBase = realpath(__DIR__ . '/../../public');
            $pathFromUrl = parse_url($mediaUrl, PHP_URL_PATH) ?: '';
            $absolutePath = $publicBase . $pathFromUrl;

            if (file_exists($absolutePath)) {
                $media = 'data:' . $mediaMime . ';base64,' . base64_encode(file_get_contents($absolutePath));
            }
        }

        $body = [
            'number'    => $number,
            'mediatype' => $evolutionMediaType,
            'mimetype'  => $mediaMime,
            'caption'   => $caption ?: '',
            'media'     => $media,
            'fileName'  => $mediaName,
        ];

        if (!empty($options['quoted_message_external_id']) || !empty($options['quoted_message_id'])) {
            $quotedId = $options['quoted_message_external_id'] ?? $options['quoted_message_id'];
            $body['quoted'] = ['key' => ['id' => $quotedId]];
        }

        $url = $config['api_url'] . '/message/sendMedia/' . urlencode($instanceName);
        $result = self::request('POST', $url, $config['api_key'], $body, 120);

        if ($result['httpCode'] !== 201 && $result['httpCode'] !== 200) {
            throw new \Exception("Falha ao enviar mídia: " . self::extractErrorMessage($result) . " (HTTP {$result['httpCode']})");
        }

        Logger::info("EvolutionService::sendMedia - Mídia enviada para {$to}");

        return [
            'success'    => true,
            'message_id' => $result['data']['key']['id'] ?? null,
            'status'     => $result['data']['status'] ?? 'sent',
            'data'       => $result['data'],
        ];
    }

    /**
     * Enviar áudio
     * POST /message/sendWhatsAppAudio/{instance}
     */
    public static function sendAudio(int $accountId, string $to, string $audioUrl, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);
        $number = self::normalizeNumber($to);

        $audio = $audioUrl;
        if (!str_starts_with($audioUrl, 'http')) {
            $publicBase = realpath(__DIR__ . '/../../public');
            $pathFromUrl = parse_url($audioUrl, PHP_URL_PATH) ?: '';
            $absolutePath = $publicBase . $pathFromUrl;

            if (file_exists($absolutePath)) {
                $mime = mime_content_type($absolutePath) ?: 'audio/mpeg';
                $audio = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absolutePath));
            }
        }

        $body = [
            'number' => $number,
            'audio'  => $audio,
        ];

        if (!empty($options['delay'])) {
            $body['delay'] = (int)$options['delay'];
        }

        $url = $config['api_url'] . '/message/sendWhatsAppAudio/' . urlencode($instanceName);
        $result = self::request('POST', $url, $config['api_key'], $body, 120);

        if ($result['httpCode'] !== 201 && $result['httpCode'] !== 200) {
            throw new \Exception("Falha ao enviar áudio: " . self::extractErrorMessage($result) . " (HTTP {$result['httpCode']})");
        }

        Logger::info("EvolutionService::sendAudio - Áudio enviado para {$to}");

        return [
            'success'    => true,
            'message_id' => $result['data']['key']['id'] ?? null,
            'status'     => $result['data']['status'] ?? 'sent',
            'data'       => $result['data'],
        ];
    }

    /**
     * Configurar webhook na instância
     * POST /webhook/set/{instance}
     */
    public static function configureWebhook(int $accountId, string $webhookUrl, array $options = []): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Conta não encontrada');
        }

        $config = self::getApiConfig($account);
        $instanceName = self::getInstanceName($account);

        $body = [
            'url'            => $webhookUrl,
            'webhookByEvents' => false,
            'webhookBase64'  => true,
            'enabled'        => true,
            'events'         => $options['events'] ?? [
                'MESSAGES_UPSERT',
                'MESSAGES_UPDATE',
                'CONNECTION_UPDATE',
                'QRCODE_UPDATED',
                'SEND_MESSAGE',
            ],
        ];

        $url = $config['api_url'] . '/webhook/set/' . urlencode($instanceName);
        $result = self::request('POST', $url, $config['api_key'], $body);

        if ($result['httpCode'] !== 201 && $result['httpCode'] !== 200) {
            throw new \Exception("Falha ao configurar webhook: " . self::extractErrorMessage($result) . " (HTTP {$result['httpCode']})");
        }

        // Salvar webhook_url na conta
        IntegrationAccount::updateWithSync($accountId, ['webhook_url' => $webhookUrl]);

        Logger::info("EvolutionService::configureWebhook - Webhook configurado: {$webhookUrl}");
        return true;
    }

    /**
     * Processar webhook recebido da Evolution API
     * 
     * Payload típico:
     * {
     *   "event": "messages.upsert",
     *   "instance": "instanceName",
     *   "data": {
     *     "key": { "remoteJid": "55119999@s.whatsapp.net", "fromMe": false, "id": "ABC" },
     *     "pushName": "Nome",
     *     "message": { "conversation": "texto" },
     *     "messageType": "conversation",
     *     "messageTimestamp": 123456
     *   }
     * }
     */
    public static function processWebhook(array $payload): void
    {
        $event = $payload['event'] ?? '';
        $instanceName = $payload['instance'] ?? '';
        $data = $payload['data'] ?? [];

        Logger::info("EvolutionService::processWebhook - Event: {$event}, Instance: {$instanceName}");
        Logger::info("EvolutionService::processWebhook - Payload: " . mb_substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 4000));

        // Identificar a conta pelo instance_id
        $account = null;
        if ($instanceName) {
            $accounts = IntegrationAccount::whereWhatsApp('instance_id', '=', $instanceName);
            $account = !empty($accounts) ? $accounts[0] : null;
        }

        if (!$account) {
            Logger::error("EvolutionService::processWebhook - Conta não encontrada para instância: {$instanceName}");
            // Tentar por apikey se estiver no header da request
            return;
        }

        switch ($event) {
            case 'connection.update':
                self::handleConnectionUpdate($account, $data);
                break;

            case 'qrcode.updated':
                // QR code atualizado - nada a fazer server-side, frontend busca via polling
                Logger::info("EvolutionService::processWebhook - QR Code atualizado para {$instanceName}");
                break;

            case 'messages.upsert':
                self::handleMessageUpsert($account, $data);
                break;

            case 'messages.update':
                self::handleMessageUpdate($account, $data);
                break;

            case 'send.message':
                self::handleSentMessage($account, $data);
                break;

            default:
                Logger::info("EvolutionService::processWebhook - Evento não tratado: {$event}");
                break;
        }
    }

    // ================================================================
    // HANDLERS DE WEBHOOK
    // ================================================================

    /**
     * Processar CONNECTION_UPDATE
     */
    private static function handleConnectionUpdate(array $account, array $data): void
    {
        $state = $data['state'] ?? 'unknown';
        $accountId = $account['id'];

        Logger::info("EvolutionService::handleConnectionUpdate - Conta #{$accountId}: state={$state}");

        if ($state === 'open') {
            IntegrationAccount::updateWithSync($accountId, ['status' => 'active']);
            Logger::info("EvolutionService - Conta #{$accountId} conectada");
        } elseif ($state === 'close') {
            IntegrationAccount::updateWithSync($accountId, ['status' => 'disconnected']);
            Logger::info("EvolutionService - Conta #{$accountId} desconectada");
        }
    }

    /**
     * Processar MESSAGES_UPSERT (mensagem recebida)
     * Converte o payload da Evolution para o formato que o WhatsAppService::processWebhook espera
     */
    private static function handleMessageUpsert(array $account, array $data): void
    {
        $key = $data['key'] ?? [];
        $remoteJid = $key['remoteJid'] ?? '';
        $fromMe = $key['fromMe'] ?? false;
        $messageId = $key['id'] ?? '';
        $pushName = $data['pushName'] ?? '';
        $messageType = $data['messageType'] ?? 'conversation';
        $messageContent = $data['message'] ?? [];
        $timestamp = $data['messageTimestamp'] ?? time();

        // Ignorar mensagens de grupo (terminam com @g.us)
        if (str_ends_with($remoteJid, '@g.us')) {
            Logger::info("EvolutionService::handleMessageUpsert - Ignorando mensagem de grupo: {$remoteJid}");
            return;
        }

        // Ignorar mensagens de status/broadcast
        if ($remoteJid === 'status@broadcast') {
            return;
        }

        // Extrair número de telefone do remoteJid
        $phoneNumber = str_replace(['@s.whatsapp.net', '@c.us'], '', $remoteJid);
        $phoneNumber = explode(':', $phoneNumber)[0]; // Remover sufixo :XX se houver

        // Extrair texto da mensagem
        $text = $messageContent['conversation']
            ?? $messageContent['extendedTextMessage']['text']
            ?? $messageContent['imageMessage']['caption']
            ?? $messageContent['videoMessage']['caption']
            ?? $messageContent['documentMessage']['caption']
            ?? '';

        // Extrair dados de mídia
        $mediaUrl = null;
        $mediaType = null;
        $mediaMime = null;
        $mediaName = null;
        $mediaBase64 = null;

        if (isset($messageContent['imageMessage'])) {
            $mediaType = 'image';
            $mediaMime = $messageContent['imageMessage']['mimetype'] ?? 'image/jpeg';
            $mediaBase64 = $messageContent['imageMessage']['base64'] ?? null;
            $mediaUrl = $messageContent['imageMessage']['url'] ?? null;
        } elseif (isset($messageContent['videoMessage'])) {
            $mediaType = 'video';
            $mediaMime = $messageContent['videoMessage']['mimetype'] ?? 'video/mp4';
            $mediaBase64 = $messageContent['videoMessage']['base64'] ?? null;
            $mediaUrl = $messageContent['videoMessage']['url'] ?? null;
        } elseif (isset($messageContent['audioMessage'])) {
            $mediaType = 'audio';
            $mediaMime = $messageContent['audioMessage']['mimetype'] ?? 'audio/ogg';
            $mediaBase64 = $messageContent['audioMessage']['base64'] ?? null;
            $mediaUrl = $messageContent['audioMessage']['url'] ?? null;
        } elseif (isset($messageContent['documentMessage'])) {
            $mediaType = 'document';
            $mediaMime = $messageContent['documentMessage']['mimetype'] ?? 'application/octet-stream';
            $mediaName = $messageContent['documentMessage']['fileName'] ?? 'document';
            $mediaBase64 = $messageContent['documentMessage']['base64'] ?? null;
            $mediaUrl = $messageContent['documentMessage']['url'] ?? null;
        } elseif (isset($messageContent['stickerMessage'])) {
            $mediaType = 'sticker';
            $mediaMime = $messageContent['stickerMessage']['mimetype'] ?? 'image/webp';
            $mediaBase64 = $messageContent['stickerMessage']['base64'] ?? null;
            $mediaUrl = $messageContent['stickerMessage']['url'] ?? null;
        }

        // Salvar mídia base64 localmente se disponível
        $localMediaUrl = null;
        if ($mediaBase64 && $mediaType) {
            $localMediaUrl = self::saveBase64Media($mediaBase64, $mediaType, $mediaMime, $mediaName);
        } elseif ($mediaUrl && $mediaType) {
            $localMediaUrl = $mediaUrl;
        }

        // Converter para formato compatível com WhatsAppService::processWebhook
        $normalizedPayload = [
            'from'     => $phoneNumber,
            'phone'    => $phoneNumber,
            'text'     => $text,
            'id'       => $messageId,
            'fromMe'   => $fromMe,
            'fromme'   => $fromMe,
            'trackid'  => $account['quepasa_trackid'] ?? $account['instance_id'] ?? '',
            'wid'      => $account['quepasa_chatid'] ?? $account['phone_number'],
            'chatid'   => $account['quepasa_chatid'] ?? $account['phone_number'],
            'pushName' => $pushName,
            'timestamp' => $timestamp,
            'source'   => 'evolution',
            'chat'     => [
                'id'    => $phoneNumber,
                'phone' => $phoneNumber,
                'title' => $pushName,
            ],
        ];

        // Adicionar informações de mídia se houver
        if ($localMediaUrl) {
            $normalizedPayload['attachment'] = [
                'url'      => $localMediaUrl,
                'mimetype' => $mediaMime,
                'filename' => $mediaName ?? ('media.' . self::getExtFromMime($mediaMime)),
                'type'     => $mediaType,
            ];
            $normalizedPayload['media_url'] = $localMediaUrl;
            $normalizedPayload['media_type'] = $mediaType;
            $normalizedPayload['media_mime'] = $mediaMime;
            $normalizedPayload['media_name'] = $mediaName;
        }

        Logger::info("EvolutionService::handleMessageUpsert - Encaminhando para WhatsAppService: from={$phoneNumber}, text=" . substr($text, 0, 100));

        // Repassar para o WhatsAppService que já tem toda a lógica de processamento
        WhatsAppService::processWebhook($normalizedPayload);
    }

    /**
     * Processar MESSAGES_UPDATE (atualização de status de mensagem)
     */
    private static function handleMessageUpdate(array $account, array $data): void
    {
        $key = $data['key'] ?? [];
        $messageId = $key['id'] ?? '';
        $status = $data['update']['status'] ?? null;

        if ($messageId && $status) {
            Logger::info("EvolutionService::handleMessageUpdate - Msg {$messageId} status: {$status}");
        }
    }

    /**
     * Processar SEND_MESSAGE (mensagem enviada pelo sistema confirmada)
     */
    private static function handleSentMessage(array $account, array $data): void
    {
        $key = $data['key'] ?? [];
        $messageId = $key['id'] ?? '';

        Logger::info("EvolutionService::handleSentMessage - Msg enviada confirmada: {$messageId}");
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Extrair mensagem de erro legível da resposta da Evolution API
     */
    private static function extractErrorMessage(array $result): string
    {
        $data = $result['data'] ?? [];
        $raw = $result['raw'] ?? '';

        // Formato: { "response": { "message": ["..."] } }
        if (!empty($data['response']['message'])) {
            $msgs = $data['response']['message'];
            return is_array($msgs) ? implode('; ', $msgs) : (string)$msgs;
        }

        // Formato: { "message": "..." } ou { "message": ["..."] }
        if (!empty($data['message'])) {
            $msg = $data['message'];
            return is_array($msg) ? implode('; ', $msg) : (string)$msg;
        }

        // Formato: { "error": "..." }
        if (!empty($data['error'])) {
            return (string)$data['error'];
        }

        // Texto puro (ex: "Cannot POST /...")
        if (!empty($raw) && strlen($raw) < 300) {
            return trim(strip_tags($raw));
        }

        return 'Erro desconhecido';
    }

    /**
     * Buscar estado de conexão da instância
     * Lança exceção se a instância não existe (404) ou erro de autenticação (401/403)
     */
    private static function fetchConnectionState(array $config, string $instanceName): array
    {
        $url = $config['api_url'] . '/instance/connectionState/' . urlencode($instanceName);
        $result = self::request('GET', $url, $config['api_key']);

        if ($result['httpCode'] === 404) {
            throw new \Exception("Instância '{$instanceName}' não existe na Evolution API");
        }
        if ($result['httpCode'] === 401 || $result['httpCode'] === 403) {
            throw new \Exception('API Key inválida ou sem permissão (HTTP ' . $result['httpCode'] . ')');
        }
        if ($result['httpCode'] >= 400) {
            throw new \Exception(self::extractErrorMessage($result) . ' (HTTP ' . $result['httpCode'] . ')');
        }

        return $result;
    }

    /**
     * Normalizar string base64 para data URI de imagem
     */
    private static function normalizeBase64Image(string $value): string
    {
        $value = trim($value);
        // Já é data URI
        if (str_starts_with($value, 'data:image/')) {
            return $value;
        }
        // É base64 puro
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', substr($value, 0, 100))) {
            return 'data:image/png;base64,' . $value;
        }
        // Pode ser o QR code como texto (não base64) - retornar como está
        return $value;
    }

    /**
     * Normalizar número de telefone para formato Evolution API
     */
    private static function normalizeNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);

        // Remover @s.whatsapp.net se veio colado
        $number = str_replace('@s.whatsapp.net', '', $number);

        return $number;
    }

    /**
     * Salvar mídia base64 localmente e retornar URL
     */
    private static function saveBase64Media(string $base64, string $type, string $mime, ?string $fileName = null): ?string
    {
        try {
            // Remover prefixo data:mime;base64, se houver
            if (str_contains($base64, ',')) {
                $base64 = explode(',', $base64, 2)[1];
            }

            $content = base64_decode($base64);
            if ($content === false) {
                return null;
            }

            $ext = self::getExtFromMime($mime);
            $dir = 'uploads/whatsapp/' . date('Y/m');
            $fullDir = realpath(__DIR__ . '/../../public') . '/' . $dir;

            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $name = $fileName ?: (uniqid('evo_') . '.' . $ext);
            if (!pathinfo($name, PATHINFO_EXTENSION)) {
                $name .= '.' . $ext;
            }

            $filePath = $fullDir . '/' . $name;
            file_put_contents($filePath, $content);

            return '/' . $dir . '/' . $name;
        } catch (\Exception $e) {
            Logger::error("EvolutionService::saveBase64Media - {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Obter extensão de arquivo a partir do MIME type
     */
    private static function getExtFromMime(string $mime): string
    {
        $map = [
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'image/webp'       => 'webp',
            'video/mp4'        => 'mp4',
            'video/webm'       => 'webm',
            'audio/ogg'        => 'ogg',
            'audio/mpeg'       => 'mp3',
            'audio/mp4'        => 'm4a',
            'audio/wav'        => 'wav',
            'audio/webm'       => 'webm',
            'application/pdf'  => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain'       => 'txt',
            'application/zip'  => 'zip',
        ];

        return $map[$mime] ?? 'bin';
    }

    /**
     * Criar conta Evolution API
     * Similar ao WhatsAppService::createAccount mas com lógica específica
     */
    public static function createAccount(array $data): int
    {
        $rules = [
            'name'         => 'required|string|max:255',
            'phone_number' => 'required|string',
            'provider'     => 'required|string|in:evolution',
        ];

        $errors = \App\Helpers\Validator::validate($data, $rules);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Verificar se número já existe
        $existing = IntegrationAccount::findWhatsAppByPhone($data['phone_number']);
        if ($existing) {
            throw new \InvalidArgumentException('Número já cadastrado');
        }

        // Gerar instance_id a partir do nome (slug), sempre sanitizado
        $instanceId = !empty($data['instance_id'])
            ? self::sanitizeInstanceName($data['instance_id'])
            : self::generateInstanceName($data['name']);

        $accountData = [
            'name'         => $data['name'],
            'phone_number' => $data['phone_number'],
            'provider'     => 'evolution',
            'channel'      => 'whatsapp',
            'api_url'      => $data['api_url'] ?? '',
            'api_key'      => $data['api_key'] ?? '',
            'instance_id'  => $instanceId,
            'status'       => 'inactive',
            'config'       => json_encode([]),
            'default_funnel_id' => $data['default_funnel_id'] ?? null,
            'default_stage_id'  => $data['default_stage_id'] ?? null,
        ];

        // Preencher api_url/api_key do .env se não informados
        if (empty($accountData['api_url'])) {
            $services = require __DIR__ . '/../../config/services.php';
            $accountData['api_url'] = $services['evolution']['api_url'] ?? '';
        }
        if (empty($accountData['api_key'])) {
            $services = $services ?? require __DIR__ . '/../../config/services.php';
            $accountData['api_key'] = $services['evolution']['api_key'] ?? '';
        }

        $accountId = IntegrationAccount::createWhatsApp($accountData);
        Logger::info("EvolutionService::createAccount - Conta criada: ID={$accountId}, instance={$instanceId}");

        return $accountId;
    }

    /**
     * Gerar nome de instância a partir do nome da conta
     */
    private static function generateInstanceName(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug ?: ('inst_' . time());
    }

    /**
     * Deletar instância na Evolution API
     * DELETE /instance/delete/{instance}
     */
    public static function deleteInstance(int $accountId): bool
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            return false;
        }

        try {
            $config = self::getApiConfig($account);
            $instanceName = self::getInstanceName($account);

            $url = $config['api_url'] . '/instance/delete/' . urlencode($instanceName);
            self::request('DELETE', $url, $config['api_key']);
            Logger::info("EvolutionService::deleteInstance - Instância {$instanceName} deletada");
        } catch (\Exception $e) {
            Logger::error("EvolutionService::deleteInstance - {$e->getMessage()}");
        }

        return true;
    }

    /**
     * Buscar instâncias na Evolution API
     * GET /instance/fetchInstances
     */
    public static function fetchInstances(string $apiUrl = null, string $apiKey = null): array
    {
        if (!$apiUrl || !$apiKey) {
            $services = require __DIR__ . '/../../config/services.php';
            $apiUrl = $apiUrl ?: ($services['evolution']['api_url'] ?? '');
            $apiKey = $apiKey ?: ($services['evolution']['api_key'] ?? '');
        }

        $apiUrl = rtrim($apiUrl, '/');
        $url = $apiUrl . '/instance/fetchInstances';
        $result = self::request('GET', $url, $apiKey);

        return $result['data'] ?? [];
    }
}
