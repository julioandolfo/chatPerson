<?php
/**
 * Teste completo do fluxo de login
 * Acesse: http://localhost/chat/public/test-full-flow.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Limpar tudo
while (ob_get_level() > 0) {
    ob_end_clean();
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Login</title>";
echo "<style>pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; margin: 10px 0; }</style>";
echo "</head><body>";

echo "<h1>Teste Completo do Fluxo de Login</h1>";

// Passo 1: Autoloader
echo "<h2>Passo 1: Autoloader</h2>";
try {
    require_once __DIR__ . '/../app/Helpers/autoload.php';
    echo "✅ Autoloader carregado<br>";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    exit;
}

// Passo 2: Configurações
echo "<h2>Passo 2: Configurações</h2>";
try {
    $appConfig = require __DIR__ . '/../config/app.php';
    echo "✅ Configurações carregadas<br>";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    exit;
}

// Passo 3: Sessão
echo "<h2>Passo 3: Sessão</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Sessão iniciada<br>";
} else {
    echo "✅ Sessão já estava ativa<br>";
}

// Passo 4: Simular REQUEST
echo "<h2>Passo 4: Simulando Requisição</h2>";
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

echo "<pre>";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "</pre>";

// Passo 5: Carregar rotas
echo "<h2>Passo 5: Carregar Rotas</h2>";
try {
    require __DIR__ . '/../routes/web.php';
    echo "✅ Rotas carregadas<br>";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

// Passo 6: Testar Router
echo "<h2>Passo 6: Executar Router</h2>";
echo "<p>Executando Router::dispatch()...</p>";

// Capturar output
ob_start();
try {
    \App\Helpers\Router::dispatch();
    $output = ob_get_clean();
    
    echo "<pre>";
    echo "Output buffer nível antes: " . ob_get_level() . "\n";
    echo "Tamanho do output: " . strlen($output) . " bytes\n";
    echo "</pre>";
    
    if (strlen($output) > 0) {
        echo "<div style='border: 2px solid green; padding: 10px; margin: 10px 0;'>";
        echo "<h3>✅ Output Gerado:</h3>";
        echo $output;
        echo "</div>";
    } else {
        echo "<p style='color: orange;'>⚠️ Router executou mas não gerou output visível</p>";
        echo "<p>Possíveis causas:</p>";
        echo "<ul>";
        echo "<li>Router fez redirect (verifique headers)</li>";
        echo "<li>Router chamou exit sem output</li>";
        echo "<li>View não está gerando output</li>";
        echo "</ul>";
        
        // Verificar headers
        echo "<h3>Headers Enviados:</h3>";
        echo "<pre>";
        $headers = headers_list();
        if (empty($headers)) {
            echo "Nenhum header enviado\n";
        } else {
            foreach ($headers as $header) {
                echo $header . "\n";
            }
        }
        echo "</pre>";
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "<p style='color: red;'><strong>❌ Erro no Router:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";

