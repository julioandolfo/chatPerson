#!/usr/bin/env php
<?php
/**
 * Backfill de 'funnel_stage_history'
 *
 * A tabela nunca foi populada. O único rastro histórico de movimentação de
 * etapa existente hoje está em 'activities' (activity_type = 'stage_moved'),
 * gravado por ActivityService::logStageMoved a partir de
 * FunnelService::moveConversation.
 *
 * Este script reconstrói o histórico em duas passadas:
 *   1) activities.stage_moved  -> uma linha por transição registrada
 *   2) conversas sem NENHUMA linha -> uma linha sintética com o estado atual
 *      (para que "passou pela etapa X" ao menos enxergue onde a conversa está)
 *
 * ⚠️  Movimentações feitas pelo KanbanAgentService antes da correção não
 *     existem em lugar nenhum e portanto não são recuperáveis.
 *
 * USO: php database/scripts/backfill_funnel_stage_history.php [--dry-run] [--limit=N]
 */

$rootDir = dirname(__DIR__, 2);
chdir($rootDir);
require_once $rootDir . '/config/bootstrap.php';

use App\Helpers\Database;

$dryRun = in_array('--dry-run', $argv);
$limit = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

echo "========================================\n";
echo "   BACKFILL funnel_stage_history\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "🔵 MODO DRY-RUN: nada será gravado\n\n";
}

// Confere se a tabela existe antes de qualquer coisa
$exists = Database::fetch("SHOW TABLES LIKE 'funnel_stage_history'");
if (empty($exists)) {
    echo "❌ Tabela 'funnel_stage_history' não existe.\n";
    echo "   Rode antes: php database/run_migrations.php 158\n";
    exit(1);
}

$hasSourceColumn = !empty(Database::fetch("SHOW COLUMNS FROM funnel_stage_history LIKE 'source'"));
$hasFunnelColumn = !empty(Database::fetch("SHOW COLUMNS FROM funnel_stage_history LIKE 'funnel_id'"));

if (!$hasSourceColumn || !$hasFunnelColumn) {
    echo "⚠️  Colunas 'source'/'funnel_id' ausentes — rode a migration 158 primeiro.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// PASSADA 1: activities.stage_moved
// ---------------------------------------------------------------------------
echo "▶ Passada 1: reconstruindo a partir de 'activities'...\n";

$sql = "SELECT a.entity_id AS conversation_id,
               a.user_id AS changed_by,
               a.created_at,
               JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.old_stage_id')) AS from_stage_id,
               JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.new_stage_id')) AS to_stage_id,
               JSON_UNQUOTE(JSON_EXTRACT(a.metadata, '$.funnel_id')) AS funnel_id
        FROM activities a
        INNER JOIN conversations c ON c.id = a.entity_id
        WHERE a.activity_type = 'stage_moved'
          AND a.entity_type = 'conversation'
        ORDER BY a.created_at ASC";

if ($limit) {
    $sql .= " LIMIT " . (int)$limit;
}

$activities = Database::fetchAll($sql);
echo "  Encontradas " . count($activities) . " movimentações em 'activities'\n";

$inserted = 0;
$skipped = 0;

foreach ($activities as $row) {
    $toStageId = $row['to_stage_id'] !== null && $row['to_stage_id'] !== 'null' ? (int)$row['to_stage_id'] : 0;
    if ($toStageId <= 0) {
        $skipped++;
        continue;
    }

    // Etapa pode ter sido deletada depois — a FK exigiria que ainda exista
    $stage = Database::fetch("SELECT id, funnel_id FROM funnel_stages WHERE id = ?", [$toStageId]);
    if (!$stage) {
        $skipped++;
        continue;
    }

    $fromStageId = ($row['from_stage_id'] !== null && $row['from_stage_id'] !== 'null')
        ? (int)$row['from_stage_id']
        : null;

    if ($fromStageId) {
        $fromExists = Database::fetch("SELECT id FROM funnel_stages WHERE id = ?", [$fromStageId]);
        if (!$fromExists) {
            $fromStageId = null;
        }
    }

    // Idempotência: mesma conversa + mesma etapa + mesmo instante já importados
    $dup = Database::fetch(
        "SELECT id FROM funnel_stage_history
         WHERE conversation_id = ? AND to_stage_id = ? AND created_at = ?",
        [(int)$row['conversation_id'], $toStageId, $row['created_at']]
    );

    if ($dup) {
        $skipped++;
        continue;
    }

    if (!$dryRun) {
        Database::insert(
            "INSERT INTO funnel_stage_history
                (conversation_id, funnel_id, from_stage_id, to_stage_id, changed_by, source, created_at)
             VALUES (?, ?, ?, ?, ?, 'backfill', ?)",
            [
                (int)$row['conversation_id'],
                (int)$stage['funnel_id'],
                $fromStageId,
                $toStageId,
                $row['changed_by'] ? (int)$row['changed_by'] : null,
                $row['created_at'],
            ]
        );
    }

    $inserted++;
}

echo "  ✅ {$inserted} linhas inseridas, {$skipped} ignoradas\n\n";

// ---------------------------------------------------------------------------
// PASSADA 2: estado atual das conversas sem histórico
// ---------------------------------------------------------------------------
echo "▶ Passada 2: estado atual das conversas sem histórico...\n";

$sql = "SELECT c.id, c.funnel_id, c.funnel_stage_id,
               COALESCE(c.moved_at, c.created_at) AS event_at
        FROM conversations c
        INNER JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
        WHERE c.funnel_stage_id IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM funnel_stage_history h WHERE h.conversation_id = c.id
          )";

