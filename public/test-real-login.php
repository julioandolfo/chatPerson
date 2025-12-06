<?php
/**
 * Teste REAL do login - simula exatamente o que acontece quando acessa /login
 * Acesse: http://localhost/chat/public/test-real-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simular exatamente o que o index.php faz
$appConfig = require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/autoload.php';
date_default_timezone_set($appConfig['timezone']);
mb_internal_encoding('UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simular REQUEST exato
$_SERVER['REQUEST_URI'] = '/chat/public/login';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/chat/public/index.php';

echo "<h1>Teste Real do Login</h1>";
echo "<p>Simulando acesso a: <code>/chat/public/login</code></p>";

echo "<h2>Antes de carregar rotas:</h2>";
echo "<pre>";
echo "Output buffer level: " . ob_get_level() . "\n";
echo "Headers sent: " . (headers_sent() ? 'SIM' : 'NÃO') . "\n";
echo "</pre>";

// Carregar rotas
require __DIR__ . '/../routes/web.php';

echo "<h2>Depois de carregar rotas:</h2>";
echo "<pre>";
echo "Output buffer level: " . ob_get_level() . "\n";
echo "</pre>";

echo "<h2>Executando Router::dispatch()...</h2>";
echo "<p style='color: orange;'>Se você ver esta mensagem DEPOIS do Router, significa que o Router não fez exit/return corretamente.</p>";

// Tentar capturar o que o Router faz
ob_start();
try {
    \App\Helpers\Router::dispatch();
    $routerOutput = ob_get_clean();
    
    echo "<h2>Resultado:</h2>";
    if (strlen($routerOutput) > 0) {
        echo "<p style='color: green;'>✅ Router gerou output (" . strlen($routerOutput) . " bytes)</p>";
        echo "<div style='border: 2px solid green; padding: 10px;'>";
        echo $routerOutput;
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Router NÃO gerou output!</p>";
        echo "<p>Isso significa que:</p>";
        echo "<ul>";
        echo "<li>O Router executou mas não gerou output visível</li>";
        echo "<li>Ou o Router fez exit/redirect antes de gerar output</li>";
        echo "<li>Ou há um problema com output buffering</li>";
        echo "</ul>";
        
        // Verificar headers
        echo "<h3>Headers Enviados:</h3>";
        $headers = headers_list();
        if (empty($headers)) {
            echo "<p>Nenhum header enviado</p>";
        } else {
            echo "<pre>";
            foreach ($headers as $header) {
                echo $header . "\n";
            }
            echo "</pre>";
        }
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p><strong>Se você está vendo esta mensagem, o Router não fez exit corretamente.</strong></p>";

