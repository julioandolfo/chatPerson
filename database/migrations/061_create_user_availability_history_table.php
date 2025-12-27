<?php
/**
 * Migration: Criar tabela de histórico de disponibilidade dos usuários
 * Rastreia mudanças de status e calcula tempo em cada status
 */

function up_create_user_availability_history_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS user_availability_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'ID do usuário/agente',
        status VARCHAR(20) NOT NULL COMMENT 'Status: online, offline, away, busy',
        started_at TIMESTAMP NOT NULL COMMENT 'Quando o status começou',
        ended_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Quando o status terminou (NULL se ainda está ativo)',
        duration_seconds INT NULL DEFAULT NULL COMMENT 'Duração em segundos (calculado quando ended_at é preenchido)',
        is_business_hours BOOLEAN DEFAULT TRUE COMMENT 'Se estava em horário comercial',
        metadata JSON NULL COMMENT 'Dados adicionais (motivo da mudança, etc)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_started_at (started_at),
        INDEX idx_ended_at (ended_at),
        INDEX idx_user_status (user_id, status),
        INDEX idx_user_period (user_id, started_at, ended_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de mudanças de status de disponibilidade'";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'user_availability_history' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'user_availability_history' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_create_user_availability_history_table() {
    $sql = "DROP TABLE IF EXISTS user_availability_history";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'user_availability_history' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela: " . $e->getMessage() . "\n";
    }
}

