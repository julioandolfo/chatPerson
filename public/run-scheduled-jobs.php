#!/usr/bin/env php
<?php
// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

// ✅ Limite de execução: 90 segundos máximo (evita travar indefinidamente)
set_time_limit(90);

// ✅ Forçar output imediato (sem buffer) para debug via navegador
if (php_sapi_name() !== 'cli') {
    @header('Content-Type: text/plain; charset=utf-8');
    @header('X-Accel-Buffering: no'); // Nginx
    @ini_set('output_buffering', '0');
    @ini_set('zlib.output_compression', '0');
    if (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);
}

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

/**
 * Output com flush imediato
 */
function cronEcho(string $msg): void
{
    echo "[" . date('Y-m-d H:i:s') . "] {$msg}\n";
    if (php_sapi_name() !== 'cli') {
        @flush();
    }
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
    $written = @file_put_contents($cronHistoryFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        echo "[" . date('Y-m-d H:i:s') . "] AVISO: Não foi possível salvar histórico em {$cronHistoryFile}\n";
        // Tentar corrigir permissões
        @chmod($historyDir, 0777);
        @chmod($cronHistoryFile, 0666);
        @file_put_contents($cronHistoryFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

/**
 * Executar um job com proteção de timeout individual
 */
function cronRunJob(string $jobName, callable $callback, int $timeoutSeconds = 30): void
{
    cronEcho("Executando {$jobName}...");
    $startTime = microtime(true);
    
    try {
        // Alarme de timeout (apenas Linux/CLI)
        $hasAlarm = function_exists('pcntl_alarm');
        if ($hasAlarm) {
            pcntl_alarm($timeoutSeconds);
        }
        
        $callback();
        
        if ($hasAlarm) {
            pcntl_alarm(0); // Cancelar alarme
        }
        
        $duration = microtime(true) - $startTime;
        cronLogJob($jobName, $duration);
        cronEcho("{$jobName} concluído em " . round($duration, 2) . "s");
    } catch (\Throwable $e) {
        $duration = microtime(true) - $startTime;
        cronLogJob($jobName, $duration, 'error', $e->getMessage());
        cronEcho("❌ {$jobName} ERRO ({$duration}s): " . $e->getMessage());
    }
}

try {
    // ✅ Garantir que diretórios de storage existem
    $storageDirs = [
        $rootDir . '/storage/cache',
        $rootDir . '/storage/logs',
    ];
    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
    
    cronEcho("Iniciando execução dos jobs...");
    
    // ✅ Arquivo de estado para controlar última execução de cada job
    $stateFile = __DIR__ . '/../storage/cache/jobs_state.json';
    $state = [];
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true) ?: [];
    }
    
    $now = time();
    
    // ========== JOBS CRÍTICOS (a cada execução) ==========
    
    // ✅ Processar buffers de IA (CRÍTICO - executar sempre, com LOCK para evitar duplicação)
    cronRunJob('AIBuffers', function() {
        $bufferDir = dirname(__DIR__) . '/storage/ai_buffers/';
        if (!is_dir($bufferDir)) { @mkdir($bufferDir, 0755, true); }
        $bufferFiles = glob($bufferDir . 'buffer_*.json') ?: [];
        if (empty($bufferFiles)) { return; }
        
        $now = time();
        foreach ($bufferFiles as $bufferFile) {
            $lockFp = null;
            try {
                $conversationId = (int)str_replace(['buffer_', '.json'], '', basename($bufferFile));
                
                // ✅ LOCK EXCLUSIVO NÃO-BLOQUEANTE para evitar processamento duplicado
                $lockFile = $bufferDir . 'lock_' . $conversationId . '.lock';
                $lockFp = fopen($lockFile, 'c');
                if (!$lockFp || !flock($lockFp, LOCK_EX | LOCK_NB)) {
                    if ($lockFp) fclose($lockFp);
                    continue; // Outro processador já está tratando
                }
                
                // Re-verificar se buffer ainda existe após adquirir lock
                if (!file_exists($bufferFile)) {
                    flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
                    continue;
                }
                
                $bufferData = json_decode(@file_get_contents($bufferFile), true);
                if (!$bufferData) { @unlink($bufferFile); flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile); continue; }
                
                $agentId = $bufferData['agent_id'] ?? null;
                $messages = $bufferData['messages'] ?? [];
                $expiresAt = $bufferData['expires_at'] ?? 0;
                
                if (!$conversationId || !$agentId || empty($messages)) { @unlink($bufferFile); flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile); continue; }
                
                $conversation = \App\Models\Conversation::find($conversationId);
                if (empty($conversation) || empty($conversation['contact_id'])) { @unlink($bufferFile); flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile); continue; }
                
                $agentModel = \App\Models\AIAgent::find($agentId);
                if (empty($agentModel) || empty($agentModel['enabled'])) { @unlink($bufferFile); flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile); continue; }
                
                if ($expiresAt > $now) { flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile); continue; } // Ainda não expirou
                
                $groupedMessage = implode("\n\n", array_map(fn($msg) => $msg['content'], $messages));
                
                // ✅ DELETAR buffer ANTES de processar
                @unlink($bufferFile);
                
                \App\Services\AIAgentService::processMessage($conversationId, $agentId, $groupedMessage);
                
                flock($lockFp, LOCK_UN); fclose($lockFp); @unlink($lockFile);
            } catch (\Throwable $e) {
                \App\Helpers\Logger::aiTools("[BUFFER ERROR] " . basename($bufferFile) . ": " . $e->getMessage());
                @unlink($bufferFile);
                if ($lockFp) { flock($lockFp, LOCK_UN); fclose($lockFp); }
            }
        }
    }, 30);
    
    // ✅ Executar delays de automações (CRÍTICO - executar sempre)
    cronRunJob('AutomationDelayJob', function() {
        AutomationDelayJob::run();
    }, 15);
    
    // ✅ Verificar timeouts de chatbot (CRÍTICO - executar sempre)
    cronRunJob('ChatbotTimeoutJob', function() {
        ChatbotTimeoutJob::run();
    }, 15);
    
    // ========== JOBS IMPORTANTES (a cada 2-3 minutos) ==========
    
    // ✅ Monitoramento de SLA (REDUZIDO: a cada 3 minutos)
    $lastSLA = $state['last_sla'] ?? 0;
    if (($now - $lastSLA) >= 180 || isset($_GET['force_sla'])) {
        cronRunJob('SLAMonitoringJob', function() {
            SLAMonitoringJob::run();
        }, 15);
        $state['last_sla'] = $now;
    }
    
    // ========== JOBS MODERADOS (a cada 10-15 minutos) ==========
    
    // ✅ Monitoramento de fallback de IA (a cada 10 minutos)
    $lastFallback = $state['last_fallback'] ?? 0;
    if (($now - $lastFallback) >= 600 || isset($_GET['force_fallback'])) {
        cronRunJob('AIFallbackMonitoringJob', function() {
            AIFallbackMonitoringJob::run();
        }, 15);
        $state['last_fallback'] = $now;
    }
    
    // ❌ Followups DESATIVADOS — não enviar mensagens automáticas
    // Para reativar, descomente o bloco abaixo:
    // $lastFollowup = $state['last_followup'] ?? 0;
    // if (($now - $lastFollowup) >= 900 || isset($_GET['force_followup'])) {
    //     cronRunJob('FollowupJob', function() {
    //         FollowupJob::run();
    //     }, 15);
    //     $state['last_followup'] = $now;
    // }
    
    // ========== JOBS LEVES (a cada hora) ==========
    
    // ✅ Monitoramento de custos de IA (a cada hora)
    $lastCost = $state['last_cost'] ?? 0;
    if (($now - $lastCost) >= 3600 || isset($_GET['force_cost_check'])) {
        cronRunJob('AICostMonitoringJob', function() {
            AICostMonitoringJob::run();
        }, 15);
        $state['last_cost'] = $now;
    }
    
    // ✅ Sincronização WooCommerce (a cada hora)
    $lastWC = $state['last_wc'] ?? 0;
    if (($now - $lastWC) >= 3600 || isset($_GET['force_wc_sync'])) {
        cronRunJob('WooCommerceSyncJob', function() {
            WooCommerceSyncJob::run();
        }, 15);
        $state['last_wc'] = $now;
    }
    
    // ✅ Salvar estado
    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    
    // ✅ Salvar histórico do cron
    cronSaveHistory();
    
    $totalTime = round(microtime(true) - $cronRunStart, 2);
    cronEcho("✅ Todos os jobs executados com sucesso em {$totalTime}s");
} catch (\Throwable $e) {
    cronEcho("❌ ERRO FATAL: " . $e->getMessage());
    error_log("Erro ao executar jobs agendados: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
    
    $cronStatus = 'error';
    $cronError = $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
    cronSaveHistory();
    
    exit(1);
}

