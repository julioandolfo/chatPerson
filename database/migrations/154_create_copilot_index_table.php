<?php
/**
 * Migration: índice do Copiloto de Atendimento (RAG sobre conversas resolvidas).
 *
 * Guarda um resumo estruturado + embedding de cada conversa resolvida/fechada,
 * para busca semântica de casos parecidos.
 */

function up_create_copilot_index_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $sql = "CREATE TABLE IF NOT EXISTS copilot_conversation_index (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NULL,
        category VARCHAR(80) NULL,
        problem TEXT NULL COMMENT 'Problema/gatilho do cliente',
        resolution TEXT NULL COMMENT 'Como foi resolvido',
        summary TEXT NULL COMMENT 'Resumo curto da conversa',
        embedding MEDIUMTEXT NULL COMMENT 'Vetor 1536 (JSON)',
        tokens INT DEFAULT 0,
        cost DECIMAL(12,6) DEFAULT 0,
        resolved_at DATETIME NULL,
        indexed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_conversation (conversation_id),
        INDEX idx_category (category),
        INDEX idx_resolved_at (resolved_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    try {
        $db->exec($sql);
        echo "✅ Tabela 'copilot_conversation_index' criada.\n";
    } catch (\Throwable $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
}

function down_create_copilot_index_table() {
    try {
        \App\Helpers\Database::getInstance()->exec("DROP TABLE IF EXISTS copilot_conversation_index");
        echo "✅ Tabela 'copilot_conversation_index' removida.\n";
    } catch (\Throwable $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
}
