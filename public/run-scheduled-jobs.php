<?php
/**
 * Script para executar jobs agendados
 * 
 * Configurar no cron para executar a cada 5 minutos:
 * Exemplo de crontab: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /caminho/para/public/run-scheduled-jobs.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar configurações
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

use App\Jobs\SLAMonitoringJob;
use App\Jobs\FollowupJob;
use App\Jobs\AICostMonitoringJob;
use App\Jobs\AutomationDelayJob;

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
    
    echo "[" . date('Y-m-d H:i:s') . "] Todos os jobs executados com sucesso\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar jobs agendados: " . $e->getMessage());
    exit(1);
}

