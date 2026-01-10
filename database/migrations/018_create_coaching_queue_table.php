<?php

/**
 * Migration: Criar tabela de fila de coaching
 */

function up_create_coaching_queue_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS coaching_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        attempts INT DEFAULT 0,
        last_error TEXT DEFAULT NULL,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_status (status),
        INDEX idx_added_at (added_at),
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_queue' criada com sucesso!\n";
}

function down_create_coaching_queue_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS coaching_queue";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_queue' removida com sucesso!\n";
}
