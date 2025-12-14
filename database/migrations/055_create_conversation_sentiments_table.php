<?php
/**
 * Migration: Criar tabela conversation_sentiments (análise de sentimento)
 */

function up_conversation_sentiments_table() {
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS conversation_sentiments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        message_id INT NULL COMMENT 'ID da mensagem analisada (NULL = análise geral da conversa)',
        sentiment_score DECIMAL(3,2) NOT NULL COMMENT 'Score -1.0 (negativo) a 1.0 (positivo)',
        sentiment_label VARCHAR(20) NOT NULL COMMENT 'positive, neutral, negative',
        emotions JSON NULL COMMENT 'Emoções detectadas: {frustration: 0.8, satisfaction: 0.2, ...}',
        urgency_level VARCHAR(20) NULL COMMENT 'low, medium, high, critical',
        confidence DECIMAL(3,2) DEFAULT 0.0 COMMENT 'Confiança da análise (0.0 a 1.0)',
        analysis_text TEXT NULL COMMENT 'Texto explicativo da análise',
        messages_analyzed INT DEFAULT 0 COMMENT 'Quantidade de mensagens analisadas',
        tokens_used INT DEFAULT 0 COMMENT 'Tokens OpenAI utilizados',
        cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo em USD',
        model_used VARCHAR(50) NULL COMMENT 'Modelo OpenAI usado (gpt-3.5-turbo, gpt-4, etc)',
        analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi analisado',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_message_id (message_id),
        INDEX idx_sentiment_label (sentiment_label),
        INDEX idx_analyzed_at (analyzed_at),
        INDEX idx_conversation_analyzed (conversation_id, analyzed_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'conversation_sentiments' criada com sucesso!\n";
        } catch (\PDOException $e) {
            try {
                $sql2 = "CREATE TABLE conversation_sentiments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id INT NOT NULL,
                    message_id INT NULL,
                    sentiment_score DECIMAL(3,2) NOT NULL,
                    sentiment_label VARCHAR(20) NOT NULL,
                    emotions JSON NULL,
                    urgency_level VARCHAR(20) NULL,
                    confidence DECIMAL(3,2) DEFAULT 0.0,
                    analysis_text TEXT NULL,
                    messages_analyzed INT DEFAULT 0,
                    tokens_used INT DEFAULT 0,
                    cost DECIMAL(10,6) DEFAULT 0,
                    model_used VARCHAR(50) NULL,
                    analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_conversation_id (conversation_id),
                    INDEX idx_message_id (message_id),
                    INDEX idx_sentiment_label (sentiment_label),
                    INDEX idx_analyzed_at (analyzed_at),
                    INDEX idx_conversation_analyzed (conversation_id, analyzed_at),
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql2);
                echo "✅ Tabela 'conversation_sentiments' criada com sucesso!\n";
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao criar tabela: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'conversation_sentiments' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️ Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_conversation_sentiments_table() {
    global $pdo;
    $sql = "DROP TABLE IF EXISTS conversation_sentiments";
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_sentiments' removida!\n";
}

