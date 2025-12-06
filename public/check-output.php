<?php
/**
 * Verificar se há problemas com output buffering
 * Acesse: http://localhost/chat/public/check-output.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificação de Output</h1>";

echo "<h2>1. Output Buffering</h2>";
echo "<pre>";
echo "ob_get_level(): " . ob_get_level() . "\n";
echo "ob_get_contents(): " . (ob_get_contents() ?: 'vazio') . "\n";
echo "</pre>";

echo "<h2>2. Headers</h2>";
echo "<pre>";
echo "headers_sent(): " . (headers_sent() ? 'SIM' : 'NÃO') . "\n";
if (headers_sent($file, $line)) {
    echo "Headers enviados em: {$file}:{$line}\n";
}
echo "</pre>";

echo "<h2>3. Testando Response::view</h2>";
require_once __DIR__ . '/../app/Helpers/autoload.php';

try {
    ob_start();
    \App\Helpers\Response::view('auth/login', []);
    $output = ob_get_clean();
    
    echo "<pre>";
    echo "Output gerado: " . strlen($output) . " bytes\n";
    if (strlen($output) > 0) {
        echo "Primeiros 200 caracteres:\n";
        echo htmlspecialchars(substr($output, 0, 200)) . "\n";
    } else {
        echo "⚠️ Nenhum output gerado!\n";
    }
    echo "</pre>";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "<p style='color: red;'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

