<?php
/**
 * Service AttachmentService
 * Gerencia upload, armazenamento e download de anexos
 */

namespace App\Services;

use App\Helpers\Logger;

class AttachmentService
{
    private static string $uploadDir = __DIR__ . '/../../public/assets/media/attachments/';
    private static int $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static array $allowedTypes = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'video' => ['mp4', 'webm', 'ogg'],
        'audio' => ['mp3', 'wav', 'ogg'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv']
    ];

    /**
     * Upload de arquivo
     */
    public static function upload(array $file, int $conversationId, int $messageId = null): array
    {
        // Validar arquivo
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('Erro ao fazer upload do arquivo');
        }

        // Validar tamanho
        if ($file['size'] > self::$maxFileSize) {
            throw new \Exception('Arquivo muito grande. Tamanho máximo: ' . (self::$maxFileSize / 1024 / 1024) . 'MB');
        }

        // Obter extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);

        // Validar tipo
        $fileType = self::getFileType($extension, $mimeType);
        if (!$fileType) {
            throw new \Exception('Tipo de arquivo não permitido');
        }

        // Criar diretório se não existir
        $conversationDir = self::$uploadDir . $conversationId . '/';
        if (!is_dir($conversationDir)) {
            mkdir($conversationDir, 0755, true);
        }

        // Gerar nome único
        $filename = uniqid('msg_', true) . '_' . time() . '.' . $extension;
        $filepath = $conversationDir . $filename;

        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        // Retornar informações do arquivo
        return [
            'filename' => $filename,
            'original_name' => $file['name'],
            'path' => 'assets/media/attachments/' . $conversationId . '/' . $filename,
            'url' => \App\Helpers\Url::to('assets/media/attachments/' . $conversationId . '/' . $filename),
            'type' => $fileType,
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'extension' => $extension
        ];
    }

    /**
     * Obter tipo MIME do arquivo
     */
    private static function getMimeType(string $filepath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $mimeType ?: 'application/octet-stream';
        }
        
        // Fallback
        return mime_content_type($filepath) ?: 'application/octet-stream';
    }

    /**
     * Determinar tipo de arquivo (image, video, audio, document)
     */
    private static function getFileType(string $extension, string $mimeType): ?string
    {
        foreach (self::$allowedTypes as $type => $extensions) {
            if (in_array($extension, $extensions)) {
                // Verificar MIME type também
                $mimePrefix = explode('/', $mimeType)[0];
                if ($type === 'image' && $mimePrefix === 'image') return 'image';
                if ($type === 'video' && $mimePrefix === 'video') return 'video';
                if ($type === 'audio' && $mimePrefix === 'audio') return 'audio';
                if ($type === 'document') return 'document';
            }
        }
        
        return null;
    }

    /**
     * Salvar anexo de URL (para WhatsApp)
     */
    public static function saveFromUrl(string $url, int $conversationId, string $originalName = null): array
    {
        // Baixar arquivo
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($fileContent)) {
            throw new \Exception('Erro ao baixar arquivo da URL');
        }

        // Criar diretório
        $conversationDir = self::$uploadDir . $conversationId . '/';
        if (!is_dir($conversationDir)) {
            mkdir($conversationDir, 0755, true);
        }

        // Determinar extensão
        $extension = self::getExtensionFromMimeType($contentType);
        if (!$extension) {
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';
        }

        // Gerar nome único
        $filename = uniqid('whatsapp_', true) . '_' . time() . '.' . $extension;
        $filepath = $conversationDir . $filename;

        // Salvar arquivo
        if (file_put_contents($filepath, $fileContent) === false) {
            throw new \Exception('Erro ao salvar arquivo');
        }

        $fileType = self::getFileType($extension, $contentType);
        
        return [
            'filename' => $filename,
            'original_name' => $originalName ?: $filename,
            'path' => 'assets/media/attachments/' . $conversationId . '/' . $filename,
            'url' => \App\Helpers\Url::to('assets/media/attachments/' . $conversationId . '/' . $filename),
            'type' => $fileType ?: 'document',
            'mime_type' => $contentType,
            'size' => strlen($fileContent),
            'extension' => $extension
        ];
    }

    /**
     * Obter extensão a partir do MIME type
     */
    private static function getExtensionFromMimeType(string $mimeType): ?string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt'
        ];
        
        return $mimeMap[$mimeType] ?? null;
    }

    /**
     * Deletar anexo
     */
    public static function delete(string $path): bool
    {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    /**
     * Obter informações do anexo
     */
    public static function getInfo(string $path): ?array
    {
        $fullPath = __DIR__ . '/../../public/' . $path;
        if (!file_exists($fullPath)) {
            return null;
        }

        return [
            'path' => $path,
            'url' => \App\Helpers\Url::to($path),
            'size' => filesize($fullPath),
            'mime_type' => self::getMimeType($fullPath),
            'exists' => true
        ];
    }

    /**
     * Validar arquivo antes do upload
     */
    public static function validateFile(array $file): array
    {
        $errors = [];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erro ao fazer upload do arquivo';
            return $errors;
        }

        if ($file['size'] > self::$maxFileSize) {
            $errors[] = 'Arquivo muito grande. Tamanho máximo: ' . (self::$maxFileSize / 1024 / 1024) . 'MB';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = self::getMimeType($file['tmp_name']);
        $fileType = self::getFileType($extension, $mimeType);

        if (!$fileType) {
            $errors[] = 'Tipo de arquivo não permitido';
        }

        return $errors;
    }
}

