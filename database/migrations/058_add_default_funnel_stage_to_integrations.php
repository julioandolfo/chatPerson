<?php
/**
 * Migration: Adicionar campos de funil/etapa padrão nas integrações
 * 
 * Permite que cada integração (WhatsApp, Email, etc) configure qual
 * funil e etapa usar como padrão para conversas criadas por ela.
 */

require_once __DIR__ . '/../../app/Helpers/Database.php';

function up_add_default_funnel_stage_to_integrations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Verificar se colunas já existem
        $columns = $db->query("SHOW COLUMNS FROM whatsapp_accounts LIKE 'default_funnel_id'")->fetchAll();
        
        if (empty($columns)) {
            // Adicionar campos na tabela whatsapp_accounts
            $db->exec("
                ALTER TABLE whatsapp_accounts 
                ADD COLUMN default_funnel_id INT NULL DEFAULT NULL AFTER status,
                ADD COLUMN default_stage_id INT NULL DEFAULT NULL AFTER default_funnel_id
            ");
            
            echo "✅ Campos default_funnel_id e default_stage_id adicionados à tabela whatsapp_accounts\n";
            
            // Adicionar foreign keys
            $db->exec("
                ALTER TABLE whatsapp_accounts 
                ADD CONSTRAINT fk_whatsapp_default_funnel 
                    FOREIGN KEY (default_funnel_id) 
                    REFERENCES funnels(id) 
                    ON DELETE SET NULL
            ");
            
            $db->exec("
                ALTER TABLE whatsapp_accounts 
                ADD CONSTRAINT fk_whatsapp_default_stage 
                    FOREIGN KEY (default_stage_id) 
                    REFERENCES funnel_stages(id) 
                    ON DELETE SET NULL
            ");
            
            echo "✅ Foreign keys criadas\n";
            
            // Adicionar índices para performance
            $db->exec("
                ALTER TABLE whatsapp_accounts 
                ADD INDEX idx_default_funnel (default_funnel_id),
                ADD INDEX idx_default_stage (default_stage_id)
            ");
            
            echo "✅ Índices criados para campos de funil/etapa padrão\n";
        } else {
            echo "ℹ️ Campos já existem na tabela whatsapp_accounts\n";
        }
        
        // Buscar funil e etapa padrão do sistema
        $defaultConfig = $db->query("
            SELECT value FROM settings WHERE `key` = 'system_default_funnel_stage' LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
        
        if ($defaultConfig) {
            $config = json_decode($defaultConfig['value'], true);
            $defaultFunnelId = $config['funnel_id'] ?? null;
            $defaultStageId = $config['stage_id'] ?? null;
            
            // Atualizar contas existentes com o padrão do sistema
            if ($defaultFunnelId && $defaultStageId) {
                $stmt = $db->prepare("
                    UPDATE whatsapp_accounts 
                    SET default_funnel_id = ?, default_stage_id = ? 
                    WHERE default_funnel_id IS NULL
                ");
                $stmt->execute([$defaultFunnelId, $defaultStageId]);
                
                $updated = $stmt->rowCount();
                echo "✅ {$updated} conta(s) WhatsApp atualizadas com funil/etapa padrão do sistema\n";
            }
        }
        
        echo "✅ Migration 058_add_default_funnel_stage_to_integrations executada com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao adicionar campos: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_add_default_funnel_stage_to_integrations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Remover foreign keys primeiro
        $db->exec("
            ALTER TABLE whatsapp_accounts 
            DROP FOREIGN KEY IF EXISTS fk_whatsapp_default_funnel,
            DROP FOREIGN KEY IF EXISTS fk_whatsapp_default_stage
        ");
        
        // Remover índices
        $db->exec("
            ALTER TABLE whatsapp_accounts 
            DROP INDEX IF EXISTS idx_default_funnel,
            DROP INDEX IF EXISTS idx_default_stage
        ");
        
        // Remover colunas
        $db->exec("
            ALTER TABLE whatsapp_accounts 
            DROP COLUMN IF EXISTS default_funnel_id,
            DROP COLUMN IF EXISTS default_stage_id
        ");
        
        echo "✅ Campos removidos da tabela whatsapp_accounts\n";
        echo "✅ Rollback da migration 058 executado com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao reverter migration: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Executar se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'] ?? '')) {
    require_once __DIR__ . '/../../config/database.php';
    up_add_default_funnel_stage_to_integrations();
}

