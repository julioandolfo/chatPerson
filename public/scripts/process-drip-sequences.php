<?php
/**
 * Script: Processar sequências Drip
 * Executar a cada 1 hora via cron
 * 
 * Windows: Task Scheduler
 * Linux: 0 * * * * php /path/to/process-drip-sequences.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\DripSequenceService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de sequências Drip...\n";

try {
    $result = DripSequenceService::processPending();
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
    echo "  - Contatos processados: {$result['processed']}\n";
    echo "  - Erros: {$result['errors']}\n";
    
    Logger::info("Drip sequences processadas: processed={$result['processed']}, errors={$result['errors']}");
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    Logger::error("Erro ao processar sequências Drip: " . $e->getMessage());
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Script finalizado com sucesso.\n";
exit(0);
