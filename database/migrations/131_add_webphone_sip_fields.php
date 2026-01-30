<?php
/**
 * Migration: Adicionar campos para WebPhone SIP integrado
 * 
 * Adiciona campos necessários para integração do WebPhone SIP:
 * - sip_password_encrypted: Senha SIP criptografada (reversível para uso no WebPhone)
 * - webphone_enabled: Se o WebPhone está habilitado para o ramal
 */

function up_add_webphone_sip_fields() {
    $db = \App\Helpers\Database::getInstance();
    
    // Verificar se a coluna já existe
    $columns = $db->fetchAll("SHOW COLUMNS FROM api4com_extensions LIKE 'sip_password_encrypted'");
    
    if (empty($columns)) {
        $sql = "ALTER TABLE api4com_extensions 
                ADD COLUMN sip_password_encrypted TEXT NULL COMMENT 'Senha SIP criptografada (AES) para WebPhone' AFTER sip_password,
                ADD COLUMN webphone_enabled TINYINT(1) DEFAULT 1 COMMENT 'Se o WebPhone está habilitado' AFTER sip_password_encrypted";
        $db->exec($sql);
        echo "✅ Campos 'sip_password_encrypted' e 'webphone_enabled' adicionados à tabela 'api4com_extensions'!\n";
    } else {
        echo "⏭️ Campos já existem na tabela 'api4com_extensions'.\n";
    }
    
    // Adicionar campo sip_domain na tabela api4com_accounts se não existir
    $columns = $db->fetchAll("SHOW COLUMNS FROM api4com_accounts LIKE 'sip_domain'");
    
    if (empty($columns)) {
        $sql = "ALTER TABLE api4com_accounts 
                ADD COLUMN sip_domain VARCHAR(255) NULL COMMENT 'Domínio SIP para WebPhone (ex: empresa.api4com.com)' AFTER domain,
                ADD COLUMN sip_port INT DEFAULT 6443 COMMENT 'Porta WebSocket SIP' AFTER sip_domain,
                ADD COLUMN webphone_enabled TINYINT(1) DEFAULT 0 COMMENT 'Se o WebPhone integrado está habilitado' AFTER sip_port";
        $db->exec($sql);
        echo "✅ Campos SIP adicionados à tabela 'api4com_accounts'!\n";
    } else {
        echo "⏭️ Campos SIP já existem na tabela 'api4com_accounts'.\n";
    }
}

function down_add_webphone_sip_fields() {
    $db = \App\Helpers\Database::getInstance();
    
    $sql = "ALTER TABLE api4com_extensions 
            DROP COLUMN IF EXISTS sip_password_encrypted,
            DROP COLUMN IF EXISTS webphone_enabled";
    $db->exec($sql);
    
    $sql = "ALTER TABLE api4com_accounts 
            DROP COLUMN IF EXISTS sip_domain,
            DROP COLUMN IF EXISTS sip_port,
            DROP COLUMN IF EXISTS webphone_enabled";
    $db->exec($sql);
    
    echo "✅ Campos WebPhone SIP removidos!\n";
}
