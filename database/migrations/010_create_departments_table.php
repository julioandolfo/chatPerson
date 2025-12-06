<?php
/**
 * Migration: Criar tabela departments
 */

function up_departments_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        parent_id INT NULL COMMENT 'ID do setor pai (hierarquia)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES departments(id) ON DELETE SET NULL,
        INDEX idx_parent_id (parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'departments' criada com sucesso!\n";
}

function down_departments_table() {
    $sql = "DROP TABLE IF EXISTS departments";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'departments' removida!\n";
}

