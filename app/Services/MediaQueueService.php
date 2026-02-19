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
            Logger::quepasa("MediaQueue - Tabela media_queue criada automaticamente");
        } catch (\Exception $e) {
            Logger::quepasa("MediaQueue - Erro ao criar tabela: " . $e->getMessage());
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
        
        if (MediaQueue::existsForMessage($externalId)) {
            Logger::quepasa("MediaQueue - Item já existe na fila: {$externalId}");
            return null;
        }

        $queueId = MediaQueue::enqueue([
            'message_id'          => $params['message_id'] ?? null,
            'conversation_id'     => $params['conversation_id'] ?? null,
            'account_id'          => $params['account_id'],
            'external_message_id' => $externalId,
            'direction'           => 'download',
            'media_type'          => $params['media_type'] ?? 'document',
            'priority'            => $params['priority'] ?? 5,
            'max_attempts'        => 10,
            'payload'             => json_encode([
                'api_url'         => $params['api_url'],
                'token'           => $params['token'],
                'trackid'         => $params['trackid'],
                'message_id_only' => $params['message_id_only'],
                'message_wid'     => $params['message_wid'],
                'filename'        => $params['filename'] ?? null,
                'mimetype'        => $params['mimetype'] ?? null,
                'filesize'        => $params['filesize'] ?? null,
                'attachment_meta' => $params['attachment_meta'] ?? [],
            ]),
        ]);

        Logger::quepasa("MediaQueue - ✅ Download enfileirado: id={$queueId}, msg={$externalId}, type={$params['media_type']}");
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

        Logger::quepasa("MediaQueue - 🔄 Processando item #{$item['id']}: {$item['media_type']} msg={$item['external_message_id']} (tentativa {$item['attempts']}/{$item['max_attempts']})");

        // Rate limit: aguardar entre processamentos
        usleep(self::$rateLimitMs * 1000);

        try {
            $result = self::executeDownload($item);
            $stats['processed']++;

            if ($result['success']) {
                MediaQueue::markCompleted($item['id'], $result);
                self::updateMessageWithDownload($item, $result);
                $stats['success']++;
                Logger::quepasa("MediaQueue - ✅ Download concluído: #{$item['id']} → {$result['path']} ({$result['size']} bytes)");
            } else {
                MediaQueue::markFailed($item['id'], $result['error'], $item['attempts'], $item['max_attempts']);
                $stats['errors']++;
                Logger::quepasa("MediaQueue - ❌ Download falhou: #{$item['id']} → {$result['error']}");
            }
        } catch (\Exception $e) {
            MediaQueue::markFailed($item['id'], $e->getMessage(), $item['attempts'], $item['max_attempts']);
            $stats['errors']++;
            Logger::quepasa("MediaQueue - ❌ Exceção: #{$item['id']} → {$e->getMessage()}");
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
            Logger::quepasa("MediaQueue - Tentando: {$url}");

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
            Logger::quepasa("MediaQueue - Resposta: HTTP {$httpCode}, Size: {$dataLen}, Type: " . ($contentType ?: 'null'));

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
            Logger::quepasa("MediaQueue - ⚠️ message_id não definido, não é possível atualizar mensagem");
            return;
        }

        $message = Message::find($item['message_id']);
        if (!$message) {
            Logger::quepasa("MediaQueue - ⚠️ Mensagem #{$item['message_id']} não encontrada");
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

        Logger::quepasa("MediaQueue - ✅ Mensagem #{$item['message_id']} atualizada com attachment");
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
