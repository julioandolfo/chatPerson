<?php
/**
 * Script para limpar OPcache
 * Acesse: /clear-opcache.php
 */

// Verificar se OPcache está habilitado
if (!function_exists('opcache_reset')) {
    die('OPcache não está habilitado neste servidor');
}

// Limpar cache
if (opcache_reset()) {
    echo "✅ OPcache limpo com sucesso!<br>";
    echo "📊 Status do OPcache:<br>";
    echo "<pre>";
    print_r(opcache_get_status());
    echo "</pre>";
} else {
    echo "❌ Erro ao limpar OPcache";
}

// Tentar limpar também o realpath cache
clearstatcache(true);
echo "<br>✅ Realpath cache limpo!";
?>
