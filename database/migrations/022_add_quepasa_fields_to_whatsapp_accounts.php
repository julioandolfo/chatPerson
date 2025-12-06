<?php
/**
 * Migration: Adicionar campos específicos da Quepasa self-hosted
 */

function up_add_quepasa_fields_to_whatsapp_accounts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Adicionar campo quepasa_user
    try {
        $check = $db->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'quepasa_user'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE whatsapp_accounts ADD COLUMN quepasa_user VARCHAR(255) NULL COMMENT 'Identificador do usuário Quepasa (X-QUEPASA-USER)' AFTER api_key");
            echo "✅ Coluna 'quepasa_user' adicionada!\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao adicionar 'quepasa_user': " . $e->getMessage() . "\n";
    }
    
    // Adicionar campo quepasa_token (token gerado pelo /scan)
    try {
        $check = $db->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'quepasa_token'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE whatsapp_accounts ADD COLUMN quepasa_token TEXT NULL COMMENT 'Token gerado pela Quepasa API (X-QUEPASA-TOKEN)' AFTER quepasa_user");
            echo "✅ Coluna 'quepasa_token' adicionada!\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao adicionar 'quepasa_token': " . $e->getMessage() . "\n";
    }
    
    // Adicionar campo quepasa_trackid
    try {
        $check = $db->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'quepasa_trackid'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE whatsapp_accounts ADD COLUMN quepasa_trackid VARCHAR(255) NULL COMMENT 'Track ID para rastreamento (X-QUEPASA-TRACKID)' AFTER quepasa_token");
            echo "✅ Coluna 'quepasa_trackid' adicionada!\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao adicionar 'quepasa_trackid': " . $e->getMessage() . "\n";
    }
    
    // Adicionar campo quepasa_chatid
    try {
        $check = $db->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'quepasa_chatid'")->fetch();
        if (!$check) {
            $db->exec("ALTER TABLE whatsapp_accounts ADD COLUMN quepasa_chatid VARCHAR(255) NULL COMMENT 'Chat ID retornado pelo scan (X-QUEPASA-CHATID)' AFTER quepasa_trackid");
            echo "✅ Coluna 'quepasa_chatid' adicionada!\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao adicionar 'quepasa_chatid': " . $e->getMessage() . "\n";
    }
}

function down_add_quepasa_fields_to_whatsapp_accounts() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE whatsapp_accounts DROP COLUMN quepasa_chatid");
        $db->exec("ALTER TABLE whatsapp_accounts DROP COLUMN quepasa_trackid");
        $db->exec("ALTER TABLE whatsapp_accounts DROP COLUMN quepasa_token");
        $db->exec("ALTER TABLE whatsapp_accounts DROP COLUMN quepasa_user");
        echo "✅ Colunas Quepasa removidas!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao remover colunas: " . $e->getMessage() . "\n";
    }
}

