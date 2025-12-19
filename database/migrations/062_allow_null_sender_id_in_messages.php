<?php
/**
 * Migration: Permitir sender_id NULL em messages
 * Para mensagens de sistema/automação que não têm um usuário específico
 */

function up_allow_null_sender_id_in_messages() {
    global $pdo;
    
    echo "🔧 Alterando coluna sender_id na tabela messages...\n";
    
    try {
        // Verificar estrutura atual
        $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'sender_id'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($column) {
            echo "  Estrutura atual: " . $column['Type'] . " " . $column['Null'] . " " . $column['Key'] . "\n";
            
            if ($column['Null'] === 'NO') {
                echo "  → Alterando para permitir NULL...\n";
                $pdo->exec("
                    ALTER TABLE messages 
                    MODIFY COLUMN sender_id INT NULL
                ");
                echo "  ✅ Coluna sender_id agora permite NULL!\n";
            } else {
                echo "  ✅ Coluna sender_id já permite NULL\n";
            }
        } else {
            echo "  ⚠️ Coluna sender_id não encontrada\n";
        }
        
    } catch (\Exception $e) {
        echo "  ❌ ERRO: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    echo "✅ Migration 062 concluída!\n\n";
}

function down_allow_null_sender_id_in_messages() {
    global $pdo;
    
    echo "🔧 Revertendo migration 062...\n";
    echo "⚠️ ATENÇÃO: Isso pode falhar se existirem registros com sender_id NULL\n";
    
    try {
        // Atualizar registros NULL para 0 (sistema)
        $pdo->exec("UPDATE messages SET sender_id = 0 WHERE sender_id IS NULL");
        
        $pdo->exec("
            ALTER TABLE messages 
            MODIFY COLUMN sender_id INT NOT NULL
        ");
        echo "✅ Rollback 062 concluído!\n";
    } catch (\Exception $e) {
        echo "❌ ERRO no rollback: " . $e->getMessage() . "\n";
        echo "⚠️ Pode haver mensagens com sender_id NULL que impedem o rollback\n";
    }
    
    echo "\n";
}

