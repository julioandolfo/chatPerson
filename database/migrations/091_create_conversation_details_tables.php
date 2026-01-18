<?php
/**
 * Migração: Criar tabelas para detalhes da conversa
 * 
 * Tabelas criadas:
 * - funnel_stage_history: Histórico de mudanças de etapas
 * - conversation_assignments: Histórico de atribuições de agentes
 * - conversation_ratings: Avaliações de conversas
 */

use App\Helpers\Database;

return new class {
    public function up(): void
    {
        echo "🔧 Criando tabelas para detalhes da conversa...\n";
        
        $db = Database::getInstance();
        
        try {
            // Tabela: funnel_stage_history
            $sql = "CREATE TABLE IF NOT EXISTS funnel_stage_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                from_stage_id INT NULL,
                to_stage_id INT NOT NULL,
                changed_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conversation (conversation_id),
                INDEX idx_from_stage (from_stage_id),
                INDEX idx_to_stage (to_stage_id),
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (from_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
                FOREIGN KEY (to_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
                FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            echo "✅ Tabela 'funnel_stage_history' criada\n";
            
            // Tabela: conversation_assignments
            $sql = "CREATE TABLE IF NOT EXISTS conversation_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                from_agent_id INT NULL,
                to_agent_id INT NULL,
                assigned_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_conversation (conversation_id),
                INDEX idx_from_agent (from_agent_id),
                INDEX idx_to_agent (to_agent_id),
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (from_agent_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (to_agent_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            echo "✅ Tabela 'conversation_assignments' criada\n";
            
            // Tabela: conversation_ratings
            $sql = "CREATE TABLE IF NOT EXISTS conversation_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
                comment TEXT NULL,
                rated_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_conversation (conversation_id),
                INDEX idx_rating (rating),
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (rated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            echo "✅ Tabela 'conversation_ratings' criada\n";
            
            echo "\n✅ Todas as tabelas foram criadas com sucesso!\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            
        } catch (\Exception $e) {
            echo "\n❌ Erro ao criar tabelas: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    public function down(): void
    {
        echo "🗑️ Removendo tabelas de detalhes da conversa...\n";
        
        $db = Database::getInstance();
        
        try {
            $db->exec("DROP TABLE IF EXISTS conversation_ratings");
            echo "✅ Tabela 'conversation_ratings' removida\n";
            
            $db->exec("DROP TABLE IF EXISTS conversation_assignments");
            echo "✅ Tabela 'conversation_assignments' removida\n";
            
            $db->exec("DROP TABLE IF EXISTS funnel_stage_history");
            echo "✅ Tabela 'funnel_stage_history' removida\n";
            
            echo "\n✅ Todas as tabelas foram removidas!\n";
            
        } catch (\Exception $e) {
            echo "\n❌ Erro ao remover tabelas: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
};
