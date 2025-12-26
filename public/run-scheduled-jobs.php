<?php
/**
 * Script para executar jobs agendados
 * 
 * ⚠️ IMPORTANTE: Configurar para executar FREQUENTEMENTE (a cada 1 minuto) para processar buffers de IA!
 * 
 * Configurar no cron para executar a cada 1 minuto:
 * Linux (crontab): * * * * * php /caminho/para/public/run-scheduled-jobs.php
 * 
 * Windows (Agendador de Tarefas):
 * - Gatilho: Repetir tarefa a cada 1 minuto
 * - Ação: C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe C:\laragon\www\chat\public\run-scheduled-jobs.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Jobs\SLAMonitoringJob;
use App\Jobs\FollowupJob;
use App\Jobs\AICostMonitoringJob;
use App\Jobs\AutomationDelayJob;
use App\Jobs\AIFallbackMonitoringJob;

try {
    // Executar monitoramento de SLA
    echo "[" . date('Y-m-d H:i:s') . "] Executando SLAMonitoringJob...\n";
    SLAMonitoringJob::run();
    echo "[" . date('Y-m-d H:i:s') . "] SLAMonitoringJob concluído\n";
    
    // Executar followups (a cada execução, mas pode ser configurado para menos frequente)
    // Executar apenas uma vez por hora (verificar minuto atual)
    $currentMinute = (int)date('i');
    if ($currentMinute % 60 === 0 || isset($_GET['force_followup'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Executando FollowupJob...\n";
        FollowupJob::run();
        echo "[" . date('Y-m-d H:i:s') . "] FollowupJob concluído\n";
    }
    
    // Executar monitoramento de custos de IA (a cada hora)
    if ($currentMinute % 60 === 0 || isset($_GET['force_cost_check'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Executando AICostMonitoringJob...\n";
        AICostMonitoringJob::run();
        echo "[" . date('Y-m-d H:i:s') . "] AICostMonitoringJob concluído\n";
    }
    
    // Executar delays de automações (a cada execução)
    echo "[" . date('Y-m-d H:i:s') . "] Executando AutomationDelayJob...\n";
    AutomationDelayJob::run();
    echo "[" . date('Y-m-d H:i:s') . "] AutomationDelayJob concluído\n";
    
    // ✅ NOVO: Processar buffers de mensagens da IA (a cada execução - crítico!)
    echo "[" . date('Y-m-d H:i:s') . "] Processando buffers de IA...\n";
    include __DIR__ . '/process-ai-buffers.php';
    echo "[" . date('Y-m-d H:i:s') . "] Buffers de IA processados\n";
    
    // Executar monitoramento de fallback de IA (a cada execução, mas respeita intervalo configurado)
    echo "[" . date('Y-m-d H:i:s') . "] Executando AIFallbackMonitoringJob...\n";
    AIFallbackMonitoringJob::run();
    echo "[" . date('Y-m-d H:i:s') . "] AIFallbackMonitoringJob concluído\n";
    
    echo "[" . date('Y-m-d H:i:s') . "] Todos os jobs executados com sucesso\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar jobs agendados: " . $e->getMessage());
    exit(1);
}

