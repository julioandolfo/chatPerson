<?php
/**
 * Indexação do Copiloto — embute conversas resolvidas na base de busca.
 *
 * Uso:
 *   php public/scripts/index-copilot.php             # indexa um lote de pendentes
 *   php public/scripts/index-copilot.php <batch>     # tamanho do lote (default 50)
 *   php public/scripts/index-copilot.php conv <id>   # indexa uma conversa específica
 *
 * Cron sugerido (a cada minuto):
 *   * * * * * php /caminho/public/scripts/index-copilot.php 50 >> /var/log/copilot-index.log 2>&1
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\CopilotService;

@set_time_limit(0);

if (($argv[1] ?? '') === 'conv') {
    $id = (int)($argv[2] ?? 0);
    $ok = $id ? CopilotService::indexConversation($id) : false;
    echo "[" . date('Y-m-d H:i:s') . "] Conversa {$id}: " . ($ok ? 'indexada' : 'pulada') . "\n";
    exit(0);
}

// Modo "tudo": indexa em laço até zerar os pendentes (uso: ... index-copilot.php all).
if (($argv[1] ?? '') === 'all') {
    $total = 0;
    while (true) {
        $done = CopilotService::indexPending(50);
        $total += $done;
        $stats = CopilotService::stats();
        echo "[" . date('Y-m-d H:i:s') . "] +{$done} (acum: {$total}) | base: {$stats['indexed']} | pendentes: {$stats['pending']}\n";
        if ($done < 1 || $stats['pending'] < 1) {
            break;
        }
    }
    echo "✅ Concluído. Total indexado nesta execução: {$total}\n";
    exit(0);
}

$batch = (int)($argv[1] ?? 50);
$done = CopilotService::indexPending($batch);
$stats = CopilotService::stats();
echo "[" . date('Y-m-d H:i:s') . "] Indexadas {$done} | total na base: {$stats['indexed']} | pendentes: {$stats['pending']}\n";
