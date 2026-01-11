<?php
/**
 * Migration: Adicionar campos para tracking de vendedor no WooCommerce
 */

function up_add_seller_and_metakey_to_woocommerce() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // 1. Adicionar campo seller_id ao cache de pedidos
    $checkColumn1 = $db->query("SHOW COLUMNS FROM woocommerce_order_cache LIKE 'seller_id'")->fetch();
    
    if (!$checkColumn1) {
        $sql = "ALTER TABLE woocommerce_order_cache 
                ADD COLUMN seller_id INT NULL 
                COMMENT 'ID do vendedor do pedido (extraído do meta_data)' 
                AFTER order_date,
                ADD INDEX idx_seller_id (seller_id)";
        
        $db->exec($sql);
        echo "✅ Campo 'seller_id' adicionado à tabela woocommerce_order_cache!\n";
    } else {
        echo "⚠️  Campo 'seller_id' já existe na tabela woocommerce_order_cache.\n";
    }
    
    // 2. Adicionar campo seller_meta_key às integrações
    $checkColumn2 = $db->query("SHOW COLUMNS FROM woocommerce_integrations LIKE 'seller_meta_key'")->fetch();
    
    if (!$checkColumn2) {
        $sql = "ALTER TABLE woocommerce_integrations 
                ADD COLUMN seller_meta_key VARCHAR(100) DEFAULT '_vendor_id' 
                COMMENT 'Meta key onde está o ID do vendedor (_vendor_id, _wcfm_vendor_id, etc)' 
                AFTER cache_ttl_minutes";
        
        $db->exec($sql);
        echo "✅ Campo 'seller_meta_key' adicionado à tabela woocommerce_integrations!\n";
    } else {
        echo "⚠️  Campo 'seller_meta_key' já existe na tabela woocommerce_integrations.\n";
    }
}

function down_add_seller_and_metakey_to_woocommerce() {
    $db = \App\Helpers\Database::getInstance();
    
    // Remover campos
    $sql1 = "ALTER TABLE woocommerce_order_cache 
             DROP INDEX idx_seller_id,
             DROP COLUMN seller_id";
    $db->exec($sql1);
    
    $sql2 = "ALTER TABLE woocommerce_integrations 
             DROP COLUMN seller_meta_key";
    $db->exec($sql2);
    
    echo "✅ Campos de vendedor removidos das tabelas WooCommerce!\n";
}
