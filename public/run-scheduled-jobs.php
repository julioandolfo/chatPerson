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
    
    // Executar sincronização de pedidos WooCommerce (a cada hora)
    if ($currentMinute % 60 === 0 || isset($_GET['force_wc_sync'])) {
        echo "[" . date('Y-m-d H:i:s') . "] Executando WooCommerceSyncJob...\n";
        WooCommerceSyncJob::run();
        echo "[" . date('Y-m-d H:i:s') . "] WooCommerceSyncJob concluído\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Todos os jobs executados com sucesso\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar jobs agendados: " . $e->getMessage());
    exit(1);
}

