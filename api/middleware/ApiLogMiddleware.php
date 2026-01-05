<?php
/**
 * Middleware de Logging
 * Registra todas as requisições da API
 */

namespace Api\Middleware;

use App\Models\ApiLog;

class ApiLogMiddleware
{
    private static float $startTime = 0;
    private static ?array $requestData = null;
    
    /**
     * Iniciar log (chamar no início da requisição)
     */
    public static function start(): void
    {
        self::$startTime = microtime(true);
        
        // Capturar dados da requisição
        $input = file_get_contents('php://input');
        
        self::$requestData = [
            'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'request_body' => $input ?: null,
            'request_headers' => json_encode(getallheaders() ?: []),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
    }
    
    /**
     * Finalizar log (chamar no final da requisição)
     */
    public static function end(int $responseCode, $responseBody = null, ?string $errorMessage = null): void
    {
        if (!self::$requestData) {
            return; // start() não foi chamado
        }
        
        $executionTime = (microtime(true) - self::$startTime) * 1000; // ms
        
        $user = ApiAuthMiddleware::user();
        $token = ApiAuthMiddleware::token();
        
        $logData = array_merge(self::$requestData, [
            'token_id' => $token['id'] ?? null,
            'user_id' => $user['id'] ?? null,
            'response_code' => $responseCode,
            'response_body' => is_string($responseBody) ? $responseBody : json_encode($responseBody),
            'error_message' => $errorMessage,
            'execution_time_ms' => round($executionTime)
        ]);
        
        try {
            ApiLog::logRequest($logData);
        } catch (\Exception $e) {
            // Não deixar erro de log quebrar a API
            error_log("Erro ao salvar API log: " . $e->getMessage());
        }
    }
    
    /**
     * Registrar buffer de saída (chamar com ob_start)
     */
    public static function captureOutput(): void
    {
        register_shutdown_function(function() {
            $output = ob_get_contents();
            $responseCode = http_response_code();
            
            self::end($responseCode, $output);
        });
        
        ob_start();
    }
}
