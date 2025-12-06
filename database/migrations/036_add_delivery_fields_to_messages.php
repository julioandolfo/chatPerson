<?php
/**
 * Migration: Adicionar campos delivered_at e error_message em messages
 */

function up_add_delivery_fields_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL DEFAULT NULL AFTER read_at,
            ADD COLUMN IF NOT EXISTS error_message TEXT NULL DEFAULT NULL AFTER status,
            ADD INDEX IF NOT EXISTS idx_delivered_at (delivered_at)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos 'delivered_at' e 'error_message' adicionados à tabela 'messages'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez (MySQL pode não suportar IF NOT EXISTS)
            try {
                $pdo->exec("ALTER TABLE messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER read_at");
                $pdo->exec("ALTER TABLE messages ADD COLUMN error_message TEXT NULL DEFAULT NULL AFTER status");
                $pdo->exec("ALTER TABLE messages ADD INDEX idx_delivered_at (delivered_at)");
                echo "✅ Campos 'delivered_at' e 'error_message' adicionados à tabela 'messages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos 'delivered_at' e 'error_message' adicionados à tabela 'messages'!\n";
        } catch (\Exception $e) {
            // Tentar adicionar um por vez
            try {
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL AFTER read_at");
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages ADD COLUMN error_message TEXT NULL DEFAULT NULL AFTER status");
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages ADD INDEX idx_delivered_at (delivered_at)");
                echo "✅ Campos 'delivered_at' e 'error_message' adicionados à tabela 'messages'!\n";
            } catch (\Exception $e2) {
                echo "⚠️  Campos podem já existir\n";
            }
        }
    }
}

function down_add_delivery_fields_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            DROP COLUMN IF EXISTS delivered_at,
            DROP COLUMN IF EXISTS error_message";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos removidos da tabela 'messages'!\n";
        } catch (\PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE messages DROP COLUMN delivered_at");
                $pdo->exec("ALTER TABLE messages DROP COLUMN error_message");
                echo "✅ Campos removidos da tabela 'messages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Erro ao remover campos: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos removidos da tabela 'messages'!\n";
        } catch (\Exception $e) {
            try {
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages DROP COLUMN delivered_at");
                \App\Helpers\Database::getInstance()->exec("ALTER TABLE messages DROP COLUMN error_message");
                echo "✅ Campos removidos da tabela 'messages'!\n";
            } catch (\Exception $e2) {
                echo "⚠️  Erro ao remover campos\n";
            }
        }
    }
}

