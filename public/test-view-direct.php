<?php
/**
 * Teste direto da view de login
 * Acesse: http://localhost/chat/public/test-view-direct.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Limpar output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once __DIR__ . '/../app/Helpers/autoload.php';

echo "<h1>Teste Direto da View</h1>";

$viewPath = __DIR__ . '/../views/auth/login.php';

echo "<h2>Informações da View</h2>";
echo "<pre>";
echo "Caminho: {$viewPath}\n";
echo "Existe: " . (file_exists($viewPath) ? 'SIM' : 'NÃO') . "\n";
if (file_exists($viewPath)) {
    echo "Tamanho: " . filesize($viewPath) . " bytes\n";
    echo "Legível: " . (is_readable($viewPath) ? 'SIM' : 'NÃO') . "\n";
}
echo "</pre>";

echo "<h2>Testando require direto:</h2>";
echo "<div style='border: 2px solid green; padding: 10px;'>";

try {
    // Variáveis que a view pode precisar
    $error = null;
    $errors = null;
    $email = '';
    
    ob_start();
    require $viewPath;
    $output = ob_get_clean();
    
    echo "<p><strong>Output gerado:</strong> " . strlen($output) . " bytes</p>";
    
    if (strlen($output) > 0) {
        echo "<p style='color: green;'>✅ View renderizada com sucesso!</p>";
        echo "<hr>";
        echo $output;
    } else {
        echo "<p style='color: red;'>❌ View não gerou output!</p>";
        echo "<p>Verificando se há erros silenciosos...</p>";
    }
} catch (\Throwable $e) {
    ob_end_clean();
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</div>";

