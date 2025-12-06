<?php
/**
 * Debug detalhado do login
 * Acesse: http://localhost/chat/public/debug-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug do Login</h1>";
echo "<style>pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; }</style>";

// 1. Testar autoloader
echo "<h2>1. Autoloader</h2>";
try {
    require_once __DIR__ . '/../app/Helpers/autoload.php';
    echo "✅ Autoloader carregado<br>";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// 2. Testar Router
echo "<h2>2. Router - Processamento de URI</h2>";
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = \App\Helpers\Url::basePath();

echo "<pre>";
echo "REQUEST_URI: {$uri}\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Base Path: '{$basePath}'\n";
echo "Method: {$method}\n";
echo "</pre>";

// Processar URI como o Router faz
$uriProcessed = str_replace('\\', '/', $uri);
if (!empty($basePath) && strpos($uriProcessed, $basePath) === 0) {
    $uriProcessed = substr($uriProcessed, strlen($basePath));
}
$uriProcessed = str_replace('/public', '', $uriProcessed);
$uriProcessed = str_replace('/index.php', '', $uriProcessed);
$uriProcessed = rtrim($uriProcessed, '/') ?: '/';

echo "<pre>";
echo "URI Processado: '{$uriProcessed}'\n";
echo "</pre>";

// 3. Carregar rotas
echo "<h2>3. Rotas Registradas</h2>";
try {
    require __DIR__ . '/../routes/web.php';
    echo "✅ Rotas carregadas<br>";
} catch (\Throwable $e) {
    echo "❌ Erro ao carregar rotas: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// 4. Testar padrão de rota
echo "<h2>4. Testando Padrão de Rota</h2>";
$routePath = '/login';
$pattern = '#^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([a-zA-Z0-9_-]+)', $routePath) . '$#';
echo "<pre>";
echo "Rota: {$routePath}\n";
echo "Pattern: {$pattern}\n";
echo "URI para testar: {$uriProcessed}\n";
if (preg_match($pattern, $uriProcessed, $matches)) {
    echo "✅ MATCH! Matches: " . print_r($matches, true) . "\n";
} else {
    echo "❌ NÃO MATCH!\n";
}
echo "</pre>";

// 5. Testar Controller diretamente
echo "<h2>5. Testando Controller</h2>";
try {
    $controller = new \App\Controllers\AuthController();
    echo "✅ Controller criado<br>";
    
    echo "<h3>Testando método showLogin()</h3>";
    ob_start();
    $controller->showLogin();
    $output = ob_get_clean();
    
    if (empty($output)) {
        echo "⚠️ Método executou mas não retornou output<br>";
        echo "Verificando se houve redirect...<br>";
    } else {
        echo "✅ Output gerado (" . strlen($output) . " bytes)<br>";
        echo "<div style='border: 2px solid green; padding: 10px; margin: 10px 0;'>";
        echo $output;
        echo "</div>";
    }
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 6. Testar Response::view
echo "<h2>6. Testando Response::view</h2>";
try {
    $viewPath = __DIR__ . '/../views/auth/login.php';
    echo "<pre>";
    echo "Caminho da view: {$viewPath}\n";
    echo "Existe: " . (file_exists($viewPath) ? 'SIM' : 'NÃO') . "\n";
    if (file_exists($viewPath)) {
        echo "Tamanho: " . filesize($viewPath) . " bytes\n";
        echo "Legível: " . (is_readable($viewPath) ? 'SIM' : 'NÃO') . "\n";
    }
    echo "</pre>";
    
    echo "<h3>Conteúdo da view (primeiras 500 chars):</h3>";
    if (file_exists($viewPath)) {
        echo "<pre>" . htmlspecialchars(substr(file_get_contents($viewPath), 0, 500)) . "...</pre>";
    }
    
    echo "<h3>Testando require direto:</h3>";
    ob_start();
    try {
        extract(['error' => null, 'errors' => null, 'email' => '']);
        require $viewPath;
        $viewOutput = ob_get_clean();
        echo "✅ View carregada (" . strlen($viewOutput) . " bytes)<br>";
        if (strlen($viewOutput) > 0) {
            echo "<div style='border: 2px solid blue; padding: 10px; margin: 10px 0; max-height: 300px; overflow: auto;'>";
            echo substr($viewOutput, 0, 1000) . "...";
            echo "</div>";
        }
    } catch (\Throwable $e) {
        ob_end_clean();
        echo "❌ Erro ao carregar view: " . $e->getMessage() . "<br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 7. Testar Router completo
echo "<h2>7. Testando Router Completo</h2>";
echo "<p>Tentando executar Router::dispatch()...</p>";
ob_start();
try {
    \App\Helpers\Router::dispatch();
    $routerOutput = ob_get_clean();
    if (empty($routerOutput)) {
        echo "⚠️ Router executou mas não retornou output (pode ter feito redirect ou exit)<br>";
    } else {
        echo "✅ Router retornou output (" . strlen($routerOutput) . " bytes)<br>";
        echo "<div style='border: 2px solid purple; padding: 10px; margin: 10px 0;'>";
        echo $routerOutput;
        echo "</div>";
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "❌ Erro no Router: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<h2>Conclusão</h2>";
echo "<p>Verifique os resultados acima para identificar onde está o problema.</p>";

