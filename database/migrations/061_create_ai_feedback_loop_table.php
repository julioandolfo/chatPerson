<?php
/**
 * Migration: Criar tabela ai_feedback_loop
 * Sistema de feedback loop para treinamento incremental dos agentes
 * 
 * IMPORTANTE: Esta migration cria a tabela no PostgreSQL (não MySQL)
 */

function up_ai_feedback_loop_table() {
    // Verificar se PostgreSQL está habilitado
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Pule esta migration ou habilite PostgreSQL primeiro.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Criar tabela no PostgreSQL
        // Nota: Foreign keys não são criadas porque ai_agents, conversations e users estão no MySQL
        // A integridade referencial será mantida via aplicação
        $sql = "
        CREATE TABLE IF NOT EXISTS ai_feedback_loop (
            id SERIAL PRIMARY KEY,
            ai_agent_id INT NOT NULL,
            conversation_id INT NOT NULL,
            message_id INT NOT NULL,
            user_question TEXT NOT NULL,
            ai_response TEXT,
            correct_answer TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            reviewed_by_user_id INT,
            reviewed_at TIMESTAMP,
            added_to_kb BOOLEAN DEFAULT FALSE,
            knowledge_base_id INT,
            created_at TIMESTAMP DEFAULT NOW()
        );
        ";
        
        $pgsql->exec($sql);
        
        // Criar índices
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_feedback_agent ON ai_feedback_loop(ai_agent_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_feedback_status ON ai_feedback_loop(status)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_feedback_pending ON ai_feedback_loop(ai_agent_id, status) WHERE status = 'pending'");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_feedback_conversation ON ai_feedback_loop(conversation_id)");
        $pgsql->exec("CREATE INDEX IF NOT EXISTS idx_feedback_created ON ai_feedback_loop(created_at)");
        
        echo "✅ Tabela 'ai_feedback_loop' criada com sucesso no PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "❌ Erro ao criar tabela 'ai_feedback_loop': " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_ai_feedback_loop_table() {
    if (!\App\Services\PostgreSQLSettingsService::isEnabled()) {
        echo "⚠️  PostgreSQL não está habilitado. Nada para remover.\n";
        return;
    }
    
    try {
        $pgsql = \App\Helpers\PostgreSQL::getConnection();
        
        // Remover índices primeiro
        $pgsql->exec("DROP INDEX IF EXISTS idx_feedback_agent");
        $pgsql->exec("DROP INDEX IF EXISTS idx_feedback_status");
        $pgsql->exec("DROP INDEX IF EXISTS idx_feedback_pending");
        $pgsql->exec("DROP INDEX IF EXISTS idx_feedback_conversation");
        $pgsql->exec("DROP INDEX IF EXISTS idx_feedback_created");
        
        // Remover tabela
        $pgsql->exec("DROP TABLE IF EXISTS ai_feedback_loop");
        
        echo "✅ Tabela 'ai_feedback_loop' removida do PostgreSQL!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_feedback_loop': " . $e->getMessage() . "\n";
    }
}

