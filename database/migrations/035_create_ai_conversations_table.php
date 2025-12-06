<?php
/**
 * Migration: Criar tabela ai_conversations
 * Logs e histórico de conversas com agentes de IA
 */

function up_ai_conversations_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        ai_agent_id INT NOT NULL COMMENT 'ID do agente de IA',
        messages JSON NOT NULL COMMENT 'Mensagens trocadas com a IA',
        tools_used JSON NULL COMMENT 'Tools utilizadas durante a conversa',
        tokens_used INT DEFAULT 0 COMMENT 'Total de tokens utilizados',
        tokens_prompt INT DEFAULT 0 COMMENT 'Tokens do prompt',
        tokens_completion INT DEFAULT 0 COMMENT 'Tokens da completion',
        cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo em USD',
        status VARCHAR(50) DEFAULT 'active' COMMENT 'Status: active, completed, failed, escalated',
        escalated_to_user_id INT NULL COMMENT 'ID do usuário para quem foi escalado',
        metadata JSON NULL COMMENT 'Metadados adicionais',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_ai_agent_id (ai_agent_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
        FOREIGN KEY (escalated_to_user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_conversations' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_conversations' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_conversations' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_conversations' pode já existir\n";
        }
    }
}

function down_ai_conversations_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_conversations";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_conversations' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_conversations': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_conversations' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_conversations'\n";
        }
    }
}

