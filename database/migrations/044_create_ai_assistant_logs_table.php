<?php
/**
 * Migration: Criar tabela ai_assistant_logs
 * Logs de uso do Assistente IA
 */

function up_ai_assistant_logs_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_assistant_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'ID do usuário que usou',
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        feature_key VARCHAR(100) NOT NULL COMMENT 'Chave da funcionalidade usada',
        ai_agent_id INT NULL COMMENT 'ID do agente de IA usado',
        input_data JSON COMMENT 'Dados de entrada (prompt, opções, etc)',
        output_data JSON COMMENT 'Dados de saída (resposta, tokens, custo)',
        tokens_used INT DEFAULT 0 COMMENT 'Tokens utilizados',
        cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo em USD',
        execution_time_ms INT DEFAULT 0 COMMENT 'Tempo de execução em milissegundos',
        success BOOLEAN DEFAULT TRUE COMMENT 'Se a execução foi bem-sucedida',
        error_message TEXT COMMENT 'Mensagem de erro se houver',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_user_id (user_id),
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_feature_key (feature_key),
        INDEX idx_ai_agent_id (ai_agent_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_assistant_logs' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_assistant_logs' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_assistant_logs' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_assistant_logs' pode já existir\n";
        }
    }
}

function down_ai_assistant_logs_table() {
    $sql = "DROP TABLE IF EXISTS ai_assistant_logs";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'ai_assistant_logs' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_assistant_logs': " . $e->getMessage() . "\n";
    }
}

