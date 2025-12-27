<?php
/**
 * Script para limpar cache do Opcache
 */

echo "<h2>Limpar Cache PHP/Opcache</h2>";

// Limpar Opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✅ Opcache limpo com sucesso!<br>";
} else {
    echo "⚠️ Opcache não está habilitado<br>";
}

// Limpar cache de realpath
if (function_exists('clearstatcache')) {
    clearstatcache(true);
    echo "✅ Cache de realpath limpo!<br>";
}

echo "<br>";
echo "📋 <strong>Informações do PHP:</strong><br>";
echo "Versão PHP: " . PHP_VERSION . "<br>";
echo "Opcache habilitado: " . (function_exists('opcache_reset') ? 'SIM' : 'NÃO') . "<br>";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "Opcache Status: " . ($status['opcache_enabled'] ? 'Ativado' : 'Desativado') . "<br>";
        echo "Scripts em cache: " . $status['opcache_statistics']['num_cached_scripts'] . "<br>";
    }
}

echo "<br><a href='/conversations'>← Voltar para Conversas</a>";

