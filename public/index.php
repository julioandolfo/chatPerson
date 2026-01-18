<?php
/**
 * Entry Point da Aplicação
 */

// Habilitar exibição de erros em desenvolvimento (mas não deprecated)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Garantir que o diretório de logs existe
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Para requisições AJAX/API (JSON), desabilitar display_errors para evitar HTML na resposta
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptsJson = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$isJsonRequest = $isAjax || $acceptsJson || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isJsonRequest) {
    // Para APIs, logar erros mas NÃO exibir (para evitar HTML no JSON)
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', $logsDir . '/app.log');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
    
    // Manipulador de erros personalizado para requisições JSON
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logsDir) {
        // Logar o erro mas não exibir
        error_log("[$errno] $errstr in $errfile on line $errline", 3, $logsDir . '/app.log');
        return true; // Não executar o handler padrão do PHP
    });
    
    // Iniciar buffer de output para capturar qualquer saída indesejada
    ob_start();
} else {
    // Para páginas HTML, exibir erros normalmente (mas não deprecated)
    ini_set('display_errors', '1');
}

// Carregar configurações primeiro
$appConfig = require __DIR__ . '/../config/app.php';

// Carregar autoloader
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Definir timezone
date_default_timezone_set($appConfig['timezone']);

// Definir encoding
mb_internal_encoding('UTF-8');

// Iniciar sessão (apenas se não estiver iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Carregar rotas
require __DIR__ . '/../routes/web.php';

// Executar rotas
use App\Helpers\Router;
Router::dispatch();
