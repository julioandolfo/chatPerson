<?php
/**
 * Script para sincronizar a view de logs para o Docker
 * 
 * Uso:
 *   php scripts/sync-logs-view.php
 *   ou
 *   docker exec -it seu-container php /var/www/html/scripts/sync-logs-view.php
 */

$baseDir = __DIR__ . '/..';
$viewsDir = $baseDir . '/views';
$logsDir = $viewsDir . '/logs';
$logViewFile = $logsDir . '/index.php';

echo "🔄 Sincronizando view de logs...\n\n";

// Criar diretório se não existir
if (!is_dir($logsDir)) {
    echo "📁 Criando diretório: {$logsDir}\n";
    if (!mkdir($logsDir, 0755, true)) {
        die("❌ Erro ao criar diretório: {$logsDir}\n");
    }
    echo "✅ Diretório criado com sucesso!\n\n";
} else {
    echo "✅ Diretório já existe: {$logsDir}\n\n";
}

// Verificar se o arquivo existe
if (file_exists($logViewFile)) {
    echo "✅ Arquivo já existe: {$logViewFile}\n";
    echo "📊 Tamanho: " . number_format(filesize($logViewFile)) . " bytes\n";
    echo "📅 Modificado: " . date('Y-m-d H:i:s', filemtime($logViewFile)) . "\n";
} else {
    echo "❌ Arquivo não encontrado: {$logViewFile}\n";
    echo "⚠️  Certifique-se de que o arquivo foi criado localmente primeiro.\n";
    echo "   Execute: php scripts/create-logs-view.php (se existir)\n";
    exit(1);
}

echo "\n✅ Sincronização concluída!\n";

