<?php
/**
 * Identificar oportunidades de cache em Services
 * Execute: php identificar_oportunidades_cache.php
 */

echo "=== IDENTIFICANDO OPORTUNIDADES DE CACHE ===\n\n";

$serviceFiles = glob(__DIR__ . '/app/Services/*.php');

$opportunities = [];

foreach ($serviceFiles as $file) {
    $content = file_get_contents($file);
    $serviceName = basename($file, '.php');
    
    // Procurar métodos públicos/estáticos
    preg_match_all('/public\s+(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*:\s*\??(\w+)/', $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $methodName = $match[1];
        $returnType = $match[2] ?? 'mixed';
        
        // Verificar se método faz query no banco
        $methodPos = strpos($content, $match[0]);
        $methodContent = substr($content, $methodPos, 2000);
        
        $hasQuery = preg_match('/(Database::|->query\(|::fetch|SELECT\s+|INSERT\s+|UPDATE\s+)/i', $methodContent);
        $hasCache = preg_match('/(Cache::remember|Cache::get|getCache\(|setCache\()/i', $methodContent);
        
        if ($hasQuery && !$hasCache) {
            // Verificar se é método de escrita (create, update, delete)
            $isWriteMethod = preg_match('/(create|update|delete|save|insert|remove)/i', $methodName);
            
            if (!$isWriteMethod) {
                $opportunities[] = [
                    'service' => $serviceName,
                    'method' => $methodName,
                    'return_type' => $returnType,
                    'priority' => 'high'
                ];
            }
        }
    }
}

echo "TOTAL DE OPORTUNIDADES ENCONTRADAS: " . count($opportunities) . "\n\n";
echo str_repeat("=", 100) . "\n";
printf("%-30s | %-40s | %-20s\n", "Service", "Método", "Tipo Retorno");
echo str_repeat("=", 100) . "\n";

foreach ($opportunities as $opp) {
    printf("%-30s | %-40s | %-20s\n",
        $opp['service'],
        $opp['method'],
        $opp['return_type']
    );
}

echo str_repeat("=", 100) . "\n\n";

// Agrupar por service
$byService = [];
foreach ($opportunities as $opp) {
    if (!isset($byService[$opp['service']])) {
        $byService[$opp['service']] = 0;
    }
    $byService[$opp['service']]++;
}

arsort($byService);

echo "TOP 10 SERVICES COM MAIS OPORTUNIDADES:\n";
echo str_repeat("-", 60) . "\n";

$i = 1;
foreach (array_slice($byService, 0, 10, true) as $service => $count) {
    echo "$i. $service: $count métodos sem cache\n";
    $i++;
}

echo "\n=== RECOMENDAÇÕES ===\n\n";
echo "Para cada método listado acima, considere adicionar cache:\n\n";
echo "```php\n";
echo "public static function metodoCacheavel(\$params): array\n";
echo "{\n";
echo "    \$cacheKey = 'chave_' . md5(json_encode(\$params));\n";
echo "    return Cache::remember(\$cacheKey, 300, function() use (\$params) {\n";
echo "        // Query original aqui\n";
echo "        return \$resultado;\n";
echo "    });\n";
echo "}\n";
echo "```\n\n";

echo "=== FIM ===\n";
