<?php
/**
 * Migration: Criar tabela ai_kanban_agent_actions_log
 * Log detalhado de ações executadas pelos agentes Kanban
 */

function up_ai_kanban_agent_actions_log_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_kanban_agent_actions_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_kanban_agent_id INT NOT NULL COMMENT 'ID do agente Kanban',
        execution_id INT NOT NULL COMMENT 'ID da execução',
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        
        -- Análise
        analysis_summary TEXT NULL COMMENT 'Resumo da análise feita pela IA',
        analysis_score DECIMAL(5,2) NULL COMMENT 'Score de confiança da análise (0-100)',
        
        -- Condições Avaliadas
        conditions_met BOOLEAN DEFAULT FALSE COMMENT 'Se condições foram atendidas',
        conditions_details JSON NULL COMMENT 'Detalhes de quais condições foram atendidas',
        
        -- Ações Executadas
        actions_executed JSON NOT NULL COMMENT 'Array de ações executadas com resultados',
        
        -- Resultado
        success BOOLEAN DEFAULT FALSE COMMENT 'Se execução foi bem-sucedida',
        error_message TEXT NULL COMMENT 'Mensagem de erro (se houver)',
        
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora da execução',
        
        FOREIGN KEY (ai_kanban_agent_id) REFERENCES ai_kanban_agents(id) ON DELETE CASCADE,
        FOREIGN KEY (execution_id) REFERENCES ai_kanban_agent_executions(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        INDEX idx_action_log_agent (ai_kanban_agent_id),
        INDEX idx_action_log_execution (execution_id),
        INDEX idx_action_log_conversation (conversation_id),
        INDEX idx_action_log_executed (executed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_actions_log' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_kanban_agent_actions_log' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_actions_log' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_kanban_agent_actions_log' pode já existir\n";
        }
    }
}

function down_ai_kanban_agent_actions_log_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_kanban_agent_actions_log";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_actions_log' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agent_actions_log': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_actions_log' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agent_actions_log'\n";
        }
    }
}

