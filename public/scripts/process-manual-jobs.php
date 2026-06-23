<?php
/**
 * Worker de geração de manuais — processa jobs pendentes (background).
 *
 * Uso:
 *   php public/scripts/process-manual-jobs.php          # processa pendentes (cron)
 *   php public/scripts/process-manual-jobs.php <jobId>   # processa um job específico
 *
 * Cron sugerido (a cada minuto):
 *   * * * * * php /caminho/public/scripts/process-manual-jobs.php >> /var/log/manual-jobs.log 2>&1
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Helpers\Database;
use App\Services\ManualGeneratorService;

@set_time_limit(0);

$jobId = (int)($argv[1] ?? 0);

if ($jobId) {
    $jobs = [['id' => $jobId]];
} else {
    $jobs = Database::fetchAll(
        "SELECT id FROM manual_jobs WHERE status = 'pending' ORDER BY id ASC LIMIT 5"
    );
}

if (empty($jobs)) {
    echo "[" . date('Y-m-d H:i:s') . "] Nenhum job pendente.\n";
    exit(0);
}

foreach ($jobs as $j) {
    $id = (int)$j['id'];
    echo "[" . date('Y-m-d H:i:s') . "] Processando job #{$id}...\n";
    try {
        $manualId = ManualGeneratorService::runJob($id);
        echo "  ✅ Manual #{$manualId} gerado (job #{$id}).\n";
    } catch (\Throwable $e) {
        echo "  ❌ Job #{$id}: " . $e->getMessage() . "\n";
    }
}
