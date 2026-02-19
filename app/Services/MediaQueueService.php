<?php

namespace App\Services;

use App\Models\MediaQueue;
use App\Models\Message;
use App\Helpers\Database;
use App\Helpers\Logger;

class MediaQueueService
{
    private static int $rateLimitMs = 2000;
    private static int $downloadTimeoutSec = 25;
    private static int $connectTimeoutSec = 5;
    private static bool $tableChecked = false;

    private static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;
        
        try {
            $db = Database::getInstance();
            $tables = $db->query("SHOW TABLES LIKE 'media_queue'")->fetchAll();
            if (!empty($tables)) return;
            
            $db->exec("
                CREATE TABLE IF NOT EXISTS media_queue (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    message_id INT NULL,
                    conversation_id INT NULL,
                    account_id INT NOT NULL,
                    external_message_id VARCHAR(255) NOT NULL,
                    direction ENUM('download', 'upload') DEFAULT 'download',
                    media_type VARCHAR(50) NULL,
                    status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'queued',
                    priority TINYINT DEFAULT 5,
                    payload JSON NOT NULL,
                    result JSON NULL,
                    attempts INT DEFAULT 0,
                    max_attempts INT DEFAULT 10,
                    error_message TEXT NULL,
                    next_attempt_at DATETIME NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    processed_at DATETIME NULL,
                    INDEX idx_status (status),
                    INDEX idx_next_attempt (status, next_attempt_at),
                    INDEX idx_external_message (external_message_id),
                    INDEX idx_message (message_id),
                    INDEX idx_conversation (conversation_id),
                    INDEX idx_account_status (account_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            Logger::mediaQueue("Tabela media_queue criada automaticamente");
        } catch (\Exception $e) {
            Logger::mediaQueue("Erro ao criar tabela: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Enfileirar download de mídia que falhou durante o webhook.
     * Salva a mensagem imediatamente com status pendente.
     */
    public static function enqueueDownload(array $params): ?int
    {
        self::ensureTable();
        $externalId = $params['external_message_id'];
        $direction = $params['direction'] ?? 'download';
        $convId = $params['conversation_id'] ?? '?';
        
        if (MediaQueue::existsForMessage($externalId)) {
            Logger::mediaQueue("[conv:{$convId}] Item já existe na fila: {$externalId}", 'WARNING');
            return null;
        }

        $queueId = MediaQueue::enqueue([
            'message_id'          => $params['message_id'] ?? null,
            'conversation_id'     => $params['conversation_id'] ?? null,
            'account_id'          => $params['account_id'],
            'external_message_id' => $externalId,
            'direction'           => $direction,
            'media_type'          => $params['media_type'] ?? 'document',
            'priority'            => $params['priority'] ?? 5,
            'max_attempts'        => 10,
            'payload'             => json_encode([
                'api_url'         => $params['api_url'],
                'token'           => $params['token'] ?? '',
                'trackid'         => $params['trackid'] ?? '',
                'message_id_only' => $params['message_id_only'] ?? null,
                'message_wid'     => $params['message_wid'] ?? null,
                'filename'        => $params['filename'] ?? null,
                'mimetype'        => $params['mimetype'] ?? null,
                'filesize'        => $params['filesize'] ?? null,
                'attachment_meta' => $params['attachment_meta'] ?? [],
            ]),
        ]);

        $label = $direction === 'upload' ? 'Upload' : 'Download';
        Logger::mediaQueue("[conv:{$convId}] {$label} enfileirado: id={$queueId}, msg={$externalId}, type=" . ($params['media_type'] ?? 'unknown'));
        return $queueId;
    }

    /**
     * Processar a fila (chamado pelo cron).
     * Processa UM item por vez com rate limiting.
     */
    public static function processQueue(): array
    {
        self::ensureTable();
        $stats = ['processed' => 0, 'success' => 0, 'errors' => 0, 'skipped' => 0];

        $item = MediaQueue::getNext();
        if (!$item) {
            return $stats;
        }

        if (!MediaQueue::markProcessing($item['id'])) {
            $stats['skipped']++;
            return $stats;
        }

        $convId = $item['conversation_id'] ?? '?';
        
        // Corrigir direction para itens antigos salvos incorretamente
        $direction = $item['direction'];
        if ($direction === 'download' && str_starts_with($item['external_message_id'], 'send_')) {
            $direction = 'upload';
            Logger::mediaQueue("[conv:{$convId}] Corrigindo direction: #{$item['id']} era 'download' mas é upload (send_*)", 'WARNING');
            $db = \App\Helpers\Database::getInstance();
            $db->prepare("UPDATE media_queue SET direction = 'upload' WHERE id = ?")->execute([$item['id']]);
        }
        
        Logger::mediaQueue("[conv:{$convId}] Processando item #{$item['id']}: dir={$direction} type={$item['media_type']} msg={$item['external_message_id']} (tentativa {$item['attempts']}/{$item['max_attempts']})");

        // Rate limit: aguardar entre processamentos
        usleep(self::$rateLimitMs * 1000);

        try {
            $result = ($direction === 'upload') 
                ? self::executeUpload($item) 
                : self::executeDownload($item);
            $stats['processed']++;

            if ($result['success']) {
                MediaQueue::markCompleted($item['id'], $result);
                if ($direction === 'download') {
                    self::updateMessageWithDownload($item, $result);
                    Logger::mediaQueue("[conv:{$convId}] Download concluído: #{$item['id']} → {$result['path']} ({$result['size']} bytes)");
                } else {
                    Logger::mediaQueue("[conv:{$convId}] Upload enviado: #{$item['id']} → message_id=" . ($result['message_id'] ?? 'null'));
                }
                $stats['success']++;
            } else {
                MediaQueue::markFailed($item['id'], $result['error'], $item['attempts'], $item['max_attempts']);
                $stats['errors']++;
                Logger::mediaQueue("[conv:{$convId}] " . ($direction === 'upload' ? 'Upload' : 'Download') . " falhou: #{$item['id']} → {$result['error']}", 'ERROR');
            }
        } catch (\Exception $e) {
            MediaQueue::markFailed($item['id'], $e->getMessage(), $item['attempts'], $item['max_attempts']);
            $stats['errors']++;
            Logger::mediaQueue("[conv:{$convId}] Exceção: #{$item['id']} → {$e->getMessage()}", 'ERROR');
        }

        return $stats;
    }

    /**
     * Executar o download de mídia via API QuePasa
     */
    private static function executeDownload(array $item): array
    {
        $payload = $item['payload'];
        $apiUrl = rtrim($payload['api_url'], '/');
        $messageIdOnly = $payload['message_id_only'];
        $messageWid = $payload['message_wid'] ?? $messageIdOnly;

        $headers = [
            'Accept: */*',
            'X-QUEPASA-TOKEN: ' . ($payload['token'] ?? ''),
            'X-QUEPASA-TRACKID: ' . ($payload['trackid'] ?? ''),
        ];

        $endpoints = [
            "/download/{$messageIdOnly}?cache=true",
            "/download/{$messageIdOnly}",
        ];
        if ($messageWid !== $messageIdOnly) {
            $endpoints[] = "/download/{$messageWid}?cache=true";
        }

        foreach ($endpoints as $endpoint) {
            $url = $apiUrl . $endpoint;
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Download tentando: {$url}");

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => self::$connectTimeoutSec,
                CURLOPT_TIMEOUT        => self::$downloadTimeoutSec,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $dataLen = $data ? strlen($data) : 0;
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Download resposta: HTTP {$httpCode}, Size: {$dataLen}, Type: " . ($contentType ?: 'null') . ($curlError ? ", cURL error: {$curlError}" : ''));

            if ($httpCode !== 200) {
                $errorBody = ($data && $dataLen < 2000) ? $data : '';
                // CDN errors: stop trying different endpoints
                if (strpos($errorBody, 'unexpected EOF') !== false
                    || strpos($errorBody, 'connection reset') !== false
                    || strpos($errorBody, 'failed to download') !== false) {
                    return ['success' => false, 'error' => "WhatsApp CDN error: {$errorBody}"];
                }
                continue;
            }

            if ($dataLen < 100) {
                continue;
            }

            $isJson = @json_decode($data);
            if ($isJson !== null) {
                continue;
            }

            // Dados válidos — salvar arquivo
            return self::saveDownloadedFile($item, $data, $contentType);
        }

        return ['success' => false, 'error' => 'Todos os endpoints falharam'];
    }

    /**
     * Re-executar envio de mídia que falhou por erro CDN.
     * Tenta: 1) base64 via /send  2) URL via /senddocument  3) URL via /send
     */
    private static function executeUpload(array $item): array
    {
        $payload = $item['payload'];
        $meta = $payload['attachment_meta'] ?? [];
        
        $sendUrl = $meta['send_url'] ?? $payload['api_url'] ?? null;
        $sendHeaders = $meta['send_headers'] ?? [];
        $sendPayload = $meta['send_payload'] ?? [];
        $mediaUrl = $meta['media_url'] ?? null;
        $apiBase = rtrim($payload['api_url'] ?? $sendUrl ?? '', '/');
        
        if (empty($apiBase)) {
            return ['success' => false, 'error' => 'URL de API ausente'];
        }
        
        // Reconstruir headers se vieram como array associativo
        if (!empty($sendHeaders) && !is_int(array_key_first($sendHeaders))) {
            $headerLines = [];
            foreach ($sendHeaders as $key => $value) {
                $headerLines[] = "{$key}: {$value}";
            }
            $sendHeaders = $headerLines;
        }
        
        $convId = $item['conversation_id'] ?? '?';
        
        // Lista de tentativas: base64 via /send, depois URL via /senddocument e /send
        $attempts = [];
        
        // Tentativa 1: payload original (base64) via /send
        if (!empty($sendPayload)) {
            $attempts[] = ['url' => $apiBase . '/send', 'payload' => $sendPayload, 'label' => 'base64 /send'];
        }
        
        // Se não temos media_url, tentar construir a partir do filename
        if (empty($mediaUrl) && !empty($payload['filename'])) {
            $publicBase = realpath(__DIR__ . '/../../public');
            if ($publicBase) {
                $possiblePaths = @glob($publicBase . '/assets/media/attachments/*/' . basename($payload['filename']));
                if (!empty($possiblePaths) && file_exists($possiblePaths[0])) {
                    $relativePath = str_replace($publicBase, '', $possiblePaths[0]);
                    $host = $_SERVER['HTTP_HOST'] ?? 'chat.personizi.com.br';
                    $mediaUrl = 'https://' . $host . $relativePath;
                    Logger::mediaQueue("[conv:{$convId}] media_url reconstruída: {$mediaUrl}");
                }
            }
        }
        
        // Tentativas 2-3: via URL pública
        if (!empty($mediaUrl)) {
            $urlMediaSafe = str_starts_with($mediaUrl, 'http://') 
                ? preg_replace('/^http:/i', 'https:', $mediaUrl)
                : $mediaUrl;
            
            $chatId = $sendPayload['chatid'] ?? $sendPayload['chatId'] ?? null;
            if ($chatId) {
                $urlPayload = [
                    'chatid'   => $chatId,
                    'url'      => $urlMediaSafe,
                    'mime'     => $payload['mimetype'] ?? $sendPayload['mime'] ?? '',
                    'filename' => $payload['filename'] ?? $sendPayload['filename'] ?? null,
                ];
                
                if ($item['media_type'] === 'document') {
                    $attempts[] = ['url' => $apiBase . '/senddocument', 'payload' => $urlPayload, 'label' => 'URL /senddocument'];
                }
                $attempts[] = ['url' => $apiBase . '/send', 'payload' => $urlPayload, 'label' => 'URL /send'];
            }
        }
        
        $lastError = '';
        foreach ($attempts as $att) {
            $jsonPayload = json_encode($att['payload']);
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Upload [{$att['label']}]: {$att['url']}, payload_size=" . strlen($jsonPayload));
            
            $ch = curl_init($att['url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_HTTPHEADER     => array_merge($sendHeaders, ['Content-Type: application/json']),
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Upload [{$att['label']}] HTTP {$httpCode}" . ($curlError ? " cURL: {$curlError}" : '') . " resp=" . substr($response ?? '', 0, 300));
            
            if ($curlError) {
                $lastError = "cURL error [{$att['label']}]: {$curlError}";
                continue;
            }
            
            if ($httpCode === 200 || $httpCode === 201) {
                $data = json_decode($response, true);
                Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Upload enviado com sucesso via [{$att['label']}]!");
                return [
                    'success'    => true,
                    'message_id' => $data['id'] ?? $data['message_id'] ?? ($data['message']['id'] ?? null),
                    'status'     => 'sent',
                ];
            }
            
            $lastError = "HTTP {$httpCode} [{$att['label']}]: " . substr($response ?? '', 0, 200);
        }
        
        return ['success' => false, 'error' => $lastError ?: 'Todas as tentativas falharam'];
    }

    /**
     * Salvar o arquivo baixado no disco
     */
    private static function saveDownloadedFile(array $item, string $data, ?string $contentType): array
    {
        $payload = $item['payload'];
        $conversationId = $item['conversation_id'];

        $saveDir = __DIR__ . '/../../public/assets/media/attachments/';
        if ($conversationId) {
            $saveDir .= $conversationId . '/';
        } else {
            $saveDir .= 'temp/';
        }
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }

        $extension = self::determineExtension($payload, $contentType);
        $filename = $item['media_type'] . '_' . $payload['message_id_only'] . '_' . time() . '.' . $extension;
        $fullPath = $saveDir . $filename;

        file_put_contents($fullPath, $data);

        $relativePath = 'assets/media/attachments/' . ($conversationId ? $conversationId . '/' : 'temp/') . $filename;

        return [
            'success'   => true,
            'path'      => $relativePath,
            'local_path'=> $fullPath,
            'filename'  => $payload['filename'] ?? $filename,
            'mime_type' => $payload['mimetype'] ?? $contentType,
            'size'      => strlen($data),
            'extension' => $extension,
        ];
    }

    private static function determineExtension(array $payload, ?string $contentType): string
    {
        $originalFilename = $payload['filename'] ?? null;
        if ($originalFilename) {
            $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
            if (!empty($ext) && $ext !== 'bin') {
                return $ext;
            }
        }

        $mime = $payload['mimetype'] ?? $contentType ?? '';
        $map = [
            'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3', 'video/mp4' => 'mp4', 'audio/mp4' => 'm4a',
            'text/plain' => 'txt', 'text/csv' => 'csv',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc', 'application/vnd.ms-excel' => 'xls',
            'application/zip' => 'zip', 'application/x-rar-compressed' => 'rar',
        ];
        $cleanMime = strtolower(trim(explode(';', $mime)[0]));
        return $map[$cleanMime] ?? 'bin';
    }

    /**
     * Atualizar a mensagem no banco com o arquivo baixado
     */
    private static function updateMessageWithDownload(array $item, array $result): void
    {
        if (empty($item['message_id'])) {
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] message_id não definido, não é possível atualizar mensagem", 'WARNING');
            return;
        }

        $message = Message::find($item['message_id']);
        if (!$message) {
            Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Mensagem #{$item['message_id']} não encontrada no banco", 'WARNING');
            return;
        }

        $attachments = $message['attachments'] ?? [];
        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true) ?? [];
        }

        $attachmentType = $item['media_type'] ?? 'document';
        $mime = $result['mime_type'] ?? '';
        if (strpos($mime, 'audio') !== false) $attachmentType = 'audio';
        elseif (strpos($mime, 'image') !== false) $attachmentType = 'image';
        elseif (strpos($mime, 'video') !== false) $attachmentType = 'video';

        $newAttachment = [
            'path'      => $result['path'],
            'type'      => $attachmentType,
            'mime_type' => $result['mime_type'],
            'mimetype'  => $result['mime_type'],
            'size'      => $result['size'],
            'filename'  => $result['filename'],
            'extension' => $result['extension'],
        ];

        // Substituir attachment pendente ou adicionar novo
        $replaced = false;
        foreach ($attachments as $i => $att) {
            if (!empty($att['download_pending'])) {
                $attachments[$i] = $newAttachment;
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $attachments[] = $newAttachment;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE messages SET attachments = ? WHERE id = ?");
        $stmt->execute([json_encode($attachments), $item['message_id']]);

        // Limpar texto placeholder se era só o filename
        $payload = $item['payload'];
        if (!empty($payload['filename']) && $message['content'] === $payload['filename']) {
            $stmt = $db->prepare("UPDATE messages SET content = '' WHERE id = ?");
            $stmt->execute([$item['message_id']]);
        }

        Logger::mediaQueue("[conv:" . ($item['conversation_id'] ?? '?') . "] Mensagem #{$item['message_id']} atualizada com attachment (type={$attachmentType})");
    }

    /**
     * Retornar status da fila para API
     */
    public static function getQueueStatus(int $conversationId): array
    {
        self::ensureTable();
        $pending = MediaQueue::getPendingByConversation($conversationId);
        $stats = MediaQueue::getStats();
        return [
            'pending_items' => $pending,
            'global_stats'  => $stats,
        ];
    }
}
