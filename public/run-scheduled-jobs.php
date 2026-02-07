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
use App\Jobs\ChatbotTimeoutJob;

// ✅ Garantir que diretório de cache existe
$cacheDir = $rootDir . '/storage/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// ✅ Histórico de execuções do cron
$cronHistoryFile = $rootDir . '/storage/cache/cron_history.json';
$cronRunStart = microtime(true);
$cronRunId = date('Y-m-d H:i:s');
$cronJobResults = [];
$cronStatus = 'success';
$cronError = null;

/**
 * Registrar resultado de um job no histórico
 */
function cronLogJob(string $jobName, float $duration, string $status = 'ok', ?string $error = null): void
{
    global $cronJobResults;
    $cronJobResults[] = [
        'job' => $jobName,
        'duration' => round($duration, 3),
        'status' => $status,
        'error' => $error,
    ];
}

/**
 * Salvar entrada no histórico do cron (manter últimas 200 execuções)
 */
function cronSaveHistory(): void
{
    global $cronHistoryFile, $cronRunStart, $cronRunId, $cronJobResults, $cronStatus, $cronError;
    
    $totalDuration = round(microtime(true) - $cronRunStart, 3);
    
    $history = [];
    if (file_exists($cronHistoryFile)) {
        $history = json_decode(file_get_contents($cronHistoryFile), true) ?: [];
    }
    
    // Adicionar execução atual
    $entry = [
        'started_at' => $cronRunId,
        'finished_at' => date('Y-m-d H:i:s'),
        'duration_s' => $totalDuration,
        'status' => $cronStatus,
        'error' => $cronError,
        'jobs_count' => count($cronJobResults),
        'jobs' => $cronJobResults,
    ];
    
    array_unshift($history, $entry); // Mais recente primeiro
    
    // Manter apenas as últimas 200 execuções
    $history = array_slice($history, 0, 200);
    
    $historyDir = dirname($cronHistoryFile);
    if (!is_dir($historyDir)) {
        @mkdir($historyDir, 0777, true);
    }
    @file_put_contents($cronHistoryFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

try {
    // ✅ CRÍTICO: Usar arquivo de controle para evitar múltiplas execuções simultâneas
    $lockFile = __DIR__ . '/../storage/cache/jobs.lock';
    
    // Garantir que o diretório existe com permissões adequadas
    $lockDir = dirname($lockFile);
    if (!is_dir($lockDir)) {
        @mkdir($lockDir, 0777, true);
    }
    
    $lockHandle = @fopen($lockFile, 'c+');
    if ($lockHandle === false) {
        // Tentar corrigir permissões e recriar
        @chmod($lockDir, 0777);
        @unlink($lockFile);
        $lockHandle = @fopen($lockFile, 'c+');
        
        if ($lockHandle === false) {
            // Se ainda falhar, continuar SEM lock (melhor rodar sem lock do que não rodar)
            echo "[" . date('Y-m-d H:i:s') . "] AVISO: Não foi possível criar arquivo de lock ({$lockFile}). Executando sem lock.\n";
            error_log("run-scheduled-jobs: Não foi possível criar lock file: {$lockFile} - Permissão negada");
            $lockHandle = null;
        }
    }
    
    if ($lockHandle !== null && !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        echo "[" . date('Y-m-d H:i:s') . "] Jobs já em execução, pulando...\n";
        fclose($lockHandle);
        $lockHandle = null;
        $cronStatus = 'skipped';
        $cronError = 'Lock ativo - já em execução';
        cronSaveHistory();
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
    try {
        include __DIR__ . '/process-ai-buffers.php';
        $duration = microtime(true) - $startTime;
        cronLogJob('AIBuffers', $duration);
    } catch (\Throwable $e) {
        $duration = microtime(true) - $startTime;
        cronLogJob('AIBuffers', $duration, 'error', $e->getMessage());
    }
    echo "[" . date('Y-m-d H:i:s') . "] Buffers de IA processados em " . round($duration, 2) . "s\n";
    
    // ✅ Executar delays de automações (CRÍTICO - executar sempre)
    echo "[" . date('Y-m-d H:i:s') . "] Executando AutomationDelayJob...\n";
    $startTime = microtime(true);
    try {
        AutomationDelayJob::run();
        $duration = microtime(true) - $startTime;
        cronLogJob('AutomationDelayJob', $duration);
    } catch (\Throwable $e) {
        $duration = microtime(true) - $startTime;
        cronLogJob('AutomationDelayJob', $duration, 'error', $e->getMessage());
    }
    echo "[" . date('Y-m-d H:i:s') . "] AutomationDelayJob concluído em " . round($duration, 2) . "s\n";
    
    // ✅ Verificar timeouts de chatbot (CRÍTICO - executar sempre)
    echo "[" . date('Y-m-d H:i:s') . "] Executando ChatbotTimeoutJob...\n";
    $startTime = microtime(true);
    try {
        ChatbotTimeoutJob::run();
        $duration = microtime(true) - $startTime;
        cronLogJob('ChatbotTimeoutJob', $duration);
    } catch (\Throwable $e) {
        $duration = microtime(true) - $startTime;
        cronLogJob('ChatbotTimeoutJob', $duration, 'error', $e->getMessage());
    }
    echo "[" . date('Y-m-d H:i:s') . "] ChatbotTimeoutJob concluído em " . round($duration, 2) . "s\n";
    
    // ========== JOBS IMPORTANTES (a cada 2-3 minutos) ==========
    
    // ✅ Monitoramento de SLA (REDUZIDO: a cada 3 minutos)
    $lastSLA = $state['last_sla'] ?? 0;
    if (($now - $lastSLA) >= 180 || isset($_GET['force_sla'])) { // 3 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando SLAMonitoringJob...\n";
        $startTime = microtime(true);
        try {
            SLAMonitoringJob::run();
            $duration = microtime(true) - $startTime;
            cronLogJob('SLAMonitoringJob', $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            cronLogJob('SLAMonitoringJob', $duration, 'error', $e->getMessage());
        }
        echo "[" . date('Y-m-d H:i:s') . "] SLAMonitoringJob concluído em " . round($duration, 2) . "s\n";
        $state['last_sla'] = $now;
    }
    
    // ========== JOBS MODERADOS (a cada 10-15 minutos) ==========
    
    // ✅ Monitoramento de fallback de IA (a cada 10 minutos)
    $lastFallback = $state['last_fallback'] ?? 0;
    if (($now - $lastFallback) >= 600 || isset($_GET['force_fallback'])) { // 10 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando AIFallbackMonitoringJob...\n";
        $startTime = microtime(true);
        try {
            AIFallbackMonitoringJob::run();
            $duration = microtime(true) - $startTime;
            cronLogJob('AIFallbackMonitoringJob', $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            cronLogJob('AIFallbackMonitoringJob', $duration, 'error', $e->getMessage());
        }
        echo "[" . date('Y-m-d H:i:s') . "] AIFallbackMonitoringJob concluído em " . round($duration, 2) . "s\n";
        $state['last_fallback'] = $now;
    }
    
    // ✅ Followups (a cada 15 minutos)
    $lastFollowup = $state['last_followup'] ?? 0;
    if (($now - $lastFollowup) >= 900 || isset($_GET['force_followup'])) { // 15 minutos
        echo "[" . date('Y-m-d H:i:s') . "] Executando FollowupJob...\n";
        $startTime = microtime(true);
        try {
            FollowupJob::run();
            $duration = microtime(true) - $startTime;
            cronLogJob('FollowupJob', $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            cronLogJob('FollowupJob', $duration, 'error', $e->getMessage());
        }
        echo "[" . date('Y-m-d H:i:s') . "] FollowupJob concluído em " . round($duration, 2) . "s\n";
        $state['last_followup'] = $now;
    }
    
    // ========== JOBS LEVES (a cada hora) ==========
    
    // ✅ Monitoramento de custos de IA (a cada hora)
    $lastCost = $state['last_cost'] ?? 0;
    if (($now - $lastCost) >= 3600 || isset($_GET['force_cost_check'])) { // 1 hora
        echo "[" . date('Y-m-d H:i:s') . "] Executando AICostMonitoringJob...\n";
        $startTime = microtime(true);
        try {
            AICostMonitoringJob::run();
            $duration = microtime(true) - $startTime;
            cronLogJob('AICostMonitoringJob', $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            cronLogJob('AICostMonitoringJob', $duration, 'error', $e->getMessage());
        }
        echo "[" . date('Y-m-d H:i:s') . "] AICostMonitoringJob concluído em " . round($duration, 2) . "s\n";
        $state['last_cost'] = $now;
    }
    
    // ✅ Sincronização WooCommerce (a cada hora)
    $lastWC = $state['last_wc'] ?? 0;
    if (($now - $lastWC) >= 3600 || isset($_GET['force_wc_sync'])) { // 1 hora
        echo "[" . date('Y-m-d H:i:s') . "] Executando WooCommerceSyncJob...\n";
        $startTime = microtime(true);
        try {
            WooCommerceSyncJob::run();
            $duration = microtime(true) - $startTime;
            cronLogJob('WooCommerceSyncJob', $duration);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            cronLogJob('WooCommerceSyncJob', $duration, 'error', $e->getMessage());
        }
        echo "[" . date('Y-m-d H:i:s') . "] WooCommerceSyncJob concluído em " . round($duration, 2) . "s\n";
        $state['last_wc'] = $now;
    }
    
    // ✅ Salvar estado
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    
    // ✅ Liberar lock
    if ($lockHandle !== null && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    
    // ✅ Salvar histórico do cron
    cronSaveHistory();
    
    echo "[" . date('Y-m-d H:i:s') . "] Todos os jobs executados com sucesso\n";
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar jobs agendados: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    
    $cronStatus = 'error';
    $cronError = $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
    cronSaveHistory();
    
    // ✅ Liberar lock em caso de erro
    if (isset($lockHandle) && $lockHandle !== null && is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
    
    exit(1);
}

