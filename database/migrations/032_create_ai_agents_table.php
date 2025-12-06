<?php
/**
 * Migration: Criar tabela ai_agents
 * Agentes de IA para atendimento automatizado
 */

function up_ai_agents_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do agente de IA',
        description TEXT NULL COMMENT 'Descrição do agente',
        agent_type VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Tipo: SDR, CS, CLOSER, FOLLOWUP, etc',
        prompt TEXT NOT NULL COMMENT 'Prompt do sistema para o agente',
        model VARCHAR(100) DEFAULT 'gpt-4' COMMENT 'Modelo OpenAI a usar (gpt-4, gpt-3.5-turbo, etc)',
        temperature DECIMAL(3,2) DEFAULT 0.7 COMMENT 'Temperature para geração (0.0 a 2.0)',
        max_tokens INT DEFAULT 2000 COMMENT 'Máximo de tokens na resposta',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Se o agente está ativo',
        max_conversations INT NULL COMMENT 'Limite máximo de conversas simultâneas',
        current_conversations INT DEFAULT 0 COMMENT 'Número atual de conversas',
        settings JSON NULL COMMENT 'Configurações adicionais do agente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_agent_type (agent_type),
        INDEX idx_enabled (enabled),
        INDEX idx_max_conversations (max_conversations)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_agents' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_agents' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_agents' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_agents' pode já existir\n";
        }
    }
}

function down_ai_agents_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_agents";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_agents' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_agents': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_agents' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_agents'\n";
        }
    }
}

