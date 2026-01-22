<?php
/**
 * Migration: Metas para múltiplos agentes
 */

function up_add_multi_agent_goals() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // 1. Adicionar novo tipo no ENUM
    $db->exec("
        ALTER TABLE goals 
        MODIFY COLUMN target_type ENUM('individual', 'team', 'department', 'global', 'multi_agent') 
        NOT NULL COMMENT 'A quem se aplica'
    ");
    
    // 2. Tabela de vinculação meta ↔ agentes
    $db->exec("
        CREATE TABLE IF NOT EXISTS goal_agent_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            goal_id INT NOT NULL,
            agent_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_goal_agent (goal_id, agent_id),
            INDEX idx_agent (agent_id),
            
            FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
            FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Metas multi-agente ativadas!\n";
}

function down_add_multi_agent_goals() {
    $db = \App\Helpers\Database::getInstance();
    
    $db->exec("DROP TABLE IF EXISTS goal_agent_targets");
    
    // Reverter ENUM para o padrão anterior
    $db->exec("
        ALTER TABLE goals 
        MODIFY COLUMN target_type ENUM('individual', 'team', 'department', 'global') 
        NOT NULL COMMENT 'A quem se aplica'
    ");
    
    echo "✅ Metas multi-agente removidas.\n";
}
