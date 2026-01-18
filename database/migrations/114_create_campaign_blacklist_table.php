<?php
/**
 * Migration: Criar tabela campaign_blacklist (Blacklist de Campanhas)
 * 
 * Lista de contatos que não devem receber campanhas
 * Pode ser por contato ou por telefone
 */

function up_create_campaign_blacklist_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS campaign_blacklist (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT COMMENT 'ID do contato (se existir)',
        phone VARCHAR(50) COMMENT 'Telefone (caso não tenha contato)',
        reason VARCHAR(255) COMMENT 'Motivo do bloqueio',
        blacklist_type VARCHAR(50) DEFAULT 'manual' COMMENT 'manual, auto_optout, auto_error, auto_inactive',
        
        -- AUDIT
        added_by INT COMMENT 'Quem adicionou',
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_contact_id (contact_id),
        INDEX idx_phone (phone),
        INDEX idx_blacklist_type (blacklist_type),
        
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'campaign_blacklist' criada com sucesso!\n";
}

function down_create_campaign_blacklist_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS campaign_blacklist";
    $db->exec($sql);
    echo "✅ Tabela 'campaign_blacklist' removida!\n";
}
