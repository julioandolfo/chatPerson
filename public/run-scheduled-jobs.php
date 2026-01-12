#!/usr/bin/env php
<?php
// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

// Carregar bootstrap (que já tem o autoloader e todas as configurações)
require_once $rootDir . '/config/bootstrap.php';

use App\Jobs\SLAMonitoringJob;
use App\Jobs\FollowupJob;
use App\Jobs\AICostMonitoringJob;
use App\Jobs\AutomationDelayJob;
use App\Jobs\AIFallbackMonitoringJob;
use App\Jobs\WooCommerceSyncJob;

// ✅ Garantir que diretório de cache existe
$cacheDir = $rootDir . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

try {
    // ✅ CRÍTICO: Usar arquivo de controle para evitar múltiplas execuções simultâneas
    $lockFile = __DIR__ . '/../storage/cache/jobs.lock';
    $lockHandle = fopen($lockFile, 'c+');
    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        echo "[" . date('Y-m-d H:i:s') . "] Jobs já em execução, pulando...\n";
        exit(0);
    }
    
    // ✅ Arquivo de estado para controlar última execução de cada job
    $stateFile = __DIR__ . '/../storage/cache/jobs_state.json';
    $state = [];
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true) ?: [];
    }
    
    $now = time();
    
    // ========== JOBS CRÍTICOS (a cada execução) ==========
    
    // ✅ Processar buffers de IA (CRÍTICO - executar sempre)
    echo "[" . date('Y-m-d H:i:s') . "] Processando buffers de IA...\n";
    $startTime = microtime(true);
    include __DIR__ . '/process-ai-buffers.php';
    $duration = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] Buffers de IA processados em {$duration}s\n";
    
    // ✅ Executar delays de automações (CRÍTICO - executar sempre)
    echo "[" . date('Y-m-d H:i:s') . "] Executando AutomationDelayJob...\n";
    $startTime = microtime(true);
    AutomationDelayJob::run();
    $duration = round(microtime(true) - $startTime, 2);
    echo "[" . date('Y-m-d H:i:s') . "] AutomationDelayJob concluído em {$duration}s\n";
    
    // ========== JOBS IMPORTANTES (a cada 2-3 minutos) ==========
    
    // ✅ Monitoramento de SLA (REDUZIDO: a cada 3 minutos)
    $lastSLA = $state['last_sla'] ?? 0;
    if (($now - $lastSLA) >= 180 || isset($_GET['force_sla'])) { // 3 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando SLAMonitoringJob...\n";
        $startTime = microtime(true);
        SLAMonitoringJob::run();
        $duration = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] SLAMonitoringJob concluído em {$duration}s\n";
        $state['last_sla'] = $now;
    }
    
    // ========== JOBS MODERADOS (a cada 10-15 minutos) ==========
    
    // ✅ Monitoramento de fallback de IA (a cada 10 minutos)
    $lastFallback = $state['last_fallback'] ?? 0;
    if (($now - $lastFallback) >= 600 || isset($_GET['force_fallback'])) { // 10 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando AIFallbackMonitoringJob...\n";
        $startTime = microtime(true);
        AIFallbackMonitoringJob::run();
        $duration = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] AIFallbackMonitoringJob concluído em {$duration}s\n";
        $state['last_fallback'] = $now;
    }
    
    // ✅ Followups (a cada 15 minutos)
    $lastFollowup = $state['last_followup'] ?? 0;
    if (($now - $lastFollowup) >= 900 || isset($_GET['force_followup'])) { // 15 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando FollowupJob...\n";
        $startTime = microtime(true);
        FollowupJob::run();
        $duration = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] FollowupJob concluído em {$duration}s\n";
        $state['last_followup'] = $now;
    }
    
    // ========== JOBS LEVES (a cada hora) ==========
    
    // ✅ Monitoramento de custos de IA (a cada hora)
    $lastCost = $state['last_cost'] ?? 0;
    if (($now - $lastCost) >= 3600 || isset($_GET['force_cost_check'])) { // 1 hora
        echo "[" . date('Y-m-d H:i:s') . "] Executando AICostMonitoringJob...\n";
        $startTime = microtime(true);
        AICostMonitoringJob::run();
        $duration = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] AICostMonitoringJob concluído em {$duration}s\n";
        $state['last_cost'] = $now;
    }
    
    // ✅ Sincronização WooCommerce (a cada hora)
    $lastWC = $state['last_wc'] ?? 0;
    if (($now - $lastWC) >= 3600 || isset($_GET['force_wc_sync'])) { // 1 hora
        echo "[" . date('Y-m-d H:i:s') . "] Executando WooCommerceSyncJob...\n";
        $startTime = microtime(true);
        WooCommerceSyncJob::run();
        $duration = round(microtime(true) - $startTime, 2);
        echo "[" . date('Y-m-d H:i:s') . "] WooCommerceSyncJob concluído em {$duration}s\n";
        $state['last_wc'] = $now;
    }
    
    // ✅ Salvar estado
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    
    // ✅ Liberar lock
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    
    echo "[" . date('Y-m-d H:i:s') . "] Todos os jobs executados com sucesso\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar jobs agendados: " . $e->getMessage());
    
    // ✅ Liberar lock em caso de erro
    if (isset($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
    
    exit(1);
}

