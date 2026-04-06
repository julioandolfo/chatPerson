<?php

function up_create_vendor_broadcasts_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS vendor_broadcasts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        integration_account_id INT NOT NULL,
        template_name VARCHAR(255) NOT NULL,
        template_language VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
        status ENUM('pending','sending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
        total_contacts INT NOT NULL DEFAULT 0,
        total_sent INT NOT NULL DEFAULT 0,
        total_delivered INT NOT NULL DEFAULT 0,
        total_failed INT NOT NULL DEFAULT 0,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        INDEX idx_agent_date (agent_id, created_at),
        INDEX idx_status (status),
        CONSTRAINT fk_vb_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vb_integration FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS vendor_broadcast_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        broadcast_id INT NOT NULL,
        contact_id INT NOT NULL,
        contact_phone VARCHAR(50) NOT NULL,
        contact_name VARCHAR(255) NULL,
        template_params JSON NULL COMMENT 'Parametros do template para este contato',
        status ENUM('pending','sent','delivered','read','failed','skipped') NOT NULL DEFAULT 'pending',
        external_message_id VARCHAR(255) NULL,
        error_message TEXT NULL,
        sent_at TIMESTAMP NULL,
        delivered_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_broadcast (broadcast_id),
        INDEX idx_contact (contact_id),
        INDEX idx_status (broadcast_id, status),
        CONSTRAINT fk_vbm_broadcast FOREIGN KEY (broadcast_id) REFERENCES vendor_broadcasts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Tabelas vendor_broadcasts e vendor_broadcast_messages criadas.\n";
}

function down_create_vendor_broadcasts_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS vendor_broadcast_messages");
    $db->exec("DROP TABLE IF EXISTS vendor_broadcasts");

    echo "Tabelas vendor_broadcasts e vendor_broadcast_messages removidas.\n";
}
