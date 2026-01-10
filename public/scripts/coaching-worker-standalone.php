#!/usr/bin/env php
<?php

/**
 * Worker para processar fila de Coaching em Tempo Real (STANDALONE)
 * 
 * Versão standalone que não depende do Composer.
 * Usa o autoloader nativo do sistema.
 * 
 * Uso: php public/scripts/coaching-worker-standalone.php
 * Cron: * * * * * cd /var/www/html && php public/scripts/coaching-worker-standalone.php >> storage/logs/coaching-worker.log 2>&1
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

// Arquivo de log do worker
$workerLogFile = $logDir . '/coaching-worker.log';

function logWorker($message) {
    global $workerLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] {$message}\n";
    file_put_contents($workerLogFile, $line, FILE_APPEND);
    echo $line; // Também exibe no STDOUT
}

logWorker("🚀 Coaching Worker iniciado (Standalone)");
logWorker("📁 Root Dir: {$rootDir}");
logWorker("📝 Log File: {$workerLogFile}");

$cycleCount = 0;
$totalProcessed = 0;
$totalErrors = 0;

// Loop infinito
while (true) {
    $cycleCount++;
    
    try {
        // Processar fila
        $result = RealtimeCoachingService::processQueue();
        
        $totalProcessed += $result['processed'];
        $totalErrors += $result['errors'];
        
        if ($result['processed'] > 0 || $result['errors'] > 0) {
            $msg = "✅ Processados: {$result['processed']} | ";
            $msg .= "⏭️ Pulados: {$result['skipped']} | ";
            $msg .= "❌ Erros: {$result['errors']} | ";
            $msg .= "📋 Fila: {$result['queue_size']}";
            logWorker($msg);
        }
        
        // A cada 20 ciclos (1 minuto), mostrar estatísticas
        if ($cycleCount % 20 === 0) {
            $msg = "📊 Total processado: {$totalProcessed} | ";
            $msg .= "Total erros: {$totalErrors} | ";
            $msg .= "Ciclos: {$cycleCount}";
            logWorker($msg);
        }
        
    } catch (\Exception $e) {
        $msg = "❌ ERRO: " . $e->getMessage();
        logWorker($msg);
        logWorker("Stack trace: " . $e->getTraceAsString());
        $totalErrors++;
    }
    
    // Aguardar 3 segundos antes do próximo ciclo
    sleep(3);
    
    // Verificar se deve parar (criar arquivo stop.txt para parar gracefully)
    $stopFile = $rootDir . '/storage/coaching-worker-stop.txt';
    if (file_exists($stopFile)) {
        logWorker("🛑 Arquivo de parada detectado. Encerrando...");
        unlink($stopFile);
        break;
    }
}

logWorker("👋 Coaching Worker finalizado");
logWorker("Total processado: {$totalProcessed}");
logWorker("Total erros: {$totalErrors}");
logWorker("Total ciclos: {$cycleCount}");
