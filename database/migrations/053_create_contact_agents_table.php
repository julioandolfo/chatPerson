<?php
/**
 * Migration: Criar tabela contact_agents (agentes atribuídos a contatos)
 */

function up_contact_agents_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS contact_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT NOT NULL COMMENT 'ID do contato',
        agent_id INT NOT NULL COMMENT 'ID do agente',
        is_primary TINYINT(1) DEFAULT 0 COMMENT 'Se é o agente principal',
        priority INT DEFAULT 0 COMMENT 'Prioridade (quanto maior, mais prioritário)',
        auto_assign_on_reopen TINYINT(1) DEFAULT 1 COMMENT 'Atribuir automaticamente quando conversa fechada for reaberta',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_contact_id (contact_id),
        INDEX idx_agent_id (agent_id),
        INDEX idx_is_primary (is_primary),
        INDEX idx_contact_primary (contact_id, is_primary),
        UNIQUE KEY unique_contact_agent (contact_id, agent_id),
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'contact_agents' criada com sucesso!\n";
        } catch (\PDOException $e) {
            // Tentar sem IF NOT EXISTS (MySQL antigo)
            try {
                $sql2 = "CREATE TABLE contact_agents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    contact_id INT NOT NULL,
                    agent_id INT NOT NULL,
                    is_primary TINYINT(1) DEFAULT 0,
                    priority INT DEFAULT 0,
                    auto_assign_on_reopen TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_contact_id (contact_id),
                    INDEX idx_agent_id (agent_id),
                    INDEX idx_is_primary (is_primary),
                    INDEX idx_contact_primary (contact_id, is_primary),
                    UNIQUE KEY unique_contact_agent (contact_id, agent_id),
                    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
                    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql2);
                echo "✅ Tabela 'contact_agents' criada com sucesso!\n";
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao criar tabela: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'contact_agents' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_contact_agents_table() {
    $sql = "DROP TABLE IF EXISTS contact_agents";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'contact_agents' removida!\n";
}

