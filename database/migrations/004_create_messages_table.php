<?php
/**
 * Migration: Criar tabela messages
 */

function up_messages_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        sender_type VARCHAR(20) NOT NULL,
        sender_id INT NOT NULL,
        content TEXT NOT NULL,
        message_type VARCHAR(20) DEFAULT 'text',
        attachments JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'messages' criada com sucesso!\n";
}

function down_messages_table() {
    $sql = "DROP TABLE IF EXISTS messages";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'messages' removida!\n";
}

