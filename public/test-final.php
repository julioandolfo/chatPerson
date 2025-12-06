<?php
/**
 * Teste FINAL - Simula exatamente o index.php
 * Acesse: http://localhost/chat/public/test-final.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- INÍCIO DO TESTE -->\n";

// Simular exatamente o index.php
$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';
date_default_timezone_set($appConfig['timezone']);
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simular REQUEST
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

echo "<!-- ANTES DE CARREGAR ROTAS -->\n";

require __DIR__ . '/../routes/web.php';

echo "<!-- ANTES DE EXECUTAR ROUTER -->\n";

use App\Helpers\Router;
Router::dispatch();

echo "<!-- SE VOCÊ ESTÁ VENDO ISSO, O ROUTER NÃO FEZ EXIT -->\n";

