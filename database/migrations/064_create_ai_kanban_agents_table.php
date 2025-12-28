<?php
/**
 * Migration: Criar tabela ai_kanban_agents
 * Agentes de IA especializados para gestão de funis e etapas Kanban
 */

function up_ai_kanban_agents_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_kanban_agents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do agente Kanban',
        description TEXT NULL COMMENT 'Descrição do agente',
        agent_type VARCHAR(50) NOT NULL DEFAULT 'kanban_custom' COMMENT 'Tipo: kanban_followup, kanban_analyzer, kanban_manager, kanban_custom',
        prompt TEXT NOT NULL COMMENT 'Prompt específico para análise de conversas do Kanban',
        model VARCHAR(100) DEFAULT 'gpt-4' COMMENT 'Modelo OpenAI (gpt-4, gpt-3.5-turbo, etc)',
        temperature DECIMAL(3,2) DEFAULT 0.7 COMMENT 'Temperature (0.0 a 2.0)',
        max_tokens INT DEFAULT 2000 COMMENT 'Máximo de tokens na resposta',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Se o agente está ativo',
        
        -- Configuração de Funis e Etapas
        target_funnel_ids JSON NULL COMMENT 'IDs dos funis alvo (NULL = todos)',
        target_stage_ids JSON NULL COMMENT 'IDs das etapas alvo (NULL = todas)',
        
        -- Configuração de Execução
        execution_type VARCHAR(50) NOT NULL DEFAULT 'interval' COMMENT 'interval, schedule, manual',
        execution_interval_hours INT NULL COMMENT 'Intervalo em horas (ex: 48 = a cada 2 dias)',
        execution_schedule JSON NULL COMMENT 'Agendamento: {\"days\": [1,3,5], \"time\": \"09:00\"}',
        last_execution_at TIMESTAMP NULL COMMENT 'Última execução',
        next_execution_at TIMESTAMP NULL COMMENT 'Próxima execução agendada',
        
        -- Condições de Ativação
        conditions JSON NOT NULL COMMENT 'Array de condições para ativação',
        
        -- Ações a Executar
        actions JSON NOT NULL COMMENT 'Array de ações a executar',
        
        -- Configurações Extras
        settings JSON NULL COMMENT 'Configurações específicas do agente',
        max_conversations_per_execution INT DEFAULT 50 COMMENT 'Limite de conversas analisadas por execução',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_agent_type (agent_type),
        INDEX idx_enabled (enabled),
        INDEX idx_execution_type (execution_type),
        INDEX idx_next_execution (next_execution_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agents' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_kanban_agents' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agents' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_kanban_agents' pode já existir\n";
        }
    }
}

function down_ai_kanban_agents_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_kanban_agents";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agents' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agents': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agents' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agents'\n";
        }
    }
}

