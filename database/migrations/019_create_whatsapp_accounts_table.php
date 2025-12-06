<?php
/**
 * Migration: Criar tabela whatsapp_accounts (contas WhatsApp para automações)
 */

function up_whatsapp_accounts_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da conta',
        phone_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Número do WhatsApp',
        provider VARCHAR(50) DEFAULT 'evolution' COMMENT 'evolution, quepasa',
        api_url VARCHAR(500) NULL COMMENT 'URL da API',
        api_key VARCHAR(255) NULL COMMENT 'Chave da API',
        instance_id VARCHAR(255) NULL COMMENT 'ID da instância',
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive, disconnected',
        config JSON NULL COMMENT 'Configurações adicionais',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_phone_number (phone_number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'whatsapp_accounts' criada com sucesso!\n";
}

function down_whatsapp_accounts_table() {
    $sql = "DROP TABLE IF EXISTS whatsapp_accounts";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'whatsapp_accounts' removida!\n";
}

