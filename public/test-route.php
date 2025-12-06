<?php
/**
 * Teste de Roteamento
 * Acesse: http://localhost/chat/public/test-route.php?route=/login
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Helpers/autoload.php';

$testRoute = $_GET['route'] ?? '/login';

echo "<h1>Teste de Roteamento</h1>";
echo "<p>Testando rota: <strong>{$testRoute}</strong></p>";

// Simular REQUEST_URI
$_SERVER['REQUEST_URI'] = '/chat/public' . $testRoute;
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "<h2>Variáveis:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'não definido') . "\n";
echo "</pre>";

echo "<h2>Processamento:</h2>";
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = \App\Helpers\Url::basePath();

echo "<pre>";
echo "URI Original: {$uri}\n";
echo "Base Path: '{$basePath}'\n";

// Processar como o Router faz
if (!empty($basePath) && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
    echo "Após remover base path: {$uri}\n";
}

$uri = str_replace('/public', '', $uri);
echo "Após remover /public: {$uri}\n";

$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/') ?: '/';
echo "URI Final: {$uri}\n";
echo "</pre>";

echo "<h2>Rotas Registradas:</h2>";
require __DIR__ . '/../routes/web.php';

// Tentar encontrar a rota manualmente
echo "<pre>";
echo "Procurando rota que corresponda a: {$uri}\n";
echo "Método: {$method}\n";
echo "</pre>";

