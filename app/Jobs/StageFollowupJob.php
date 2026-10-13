<?php
/**
 * Job StageFollowupJob
 * Envia as mensagens de follow-up por etapa do funil que já venceram
 * ("Vamos continuar?", "Ainda está por aí?"...)
 */

namespace App\Jobs;

use App\Services\StageFollowupService;
use App\Helpers\Logger;

class StageFollowupJob
{
    public static function run(): void
    {
        try {
            $stats = StageFollowupService::processDue();

            if ($stats['due'] > 0) {
                Logger::info("StageFollowupJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}");
            }
            echo "[" . date('Y-m-d H:i:s') . "] StageFollowupJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}\n";
        } catch (\PDOException $e) {
            // Tabelas ainda não criadas (migration 159 pendente): não quebrar o cron
            if ($e->getCode() === '42S02') {
                echo "[" . date('Y-m-d H:i:s') . "] StageFollowupJob: tabelas não encontradas — execute: php database/run_migrations.php 159\n";
                return;
            }
            throw $e;
        }
    }
}
