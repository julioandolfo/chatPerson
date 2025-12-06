<?php
/**
 * Script para executar automações agendadas
 * Deve ser executado via cron job a cada minuto
 * 
 * Exemplo de cron:
 * * * * * * php /caminho/para/scripts/run-scheduled-automations.php
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Helpers/autoload.php';

use App\Services\AutomationService;

try {
    echo "[" . date('Y-m-d H:i:s') . "] Executando automações agendadas...\n";
    
    AutomationService::executeScheduledAutomations();
    
    echo "[" . date('Y-m-d H:i:s') . "] Automações agendadas executadas com sucesso!\n";
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    error_log("Erro ao executar automações agendadas: " . $e->getMessage());
    exit(1);
}

