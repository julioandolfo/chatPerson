<?php
/**
 * Migration: Criar tabela role_permissions (relação muitos-para-muitos)
 */

function up_role_permissions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        INDEX idx_role_id (role_id),
        INDEX idx_permission_id (permission_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'role_permissions' criada com sucesso!\n";
}

function down_role_permissions_table() {
    $sql = "DROP TABLE IF EXISTS role_permissions";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'role_permissions' removida!\n";
}

