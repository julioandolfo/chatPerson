<?php
/**
 * Migration: Criar tabela campaign_rotation_log (Log de Rotação de Contas)
 * 
 * Registra qual conta foi usada para cada envio
 * Útil para monitorar distribuição e performance de cada conta
 */

function up_create_campaign_rotation_log_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS campaign_rotation_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL COMMENT 'ID da campanha',
        integration_account_id INT NOT NULL COMMENT 'Conta WhatsApp usada',
        campaign_message_id INT COMMENT 'ID da mensagem da campanha',
        
        -- ESTATÍSTICAS DESTA CONTA NESTA CAMPANHA
        messages_sent INT DEFAULT 0 COMMENT 'Mensagens enviadas por esta conta',
        messages_delivered INT DEFAULT 0 COMMENT 'Mensagens entregues',
        messages_failed INT DEFAULT 0 COMMENT 'Mensagens falhadas',
        
        -- TRACKING
        last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Última vez que foi usada',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_campaign_id (campaign_id),
        INDEX idx_integration_account_id (integration_account_id),
        INDEX idx_campaign_account (campaign_id, integration_account_id),
        
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (campaign_message_id) REFERENCES campaign_messages(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'campaign_rotation_log' criada com sucesso!\n";
}

function down_create_campaign_rotation_log_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS campaign_rotation_log";
    $db->exec($sql);
    echo "✅ Tabela 'campaign_rotation_log' removida!\n";
}
