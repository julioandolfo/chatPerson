<?php
/**
 * Migration: Add Cooldown and Re-execution Settings to AI Kanban Agents
 * 
 * Adiciona campos para controlar cooldown entre execuções e permitir re-execução baseada em mudanças
 */

function up_add_cooldown_to_kanban_agents() {
    global $pdo;
    
    $sql = "
        ALTER TABLE ai_kanban_agents 
        ADD COLUMN IF NOT EXISTS cooldown_hours INT DEFAULT 24 COMMENT 'Horas de cooldown entre execuções na mesma conversa',
        ADD COLUMN IF NOT EXISTS allow_reexecution_on_change BOOLEAN DEFAULT 1 COMMENT 'Permitir re-execução se houver mudanças significativas'
    ";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campos cooldown_hours e allow_reexecution_on_change adicionados à tabela 'ai_kanban_agents'!\n";
}

function down_add_cooldown_to_kanban_agents() {
    global $pdo;
    
    $sql = "
        ALTER TABLE ai_kanban_agents 
        DROP COLUMN IF EXISTS cooldown_hours,
        DROP COLUMN IF EXISTS allow_reexecution_on_change
    ";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campos removidos da tabela 'ai_kanban_agents'!\n";
}
