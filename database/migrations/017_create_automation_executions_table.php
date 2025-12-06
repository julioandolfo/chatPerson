<?php
/**
 * Migration: Criar tabela automation_executions (execuções das automações)
 */

function up_automation_executions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS automation_executions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL,
        conversation_id INT NULL COMMENT 'Conversa que disparou a automação',
        node_id INT NULL COMMENT 'Nó atual sendo executado',
        status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, running, completed, failed',
        execution_data JSON NULL COMMENT 'Dados da execução',
        error_message TEXT NULL,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
        INDEX idx_automation_id (automation_id),
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'automation_executions' criada com sucesso!\n";
}

function down_automation_executions_table() {
    $sql = "DROP TABLE IF EXISTS automation_executions";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'automation_executions' removida!\n";
}

