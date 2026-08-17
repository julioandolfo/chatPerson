<?php
/**
 * Migration: Garantir e enriquecer 'funnel_stage_history'
 *
 * A tabela foi originalmente declarada na migration 091, mas naquele arquivo o
 * corpo está em uma classe anônima (return new class), enquanto o runner
 * (database/run_migrations.php) só executa funções up_*. Ou seja: em muitas
 * instalações a tabela simplesmente não existe.
 *
 * Além disso, nenhum ponto do código gravava nela — o histórico de etapas era
 * lido (FunnelService::getConversationDetails, ContactSegmentationService) mas
 * nunca escrito. Esta migration prepara a tabela para virar a fonte de verdade
 * de "a conversa passou pela etapa X".
 */

function up_enhance_funnel_stage_history() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    // 1) Garantir que a tabela existe (idempotente)
    $sql = "CREATE TABLE IF NOT EXISTS funnel_stage_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        from_stage_id INT NULL,
        to_stage_id INT NOT NULL,
        changed_by INT NULL COMMENT 'Usuário humano que moveu (NULL = sistema/IA)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (conversation_id),
        INDEX idx_from_stage (from_stage_id),
        INDEX idx_to_stage (to_stage_id),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (from_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
        FOREIGN KEY (to_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "✅ Tabela 'funnel_stage_history' garantida!\n";

    // 2) Colunas adicionais (uma a uma, para funcionar em MySQL sem IF NOT EXISTS)
    $columns = [
        'funnel_id' => "ADD COLUMN funnel_id INT NULL COMMENT 'Funil de destino' AFTER conversation_id",
        'changed_by_ai_agent_id' => "ADD COLUMN changed_by_ai_agent_id INT NULL COMMENT 'Agente de IA que moveu (kanban/tool)'",
        'source' => "ADD COLUMN source VARCHAR(30) NULL COMMENT 'manual|automation|kanban_agent|ai_tool|api|system|backfill'",
    ];

    foreach ($columns as $column => $clause) {
        if (columnExistsFsh($db, $column)) {
            echo "ℹ️  Coluna '{$column}' já existe\n";
            continue;
        }

        try {
            $db->exec("ALTER TABLE funnel_stage_history {$clause}");
            echo "✅ Coluna '{$column}' adicionada\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao adicionar '{$column}': " . $e->getMessage() . "\n";
        }
    }

    // 3) Índices de consulta da coorte ("passou pela etapa X no período Y")
    $indexes = [
        'idx_stage_date' => "ADD INDEX idx_stage_date (to_stage_id, created_at)",
        'idx_conv_date' => "ADD INDEX idx_conv_date (conversation_id, created_at)",
        'idx_funnel_date' => "ADD INDEX idx_funnel_date (funnel_id, created_at)",
    ];

    foreach ($indexes as $index => $clause) {
        if (indexExistsFsh($db, $index)) {
            echo "ℹ️  Índice '{$index}' já existe\n";
            continue;
        }

        try {
            $db->exec("ALTER TABLE funnel_stage_history {$clause}");
            echo "✅ Índice '{$index}' criado\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao criar índice '{$index}': " . $e->getMessage() . "\n";
        }
    }

    echo "\n👉 Próximo passo: rodar o backfill do histórico:\n";
    echo "   php database/scripts/backfill_funnel_stage_history.php\n";
}

function columnExistsFsh($db, string $column): bool {
    $stmt = $db->query("SHOW COLUMNS FROM funnel_stage_history LIKE " . $db->quote($column));
    return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
}

function indexExistsFsh($db, string $index): bool {
    $stmt = $db->query("SHOW INDEX FROM funnel_stage_history WHERE Key_name = " . $db->quote($index));
    return (bool)$stmt->fetch(\PDO::FETCH_ASSOC);
}

function down_enhance_funnel_stage_history() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    foreach (['idx_stage_date', 'idx_conv_date', 'idx_funnel_date'] as $index) {
        try {
            $db->exec("ALTER TABLE funnel_stage_history DROP INDEX {$index}");
        } catch (\PDOException $e) {
            // índice pode não existir
        }
    }

    foreach (['funnel_id', 'changed_by_ai_agent_id', 'source'] as $column) {
        try {
            $db->exec("ALTER TABLE funnel_stage_history DROP COLUMN {$column}");
        } catch (\PDOException $e) {
            // coluna pode não existir
        }
    }

    echo "✅ Colunas e índices adicionais removidos de 'funnel_stage_history'!\n";
}
