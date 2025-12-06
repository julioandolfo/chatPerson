<?php
/**
 * Migration: Criar tabela tags
 */

function up_tags_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        color VARCHAR(7) DEFAULT '#009EF7' COMMENT 'Cor em hexadecimal',
        description TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'tags' criada com sucesso!\n";
}

function down_tags_table() {
    $sql = "DROP TABLE IF EXISTS tags";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'tags' removida!\n";
}

