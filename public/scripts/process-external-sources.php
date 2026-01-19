<?php
/**
 * Cron Job: Processar sincronização de fontes externas
 * Executar a cada hora via Task Scheduler ou cron
 * 
 * Windows: C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\chat\public\scripts\process-external-sources.php
 * Linux: 0 * * * * php /var/www/html/public/scripts/process-external-sources.php
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ExternalDataSourceService;
use App\Helpers\Logger;

echo "=== PROCESSANDO FONTES EXTERNAS ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

$start = microtime(true);

try {
    ExternalDataSourceService::processPending();
    
    $duration = round((microtime(true) - $start) * 1000);
    
    echo "\n=== CONCLUÍDO ===\n";
    echo "Tempo total: {$duration}ms\n";
    echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    Logger::error("Erro ao processar fontes externas: " . $e->getMessage());
    exit(1);
}
