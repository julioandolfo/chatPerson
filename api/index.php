<?php
/**
 * Entry Point da API REST
 * Todas as requisições para /api/* passam por aqui
 */

// Iniciar sessão e carregar autoload
session_start();
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Autoload da API
spl_autoload_register(function ($class) {
    // Namespace Api\
    if (strpos($class, 'Api\\') === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Imports
use Api\Middleware\CorsMiddleware;
use Api\Middleware\ApiLogMiddleware;
use Api\Helpers\ApiResponse;

// Aplicar CORS
CorsMiddleware::handle();

// Iniciar logging
ApiLogMiddleware::start();
ApiLogMiddleware::captureOutput();

// Tratamento de erros global
set_exception_handler(function ($exception) {
    ApiLogMiddleware::end(500, null, $exception->getMessage());
    ApiResponse::serverError('Erro interno do servidor', $exception);
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

try {
    // Carregar rotas
    require_once __DIR__ . '/v1/routes.php';
} catch (\Exception $e) {
    ApiResponse::serverError('Erro ao processar requisição', $e);
}
