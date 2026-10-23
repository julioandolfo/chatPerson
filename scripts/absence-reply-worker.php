<?php
/**
 * Envio da mensagem de ausência em segundo plano
 *
 * Disparado por AgentAbsenceService quando o cliente escreve para um agente ausente:
 * espera o atraso configurado (ex.: 10 s) e envia. Se este processo não rodar, o cron
 * (AgentAbsenceJob) envia na próxima execução.
 *
 * Uso: php scripts/absence-reply-worker.php <id em agent_absence_replies>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$rootDir = dirname(__DIR__);
chdir($rootDir);
date_default_timezone_set('America/Sao_Paulo');
set_time_limit(0);

require_once $rootDir . '/config/bootstrap.php';

$replyId = (int)($argv[1] ?? 0);
if ($replyId <= 0) {
    fwrite(STDERR, "Uso: php scripts/absence-reply-worker.php <id>\n");
    exit(1);
}

try {
    \App\Services\AgentAbsenceService::runWorker($replyId);
} catch (\Throwable $e) {
    \App\Helpers\Logger::error("absence-reply-worker #{$replyId}: " . $e->getMessage());
    exit(1);
}
