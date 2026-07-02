<?php
/**
 * AttachmentsController - API v1
 * Servir mídia de conversas para o app mobile: URLs assinadas (HMAC) ou JWT.
 * Arquivos vivem em public/assets/media/attachments/{conversationId}/...
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Services\ConversationService;

class AttachmentsController
{
    /** Validade padrão da URL assinada (segundos) */
    private const SIGNATURE_TTL = 3600;

    /**
     * Gerar URL assinada para um anexo (requer auth)
     * GET /api/v1/attachments/sign?path=assets/media/attachments/123/arquivo.jpg
     */
    public function sign(): void
    {
        $path = $this->normalizePath((string)($_GET['path'] ?? ''));

        if ($path === null) {
            ApiResponse::validationError('Dados inválidos', ['path' => ['Path de anexo inválido']]);
        }

        // Permissão: o anexo pertence a uma conversa que o usuário pode ver
        $conversationId = $this->extractConversationId($path);
        if ($conversationId && !ConversationService::canView($conversationId, ApiAuthMiddleware::userId())) {
            ApiResponse::forbidden('Você não tem permissão para acessar este anexo');
        }

        $expires = time() + self::SIGNATURE_TTL;
        $signature = $this->computeSignature($path, $expires);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $url = "{$scheme}://{$host}/api/v1/attachments/view"
            . '?path=' . rawurlencode($path)
            . '&exp=' . $expires
            . '&sig=' . $signature;

        ApiResponse::success([
            'url' => $url,
            'expires_at' => date('c', $expires),
        ]);
    }

    /**
     * Servir o arquivo (público, validado por assinatura HMAC — players e caches de imagem
     * não enviam header Authorization)
     * GET /api/v1/attachments/view?path=...&exp=...&sig=...
     */
    public function view(): void
    {
        $path = $this->normalizePath((string)($_GET['path'] ?? ''));
        $expires = (int)($_GET['exp'] ?? 0);
        $signature = (string)($_GET['sig'] ?? '');

        if ($path === null || $expires <= 0 || $signature === '') {
            ApiResponse::badRequest('Parâmetros inválidos');
        }

        if ($expires < time()) {
            ApiResponse::error('URL expirada', 410, 'EXPIRED');
        }

        if (!hash_equals($this->computeSignature($path, $expires), $signature)) {
            ApiResponse::forbidden('Assinatura inválida');
        }

        $fullPath = realpath(dirname(__DIR__, 3) . '/public/' . $path);
        $baseDir = realpath(dirname(__DIR__, 3) . '/public/assets/media');

        if (!$fullPath || !$baseDir || strpos($fullPath, $baseDir) !== 0 || !is_file($fullPath)) {
            ApiResponse::notFound('Arquivo não encontrado');
        }

        $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
        $fileSize = filesize($fullPath);

        // Suporte a Range (players de áudio/vídeo em iOS exigem)
        $start = 0;
        $end = $fileSize - 1;
        $isPartial = false;

        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $isPartial = true;
            if ($m[1] !== '') {
                $start = (int)$m[1];
            }
            if ($m[2] !== '') {
                $end = min((int)$m[2], $fileSize - 1);
            }
            if ($start > $end || $start >= $fileSize) {
                http_response_code(416);
                header("Content-Range: bytes */{$fileSize}");
                exit;
            }
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($isPartial ? 206 : 200);
        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('Cache-Control: private, max-age=3600');
        if ($isPartial) {
            header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
        }

        $fp = fopen($fullPath, 'rb');
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remaining));
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($fp);
        exit;
    }

    /**
     * Normalizar e validar o path do anexo.
     * Aceita "assets/media/attachments/..." com ou sem prefixos ("/", URL completa).
     */
    private function normalizePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        // Aceitar URL completa apontando para o próprio sistema
        $parsed = parse_url($path, PHP_URL_PATH);
        if ($parsed) {
            $path = $parsed;
        }

        $path = ltrim($path, '/');

        // Bloquear path traversal
        if (strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            return null;
        }

        // Somente mídia de conversas
        if (strpos($path, 'assets/media/') !== 0) {
            return null;
        }

        return $path;
    }

    private function extractConversationId(string $path): ?int
    {
        // assets/media/attachments/{conversationId}/arquivo
        if (preg_match('#^assets/media/attachments/(\d+)/#', $path, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function computeSignature(string $path, int $expires): string
    {
        return hash_hmac('sha256', $path . '|' . $expires, $this->getSecret());
    }

    private function getSecret(): string
    {
        $secret = getenv('JWT_SECRET') ?: (\App\Models\Setting::get('jwt_secret') ?? null);

        if (!$secret) {
            $secret = bin2hex(random_bytes(32));
            \App\Models\Setting::set('jwt_secret', $secret);
        }

        return $secret;
    }
}
