<?php
/**
 * Migration: Criar tabela roles
 */

function up_roles_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT NULL,
        level INT NOT NULL DEFAULT 0 COMMENT 'Nível hierárquico (0-7)',
        is_system BOOLEAN DEFAULT FALSE COMMENT 'Role do sistema não pode ser deletada',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_level (level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'roles' criada com sucesso!\n";
}

function down_roles_table() {
    $sql = "DROP TABLE IF EXISTS roles";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'roles' removida!\n";
}

