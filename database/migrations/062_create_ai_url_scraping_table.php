<?php
/**
 * Migration: Criar tabela ai_url_scraping
 * URLs sendo processadas para adicionar à knowledge base
 * 
 * IMPORTANTE: Esta migration cria a tabela no PostgreSQL (não MySQL)
 */

function up_ai_url_scraping_table() {
    // Verificar se PostgreSQL está habilitado
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Pule esta migration ou habilite PostgreSQL primeiro.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Criar tabela no PostgreSQL
        // Nota: Foreign key não é criada porque ai_agents está no MySQL
        // A integridade referencial será mantida via aplicação
        $sql = "
        CREATE TABLE IF NOT EXISTS ai_url_scraping (
            id SERIAL PRIMARY KEY,
            ai_agent_id INT NOT NULL,
            url VARCHAR(1000) NOT NULL,
            title VARCHAR(500),
            content TEXT,
            scraped_at TIMESTAMP DEFAULT NOW(),
            status VARCHAR(50) DEFAULT 'pending',
            error_message TEXT,
            chunks_created INT DEFAULT 0,
            metadata JSONB,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        );
        ";
        
        $pgsql->exec($sql);
        
        // Criar constraint única e índices
        $pgsql->exec("CREATE UNIQUE INDEX IF NOT EXISTS unique_scraping_url_agent ON ai_url_scraping(ai_agent_id, url)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_scraping_agent ON ai_url_scraping(ai_agent_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_scraping_status ON ai_url_scraping(status)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_scraping_created ON ai_url_scraping(created_at)");
        
        echo "✅ Tabela 'ai_url_scraping' criada com sucesso no PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "❌ Erro ao criar tabela 'ai_url_scraping': " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_ai_url_scraping_table() {
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Nada para remover.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Remover índices primeiro
        $pgsql->exec("DROP INDEX IF EXISTS unique_scraping_url_agent");
        $pgsql->exec("DROP INDEX IF EXISTS idx_scraping_agent");
        $pgsql->exec("DROP INDEX IF EXISTS idx_scraping_status");
        $pgsql->exec("DROP INDEX IF EXISTS idx_scraping_created");
        
        // Remover tabela
        $pgsql->exec("DROP TABLE IF EXISTS ai_url_scraping");
        
        echo "✅ Tabela 'ai_url_scraping' removida do PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_url_scraping': " . $e->getMessage() . "\n";
    }
}

