<?php
/**
 * Script para processar campanhas ativas
 * 
 * Rodar via cron a cada 1 minuto:
 * * * * * * php /caminho/para/public/scripts/process-campaigns.php >> /caminho/para/logs/campaigns.log 2>&1
 * 
 * Windows Task Scheduler:
 * - Programa: php.exe
 * - Argumentos: C:\laragon\www\chat\public\scripts\process-campaigns.php
 * - Repetir: A cada 1 minuto
 */

// Ajustar caminho do bootstrap
$bootstrapPath = __DIR__ . '/../../config/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    $bootstrapPath = __DIR__ . '/../../app/Helpers/autoload.php';
}

require_once $bootstrapPath;

use App\Services\CampaignSchedulerService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de campanhas...\n";

try {
    // Processar até 50 mensagens por execução
    $processed = CampaignSchedulerService::processPending(50);
    
    // Contabilizar resultados
    $sent = 0;
    $skipped = 0;
    $failed = 0;
    
    foreach ($processed as $result) {
        if ($result['status'] === 'sent') {
            $sent++;
        } elseif ($result['status'] === 'skipped') {
            $skipped++;
        } elseif ($result['status'] === 'failed') {
            $failed++;
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
    echo "  - Enviadas: {$sent}\n";
    echo "  - Puladas: {$skipped}\n";
    echo "  - Falhadas: {$failed}\n";
    echo "  - Total processadas: " . count($processed) . "\n";
    
    Logger::info("Cron campaigns: Enviadas={$sent}, Puladas={$skipped}, Falhadas={$failed}");
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    Logger::error("Erro ao processar campanhas: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script finalizado com sucesso.\n";
exit(0);
