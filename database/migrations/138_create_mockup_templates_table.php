<?php
/**
 * Migration: Criar tabela mockup_templates (Templates de Mockup)
 * 
 * Templates salvos do editor canvas que podem ser reutilizados
 */

function up_create_mockup_templates_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS mockup_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome do template',
        description TEXT COMMENT 'Descrição do template',
        category VARCHAR(100) COMMENT 'Categoria do template',
        
        -- CANVAS
        canvas_data JSON NOT NULL COMMENT 'Estado completo do canvas (Fabric.js)',
        canvas_width INT DEFAULT 1024 COMMENT 'Largura do canvas',
        canvas_height INT DEFAULT 1024 COMMENT 'Altura do canvas',
        
        -- PREVIEW
        thumbnail_path VARCHAR(500) COMMENT 'Miniatura do template',
        
        -- COMPARTILHAMENTO
        is_public BOOLEAN DEFAULT false COMMENT 'Se é público para todos usuários',
        
        -- ESTATÍSTICAS
        usage_count INT DEFAULT 0 COMMENT 'Quantas vezes foi usado',
        
        -- AUDIT
        created_by INT COMMENT 'Usuário que criou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_category (category),
        INDEX idx_is_public (is_public),
        INDEX idx_created_by (created_by),
        INDEX idx_usage_count (usage_count),
        
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'mockup_templates' criada com sucesso!\n";
}

function down_create_mockup_templates_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS mockup_templates";
    $db->exec($sql);
    echo "✅ Tabela 'mockup_templates' removida!\n";
}
