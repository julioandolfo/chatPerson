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
    
    // Migrar dados de whatsapp_accounts para integration_accounts
    $sql = "
        INSERT INTO integration_accounts 
        (name, provider, channel, phone_number, api_url, api_key, instance_id, status, config, default_funnel_id, default_stage_id, created_at, updated_at)
        SELECT 
            name,
            provider,
            'whatsapp' as channel,
            phone_number,
            api_url,
            api_key,
            instance_id,
            status,
            JSON_OBJECT(
                'quepasa_user', COALESCE(quepasa_user, ''),
                'quepasa_token', COALESCE(quepasa_token, ''),
                'quepasa_trackid', COALESCE(quepasa_trackid, ''),
                'quepasa_chatid', COALESCE(quepasa_chatid, ''),
                'wavoip_token', COALESCE(wavoip_token, ''),
                'wavoip_enabled', COALESCE(wavoip_enabled, 0)
            ) as config,
            default_funnel_id,
            default_stage_id,
            created_at,
            updated_at
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

