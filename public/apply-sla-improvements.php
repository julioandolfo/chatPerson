<?php
/**
 * Script para aplicar todas as melhorias de SLA
 * 
 * Executa:
 * - Migrations de novos campos
 * - Migrations de tabelas de configuração
 * - Popular dados padrão
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('America/Sao_Paulo');

echo "╔═══════════════════════════════════════════════════════╗\n";
echo "║   APLICAR MELHORIAS DE SLA - Sistema Completo        ║\n";
echo "╚═══════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Lista de migrations a executar
    $migrations = [
        '069_add_sla_advanced_fields_to_conversations',
        '070_create_working_hours_config_table',
        '071_create_sla_rules_table',
        '072_add_priority_to_conversations'
    ];
    
    echo "📋 Migrations a executar: " . count($migrations) . "\n\n";
    
    foreach ($migrations as $migration) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "🔧 Executando: {$migration}\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        $file = __DIR__ . "/../database/migrations/{$migration}.php";
        
        if (!file_exists($file)) {
            echo "❌ Arquivo não encontrado: {$file}\n\n";
            continue;
        }
        
        require_once $file;
        
        // Chamar função up
        $functionName = 'up_' . $migration;
        if (function_exists($functionName)) {
            try {
                $functionName();
                echo "\n✅ Migration {$migration} executada com sucesso!\n\n";
            } catch (\Exception $e) {
                echo "\n⚠️  Erro ao executar migration: " . $e->getMessage() . "\n\n";
            }
        } else {
            echo "⚠️  Função {$functionName} não encontrada\n\n";
        }
    }
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔄 Limpando caches...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Limpar cache de working hours
    \App\Helpers\WorkingHoursCalculator::clearCache();
    echo "✅ Cache de horários limpo\n";
    
    // Limpar cache de permissões (se existir)
    if (file_exists(__DIR__ . '/../storage/cache/permissions')) {
        $files = glob(__DIR__ . '/../storage/cache/permissions/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "✅ Cache de permissões limpo\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Verificando estrutura do banco...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Verificar colunas adicionadas
    $conversationColumns = $pdo->query("SHOW COLUMNS FROM conversations")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedColumns = [
        'first_response_at',
        'first_human_response_at',
        'sla_paused_at',
        'sla_paused_duration',
        'sla_warning_sent',
        'reassignment_count',
        'last_reassignment_at',
        'priority'
    ];
    
    echo "Colunas em 'conversations':\n";
    foreach ($expectedColumns as $col) {
        $exists = in_array($col, $conversationColumns);
        echo "  " . ($exists ? "✅" : "❌") . " {$col}\n";
    }
    
    // Verificar tabelas criadas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'working_hours_config',
        'holidays',
        'sla_rules'
    ];
    
    echo "\nTabelas criadas:\n";
    foreach ($expectedTables as $table) {
        $exists = in_array($table, $tables);
        echo "  " . ($exists ? "✅" : "❌") . " {$table}\n";
    }
    
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ CONCLUÍDO COM SUCESSO!\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "📚 Próximos passos:\n";
    echo "1. Acessar Configurações → Conversas → SLA\n";
    echo "2. Configurar horários de trabalho (se necessário)\n";
    echo "3. Adicionar feriados específicos\n";
    echo "4. Criar regras de SLA personalizadas (opcional)\n";
    echo "5. Testar indicadores visuais na lista de conversas\n\n";
    
    echo "📖 Documentação: SLA_IMPROVEMENTS_DOCUMENTATION.md\n\n";
    
} catch (\Exception $e) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "❌ ERRO CRÍTICO\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
    exit(1);
}
