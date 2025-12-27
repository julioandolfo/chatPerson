<?php
/**
 * Migration: Adicionar campos de rastreamento de disponibilidade
 * Campos para atividade, sessão e histórico de status
 */

function up_add_availability_tracking_fields() {
    global $pdo;
    
    $sql = "ALTER TABLE users 
            ADD COLUMN IF NOT EXISTS last_activity_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Última atividade registrada do usuário' AFTER last_seen_at,
            ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) NULL DEFAULT NULL COMMENT 'ID da sessão atual (para rastrear múltiplas abas)' AFTER last_activity_at,
            ADD INDEX IF NOT EXISTS idx_last_activity_at (last_activity_at),
            ADD INDEX IF NOT EXISTS idx_session_id (session_id)";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos de rastreamento de disponibilidade adicionados à tabela 'users'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN last_activity_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Última atividade registrada do usuário' AFTER last_seen_at");
                $pdo->exec("ALTER TABLE users ADD COLUMN session_id VARCHAR(255) NULL DEFAULT NULL COMMENT 'ID da sessão atual (para rastrear múltiplas abas)' AFTER last_activity_at");
                $pdo->exec("ALTER TABLE users ADD INDEX idx_last_activity_at (last_activity_at)");
                $pdo->exec("ALTER TABLE users ADD INDEX idx_session_id (session_id)");
                echo "✅ Campos de rastreamento de disponibilidade adicionados à tabela 'users'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos de rastreamento de disponibilidade adicionados à tabela 'users'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campos podem já existir\n";
        }
    }
}

function down_add_availability_tracking_fields() {
    $sql = "ALTER TABLE users 
            DROP COLUMN IF EXISTS last_activity_at,
            DROP COLUMN IF EXISTS session_id";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Campos de rastreamento de disponibilidade removidos da tabela 'users'!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover campos: " . $e->getMessage() . "\n";
    }
}

