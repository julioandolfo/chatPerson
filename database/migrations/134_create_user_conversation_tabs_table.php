<?php
/**
 * Migration: Criar tabela user_conversation_tabs
 * Permite que cada agente configure suas próprias abas na listagem de conversas,
 * vinculadas a tags existentes.
 */

function up_user_conversation_tabs_table() {
    $sql = "CREATE TABLE IF NOT EXISTS user_conversation_tabs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tag_id INT NOT NULL,
        position INT DEFAULT 0 COMMENT 'Ordem de exibição da aba',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_tag (user_id, tag_id),
        INDEX idx_user_position (user_id, position),
        CONSTRAINT fk_uct_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_uct_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'user_conversation_tabs' criada com sucesso!\n";
}

function down_user_conversation_tabs_table() {
    $sql = "DROP TABLE IF EXISTS user_conversation_tabs";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'user_conversation_tabs' removida!\n";
}
