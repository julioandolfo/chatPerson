#!/usr/bin/env php
<?php

/**
 * Processar Fila de Coaching em Tempo Real (CRON)
 * 
 * Este script processa a fila de coaching uma vez e termina.
 * Ideal para ser executado via CRON a cada 5-10 segundos.
 * 
 * Adicionar no crontab:
 * * * * * * cd /var/www/html && php public/scripts/process-coaching-queue.php >> /var/log/coaching-cron.log 2>&1
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\RealtimeCoachingService;

try {
    // Processar fila (apenas uma vez)
    $result = RealtimeCoachingService::processQueue();
    
    // Log apenas se houver atividade
    if ($result['processed'] > 0 || $result['errors'] > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] ";
        echo "Processados: {$result['processed']} | ";
        echo "Erros: {$result['errors']} | ";
        echo "Fila: {$result['queue_size']}\n";
    }
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
