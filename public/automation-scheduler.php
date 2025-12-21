<?php
/**
 * Scheduler de Automações
 * 
 * Este script deve ser executado periodicamente via cronjob
 * Recomendado: a cada 1 minuto
 * 
 * ==== CONFIGURAÇÃO DO CRONJOB ====
 * 
 * Linux/Mac (Crontab):
 * * * * * * cd /path/to/project && php public/automation-scheduler.php >> storage/logs/scheduler.log 2>&1
 * 
 * Windows (Task Scheduler):
 * 1. Abrir "Agendador de Tarefas"
 * 2. Criar Nova Tarefa
 * 3. Nome: "Chat Automation Scheduler"
 * 4. Gatilho: Repetir a cada 1 minuto
 * 5. Ação: Iniciar programa
 *    - Programa: C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe
 *    - Argumentos: public/automation-scheduler.php
 *    - Iniciar em: C:\laragon\www\chat
 * 
 * ==== TESTE MANUAL ====
 * 
 * Para testar manualmente:
 * php public/automation-scheduler.php
 * 
 * ==== LOGS ====
 * 
 * Os logs detalhados são salvos em:
 * - storage/logs/automation-YYYY-MM-DD.log (via Logger::automation)
 * - storage/logs/scheduler.log (saída do cronjob)
 */

// Carregar configurações
$appConfig = require __DIR__ . '/../config/app.php';

// Carregar autoloader
require_once __DIR__ . '/../app/Helpers/autoload.php';

// Definir timezone
date_default_timezone_set($appConfig['timezone']);

// Definir encoding
mb_internal_encoding('UTF-8');

use App\Services\AutomationSchedulerService;
use App\Helpers\Logger;

$startTime = microtime(true);

echo str_repeat("=", 80) . "\n";
echo "[" . date('Y-m-d H:i:s') . "] AUTOMATION SCHEDULER INICIADO\n";
echo str_repeat("=", 80) . "\n";

Logger::automation(str_repeat("=", 80));
Logger::automation("AUTOMATION SCHEDULER INICIADO em " . date('Y-m-d H:i:s'));
Logger::automation(str_repeat("=", 80));

try {
    // 1. Processar gatilhos baseados em tempo (agendados)
    echo "\n[" . date('H:i:s') . "] Processando gatilhos 'time_based'...\n";
    AutomationSchedulerService::processTimeBasedTriggers();
    
    // 2. Processar gatilhos de tempo sem resposta do cliente
    echo "[" . date('H:i:s') . "] Processando gatilhos 'no_customer_response'...\n";
    AutomationSchedulerService::processNoCustomerResponseTriggers();
    
    // 3. Processar gatilhos de tempo sem resposta do agente
    echo "[" . date('H:i:s') . "] Processando gatilhos 'no_agent_response'...\n";
    AutomationSchedulerService::processNoAgentResponseTriggers();
    
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Scheduler executado com sucesso!\n";
    echo "Tempo de execução: {$executionTime}s\n";
    echo str_repeat("=", 80) . "\n\n";
    
    Logger::automation(str_repeat("=", 80));
    Logger::automation("✅ Scheduler executado com sucesso! Tempo: {$executionTime}s");
    Logger::automation(str_repeat("=", 80) . "\n");
    
} catch (\Exception $e) {
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO NO SCHEDULER\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "Tempo até erro: {$executionTime}s\n";
    echo str_repeat("=", 80) . "\n\n";
    
    Logger::automation(str_repeat("=", 80));
    Logger::automation("❌ ERRO no scheduler: " . $e->getMessage());
    Logger::automation("Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")");
    Logger::automation("Stack trace:\n" . $e->getTraceAsString());
    Logger::automation(str_repeat("=", 80) . "\n");
    
    // Re-throw para que o cronjob registre o erro
    throw $e;
}

