<?php
/**
 * Migration: Criar tabela media_queue
 * Fila de download/upload de mídia com rate limiting
 */

function up_create_media_queue_table() {
    $db = \App\Helpers\Database::getInstance();
    
    $tables = $db->query("SHOW TABLES LIKE 'media_queue'")->fetchAll();
    if (!empty($tables)) {
        echo "⏭️ Tabela 'media_queue' já existe.\n";
        return;
    }
    
    $db->exec("
        CREATE TABLE media_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NULL,
            conversation_id INT NULL,
            account_id INT NOT NULL,
            external_message_id VARCHAR(255) NOT NULL,
            direction ENUM('download', 'upload') DEFAULT 'download',
            media_type VARCHAR(50) NULL COMMENT 'document, image, video, audio, etc',
            status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'queued',
            priority TINYINT DEFAULT 5 COMMENT '1=highest, 10=lowest',
            payload JSON NOT NULL COMMENT 'Dados necessários para processar (attachment info, account info, etc)',
            result JSON NULL COMMENT 'Resultado do processamento (path, url, size, etc)',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 10,
            error_message TEXT NULL,
            next_attempt_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            processed_at DATETIME NULL,
            INDEX idx_status (status),
            INDEX idx_next_attempt (status, next_attempt_at),
            INDEX idx_external_message (external_message_id),
            INDEX idx_message (message_id),
            INDEX idx_conversation (conversation_id),
            INDEX idx_account_status (account_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ Tabela 'media_queue' criada com sucesso!\n";
}

function down_create_media_queue_table() {
    $db = \App\Helpers\Database::getInstance();
    $db->exec("DROP TABLE IF EXISTS media_queue");
    echo "✅ Tabela 'media_queue' removida!\n";
}
