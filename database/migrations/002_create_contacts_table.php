<?php
/**
 * Migration: Criar tabela contacts
 */

function up_contacts_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        avatar VARCHAR(255) NULL,
        custom_attributes JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'contacts' criada com sucesso!\n";
}

function down_contacts_table() {
    $sql = "DROP TABLE IF EXISTS contacts";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'contacts' removida!\n";
}

