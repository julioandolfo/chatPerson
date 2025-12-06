<?php
/**
 * Migration: Criar tabela funnels
 */

function up_funnels_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS funnels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        is_default BOOLEAN DEFAULT FALSE,
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_is_default (is_default)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'funnels' criada com sucesso!\n";
}

function down_funnels_table() {
    $sql = "DROP TABLE IF EXISTS funnels";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'funnels' removida!\n";
}

