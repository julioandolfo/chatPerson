#!/usr/bin/env php
<?php
/**
 * CRON Job - Calcular Métricas de Contatos (STANDALONE)
 * 
 * Versão standalone que não depende do Composer.
 * Usa o autoloader nativo do sistema.
 * 
 * Recalcula métricas de contatos de forma inteligente:
 * - Prioriza conversas abertas com mensagens novas
 * - Não recalcula conversas fechadas já processadas
 * - Processa em lotes para não sobrecarregar
 * 
 * Adicionar ao crontab:
 * # A cada 30 minutos (ajuste conforme necessário)
 * */30 * * * * cd /var/www/html && php cron/calculate-contact-metrics.php >> logs/cron-metrics.log 2>&1
 * 
 * Ou para teste manual:
 * php cron/calculate-contact-metrics.php
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

use App\Services\ContactMetricsService;

// Configurações
$batchSize = 100; // Processar 100 contatos por vez
$maxExecutionTime = 300; // 5 minutos máximo

set_time_limit($maxExecutionTime);

$startTime = microtime(true);
$startMemory = memory_get_usage(true);

echo "═══════════════════════════════════════════════════════════════\n";
echo "🚀 CRON: Calculando métricas de contatos (Standalone)\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "📁 Root Dir: {$rootDir}\n";
echo "⏰ Início: " . date('Y-m-d H:i:s') . "\n";
echo "📊 Lote: {$batchSize} contatos\n";
echo "\n";

try {
    // Processar lote
    $results = ContactMetricsService::processBatch($batchSize);
    
    $endTime = microtime(true);
    $endMemory = memory_get_usage(true);
    
    $duration = round($endTime - $startTime, 2);
    $memoryUsed = round(($endMemory - $startMemory) / 1024 / 1024, 2);
    
    echo "───────────────────────────────────────────────────────────────\n";
    echo "✅ RESULTADO\n";
    echo "───────────────────────────────────────────────────────────────\n";
    echo "Processados: {$results['processed']}\n";
    echo "Erros: {$results['errors']}\n";
    echo "Pulados: {$results['skipped']}\n";
    echo "\n";
    echo "Tempo: {$duration}s\n";
    echo "Memória: {$memoryUsed}MB\n";
    echo "\n";
    
    if ($results['processed'] > 0) {
        $avgTime = round($duration / $results['processed'], 3);
        echo "Média: {$avgTime}s por contato\n";
    }
    
    echo "───────────────────────────────────────────────────────────────\n";
    
    // Log para arquivo
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/cron-metrics.log';
    $logEntry = sprintf(
        "[%s] Processados: %d | Erros: %d | Tempo: %.2fs | Memória: %.2fMB\n",
        date('Y-m-d H:i:s'),
        $results['processed'],
        $results['errors'],
        $duration,
        $memoryUsed
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    exit(0);
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    // Log de erro
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/cron-metrics-error.log';
    $logEntry = sprintf(
        "[%s] ERRO: %s\n%s\n\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getTraceAsString()
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    exit(1);
}
