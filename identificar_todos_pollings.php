<?php
/**
 * Identificar TODOS os pollings ativos no sistema
 * Execute: php identificar_todos_pollings.php
 */

echo "=== IDENTIFICANDO TODOS OS POLLINGS DO SISTEMA ===\n\n";

$jsFiles = [
    'views/conversations/index.php',
    'public/assets/js/custom/sla-indicator.js',
    'public/assets/js/coaching-inline.js',
    'public/assets/js/realtime-coaching.js',
    'public/assets/js/activity-tracker.js',
    'public/assets/js/websocket-client.js',
    'views/dashboard/index.php',
];

$pollings = [];

foreach ($jsFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (!file_exists($fullPath)) {
        continue;
    }
    
    $content = file_get_contents($fullPath);
    
    // Procurar setInterval
    preg_match_all('/setInterval\s*\(\s*(?:function\s*\(|.*?=>|[a-zA-Z_]+).*?,\s*(\d+)\s*\)/s', $content, $matches, PREG_OFFSET_CAPTURE);
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            $interval = (int)$match[0];
            $position = $match[1];
            
            // Pegar contexto ao redor
            $start = max(0, $position - 200);
            $contextBefore = substr($content, $start, 200);
            $contextAfter = substr($content, $position, 200);
            
            // Tentar identificar função
            preg_match('/function\s+([a-zA-Z_]\w*)|const\s+([a-zA-Z_]\w*)\s*=|let\s+([a-zA-Z_]\w*)\s*=|var\s+([a-zA-Z_]\w*)\s*=/', $contextBefore, $funcMatch);
            $functionName = $funcMatch[1] ?? $funcMatch[2] ?? $funcMatch[3] ?? $funcMatch[4] ?? 'unknown';
            
            // Tentar identificar URL/endpoint
            preg_match('/fetch\s*\(\s*[\'"]([^\'"]+)[\'"]|url:\s*[\'"]([^\'"]+)[\'"]/', $contextAfter, $urlMatch);
            $endpoint = $urlMatch[1] ?? $urlMatch[2] ?? 'unknown';
            
            $pollings[] = [
                'file' => basename($file),
                'function' => $functionName,
                'endpoint' => $endpoint,
                'interval_ms' => $interval,
                'interval_sec' => round($interval / 1000, 1),
                'queries_per_hour' => round(3600 / ($interval / 1000), 1),
            ];
        }
    }
}

// Ordenar por frequência
usort($pollings, function($a, $b) {
    return $a['interval_ms'] - $b['interval_ms'];
});

echo "TOTAL DE POLLINGS ENCONTRADOS: " . count($pollings) . "\n\n";
echo str_repeat("=", 120) . "\n";
printf("%-30s | %-20s | %-30s | %10s | %10s\n", 
    "Arquivo", "Função", "Endpoint", "Intervalo", "Queries/h");
echo str_repeat("=", 120) . "\n";

foreach ($pollings as $p) {
    printf("%-30s | %-20s | %-30s | %8ss | %9.1f\n",
        substr($p['file'], 0, 30),
        substr($p['function'], 0, 20),
        substr($p['endpoint'], 0, 30),
        $p['interval_sec'],
        $p['queries_per_hour']
    );
}

echo str_repeat("=", 120) . "\n";

// Calcular total de queries/hora por aba
$totalQueriesPerHour = array_sum(array_column($pollings, 'queries_per_hour'));
echo "\nTOTAL DE QUERIES POR HORA POR ABA: " . round($totalQueriesPerHour, 1) . "\n";
echo "TOTAL DE QUERIES POR MINUTO POR ABA: " . round($totalQueriesPerHour / 60, 1) . "\n";
echo "TOTAL DE QUERIES POR SEGUNDO POR ABA: " . round($totalQueriesPerHour / 3600, 2) . "\n\n";

// Identificar os mais frequentes
echo "TOP 5 POLLINGS MAIS FREQUENTES:\n";
echo str_repeat("-", 80) . "\n";

$top5 = array_slice($pollings, 0, 5);
foreach ($top5 as $i => $p) {
    echo ($i+1) . ". {$p['function']} ({$p['file']})\n";
    echo "   Intervalo: {$p['interval_sec']}s\n";
    echo "   Queries/hora: {$p['queries_per_hour']}\n";
    echo "   Impacto: " . round(($p['queries_per_hour'] / $totalQueriesPerHour) * 100, 1) . "%\n\n";
}

// Estimativa com múltiplas abas
echo "\nESTIMATIVA COM MÚLTIPLAS ABAS:\n";
echo str_repeat("-", 80) . "\n";

$queriesPerSecondPerTab = $totalQueriesPerHour / 3600;

for ($tabs = 1; $tabs <= 10; $tabs++) {
    $qps = $queriesPerSecondPerTab * $tabs;
    $status = $qps < 10 ? '🟢' : ($qps < 30 ? '🟡' : '🔴');
    echo sprintf("%2d abas: %6.2f QPS %s\n", $tabs, $qps, $status);
}

echo "\n=== FIM ===\n";
