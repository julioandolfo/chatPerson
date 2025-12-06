<?php
/**
 * Migration: Criar tabela agent_funnel_permissions (permissões de agentes por funil/estágio)
 */

function up_agent_funnel_permissions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS agent_funnel_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'ID do agente',
        funnel_id INT NULL COMMENT 'NULL = todos os funis',
        stage_id INT NULL COMMENT 'NULL = todos os estágios do funil',
        permission_type VARCHAR(50) DEFAULT 'view' COMMENT 'view, edit, move',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE,
        FOREIGN KEY (stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_funnel_id (funnel_id),
        INDEX idx_stage_id (stage_id),
        UNIQUE KEY unique_permission (user_id, funnel_id, stage_id, permission_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'agent_funnel_permissions' criada com sucesso!\n";
}

function down_agent_funnel_permissions_table() {
    $sql = "DROP TABLE IF EXISTS agent_funnel_permissions";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'agent_funnel_permissions' removida!\n";
}

