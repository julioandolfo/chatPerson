<?php
/**
 * Migration: Criar tabela ai_kanban_agent_executions
 * Histórico de execuções dos agentes Kanban
 */

function up_ai_kanban_agent_executions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_kanban_agent_executions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_kanban_agent_id INT NOT NULL COMMENT 'ID do agente Kanban',
        execution_type VARCHAR(50) NOT NULL DEFAULT 'scheduled' COMMENT 'scheduled, manual, triggered',
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Início da execução',
        completed_at TIMESTAMP NULL COMMENT 'Fim da execução',
        status VARCHAR(50) DEFAULT 'running' COMMENT 'running, completed, failed, cancelled',
        
        -- Estatísticas
        conversations_analyzed INT DEFAULT 0 COMMENT 'Conversas analisadas',
        conversations_acted_upon INT DEFAULT 0 COMMENT 'Conversas que tiveram ações executadas',
        actions_executed INT DEFAULT 0 COMMENT 'Total de ações executadas',
        errors_count INT DEFAULT 0 COMMENT 'Total de erros',
        
        -- Resultados
        results JSON NULL COMMENT 'Detalhes da execução',
        error_message TEXT NULL COMMENT 'Mensagem de erro (se houver)',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (ai_kanban_agent_id) REFERENCES ai_kanban_agents(id) ON DELETE CASCADE,
        INDEX idx_execution_agent (ai_kanban_agent_id),
        INDEX idx_execution_status (status),
        INDEX idx_execution_started (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_executions' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_kanban_agent_executions' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_executions' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_kanban_agent_executions' pode já existir\n";
        }
    }
}

function down_ai_kanban_agent_executions_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_kanban_agent_executions";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_executions' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agent_executions': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_kanban_agent_executions' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_kanban_agent_executions'\n";
        }
    }
}

