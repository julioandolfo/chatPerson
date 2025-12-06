<?php
/**
 * Migration: Criar tabela conversations
 */

function up_conversations_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inbox_id INT NULL,
        contact_id INT NOT NULL,
        agent_id INT NULL,
        funnel_id INT NULL,
        funnel_stage_id INT NULL,
        channel VARCHAR(50) DEFAULT 'whatsapp' COMMENT 'whatsapp, email, chat, telegram',
        whatsapp_account_id INT NULL COMMENT 'ID da conta WhatsApp se canal for whatsapp',
        status VARCHAR(20) DEFAULT 'open',
        priority VARCHAR(20) DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL,
        moved_at TIMESTAMP NULL,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (whatsapp_account_id) REFERENCES whatsapp_accounts(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_agent_id (agent_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_channel (channel),
        INDEX idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversations' criada com sucesso!\n";
}

function down_conversations_table() {
    $sql = "DROP TABLE IF EXISTS conversations";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'conversations' removida!\n";
}

