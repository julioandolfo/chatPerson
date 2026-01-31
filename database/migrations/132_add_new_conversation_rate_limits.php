<?php
/**
 * Migration: Adicionar campos de limite de novas conversas nas integrações
 * 
 * Permite configurar limite de quantas novas conversas manuais podem ser
 * criadas por integração em um período de tempo (minutos, horas ou dias)
 */

use App\Helpers\Database;

function up_new_conversation_rate_limits(): void
{
    $db = Database::getInstance();
    
    // Adicionar campos em whatsapp_accounts
    $columnsWA = [
        'new_conv_limit_enabled' => "ALTER TABLE whatsapp_accounts ADD COLUMN new_conv_limit_enabled TINYINT(1) DEFAULT 0 COMMENT 'Habilitar limite de novas conversas'",
        'new_conv_limit_count' => "ALTER TABLE whatsapp_accounts ADD COLUMN new_conv_limit_count INT DEFAULT 10 COMMENT 'Quantidade máxima de novas conversas'",
        'new_conv_limit_period' => "ALTER TABLE whatsapp_accounts ADD COLUMN new_conv_limit_period ENUM('minutes', 'hours', 'days') DEFAULT 'hours' COMMENT 'Período do limite'",
        'new_conv_limit_period_value' => "ALTER TABLE whatsapp_accounts ADD COLUMN new_conv_limit_period_value INT DEFAULT 1 COMMENT 'Valor do período (ex: 1 hora, 30 minutos)'",
    ];
    
    foreach ($columnsWA as $column => $sql) {
        try {
            $exists = $db->query(
                "SELECT COUNT(*) as cnt FROM information_schema.columns 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'whatsapp_accounts' 
                 AND column_name = ?",
                [$column]
            )->fetch();
            
            if (!$exists || $exists['cnt'] == 0) {
                $db->exec($sql);
                echo "  ✓ Coluna {$column} adicionada em whatsapp_accounts\n";
            }
        } catch (\Exception $e) {
            echo "  ! Erro ao adicionar {$column} em whatsapp_accounts: " . $e->getMessage() . "\n";
        }
    }
    
    // Adicionar campos em integration_accounts
    $columnsIA = [
        'new_conv_limit_enabled' => "ALTER TABLE integration_accounts ADD COLUMN new_conv_limit_enabled TINYINT(1) DEFAULT 0 COMMENT 'Habilitar limite de novas conversas'",
        'new_conv_limit_count' => "ALTER TABLE integration_accounts ADD COLUMN new_conv_limit_count INT DEFAULT 10 COMMENT 'Quantidade máxima de novas conversas'",
        'new_conv_limit_period' => "ALTER TABLE integration_accounts ADD COLUMN new_conv_limit_period ENUM('minutes', 'hours', 'days') DEFAULT 'hours' COMMENT 'Período do limite'",
        'new_conv_limit_period_value' => "ALTER TABLE integration_accounts ADD COLUMN new_conv_limit_period_value INT DEFAULT 1 COMMENT 'Valor do período (ex: 1 hora, 30 minutos)'",
    ];
    
    foreach ($columnsIA as $column => $sql) {
        try {
            $exists = $db->query(
                "SELECT COUNT(*) as cnt FROM information_schema.columns 
                 WHERE table_schema = DATABASE() 
                 AND table_name = 'integration_accounts' 
                 AND column_name = ?",
                [$column]
            )->fetch();
            
            if (!$exists || $exists['cnt'] == 0) {
                $db->exec($sql);
                echo "  ✓ Coluna {$column} adicionada em integration_accounts\n";
            }
        } catch (\Exception $e) {
            echo "  ! Erro ao adicionar {$column} em integration_accounts: " . $e->getMessage() . "\n";
        }
    }
    
    // Criar tabela para rastrear novas conversas por integração
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS new_conversation_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            account_type ENUM('whatsapp', 'integration') NOT NULL COMMENT 'Tipo de conta',
            account_id INT NOT NULL COMMENT 'ID da conta (whatsapp_accounts ou integration_accounts)',
            user_id INT NULL COMMENT 'ID do usuário que criou',
            contact_id INT NOT NULL COMMENT 'ID do contato',
            conversation_id INT NOT NULL COMMENT 'ID da conversa criada',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_account_type_id (account_type, account_id),
            INDEX idx_created_at (created_at),
            INDEX idx_account_created (account_type, account_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Log de novas conversas manuais para controle de rate limit'
    ";
    
    try {
        $db->exec($createTableSQL);
        echo "  ✓ Tabela new_conversation_log criada/verificada\n";
    } catch (\Exception $e) {
        echo "  ! Erro ao criar tabela new_conversation_log: " . $e->getMessage() . "\n";
    }
    
    echo "Migration 132_add_new_conversation_rate_limits concluída!\n";
}

function down_new_conversation_rate_limits(): void
{
    $db = Database::getInstance();
    
    // Remover colunas de whatsapp_accounts
    $columns = ['new_conv_limit_enabled', 'new_conv_limit_count', 'new_conv_limit_period', 'new_conv_limit_period_value'];
    
    foreach ($columns as $column) {
        try {
            $db->exec("ALTER TABLE whatsapp_accounts DROP COLUMN {$column}");
            echo "  ✓ Coluna {$column} removida de whatsapp_accounts\n";
        } catch (\Exception $e) {
            // Ignorar se não existir
        }
    }
    
    foreach ($columns as $column) {
        try {
            $db->exec("ALTER TABLE integration_accounts DROP COLUMN {$column}");
            echo "  ✓ Coluna {$column} removida de integration_accounts\n";
        } catch (\Exception $e) {
            // Ignorar se não existir
        }
    }
    
    try {
        $db->exec("DROP TABLE IF EXISTS new_conversation_log");
        echo "  ✓ Tabela new_conversation_log removida\n";
    } catch (\Exception $e) {
        // Ignorar
    }
}
