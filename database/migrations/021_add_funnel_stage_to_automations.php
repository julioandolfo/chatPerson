<?php
/**
 * Migration: Adicionar campos funnel_id e stage_id à tabela automations
 */

function up_add_funnel_stage_to_automations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se coluna funnel_id já existe
    $checkFunnel = $db->query("SHOW COLUMNS FROM automations LIKE 'funnel_id'")->fetch();
    if (!$checkFunnel) {
        $sql = "ALTER TABLE automations 
                ADD COLUMN funnel_id INT NULL COMMENT 'Funil específico (NULL = todos os funis)' AFTER trigger_config,
                ADD CONSTRAINT fk_automation_funnel
                FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE";
        $db->exec($sql);
        echo "✅ Coluna 'funnel_id' adicionada à tabela 'automations'!\n";
    } else {
        echo "⚠️ Coluna 'funnel_id' já existe.\n";
    }
    
    // Verificar se coluna stage_id já existe
    $checkStage = $db->query("SHOW COLUMNS FROM automations LIKE 'stage_id'")->fetch();
    if (!$checkStage) {
        $sql = "ALTER TABLE automations 
                ADD COLUMN stage_id INT NULL COMMENT 'Estágio específico (NULL = todos os estágios)' AFTER funnel_id,
                ADD CONSTRAINT fk_automation_stage
                FOREIGN KEY (stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE";
        $db->exec($sql);
        echo "✅ Coluna 'stage_id' adicionada à tabela 'automations'!\n";
    } else {
        echo "⚠️ Coluna 'stage_id' já existe.\n";
    }
    
    // Adicionar índices se não existirem
    try {
        $db->exec("CREATE INDEX idx_funnel_id ON automations(funnel_id)");
        echo "✅ Índice 'idx_funnel_id' criado!\n";
    } catch (\Exception $e) {
        echo "⚠️ Índice 'idx_funnel_id' pode já existir.\n";
    }
    
    try {
        $db->exec("CREATE INDEX idx_stage_id ON automations(stage_id)");
        echo "✅ Índice 'idx_stage_id' criado!\n";
    } catch (\Exception $e) {
        echo "⚠️ Índice 'idx_stage_id' pode já existir.\n";
    }
}

function down_add_funnel_stage_to_automations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE automations DROP FOREIGN KEY fk_automation_stage");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE automations DROP FOREIGN KEY fk_automation_funnel");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE automations DROP COLUMN stage_id");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE automations DROP COLUMN funnel_id");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    echo "✅ Colunas removidas da tabela 'automations'!\n";
}

