<?php
/**
 * Migration: Adicionar campos WavoIP em whatsapp_accounts
 */

function up_add_wavoip_fields_to_whatsapp_accounts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Verificar se coluna já existe antes de adicionar
        $checkSql = "SHOW COLUMNS FROM whatsapp_accounts LIKE 'wavoip_token'";
        $result = $db->query($checkSql);
        
        if ($result->rowCount() == 0) {
            $db->exec("ALTER TABLE whatsapp_accounts 
                ADD COLUMN wavoip_token VARCHAR(255) NULL COMMENT 'Token de autenticação WavoIP' AFTER quepasa_chatid,
                ADD COLUMN wavoip_enabled TINYINT(1) DEFAULT 0 COMMENT 'Se chamadas de voz estão habilitadas' AFTER wavoip_token");
            echo "✅ Campos WavoIP adicionados à tabela 'whatsapp_accounts'!\n";
        } else {
            echo "ℹ️ Campos WavoIP já existem na tabela 'whatsapp_accounts'.\n";
        }
    } catch (\Exception $e) {
        echo "⚠️ Erro ao adicionar campos WavoIP: " . $e->getMessage() . "\n";
    }
}

function down_add_wavoip_fields_to_whatsapp_accounts() {
    $db = \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE whatsapp_accounts 
            DROP COLUMN IF EXISTS wavoip_token,
            DROP COLUMN IF EXISTS wavoip_enabled");
        echo "✅ Campos WavoIP removidos da tabela 'whatsapp_accounts'!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro ao remover campos WavoIP: " . $e->getMessage() . "\n";
    }
}

