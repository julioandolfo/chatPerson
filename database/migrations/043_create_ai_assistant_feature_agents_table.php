<?php
/**
 * Migration: Criar tabela ai_assistant_feature_agents
 * Regras de seleção automática de agentes por funcionalidade
 */

function up_ai_assistant_feature_agents_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_assistant_feature_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_key VARCHAR(100) NOT NULL COMMENT 'Chave da funcionalidade',
        ai_agent_id INT NOT NULL COMMENT 'ID do agente de IA',
        priority INT DEFAULT 0 COMMENT 'Prioridade para seleção (maior = mais prioritário)',
        conditions JSON COMMENT 'Condições para usar este agente (canal, tags, sentimento, etc)',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Regra ativa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_feature (feature_key),
        INDEX idx_agent (ai_agent_id),
        INDEX idx_priority (priority),
        INDEX idx_enabled (enabled),
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_assistant_feature_agents' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_assistant_feature_agents' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_assistant_feature_agents' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_assistant_feature_agents' pode já existir\n";
        }
    }
}

function down_ai_assistant_feature_agents_table() {
    $sql = "DROP TABLE IF EXISTS ai_assistant_feature_agents";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'ai_assistant_feature_agents' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_assistant_feature_agents': " . $e->getMessage() . "\n";
    }
}

