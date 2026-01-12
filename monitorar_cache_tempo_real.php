<?php
/**
 * Monitorar criação de cache em tempo real
 * Execute: php monitorar_cache_tempo_real.php
 */

$cacheDir = __DIR__ . '/storage/cache/queries/';

echo "=== MONITORANDO CACHE EM TEMPO REAL ===\n\n";
echo "Diretório: $cacheDir\n";
echo "Monitorando por 60 segundos...\n";
echo "Pressione Ctrl+C para parar\n\n";

// Obter lista inicial
$filesBefore = glob($cacheDir . '*.cache');
$countBefore = count($filesBefore);

echo "Arquivos no início: $countBefore\n";
echo str_repeat("-", 80) . "\n\n";

$startTime = time();
$lastCheck = [];

// Monitorar por 60 segundos
while ((time() - $startTime) < 60) {
    $filesNow = glob($cacheDir . '*.cache');
    
    // Verificar novos arquivos
    foreach ($filesNow as $file) {
        $basename = basename($file);
        $mtime = filemtime($file);
        
        // Se arquivo foi modificado nos últimos 2 segundos
        if ($mtime > (time() - 2)) {
            if (!isset($lastCheck[$basename]) || $lastCheck[$basename] != $mtime) {
                $size = filesize($file);
                $age = time() - $mtime;
                
                // Ler conteúdo do cache
                $content = @file_get_contents($file);
                $cacheKey = 'unknown';
                if ($content) {
                    $data = @json_decode($content, true);
                    if ($data && isset($data['key'])) {
                        $cacheKey = $data['key'];
                    }
                }
                
                $timestamp = date('H:i:s');
                echo "[{$timestamp}] 🆕 CACHE CRIADO/ATUALIZADO\n";
                echo "  Arquivo: $basename\n";
                echo "  Chave: $cacheKey\n";
                echo "  Tamanho: " . number_format($size) . " bytes\n";
                echo "  Idade: {$age}s\n\n";
                
                $lastCheck[$basename] = $mtime;
            }
        }
    }
    
    // Aguardar 1 segundo
    sleep(1);
}

echo "\n" . str_repeat("-", 80) . "\n";

// Contar arquivos no final
$filesAfter = glob($cacheDir . '*.cache');
$countAfter = count($filesAfter);

echo "\nRESULTADO:\n";
echo "  Arquivos no início: $countBefore\n";
echo "  Arquivos no final: $countAfter\n";
echo "  Diferença: " . ($countAfter - $countBefore) . "\n\n";

if ($countAfter > $countBefore) {
    echo "✅ Cache ESTÁ sendo criado! ($countAfter caches ativos)\n";
} else {
    echo "⚠️ Nenhum cache novo foi criado em 60 segundos.\n";
    echo "   Possíveis causas:\n";
    echo "   - Nenhuma requisição à lista de conversas\n";
    echo "   - Cache já existe e não expirou\n";
    echo "   - Filtros desabilitam cache (search, date_from, date_to)\n";
}

// Listar todos os caches atuais
echo "\n\nCACHES ATUAIS:\n";
echo str_repeat("-", 80) . "\n";

foreach ($filesAfter as $file) {
    $basename = basename($file);
    $age = time() - filemtime($file);
    $size = filesize($file);
    
    $content = @file_get_contents($file);
    $cacheKey = 'unknown';
    $createdAt = 'unknown';
    if ($content) {
        $data = @json_decode($content, true);
        if ($data) {
            $cacheKey = $data['key'] ?? 'unknown';
            $createdAt = isset($data['created_at']) ? date('H:i:s', $data['created_at']) : 'unknown';
        }
    }
    
    $ageStr = $age < 60 ? $age . 's' : floor($age/60) . 'm';
    
    echo sprintf("%-40s | %6s | %8s | %s\n", 
        substr($basename, 0, 40),
        $ageStr,
        number_format($size) . 'b',
        substr($cacheKey, 0, 40)
    );
}

echo "\n=== FIM ===\n";
