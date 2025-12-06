<?php
/**
 * Migration: Criar tabela automation_nodes (nós do fluxo)
 */

function up_automation_nodes_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS automation_nodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL,
        node_type VARCHAR(50) NOT NULL COMMENT 'action, condition, trigger, etc',
        node_data JSON NOT NULL COMMENT 'Dados do nó (configuração, posição, conexões)',
        position_x INT DEFAULT 0 COMMENT 'Posição X no canvas',
        position_y INT DEFAULT 0 COMMENT 'Posição Y no canvas',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
        INDEX idx_automation_id (automation_id),
        INDEX idx_node_type (node_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'automation_nodes' criada com sucesso!\n";
}

function down_automation_nodes_table() {
    $sql = "DROP TABLE IF EXISTS automation_nodes";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'automation_nodes' removida!\n";
}

