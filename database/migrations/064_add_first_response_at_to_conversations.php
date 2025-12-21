<?php
/**
 * Migration: Adicionar coluna first_response_at à tabela conversations
 * Para rastreamento de SLA de primeira resposta
 */

function up_add_first_response_at_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations ADD COLUMN IF NOT EXISTS first_response_at TIMESTAMP NULL AFTER resolved_at";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Coluna 'first_response_at' adicionada à tabela 'conversations' com sucesso!\n";
}

function down_add_first_response_at_to_conversations() {
    $sql = "ALTER TABLE conversations DROP COLUMN IF EXISTS first_response_at";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Coluna 'first_response_at' removida da tabela 'conversations'!\n";
}

