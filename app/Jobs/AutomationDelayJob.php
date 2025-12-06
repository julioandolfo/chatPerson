<?php
/**
 * Job AutomationDelayJob
 * Processa delays agendados de automações
 */

namespace App\Jobs;

use App\Services\AutomationDelayService;

class AutomationDelayJob
{
    /**
     * Executar job de delays de automação
     */
    public static function run(): void
    {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Iniciando AutomationDelayJob...\n";
            
            // Processar até 100 delays pendentes por execução
            $result = AutomationDelayService::processPendingDelays(100);
            
            $processed = count($result['processed']);
            $errors = count($result['errors']);
            
            echo "[" . date('Y-m-d H:i:s') . "] AutomationDelayJob executado: {$processed} processados, {$errors} erros\n";
            
            if ($errors > 0) {
                error_log("AutomationDelayJob: {$errors} delays falharam");
            }
            
            // Limpar delays antigos (completados ou falhados há mais de 30 dias)
            // Executar apenas uma vez por dia (verificar hora)
            $currentHour = (int)date('H');
            if ($currentHour === 2) { // Executar às 2h da manhã
                $cleaned = AutomationDelayService::cleanOldDelays(30);
                if ($cleaned > 0) {
                    echo "[" . date('Y-m-d H:i:s') . "] Limpeza: {$cleaned} delays antigos removidos\n";
                }
            }
            
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERRO no AutomationDelayJob: " . $e->getMessage() . "\n";
            error_log("Erro ao executar AutomationDelayJob: " . $e->getMessage());
            throw $e;
        }
    }
}

