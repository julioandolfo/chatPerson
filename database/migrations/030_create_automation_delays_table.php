<?php
/**
 * Migration: Criar tabela automation_delays
 * Armazena delays agendados de automações para execução posterior
 */

function up_create_automation_delays_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS automation_delays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        automation_id INT NOT NULL COMMENT 'ID da automação',
        execution_id INT NULL COMMENT 'ID da execução da automação',
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        node_id VARCHAR(50) NOT NULL COMMENT 'ID do nó de delay',
        delay_seconds INT NOT NULL COMMENT 'Delay em segundos',
        scheduled_at DATETIME NOT NULL COMMENT 'Data/hora agendada para execução',
        executed_at DATETIME NULL COMMENT 'Data/hora em que foi executado',
        status ENUM('pending', 'executing', 'completed', 'failed', 'cancelled') DEFAULT 'pending' COMMENT 'Status do delay',
        node_data JSON NULL COMMENT 'Dados do nó para continuar execução',
        next_nodes JSON NULL COMMENT 'IDs dos próximos nós a executar',
        error_message TEXT NULL COMMENT 'Mensagem de erro se falhar',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status_scheduled (status, scheduled_at),
        INDEX idx_conversation (conversation_id),
        INDEX idx_automation (automation_id),
        INDEX idx_execution (execution_id),
        FOREIGN KEY (automation_id) REFERENCES automations(id) ON DELETE CASCADE,
        FOREIGN KEY (execution_id) REFERENCES automation_executions(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delays agendados de automações'";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'automation_delays' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "❌ Erro ao criar tabela 'automation_delays': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'automation_delays' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao criar tabela 'automation_delays': " . $e->getMessage() . "\n";
        }
    }
}

function down_create_automation_delays_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS automation_delays";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'automation_delays' removida com sucesso!\n";
        } catch (\PDOException $e) {
            echo "❌ Erro ao remover tabela 'automation_delays': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'automation_delays' removida com sucesso!\n";
        } catch (\Exception $e) {
            echo "❌ Erro ao remover tabela 'automation_delays': " . $e->getMessage() . "\n";
        }
    }
}

