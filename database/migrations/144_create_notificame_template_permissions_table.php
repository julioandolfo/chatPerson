<?php

function up_create_notificame_template_permissions_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS notificame_template_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        integration_account_id INT NOT NULL,
        template_name VARCHAR(255) NOT NULL,
        allowed_users JSON NULL COMMENT 'Array de user IDs permitidos. NULL = todos podem usar',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_account_template (integration_account_id, template_name),
        INDEX idx_account (integration_account_id),
        CONSTRAINT fk_ntp_integration_account FOREIGN KEY (integration_account_id) REFERENCES integration_accounts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "Tabela notificame_template_permissions criada.\n";
}

function down_create_notificame_template_permissions_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS notificame_template_permissions");

    echo "Tabela notificame_template_permissions removida.\n";
}
