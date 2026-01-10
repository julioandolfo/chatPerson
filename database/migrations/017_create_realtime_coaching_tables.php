<?php
/**
 * Migration: Criar tabelas para Coaching em Tempo Real
 */

function up_realtime_coaching() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS realtime_coaching_hints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        client_message TEXT NOT NULL,
        hint_type VARCHAR(50) NOT NULL,
        hint_title VARCHAR(255) NOT NULL,
        hint_message TEXT NOT NULL,
        suggestions JSON DEFAULT NULL,
        context_summary TEXT DEFAULT NULL,
        model_used VARCHAR(50) DEFAULT NULL,
        tokens_used INT DEFAULT 0,
        cost DECIMAL(10,6) DEFAULT 0,
        shown_at TIMESTAMP NULL DEFAULT NULL,
        dismissed_at TIMESTAMP NULL DEFAULT NULL,
        used_suggestion TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation (conversation_id),
        INDEX idx_agent (agent_id),
        INDEX idx_created_at (created_at),
        INDEX idx_hint_type (hint_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'realtime_coaching_hints' criada com sucesso!\n";
    
    // Tabela de cache para evitar análises duplicadas
    $sql2 = "CREATE TABLE IF NOT EXISTS realtime_coaching_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_hash VARCHAR(64) NOT NULL UNIQUE,
        hint_type VARCHAR(50) NOT NULL,
        hint_title VARCHAR(255) NOT NULL,
        hint_message TEXT NOT NULL,
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

function down_realtime_coaching() {
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
