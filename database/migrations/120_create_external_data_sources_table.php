<?php
/**
 * Migration: Criar tabela external_data_sources
 * Fontes de dados externas (MySQL, PostgreSQL, etc) para sincronização de contatos
 */

function up_120_create_external_data_sources_table(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    echo "Criando tabela external_data_sources...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS external_data_sources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da fonte',
        type VARCHAR(50) DEFAULT 'mysql' COMMENT 'mysql, postgresql, sqlserver, api',
        connection_config JSON NOT NULL COMMENT 'host, port, database, username, password',
        table_name VARCHAR(255) NULL COMMENT 'Tabela a ser consultada',
        column_mapping JSON NULL COMMENT 'Mapeamento de colunas: {name, phone, email, custom_fields}',
        query_config JSON NULL COMMENT 'WHERE, ORDER BY, LIMIT customizados',
        sync_frequency VARCHAR(50) DEFAULT 'manual' COMMENT 'manual, hourly, daily, weekly',
        last_sync_at TIMESTAMP NULL,
        last_sync_status VARCHAR(50) NULL COMMENT 'success, error',
        last_sync_message TEXT NULL,
        total_records INT DEFAULT 0,
        status VARCHAR(50) DEFAULT 'active' COMMENT 'active, inactive, error',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela external_data_sources criada\n";
    
    // Tabela de logs de sincronização
    echo "Criando tabela external_data_sync_logs...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS external_data_sync_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_id INT NOT NULL,
        started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        status VARCHAR(50) DEFAULT 'running' COMMENT 'running, success, error',
        records_fetched INT DEFAULT 0,
        records_created INT DEFAULT 0,
        records_updated INT DEFAULT 0,
        records_failed INT DEFAULT 0,
        error_message TEXT NULL,
        execution_time_ms INT NULL,
        FOREIGN KEY (source_id) REFERENCES external_data_sources(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela external_data_sync_logs criada\n";
}

function down_120_create_external_data_sources_table(): void
{
    $db = \App\Helpers\Database::getInstance();
    
    $db->exec("DROP TABLE IF EXISTS external_data_sync_logs");
    $db->exec("DROP TABLE IF EXISTS external_data_sources");
    
    echo "✅ Tabelas de fontes externas removidas\n";
}
