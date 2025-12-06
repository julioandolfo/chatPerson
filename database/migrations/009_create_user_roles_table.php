<?php
/**
 * Migration: Criar tabela user_roles (relação muitos-para-muitos)
 */

function up_user_roles_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS user_roles (
        user_id INT NOT NULL,
        role_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, role_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_role_id (role_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'user_roles' criada com sucesso!\n";
}

function down_user_roles_table() {
    $sql = "DROP TABLE IF EXISTS user_roles";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'user_roles' removida!\n";
}

