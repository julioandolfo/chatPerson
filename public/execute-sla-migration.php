<?php
/**
 * Script para executar migration da tabela sla_rules
 * 
 * Executar via terminal: php public/execute-sla-migration.php
 * Ou via navegador: http://seu-dominio.com/execute-sla-migration.php
 */

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/Helpers/autoload.php';

// Se for via web, usar HTML
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Executar Migration SLA</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
    echo ".success{color:#4ec9b0;}.error{color:#f48771;}.warning{color:#dcdcaa;}";
    echo "pre{background:#252526;padding:15px;border-radius:5px;border-left:3px solid #007acc;}</style>";
    echo "</head><body>";
    echo "<h1>🔧 Executar Migration SLA Rules</h1>";
}

try {
    // Conectar ao banco
    $dbConfig = require __DIR__ . '/../config/database.php';
    
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['options']
    );
    
    // Usar database
    $pdo->exec("USE `{$dbConfig['database']}`");
    
    echo $isWeb ? "<pre>" : "";
    echo "📊 Conectado ao banco: {$dbConfig['database']}\n\n";
    
    // Verificar se tabela já existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'sla_rules'");
    $tableExists = $checkTable->rowCount() > 0;
    
    if ($tableExists) {
        echo "⚠️  ATENÇÃO: Tabela 'sla_rules' já existe!\n";
        echo "   Deseja recriar? (Dados serão perdidos)\n\n";
        
        if ($isWeb) {
            echo "</pre>";
            echo "<div style='margin:20px 0;'>";
            echo "<a href='?force=1' style='padding:10px 20px;background:#f48771;color:white;text-decoration:none;border-radius:5px;'>SIM, RECRIAR TABELA</a> ";
            echo "<a href='view-all-logs.php' style='padding:10px 20px;background:#569cd6;color:white;text-decoration:none;border-radius:5px;'>Não, Ver Logs</a>";
            echo "</div>";
            
            if (!isset($_GET['force'])) {
                echo "</body></html>";
                exit;
            }
            
            echo "<pre>";
            echo "\n🔄 Recriando tabela...\n\n";
            $pdo->exec("DROP TABLE IF EXISTS sla_rules");
            echo "   ✅ Tabela antiga removida\n";
        } else {
            echo "   Execute com --force para recriar: php public/execute-sla-migration.php --force\n";
            
            if (!in_array('--force', $argv ?? [])) {
                exit(1);
            }
            
            echo "\n🔄 Recriando tabela...\n\n";
            $pdo->exec("DROP TABLE IF EXISTS sla_rules");
            echo "   ✅ Tabela antiga removida\n";
        }
    }
    
    // Carregar e executar migration
    $migrationFile = __DIR__ . '/../database/migrations/071_create_sla_rules_table.php';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration não encontrada: {$migrationFile}");
    }
    
    // Incluir migration
    require_once $migrationFile;
    
    // Executar função up
    if (!function_exists('up_create_sla_rules')) {
        throw new Exception("Função 'up_create_sla_rules' não encontrada na migration");
    }
    
    echo "🚀 Executando migration 071_create_sla_rules_table...\n\n";
    
    ob_start();
    up_create_sla_rules();
    $output = ob_get_clean();
    
    echo $output . "\n";
    
    // Verificar se tabela foi criada
    $checkTable = $pdo->query("SHOW TABLES LIKE 'sla_rules'");
    if ($checkTable->rowCount() > 0) {
        // Contar registros
        $count = $pdo->query("SELECT COUNT(*) as total FROM sla_rules")->fetch(PDO::FETCH_ASSOC);
        
        echo "\n✅ SUCESSO! Tabela 'sla_rules' criada com {$count['total']} regras padrão\n\n";
        
        // Listar regras
        echo "📋 Regras de SLA cadastradas:\n";
        $rules = $pdo->query("SELECT * FROM sla_rules ORDER BY priority DESC")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rules as $rule) {
            echo "   • {$rule['name']}: ";
            echo "Primeira resposta: {$rule['first_response_time']}min | ";
            echo "Resolução: {$rule['resolution_time']}min | ";
            echo "Contínua: {$rule['ongoing_response_time']}min\n";
        }
        
        if ($isWeb) {
            echo "</pre>";
            echo "<div class='success' style='margin:20px 0;padding:15px;background:#1e3a1e;border-radius:5px;'>";
            echo "<h2>✅ Migration executada com sucesso!</h2>";
            echo "<p>A tabela 'sla_rules' foi criada e o erro não deve mais ocorrer.</p>";
            echo "</div>";
            echo "<div style='margin:20px 0;'>";
            echo "<a href='view-all-logs.php' style='padding:10px 20px;background:#4ec9b0;color:white;text-decoration:none;border-radius:5px;'>📋 Ver Logs</a> ";
            echo "<a href='/dashboard' style='padding:10px 20px;background:#569cd6;color:white;text-decoration:none;border-radius:5px;'>🏠 Dashboard</a>";
            echo "</div>";
            echo "<div class='warning' style='padding:10px;background:#3c3c1e;border-radius:5px;margin-top:20px;'>";
            echo "⚠️ <strong>IMPORTANTE:</strong> Remova este arquivo por segurança: <code>public/execute-sla-migration.php</code>";
            echo "</div>";
        } else {
            echo "\n💡 Dica: Você pode remover este script agora:\n";
            echo "   rm public/execute-sla-migration.php\n";
        }
    } else {
        throw new Exception("Tabela não foi criada corretamente");
    }
    
} catch (Exception $e) {
    echo $isWeb ? "<div class='error'>" : "";
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
    echo $isWeb ? "</div>" : "";
    exit(1);
}

if ($isWeb) {
    echo "</body></html>";
}
