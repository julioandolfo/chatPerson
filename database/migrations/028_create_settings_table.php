<?php
/**
 * Migration: Criar tabela settings
 */

function up_settings_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Chave da configuração',
        `value` TEXT NULL COMMENT 'Valor da configuração (pode ser JSON)',
        `type` VARCHAR(50) DEFAULT 'string' COMMENT 'Tipo: string, integer, boolean, json',
        `group` VARCHAR(100) DEFAULT 'general' COMMENT 'Grupo: general, email, whatsapp, security, etc',
        `label` VARCHAR(255) NULL COMMENT 'Label para exibição',
        `description` TEXT NULL COMMENT 'Descrição da configuração',
        `is_public` BOOLEAN DEFAULT FALSE COMMENT 'Se pode ser acessada sem autenticação',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_group (`group`),
        INDEX idx_key (`key`),
        INDEX idx_is_public (`is_public`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'settings' criada com sucesso!\n";
}

function down_settings_table() {
    $sql = "DROP TABLE IF EXISTS settings";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'settings' removida!\n";
}

