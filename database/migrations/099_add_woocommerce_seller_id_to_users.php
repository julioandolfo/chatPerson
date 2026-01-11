<?php
/**
 * Migration: Adicionar campo woocommerce_seller_id à tabela users
 * Para tracking de conversão Lead → Venda
 */

function up_add_woocommerce_seller_id_to_users() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Verificar se a coluna já existe
    $checkColumn = $db->query("SHOW COLUMNS FROM users LIKE 'woocommerce_seller_id'")->fetch();
    
    if (!$checkColumn) {
        $sql = "ALTER TABLE users 
                ADD COLUMN woocommerce_seller_id INT NULL 
                COMMENT 'ID do vendedor no WooCommerce para tracking de conversão' 
                AFTER avatar,
                ADD INDEX idx_woocommerce_seller_id (woocommerce_seller_id)";
        
        $db->exec($sql);
        echo "✅ Campo 'woocommerce_seller_id' adicionado à tabela users!\n";
    } else {
        echo "⚠️  Campo 'woocommerce_seller_id' já existe na tabela users.\n";
    }
}

function down_add_woocommerce_seller_id_to_users() {
    $db = \App\Helpers\Database::getInstance();
    
    $sql = "ALTER TABLE users 
            DROP INDEX idx_woocommerce_seller_id,
            DROP COLUMN woocommerce_seller_id";
    
    $db->exec($sql);
    echo "✅ Campo 'woocommerce_seller_id' removido da tabela users!\n";
}
