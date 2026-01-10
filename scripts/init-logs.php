<?php
/**
 * Script para inicializar arquivos de log
 * 
 * Uso: php scripts/init-logs.php
 */

$logsDir = __DIR__ . '/../logs';
$storageLogsDir = __DIR__ . '/../storage/logs';

// Criar diretórios se não existirem
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    echo "✅ Diretório 'logs' criado\n";
}

if (!is_dir($storageLogsDir)) {
    mkdir($storageLogsDir, 0755, true);
    echo "✅ Diretório 'storage/logs' criado\n";
}

// Lista de arquivos de log
$logFiles = [
    'logs/coaching.log' => '🎯 Sistema de Coaching em Tempo Real',
    'logs/app.log' => '📱 Aplicação',
    'logs/conversas.log' => '💬 Conversas',
    'logs/quepasa.log' => '📞 Quepasa (WhatsApp)',
    'logs/automacao.log' => '🤖 Automações',
    'logs/ai_agent.log' => '🧠 Agentes de IA',
    'logs/ai_tools.log' => '🔧 Ferramentas de IA',
    'logs/kanban_agents.log' => '📊 Kanban Agents',
    'logs/error.log' => '❌ Erros PHP',
    'storage/logs/kanban-agents-cron.log' => '⏰ Kanban Agents Cron',
];

$created = 0;
$existing = 0;

foreach ($logFiles as $file => $description) {
    $fullPath = __DIR__ . '/../' . $file;
    
    if (!file_exists($fullPath)) {
        $timestamp = date('Y-m-d H:i:s');
        $content = "[{$timestamp}] {$description} - Log iniciado\n";
        
        if (file_put_contents($fullPath, $content)) {
            echo "✅ Criado: {$file}\n";
            $created++;
        } else {
            echo "❌ Erro ao criar: {$file}\n";
        }
    } else {
        echo "ℹ️  Já existe: {$file}\n";
        $existing++;
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Resumo:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "✅ Criados: {$created}\n";
echo "ℹ️  Já existiam: {$existing}\n";
echo "📋 Total: " . count($logFiles) . "\n";
echo "\n";
echo "🎉 Todos os arquivos de log estão prontos!\n";
echo "📍 Visualize em: /view-all-logs.php\n";
echo "\n";
