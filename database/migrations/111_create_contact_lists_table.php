<?php
/**
 * Migration: Criar tabela contact_lists (Listas de Contatos)
 * 
 * Listas de contatos para campanhas
 * Suporta listas estáticas e dinâmicas (com filtros)
 */

function up_create_contact_lists_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS contact_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da lista',
        description TEXT COMMENT 'Descrição',
        
        -- TIPO DE LISTA
        is_dynamic BOOLEAN DEFAULT FALSE COMMENT 'Lista dinâmica (recalcula com filtros)',
        filter_config JSON COMMENT 'Configuração de filtros se is_dynamic = TRUE',
        
        -- ESTATÍSTICAS
        total_contacts INT DEFAULT 0 COMMENT 'Total de contatos na lista',
        last_calculated_at TIMESTAMP NULL COMMENT 'Última vez que foi recalculada',
        
        -- AUDIT
        created_by INT COMMENT 'Usuário que criou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_name (name),
        INDEX idx_is_dynamic (is_dynamic),
        INDEX idx_created_by (created_by),
        
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'contact_lists' criada com sucesso!\n";
}

function down_create_contact_lists_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS contact_lists";
    $db->exec($sql);
    echo "✅ Tabela 'contact_lists' removida!\n";
}
