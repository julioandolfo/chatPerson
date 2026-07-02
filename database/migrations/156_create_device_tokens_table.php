<?php
/**
 * Migration: Criar tabela device_tokens
 * Armazena tokens de push (Expo Push Tokens) dos dispositivos móveis (app Chat Privus)
 */

function up_device_tokens_table() {
    $sql = "CREATE TABLE IF NOT EXISTS device_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        platform ENUM('ios','android') NOT NULL,
        device_name VARCHAR(255) NULL,
        app_version VARCHAR(50) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP NULL,
        revoked_at TIMESTAMP NULL,
        UNIQUE KEY unique_token (token),
        INDEX idx_user_active (user_id, revoked_at),
        CONSTRAINT fk_device_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'device_tokens' criada com sucesso!\n";
}

function down_device_tokens_table() {
    $sql = "DROP TABLE IF EXISTS device_tokens";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'device_tokens' removida!\n";
}
