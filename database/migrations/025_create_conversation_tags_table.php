<?php
/**
 * Migration: Criar tabela conversation_tags (relação muitos-para-muitos)
 */

function up_conversation_tags_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_tags (
        conversation_id INT NOT NULL,
        tag_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (conversation_id, tag_id),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_tag_id (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_tags' criada com sucesso!\n";
}

function down_conversation_tags_table() {
    $sql = "DROP TABLE IF EXISTS conversation_tags";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'conversation_tags' removida!\n";
}

