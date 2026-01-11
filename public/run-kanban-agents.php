#!/usr/bin/env php
<?php

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// ✅ CRÍTICO: Definir timezone ANTES de qualquer operação com data/hora
date_default_timezone_set('America/Sao_Paulo');

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

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

