<?php
/**
 * Migration: Criar tabela whatsapp_templates para gerenciamento de templates
 * e adicionar campos CoEx na tabela whatsapp_phones
 */

function up_create_whatsapp_templates_table() {
    global $pdo;
    
    // Criar tabela de templates WhatsApp
    $sql = "CREATE TABLE IF NOT EXISTS whatsapp_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waba_id VARCHAR(50) NOT NULL COMMENT 'WhatsApp Business Account ID',
        whatsapp_phone_id INT NULL COMMENT 'FK para whatsapp_phones',
        template_id VARCHAR(100) NULL COMMENT 'ID do template na Meta',
        name VARCHAR(255) NOT NULL COMMENT 'Nome do template (slug)',
        display_name VARCHAR(255) NULL COMMENT 'Nome de exibição',
        language VARCHAR(20) NOT NULL DEFAULT 'pt_BR' COMMENT 'Código do idioma',
        category ENUM('MARKETING', 'UTILITY', 'AUTHENTICATION') NOT NULL DEFAULT 'UTILITY' COMMENT 'Categoria do template',
        status ENUM('PENDING', 'APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'DRAFT') NOT NULL DEFAULT 'DRAFT' COMMENT 'Status na Meta',
        quality_score VARCHAR(20) NULL COMMENT 'Qualidade: GREEN, YELLOW, RED',
        
        -- Componentes do template
        header_type ENUM('NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT') DEFAULT 'NONE',
        header_text TEXT NULL,
        header_media_url TEXT NULL,
        body_text TEXT NOT NULL COMMENT 'Corpo do template com {{1}}, {{2}} etc',
        footer_text VARCHAR(60) NULL,
        
        -- Botões (JSON array)
        buttons JSON NULL COMMENT 'Array de botões [{type, text, url/phone}]',
        
        -- Componentes completos (JSON raw da Meta)
        components JSON NULL COMMENT 'Componentes raw retornados pela Meta API',
        
        -- Estatísticas
        sent_count INT DEFAULT 0,
        delivered_count INT DEFAULT 0,
        read_count INT DEFAULT 0,
        failed_count INT DEFAULT 0,
        last_sent_at DATETIME NULL,
        
        -- Metadata
        rejection_reason TEXT NULL COMMENT 'Motivo da rejeição pela Meta',
        last_synced_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_waba_id (waba_id),
        INDEX idx_name_language (name, language),
        INDEX idx_status (status),
        INDEX idx_category (category),
        INDEX idx_whatsapp_phone (whatsapp_phone_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    // Adicionar campos CoEx na tabela whatsapp_phones
    $columns = [
        'coex_enabled' => "ALTER TABLE whatsapp_phones ADD COLUMN coex_enabled TINYINT(1) DEFAULT 0 COMMENT 'CoEx ativo neste número' AFTER is_connected",
        'coex_status' => "ALTER TABLE whatsapp_phones ADD COLUMN coex_status ENUM('inactive', 'onboarding', 'syncing', 'active', 'error') DEFAULT 'inactive' COMMENT 'Status do CoEx' AFTER coex_enabled",
        'coex_capabilities' => "ALTER TABLE whatsapp_phones ADD COLUMN coex_capabilities JSON NULL COMMENT 'Capacidades do CoEx (send_messages, etc.)' AFTER coex_status",
        'coex_activated_at' => "ALTER TABLE whatsapp_phones ADD COLUMN coex_activated_at DATETIME NULL COMMENT 'Quando CoEx foi ativado' AFTER coex_capabilities",
        'coex_history_synced' => "ALTER TABLE whatsapp_phones ADD COLUMN coex_history_synced TINYINT(1) DEFAULT 0 COMMENT 'Se o histórico foi sincronizado' AFTER coex_activated_at",
    ];
    
    foreach ($columns as $column => $sql) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM whatsapp_phones LIKE '{$column}'");
            if ($check->rowCount() === 0) {
                $pdo->exec($sql);
            }
        } catch (\Exception $e) {
            // Tabela pode não existir ainda
        }
    }
    
    echo "Migration 062: whatsapp_templates criada + campos CoEx adicionados\n";
}

function down_create_whatsapp_templates_table() {
    $pdo = \App\Helpers\Database::getInstance();
    $pdo->exec("DROP TABLE IF EXISTS whatsapp_templates");
    
    // Remover campos CoEx
    try {
        $pdo->exec("ALTER TABLE whatsapp_phones DROP COLUMN coex_enabled");
        $pdo->exec("ALTER TABLE whatsapp_phones DROP COLUMN coex_status");
        $pdo->exec("ALTER TABLE whatsapp_phones DROP COLUMN coex_capabilities");
        $pdo->exec("ALTER TABLE whatsapp_phones DROP COLUMN coex_activated_at");
        $pdo->exec("ALTER TABLE whatsapp_phones DROP COLUMN coex_history_synced");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    echo "Migration 062: whatsapp_templates removida + campos CoEx removidos\n";
}
