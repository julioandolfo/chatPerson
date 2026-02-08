<?php
/**
 * Migration: Adicionar campos de proxy e native session à integration_accounts
 * Para suportar o provider "native" (Baileys) com proxies individuais
 */

function up_add_proxy_and_native_fields() {
    $db = \App\Helpers\Database::getInstance();
    
    // proxy_host - URL do proxy (ex: socks5://proxy.example.com:1080)
    $columns = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'proxy_host'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE integration_accounts ADD COLUMN proxy_host VARCHAR(500) NULL AFTER config");
        echo "✅ Coluna 'proxy_host' adicionada à tabela 'integration_accounts'!\n";
    } else {
        echo "⏭️ Coluna 'proxy_host' já existe.\n";
    }
    
    // proxy_user - Usuário do proxy
    $columns = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'proxy_user'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE integration_accounts ADD COLUMN proxy_user VARCHAR(255) NULL AFTER proxy_host");
        echo "✅ Coluna 'proxy_user' adicionada!\n";
    } else {
        echo "⏭️ Coluna 'proxy_user' já existe.\n";
    }
    
    // proxy_pass - Senha do proxy
    $columns = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'proxy_pass'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE integration_accounts ADD COLUMN proxy_pass VARCHAR(255) NULL AFTER proxy_user");
        echo "✅ Coluna 'proxy_pass' adicionada!\n";
    } else {
        echo "⏭️ Coluna 'proxy_pass' já existe.\n";
    }
    
    // native_session_id - ID da sessão no Baileys Service
    $columns = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'native_session_id'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE integration_accounts ADD COLUMN native_session_id VARCHAR(255) NULL AFTER proxy_pass");
        echo "✅ Coluna 'native_session_id' adicionada!\n";
    } else {
        echo "⏭️ Coluna 'native_session_id' já existe.\n";
    }
    
    // native_service_url - URL do Baileys Service (default: http://127.0.0.1:3100)
    $columns = $db->query("SHOW COLUMNS FROM integration_accounts LIKE 'native_service_url'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE integration_accounts ADD COLUMN native_service_url VARCHAR(500) NULL DEFAULT 'http://127.0.0.1:3100' AFTER native_session_id");
        echo "✅ Coluna 'native_service_url' adicionada!\n";
    } else {
        echo "⏭️ Coluna 'native_service_url' já existe.\n";
    }
}

function down_add_proxy_and_native_fields() {
    $db = \App\Helpers\Database::getInstance();
    $db->exec("ALTER TABLE integration_accounts DROP COLUMN IF EXISTS proxy_host");
    $db->exec("ALTER TABLE integration_accounts DROP COLUMN IF EXISTS proxy_user");
    $db->exec("ALTER TABLE integration_accounts DROP COLUMN IF EXISTS proxy_pass");
    $db->exec("ALTER TABLE integration_accounts DROP COLUMN IF EXISTS native_session_id");
    $db->exec("ALTER TABLE integration_accounts DROP COLUMN IF EXISTS native_service_url");
    echo "✅ Colunas proxy/native removidas da tabela 'integration_accounts'!\n";
}
