<?php
/**
 * Migration: Criar tabela saved_searches
 * Data: 2025-01-27
 */

function up_saved_searches_table() {
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS saved_searches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        query VARCHAR(500),
        filters JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'saved_searches' criada com sucesso!\n";
}

function down_saved_searches_table() {
    global $pdo;
    $sql = "DROP TABLE IF EXISTS saved_searches";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'saved_searches' removida com sucesso!\n";
}

