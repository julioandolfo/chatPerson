#!/usr/bin/env php
<?php

/**
 * Worker para processar fila de Coaching em Tempo Real
 * 
 * Este script deve rodar continuamente em background processando a fila.
 * 
 * Opções de execução:
 * 1. Cron job a cada 5 segundos (menos eficiente)
 * 2. Supervisor (recomendado)
 * 3. Screen/tmux (desenvolvimento)
 * 
 * Uso: php public/scripts/coaching-worker.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\RealtimeCoachingService;

echo "[" . date('Y-m-d H:i:s') . "] 🚀 Coaching Worker iniciado\n";

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
            echo "[" . date('Y-m-d H:i:s') . "] ";
            echo "✅ Processados: {$result['processed']} | ";
            echo "⏭️ Pulados: {$result['skipped']} | ";
            echo "❌ Erros: {$result['errors']} | ";
            echo "📋 Fila: {$result['queue_size']}\n";
        }
        
        // A cada 20 ciclos (1 minuto), mostrar estatísticas
        if ($cycleCount % 20 === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] ";
            echo "📊 Total processado: {$totalProcessed} | ";
            echo "Total erros: {$totalErrors} | ";
            echo "Ciclos: {$cycleCount}\n";
        }
        
    } catch (\Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
        $totalErrors++;
    }
    
    // Aguardar 3 segundos antes do próximo ciclo
    sleep(3);
    
    // Verificar se deve parar (opcional: criar arquivo stop.txt para parar gracefully)
    if (file_exists(__DIR__ . '/coaching-worker-stop.txt')) {
        echo "[" . date('Y-m-d H:i:s') . "] 🛑 Arquivo de parada detectado. Encerrando...\n";
        unlink(__DIR__ . '/coaching-worker-stop.txt');
        break;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] 👋 Coaching Worker finalizado\n";
echo "Total processado: {$totalProcessed}\n";
echo "Total erros: {$totalErrors}\n";
echo "Total ciclos: {$cycleCount}\n";
