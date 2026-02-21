<?php
/**
 * Migration: Tornar expires_at nullable em woocommerce_order_cache
 * NULL = armazenamento permanente (pedidos sincronizados via cron/manual não expiram)
 */

function up_alter_woocommerce_order_cache_expires_nullable() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    // Alterar coluna para aceitar NULL (NULL = permanente, sem expiração)
    $db->exec("ALTER TABLE woocommerce_order_cache 
               MODIFY COLUMN expires_at TIMESTAMP NULL DEFAULT NULL 
               COMMENT 'NULL = permanente; valor = expira em data/hora definida'");

    // Marcar todos os registros existentes como permanentes
    $stmt = $db->exec("UPDATE woocommerce_order_cache SET expires_at = NULL WHERE 1");

    echo "✅ Coluna expires_at alterada para NULL em woocommerce_order_cache — todos os pedidos existentes marcados como permanentes.\n";
}

function down_alter_woocommerce_order_cache_expires_nullable() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    // Restaurar como NOT NULL com valor padrão futuro para não quebrar registros existentes
    $db->exec("UPDATE woocommerce_order_cache SET expires_at = DATE_ADD(NOW(), INTERVAL 3650 DAY) WHERE expires_at IS NULL");
    $db->exec("ALTER TABLE woocommerce_order_cache 
               MODIFY COLUMN expires_at TIMESTAMP NOT NULL");

    echo "✅ Coluna expires_at restaurada para NOT NULL em woocommerce_order_cache.\n";
}
