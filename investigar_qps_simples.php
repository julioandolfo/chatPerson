<?php
/**
 * Script SIMPLIFICADO para investigar QPS alto
 * NÃO usa performance_schema (requer permissões especiais)
 * Execute: php investigar_qps_simples.php
 */

require_once __DIR__ . '/app/Helpers/Database.php';

echo "=== INVESTIGANDO QPS ALTO (7764/s) - VERSÃO SIMPLES ===\n\n";

try {
    $db = \App\Helpers\Database::getInstance();
    
    // 1. PROCESSLIST - Ver o que está rodando AGORA
    echo "1. PROCESSOS ATIVOS (PROCESSLIST):\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SHOW FULL PROCESSLIST";
    $result = $db->query($sql);
    $processes = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de conexões: " . count($processes) . "\n\n";
    
    // Agrupar por comando
    $byCommand = [];
    $byState = [];
    $byInfo = [];
    
    foreach ($processes as $proc) {
        $cmd = $proc['Command'] ?? 'Unknown';
        $state = $proc['State'] ?? 'None';
        $info = $proc['Info'] ?? '';
        
        // Contar comandos
        if (!isset($byCommand[$cmd])) $byCommand[$cmd] = 0;
        $byCommand[$cmd]++;
        
        // Contar estados
        if (!isset($byState[$state])) $byState[$state] = 0;
        $byState[$state]++;
        
        // Capturar queries únicas
        if (!empty($info) && $info !== 'SHOW FULL PROCESSLIST') {
            $shortQuery = substr($info, 0, 100);
            if (!isset($byInfo[$shortQuery])) $byInfo[$shortQuery] = 0;
            $byInfo[$shortQuery]++;
        }
    }
    
    echo "Por Comando:\n";
    arsort($byCommand);
    foreach ($byCommand as $cmd => $count) {
        echo sprintf("  %-20s: %d conexões\n", $cmd, $count);
    }
    
    echo "\nPor Estado:\n";
    arsort($byState);
    foreach ($byState as $state => $count) {
        echo sprintf("  %-30s: %d\n", $state, $count);
    }
    
    if (count($byInfo) > 0) {
        echo "\nQueries Ativas:\n";
        arsort($byInfo);
        foreach (array_slice($byInfo, 0, 10) as $query => $count) {
            echo sprintf("  [%dx] %s...\n", $count, substr($query, 0, 80));
        }
    }
    
    // 2. COMANDOS EXECUTADOS (Com_*)
    echo "\n\n2. COMANDOS EXECUTADOS (Com_*):\n";
    echo str_repeat("=", 100) . "\n";
    
    $sql = "SHOW GLOBAL STATUS LIKE 'Com_%'";
    $result = $db->query($sql);
    $commands = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $filtered = [];
    foreach ($commands as $cmd) {
        if ($cmd['Value'] > 0) {
            $filtered[$cmd['Variable_name']] = (int)$cmd['Value'];
        }
    }
    
    // Ordenar por valor
    arsort($filtered);
    
    // Top 20
    echo "Top 20 comandos mais executados:\n";
    $i = 1;
    foreach (array_slice($filtered, 0, 20, true) as $name => $value) {
        echo sprintf("%2d. %-40s %s\n", $i++, $name, number_format($value));
    }
    
    // 3. MÉTRICAS IMPORTANTES
    echo "\n\n3. MÉTRICAS IMPORTANTES:\n";
    echo str_repeat("=", 100) . "\n";
    
    $metrics = [
        'Questions' => 'Total de queries executadas',
        'Queries' => 'Total de queries (incluindo internas)',
        'Uptime' => 'Tempo online (segundos)',
        'Threads_connected' => 'Conexões ativas',
        'Threads_running' => 'Threads executando',
        'Slow_queries' => 'Queries lentas',
        'Created_tmp_tables' => 'Tabelas temporárias criadas',
        'Created_tmp_disk_tables' => 'Tabelas temporárias em disco',
    ];
    
    foreach ($metrics as $key => $desc) {
        $sql = "SHOW GLOBAL STATUS LIKE '$key'";
        $result = $db->query($sql);
        $row = $result->fetch(PDO::FETCH_ASSOC);
        
        $value = $row['Value'] ?? 0;
        echo sprintf("%-30s: %15s | %s\n", $key, number_format($value), $desc);
        
        // Calcular QPS médio
        if ($key === 'Questions') {
            $questionsValue = $value;
        }
        if ($key === 'Uptime') {
            $uptimeValue = $value;
            if ($uptimeValue > 0 && isset($questionsValue)) {
                $avgQPS = $questionsValue / $uptimeValue;
                echo sprintf("\n🎯 QPS MÉDIO desde o início: %.2f queries/segundo\n", $avgQPS);
            }
        }
    }
    
    // 4. VERIFICAR CACHE DE QUERIES
    echo "\n\n4. CACHE DE QUERIES (storage/cache/queries):\n";
    echo str_repeat("=", 100) . "\n";
    
    $cacheDir = __DIR__ . '/storage/cache/queries';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        $totalFiles = count($files);
        
        echo "Total de arquivos de cache: " . $totalFiles . "\n";
        
        if ($totalFiles === 0) {
            echo "\n⚠️ ALERTA: NENHUM ARQUIVO DE CACHE!\n";
            echo "   Isso significa que o cache NÃO está funcionando!\n";
            echo "   TODAS as queries estão indo direto pro banco!\n\n";
        } else {
            echo "\n✅ Cache está gerando arquivos.\n";
            echo "   Últimos 10 caches criados:\n\n";
            
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            foreach (array_slice($files, 0, 10) as $file) {
                $name = basename($file);
                $age = time() - filemtime($file);
                $size = filesize($file);
                
                $ageStr = $age < 60 ? $age . 's' : floor($age/60) . 'm';
                
                echo sprintf("  %-50s | %5s atrás | %8s\n", 
                    substr($name, 0, 50), 
                    $ageStr,
                    number_format($size) . ' bytes'
                );
            }
        }
        
        // Verificar permissões
        echo "\nPermissões do diretório:\n";
        echo "  Legível: " . (is_readable($cacheDir) ? "✅ SIM" : "❌ NÃO") . "\n";
        echo "  Gravável: " . (is_writable($cacheDir) ? "✅ SIM" : "❌ NÃO") . "\n";
        
    } else {
        echo "⚠️ ALERTA: Diretório de cache NÃO existe!\n";
        echo "   Caminho: $cacheDir\n";
        echo "   Cache NÃO está funcionando!\n";
    }
    
    // 5. VERIFICAR CACHE DE PERMISSÕES
    echo "\n\n5. CACHE DE PERMISSÕES (storage/cache/permissions):\n";
    echo str_repeat("=", 100) . "\n";
    
    $permCacheDir = __DIR__ . '/storage/cache/permissions';
    if (is_dir($permCacheDir)) {
        $files = glob($permCacheDir . '/*.cache');
        echo "Total de arquivos de cache: " . count($files) . "\n";
        
        if (count($files) === 0) {
            echo "⚠️ ALERTA: Nenhum cache de permissões!\n";
        }
    } else {
        echo "⚠️ ALERTA: Diretório de cache de permissões NÃO existe!\n";
    }
    
    // 6. TESTE DE CACHE
    echo "\n\n6. TESTE DE CACHE:\n";
    echo str_repeat("=", 100) . "\n";
    
    require_once __DIR__ . '/app/Helpers/Cache.php';
    
    $testKey = 'test_' . time();
    $testValue = 'test_value_' . rand(1000, 9999);
    
    echo "Testando cache...\n";
    echo "  Chave: $testKey\n";
    echo "  Valor: $testValue\n\n";
    
    // Tentar salvar
    $saved = \App\Helpers\Cache::set($testKey, $testValue, 60);
    echo "  Salvar: " . ($saved ? "✅ OK" : "❌ FALHOU") . "\n";
    
    // Tentar recuperar
    $retrieved = \App\Helpers\Cache::get($testKey);
    $retrieveOk = ($retrieved === $testValue);
    echo "  Recuperar: " . ($retrieveOk ? "✅ OK" : "❌ FALHOU") . "\n";
    
    if (!$saved || !$retrieveOk) {
        echo "\n🚨 PROBLEMA ENCONTRADO: Cache NÃO está funcionando!\n";
        echo "   Isso explica o QPS alto!\n";
    } else {
        echo "\n✅ Cache está funcionando corretamente.\n";
    }
    
    // 7. RESUMO E DIAGNÓSTICO
    echo "\n\n7. DIAGNÓSTICO:\n";
    echo str_repeat("=", 100) . "\n";
    
    $diagnostico = [];
    
    // Verificar conexões
    if (count($processes) > 50) {
        $diagnostico[] = "⚠️ ALERTA: " . count($processes) . " conexões ativas (ideal < 20)";
    }
    
    // Verificar cache
    if ($totalFiles === 0) {
        $diagnostico[] = "🚨 CRÍTICO: Cache de queries NÃO está funcionando!";
    }
    
    // Verificar threads
    if (isset($filtered['Com_select']) && $filtered['Com_select'] > 1000000) {
        $diagnostico[] = "⚠️ ALERTA: Muitos SELECTs executados (" . number_format($filtered['Com_select']) . ")";
    }
    
    if (count($diagnostico) > 0) {
        echo "\nProblemas Encontrados:\n";
        foreach ($diagnostico as $msg) {
            echo "  " . $msg . "\n";
        }
    } else {
        echo "Nenhum problema óbvio detectado.\n";
    }
    
    echo "\n\n=== FIM DA INVESTIGAÇÃO ===\n";
    echo "\n📊 PRÓXIMO PASSO:\n";
    echo "Execute novamente o teste de QPS:\n";
    echo "1. SHOW GLOBAL STATUS LIKE 'Questions';\n";
    echo "2. Aguarde 10 segundos\n";
    echo "3. SHOW GLOBAL STATUS LIKE 'Questions';\n";
    echo "4. Calcule: (valor2 - valor1) / 10\n\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
