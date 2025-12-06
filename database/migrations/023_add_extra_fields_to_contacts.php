<?php
/**
 * Migration: Adicionar campos extras aos contatos
 */

function up_add_extra_fields_to_contacts() {
    global $pdo;
    
    $sql = "ALTER TABLE contacts 
            ADD COLUMN IF NOT EXISTS last_name VARCHAR(255) NULL AFTER name,
            ADD COLUMN IF NOT EXISTS city VARCHAR(255) NULL AFTER phone,
            ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL AFTER city,
            ADD COLUMN IF NOT EXISTS bio TEXT NULL AFTER country,
            ADD COLUMN IF NOT EXISTS company VARCHAR(255) NULL AFTER bio,
            ADD COLUMN IF NOT EXISTS social_media JSON NULL AFTER company,
            ADD COLUMN IF NOT EXISTS whatsapp_id VARCHAR(255) NULL AFTER phone COMMENT 'ID completo do WhatsApp (ex: 554796544996@s.whatsapp.net)',
            ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP NULL AFTER updated_at,
            ADD INDEX idx_last_name (last_name),
            ADD INDEX idx_city (city),
            ADD INDEX idx_country (country),
            ADD INDEX idx_company (company),
            ADD INDEX idx_last_activity_at (last_activity_at)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos extras adicionados à tabela 'contacts'!\n";
        } catch (\PDOException $e) {
            // Tentar adicionar um por vez se der erro
            try {
                $pdo->exec("ALTER TABLE contacts ADD COLUMN last_name VARCHAR(255) NULL AFTER name");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN city VARCHAR(255) NULL AFTER phone");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN country VARCHAR(100) NULL AFTER city");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN bio TEXT NULL AFTER country");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN company VARCHAR(255) NULL AFTER bio");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN social_media JSON NULL AFTER company");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN whatsapp_id VARCHAR(255) NULL AFTER phone");
                $pdo->exec("ALTER TABLE contacts ADD COLUMN last_activity_at TIMESTAMP NULL AFTER updated_at");
                $pdo->exec("ALTER TABLE contacts ADD INDEX idx_last_name (last_name)");
                $pdo->exec("ALTER TABLE contacts ADD INDEX idx_city (city)");
                $pdo->exec("ALTER TABLE contacts ADD INDEX idx_country (country)");
                $pdo->exec("ALTER TABLE contacts ADD INDEX idx_company (company)");
                $pdo->exec("ALTER TABLE contacts ADD INDEX idx_last_activity_at (last_activity_at)");
                echo "✅ Campos extras adicionados à tabela 'contacts'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Alguns campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos extras adicionados à tabela 'contacts'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Alguns campos podem já existir\n";
        }
    }
}

function down_add_extra_fields_to_contacts() {
    $sql = "ALTER TABLE contacts 
            DROP COLUMN IF EXISTS last_name,
            DROP COLUMN IF EXISTS city,
            DROP COLUMN IF EXISTS country,
            DROP COLUMN IF EXISTS bio,
            DROP COLUMN IF EXISTS company,
            DROP COLUMN IF EXISTS social_media,
            DROP COLUMN IF EXISTS whatsapp_id,
            DROP COLUMN IF EXISTS last_activity_at";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Campos extras removidos da tabela 'contacts'!\n";
}

