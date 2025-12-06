<?php
/**
 * Teste direto do login sem passar pelo Router
 * Acesse: http://localhost/chat/public/test-login-direct.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Helpers/autoload.php';

echo "<h1>Teste Direto do Login</h1>";

// Simular que estamos acessando /login
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

echo "<h2>Simulando acesso a /login</h2>";
echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "</pre>";

echo "<h2>Testando Router:</h2>";
try {
    require __DIR__ . '/../routes/web.php';
    \App\Helpers\Router::dispatch();
} catch (\Throwable $e) {
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

