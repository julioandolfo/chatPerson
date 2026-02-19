#!/usr/bin/env php
<?php

/**
 * Processar Fila de Download de Mídia (CRON)
 * 
 * Este script processa a fila de downloads/uploads de mídia com rate limiting.
 * Processa UM item por vez para respeitar os limites do WhatsApp CDN.
 * 
 * Adicionar no crontab (a cada 30 segundos):
 * * * * * cd /var/www/html && php public/scripts/process-media-queue.php >> /var/log/media-queue.log 2>&1
 * * * * * sleep 30 && cd /var/www/html && php public/scripts/process-media-queue.php >> /var/log/media-queue.log 2>&1
 * 
 * Ou rodar em loop contínuo:
 * php public/scripts/process-media-queue.php --loop
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\MediaQueueService;
use App\Models\MediaQueue;

$isLoop = in_array('--loop', $argv ?? []);
$maxLoopTime = 300; // 5 minutos máximo em modo loop

try {
    if ($isLoop) {
        $startTime = time();
        $processed = 0;
        
        echo "[" . date('Y-m-d H:i:s') . "] Modo loop iniciado (máx {$maxLoopTime}s)\n";
        
        while (time() - $startTime < $maxLoopTime) {
            $result = MediaQueueService::processQueue();
            
            if ($result['processed'] > 0) {
                $processed += $result['processed'];
                echo "[" . date('Y-m-d H:i:s') . "] Processado: success={$result['success']}, errors={$result['errors']}\n";
            }
            
            // Se não há nada na fila, esperar mais
            if ($result['processed'] === 0) {
                sleep(5);
            } else {
                sleep(2);
            }
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Loop encerrado. Total processado: {$processed}\n";
        
    } else {
        $result = MediaQueueService::processQueue();
        
        if ($result['processed'] > 0 || $result['errors'] > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] ";
            echo "Processados: {$result['processed']} | ";
            echo "Sucesso: {$result['success']} | ";
            echo "Erros: {$result['errors']}\n";
        }
        
        // Limpar itens antigos uma vez por dia (verificar pela hora)
        if (date('H:i') === '03:00') {
            $cleaned = MediaQueue::cleanup(7);
            if ($cleaned > 0) {
                echo "[" . date('Y-m-d H:i:s') . "] Limpeza: {$cleaned} itens removidos\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
