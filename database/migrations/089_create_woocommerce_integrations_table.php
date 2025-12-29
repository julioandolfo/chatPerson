<?php
/**
 * Migration: Criar tabela woocommerce_integrations
 * Integrações nativas com WooCommerce
 */

function up_create_woocommerce_integrations_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS woocommerce_integrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da integração (ex: Loja Principal)',
        woocommerce_url VARCHAR(500) NOT NULL COMMENT 'URL da loja WooCommerce',
        consumer_key VARCHAR(255) NOT NULL COMMENT 'Consumer Key da API',
        consumer_secret VARCHAR(500) NOT NULL COMMENT 'Consumer Secret da API',
        
        -- Configuração de mapeamento de campos
        contact_field_mapping JSON NOT NULL COMMENT 'Mapeamento de campos do contato para busca no WooCommerce',
        
        -- Configurações de busca
        search_settings JSON NULL COMMENT 'Configurações de busca avançada',
        
        -- Status e controle
        status VARCHAR(20) DEFAULT 'active' COMMENT 'active, inactive, error',
        last_sync_at TIMESTAMP NULL COMMENT 'Última sincronização',
        last_error TEXT NULL COMMENT 'Último erro ocorrido',
        sync_frequency_minutes INT DEFAULT 15 COMMENT 'Frequência de sincronização automática',
        
        -- Cache de pedidos
        cache_enabled BOOLEAN DEFAULT true,
        cache_ttl_minutes INT DEFAULT 5,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_status (status),
        INDEX idx_last_sync (last_sync_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'woocommerce_integrations' criada com sucesso!\n";
}

function down_create_woocommerce_integrations_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS woocommerce_integrations";
    $db->exec($sql);
    echo "✅ Tabela 'woocommerce_integrations' removida!\n";
}

