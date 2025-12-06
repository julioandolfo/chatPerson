<?php
/**
 * Migration: Criar tabela agent_departments (relação muitos-para-muitos)
 */

function up_agent_departments_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS agent_departments (
        user_id INT NOT NULL COMMENT 'ID do usuário/agente',
        department_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, department_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_department_id (department_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'agent_departments' criada com sucesso!\n";
}

function down_agent_departments_table() {
    $sql = "DROP TABLE IF EXISTS agent_departments";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'agent_departments' removida!\n";
}

