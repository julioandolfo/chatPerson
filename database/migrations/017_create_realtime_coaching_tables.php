<?php
/**
 * Migration: Criar tabelas para Coaching em Tempo Real
 */

function up_create_realtime_coaching_tables() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS realtime_coaching_hints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        message_id INT DEFAULT NULL,
        hint_type VARCHAR(50) NOT NULL,
        hint_text TEXT NOT NULL,
        suggestions JSON DEFAULT NULL,
        model_used VARCHAR(50) DEFAULT NULL,
        tokens_used INT DEFAULT 0,
        cost DECIMAL(10,6) DEFAULT 0,
        viewed_at TIMESTAMP NULL DEFAULT NULL,
        feedback VARCHAR(20) DEFAULT NULL COMMENT 'helpful, not_helpful',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
        INDEX idx_conversation (conversation_id),
        INDEX idx_agent (agent_id),
        INDEX idx_message (message_id),
        INDEX idx_created_at (created_at),
        INDEX idx_hint_type (hint_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'realtime_coaching_hints' criada com sucesso!\n";
    
    // Tabela de cache para evitar análises duplicadas (não é mais usada - cache em memória)
    // Mantida para compatibilidade futura
    $sql2 = "CREATE TABLE IF NOT EXISTS realtime_coaching_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_hash VARCHAR(64) NOT NULL UNIQUE,
        hint_type VARCHAR(50) NOT NULL,
        hint_text TEXT NOT NULL,
        suggestions JSON DEFAULT NULL,
        model_used VARCHAR(50) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        INDEX idx_hash (message_hash),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql2);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql2);
    }
    
    echo "✅ Tabela 'realtime_coaching_cache' criada com sucesso!\n";
}

function down_create_realtime_coaching_tables() {
    global $pdo;
    
    $sql1 = "DROP TABLE IF EXISTS realtime_coaching_hints";
    $sql2 = "DROP TABLE IF EXISTS realtime_coaching_cache";
    
    if (isset($pdo)) {
        $pdo->exec($sql1);
        $pdo->exec($sql2);
    } else {
        $db = \App\Helpers\Database::getInstance();
        $db->exec($sql1);
        $db->exec($sql2);
    }
    
    echo "✅ Tabelas de coaching em tempo real removidas!\n";
}
