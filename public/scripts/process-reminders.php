<?php
/**
 * Script para processar lembretes pendentes
 * 
 * Rodar via cron a cada minuto:
 * * * * * * php /caminho/para/public/scripts/process-reminders.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ReminderService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de lembretes...\n";

try {
    $processed = ReminderService::processPending(50);
    
    $notified = 0;
    $errors = 0;
    
    foreach ($processed as $result) {
        if ($result['status'] === 'notified') {
            $notified++;
        } elseif ($result['status'] === 'error') {
            $errors++;
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
    echo "  - Notificados: {$notified}\n";
    echo "  - Erros: {$errors}\n";
    echo "  - Total processados: " . count($processed) . "\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    Logger::error("Erro ao processar lembretes: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script finalizado com sucesso.\n";

