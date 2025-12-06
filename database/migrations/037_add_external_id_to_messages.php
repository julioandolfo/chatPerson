<?php
/**
 * Migration: Adicionar campo external_id em messages para rastrear IDs externos (WhatsApp, etc)
 */

function up_add_external_id_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS external_id VARCHAR(255) NULL DEFAULT NULL AFTER id,
            ADD INDEX IF NOT EXISTS idx_external_id (external_id)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campo 'external_id' adicionado à tabela 'messages'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez (MySQL pode não suportar IF NOT EXISTS)
            try {
                $pdo->exec("ALTER TABLE messages ADD COLUMN external_id VARCHAR(255) NULL DEFAULT NULL AFTER id");
                $pdo->exec("ALTER TABLE messages ADD INDEX idx_external_id (external_id)");
                echo "✅ Campo 'external_id' adicionado à tabela 'messages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campo pode já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campo 'external_id' adicionado à tabela 'messages'!\n";
        } catch (\Exception $e) {
            // Tentar adicionar um por vez
            try {
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages ADD COLUMN external_id VARCHAR(255) NULL DEFAULT NULL AFTER id");
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages ADD INDEX idx_external_id (external_id)");
                echo "✅ Campo 'external_id' adicionado à tabela 'messages'!\n";
            } catch (\Exception $e2) {
                echo "⚠️  Campo pode já existir\n";
            }
        }
    }
}

function down_add_external_id_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages DROP COLUMN IF EXISTS external_id";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campo removido da tabela 'messages'!\n";
        } catch (\PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE messages DROP COLUMN external_id");
                echo "✅ Campo removido da tabela 'messages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Erro ao remover campo: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campo removido da tabela 'messages'!\n";
        } catch (\Exception $e) {
            try {
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages DROP COLUMN external_id");
                echo "✅ Campo removido da tabela 'messages'!\n";
            } catch (\Exception $e2) {
                echo "⚠️  Erro ao remover campo\n";
            }
        }
    }
}

