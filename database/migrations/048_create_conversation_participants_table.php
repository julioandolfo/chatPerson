<?php
/**
 * Migration: Criar tabela conversation_participants (participantes de conversas)
 */

function up_conversation_participants_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        added_by INT NULL COMMENT 'Usuário que adicionou o participante',
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        removed_at TIMESTAMP NULL COMMENT 'Soft delete - quando foi removido',
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_removed_at (removed_at),
        INDEX idx_conversation_user (conversation_id, user_id),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_participants' criada com sucesso!\n";
}

function down_conversation_participants_table() {
    $sql = "DROP TABLE IF EXISTS conversation_participants";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'conversation_participants' removida!\n";
}

