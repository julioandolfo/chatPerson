<?php
/**
 * Migration: Adicionar status 'cancelled' à tabela conversation_mentions
 */

function up_add_cancelled_status_to_mentions() {
    global $pdo;
    
    if (!isset($pdo)) {
        $pdo = \App\Helpers\Database::getInstance();
    }
    
    // Alterar ENUM para incluir 'cancelled'
    $sql = "ALTER TABLE conversation_mentions 
            MODIFY COLUMN status ENUM('pending', 'accepted', 'declined', 'expired', 'cancelled') 
            DEFAULT 'pending' 
            COMMENT 'Status do convite'";
    
    try {
        $pdo->exec($sql);
        echo "✅ Status 'cancelled' adicionado à tabela conversation_mentions\n";
    } catch (\PDOException $e) {
        echo "⚠️ Aviso ao modificar status: " . $e->getMessage() . "\n";
    }
}

function down_add_cancelled_status_to_mentions() {
    global $pdo;
    
    if (!isset($pdo)) {
        $pdo = \App\Helpers\Database::getInstance();
    }
    
    // Voltar ao ENUM original (sem 'cancelled')
    // Primeiro atualizar registros 'cancelled' para 'declined'
    $pdo->exec("UPDATE conversation_mentions SET status = 'declined' WHERE status = 'cancelled'");
    
    $sql = "ALTER TABLE conversation_mentions 
            MODIFY COLUMN status ENUM('pending', 'accepted', 'declined', 'expired') 
            DEFAULT 'pending' 
            COMMENT 'Status do convite'";
    
    try {
        $pdo->exec($sql);
        echo "✅ Status 'cancelled' removido da tabela conversation_mentions\n";
    } catch (\PDOException $e) {
        echo "⚠️ Aviso ao reverter status: " . $e->getMessage() . "\n";
    }
}

// Executar se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../../config/database.php';
    up_add_cancelled_status_to_mentions();
}

