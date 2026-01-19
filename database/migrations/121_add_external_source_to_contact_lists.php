<?php
/**
 * Migration: Adicionar campos de fonte externa e ordem de envio em contact_lists
 */

function up_121_add_external_source_to_contact_lists(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Adicionando campos de fonte externa em contact_lists...\n";
    
    $sql = "ALTER TABLE contact_lists 
            ADD COLUMN IF NOT EXISTS external_source_id INT NULL AFTER total_contacts,
            ADD COLUMN IF NOT EXISTS sync_enabled BOOLEAN DEFAULT FALSE AFTER external_source_id,
            ADD COLUMN IF NOT EXISTS send_order VARCHAR(50) DEFAULT 'default' AFTER sync_enabled,
            ADD COLUMN IF NOT EXISTS send_order_config JSON NULL AFTER send_order,
            ADD COLUMN IF NOT EXISTS last_sync_at TIMESTAMP NULL AFTER send_order_config";
    
    $db->exec($sql);
    
    // Adicionar foreign key
    try {
        $sql = "ALTER TABLE contact_lists 
                ADD CONSTRAINT fk_contact_lists_external_source 
                FOREIGN KEY (external_source_id) REFERENCES external_data_sources(id) ON DELETE SET NULL";
        $db->exec($sql);
    } catch (\Exception $e) {
        echo "⚠️ Foreign key já existe ou erro: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Campos de fonte externa adicionados\n";
    echo "
    Campos adicionados:
    - external_source_id: ID da fonte externa
    - sync_enabled: Sincronização automática habilitada?
    - send_order: default, random, asc, desc, custom
    - send_order_config: {field, direction, conditions}
    - last_sync_at: Última sincronização
    \n";
}

function down_121_add_external_source_to_contact_lists(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE contact_lists DROP FOREIGN KEY fk_contact_lists_external_source");
    } catch (\Exception $e) {
        // Ignorar se não existir
    }
    
    $db->exec("ALTER TABLE contact_lists 
               DROP COLUMN IF EXISTS external_source_id,
               DROP COLUMN IF EXISTS sync_enabled,
               DROP COLUMN IF EXISTS send_order,
               DROP COLUMN IF EXISTS send_order_config,
               DROP COLUMN IF EXISTS last_sync_at");
    
    echo "✅ Campos de fonte externa removidos\n";
}
