<?php
/**
 * Migration: Criar tabela ai_agent_tools
 * Relacionamento entre agentes de IA e tools
 */

function up_ai_agent_tools_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_agent_tools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ai_agent_id INT NOT NULL COMMENT 'ID do agente de IA',
        ai_tool_id INT NOT NULL COMMENT 'ID da tool',
        config JSON NULL COMMENT 'Configuração específica da tool para este agente',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Se a tool está habilitada para este agente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_agent_tool (ai_agent_id, ai_tool_id),
        INDEX idx_ai_agent_id (ai_agent_id),
        INDEX idx_ai_tool_id (ai_tool_id),
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
        FOREIGN KEY (ai_tool_id) REFERENCES ai_tools(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_agent_tools' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_agent_tools' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_agent_tools' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_agent_tools' pode já existir\n";
        }
    }
}

function down_ai_agent_tools_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_agent_tools";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_agent_tools' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_agent_tools': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_agent_tools' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_agent_tools'\n";
        }
    }
}

