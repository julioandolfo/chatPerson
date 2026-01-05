<?php
/**
 * Middleware CORS
 * Gerenciar Cross-Origin Resource Sharing
 */

namespace Api\Middleware;

class CorsMiddleware
{
    /**
     * Aplicar headers CORS
     */
    public static function handle(): void
    {
        // Obter origem permitida (pode configurar em settings)
        $allowedOrigins = self::getAllowedOrigins();
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Verificar se origem é permitida
        if (self::isOriginAllowed($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
        } else {
            // Permitir todas (ajustar conforme necessário)
            header('Access-Control-Allow-Origin: *');
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 horas
        
        // Responder a OPTIONS (preflight)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    /**
     * Obter origens permitidas
     */
    private static function getAllowedOrigins(): array
    {
        // Tentar obter das configurações
        $setting = \App\Models\Setting::get('api_allowed_origins');
        
        if ($setting) {
            return array_map('trim', explode(',', $setting));
        }
        
        // Padrão: localhost e domínio atual
        $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
        
        return [
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8080',
            "https://{$currentDomain}",
            "http://{$currentDomain}"
        ];
    }
    
    /**
     * Verificar se origem é permitida
     */
    private static function isOriginAllowed(string $origin, array $allowedOrigins): bool
    {
        if (empty($origin)) {
            return false;
        }
        
        foreach ($allowedOrigins as $allowed) {
            // Wildcard
            if ($allowed === '*') {
                return true;
            }
            
            // Match exato
            if ($origin === $allowed) {
                return true;
            }
            
            // Wildcard no subdomínio (ex: *.example.com)
            if (strpos($allowed, '*.') === 0) {
                $domain = substr($allowed, 2);
                if (substr($origin, -strlen($domain)) === $domain) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
