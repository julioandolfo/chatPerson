<?php
/**
 * Migration: Gerador de Manuais a partir de conversas (CS/Pós-venda)
 *
 * - manual_jobs:        cada execução de geração (agente, período, status, custo)
 * - manual_extracts:    saída do MAP (1 por conversa) com JSON estruturado
 * - generated_manuals:  o manual final (markdown) + divergências detectadas
 */

function up_create_manual_generator_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $statements = [];

    $statements[] = "CREATE TABLE IF NOT EXISTS manual_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        agent_id INT NULL COMMENT 'Agente de origem das conversas (NULL = todos)',
        date_from DATE NOT NULL,
        date_to DATE NOT NULL,
        conversation_limit INT DEFAULT 30,
        status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending|mapping|clustering|reducing|done|failed',
        total_conversations INT DEFAULT 0,
        processed_conversations INT DEFAULT 0,
        model_map VARCHAR(60) DEFAULT 'gpt-4o-mini',
        model_reduce VARCHAR(60) DEFAULT 'gpt-4o',
        tokens_used INT DEFAULT 0,
        cost DECIMAL(12,6) DEFAULT 0,
        error_message TEXT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_agent (agent_id),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $statements[] = "CREATE TABLE IF NOT EXISTS manual_extracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        conversation_id INT NOT NULL,
        extract_json JSON NULL COMMENT 'Unidades de conhecimento extraidas da conversa',
        tokens INT DEFAULT 0,
        cost DECIMAL(12,6) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_job (job_id),
        INDEX idx_conversation (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $statements[] = "CREATE TABLE IF NOT EXISTS generated_manuals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        title VARCHAR(200) NOT NULL,
        content_markdown MEDIUMTEXT NULL,
        divergences_json JSON NULL COMMENT 'Contradicoes de atendimento detectadas',
        status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft|published',
        version INT DEFAULT 1,
        published_to_rag_agent_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_job (job_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($statements as $sql) {
        try {
            $db->exec($sql);
            echo "✅ Tabela criada/ok.\n";
        } catch (\Exception $e) {
            echo "⚠️  " . $e->getMessage() . "\n";
        }
    }
    echo "✅ Migration manual_generator concluída!\n";
}

function down_create_manual_generator_tables() {
    $db = \App\Helpers\Database::getInstance();
    foreach (['generated_manuals', 'manual_extracts', 'manual_jobs'] as $t) {
        try { $db->exec("DROP TABLE IF EXISTS {$t}"); echo "✅ {$t} removida\n"; }
        catch (\Exception $e) { echo "⚠️  {$e->getMessage()}\n"; }
    }
}
