<?php
/**
 * Migration: Criar tabela de notificações de campanhas
 */

function up_118_create_campaign_notifications(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Criando tabela campaign_notifications...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS campaign_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        campaign_id INT,
        message TEXT NOT NULL,
        data JSON,
        read_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        INDEX idx_campaign_id (campaign_id),
        INDEX idx_created_at (created_at),
        INDEX idx_read_at (read_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela campaign_notifications criada com sucesso!\n";
}

function down_118_create_campaign_notifications(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Removendo tabela campaign_notifications...\n";
    
    $db->exec("DROP TABLE IF EXISTS campaign_notifications");
    
    echo "✅ Tabela campaign_notifications removida\n";
}
