<?php
// Script de teste super simples
header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== TESTE SIMPLES ===\n\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Current directory: " . getcwd() . "\n";
echo "Script path: " . __FILE__ . "\n";
echo "Date/Time: " . date('Y-m-d H:i:s') . "\n\n";

// Testar se consegue carregar o bootstrap
$rootDir = dirname(__DIR__, 2);
echo "Root directory: {$rootDir}\n";

$bootstrapPath = $rootDir . '/config/bootstrap.php';
echo "Bootstrap path: {$bootstrapPath}\n";
echo "Bootstrap exists: " . (file_exists($bootstrapPath) ? 'SIM' : 'NÃO') . "\n\n";

if (file_exists($bootstrapPath)) {
    echo "Tentando carregar bootstrap...\n";
    try {
        require_once $bootstrapPath;
        echo "✅ Bootstrap carregado com sucesso!\n";
    } catch (\Throwable $e) {
        echo "❌ ERRO ao carregar bootstrap:\n";
        echo $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    }
}

echo "\n=== FIM DO TESTE ===\n";
