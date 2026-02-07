<?php
/**
 * Migration: Adicionar suporte a reações de mensagens
 * 
 * Adiciona coluna 'reactions' (JSON) na tabela messages para armazenar
 * reações de emoji vinculadas à mensagem original.
 * 
 * Estrutura do JSON:
 * [
 *   {"emoji": "❤️", "from": "contact", "sender_id": 123, "timestamp": 1707307200},
 *   {"emoji": "👍", "from": "agent", "sender_id": 5, "timestamp": 1707307300}
 * ]
 */

function up_add_reactions_to_messages() {
    $db = \App\Helpers\Database::getInstance();
    
    // Verificar se coluna já existe
    $columns = $db->query("SHOW COLUMNS FROM messages LIKE 'reactions'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE messages ADD COLUMN reactions JSON NULL AFTER attachments");
        echo "✅ Coluna 'reactions' adicionada à tabela 'messages'!\n";
    } else {
        echo "⏭️ Coluna 'reactions' já existe na tabela 'messages'.\n";
    }
}

function down_add_reactions_to_messages() {
    $db = \App\Helpers\Database::getInstance();
    $db->exec("ALTER TABLE messages DROP COLUMN IF EXISTS reactions");
    echo "✅ Coluna 'reactions' removida da tabela 'messages'!\n";
}
