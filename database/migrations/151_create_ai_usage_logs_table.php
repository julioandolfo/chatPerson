<?php
/**
 * Migration: Criar tabela ai_usage_logs
 * Tabela unificada de consumo de IA (tokens/custo) para recursos que antes
 * não eram rastreados: embeddings, TTS, transcrição de áudio de mensagens,
 * extração de memória, visão/DALL·E e agentes Kanban.
 */

function up_create_ai_usage_logs_table() {
    global $pdo;

    $sql = "CREATE TABLE IF NOT EXISTS ai_usage_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature VARCHAR(80) NOT NULL COMMENT 'Recurso de IA (embedding, tts, audio_transcription, agent_memory, mockup_generation, kanban_agent, ...)',
        provider VARCHAR(40) NOT NULL DEFAULT 'openai' COMMENT 'Provedor (openai, elevenlabs, ...)',
        model VARCHAR(80) NULL COMMENT 'Modelo utilizado',
        tokens_used INT DEFAULT 0 COMMENT 'Total de tokens utilizados',
        prompt_tokens INT DEFAULT 0 COMMENT 'Tokens de entrada',
        completion_tokens INT DEFAULT 0 COMMENT 'Tokens de saída',
        cost DECIMAL(12,6) DEFAULT 0 COMMENT 'Custo estimado em USD',
        conversation_id INT NULL COMMENT 'Conversa relacionada (se houver)',
        user_id INT NULL COMMENT 'Usuário relacionado (se houver)',
        metadata JSON NULL COMMENT 'Dados extras (chars, duração, breakdown, etc)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_feature (feature),
        INDEX idx_provider (provider),
        INDEX idx_created_at (created_at),
        INDEX idx_conversation_id (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_usage_logs' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_usage_logs' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_usage_logs' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_usage_logs' pode já existir\n";
        }
    }
}

function down_create_ai_usage_logs_table() {
    $sql = "DROP TABLE IF EXISTS ai_usage_logs";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'ai_usage_logs' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_usage_logs': " . $e->getMessage() . "\n";
    }
}
