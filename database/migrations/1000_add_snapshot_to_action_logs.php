<?php
/**
 * Migration: Add Conversation Snapshot to Action Logs
 * 
 * Adiciona campo para armazenar snapshot do estado da conversa no momento da execução
 */

function up_add_snapshot_to_action_logs() {
    global $pdo;
    
    $sql = "
        ALTER TABLE ai_kanban_agent_actions_log
        ADD COLUMN IF NOT EXISTS conversation_snapshot JSON DEFAULT NULL COMMENT 'Estado da conversa no momento da execução'
    ";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campo conversation_snapshot adicionado à tabela 'ai_kanban_agent_actions_log'!\n";
}

function down_add_snapshot_to_action_logs() {
    global $pdo;
    
    $sql = "
        ALTER TABLE ai_kanban_agent_actions_log
        DROP COLUMN IF EXISTS conversation_snapshot
    ";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Campo conversation_snapshot removido da tabela 'ai_kanban_agent_actions_log'!\n";
}
