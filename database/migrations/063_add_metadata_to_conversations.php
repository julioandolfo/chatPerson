<?php
/**
 * Migration: Adicionar campo metadata à tabela conversations
 * Para armazenar estado de chatbots, automações e outros dados dinâmicos
 */

function up_add_metadata_to_conversations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Adicionar metadata (JSON para chatbots e automações)
    $sql1 = "ALTER TABLE conversations 
             ADD COLUMN metadata JSON NULL COMMENT 'Estado de chatbots, automações e dados dinâmicos' AFTER priority";
    $db->exec($sql1);
    echo "✅ Coluna 'metadata' adicionada à tabela 'conversations' com sucesso!\n";
    
    // Adicionar assigned_at (timestamp de atribuição)
    $sql2 = "ALTER TABLE conversations 
             ADD COLUMN assigned_at TIMESTAMP NULL COMMENT 'Data/hora da atribuição ao agente' AFTER moved_at";
    $db->exec($sql2);
    echo "✅ Coluna 'assigned_at' adicionada à tabela 'conversations' com sucesso!\n";
}

function down_add_metadata_to_conversations() {
    $sql = "ALTER TABLE conversations DROP COLUMN metadata";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Coluna 'metadata' removida da tabela 'conversations'!\n";
}

