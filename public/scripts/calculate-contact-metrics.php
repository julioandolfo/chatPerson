<?php
/**
 * Cron Job: Calcular Métricas de Contatos
 * Executar a cada 30 minutos via Task Scheduler ou cron
 * 
 * Windows: C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\chat\public\scripts\calculate-contact-metrics.php
 * Linux: 0,30 * * * * php /var/www/html/public/scripts/calculate-contact-metrics.php
 * Docker: (a cada 30 min) docker exec CONTAINER php /var/www/html/public/scripts/calculate-contact-metrics.php
 * 
 * Recalcula métricas de contatos de forma inteligente:
 * - Prioriza conversas abertas com mensagens novas
 * - Não recalcula conversas fechadas já processadas
 * - Processa em lotes para não sobrecarregar
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ContactMetricsService;
use App\Helpers\Logger;

echo "=== CALCULANDO MÉTRICAS DE CONTATOS ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

// Configurações
$batchSize = 100; // Processar 100 contatos por vez
$maxExecutionTime = 300; // 5 minutos máximo

set_time_limit($maxExecutionTime);

$start = microtime(true);
$startMemory = memory_get_usage(true);

try {
    // Verificar se o Service existe
    if (!class_exists(ContactMetricsService::class)) {
        throw new \Exception("ContactMetricsService não encontrado");
    }
    
    // Processar lote
    $results = ContactMetricsService::processBatch($batchSize);
    
    $duration = round((microtime(true) - $start) * 1000);
    $memoryUsed = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 2);
    
    // Exibir resultados
    echo "Processados: " . ($results['processed'] ?? 0) . "\n";
    echo "Erros: " . ($results['errors'] ?? 0) . "\n";
    echo "Pulados: " . ($results['skipped'] ?? 0) . "\n";
    
    if (($results['processed'] ?? 0) > 0) {
        $avgTime = round(($duration / 1000) / $results['processed'], 3);
        echo "Média: {$avgTime}s por contato\n";
    }
    
    echo "\n=== CONCLUÍDO ===\n";
    echo "Tempo total: {$duration}ms\n";
    echo "Memória usada: {$memoryUsed}MB\n";
    echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
    Logger::info("Contact Metrics: {$results['processed']} processados, {$results['errors']} erros");
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    Logger::error("Erro ao calcular métricas de contatos: " . $e->getMessage());
    exit(1);
}

exit(0);
