<?php
/**
 * Migration: Migrar dados de whatsapp_accounts para integration_accounts
 * Mantém whatsapp_accounts para compatibilidade (deprecated)
 */

function up_migrate_whatsapp_to_integration_accounts() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se já existe dados migrados
    $checkMigrated = $db->query("SELECT COUNT(*) as count FROM integration_accounts WHERE provider IN ('quepasa', 'evolution')")->fetch();
    if ($checkMigrated && $checkMigrated['count'] > 0) {
        echo "⚠️ Dados já migrados. Pulando migração.\n";
        return;
    }
    
    // Verificar quais colunas existem na tabela whatsapp_accounts
    $columns = $db->query("SHOW COLUMNS FROM whatsapp_accounts")->fetchAll(PDO::FETCH_COLUMN);
    $hasApiKey = in_array('api_key', $columns);
    $hasQuepasaUser = in_array('quepasa_user', $columns);
    $hasQuepasaToken = in_array('quepasa_token', $columns);
    $hasQuepasaTrackid = in_array('quepasa_trackid', $columns);
    $hasQuepasaChatid = in_array('quepasa_chatid', $columns);
    $hasWavoipToken = in_array('wavoip_token', $columns);
    $hasWavoipEnabled = in_array('wavoip_enabled', $columns);
    $hasDefaultFunnelId = in_array('default_funnel_id', $columns);
    $hasDefaultStageId = in_array('default_stage_id', $columns);
    
    // Construir lista de colunas para SELECT e INSERT dinamicamente
    // Nota: integration_accounts usa 'api_token', não 'api_key'
    $selectFields = ['name', 'provider', "'whatsapp' as channel", 'phone_number', 'api_url'];
    $insertFields = ['name', 'provider', 'channel', 'phone_number', 'api_url'];
    
    if ($hasApiKey) {
        $selectFields[] = 'api_key as api_token'; // Mapear api_key para api_token
        $insertFields[] = 'api_token';
    }
    
    $selectFields[] = 'instance_id';
    $insertFields[] = 'instance_id';
    
    $selectFields[] = 'status';
    $insertFields[] = 'status';
    
    // Construir JSON_OBJECT para config dinamicamente
    $configFields = [];
    if ($hasQuepasaUser) {
        $configFields[] = "'quepasa_user', COALESCE(quepasa_user, '')";
    }
    if ($hasQuepasaToken) {
        $configFields[] = "'quepasa_token', COALESCE(quepasa_token, '')";
    }
    if ($hasQuepasaTrackid) {
        $configFields[] = "'quepasa_trackid', COALESCE(quepasa_trackid, '')";
    }
    if ($hasQuepasaChatid) {
        $configFields[] = "'quepasa_chatid', COALESCE(quepasa_chatid, '')";
    }
    if ($hasWavoipToken) {
        $configFields[] = "'wavoip_token', COALESCE(wavoip_token, '')";
    }
    if ($hasWavoipEnabled) {
        $configFields[] = "'wavoip_enabled', COALESCE(wavoip_enabled, 0)";
    }
    
    $configJson = !empty($configFields) 
        ? "JSON_OBJECT(" . implode(', ', $configFields) . ")" 
        : "JSON_OBJECT()";
    
    $selectFields[] = $configJson . " as config";
    $insertFields[] = 'config';
    
    if ($hasDefaultFunnelId) {
        $selectFields[] = 'default_funnel_id';
        $insertFields[] = 'default_funnel_id';
    }
    
    if ($hasDefaultStageId) {
        $selectFields[] = 'default_stage_id';
        $insertFields[] = 'default_stage_id';
    }
    
    $selectFields[] = 'created_at';
    $insertFields[] = 'created_at';
    
    $selectFields[] = 'updated_at';
    $insertFields[] = 'updated_at';
    
    // Migrar dados de whatsapp_accounts para integration_accounts
    $sql = "
        INSERT INTO integration_accounts 
        (" . implode(', ', $insertFields) . ")
        SELECT 
            " . implode(', ', $selectFields) . "
        FROM whatsapp_accounts
    ";
    
    $db->exec($sql);
    echo "✅ Dados de whatsapp_accounts migrados para integration_accounts!\n";
    
    // Atualizar conversations para usar integration_account_id
    // Buscar correspondência por phone_number e provider
    $sql = "
        UPDATE conversations c
        INNER JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
        INNER JOIN integration_accounts ia ON ia.phone_number = wa.phone_number 
            AND ia.provider = wa.provider 
            AND ia.channel = 'whatsapp'
        SET c.integration_account_id = ia.id
        WHERE c.whatsapp_account_id IS NOT NULL
            AND c.integration_account_id IS NULL
    ";
    
    $affected = $db->exec($sql);
    echo "✅ {$affected} conversas atualizadas com integration_account_id!\n";
}

function down_migrate_whatsapp_to_integration_accounts() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Remover integration_account_id das conversas migradas
    $sql = "
        UPDATE conversations c
        INNER JOIN integration_accounts ia ON c.integration_account_id = ia.id
        INNER JOIN whatsapp_accounts wa ON wa.phone_number = ia.phone_number 
            AND wa.provider = ia.provider
        SET c.integration_account_id = NULL
        WHERE ia.provider IN ('quepasa', 'evolution')
    ";
    
    $affected = $db->exec($sql);
    echo "✅ {$affected} conversas revertidas!\n";
    
    // Remover contas migradas
    $sql = "DELETE FROM integration_accounts WHERE provider IN ('quepasa', 'evolution')";
    $db->exec($sql);
    echo "✅ Contas migradas removidas de integration_accounts!\n";
}

