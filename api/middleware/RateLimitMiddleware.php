<?php
/**
 * Middleware de Rate Limiting
 * Limita número de requisições por minuto
 */

namespace Api\Middleware;

use Api\Helpers\ApiResponse;
use App\Models\ApiToken;

class RateLimitMiddleware
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/rate_limit/';
    
    /**
     * Verificar limite de requisições
     */
    public static function handle(?int $limit = null, ?string $identifier = null): void
    {
        // Criar diretório de cache se não existir
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        // Definir identificador (IP ou token)
        if ($identifier === null) {
            $user = ApiAuthMiddleware::user();
            $token = ApiAuthMiddleware::token();
            
            if ($token) {
                $identifier = "token_{$token['id']}";
                $limit = $limit ?? $token['rate_limit'] ?? 100;
            } elseif ($user) {
                $identifier = "user_{$user['id']}";
                $limit = $limit ?? 100;
            } else {
                $identifier = "ip_" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                $limit = $limit ?? 60; // Mais restritivo para não autenticados
            }
        }
        
        $limit = $limit ?? 100;
        
        // Obter contador atual
        $cacheFile = self::$cacheDir . md5($identifier) . '.json';
        $data = self::getCache($cacheFile);
        
        if (!$data) {
            $data = [
                'count' => 0,
                'reset_at' => time() + 60 // 1 minuto
            ];
        }
        
        // Reset se passou o tempo
        if (time() > $data['reset_at']) {
            $data = [
                'count' => 0,
                'reset_at' => time() + 60
            ];
        }
        
        // Incrementar contador
        $data['count']++;
        
        // Verificar limite
        if ($data['count'] > $limit) {
            $retryAfter = $data['reset_at'] - time();
            
            header("X-RateLimit-Limit: {$limit}");
            header("X-RateLimit-Remaining: 0");
            header("X-RateLimit-Reset: {$data['reset_at']}");
            header("Retry-After: {$retryAfter}");
            
            ApiResponse::tooManyRequests("Limite de {$limit} requisições por minuto excedido");
        }
        
        // Salvar contador
        self::setCache($cacheFile, $data);
        
        // Adicionar headers de rate limit
        $remaining = $limit - $data['count'];
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$data['reset_at']}");
    }
    
    /**
     * Obter cache
     */
    private static function getCache(string $file): ?array
    {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        
        if (!$content) {
            return null;
        }
        
        return json_decode($content, true);
    }
    
    /**
     * Salvar cache
     */
    private static function setCache(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data));
    }
    
    /**
     * Limpar cache antigo (chamado periodicamente)
     */
    public static function cleanOldCache(): int
    {
        $count = 0;
        $now = time();
        
        if (!is_dir(self::$cacheDir)) {
            return 0;
        }
        
        $files = glob(self::$cacheDir . '*.json');
        
        foreach ($files as $file) {
            $data = self::getCache($file);
            
            if ($data && $now > $data['reset_at'] + 3600) { // 1 hora após reset
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
}
