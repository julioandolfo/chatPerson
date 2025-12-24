<?php
/**
 * Migration: Criar tabela api4com_accounts (contas Api4Com)
 */

function up_api4com_accounts_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS api4com_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da conta',
        api_url VARCHAR(500) NOT NULL COMMENT 'URL base da API Api4Com',
        api_token VARCHAR(500) NOT NULL COMMENT 'Token de autenticação',
        domain VARCHAR(255) NULL COMMENT 'Domínio da conta Api4Com',
        enabled TINYINT(1) DEFAULT 1 COMMENT 'Se está habilitado',
        webhook_url VARCHAR(500) NULL COMMENT 'URL do webhook configurada',
        config JSON NULL COMMENT 'Configurações adicionais',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_enabled (enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'api4com_accounts' criada com sucesso!\n";
}

function down_api4com_accounts_table() {
    $sql = "DROP TABLE IF EXISTS api4com_accounts";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'api4com_accounts' removida!\n";
}

