<?php
/**
 * Teste simples do Router
 * Acesse: http://localhost/chat/public/test-router-simple.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste Simples do Router</h1>";

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Configurar
$appConfig = require __DIR__ . '/../config/app.php';
date_default_timezone_set($appConfig['timezone']);
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simular requisição
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

echo "<h2>Antes do Router</h2>";
echo "<pre>";
echo "Output buffer level: " . ob_get_level() . "\n";
echo "Headers sent: " . (headers_sent() ? 'SIM' : 'NÃO') . "\n";
echo "</pre>";

// Carregar rotas
require __DIR__ . '/../routes/web.php';

echo "<h2>Executando Router</h2>";
echo "<p>Chamando Router::dispatch()...</p>";

// NÃO usar ob_start aqui - deixar o Router gerenciar
try {
    \App\Helpers\Router::dispatch();
    echo "<p style='color: red;'>⚠️ Router executou mas não fez exit/return (isso não deveria acontecer)</p>";
} catch (\Throwable $e) {
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<h2>Depois do Router</h2>";
echo "<pre>";
echo "Output buffer level: " . ob_get_level() . "\n";
echo "Headers sent: " . (headers_sent() ? 'SIM' : 'NÃO') . "\n";
echo "</pre>";

