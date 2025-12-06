<?php
/**
 * Migration: Criar tabela automations
 */

function up_automations_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS automations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        trigger_type VARCHAR(50) NOT NULL COMMENT 'new_conversation, message_received, time_based, etc',
        trigger_config JSON NULL COMMENT 'Configuração do trigger (canal, número, etc)',
        funnel_id INT NULL COMMENT 'Funil específico (NULL = todos os funis)',
        stage_id INT NULL COMMENT 'Estágio específico (NULL = todos os estágios)',
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive',
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE,
        FOREIGN KEY (stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
        INDEX idx_status (status),
        INDEX idx_trigger_type (trigger_type),
        INDEX idx_funnel_id (funnel_id),
        INDEX idx_stage_id (stage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'automations' criada com sucesso!\n";
}

function down_automations_table() {
    $sql = "DROP TABLE IF EXISTS automations";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'automations' removida!\n";
}

