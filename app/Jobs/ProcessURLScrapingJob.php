<?php
/**
 * Job ProcessURLScrapingJob
 * Processa URLs pendentes em background
 */

namespace App\Jobs;

use App\Models\AIUrlScraping;
use App\Services\URLScrapingService;
use App\Helpers\Logger;

class ProcessURLScrapingJob
{
    /**
     * Processar URLs pendentes
     * 
     * @param int $limit Número máximo de URLs para processar
     * @return array Estatísticas do processamento
     */
    public static function processPending(int $limit = 10): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            // Buscar URLs pendentes
            $pendingUrls = AIUrlScraping::getPending(null, $limit);

            Logger::info("ProcessURLScrapingJob::processPending - Processando " . count($pendingUrls) . " URLs pendentes");

            foreach ($pendingUrls as $url) {
                try {
                    $success = URLScrapingService::processUrl($url['id']);
                    
                    if ($success) {
                        $stats['success']++;
                    } else {
                        $stats['failed']++;
                        $stats['errors'][] = "URL {$url['url']}: Falha no processamento";
                    }
                    
                    $stats['processed']++;
                    
                    // Pequeno delay para não sobrecarregar
                    usleep(500000); // 0.5 segundos
                    
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "URL {$url['url']}: " . $e->getMessage();
                    Logger::error("ProcessURLScrapingJob::processPending - Erro ao processar URL {$url['id']}: " . $e->getMessage());
                }
            }

            Logger::info("ProcessURLScrapingJob::processPending - Concluído: {$stats['success']} sucesso, {$stats['failed']} falhas");

        } catch (\Exception $e) {
            Logger::error("ProcessURLScrapingJob::processPending - Erro geral: " . $e->getMessage());
            $stats['errors'][] = "Erro geral: " . $e->getMessage();
        }

        return $stats;
    }

    /**
     * Processar URLs de um agente específico
     */
    public static function processByAgent(int $agentId, int $limit = 10): array
    {
        $stats = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        try {
            $pendingUrls = AIUrlScraping::getPending($agentId, $limit);

            foreach ($pendingUrls as $url) {
                try {
                    $success = URLScrapingService::processUrl($url['id']);
                    
                    if ($success) {
                        $stats['success']++;
                    } else {
                        $stats['failed']++;
                    }
                    
                    $stats['processed']++;
                    usleep(500000);
                    
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "URL {$url['url']}: " . $e->getMessage();
                }
            }

        } catch (\Exception $e) {
            Logger::error("ProcessURLScrapingJob::processByAgent - Erro: " . $e->getMessage());
        }

        return $stats;
    }
}

