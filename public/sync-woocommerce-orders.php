#!/usr/bin/env php
<?php
/**
 * Script Standalone: Sincronização de Pedidos WooCommerce
 * 
 * Uso:
 * php public/sync-woocommerce-orders.php
 * 
 * Ou via navegador (para teste):
 * http://seudominio.com/sync-woocommerce-orders.php
 */

// Garantir que estamos no diretório correto
$rootDir = dirname(__DIR__);
chdir($rootDir);

// Definir timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar bootstrap
require_once $rootDir . '/config/bootstrap.php';

use App\Jobs\WooCommerceSyncJob;

// Executar sincronização
try {
    echo "============================================\n";
    echo "SINCRONIZAÇÃO DE PEDIDOS WOOCOMMERCE\n";
    echo "============================================\n";
    echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";
    
    WooCommerceSyncJob::run();
    
    echo "\n============================================\n";
    echo "SINCRONIZAÇÃO CONCLUÍDA COM SUCESSO!\n";
    echo "============================================\n";
    echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
} catch (\Exception $e) {
    echo "\n============================================\n";
    echo "ERRO NA SINCRONIZAÇÃO\n";
    echo "============================================\n";
    echo "Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    error_log("Erro na sincronização WooCommerce: " . $e->getMessage());
    
    exit(1);
}
