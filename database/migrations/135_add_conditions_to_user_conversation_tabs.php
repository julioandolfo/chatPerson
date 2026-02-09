<?php
/**
 * Migration: Adicionar condições avançadas à tabela user_conversation_tabs
 * Permite criar abas com condições múltiplas (tags + funis + etapas + departamentos)
 * com lógica AND/OR configurável.
 */

function up_add_conditions_to_user_conversation_tabs() {
    $db = \App\Helpers\Database::getInstance();
    
    // 1. Tornar tag_id nullable (abas podem não ter tag primária)
    $db->exec("ALTER TABLE user_conversation_tabs MODIFY tag_id INT NULL");
    
    // 2. Remover constraint UNIQUE (user_id, tag_id) - pois tag_id pode ser NULL
    // e múltiplas abas podem compartilhar tags
    try {
        $db->exec("ALTER TABLE user_conversation_tabs DROP INDEX unique_user_tag");
    } catch (\Exception $e) {
        // Index pode não existir
    }
    
    // 3. Remover FK constraint para permitir tag_id NULL
    try {
        $db->exec("ALTER TABLE user_conversation_tabs DROP FOREIGN KEY fk_uct_tag");
    } catch (\Exception $e) {
        // FK pode não existir com esse nome
    }
    
    // 4. Adicionar novas colunas
    $columns = $db->query("SHOW COLUMNS FROM user_conversation_tabs LIKE 'name'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE user_conversation_tabs ADD COLUMN name VARCHAR(255) NULL AFTER tag_id");
    }
    
    $columns = $db->query("SHOW COLUMNS FROM user_conversation_tabs LIKE 'color'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE user_conversation_tabs ADD COLUMN color VARCHAR(20) NULL AFTER name");
    }
    
    $columns = $db->query("SHOW COLUMNS FROM user_conversation_tabs LIKE 'conditions'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE user_conversation_tabs ADD COLUMN conditions JSON NULL AFTER color");
    }
    
    $columns = $db->query("SHOW COLUMNS FROM user_conversation_tabs LIKE 'match_type'")->fetchAll();
    if (empty($columns)) {
        $db->exec("ALTER TABLE user_conversation_tabs ADD COLUMN match_type VARCHAR(3) DEFAULT 'AND' AFTER conditions");
    }
    
    // 5. Re-adicionar FK para tag_id (agora nullable, com SET NULL on delete)
    try {
        $db->exec("ALTER TABLE user_conversation_tabs ADD CONSTRAINT fk_uct_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE SET NULL");
    } catch (\Exception $e) {
        // FK pode já existir
    }
    
    echo "✅ Tabela 'user_conversation_tabs' atualizada com condições avançadas!\n";
}

function down_add_conditions_to_user_conversation_tabs() {
    $db = \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE user_conversation_tabs DROP FOREIGN KEY fk_uct_tag");
    } catch (\Exception $e) {}
    
    try {
        $db->exec("ALTER TABLE user_conversation_tabs 
            DROP COLUMN IF EXISTS match_type,
            DROP COLUMN IF EXISTS conditions,
            DROP COLUMN IF EXISTS color,
            DROP COLUMN IF EXISTS name,
            MODIFY tag_id INT NOT NULL");
    } catch (\Exception $e) {}
    
    try {
        $db->exec("ALTER TABLE user_conversation_tabs ADD UNIQUE KEY unique_user_tag (user_id, tag_id)");
        $db->exec("ALTER TABLE user_conversation_tabs ADD CONSTRAINT fk_uct_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE");
    } catch (\Exception $e) {}
    
    echo "✅ Revertida alteração de condições avançadas!\n";
}
