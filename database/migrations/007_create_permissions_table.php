<?php
/**
 * Migration: Criar tabela permissions
 */

function up_permissions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        description TEXT NULL,
        module VARCHAR(100) NULL COMMENT 'Módulo do sistema (conversations, contacts, etc)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_module (module)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'permissions' criada com sucesso!\n";
}

function down_permissions_table() {
    $sql = "DROP TABLE IF EXISTS permissions";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'permissions' removida!\n";
}

