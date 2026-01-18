<?php
/**
 * Migration: Adicionar campos para A/B Testing em campanhas
 */

function up_116_add_ab_testing_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Adicionando campos para A/B Testing...\n";
    
    $sql = "ALTER TABLE campaigns 
            ADD COLUMN IF NOT EXISTS is_ab_test BOOLEAN DEFAULT FALSE AFTER priority,
            ADD COLUMN IF NOT EXISTS ab_test_config JSON AFTER is_ab_test,
            ADD COLUMN IF NOT EXISTS winning_variant VARCHAR(10) AFTER ab_test_config";
    
    $db->exec($sql);
    
    echo "✅ Campos de A/B Testing adicionados\n";
    
    // Criar tabela de variantes
    echo "Criando tabela campaign_variants...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS campaign_variants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        variant_name VARCHAR(10) NOT NULL,
        message_content TEXT NOT NULL,
        percentage INT DEFAULT 50,
        total_sent INT DEFAULT 0,
        total_delivered INT DEFAULT 0,
        total_read INT DEFAULT 0,
        total_replied INT DEFAULT 0,
        delivery_rate DECIMAL(5,2) DEFAULT 0,
        read_rate DECIMAL(5,2) DEFAULT 0,
        reply_rate DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        UNIQUE KEY unique_campaign_variant (campaign_id, variant_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela campaign_variants criada\n";
    
    // Adicionar campo variant na tabela campaign_messages
    echo "Adicionando campo variant em campaign_messages...\n";
    
    $sql = "ALTER TABLE campaign_messages 
            ADD COLUMN IF NOT EXISTS variant VARCHAR(10) DEFAULT 'A' AFTER content";
    
    $db->exec($sql);
    
    echo "✅ Campo variant adicionado\n";
}

function down_116_add_ab_testing_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Removendo campos de A/B Testing...\n";
    
    $db->exec("DROP TABLE IF EXISTS campaign_variants");
    
    $db->exec("ALTER TABLE campaigns 
               DROP COLUMN IF EXISTS is_ab_test,
               DROP COLUMN IF EXISTS ab_test_config,
               DROP COLUMN IF EXISTS winning_variant");
    
    $db->exec("ALTER TABLE campaign_messages DROP COLUMN IF EXISTS variant");
    
    echo "✅ Campos de A/B Testing removidos\n";
}
