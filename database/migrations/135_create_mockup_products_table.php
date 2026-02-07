<?php
/**
 * Migration: Criar tabela mockup_products (Produtos para Mockups)
 * 
 * Armazena produtos/brindes salvos que podem ser reutilizados
 * nas gerações de mockup
 */

function up_create_mockup_products_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS mockup_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do produto (ex: Caneca Branca)',
        category VARCHAR(100) COMMENT 'Categoria (caneca, camiseta, caderno, etc)',
        description TEXT COMMENT 'Descrição detalhada do produto',
        image_path VARCHAR(500) NOT NULL COMMENT 'Caminho da imagem do produto',
        thumbnail_path VARCHAR(500) COMMENT 'Miniatura do produto',
        is_template BOOLEAN DEFAULT false COMMENT 'Se é um template reutilizável',
        metadata JSON COMMENT 'Dados adicionais (cor, tamanho, material, etc)',
        usage_count INT DEFAULT 0 COMMENT 'Quantas vezes foi usado',
        
        -- AUDIT
        created_by INT COMMENT 'Usuário que criou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_category (category),
        INDEX idx_created_by (created_by),
        INDEX idx_is_template (is_template),
        
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'mockup_products' criada com sucesso!\n";
}

function down_create_mockup_products_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS mockup_products";
    $db->exec($sql);
    echo "✅ Tabela 'mockup_products' removida!\n";
}