if ($limit) {
    $sql .= " LIMIT " . (int)$limit;
}

$conversations = Database::fetchAll($sql);
echo "  Encontradas " . count($conversations) . " conversas sem histórico\n";

$synthetic = 0;

foreach ($conversations as $conversation) {
    if (!$dryRun) {
        Database::insert(
            "INSERT INTO funnel_stage_history
                (conversation_id, funnel_id, from_stage_id, to_stage_id, changed_by, source, created_at)
             VALUES (?, ?, NULL, ?, NULL, 'backfill', ?)",
            [
                (int)$conversation['id'],
                $conversation['funnel_id'] ? (int)$conversation['funnel_id'] : null,
                (int)$conversation['funnel_stage_id'],
                $conversation['event_at'],
            ]
        );
    }

    $synthetic++;
}

echo "  ✅ {$synthetic} linhas sintéticas inseridas\n\n";

// ---------------------------------------------------------------------------
// PASSADA 3: conversas COM histórico mas cuja etapa atual não é a última
// (movimentações perdidas do KanbanAgentService/IA antes da correção)
// ---------------------------------------------------------------------------
echo "▶ Passada 3: reconciliando etapa atual com a última do histórico...\n";

$sql = "SELECT * FROM (
            SELECT c.id, c.funnel_id, c.funnel_stage_id,
                   COALESCE(c.moved_at, c.updated_at, c.created_at) AS event_at,
                   (SELECT h.to_stage_id FROM funnel_stage_history h
                     WHERE h.conversation_id = c.id
                     ORDER BY h.created_at DESC, h.id DESC LIMIT 1) AS last_stage_id
            FROM conversations c
            INNER JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
            WHERE c.funnel_stage_id IS NOT NULL
              AND EXISTS (SELECT 1 FROM funnel_stage_history h2 WHERE h2.conversation_id = c.id)
        ) t
        WHERE t.last_stage_id IS NOT NULL AND t.last_stage_id <> t.funnel_stage_id";

if ($limit) {
    $sql .= " LIMIT " . (int)$limit;
}

$diverged = Database::fetchAll($sql);
echo "  Encontradas " . count($diverged) . " conversas com etapa divergente\n";

$reconciled = 0;

foreach ($diverged as $conversation) {
    if (!$dryRun) {
        Database::insert(
            "INSERT INTO funnel_stage_history
                (conversation_id, funnel_id, from_stage_id, to_stage_id, changed_by, source, created_at)
             VALUES (?, ?, ?, ?, NULL, 'backfill', ?)",
            [
                (int)$conversation['id'],
                $conversation['funnel_id'] ? (int)$conversation['funnel_id'] : null,
                (int)$conversation['last_stage_id'],
                (int)$conversation['funnel_stage_id'],
                $conversation['event_at'],
            ]
        );
    }

    $reconciled++;
}

echo "  ✅ {$reconciled} linhas de reconciliação inseridas\n\n";

echo "========================================\n";
echo "   RESUMO\n";
echo "========================================\n";
echo "  De 'activities':   {$inserted}\n";
echo "  Sintéticas:        {$synthetic}\n";
echo "  Reconciliação:     {$reconciled}\n";
echo "  Total:             " . ($inserted + $synthetic + $reconciled) . "\n";

if ($dryRun) {
    echo "\n🔵 DRY-RUN: nada foi gravado. Rode sem --dry-run para aplicar.\n";
}
