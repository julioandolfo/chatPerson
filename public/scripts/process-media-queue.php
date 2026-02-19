#!/usr/bin/env php
<?php

/**
 * Processar Fila de Download/Upload de Mídia (CRON)
 * 
 * Processa UM item por vez para respeitar os limites do WhatsApp CDN.
 * 
 * Coolify (a cada minuto):
 * sh -lc 'flock -n /tmp/process_media_queue.lock php public/scripts/process-media-queue.php >> /var/www/html/storage/logs/process-media-queue.log 2>&1 || true'
 * 
 * Loop contínuo:
 * php public/scripts/process-media-queue.php --loop
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\MediaQueueService;
use App\Models\MediaQueue;
use App\Helpers\Logger;

$isLoop = in_array('--loop', $argv ?? []);
$maxLoopTime = 300;

Logger::mediaQueue("═══ CRON INICIADO ═══ PID=" . getmypid() . " mode=" . ($isLoop ? 'loop' : 'single') . " host=" . gethostname());

try {
    $db = \App\Helpers\Database::getInstance();
    $tables = $db->query("SHOW TABLES LIKE 'media_queue'")->fetchAll();
    if (empty($tables)) {
        Logger::mediaQueue("Tabela media_queue não existe. Nada a processar.", 'WARNING');
        exit(0);
    }

    $queueStats = $db->query("
        SELECT status, COUNT(*) as total 
        FROM media_queue 
        GROUP BY status
    ")->fetchAll(\PDO::FETCH_KEY_PAIR);

    $queued = $queueStats['queued'] ?? 0;
    $processing = $queueStats['processing'] ?? 0;
    $failed = $queueStats['failed'] ?? 0;
    $completed = $queueStats['completed'] ?? 0;

    Logger::mediaQueue("Status da fila: queued={$queued} | processing={$processing} | failed={$failed} | completed={$completed}");

    if ($queued === 0 && $processing === 0) {
        Logger::mediaQueue("Fila vazia, nada a processar.");
        exit(0);
    }

    $nextItem = $db->query("
        SELECT id, direction, media_type, status, attempts, max_attempts, 
               external_message_id, next_attempt_at, error_message,
               message_id, conversation_id
        FROM media_queue 
        WHERE status IN ('queued', 'processing') 
          AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
        ORDER BY priority ASC, id ASC 
        LIMIT 1
    ")->fetch(\PDO::FETCH_ASSOC);

    if ($nextItem) {
        Logger::mediaQueue("Próximo item: #{$nextItem['id']} dir={$nextItem['direction']} type={$nextItem['media_type']} status={$nextItem['status']} attempts={$nextItem['attempts']}/{$nextItem['max_attempts']} msg_id={$nextItem['message_id']} conv_id={$nextItem['conversation_id']} ext_id={$nextItem['external_message_id']}");
    } else {
        Logger::mediaQueue("Nenhum item elegível (todos com next_attempt_at no futuro).");
        
        $nextAttempt = $db->query("
            SELECT id, next_attempt_at, status, attempts 
            FROM media_queue 
            WHERE status IN ('queued', 'processing') 
            ORDER BY next_attempt_at ASC 
            LIMIT 3
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        if (!empty($nextAttempt)) {
            foreach ($nextAttempt as $na) {
                Logger::mediaQueue("  Aguardando: #{$na['id']} next_attempt={$na['next_attempt_at']} status={$na['status']} attempts={$na['attempts']}");
            }
        }
        exit(0);
    }

    if ($isLoop) {
        $startTime = time();
        $totalProcessed = 0;
        $totalSuccess = 0;
        $totalErrors = 0;

        Logger::mediaQueue("Modo loop iniciado (máx {$maxLoopTime}s)");

        while (time() - $startTime < $maxLoopTime) {
            $elapsed = time() - $startTime;
            $result = MediaQueueService::processQueue();

            if ($result['processed'] > 0) {
                $totalProcessed += $result['processed'];
                $totalSuccess += $result['success'];
                $totalErrors += $result['errors'];
                Logger::mediaQueue("Loop [{$elapsed}s]: processed={$result['processed']} success={$result['success']} errors={$result['errors']} (acumulado: {$totalProcessed} proc / {$totalSuccess} ok / {$totalErrors} err)");
            }

            if ($result['processed'] === 0) {
                sleep(5);
            } else {
                sleep(2);
            }
        }

        Logger::mediaQueue("═══ LOOP ENCERRADO ═══ Duração=" . (time() - $startTime) . "s total_processed={$totalProcessed} success={$totalSuccess} errors={$totalErrors}");

    } else {
        $startMs = microtime(true);
        $result = MediaQueueService::processQueue();
        $durationMs = round((microtime(true) - $startMs) * 1000);

        if ($result['processed'] > 0 || $result['errors'] > 0) {
            Logger::mediaQueue("Resultado: processed={$result['processed']} success={$result['success']} errors={$result['errors']} skipped={$result['skipped']} duration={$durationMs}ms");
        } else {
            Logger::mediaQueue("Nenhum item processado nesta execução. duration={$durationMs}ms");
        }

        if (date('H:i') === '03:00') {
            $cleaned = MediaQueue::cleanup(7);
            if ($cleaned > 0) {
                Logger::mediaQueue("Limpeza diária: {$cleaned} itens antigos removidos");
            }
        }
    }

} catch (\Exception $e) {
    Logger::mediaQueue("EXCEÇÃO FATAL: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine(), 'ERROR');
    Logger::mediaQueue("Stack trace: " . $e->getTraceAsString(), 'ERROR');
}

Logger::mediaQueue("═══ CRON FINALIZADO ═══");
exit(0);
