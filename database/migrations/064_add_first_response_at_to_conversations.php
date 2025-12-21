<?php
/**
 * Migration: Adicionar coluna first_response_at à tabela conversations
 * Para rastreamento de SLA de primeira resposta
 */

function up_add_first_response_at_to_conversations() {
    global $pdo;
    
    // Verificar se coluna já existe
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'first_response_at'";
    $result = $db->query($checkSql)->fetchAll();
    
    if (empty($result)) {
        // Coluna não existe, criar
        $sql = "ALTER TABLE conversations ADD COLUMN first_response_at TIMESTAMP NULL AFTER resolved_at";
        $db->exec($sql);
        echo "✅ Coluna 'first_response_at' adicionada à tabela 'conversations' com sucesso!\n";
    } else {
        echo "ℹ️ Coluna 'first_response_at' já existe na tabela 'conversations'.\n";
    }
}

function down_add_first_response_at_to_conversations() {
    $sql = "ALTER TABLE conversations DROP COLUMN IF EXISTS first_response_at";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Coluna 'first_response_at' removida da tabela 'conversations'!\n";
}

