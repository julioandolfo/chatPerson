<?php
/**
 * Migration: Criar tabela contact_list_items (Itens de Listas de Contatos)
 * 
 * Relacionamento entre listas e contatos
 * Cada item pode ter variáveis específicas para personalização
 */

function up_create_contact_list_items_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS contact_list_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_list_id INT NOT NULL COMMENT 'ID da lista',
        contact_id INT NOT NULL COMMENT 'ID do contato',
        
        -- VARIÁVEIS PERSONALIZADAS DESTE CONTATO
        custom_variables JSON COMMENT 'Variáveis específicas para este contato nesta lista',
        
        -- TRACKING
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi adicionado',
        added_by INT COMMENT 'Quem adicionou',
        
        UNIQUE KEY unique_list_contact (contact_list_id, contact_id),
        INDEX idx_contact_list_id (contact_list_id),
        INDEX idx_contact_id (contact_id),
        
        FOREIGN KEY (contact_list_id) REFERENCES contact_lists(id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'contact_list_items' criada com sucesso!\n";
}

function down_create_contact_list_items_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS contact_list_items";
    $db->exec($sql);
    echo "✅ Tabela 'contact_list_items' removida!\n";
}
