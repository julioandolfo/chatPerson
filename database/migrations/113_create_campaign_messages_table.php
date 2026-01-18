<?php
/**
 * Migration: Criar tabela campaign_messages (Mensagens de Campanha)
 * 
 * Registra cada mensagem individual de uma campanha
 * Permite tracking completo: enviada, entregue, lida, respondida
 */

function up_create_campaign_messages_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS campaign_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL COMMENT 'ID da campanha',
        contact_id INT NOT NULL COMMENT 'ID do contato',
        conversation_id INT COMMENT 'ID da conversa criada',
        message_id INT COMMENT 'ID da mensagem enviada',
        integration_account_id INT COMMENT 'Conta WhatsApp usada para enviar',
        
        -- CONTEÚDO
        content TEXT COMMENT 'Conteúdo processado (com variáveis substituídas)',
        attachments JSON COMMENT 'Anexos da mensagem',
        
        -- STATUS
        status VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, scheduled, sending, sent, delivered, read, replied, failed, skipped',
        error_message TEXT COMMENT 'Mensagem de erro se falhou',
        skip_reason VARCHAR(255) COMMENT 'Motivo se foi pulado',
        
        -- TRACKING DETALHADO
        scheduled_at TIMESTAMP NULL COMMENT 'Quando foi agendado',
        sent_at TIMESTAMP NULL COMMENT 'Quando foi enviado',
        delivered_at TIMESTAMP NULL COMMENT 'Quando foi entregue',
        read_at TIMESTAMP NULL COMMENT 'Quando foi lido',
        replied_at TIMESTAMP NULL COMMENT 'Quando foi respondido',
        failed_at TIMESTAMP NULL COMMENT 'Quando falhou',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_campaign_id (campaign_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_status (status),
        INDEX idx_scheduled_at (scheduled_at),
        INDEX idx_campaign_contact (campaign_id, contact_id),
        INDEX idx_integration_account_id (integration_account_id),
        
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
        FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'campaign_messages' criada com sucesso!\n";
}

function down_create_campaign_messages_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS campaign_messages";
    $db->exec($sql);
    echo "✅ Tabela 'campaign_messages' removida!\n";
}
