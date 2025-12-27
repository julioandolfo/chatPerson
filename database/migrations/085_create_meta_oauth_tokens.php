<?php
/**
 * Migration: Create meta_oauth_tokens table
 * 
 * Armazena tokens OAuth 2.0 da Meta (Instagram + WhatsApp)
 * Um token pode ser usado para ambas as APIs
 */

function up_create_meta_oauth_tokens() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS meta_oauth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Identificação
        meta_user_id VARCHAR(255) NOT NULL COMMENT 'ID do usuário Meta (Instagram User ID ou WhatsApp Business Account ID)',
        app_type ENUM('instagram', 'whatsapp', 'both') DEFAULT 'both' COMMENT 'Tipo de app conectado',
        
        -- OAuth
        access_token TEXT NOT NULL,
        token_type VARCHAR(50) DEFAULT 'bearer',
        expires_at TIMESTAMP NULL COMMENT 'Quando o token expira (geralmente 60 dias)',
        
        -- Refresh (se disponível)
        refresh_token TEXT NULL,
        
        -- Permissões
        scopes TEXT COMMENT 'Lista de permissões separadas por vírgula',
        
        -- Relacionamento
        integration_account_id INT NULL COMMENT 'Vinculado a uma conta de integração',
        
        -- Metadados
        meta_app_id VARCHAR(255) NULL COMMENT 'ID do app Meta usado',
        last_used_at TIMESTAMP NULL COMMENT 'Última vez que o token foi usado',
        
        -- Controle
        is_valid BOOLEAN DEFAULT TRUE COMMENT 'Token ainda válido?',
        revoked_at TIMESTAMP NULL COMMENT 'Quando foi revogado (se aplicável)',
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Índices
        INDEX idx_meta_user_id (meta_user_id),
        INDEX idx_app_type (app_type),
        INDEX idx_integration_account (integration_account_id),
        INDEX idx_expires_at (expires_at),
        INDEX idx_is_valid (is_valid),
        
        -- Foreign Keys
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens OAuth da Meta (Instagram + WhatsApp)';";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'meta_oauth_tokens' criada com sucesso!\n";
}

function down_create_meta_oauth_tokens() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS meta_oauth_tokens";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'meta_oauth_tokens' removida com sucesso!\n";
}

