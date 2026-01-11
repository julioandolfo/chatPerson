<?php
/**
 * Migration: Criar tabela de knowledge base de coaching no PostgreSQL
 * Esta tabela armazena conhecimento extraído de hints bem-sucedidos
 * para aprendizado contínuo do sistema via RAG
 */

function up_create_coaching_knowledge_base_postgres() {
    // Verificar se PostgreSQL está configurado
    if (!\App\Helpers\PostgreSQL::isAvailable()) {
        echo "⚠️  PostgreSQL não configurado. Pulando migration de coaching_knowledge_base.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Criar tabela
        $sql = "CREATE TABLE IF NOT EXISTS coaching_knowledge_base (
            id SERIAL PRIMARY KEY,
            
            -- Contexto da situação
            situation_type VARCHAR(50) NOT NULL,
            client_message TEXT NOT NULL,
            conversation_context TEXT,
            
            -- Resposta/Ação bem-sucedida
            successful_response TEXT NOT NULL,
            agent_action VARCHAR(100),
            
            -- Resultado
            conversation_outcome VARCHAR(50),
            sales_value DECIMAL(10,2) DEFAULT 0,
            time_to_outcome_minutes INT,
            
            -- Metadados
            agent_id INT NOT NULL,
            conversation_id INT NOT NULL,
            hint_id INT NOT NULL,
            department VARCHAR(100),
            funnel_stage VARCHAR(100),
            
            -- Qualidade validada
            feedback_score INT DEFAULT 0 CHECK (feedback_score BETWEEN 1 AND 5),
            times_reused INT DEFAULT 0,
            success_rate DECIMAL(5,2) DEFAULT 0,
            
            -- Vetorização (pgvector)
            embedding vector(1536),
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pgsql->exec($sql);
        
        // Criar índices
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_situation ON coaching_knowledge_base(situation_type)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_agent ON coaching_knowledge_base(agent_id)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_conversation ON coaching_knowledge_base(conversation_id)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_hint ON coaching_knowledge_base(hint_id)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_outcome ON coaching_knowledge_base(conversation_outcome)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_score ON coaching_knowledge_base(feedback_score)",
            "CREATE INDEX IF NOT EXISTS idx_coaching_kb_created ON coaching_knowledge_base(created_at)"
        ];
        
        foreach ($indexes as $indexSql) {
            $pgsql->exec($indexSql);
        }
        
        // Criar índice vetorial (ivfflat para busca semântica)
        $vectorIndexSql = "CREATE INDEX IF NOT EXISTS idx_coaching_kb_embedding 
                          ON coaching_knowledge_base 
                          USING ivfflat (embedding vector_cosine_ops) 
                          WITH (lists = 100)";
        
        $pgsql->exec($vectorIndexSql);
        
        echo "✅ Tabela 'coaching_knowledge_base' criada no PostgreSQL com sucesso!\n";
        echo "✅ Índices criados (incluindo índice vetorial para RAG)!\n";
        
    } catch (\Exception $e) {
        echo "❌ Erro ao criar tabela no PostgreSQL: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_create_coaching_knowledge_base_postgres() {
    if (!\App\Helpers\PostgreSQL::isAvailable()) {
        echo "⚠️  PostgreSQL não configurado. Pulando remoção de coaching_knowledge_base.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        $sql = "DROP TABLE IF EXISTS coaching_knowledge_base CASCADE";
        $pgsql->exec($sql);
        
        echo "✅ Tabela 'coaching_knowledge_base' removida do PostgreSQL!\n";
        
    } catch (\Exception $e) {
        echo "❌ Erro ao remover tabela do PostgreSQL: " . $e->getMessage() . "\n";
        throw $e;
    }
}
