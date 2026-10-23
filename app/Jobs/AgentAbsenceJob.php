<?php
/**
 * Job AgentAbsenceJob
 * Envia as mensagens de ausência pendentes que o processo em segundo plano não enviou
 * (servidor sem exec, atraso longo, nova tentativa após falha).
 */

namespace App\Jobs;

use App\Services\AgentAbsenceService;
use App\Helpers\Logger;

class AgentAbsenceJob
{
    public static function run(): void
    {
        try {
            $stats = AgentAbsenceService::processDue();

            if ($stats['due'] > 0) {
                Logger::info("AgentAbsenceJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}");
            }
            echo "[" . date('Y-m-d H:i:s') . "] AgentAbsenceJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}\n";
        } catch (\PDOException $e) {
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                echo "[" . date('Y-m-d H:i:s') . "] AgentAbsenceJob: banco desatualizado — execute: php database/run_migrations.php 163\n";
                return;
            }
            throw $e;
        }
    }
}
