<?php
/**
 * Helper ApiResponse
 * Padronização de respostas da API
 */

namespace Api\Helpers;

class ApiResponse
{
    /**
     * Resposta de sucesso
     */
    public static function success($data = null, int $statusCode = 200, ?string $message = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => true
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Resposta de erro
     */
    public static function error(string $message, int $statusCode = 400, ?string $code = null, $details = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message
            ]
        ];
        
        if ($code !== null) {
            $response['error']['code'] = $code;
        }
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Resposta 400 - Bad Request
     */
    public static function badRequest(string $message = 'Requisição inválida', $details = null): void
    {
        self::error($message, 400, 'BAD_REQUEST', $details);
    }
    
    /**
     * Resposta 401 - Unauthorized
     */
    public static function unauthorized(string $message = 'Token inválido ou expirado'): void
    {
        self::error($message, 401, 'UNAUTHORIZED');
    }
    
    /**
     * Resposta 403 - Forbidden
     */
    public static function forbidden(string $message = 'Você não tem permissão para esta ação'): void
    {
        self::error($message, 403, 'FORBIDDEN');
    }
    
    /**
     * Resposta 404 - Not Found
     */
    public static function notFound(string $message = 'Recurso não encontrado'): void
    {
        self::error($message, 404, 'NOT_FOUND');
    }
    
    /**
     * Resposta 422 - Unprocessable Entity (erro de validação)
     */
    public static function validationError(string $message = 'Dados inválidos', array $errors = []): void
    {
        self::error($message, 422, 'VALIDATION_ERROR', $errors);
    }
    
    /**
     * Resposta 429 - Too Many Requests
     */
    public static function tooManyRequests(string $message = 'Muitas requisições. Tente novamente mais tarde.'): void
    {
        self::error($message, 429, 'TOO_MANY_REQUESTS');
    }
    
    /**
     * Resposta 500 - Internal Server Error
     */
    public static function serverError(string $message = 'Erro interno do servidor', ?\Exception $exception = null): void
    {
        // Log do erro
        if ($exception) {
            error_log("API Server Error: " . $exception->getMessage());
            error_log("Stack Trace: " . $exception->getTraceAsString());
        }
        
        // Em produção, não expor detalhes do erro
        $isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        
        $details = null;
        if ($isDevelopment && $exception) {
            $details = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }
        
        self::error($message, 500, 'SERVER_ERROR', $details);
    }
    
    /**
     * Resposta de paginação
     */
    public static function paginated(array $data, int $total, int $page, int $perPage): void
    {
        $totalPages = ceil($total / $perPage);
        
        self::success([
            'items' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    /**
     * Resposta 201 - Created
     */
    public static function created($data, string $message = 'Recurso criado com sucesso'): void
    {
        self::success($data, 201, $message);
    }
    
    /**
     * Resposta 204 - No Content
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}
