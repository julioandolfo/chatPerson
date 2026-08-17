<?php
/**
 * Migration: Criar tabelas da Análise de Conversas por Coorte
 *
 * - conversation_analysis_batches: cada execução de análise (filtros + contexto + resultado)
 * - conversation_analysis_items:   resultado por conversa dentro do lote
 */

function up_create_conversation_analysis_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $sql = "CREATE TABLE IF NOT EXISTS conversation_analysis_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NULL COMMENT 'Nome dado pelo usuário à análise',
        context_question TEXT NOT NULL COMMENT 'O que o usuário quer entender (contexto da análise)',
        filters JSON NOT NULL COMMENT 'Filtros da coorte (etapas, agentes, times, período...)',
        date_from DATE NULL,
        date_to DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|running|completed|failed|cancelled',
        total_conversations INT DEFAULT 0,
        analyzed_conversations INT DEFAULT 0,
        failed_conversations INT DEFAULT 0,
        metrics JSON NULL COMMENT 'Métricas determinísticas agregadas (fase 0)',
        summary JSON NULL COMMENT 'Síntese executiva gerada pela IA (fase 2)',
        model_map VARCHAR(50) NULL COMMENT 'Modelo usado na análise por conversa',
        model_reduce VARCHAR(50) NULL COMMENT 'Modelo usado na síntese',
        tokens_used INT DEFAULT 0,
        cost DECIMAL(10,4) DEFAULT 0 COMMENT 'Custo acumulado em USD',
        cost_limit DECIMAL(10,4) NULL COMMENT 'Teto de custo definido para este lote',
        error_message TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at TIMESTAMP NULL,
        completed_at TIMESTAMP NULL,
        INDEX idx_status_created (status, created_at),
        INDEX idx_created_by (created_by),
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "✅ Tabela 'conversation_analysis_batches' criada com sucesso!\n";

    $sql = "CREATE TABLE IF NOT EXISTS conversation_analysis_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id INT NOT NULL,
        conversation_id INT NOT NULL,
        metrics JSON NULL COMMENT 'Métricas determinísticas da conversa (fase 0)',
        analysis JSON NULL COMMENT 'Análise da IA (fase 1)',
        outcome VARCHAR(40) NULL COMMENT 'Desnormalizado para agregação',
        primary_reason VARCHAR(40) NULL COMMENT 'Motivo principal (taxonomia fechada)',
        drop_off_stage_id INT NULL COMMENT 'Etapa em que a conversa travou',
        who_stopped VARCHAR(20) NULL COMMENT 'cliente|vendedor|ia|ninguem',
        agent_id INT NULL COMMENT 'Agente humano responsável na conversa',
        confidence DECIMAL(3,2) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|processing|analyzed|skipped|failed',
        tokens_used INT DEFAULT 0,
        cost DECIMAL(10,6) DEFAULT 0,
        error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        analyzed_at TIMESTAMP NULL,
        UNIQUE KEY uk_batch_conversation (batch_id, conversation_id),
        INDEX idx_batch_status (batch_id, status),
        INDEX idx_batch_reason (batch_id, primary_reason),
        INDEX idx_batch_stage (batch_id, drop_off_stage_id),
        INDEX idx_batch_agent (batch_id, agent_id),
        FOREIGN KEY (batch_id) REFERENCES conversation_analysis_batches(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "✅ Tabela 'conversation_analysis_items' criada com sucesso!\n";
}

function down_create_conversation_analysis_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS conversation_analysis_items");
    echo "✅ Tabela 'conversation_analysis_items' removida!\n";

    $db->exec("DROP TABLE IF EXISTS conversation_analysis_batches");
    echo "✅ Tabela 'conversation_analysis_batches' removida!\n";
}
