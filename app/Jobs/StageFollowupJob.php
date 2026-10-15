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
                Logger::info("StageFollowupJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}, aguardando_vez={$stats['throttled']}");
            }
            echo "[" . date('Y-m-d H:i:s') . "] StageFollowupJob: vencidos={$stats['due']}, enviados={$stats['sent']}, ignorados={$stats['skipped']}, falhas={$stats['failed']}, aguardando_vez={$stats['throttled']}\n";
        } catch (\PDOException $e) {
            // Tabela/coluna ainda não criada (migrations 159/161 pendentes): não quebrar o cron
            if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
                echo "[" . date('Y-m-d H:i:s') . "] StageFollowupJob: banco desatualizado — execute: php database/run_migrations.php 159 && php database/run_migrations.php 161\n";
                return;
            }
            throw $e;
        }
    }
}
