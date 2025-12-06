<?php
/**
 * Migration: Adicionar campos de mensagem citada (reply) à tabela messages
 */

function up_add_quoted_message_fields_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS quoted_message_id INT NULL,
            ADD COLUMN IF NOT EXISTS quoted_sender_name VARCHAR(255) NULL,
            ADD COLUMN IF NOT EXISTS quoted_text TEXT NULL,
            ADD INDEX IF NOT EXISTS idx_quoted_message_id (quoted_message_id),
            ADD FOREIGN KEY IF NOT EXISTS fk_quoted_message (quoted_message_id) REFERENCES messages(id) ON DELETE SET NULL";
    
    try {
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Campos de mensagem citada adicionados à tabela 'messages'!\n";
    } catch (\Exception $e) {
        // Tentar sem IF NOT EXISTS (MySQL antigo)
        $sql = "ALTER TABLE messages 
                ADD COLUMN quoted_message_id INT NULL,
                ADD COLUMN quoted_sender_name VARCHAR(255) NULL,
                ADD COLUMN quoted_text TEXT NULL";
        
        try {
            if (isset($pdo)) {
                $pdo->exec($sql);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql);
            }
            
            // Adicionar índices separadamente
            try {
                $indexSql = "CREATE INDEX idx_quoted_message_id ON messages(quoted_message_id)";
                if (isset($pdo)) {
                    $pdo->exec($indexSql);
                } else {
                    \App\Helpers\Database::getInstance()->exec($indexSql);
                }
            } catch (\Exception $e2) {
                // Índice pode já existir
            }
            
            // Adicionar foreign key separadamente
            try {
                $fkSql = "ALTER TABLE messages ADD CONSTRAINT fk_quoted_message FOREIGN KEY (quoted_message_id) REFERENCES messages(id) ON DELETE SET NULL";
                if (isset($pdo)) {
                    $pdo->exec($fkSql);
                } else {
                    \App\Helpers\Database::getInstance()->exec($fkSql);
                }
            } catch (\Exception $e3) {
                // Foreign key pode já existir
            }
            
            echo "✅ Campos de mensagem citada adicionados à tabela 'messages'!\n";
        } catch (\Exception $e2) {
            echo "⚠️ Erro ao adicionar campos (podem já existir): " . $e2->getMessage() . "\n";
        }
    }
}

function down_add_quoted_message_fields_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            DROP FOREIGN KEY IF EXISTS fk_quoted_message,
            DROP INDEX IF EXISTS idx_quoted_message_id,
            DROP COLUMN IF EXISTS quoted_message_id,
            DROP COLUMN IF EXISTS quoted_sender_name,
            DROP COLUMN IF EXISTS quoted_text";
    
    try {
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Campos de mensagem citada removidos da tabela 'messages'!\n";
    } catch (\Exception $e) {
        // Tentar sem IF EXISTS
        try {
            $sql1 = "ALTER TABLE messages DROP FOREIGN KEY fk_quoted_message";
            if (isset($pdo)) {
                $pdo->exec($sql1);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql1);
            }
        } catch (\Exception $e1) {
            // Ignorar
        }
        
        try {
            $sql2 = "ALTER TABLE messages DROP INDEX idx_quoted_message_id";
            if (isset($pdo)) {
                $pdo->exec($sql2);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql2);
            }
        } catch (\Exception $e2) {
            // Ignorar
        }
        
        try {
            $sql3 = "ALTER TABLE messages DROP COLUMN quoted_message_id, DROP COLUMN quoted_sender_name, DROP COLUMN quoted_text";
            if (isset($pdo)) {
                $pdo->exec($sql3);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql3);
            }
            echo "✅ Campos de mensagem citada removidos da tabela 'messages'!\n";
        } catch (\Exception $e3) {
            echo "⚠️ Erro ao remover campos: " . $e3->getMessage() . "\n";
        }
    }
}

