<?php
/**
 * Cron: Poll de contas de Email (IMAP) -> ingestão como conversas.
 *
 * Uso (recomendado, com lock para evitar sobreposição):
 *   * /5 * * * * flock -n /tmp/poll_imap_emails.lock php /caminho/public/scripts/poll-imap-emails.php >> /caminho/logs/email_poller.log 2>&1
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Jobs\EmailPollerJob;

$start = microtime(true);
echo '[' . date('Y-m-d H:i:s') . "] Iniciando poll de emails (IMAP)...\n";

try {
    $summary = EmailPollerJob::run();
    echo '[' . date('Y-m-d H:i:s') . '] Concluído: ' . json_encode($summary)
        . ' em ' . round(microtime(true) - $start, 2) . "s\n";
    exit(0);
} catch (\Throwable $e) {
    echo '[' . date('Y-m-d H:i:s') . '] ERRO: ' . $e->getMessage() . "\n";
    exit(1);
}
