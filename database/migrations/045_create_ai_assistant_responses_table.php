<?php
/**
 * Migration: Criar tabela ai_assistant_responses
 * Armazena histórico de respostas geradas pelo Assistente IA
 */

function up_ai_assistant_responses() {
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS ai_assistant_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        conversation_id INT NOT NULL,
        feature_key VARCHAR(50) NOT NULL,
        ai_agent_id INT NULL,
        response_text TEXT NOT NULL,
        tone VARCHAR(50) NULL,
        tokens_used INT DEFAULT 0,
        cost DECIMAL(10, 6) DEFAULT 0.000000,
        is_favorite TINYINT(1) DEFAULT 0,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_conversation (user_id, conversation_id),
        INDEX idx_feature (feature_key),
        INDEX idx_favorite (user_id, is_favorite),
        INDEX idx_created (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'ai_assistant_responses' criada com sucesso!\n";
}

function down_ai_assistant_responses() {
    global $pdo;
    $sql = "DROP TABLE IF EXISTS ai_assistant_responses";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'ai_assistant_responses' removida com sucesso!\n";
}

