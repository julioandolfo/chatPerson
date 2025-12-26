<?php
/**
 * Migration: Criar tabela ai_fallback_metrics
 */

function up_ai_fallback_metrics_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_fallback_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL COMMENT 'ID da conversa',
        action VARCHAR(50) NOT NULL COMMENT 'Ação: reprocessed, escalated, ignored_closing, ignored',
        reason TEXT NULL COMMENT 'Motivo da ação',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'ai_fallback_metrics' criada com sucesso!\n";
}

function down_ai_fallback_metrics_table() {
    $sql = "DROP TABLE IF EXISTS ai_fallback_metrics";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'ai_fallback_metrics' removida!\n";
}

