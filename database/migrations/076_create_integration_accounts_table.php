<?php
/**
 * Migration: Criar tabela integration_accounts (contas de integração unificadas)
 * Suporta: Notificame, WhatsApp Official, e futuras integrações
 */

function up_create_integration_accounts_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS integration_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da conta',
        provider VARCHAR(50) NOT NULL COMMENT 'notificame, whatsapp_official, quepasa, evolution',
        channel VARCHAR(50) NOT NULL COMMENT 'whatsapp, instagram, facebook, telegram, mercadolivre, webchat, email, olx, linkedin, google_business, youtube, tiktok',
        api_token VARCHAR(500) NULL COMMENT 'Token da API',
        api_url VARCHAR(500) NULL DEFAULT 'https://app.notificame.com.br/api/v1/' COMMENT 'URL base da API',
        account_id VARCHAR(255) NULL COMMENT 'ID da conta na plataforma externa',
        phone_number VARCHAR(50) NULL COMMENT 'Número (para WhatsApp)',
        username VARCHAR(255) NULL COMMENT 'Username (para Instagram, Telegram, etc)',
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive, disconnected, error',
        config JSON NULL COMMENT 'Configurações específicas do canal',
        webhook_url VARCHAR(500) NULL COMMENT 'URL do webhook configurada',
        webhook_secret VARCHAR(255) NULL COMMENT 'Secret para validar webhooks',
        default_funnel_id INT NULL COMMENT 'Funil padrão',
        default_stage_id INT NULL COMMENT 'Etapa padrão',
        last_sync_at TIMESTAMP NULL COMMENT 'Última sincronização',
        error_message TEXT NULL COMMENT 'Última mensagem de erro',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_provider_channel (provider, channel),
        INDEX idx_status (status),
        INDEX idx_phone_number (phone_number),
        INDEX idx_channel (channel),
        FOREIGN KEY (default_funnel_id) REFERENCES funnels(id) ON DELETE SET NULL,
        FOREIGN KEY (default_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'integration_accounts' criada com sucesso!\n";
}

function down_create_integration_accounts_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE integration_accounts DROP FOREIGN KEY integration_accounts_ibfk_1");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE integration_accounts DROP FOREIGN KEY integration_accounts_ibfk_2");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    $sql = "DROP TABLE IF EXISTS integration_accounts";
    $db->exec($sql);
    echo "✅ Tabela 'integration_accounts' removida!\n";
}

