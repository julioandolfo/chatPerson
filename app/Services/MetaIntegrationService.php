<?php

namespace App\Services;

use App\Helpers\Logger;
use App\Models\MetaOAuthToken;

/**
 * MetaIntegrationService
 * 
 * Service base para integrações com APIs da Meta (Instagram + WhatsApp)
 * Compartilha lógica comum como autenticação, rate limiting, logs, etc.
 */
class MetaIntegrationService
{
    /**
     * Configurações da Meta
     */
    protected static array $config = [];
    
    /**
     * Cache de rate limits
     */
    protected static array $rateLimitCache = [];
    
    /**
     * Inicializar configurações
     */
    protected static function initConfig(): void
    {
        if (empty(self::$config)) {
            $configFile = __DIR__ . '/../../config/meta.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            }
        }
    }
    
    /**
     * Fazer requisição HTTP para APIs da Meta
     * 
     * @param string $url URL completa da API
     * @param string $accessToken Token de acesso
     * @param string $method GET, POST, PUT, DELETE
     * @param array $data Dados para enviar
     * @param array $headers Headers adicionais
     * @return array
     * @throws \Exception
     */
    public static function makeRequest(
        string $url,
        string $accessToken,
        string $method = 'GET',
        array $data = [],
        array $headers = []
    ): array {
        self::initConfig();
        
        // Log da requisição
        self::logDebug("Meta API Request: {$method} {$url}", [
            'method' => $method,
            'data' => $data,
        ]);
        
        // Preparar cURL
        $ch = curl_init();
        
        // Headers padrão
        $defaultHeaders = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        
        $allHeaders = array_merge($defaultHeaders, $headers);
        
        // Configurar cURL
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $allHeaders,
        ]);
        
        // Configurar método e dados
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
                
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }
        
        // Executar requisição
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Verificar erro de cURL
        if ($response === false) {
            self::logError("Meta API cURL Error: {$error}");
            throw new \Exception("Erro de conexão com Meta API: {$error}");
        }
        
        // Parse JSON
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::logError("Meta API Invalid JSON", [
                'response' => substr($response, 0, 500),
                'http_code' => $httpCode,
            ]);
            throw new \Exception("Resposta inválida da Meta API (HTTP {$httpCode})");
        }
        
        // Log da resposta
        self::logDebug("Meta API Response: HTTP {$httpCode}", [
            'http_code' => $httpCode,
            'response' => $decoded,
        ]);
        
        // Verificar erros da API
        if ($httpCode >= 400) {
            $errorMessage = self::extractErrorMessage($decoded);
            
            self::logError("Meta API Error: HTTP {$httpCode}", [
                'http_code' => $httpCode,
                'error' => $errorMessage,
                'response' => $decoded,
            ]);
            
            throw new \Exception("Erro na Meta API (HTTP {$httpCode}): {$errorMessage}");
        }
        
        return $decoded;
    }
    
    /**
     * Extrair mensagem de erro da resposta da Meta API
     */
    protected static function extractErrorMessage(array $response): string
    {
        // Formato Meta API: { "error": { "message": "...", "type": "...", "code": ... } }
        if (isset($response['error'])) {
            if (is_array($response['error'])) {
                $message = $response['error']['message'] ?? 'Erro desconhecido';
                $code = $response['error']['code'] ?? '';
                $type = $response['error']['type'] ?? '';
                
                return "{$message}" . ($code ? " (Código: {$code})" : "") . ($type ? " [Tipo: {$type}]" : "");
            }
            
            return is_string($response['error']) ? $response['error'] : json_encode($response['error']);
        }
        
        return json_encode($response);
    }
    
    /**
     * Obter token OAuth válido por Meta User ID
     */
    public static function getValidToken(string $metaUserId): ?array
    {
        $token = MetaOAuthToken::getByMetaUserId($metaUserId);
        
        if (!$token) {
            return null;
        }
        
        if (!MetaOAuthToken::isValid($token)) {
            self::logWarning("Token expirado ou inválido para Meta User ID: {$metaUserId}");
            return null;
        }
        
        // Marcar como usado
        MetaOAuthToken::markAsUsed($token['id']);
        
        return $token;
    }
    
    /**
     * Validar webhook signature da Meta
     * 
     * @param string $signature Header X-Hub-Signature-256
     * @param string $payload Raw body da requisição
     * @param string $appSecret App Secret da Meta
     * @return bool
     */
    public static function validateWebhookSignature(string $signature, string $payload, string $appSecret): bool
    {
        // Remover prefixo "sha256="
        $signature = str_replace('sha256=', '', $signature);
        
        // Calcular hash esperado
        $expectedHash = hash_hmac('sha256', $payload, $appSecret);
        
        // Comparar de forma segura
        return hash_equals($expectedHash, $signature);
    }
    
    /**
     * Verificar rate limit
     * 
     * @param string $api 'instagram' ou 'whatsapp'
     * @param string $identifier Identificador único (user_id ou phone_number_id)
     * @return bool True se pode fazer requisição, False se atingiu o limite
     */
    public static function checkRateLimit(string $api, string $identifier): bool
    {
        self::initConfig();
        
        $key = "{$api}:{$identifier}";
        $now = time();
        
        // Limites configurados
        $limits = self::$config['rate_limits'][$api] ?? null;
        if (!$limits) {
            return true; // Sem limite configurado
        }
        
        // Inicializar cache se não existe
        if (!isset(self::$rateLimitCache[$key])) {
            self::$rateLimitCache[$key] = [
                'requests' => [],
                'last_reset' => $now,
            ];
        }
        
        $cache = &self::$rateLimitCache[$key];
        
        // Resetar se passou 1 hora (Instagram) ou 1 segundo (WhatsApp)
        $resetInterval = $api === 'instagram' ? 3600 : 1;
        if ($now - $cache['last_reset'] >= $resetInterval) {
            $cache['requests'] = [];
            $cache['last_reset'] = $now;
        }
        
        // Remover requisições antigas
        $cache['requests'] = array_filter($cache['requests'], function($timestamp) use ($now, $resetInterval) {
            return ($now - $timestamp) < $resetInterval;
        });
        
        // Verificar limite
        $maxRequests = $api === 'instagram' 
            ? ($limits['requests_per_hour'] ?? 200)
            : ($limits['messages_per_second'] ?? 80);
        
        if (count($cache['requests']) >= $maxRequests) {
            self::logWarning("Rate limit atingido para {$api}:{$identifier}");
            return false;
        }
        
        // Registrar requisição
        $cache['requests'][] = $now;
        
        return true;
    }
    
    /**
     * Retry com backoff exponencial
     */
    public static function retryWithBackoff(callable $callback, int $maxAttempts = 3): mixed
    {
        self::initConfig();
        
        $retry = self::$config['retry'] ?? [
            'max_attempts' => 3,
            'initial_delay' => 2000,
            'backoff_multiplier' => 2,
        ];
        
        $attempt = 0;
        $delay = $retry['initial_delay'] ?? 2000;
        
        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $attempt++;
                
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                
                self::logWarning("Tentativa {$attempt} falhou, retentando em " . ($delay / 1000) . "s: {$e->getMessage()}");
                
                usleep($delay * 1000); // Converter para microsegundos
                $delay *= ($retry['backoff_multiplier'] ?? 2);
            }
        }
        
        throw new \Exception("Falha após {$maxAttempts} tentativas");
    }
    
    // ==================== LOGGING ====================
    
    protected static function logDebug(string $message, array $context = []): void
    {
        Logger::meta('DEBUG', $message, $context);
    }
    
    protected static function logInfo(string $message, array $context = []): void
    {
        Logger::meta('INFO', $message, $context);
    }
    
    protected static function logWarning(string $message, array $context = []): void
    {
        Logger::meta('WARNING', $message, $context);
    }
    
    protected static function logError(string $message, array $context = []): void
    {
        Logger::meta('ERROR', $message, $context);
    }
}

