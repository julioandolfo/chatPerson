<?php
/**
 * Migration: Criar tabela ai_knowledge_base
 * Base de conhecimento vetorizada para sistema RAG
 * 
 * IMPORTANTE: Esta migration cria a tabela no PostgreSQL (não MySQL)
 */

function up_ai_knowledge_base_table() {
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
        CREATE TABLE IF NOT EXISTS ai_knowledge_base (
            id SERIAL PRIMARY KEY,
            ai_agent_id INT NOT NULL,
            content_type VARCHAR(50) NOT NULL,
            title VARCHAR(500),
            content TEXT NOT NULL,
            source_url VARCHAR(1000),
            metadata JSONB,
            embedding vector(1536),
            chunk_index INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW()
        );
        ";
        
        $pgsql->exec($sql);
        
        // Criar índices
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_knowledge_agent ON ai_knowledge_base(ai_agent_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_knowledge_type ON ai_knowledge_base(content_type)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_knowledge_created ON ai_knowledge_base(created_at)");
        
        // Criar índice IVFFlat para busca semântica
        // Nota: IVFFlat funciona melhor com dados, mas pode ser criado vazio com lists menor
        try {
            // Tentar criar com lists pequeno primeiro (funciona mesmo sem dados)
            $pgsql->exec("
                CREATE INDEX IF NOT EXISTS idx_knowledge_embedding ON ai_knowledge_base 
                USING ivfflat (embedding vector_cosine_ops) WITH (lists = 10);
            ");
            echo "✅ Índice IVFFlat criado com sucesso!\n";
            echo "💡 Após inserir mais dados (100+ registros), recrie o índice com lists maior para melhor performance:\n";
            echo "   DROP INDEX idx_knowledge_embedding;\n";
            echo "   CREATE INDEX idx_knowledge_embedding ON ai_knowledge_base USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);\n";
        } catch (\Exception $e) {
            echo "⚠️  Índice IVFFlat não pôde ser criado: " . $e->getMessage() . "\n";
            echo "💡 Execute manualmente após inserir alguns dados:\n";
            echo "   CREATE INDEX idx_knowledge_embedding ON ai_knowledge_base USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);\n";
        }
        
        echo "✅ Tabela 'ai_knowledge_base' criada com sucesso no PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "❌ Erro ao criar tabela 'ai_knowledge_base': " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_ai_knowledge_base_table() {
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Nada para remover.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Remover índices primeiro
        $pgsql->exec("DROP INDEX IF EXISTS idx_knowledge_embedding");
        $pgsql->exec("DROP INDEX IF EXISTS idx_knowledge_agent");
        $pgsql->exec("DROP INDEX IF EXISTS idx_knowledge_type");
        $pgsql->exec("DROP INDEX IF EXISTS idx_knowledge_created");
        
        // Remover tabela
        $pgsql->exec("DROP TABLE IF EXISTS ai_knowledge_base");
        
        echo "✅ Tabela 'ai_knowledge_base' removida do PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_knowledge_base': " . $e->getMessage() . "\n";
    }
}

