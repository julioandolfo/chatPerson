<?php
/**
 * Gerar manual a partir de conversas (CLI) — permite lotes maiores que o teto da UI.
 *
 * Uso:
 *   php public/scripts/generate-manual.php <agentId|0> <dateFrom> <dateTo> <limit> "<titulo>"
 *
 * Exemplos:
 *   php public/scripts/generate-manual.php 7 2026-06-01 2026-06-17 100 "Manual CS - Gustavo"
 *   php public/scripts/generate-manual.php 0 2026-05-01 2026-05-31 150 "Manual CS - Equipe"
 *   (agentId 0 = todos os agentes)
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\ManualGeneratorService;

$agentId  = (int)($argv[1] ?? 0);
$dateFrom = $argv[2] ?? date('Y-m-01');
$dateTo   = $argv[3] ?? date('Y-m-d');
$limit    = (int)($argv[4] ?? 50);
$title    = $argv[5] ?? ('Manual de Processos — ' . date('d/m/Y'));

@set_time_limit(0);

echo "Gerando manual...\n";
echo "  Agente: " . ($agentId ?: 'TODOS') . " | Período: {$dateFrom} .. {$dateTo} | Limite: {$limit}\n";

$preview = ManualGeneratorService::preview($agentId ?: null, $dateFrom, $dateTo, $limit);
echo "  Conversas elegíveis: {$preview['conversations']} | custo estimado: \${$preview['estimated_cost']}\n";

if ($preview['conversations'] < 1) {
    echo "Nenhuma conversa elegível. Abortando.\n";
    exit(1);
}

try {
    $jobId = ManualGeneratorService::createJob([
        'title' => $title,
        'agent_id' => $agentId ?: null,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'conversation_limit' => $limit,
        'created_by' => null,
    ]);
    echo "  Job #{$jobId} criado. Processando (pode levar alguns minutos)...\n";

    $manualId = ManualGeneratorService::runJob($jobId);
    echo "✅ Manual gerado! ID: {$manualId}\n";
    echo "   Acesse em: /manuals/view?id={$manualId}\n";
} catch (\Throwable $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
