<?php
/**
 * Migration: Adicionar department_id em conversations
 */

function up_add_department_id_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            ADD COLUMN IF NOT EXISTS department_id INT NULL AFTER agent_id,
            ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
            ADD INDEX idx_department_id (department_id)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campo 'department_id' adicionado à tabela 'conversations'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez
            try {
                $pdo->exec("ALTER TABLE conversations ADD COLUMN department_id INT NULL AFTER agent_id");
                $pdo->exec("ALTER TABLE conversations ADD FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL");
                $pdo->exec("ALTER TABLE conversations ADD INDEX idx_department_id (department_id)");
                echo "✅ Campo 'department_id' adicionado à tabela 'conversations'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campo pode já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campo 'department_id' adicionado à tabela 'conversations'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campo pode já existir\n";
        }
    }
}

function down_add_department_id_to_conversations() {
    $sql = "ALTER TABLE conversations DROP FOREIGN KEY conversations_ibfk_2, DROP COLUMN department_id";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Campo 'department_id' removido da tabela 'conversations'!\n";
}

