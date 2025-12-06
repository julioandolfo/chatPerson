<?php
/**
 * Debug do Router
 * Acesse: http://localhost/chat/public/debug-router.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Helpers/autoload.php';

echo "<h1>Debug do Router</h1>";

echo "<h2>Variáveis do Servidor:</h2>";
echo "<pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'não definido') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'não definido') . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'não definido') . "\n";
echo "</pre>";

echo "<h2>URL Helper:</h2>";
echo "<pre>";
$basePath = \App\Helpers\Url::basePath();
echo "Base Path: '{$basePath}'\n";
echo "URL Login: " . \App\Helpers\Url::to('/login') . "\n";
echo "URL Dashboard: " . \App\Helpers\Url::to('/dashboard') . "\n";
echo "</pre>";

echo "<h2>Processamento do Router:</h2>";
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
echo "<pre>";
echo "Method: {$method}\n";
echo "URI Original: {$uri}\n";

// Processar como o Router faz
$uri = str_replace('\\', '/', $uri);
$uri = str_replace('/public', '', $uri);

if (!empty($basePath) && strpos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

$uri = str_replace('/index.php', '', $uri);
$uri = rtrim($uri, '/') ?: '/';

echo "URI Processado: {$uri}\n";
echo "</pre>";

echo "<h2>Rotas Registradas:</h2>";
echo "<pre>";
require __DIR__ . '/../routes/web.php';
// Não podemos acessar rotas privadas, mas podemos verificar se foram carregadas
echo "Rotas carregadas do arquivo web.php\n";
echo "</pre>";

