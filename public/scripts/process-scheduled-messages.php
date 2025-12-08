<?php
/**
 * Script para processar mensagens agendadas pendentes
 * 
 * Rodar via cron a cada minuto:
 * * * * * * php /caminho/para/public/scripts/process-scheduled-messages.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ScheduledMessageService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de mensagens agendadas...\n";

try {
    $processed = ScheduledMessageService::processPending(50);
    
    $sent = 0;
    $cancelled = 0;
    $failed = 0;
    
    foreach ($processed as $result) {
        if ($result['status'] === 'sent') {
            $sent++;
        } elseif ($result['status'] === 'cancelled') {
            $cancelled++;
        } elseif ($result['status'] === 'failed') {
            $failed++;
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
    echo "  - Enviadas: {$sent}\n";
    echo "  - Canceladas: {$cancelled}\n";
    echo "  - Falhadas: {$failed}\n";
    echo "  - Total processadas: " . count($processed) . "\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    Logger::error("Erro ao processar mensagens agendadas: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script finalizado com sucesso.\n";

