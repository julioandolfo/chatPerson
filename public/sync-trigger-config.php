<?php
/**
 * Script para sincronizar trigger_config das automações existentes
 * 
 * Execute via CLI: php public/sync-trigger-config.php
 * Ou via browser: http://localhost/sync-trigger-config.php
 */

// Bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

// Verificar se está sendo executado via CLI ou browser
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    // Se for via browser, verificar autenticação
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die('❌ Acesso negado. Faça login primeiro.');
    }
    
    // Adicionar header HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sincronizar Trigger Config</title>
        <style>
            body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
            .success { color: #4ec9b0; }
            .error { color: #f48771; }
            .warning { color: #ce9178; }
            .info { color: #569cd6; }
        </style>
    </head>
    <body>
    <pre>';
}

try {
    // Carregar migration
    require_once __DIR__ . '/../database/migrations/085_sync_trigger_config.php';
    
    echo ($isCli ? "" : "<span class='info'>") . "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║   Sincronizar Trigger Config das Automações Existentes   ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝" . ($isCli ? "" : "</span>") . "\n\n";
    
    // Executar migration
    up_sync_trigger_config();
    
    echo "\n";
    echo ($isCli ? "" : "<span class='success'>") . "✅ Processo concluído com sucesso!" . ($isCli ? "" : "</span>") . "\n";
    
    if (!$isCli) {
        echo "\n<a href='/automations' style='color: #569cd6;'>← Voltar para Automações</a>";
    }
    
} catch (\Exception $e) {
    echo ($isCli ? "" : "<span class='error'>") . "❌ ERRO: " . $e->getMessage() . ($isCli ? "" : "</span>") . "\n";
    echo ($isCli ? "" : "<span class='error'>") . "Stack trace:\n" . $e->getTraceAsString() . ($isCli ? "" : "</span>") . "\n";
    exit(1);
}

if (!$isCli) {
    echo '</pre>
    </body>
    </html>';
}

