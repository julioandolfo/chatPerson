<?php
/**
 * Script para limpar OPcache do PHP
 */

header('Content-Type: text/html; charset=utf-8');

echo '<h1>Limpar OPcache</h1>';

// Verificar se OPcache está habilitado
if (!function_exists('opcache_reset')) {
    echo '<p style="color: red;">❌ OPcache não está habilitado neste servidor.</p>';
    exit;
}

// Limpar OPcache
$result = opcache_reset();

if ($result) {
    echo '<p style="color: green;">✅ OPcache limpo com sucesso!</p>';
    echo '<p>Agora atualize a página do dashboard para ver as mudanças.</p>';
} else {
    echo '<p style="color: red;">❌ Erro ao limpar OPcache.</p>';
}

// Mostrar informações do OPcache
echo '<h2>Informações do OPcache</h2>';
echo '<pre>';
print_r(opcache_get_status());
echo '</pre>';

echo '<hr>';
echo '<p><a href="' . $_SERVER['HTTP_REFERER'] . '">← Voltar</a></p>';

