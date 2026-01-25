<?php
/**
 * Migration: Adicionar suporte ao tipo google_maps em external_data_sources
 * 
 * Permite buscar leads do Google Maps (Google Places API) e adicionar
 * automaticamente às listas de contatos para campanhas
 */

function up_122_add_google_maps_source_type(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Adicionando suporte ao tipo google_maps em external_data_sources...\n";
    
    // Adicionar campo search_config para armazenar configurações de busca do Google Maps
    // e campo provider para escolher entre google_places e outscraper
    $sql = "ALTER TABLE external_data_sources 
            ADD COLUMN IF NOT EXISTS search_config JSON NULL COMMENT 'Config de busca: keyword, location, radius, max_results' AFTER query_config,
            ADD COLUMN IF NOT EXISTS provider VARCHAR(50) DEFAULT 'google_places' COMMENT 'google_places, outscraper' AFTER type,
            ADD COLUMN IF NOT EXISTS last_page_token VARCHAR(255) NULL COMMENT 'Token para paginação do Google Places' AFTER total_records,
            ADD COLUMN IF NOT EXISTS total_synced INT DEFAULT 0 COMMENT 'Total de contatos sincronizados' AFTER last_page_token";
    
    $db->exec($sql);
    
    echo "✅ Campos de google_maps adicionados\n";
    
    // Adicionar campo place_id no contacts para deduplicação
    echo "Adicionando campo place_id em contacts...\n";
    
    $sql = "ALTER TABLE contacts 
            ADD COLUMN IF NOT EXISTS place_id VARCHAR(255) NULL COMMENT 'Google Place ID para deduplicação' AFTER identifier,
            ADD COLUMN IF NOT EXISTS source VARCHAR(50) NULL COMMENT 'Origem do contato: manual, import, google_maps, instagram' AFTER place_id,
            ADD COLUMN IF NOT EXISTS company VARCHAR(255) NULL COMMENT 'Nome da empresa' AFTER source,
            ADD COLUMN IF NOT EXISTS address TEXT NULL COMMENT 'Endereço completo' AFTER company,
            ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL COMMENT 'Cidade' AFTER address,
            ADD COLUMN IF NOT EXISTS state VARCHAR(50) NULL COMMENT 'Estado/UF' AFTER city,
            ADD COLUMN IF NOT EXISTS country VARCHAR(50) DEFAULT 'Brasil' COMMENT 'País' AFTER state,
            ADD COLUMN IF NOT EXISTS rating DECIMAL(2,1) NULL COMMENT 'Avaliação no Google Maps' AFTER country,
            ADD COLUMN IF NOT EXISTS category VARCHAR(255) NULL COMMENT 'Categoria do negócio' AFTER rating";
    
    $db->exec($sql);
    
    // Criar índice único para place_id
    try {
        $sql = "CREATE UNIQUE INDEX idx_contacts_place_id ON contacts(place_id)";
        $db->exec($sql);
    } catch (\Exception $e) {
        echo "⚠️ Índice place_id já existe ou erro: " . $e->getMessage() . "\n";
    }
    
    // Criar índice para source
    try {
        $sql = "CREATE INDEX idx_contacts_source ON contacts(source)";
        $db->exec($sql);
    } catch (\Exception $e) {
        echo "⚠️ Índice source já existe ou erro: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Campos de contato adicionados\n";
    
    echo "
    Configuração search_config para type='google_maps':
    {
        \"keyword\": \"restaurantes\",
        \"location\": \"São Paulo, SP\",
        \"radius\": 5000,
        \"max_results\": 100,
        \"language\": \"pt-BR\"
    }
    
    Providers disponíveis:
    - google_places: API oficial do Google (requer API key)
    - outscraper: Serviço de terceiros (fallback, mais barato em volume)
    \n";
}

function down_122_add_google_maps_source_type(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Removendo campos de google_maps...\n";
    
    // Remover índices
    try {
        $db->exec("DROP INDEX idx_contacts_place_id ON contacts");
    } catch (\Exception $e) {
        // Ignorar
    }
    
    try {
        $db->exec("DROP INDEX idx_contacts_source ON contacts");
    } catch (\Exception $e) {
        // Ignorar
    }
    
    // Remover colunas de contacts
    $db->exec("ALTER TABLE contacts 
               DROP COLUMN IF EXISTS place_id,
               DROP COLUMN IF EXISTS source,
               DROP COLUMN IF EXISTS company,
               DROP COLUMN IF EXISTS address,
               DROP COLUMN IF EXISTS city,
               DROP COLUMN IF EXISTS state,
               DROP COLUMN IF EXISTS country,
               DROP COLUMN IF EXISTS rating,
               DROP COLUMN IF EXISTS category");
    
    // Remover colunas de external_data_sources
    $db->exec("ALTER TABLE external_data_sources 
               DROP COLUMN IF EXISTS search_config,
               DROP COLUMN IF EXISTS provider,
               DROP COLUMN IF EXISTS last_page_token,
               DROP COLUMN IF EXISTS total_synced");
    
    echo "✅ Campos de google_maps removidos\n";
}
