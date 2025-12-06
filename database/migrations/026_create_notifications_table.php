<?php
/**
 * Migration: Criar tabela notifications
 */

function up_notifications_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'Usuário que receberá a notificação',
        type VARCHAR(50) NOT NULL COMMENT 'Tipo: message, conversation, assignment, etc',
        title VARCHAR(255) NOT NULL COMMENT 'Título da notificação',
        message TEXT NOT NULL COMMENT 'Mensagem da notificação',
        link VARCHAR(500) NULL COMMENT 'Link relacionado (ex: /conversations/123)',
        data JSON NULL COMMENT 'Dados adicionais em JSON',
        is_read BOOLEAN DEFAULT FALSE COMMENT 'Se foi lida',
        read_at TIMESTAMP NULL COMMENT 'Data/hora que foi lida',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_type (type),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'notifications' criada com sucesso!\n";
}

function down_notifications_table() {
    $sql = "DROP TABLE IF EXISTS notifications";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'notifications' removida!\n";
}

