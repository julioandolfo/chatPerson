<?php
/**
 * Migration: Adicionar campos read_at e status em messages
 */

function up_add_read_at_to_messages() {
    global $pdo;
    
    $sql = "ALTER TABLE messages 
            ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL DEFAULT NULL AFTER created_at,
            ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'sent' AFTER message_type,
            ADD INDEX IF NOT EXISTS idx_read_at (read_at),
            ADD INDEX IF NOT EXISTS idx_status (status)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos 'read_at' e 'status' adicionados à tabela 'messages'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez (MySQL pode não suportar IF NOT EXISTS)
            try {
                $pdo->exec("ALTER TABLE messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL AFTER created_at");
                $pdo->exec("ALTER TABLE messages ADD COLUMN status VARCHAR(20) DEFAULT 'sent' AFTER message_type");
                $pdo->exec("ALTER TABLE messages ADD INDEX idx_read_at (read_at)");
                $pdo->exec("ALTER TABLE messages ADD INDEX idx_status (status)");
                echo "✅ Campos 'read_at' e 'status' adicionados à tabela 'messages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos 'read_at' e 'status' adicionados à tabela 'messages'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campos podem já existir\n";
        }
    }
}

function down_add_read_at_to_messages() {
    $sql = "ALTER TABLE messages 
            DROP COLUMN IF EXISTS read_at,
            DROP COLUMN IF EXISTS status";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Campos removidos da tabela 'messages'!\n";
}

