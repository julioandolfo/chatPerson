<?php
/**
 * Migration: Corrigir tabela automation_executions
 * Adicionar coluna updated_at que está faltando
 */

function up_fix_automation_executions_table() {
    global $pdo;
    
    echo "🔧 Verificando tabela automation_executions...\n";
    
    // Verificar se a coluna updated_at existe
    $stmt = $pdo->query("SHOW COLUMNS FROM automation_executions LIKE 'updated_at'");
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "  → Adicionando coluna 'updated_at'...\n";
        $pdo->exec("
            ALTER TABLE automation_executions
            ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            AFTER created_at
        ");
        echo "  ✅ Coluna 'updated_at' adicionada!\n";
    } else {
        echo "  ✅ Coluna 'updated_at' já existe\n";
    }
    
    echo "✅ Migration 061 concluída!\n\n";
}

function down_fix_automation_executions_table() {
    global $pdo;
    
    echo "🔧 Revertendo migration 061...\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM automation_executions LIKE 'updated_at'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        $pdo->exec("ALTER TABLE automation_executions DROP COLUMN updated_at");
        echo "✅ Coluna 'updated_at' removida\n";
    }
    
    echo "✅ Rollback 061 concluído!\n\n";
}

