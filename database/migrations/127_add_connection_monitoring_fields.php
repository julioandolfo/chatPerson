<?php
/**
 * Migration: Adicionar campos de monitoramento de conexão WhatsApp
 * Campos para rastrear verificações periódicas de conexão
 */

function up_connection_monitoring_fields() {
    global $pdo;
    
    // Adicionar campos à tabela whatsapp_accounts
    $sqls = [
        // Última verificação de conexão
        "ALTER TABLE whatsapp_accounts ADD COLUMN IF NOT EXISTS last_connection_check TIMESTAMP NULL COMMENT 'Última verificação de conexão'",
        
        // Resultado da última verificação (connected/disconnected)
        "ALTER TABLE whatsapp_accounts ADD COLUMN IF NOT EXISTS last_connection_result ENUM('connected', 'disconnected', 'error') NULL COMMENT 'Resultado da última verificação'",
        
        // Mensagem da última verificação
        "ALTER TABLE whatsapp_accounts ADD COLUMN IF NOT EXISTS last_connection_message VARCHAR(500) NULL COMMENT 'Mensagem da última verificação'",
        
        // Contagem de falhas consecutivas
        "ALTER TABLE whatsapp_accounts ADD COLUMN IF NOT EXISTS consecutive_failures INT DEFAULT 0 COMMENT 'Contagem de falhas consecutivas de conexão'",
        
        // Índice para buscar por última verificação
        "ALTER TABLE whatsapp_accounts ADD INDEX IF NOT EXISTS idx_last_connection_check (last_connection_check)"
    ];
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    foreach ($sqls as $sql) {
        try {
            $db->exec($sql);
        } catch (\Exception $e) {
            // Ignorar erros de "coluna já existe"
            if (strpos($e->getMessage(), 'Duplicate column') === false && 
                strpos($e->getMessage(), 'Duplicate key') === false) {
                echo "⚠️  Aviso: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "✅ Campos de monitoramento de conexão adicionados à tabela 'whatsapp_accounts'!\n";
}

function down_connection_monitoring_fields() {
    global $pdo;
    
    $sqls = [
        "ALTER TABLE whatsapp_accounts DROP COLUMN IF EXISTS last_connection_check",
        "ALTER TABLE whatsapp_accounts DROP COLUMN IF EXISTS last_connection_result",
        "ALTER TABLE whatsapp_accounts DROP COLUMN IF EXISTS last_connection_message",
        "ALTER TABLE whatsapp_accounts DROP COLUMN IF EXISTS consecutive_failures",
        "ALTER TABLE whatsapp_accounts DROP INDEX IF EXISTS idx_last_connection_check"
    ];
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    foreach ($sqls as $sql) {
        try {
            $db->exec($sql);
        } catch (\Exception $e) {
            // Ignorar erros
        }
    }
    
    echo "✅ Campos de monitoramento de conexão removidos!\n";
}
