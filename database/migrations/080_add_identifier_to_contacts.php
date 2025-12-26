<?php
/**
 * Migration: Adicionar coluna identifier à tabela contacts
 * Para suportar integrações de canais sociais (Instagram, Facebook, Telegram, etc)
 */

function up_add_identifier_to_contacts() {
    $db = \App\Helpers\Database::getInstance();
    
    // Verificar se a coluna já existe
    $columns = $db->query("SHOW COLUMNS FROM contacts LIKE 'identifier'")->fetchAll();
    
    if (empty($columns)) {
        $sql = "ALTER TABLE contacts 
                ADD COLUMN identifier VARCHAR(255) NULL AFTER whatsapp_id,
                ADD INDEX idx_identifier (identifier)";
        
        $db->exec($sql);
        echo "✅ Coluna 'identifier' adicionada à tabela 'contacts' com sucesso!\n";
    } else {
        echo "ℹ️  Coluna 'identifier' já existe na tabela 'contacts'.\n";
    }
}

function down_add_identifier_to_contacts() {
    $db = \App\Helpers\Database::getInstance();
    
    $sql = "ALTER TABLE contacts 
            DROP INDEX idx_identifier,
            DROP COLUMN identifier";
    
    $db->exec($sql);
    echo "✅ Coluna 'identifier' removida da tabela 'contacts'!\n";
}

