<?php
/**
 * Migration: Add Meta (Instagram + WhatsApp) fields to contacts
 * 
 * Adiciona campos específicos do Instagram Graph API e WhatsApp Cloud API
 */

function up_add_meta_fields_to_contacts() {
    global $pdo;
    
    $sql = "ALTER TABLE contacts 
        ADD COLUMN instagram_user_id VARCHAR(255) NULL AFTER instagram_username COMMENT 'Instagram User ID (Graph API)',
        ADD COLUMN whatsapp_wa_id VARCHAR(255) NULL AFTER whatsapp_id COMMENT 'WhatsApp ID (Cloud API)',
        ADD COLUMN meta_synced_at TIMESTAMP NULL COMMENT 'Última sincronização com Meta APIs',
        ADD INDEX idx_instagram_user_id (instagram_user_id),
        ADD INDEX idx_whatsapp_wa_id (whatsapp_wa_id)";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campos Meta adicionados à tabela 'contacts' com sucesso!\n";
}

function down_add_meta_fields_to_contacts() {
    global $pdo;
    
    $sql = "ALTER TABLE contacts 
        DROP INDEX IF EXISTS idx_instagram_user_id,
        DROP INDEX IF EXISTS idx_whatsapp_wa_id,
        DROP COLUMN IF EXISTS instagram_user_id,
        DROP COLUMN IF EXISTS whatsapp_wa_id,
        DROP COLUMN IF EXISTS meta_synced_at";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campos Meta removidos da tabela 'contacts' com sucesso!\n";
}

