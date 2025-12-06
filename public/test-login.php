<?php
/**
 * Teste simples da página de login
 * Acesse: http://localhost/chat/public/test-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste de Login</h1>";

// Testar autoloader
echo "<h2>1. Testando autoloader...</h2>";
require_once __DIR__ . '/../app/Helpers/autoload.php';
echo "✅ Autoloader carregado<br>";

// Testar Url helper
echo "<h2>2. Testando Url helper...</h2>";
try {
    $basePath = \App\Helpers\Url::basePath();
    echo "✅ Base Path: '{$basePath}'<br>";
    
    $url = \App\Helpers\Url::to('/login');
    echo "✅ URL Login: '{$url}'<br>";
    
    $asset = \App\Helpers\Url::asset('css/style.css');
    echo "✅ Asset: '{$asset}'<br>";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Testar view
echo "<h2>3. Testando view de login...</h2>";
try {
    $viewPath = __DIR__ . '/../views/auth/login.php';
    echo "Caminho da view: {$viewPath}<br>";
    
    if (file_exists($viewPath)) {
        echo "✅ Arquivo existe<br>";
        echo "Tamanho: " . filesize($viewPath) . " bytes<br>";
    } else {
        echo "❌ Arquivo não existe<br>";
    }
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

// Testar Response::view
echo "<h2>4. Testando Response::view...</h2>";
try {
    \App\Helpers\Response::view('auth/login', []);
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

