<?php
/**
 * Script para executar agentes Kanban periodicamente
 * Deve ser executado via cron (ex: a cada 5 minutos)
 * 
 * Exemplo de cron:
 * */5 * * * * php /caminho/para/public/run-kanban-agents.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\KanbanAgentService;
use App\Helpers\Logger;

try {
    Logger::info("run-kanban-agents.php - Iniciando execução de agentes Kanban");
    
    $results = KanbanAgentService::executeReadyAgents();
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
            Logger::info("run-kanban-agents.php - Agente {$result['agent_id']} ({$result['agent_name']}) executado com sucesso: {$result['message']}");
        } else {
            $errorCount++;
            Logger::error("run-kanban-agents.php - Erro ao executar agente {$result['agent_id']} ({$result['agent_name']}): {$result['message']}");
        }
    }
    
    echo "✅ Execução concluída: {$successCount} sucesso(s), {$errorCount} erro(s)\n";
    Logger::info("run-kanban-agents.php - Execução concluída: {$successCount} sucesso(s), {$errorCount} erro(s)");
    
} catch (\Exception $e) {
    echo "❌ Erro fatal: " . $e->getMessage() . "\n";
    Logger::error("run-kanban-agents.php - Erro fatal: " . $e->getMessage());
    exit(1);
}

