<?php
/**
 * Migration: Adicionar suporte para Drip Campaigns (campanhas em sequência)
 */

function up_117_add_drip_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Adicionando campos para Drip Campaigns...\n";
    
    // Adicionar campos em campaigns
    $sql = "ALTER TABLE campaigns 
            ADD COLUMN IF NOT EXISTS is_drip_campaign BOOLEAN DEFAULT FALSE AFTER is_ab_test,
            ADD COLUMN IF NOT EXISTS drip_parent_id INT NULL AFTER is_drip_campaign,
            ADD COLUMN IF NOT EXISTS drip_step INT DEFAULT 1 AFTER drip_parent_id,
            ADD COLUMN IF NOT EXISTS drip_delay_days INT DEFAULT 0 AFTER drip_step,
            ADD COLUMN IF NOT EXISTS drip_trigger_type VARCHAR(50) DEFAULT 'time' AFTER drip_delay_days,
            ADD COLUMN IF NOT EXISTS drip_trigger_config JSON AFTER drip_trigger_type";
    
    $db->exec($sql);
    
    echo "✅ Campos de Drip Campaign adicionados\n";
    
    // Criar tabela de sequências de drip
    echo "Criando tabela drip_sequences...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS drip_sequences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(50) DEFAULT 'active',
        total_steps INT DEFAULT 0,
        total_contacts INT DEFAULT 0,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela drip_sequences criada\n";
    
    // Criar tabela de passos do drip
    echo "Criando tabela drip_steps...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS drip_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sequence_id INT NOT NULL,
        campaign_id INT,
        step_order INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        message_content TEXT NOT NULL,
        delay_days INT DEFAULT 0,
        delay_hours INT DEFAULT 0,
        trigger_type VARCHAR(50) DEFAULT 'time',
        trigger_config JSON,
        condition_type VARCHAR(50),
        condition_config JSON,
        status VARCHAR(50) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sequence_id) REFERENCES drip_sequences(id) ON DELETE CASCADE,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela drip_steps criada\n";
    
    // Criar tabela de progresso de contatos no drip
    echo "Criando tabela drip_contact_progress...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS drip_contact_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sequence_id INT NOT NULL,
        contact_id INT NOT NULL,
        current_step INT DEFAULT 1,
        status VARCHAR(50) DEFAULT 'active',
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_step_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        opted_out BOOLEAN DEFAULT FALSE,
        opted_out_at TIMESTAMP NULL,
        FOREIGN KEY (sequence_id) REFERENCES drip_sequences(id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        UNIQUE KEY unique_sequence_contact (sequence_id, contact_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela drip_contact_progress criada\n";
}

function down_117_add_drip_campaigns(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Removendo estrutura de Drip Campaigns...\n";
    
    $db->exec("DROP TABLE IF EXISTS drip_contact_progress");
    $db->exec("DROP TABLE IF EXISTS drip_steps");
    $db->exec("DROP TABLE IF EXISTS drip_sequences");
    
    $db->exec("ALTER TABLE campaigns 
               DROP COLUMN IF EXISTS is_drip_campaign,
               DROP COLUMN IF EXISTS drip_parent_id,
               DROP COLUMN IF EXISTS drip_step,
               DROP COLUMN IF EXISTS drip_delay_days,
               DROP COLUMN IF EXISTS drip_trigger_type,
               DROP COLUMN IF EXISTS drip_trigger_config");
    
    echo "✅ Estrutura de Drip Campaigns removida\n";
}
