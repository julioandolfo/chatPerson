<?php
/**
 * Migration: Criar tabela ai_assistant_features
 * Funcionalidades do Assistente IA (Gerar Resposta, Resumir, etc)
 */

function up_ai_assistant_features_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_assistant_features (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Chave única da funcionalidade (ex: generate_response, summarize)',
        name VARCHAR(255) NOT NULL COMMENT 'Nome da funcionalidade',
        description TEXT COMMENT 'Descrição da funcionalidade',
        icon VARCHAR(50) DEFAULT 'ki-abstract-26' COMMENT 'Ícone Metronic',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Funcionalidade ativa globalmente',
        default_ai_agent_id INT NULL COMMENT 'ID do agente padrão para esta funcionalidade',
        auto_select_agent BOOLEAN DEFAULT TRUE COMMENT 'Selecionar agente automaticamente baseado em contexto',
        settings JSON COMMENT 'Configurações padrão da funcionalidade',
        order_index INT DEFAULT 0 COMMENT 'Ordem de exibição',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_enabled (enabled),
        INDEX idx_order (order_index),
        FOREIGN KEY (default_ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_assistant_features' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_assistant_features' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_assistant_features' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_assistant_features' pode já existir\n";
        }
    }
}

function down_ai_assistant_features_table() {
    $sql = "DROP TABLE IF EXISTS ai_assistant_features";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'ai_assistant_features' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_assistant_features': " . $e->getMessage() . "\n";
    }
}

