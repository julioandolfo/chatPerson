<?php
/**
 * Migration: Create instagram_accounts table
 * 
 * Armazena informações de contas Instagram conectadas via Graph API
 */

function up_create_instagram_accounts() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS instagram_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Identificação Instagram
        instagram_user_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'ID único do Instagram',
        username VARCHAR(255) NULL COMMENT '@username',
        name VARCHAR(255) NULL COMMENT 'Nome completo',
        
        -- Perfil
        profile_picture_url TEXT NULL COMMENT 'URL da foto de perfil',
        biography TEXT NULL COMMENT 'Bio do perfil',
        website VARCHAR(500) NULL COMMENT 'Site do perfil',
        
        -- Estatísticas
        followers_count INT DEFAULT 0,
        follows_count INT DEFAULT 0,
        media_count INT DEFAULT 0,
        
        -- Tipo de conta
        account_type ENUM('BUSINESS', 'CREATOR', 'PERSONAL') DEFAULT 'BUSINESS' COMMENT 'Tipo de conta Instagram',
        
        -- Relacionamentos
        integration_account_id INT NULL COMMENT 'Conta de integração vinculada',
        meta_oauth_token_id INT NULL COMMENT 'Token OAuth usado',
        facebook_page_id VARCHAR(255) NULL COMMENT 'Página do Facebook vinculada',
        
        -- Status
        is_active BOOLEAN DEFAULT TRUE,
        is_connected BOOLEAN DEFAULT TRUE COMMENT 'Ainda conectada?',
        last_synced_at TIMESTAMP NULL COMMENT 'Última sincronização de dados',
        
        -- Configurações
        auto_reply BOOLEAN DEFAULT FALSE COMMENT 'Resposta automática habilitada?',
        welcome_message TEXT NULL COMMENT 'Mensagem de boas-vindas',
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        disconnected_at TIMESTAMP NULL COMMENT 'Quando foi desconectada',
        
        -- Índices
        INDEX idx_instagram_user_id (instagram_user_id),
        INDEX idx_username (username),
        INDEX idx_integration_account (integration_account_id),
        INDEX idx_meta_oauth_token (meta_oauth_token_id),
        INDEX idx_is_active (is_active),
        INDEX idx_is_connected (is_connected),
        
        -- Foreign Keys
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL,
        FOREIGN KEY (meta_oauth_token_id) REFERENCES meta_oauth_tokens(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contas Instagram conectadas via Graph API';";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'instagram_accounts' criada com sucesso!\n";
}

function down_create_instagram_accounts() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS instagram_accounts";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'instagram_accounts' removida com sucesso!\n";
}

