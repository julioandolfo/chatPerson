<?php
/**
 * Migration: Criar tabela ai_agent_memory
 * Memória persistente dos agentes de IA
 * 
 * IMPORTANTE: Esta migration cria a tabela no PostgreSQL (não MySQL)
 */

function up_ai_agent_memory_table() {
    // Verificar se PostgreSQL está habilitado
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Pule esta migration ou habilite PostgreSQL primeiro.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Criar tabela no PostgreSQL
        // Nota: Foreign keys não são criadas porque ai_agents e conversations estão no MySQL
        // A integridade referencial será mantida via aplicação
        $sql = "
        CREATE TABLE IF NOT EXISTS ai_agent_memory (
            id SERIAL PRIMARY KEY,
            ai_agent_id INT NOT NULL,
            conversation_id INT NOT NULL,
            memory_type VARCHAR(50) NOT NULL,
            key VARCHAR(255),
            value TEXT NOT NULL,
            importance DECIMAL(3,2) DEFAULT 0.5,
            expires_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT NOW()
        );
        ";
        
        $pgsql->exec($sql);
        
        // Criar índices
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_memory_agent ON ai_agent_memory(ai_agent_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_memory_conversation ON ai_agent_memory(conversation_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_memory_key ON ai_agent_memory(ai_agent_id, key)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_memory_type ON ai_agent_memory(memory_type)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_memory_expires ON ai_agent_memory(expires_at) WHERE expires_at IS NOT NULL");
        
        echo "✅ Tabela 'ai_agent_memory' criada com sucesso no PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "❌ Erro ao criar tabela 'ai_agent_memory': " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_ai_agent_memory_table() {
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Nada para remover.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Remover índices primeiro
        $pgsql->exec("DROP INDEX IF EXISTS idx_memory_agent");
        $pgsql->exec("DROP INDEX IF EXISTS idx_memory_conversation");
        $pgsql->exec("DROP INDEX IF EXISTS idx_memory_key");
        $pgsql->exec("DROP INDEX IF EXISTS idx_memory_type");
        $pgsql->exec("DROP INDEX IF EXISTS idx_memory_expires");
        
        // Remover tabela
        $pgsql->exec("DROP TABLE IF EXISTS ai_agent_memory");
        
        echo "✅ Tabela 'ai_agent_memory' removida do PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_agent_memory': " . $e->getMessage() . "\n";
    }
}

