<?php
/**
 * Entry Point da Aplicação
 */

// Habilitar exibição de erros em desenvolvimento
error_reporting(E_ALL);

// Para requisições AJAX/API (JSON), desabilitar display_errors para evitar HTML na resposta
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$acceptsJson = !empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
$isJsonRequest = $isAjax || $acceptsJson || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

if ($isJsonRequest) {
    // Para APIs, logar erros mas NÃO exibir (para evitar HTML no JSON)
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/app.log');
} else {
    // Para páginas HTML, exibir erros normalmente
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
