<?php
/**
 * Migration: Garantir tabelas de Drip Campaigns
 */

function up_119_create_drip_sequences_tables_fix(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Garantindo tabelas de Drip Campaigns...\n";
    
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
    echo "✅ Tabela drip_sequences garantida\n";
    
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
    echo "✅ Tabela drip_steps garantida\n";
    
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
    echo "✅ Tabela drip_contact_progress garantida\n";
}

function down_119_create_drip_sequences_tables_fix(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    $db->exec("DROP TABLE IF EXISTS drip_contact_progress");
    $db->exec("DROP TABLE IF EXISTS drip_steps");
    $db->exec("DROP TABLE IF EXISTS drip_sequences");
    
    echo "✅ Tabelas de Drip Campaigns removidas (fix)\n";
}
