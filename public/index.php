<?php
/**
 * Entry Point da Aplicação
 */

// Habilitar exibição de erros em desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
