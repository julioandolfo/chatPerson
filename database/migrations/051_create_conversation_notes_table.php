<?php
/**
 * Migration: Criar tabela conversation_notes (notas internas de conversas)
 */

function up_conversation_notes_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        user_id INT NOT NULL COMMENT 'Quem criou a nota',
        content TEXT NOT NULL COMMENT 'Conteúdo da nota',
        is_private TINYINT(1) DEFAULT 0 COMMENT 'Se a nota é privada (só visível para quem criou)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at),
        INDEX idx_conversation_created (conversation_id, created_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'conversation_notes' criada com sucesso!\n";
        } catch (\PDOException $e) {
            // Tentar sem IF NOT EXISTS (MySQL antigo)
            try {
                $sql2 = "CREATE TABLE conversation_notes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id INT NOT NULL,
                    user_id INT NOT NULL,
                    content TEXT NOT NULL,
                    is_private TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_conversation_id (conversation_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_created_at (created_at),
                    INDEX idx_conversation_created (conversation_id, created_at),
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql2);
                echo "✅ Tabela 'conversation_notes' criada com sucesso!\n";
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao criar tabela: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'conversation_notes' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_conversation_notes_table() {
    $sql = "DROP TABLE IF EXISTS conversation_notes";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'conversation_notes' removida!\n";
}

