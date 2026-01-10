#!/usr/bin/env php
<?php

/**
 * Processar Fila de Coaching em Tempo Real - CRON (STANDALONE)
 * 
 * Versão standalone que não depende do Composer.
 * Este script processa a fila uma vez e termina.
 * Ideal para CRON.
 * 
 * Cron (a cada minuto):
 * * * * * * cd /var/www/html && php public/scripts/process-coaching-queue-standalone.php >> storage/logs/coaching-cron.log 2>&1
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Carregar bootstrap (que já tem o autoloader)
require_once $rootDir . '/config/bootstrap.php';

use App\Services\RealtimeCoachingService;

// Garantir que o diretório de logs existe
$logDir = $rootDir . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

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
    
    exit(0);
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
