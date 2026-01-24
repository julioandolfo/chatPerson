<?php
/**
 * Migration: Adicionar campos de horário de almoço à tabela working_hours_config
 */

function up_add_lunch_break_to_working_hours() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🔧 Adicionando campos de horário de almoço...\n";
    
    // Verificar se a tabela existe
    $tables = $db->query("SHOW TABLES LIKE 'working_hours_config'")->fetchAll();
    if (empty($tables)) {
        echo "   ⚠️ Tabela 'working_hours_config' não existe. Execute a migration 070 primeiro.\n";
        return;
    }
    
    // Verificar se os campos já existem
    $columns = $db->query("SHOW COLUMNS FROM working_hours_config LIKE 'lunch_start'")->fetchAll();
    if (!empty($columns)) {
        echo "   ⚠️ Campos de almoço já existem.\n";
        return;
    }
    
    // Adicionar campos
    $sql = "ALTER TABLE working_hours_config 
            ADD COLUMN lunch_enabled TINYINT(1) DEFAULT 0 COMMENT 'Se tem intervalo de almoço' AFTER end_time,
            ADD COLUMN lunch_start TIME DEFAULT '12:00:00' COMMENT 'Início do almoço' AFTER lunch_enabled,
            ADD COLUMN lunch_end TIME DEFAULT '13:00:00' COMMENT 'Fim do almoço' AFTER lunch_start";
    
    $db->exec($sql);
    echo "   ✅ Campos lunch_enabled, lunch_start, lunch_end adicionados\n";
    
    echo "\n✅ Migration concluída!\n";
}

function down_add_lunch_break_to_working_hours() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "🔧 Removendo campos de horário de almoço...\n";
    
    $sql = "ALTER TABLE working_hours_config 
            DROP COLUMN IF EXISTS lunch_enabled,
            DROP COLUMN IF EXISTS lunch_start,
            DROP COLUMN IF EXISTS lunch_end";
    
    $db->exec($sql);
    echo "   ✅ Campos removidos\n";
}
