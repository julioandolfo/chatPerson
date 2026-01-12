#!/usr/bin/env php
<?php
/**
 * Script de teste de performance dos jobs agendados
 * Executa os jobs e mostra estatísticas de tempo/recursos
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

date_default_timezone_set('America/Sao_Paulo');
require_once $rootDir . '/config/bootstrap.php';

use App\Jobs\SLAMonitoringJob;
use App\Jobs\FollowupJob;
use App\Jobs\AICostMonitoringJob;
use App\Jobs\AutomationDelayJob;
use App\Jobs\AIFallbackMonitoringJob;
use App\Jobs\WooCommerceSyncJob;

echo "==========================================\n";
echo "  TESTE DE PERFORMANCE - JOBS AGENDADOS  \n";
echo "==========================================\n\n";

$stats = [];
$totalStart = microtime(true);
$memoryStart = memory_get_usage();

// ========== 1. AI Buffers ==========
echo "🔵 [1/6] Testando process-ai-buffers.php...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    ob_start();
    include __DIR__ . '/process-ai-buffers.php';
    $output = ob_get_clean();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['ai_buffers'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['ai_buffers'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== 2. Automation Delays ==========
echo "🔵 [2/6] Testando AutomationDelayJob...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    AutomationDelayJob::run();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['automation_delays'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['automation_delays'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== 3. SLA Monitoring ==========
echo "🔵 [3/6] Testando SLAMonitoringJob...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    SLAMonitoringJob::run();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['sla_monitoring'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['sla_monitoring'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== 4. AI Fallback ==========
echo "🔵 [4/6] Testando AIFallbackMonitoringJob...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    AIFallbackMonitoringJob::run();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['ai_fallback'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['ai_fallback'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== 5. Followup ==========
echo "🔵 [5/6] Testando FollowupJob...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    FollowupJob::run();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['followup'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['followup'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== 6. AI Cost Monitoring ==========
echo "🔵 [6/6] Testando AICostMonitoringJob...\n";
$start = microtime(true);
$memBefore = memory_get_usage();
try {
    AICostMonitoringJob::run();
    $duration = microtime(true) - $start;
    $memUsed = memory_get_usage() - $memBefore;
    $stats['ai_cost'] = [
        'duration' => $duration,
        'memory' => $memUsed,
        'status' => 'OK'
    ];
    echo "   ✅ Concluído em " . round($duration, 2) . "s | Memória: " . number_format($memUsed / 1024, 0) . " KB\n\n";
} catch (\Exception $e) {
    $stats['ai_cost'] = ['status' => 'ERRO', 'message' => $e->getMessage()];
    echo "   ❌ ERRO: " . $e->getMessage() . "\n\n";
}

// ========== RESUMO ==========
$totalDuration = microtime(true) - $totalStart;
$totalMemory = memory_get_usage() - $memoryStart;
$peakMemory = memory_get_peak_usage();

echo "==========================================\n";
echo "              RESUMO GERAL               \n";
echo "==========================================\n\n";

echo "⏱️  TEMPO TOTAL: " . round($totalDuration, 2) . "s\n";
echo "💾 MEMÓRIA USADA: " . number_format($totalMemory / 1024 / 1024, 2) . " MB\n";
echo "📊 MEMÓRIA PICO: " . number_format($peakMemory / 1024 / 1024, 2) . " MB\n\n";

echo "DETALHES POR JOB:\n";
echo str_repeat("-", 60) . "\n";
printf("%-25s %10s %15s %10s\n", "Job", "Tempo", "Memória", "Status");
echo str_repeat("-", 60) . "\n";

foreach ($stats as $job => $data) {
    if ($data['status'] === 'OK') {
        printf(
            "%-25s %9.2fs %12s KB %10s\n",
            $job,
            $data['duration'],
            number_format($data['memory'] / 1024, 0),
            $data['status']
        );
    } else {
        printf("%-25s %10s %15s %10s\n", $job, '-', '-', $data['status']);
    }
}

echo str_repeat("-", 60) . "\n\n";

// ========== ANÁLISE ==========
echo "ANÁLISE DE PERFORMANCE:\n\n";

$slowJobs = [];
foreach ($stats as $job => $data) {
    if ($data['status'] === 'OK' && $data['duration'] > 5) {
        $slowJobs[] = $job . " (" . round($data['duration'], 2) . "s)";
    }
}

if (!empty($slowJobs)) {
    echo "⚠️  JOBS LENTOS (>5s):\n";
    foreach ($slowJobs as $job) {
        echo "   - " . $job . "\n";
    }
    echo "\n";
} else {
    echo "✅ Todos os jobs estão dentro do tempo esperado (<5s)\n\n";
}

if ($peakMemory > 128 * 1024 * 1024) { // > 128MB
    echo "⚠️  USO DE MEMÓRIA ALTO (>" . number_format($peakMemory / 1024 / 1024, 0) . "MB)\n";
    echo "   Considere otimizar queries ou reduzir limites\n\n";
} else {
    echo "✅ Uso de memória aceitável\n\n";
}

if ($totalDuration > 30) {
    echo "⚠️  TEMPO TOTAL ALTO (>" . round($totalDuration, 0) . "s)\n";
    echo "   Considere reduzir frequência ou limites dos jobs\n\n";
} else {
    echo "✅ Tempo total aceitável\n\n";
}

echo "==========================================\n";
echo "Teste concluído em " . date('Y-m-d H:i:s') . "\n";
echo "==========================================\n";
