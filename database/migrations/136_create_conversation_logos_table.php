<?php
/**
 * Migration: Criar tabela conversation_logos (Logos por Conversa)
 * 
 * Armazena logos enviadas pelos clientes em cada conversa
 * para uso nos mockups
 */

function up_create_conversation_logos_table() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_logos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL COMMENT 'Conversa onde a logo foi enviada',
        contact_id INT COMMENT 'Contato que enviou (se aplicável)',
        logo_path VARCHAR(500) NOT NULL COMMENT 'Caminho da logo',
        thumbnail_path VARCHAR(500) COMMENT 'Miniatura da logo',
        original_filename VARCHAR(255) COMMENT 'Nome original do arquivo',
        file_size INT COMMENT 'Tamanho em bytes',
        mime_type VARCHAR(100) COMMENT 'Tipo MIME (image/png, etc)',
        dimensions JSON COMMENT 'Largura e altura {width: 500, height: 300}',
        is_primary BOOLEAN DEFAULT false COMMENT 'Se é a logo principal da conversa',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_is_primary (is_primary),
        
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'conversation_logos' criada com sucesso!\n";
}

function down_create_conversation_logos_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS conversation_logos";
    $db->exec($sql);
    echo "✅ Tabela 'conversation_logos' removida!\n";
}
