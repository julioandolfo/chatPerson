<?php
/**
 * Job AIFallbackMonitoringJob
 * Executa monitoramento de fallback de IA periodicamente
 */

namespace App\Jobs;

use App\Services\AIFallbackMonitoringService;

class AIFallbackMonitoringJob
{
    /**
     * Executar job de monitoramento de fallback
     */
    public static function run(): void
    {
        try {
            $results = AIFallbackMonitoringService::checkAndProcessStuckConversations();
            
            error_log("AIFallbackMonitoringJob executado: " . json_encode([
                'checked' => $results['checked'],
                'stuck_found' => $results['stuck_found'],
                'reprocessed' => $results['reprocessed'],
                'escalated' => $results['escalated'],
                'ignored_closing' => $results['ignored_closing'],
                'errors' => count($results['errors'])
            ]));
        } catch (\Exception $e) {
            error_log("Erro ao executar AIFallbackMonitoringJob: " . $e->getMessage());
            throw $e;
        }
    }
}

