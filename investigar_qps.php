<?php
/**
 * Script para investigar QPS alto
 * Execute: php investigar_qps.php
 */

require_once __DIR__ . '/app/Helpers/Database.php';

echo "=== INVESTIGANDO QPS ALTO (7764/s) ===\n\n";

try {
    $db = \App\Helpers\Database::getInstance();
    
    // 1. Ver top 20 queries mais executadas
    echo "1. TOP 20 QUERIES MAIS EXECUTADAS:\n";
    echo str_repeat("-", 100) . "\n";
    
    echo "⚠️ AVISO: Performance Schema não disponível (requer permissões especiais)\n";
    echo "Use o script 'investigar_qps_simples.php' para análise sem performance_schema\n";
    echo "Pulando para próxima verificação...\n";
    
    // 2. Ver conexões ativas
    echo "\n2. CONEXÕES ATIVAS:\n";
    echo str_repeat("-", 100) . "\n";
    
    $sql = "SHOW PROCESSLIST";
    $result = $db->query($sql);
    $processes = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de conexões: " . count($processes) . "\n\n";
    
    $byCommand = [];
    $byState = [];
    
    foreach ($processes as $proc) {
        $cmd = $proc['Command'] ?? 'Unknown';
        $state = $proc['State'] ?? 'Unknown';
        
        if (!isset($byCommand[$cmd])) $byCommand[$cmd] = 0;
        if (!isset($byState[$state])) $byState[$state] = 0;
        
        $byCommand[$cmd]++;
        $byState[$state]++;
    }
    
    echo "Por Comando:\n";
    arsort($byCommand);
    foreach ($byCommand as $cmd => $count) {
        echo "  $cmd: $count\n";
    }
    
    echo "\nPor Estado:\n";
    arsort($byState);
    foreach ($byState as $state => $count) {
        echo "  $state: $count\n";
    }
    
    // 3. Ver comandos mais executados
    echo "\n\n3. COMANDOS MAIS EXECUTADOS:\n";
    echo str_repeat("-", 100) . "\n";
    
    $sql = "SHOW GLOBAL STATUS LIKE 'Com_%'";
    $result = $db->query($sql);
    $commands = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $filtered = [];
    foreach ($commands as $cmd) {
        if ($cmd['Value'] > 0 && !in_array($cmd['Variable_name'], ['Com_show_status', 'Com_show_variables'])) {
            $filtered[] = $cmd;
        }
    }
    
    // Ordenar por valor (maior primeiro)
    usort($filtered, function($a, $b) {
        return $b['Value'] - $a['Value'];
    });
    
    // Top 30
    foreach (array_slice($filtered, 0, 30) as $cmd) {
        echo sprintf("%-40s %s\n", $cmd['Variable_name'], number_format($cmd['Value']));
    }
    
    // 4. Verificar cache de queries
    echo "\n\n4. CACHE DE QUERIES (storage/cache/queries):\n";
    echo str_repeat("-", 100) . "\n";
    
    $cacheDir = __DIR__ . '/storage/cache/queries';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        echo "Total de arquivos de cache: " . count($files) . "\n\n";
        
        if (count($files) > 0) {
            echo "Últimos 10 caches criados:\n";
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            foreach (array_slice($files, 0, 10) as $file) {
                $name = basename($file);
                $age = time() - filemtime($file);
                $size = filesize($file);
                echo sprintf("  %-60s | %4ds atrás | %6s\n", 
                    substr($name, 0, 60), 
                    $age,
                    number_format($size) . 'b'
                );
            }
        } else {
            echo "⚠️ NENHUM ARQUIVO DE CACHE! Cache pode não estar funcionando!\n";
        }
    } else {
        echo "⚠️ Diretório de cache não existe!\n";
    }
    
    // 5. Verificar cache de permissões
    echo "\n\n5. CACHE DE PERMISSÕES (storage/cache/permissions):\n";
    echo str_repeat("-", 100) . "\n";
    
    $permCacheDir = __DIR__ . '/storage/cache/permissions';
    if (is_dir($permCacheDir)) {
        $files = glob($permCacheDir . '/*.cache');
        echo "Total de arquivos de cache: " . count($files) . "\n";
    } else {
        echo "⚠️ Diretório de cache não existe!\n";
    }
    
    echo "\n\n=== FIM DA INVESTIGAÇÃO ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
