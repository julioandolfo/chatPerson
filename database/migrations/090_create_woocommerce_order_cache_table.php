<?php
/**
 * Migration: Criar tabela woocommerce_order_cache
 * Cache de pedidos do WooCommerce por contato
 */

function up_create_woocommerce_order_cache_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS woocommerce_order_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        woocommerce_integration_id INT NOT NULL,
        contact_id INT NOT NULL,
        order_id INT NOT NULL COMMENT 'ID do pedido no WooCommerce',
        order_data JSON NOT NULL COMMENT 'Dados completos do pedido (cache)',
        order_status VARCHAR(50) NOT NULL,
        order_total DECIMAL(10,2) NOT NULL,
        order_date DATETIME NOT NULL,
        cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        
        UNIQUE KEY unique_order (woocommerce_integration_id, contact_id, order_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_expires_at (expires_at),
        INDEX idx_order_status (order_status),
        FOREIGN KEY (woocommerce_integration_id) REFERENCES woocommerce_integrations(id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'woocommerce_order_cache' criada com sucesso!\n";
}

function down_create_woocommerce_order_cache_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Remover foreign keys primeiro
    try {
        $db->exec("ALTER TABLE woocommerce_order_cache DROP FOREIGN KEY woocommerce_order_cache_ibfk_1");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE woocommerce_order_cache DROP FOREIGN KEY woocommerce_order_cache_ibfk_2");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    $sql = "DROP TABLE IF EXISTS woocommerce_order_cache";
    $db->exec($sql);
    echo "✅ Tabela 'woocommerce_order_cache' removida!\n";
}

