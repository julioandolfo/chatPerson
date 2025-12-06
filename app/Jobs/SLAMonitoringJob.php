<?php
/**
 * Job SLAMonitoringJob
 * Executa monitoramento de SLA periodicamente
 */

namespace App\Jobs;

use App\Services\SLAMonitoringService;

class SLAMonitoringJob
{
    /**
     * Executar job de monitoramento de SLA
     */
    public static function run(): void
    {
        try {
            $results = SLAMonitoringService::checkAndProcessSLA();
            
            error_log("SLA Monitoring executado: " . json_encode($results));
        } catch (\Exception $e) {
            error_log("Erro ao executar SLAMonitoringJob: " . $e->getMessage());
            throw $e;
        }
    }
}

