<?php
/**
 * Migration: Add Meta (Instagram + WhatsApp) fields to contacts
 * 
 * Adiciona campos específicos do Instagram Graph API e WhatsApp Cloud API
 */

function up_add_meta_fields_to_contacts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se as colunas já existem
    $columns = [];
    $result = $db->query("SHOW COLUMNS FROM contacts");
    while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    // Adicionar colunas se não existirem
    if (!in_array('instagram_user_id', $columns)) {
        $db->exec("ALTER TABLE contacts ADD COLUMN instagram_user_id VARCHAR(255) NULL COMMENT 'Instagram User ID (Graph API)'");
        echo "✅ Coluna 'instagram_user_id' adicionada\n";
    } else {
        echo "ℹ️ Coluna 'instagram_user_id' já existe\n";
    }
    
    if (!in_array('whatsapp_wa_id', $columns)) {
        $db->exec("ALTER TABLE contacts ADD COLUMN whatsapp_wa_id VARCHAR(255) NULL COMMENT 'WhatsApp ID (Cloud API)'");
        echo "✅ Coluna 'whatsapp_wa_id' adicionada\n";
    } else {
        echo "ℹ️ Coluna 'whatsapp_wa_id' já existe\n";
    }
    
    if (!in_array('meta_synced_at', $columns)) {
        $db->exec("ALTER TABLE contacts ADD COLUMN meta_synced_at TIMESTAMP NULL COMMENT 'Última sincronização com Meta APIs'");
        echo "✅ Coluna 'meta_synced_at' adicionada\n";
    } else {
        echo "ℹ️ Coluna 'meta_synced_at' já existe\n";
    }
    
    // Adicionar índices se não existirem
    try {
        $db->exec("ALTER TABLE contacts ADD INDEX idx_instagram_user_id (instagram_user_id)");
        echo "✅ Índice 'idx_instagram_user_id' criado\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️ Índice 'idx_instagram_user_id' já existe\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE contacts ADD INDEX idx_whatsapp_wa_id (whatsapp_wa_id)");
        echo "✅ Índice 'idx_whatsapp_wa_id' criado\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️ Índice 'idx_whatsapp_wa_id' já existe\n";
        } else {
            throw $e;
        }
    }
    
    echo "✅ Campos Meta adicionados à tabela 'contacts' com sucesso!\n";
}

function down_add_meta_fields_to_contacts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Remover índices
    try {
        $db->exec("ALTER TABLE contacts DROP INDEX idx_instagram_user_id");
        echo "✅ Índice 'idx_instagram_user_id' removido\n";
    } catch (\PDOException $e) {
        echo "ℹ️ Índice 'idx_instagram_user_id' não existe\n";
    }
    
    try {
        $db->exec("ALTER TABLE contacts DROP INDEX idx_whatsapp_wa_id");
        echo "✅ Índice 'idx_whatsapp_wa_id' removido\n";
    } catch (\PDOException $e) {
        echo "ℹ️ Índice 'idx_whatsapp_wa_id' não existe\n";
    }
    
    // Remover colunas
    try {
        $db->exec("ALTER TABLE contacts DROP COLUMN instagram_user_id");
        echo "✅ Coluna 'instagram_user_id' removida\n";
    } catch (\PDOException $e) {
        echo "ℹ️ Coluna 'instagram_user_id' não existe\n";
    }
    
    try {
        $db->exec("ALTER TABLE contacts DROP COLUMN whatsapp_wa_id");
        echo "✅ Coluna 'whatsapp_wa_id' removida\n";
    } catch (\PDOException $e) {
        echo "ℹ️ Coluna 'whatsapp_wa_id' não existe\n";
    }
    
    try {
        $db->exec("ALTER TABLE contacts DROP COLUMN meta_synced_at");
        echo "✅ Coluna 'meta_synced_at' removida\n";
    } catch (\PDOException $e) {
        echo "ℹ️ Coluna 'meta_synced_at' não existe\n";
    }
    
    echo "✅ Campos Meta removidos da tabela 'contacts' com sucesso!\n";
}

