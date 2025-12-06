<?php
/**
 * Job AICostMonitoringJob
 * Monitora custos e limites de agentes de IA
 */

namespace App\Jobs;

use App\Services\AICostControlService;

class AICostMonitoringJob
{
    /**
     * Executar monitoramento de custos
     */
    public static function run(): void
    {
        try {
            echo "[" . date('Y-m-d H:i:s') . "] Iniciando AICostMonitoringJob...\n";
            
            // Verificar custos de todos os agentes e criar alertas
            AICostControlService::checkAllAgentsCosts();
            
            // Resetar limites mensais se necessário (primeiro dia do mês)
            $currentDay = (int)date('d');
            if ($currentDay === 1) {
                echo "[" . date('Y-m-d H:i:s') . "] Resetando limites mensais...\n";
                AICostControlService::resetMonthlyLimits();
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] AICostMonitoringJob executado com sucesso\n";
            error_log("AICostMonitoringJob executado com sucesso");
        } catch (\Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERRO no AICostMonitoringJob: " . $e->getMessage() . "\n";
            error_log("Erro ao executar AICostMonitoringJob: " . $e->getMessage());
            throw $e;
        }
    }
}

