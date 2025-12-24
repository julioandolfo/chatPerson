<?php
/**
 * Migration: Criar tabela api4com_extensions (ramais Api4Com)
 */

function up_api4com_extensions_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS api4com_extensions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'Usuário do sistema',
        api4com_account_id INT NOT NULL COMMENT 'Conta Api4Com',
        extension_id VARCHAR(255) NULL COMMENT 'ID do ramal na Api4Com',
        extension_number VARCHAR(50) NULL COMMENT 'Número do ramal',
        sip_username VARCHAR(255) NULL COMMENT 'Username SIP',
        sip_password VARCHAR(500) NULL COMMENT 'Senha SIP (criptografada)',
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive',
        metadata JSON NULL COMMENT 'Metadados adicionais',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (api4com_account_id) REFERENCES api4com_accounts(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_api4com_account_id (api4com_account_id),
        INDEX idx_status (status),
        UNIQUE KEY unique_user_account (user_id, api4com_account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'api4com_extensions' criada com sucesso!\n";
}

function down_api4com_extensions_table() {
    $sql = "DROP TABLE IF EXISTS api4com_extensions";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'api4com_extensions' removida!\n";
}

