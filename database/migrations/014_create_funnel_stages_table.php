<?php
/**
 * Migration: Criar tabela funnel_stages
 */

function up_funnel_stages_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS funnel_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        position INT NOT NULL DEFAULT 0 COMMENT 'Ordem no kanban',
        color VARCHAR(20) DEFAULT '#009ef7' COMMENT 'Cor do card no kanban',
        is_default BOOLEAN DEFAULT FALSE COMMENT 'Estágio padrão (ex: Novo)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE,
        INDEX idx_funnel_id (funnel_id),
        INDEX idx_position (position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'funnel_stages' criada com sucesso!\n";
}

function down_funnel_stages_table() {
    $sql = "DROP TABLE IF EXISTS funnel_stages";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'funnel_stages' removida!\n";
}

