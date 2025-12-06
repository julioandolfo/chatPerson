<?php
/**
 * Migration: Adicionar campos de agente à tabela users
 * Campos para disponibilidade, limite de conversas e estatísticas
 */

function up_add_agent_fields_to_users() {
    global $pdo;
    
    $sql = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS availability_status VARCHAR(20) DEFAULT 'offline' COMMENT 'Status: online, offline, away, busy' AFTER status,
            ADD COLUMN IF NOT EXISTS max_conversations INT NULL DEFAULT NULL COMMENT 'Limite máximo de conversas simultâneas' AFTER availability_status,
            ADD COLUMN IF NOT EXISTS current_conversations INT DEFAULT 0 COMMENT 'Número atual de conversas abertas' AFTER max_conversations,
            ADD COLUMN IF NOT EXISTS last_seen_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Última vez que o agente esteve online' AFTER current_conversations,
            ADD COLUMN IF NOT EXISTS agent_settings JSON NULL COMMENT 'Configurações específicas do agente' AFTER last_seen_at,
            ADD INDEX IF NOT EXISTS idx_availability_status (availability_status),
            ADD INDEX IF NOT EXISTS idx_max_conversations (max_conversations)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos de agente adicionados à tabela 'users'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN availability_status VARCHAR(20) DEFAULT 'offline' COMMENT 'Status: online, offline, away, busy' AFTER status");
                $pdo->exec("ALTER TABLE users ADD COLUMN max_conversations INT NULL DEFAULT NULL COMMENT 'Limite máximo de conversas simultâneas' AFTER availability_status");
                $pdo->exec("ALTER TABLE users ADD COLUMN current_conversations INT DEFAULT 0 COMMENT 'Número atual de conversas abertas' AFTER max_conversations");
                $pdo->exec("ALTER TABLE users ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Última vez que o agente esteve online' AFTER current_conversations");
                $pdo->exec("ALTER TABLE users ADD COLUMN agent_settings JSON NULL COMMENT 'Configurações específicas do agente' AFTER last_seen_at");
                $pdo->exec("ALTER TABLE users ADD INDEX idx_availability_status (availability_status)");
                $pdo->exec("ALTER TABLE users ADD INDEX idx_max_conversations (max_conversations)");
                echo "✅ Campos de agente adicionados à tabela 'users'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos de agente adicionados à tabela 'users'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campos podem já existir\n";
        }
    }
}

function down_add_agent_fields_to_users() {
    $sql = "ALTER TABLE users 
            DROP COLUMN IF EXISTS availability_status,
            DROP COLUMN IF EXISTS max_conversations,
            DROP COLUMN IF EXISTS current_conversations,
            DROP COLUMN IF EXISTS last_seen_at,
            DROP COLUMN IF EXISTS agent_settings";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Campos de agente removidos da tabela 'users'!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover campos: " . $e->getMessage() . "\n";
    }
}

