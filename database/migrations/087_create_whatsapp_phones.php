<?php
/**
 * Migration: Create whatsapp_phones table
 * 
 * Armazena números WhatsApp conectados via Cloud API
 */

function up_create_whatsapp_phones() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_phones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Identificação WhatsApp
        phone_number_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'ID único do número WhatsApp (Meta)',
        phone_number VARCHAR(50) NOT NULL COMMENT 'Número de telefone (+5511999999999)',
        display_phone_number VARCHAR(50) NULL COMMENT 'Número formatado para exibição',
        
        -- WhatsApp Business
        waba_id VARCHAR(255) NOT NULL COMMENT 'WhatsApp Business Account ID',
        verified_name VARCHAR(255) NULL COMMENT 'Nome verificado do negócio',
        
        -- Status
        quality_rating ENUM('GREEN', 'YELLOW', 'RED', 'UNKNOWN') DEFAULT 'UNKNOWN' COMMENT 'Qualidade do número',
        account_mode ENUM('SANDBOX', 'LIVE') DEFAULT 'SANDBOX' COMMENT 'Modo da conta',
        
        -- Limites de mensagens
        messaging_limit_tier VARCHAR(50) NULL COMMENT 'Tier de limite (TIER_1K, TIER_10K, etc)',
        
        -- Relacionamentos
        integration_account_id INT NULL COMMENT 'Conta de integração vinculada',
        meta_oauth_token_id INT NULL COMMENT 'Token OAuth usado',
        
        -- Status de conexão
        is_active BOOLEAN DEFAULT TRUE,
        is_connected BOOLEAN DEFAULT TRUE COMMENT 'Ainda conectado?',
        last_message_at TIMESTAMP NULL COMMENT 'Última mensagem enviada/recebida',
        
        -- Configurações
        webhook_url TEXT NULL COMMENT 'URL do webhook configurado',
        webhook_verified BOOLEAN DEFAULT FALSE COMMENT 'Webhook verificado?',
        
        -- Templates
        templates_count INT DEFAULT 0 COMMENT 'Quantidade de templates aprovados',
        last_template_sync_at TIMESTAMP NULL COMMENT 'Última sincronização de templates',
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        disconnected_at TIMESTAMP NULL COMMENT 'Quando foi desconectado',
        
        -- Índices
        INDEX idx_phone_number_id (phone_number_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_waba_id (waba_id),
        INDEX idx_integration_account (integration_account_id),
        INDEX idx_meta_oauth_token (meta_oauth_token_id),
        INDEX idx_is_active (is_active),
        INDEX idx_is_connected (is_connected),
        INDEX idx_quality_rating (quality_rating),
        
        -- Foreign Keys
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL,
        FOREIGN KEY (meta_oauth_token_id) REFERENCES meta_oauth_tokens(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Números WhatsApp conectados via Cloud API';";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'whatsapp_phones' criada com sucesso!\n";
}

function down_create_whatsapp_phones() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS whatsapp_phones";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'whatsapp_phones' removida com sucesso!\n";
}

