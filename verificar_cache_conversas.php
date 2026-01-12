<?php
/**
 * Script para verificar se o cache de conversas está funcionando
 * Execute: php verificar_cache_conversas.php
 */

require_once __DIR__ . '/app/Helpers/Database.php';
require_once __DIR__ . '/app/Helpers/Auth.php';
require_once __DIR__ . '/app/Services/ConversationService.php';

echo "=== VERIFICANDO CACHE DE CONVERSAS ===\n\n";

// Forçar login como admin (ID 1)
$_SESSION['user_id'] = 1;

try {
    // 1. Ver configuração de cache
    echo "1. CONFIGURAÇÃO DE CACHE:\n";
    echo str_repeat("-", 80) . "\n";
    
    $cacheDir = __DIR__ . '/storage/cache/queries/';
    echo "Diretório: $cacheDir\n";
    echo "Existe: " . (is_dir($cacheDir) ? "✅ SIM" : "❌ NÃO") . "\n";
    echo "Gravável: " . (is_writable($cacheDir) ? "✅ SIM" : "❌ NÃO") . "\n";
    
    // 2. Limpar cache antes do teste
    echo "\n2. LIMPANDO CACHE ANTERIOR:\n";
    echo str_repeat("-", 80) . "\n";
    
    $files = glob($cacheDir . '*.cache');
    $countBefore = count($files);
    echo "Arquivos antes: $countBefore\n";
    
    foreach ($files as $file) {
        if (strpos($file, 'user_1_conversations_') !== false) {
            @unlink($file);
        }
    }
    
    $filesAfter = glob($cacheDir . '*.cache');
    $countAfter = count($filesAfter);
    echo "Arquivos depois: $countAfter\n";
    echo "Removidos: " . ($countBefore - $countAfter) . "\n";
    
    // 3. Primeira chamada (deve criar cache)
    echo "\n3. PRIMEIRA CHAMADA (deve criar cache):\n";
    echo str_repeat("-", 80) . "\n";
    
    $filters = [
        'status' => 'open',
        'limit' => 10
    ];
    
    $start = microtime(true);
    $conversations1 = \App\Services\ConversationService::list($filters, 1);
    $time1 = round((microtime(true) - $start) * 1000, 2);
    
    echo "Tempo: {$time1}ms\n";
    echo "Conversas retornadas: " . count($conversations1) . "\n";
    
    // Verificar se cache foi criado
    $filesNew = glob($cacheDir . 'user_1_conversations_*.cache');
    echo "Arquivos de cache criados: " . count($filesNew) . "\n";
    
    if (count($filesNew) > 0) {
        echo "✅ Cache FOI criado!\n";
        foreach ($filesNew as $file) {
            $age = time() - filemtime($file);
            $size = filesize($file);
            echo "  - " . basename($file) . " ({$age}s atrás, " . number_format($size) . " bytes)\n";
        }
    } else {
        echo "❌ Cache NÃO foi criado!\n";
    }
    
    // 4. Segunda chamada (deve usar cache)
    echo "\n4. SEGUNDA CHAMADA (deve usar cache):\n";
    echo str_repeat("-", 80) . "\n";
    
    sleep(1); // Aguardar 1 segundo
    
    $start = microtime(true);
    $conversations2 = \App\Services\ConversationService::list($filters, 1);
    $time2 = round((microtime(true) - $start) * 1000, 2);
    
    echo "Tempo: {$time2}ms\n";
    echo "Conversas retornadas: " . count($conversations2) . "\n";
    
    // Comparar tempos
    $improvement = round((($time1 - $time2) / $time1) * 100, 1);
    echo "\nComparação:\n";
    echo "  Primeira: {$time1}ms\n";
    echo "  Segunda:  {$time2}ms\n";
    echo "  Melhoria: {$improvement}%\n";
    
    if ($time2 < $time1 * 0.5) {
        echo "✅ Cache ESTÁ funcionando! (segunda chamada 50%+ mais rápida)\n";
    } elseif ($time2 < $time1) {
        echo "⚠️ Cache pode estar funcionando, mas ganho é pequeno\n";
    } else {
        echo "❌ Cache NÃO está funcionando! (segunda chamada deveria ser mais rápida)\n";
    }
    
    // 5. Testar com filtros que desabilitam cache
    echo "\n5. TESTE COM FILTROS QUE DESABILITAM CACHE:\n";
    echo str_repeat("-", 80) . "\n";
    
    $filtersNoCache = [
        'status' => 'open',
        'search' => 'teste', // Este filtro desabilita cache
        'limit' => 10
    ];
    
    $start = microtime(true);
    $conversations3 = \App\Services\ConversationService::list($filtersNoCache, 1);
    $time3 = round((microtime(true) - $start) * 1000, 2);
    
    echo "Tempo com search: {$time3}ms\n";
    echo "Conversas: " . count($conversations3) . "\n";
    
    // Verificar se cache foi criado (não deveria)
    $filesSearch = glob($cacheDir . '*' . md5(json_encode($filtersNoCache)) . '*.cache');
    if (count($filesSearch) > 0) {
        echo "⚠️ Cache foi criado mesmo com filtro search (não deveria)\n";
    } else {
        echo "✅ Cache NÃO foi criado (correto, pois search desabilita cache)\n";
    }
    
    // 6. Diagnóstico
    echo "\n6. DIAGNÓSTICO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $totalFiles = count(glob($cacheDir . '*.cache'));
    $cacheSize = 0;
    foreach (glob($cacheDir . '*.cache') as $file) {
        $cacheSize += filesize($file);
    }
    
    echo "Total de arquivos de cache: $totalFiles\n";
    echo "Tamanho total: " . number_format($cacheSize) . " bytes (" . round($cacheSize/1024, 1) . " KB)\n";
    
    if ($totalFiles > 0 && $time2 < $time1 * 0.5) {
        echo "\n✅ CONCLUSÃO: Cache está funcionando CORRETAMENTE!\n";
        echo "   O QPS alto não é problema de cache.\n";
        echo "   Provável causa: Múltiplas abas/usuários ou pollings adicionais.\n";
    } elseif ($totalFiles > 0 && $time2 >= $time1 * 0.5) {
        echo "\n⚠️ CONCLUSÃO: Cache está sendo criado, mas não está sendo USADO.\n";
        echo "   Possíveis causas:\n";
        echo "   - TTL muito curto\n";
        echo "   - Filtros que desabilitam cache\n";
        echo "   - Lógica de canUseCache muito restritiva\n";
    } else {
        echo "\n❌ CONCLUSÃO: Cache NÃO está funcionando!\n";
        echo "   Possíveis causas:\n";
        echo "   - Permissões do diretório\n";
        echo "   - Erro na função setCache\n";
        echo "   - canUseCache retornando sempre false\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIM ===\n";
