<?php
/**
 * Analisar requests à API de conversas para ver se cache está sendo usado
 * Este script intercepta logs para ver quais filtros estão sendo enviados
 */

require_once __DIR__ . '/app/Helpers/Database.php';

echo "=== ANÁLISE DE REQUESTS À API DE CONVERSAS ===\n\n";

// 1. Ver últimas linhas do log de conversas
echo "1. ÚLTIMAS REQUISIÇÕES (conversas.log):\n";
echo str_repeat("-", 80) . "\n";

$logFile = __DIR__ . '/storage/logs/conversas.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50); // Últimas 50 linhas
    
    $requestCount = 0;
    $cacheHits = 0;
    $cacheMisses = 0;
    $filterTypes = [];
    
    foreach ($lastLines as $line) {
        // Contar requests
        if (strpos($line, 'ConversationController::index') !== false) {
            $requestCount++;
        }
        
        // Ver filtros enviados
        if (strpos($line, 'GET params') !== false) {
            // Extrair JSON
            if (preg_match('/\{.*\}/', $line, $matches)) {
                $params = json_decode($matches[0], true);
                if ($params) {
                    // Identificar filtros ativos
                    $activeFilters = [];
                    foreach (['search', 'date_from', 'date_to', 'status', 'channel', 'agent_id'] as $filter) {
                        if (isset($params[$filter]) && $params[$filter] !== '') {
                            $activeFilters[] = $filter;
                        }
                    }
                    
                    if (!empty($activeFilters)) {
                        $filtersKey = implode(',', $activeFilters);
                        if (!isset($filterTypes[$filtersKey])) {
                            $filterTypes[$filtersKey] = 0;
                        }
                        $filterTypes[$filtersKey]++;
                    }
                }
            }
        }
        
        echo substr($line, 0, 200) . "\n";
    }
    
    echo "\nRESUMO:\n";
    echo "  Total de requests: $requestCount\n";
    echo "  Combinações de filtros:\n";
    
    if (empty($filterTypes)) {
        echo "    (sem filtros ativos)\n";
    } else {
        arsort($filterTypes);
        foreach ($filterTypes as $filters => $count) {
            echo "    [{$filters}]: $count vezes\n";
        }
    }
    
} else {
    echo "❌ Arquivo de log não encontrado: $logFile\n";
}

// 2. Analisar caches atuais para ver padrões
echo "\n\n2. ANÁLISE DOS CACHES ATUAIS:\n";
echo str_repeat("-", 80) . "\n";

$cacheDir = __DIR__ . '/storage/cache/queries/';
$files = glob($cacheDir . '*.cache');

echo "Total de caches: " . count($files) . "\n\n";

$cachesByType = [];
$totalSize = 0;

foreach ($files as $file) {
    $content = @file_get_contents($file);
    if ($content) {
        $data = @json_decode($content, true);
        if ($data && isset($data['key'])) {
            $key = $data['key'];
            $size = filesize($file);
            $age = time() - filemtime($file);
            $ttl = 300; // 5 minutos
            $remaining = $ttl - $age;
            
            $totalSize += $size;
            
            // Identificar tipo de cache
            if (strpos($key, 'user_') === 0 && strpos($key, '_conversations_') !== false) {
                $type = 'conversations';
                if (!isset($cachesByType[$type])) {
                    $cachesByType[$type] = 0;
                }
                $cachesByType[$type]++;
                
                $status = $remaining > 0 ? "✅ Válido ({$remaining}s)" : "❌ Expirado";
                
                echo "Cache de Conversas:\n";
                echo "  Chave: $key\n";
                echo "  Tamanho: " . number_format($size) . " bytes\n";
                echo "  Idade: {$age}s / {$ttl}s\n";
                echo "  Status: $status\n\n";
            }
        }
    }
}

echo "\nRESUMO:\n";
echo "  Total: " . count($files) . " caches\n";
echo "  Tamanho total: " . number_format($totalSize) . " bytes (" . round($totalSize/1024, 1) . " KB)\n";
echo "  Por tipo:\n";
foreach ($cachesByType as $type => $count) {
    echo "    $type: $count\n";
}

// 3. Testar se filtros desabilitam cache
echo "\n\n3. TESTE DE FILTROS:\n";
echo str_repeat("-", 80) . "\n";

$testCases = [
    ['status' => 'open'] => '✅ Deveria usar cache',
    ['status' => 'open', 'search' => 'teste'] => '❌ NÃO deve usar cache (search)',
    ['status' => 'open', 'date_from' => '2026-01-01'] => '❌ NÃO deve usar cache (date_from)',
    ['status' => 'open', 'channel' => 'whatsapp'] => '✅ Deveria usar cache',
    ['status' => 'open', 'agent_id' => '1'] => '✅ Deveria usar cache',
];

foreach ($testCases as $filters => $expected) {
    $filtersStr = json_encode($filters);
    echo "Filtros: $filtersStr\n";
    echo "  Esperado: $expected\n\n";
}

// 4. Recomendações
echo "\n\n4. RECOMENDAÇÕES:\n";
echo str_repeat("-", 80) . "\n";

$conversationsCaches = $cachesByType['conversations'] ?? 0;

if ($conversationsCaches == 0) {
    echo "🔴 PROBLEMA CRÍTICO: Nenhum cache de conversas ativo!\n\n";
    echo "Possíveis causas:\n";
    echo "  1. Usuários sempre usam filtros search/date_from/date_to\n";
    echo "  2. TTL muito curto (300s = 5 minutos)\n";
    echo "  3. Cache está expirando antes de ser reutilizado\n\n";
    echo "Soluções:\n";
    echo "  1. Aumentar TTL para 600s (10 minutos)\n";
    echo "  2. Criar cache mesmo com search (cache por padrão)\n";
    echo "  3. Implementar cache em níveis (cache quente + cache frio)\n";
    
} elseif ($conversationsCaches < 5) {
    echo "🟡 ATENÇÃO: Poucos caches ativos ({$conversationsCaches})\n\n";
    echo "Com 3.210 QPS, esperávamos mais caches.\n\n";
    echo "Possíveis causas:\n";
    echo "  1. Usuários usam muitas combinações diferentes de filtros\n";
    echo "  2. TTL curto faz cache expirar rápido\n";
    echo "  3. Poucas requisições reutilizam mesmos filtros\n\n";
    echo "Recomendações:\n";
    echo "  1. Aumentar TTL para 600-900s\n";
    echo "  2. Simplificar filtros (menos combinações)\n";
    echo "  3. Cache mais agressivo\n";
    
} else {
    echo "✅ Cache está funcionando adequadamente\n\n";
    echo "Se QPS ainda alto, causa provável é:\n";
    echo "  - Múltiplas abas/usuários simultâneos\n";
    echo "  - Subqueries na query principal\n";
}

echo "\n=== FIM ===\n";
