<?php
/**
 * Script para processar URLs pendentes do RAG
 * 
 * Execute via cron:
 * */5 * * * * php /caminho/para/public/process-rag-urls.php
 * 
 * Ou execute manualmente:
 * php public/process-rag-urls.php
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Jobs\ProcessURLScrapingJob;
use App\Services\AgentMemoryService;

echo "[" . date('Y-m-d H:i:s') . "] Iniciando processamento de URLs RAG...\n";

try {
    // Processar URLs pendentes (limite de 10 por execução)
    $stats = ProcessURLScrapingJob::processPending(10);
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento concluído:\n";
    echo "  - Processadas: {$stats['processed']}\n";
    echo "  - Sucesso: {$stats['success']}\n";
    echo "  - Falhas: {$stats['failed']}\n";
    
    if (!empty($stats['errors'])) {
        echo "  - Erros:\n";
        foreach ($stats['errors'] as $error) {
            echo "    * {$error}\n";
        }
    }
    
    // Limpar memórias expiradas
    $cleaned = AgentMemoryService::cleanExpired();
    if ($cleaned > 0) {
        echo "  - Memórias expiradas removidas: {$cleaned}\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processamento finalizado.\n";
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);

