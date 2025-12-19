<?php
/**
 * Migration: Adicionar campo metadata à tabela conversations
 * Para armazenar estado de chatbots, automações e outros dados dinâmicos
 */

function up_add_metadata_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            ADD COLUMN metadata JSON NULL COMMENT 'Estado de chatbots, automações e dados dinâmicos' AFTER priority";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Coluna 'metadata' adicionada à tabela 'conversations' com sucesso!\n";
}

function down_add_metadata_to_conversations() {
    $sql = "ALTER TABLE conversations DROP COLUMN metadata";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Coluna 'metadata' removida da tabela 'conversations'!\n";
}

