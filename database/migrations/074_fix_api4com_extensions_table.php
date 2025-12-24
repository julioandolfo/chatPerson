<?php
/**
 * Migration: Corrigir tabela api4com_extensions
 * - Tornar user_id nullable
 * - Remover constraint unique_user_account
 * - Adicionar constraint única por extension_id + account
 */

function up_fix_api4com_extensions_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // 1. Remover a constraint unique_user_account se existir
    try {
        $db->exec("ALTER TABLE api4com_extensions DROP INDEX unique_user_account");
        echo "✅ Constraint 'unique_user_account' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️ Constraint 'unique_user_account' não existe ou já foi removida\n";
    }
    
    // 2. Alterar user_id para NULL
    try {
        $db->exec("ALTER TABLE api4com_extensions MODIFY COLUMN user_id INT NULL COMMENT 'Usuário do sistema'");
        echo "✅ Coluna 'user_id' alterada para NULL!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao alterar user_id: " . $e->getMessage() . "\n";
    }
    
    // 3. Remover a foreign key de user_id (se existir) e recriar como opcional
    try {
        // Encontrar o nome da FK
        $result = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                              WHERE TABLE_NAME = 'api4com_extensions' 
                              AND COLUMN_NAME = 'user_id' 
                              AND REFERENCED_TABLE_NAME = 'users'")->fetchAll();
        
        foreach ($result as $row) {
            $db->exec("ALTER TABLE api4com_extensions DROP FOREIGN KEY " . $row['CONSTRAINT_NAME']);
            echo "✅ FK '{$row['CONSTRAINT_NAME']}' removida!\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao remover FK: " . $e->getMessage() . "\n";
    }
    
    // 4. Adicionar constraint única por extension_id + account (para evitar duplicatas de ramais)
    try {
        $db->exec("ALTER TABLE api4com_extensions ADD UNIQUE INDEX unique_extension_account (extension_id, api4com_account_id)");
        echo "✅ Constraint 'unique_extension_account' adicionada!\n";
    } catch (\Exception $e) {
        echo "⚠️ Constraint 'unique_extension_account' já existe ou erro: " . $e->getMessage() . "\n";
    }
    
    // 5. Recriar FK de user_id como opcional (ON DELETE SET NULL)
    try {
        $db->exec("ALTER TABLE api4com_extensions ADD CONSTRAINT fk_extensions_user 
                   FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "✅ FK 'fk_extensions_user' adicionada com ON DELETE SET NULL!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao criar FK: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Migration 'fix_api4com_extensions_table' concluída!\n";
}

function down_fix_api4com_extensions_table() {
    echo "⚠️ Esta migration não tem rollback automático\n";
}

