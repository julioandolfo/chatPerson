<?php
/**
 * Migration: Criar tabelas para análise de performance de vendedores
 */

require_once __DIR__ . '/../../app/Helpers/Database.php';

function up_create_agent_performance_analysis_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Tabela principal: análises individuais de conversas
    $sql = "CREATE TABLE IF NOT EXISTS agent_performance_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        
        -- Notas individuais (0-5 com 1 casa decimal)
        proactivity_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Proatividade do vendedor',
        objection_handling_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Quebra de objeções',
        rapport_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Rapport e empatia',
        closing_techniques_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Técnicas de fechamento',
        qualification_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Qualificação do lead',
        clarity_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Clareza na comunicação',
        value_proposition_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Apresentação de valor',
        response_time_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Tempo de resposta',
        follow_up_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Follow-up e próximos passos',
        professionalism_score DECIMAL(2,1) DEFAULT NULL COMMENT 'Profissionalismo',
        
        -- Nota geral (média ponderada 0-5)
        overall_score DECIMAL(3,2) DEFAULT NULL COMMENT 'Nota geral ponderada',
        
        -- Análises textuais (JSON)
        strengths JSON DEFAULT NULL COMMENT 'Lista de pontos fortes',
        weaknesses JSON DEFAULT NULL COMMENT 'Lista de pontos fracos',
        improvement_suggestions JSON DEFAULT NULL COMMENT 'Sugestões de melhoria',
        key_moments JSON DEFAULT NULL COMMENT 'Momentos-chave da conversa',
        detailed_analysis TEXT DEFAULT NULL COMMENT 'Análise detalhada completa',
        
        -- Metadados da conversa
        messages_analyzed INT DEFAULT 0 COMMENT 'Total de mensagens analisadas',
        agent_messages_count INT DEFAULT 0 COMMENT 'Mensagens enviadas pelo agente',
        client_messages_count INT DEFAULT 0 COMMENT 'Mensagens do cliente',
        conversation_duration_minutes INT DEFAULT NULL COMMENT 'Duração em minutos',
        
        -- Contexto
        funnel_stage VARCHAR(100) DEFAULT NULL COMMENT 'Estágio do funil',
        conversation_value DECIMAL(10,2) DEFAULT NULL COMMENT 'Valor estimado da venda',
        
        -- IA
        model_used VARCHAR(50) DEFAULT NULL COMMENT 'Modelo GPT usado',
        tokens_used INT DEFAULT 0 COMMENT 'Tokens consumidos',
        cost DECIMAL(10,6) DEFAULT 0 COMMENT 'Custo em USD',
        
        -- Timestamps
        analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi analisado',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        
        INDEX idx_agent_score (agent_id, overall_score),
        INDEX idx_analyzed_at (analyzed_at),
        INDEX idx_conversation (conversation_id),
        INDEX idx_overall_score (overall_score),
        UNIQUE KEY unique_conversation (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'agent_performance_analysis' criada com sucesso!\n";
    
    // Tabela de sumários agregados por período
    $sql = "CREATE TABLE IF NOT EXISTS agent_performance_summary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly' COMMENT 'Tipo de período',
        period_start DATE NOT NULL COMMENT 'Início do período',
        period_end DATE NOT NULL COMMENT 'Fim do período',
        
        -- Médias das dimensões (0-5)
        avg_proactivity DECIMAL(3,2) DEFAULT NULL,
        avg_objection_handling DECIMAL(3,2) DEFAULT NULL,
        avg_rapport DECIMAL(3,2) DEFAULT NULL,
        avg_closing_techniques DECIMAL(3,2) DEFAULT NULL,
        avg_qualification DECIMAL(3,2) DEFAULT NULL,
        avg_clarity DECIMAL(3,2) DEFAULT NULL,
        avg_value_proposition DECIMAL(3,2) DEFAULT NULL,
        avg_response_time DECIMAL(3,2) DEFAULT NULL,
        avg_follow_up DECIMAL(3,2) DEFAULT NULL,
        avg_professionalism DECIMAL(3,2) DEFAULT NULL,
        
        -- Nota geral
        avg_overall_score DECIMAL(3,2) DEFAULT NULL COMMENT 'Média geral do período',
        
        -- Estatísticas
        total_conversations_analyzed INT DEFAULT 0 COMMENT 'Total de conversas analisadas',
        total_messages_sent INT DEFAULT 0 COMMENT 'Total de mensagens enviadas',
        avg_conversation_duration INT DEFAULT NULL COMMENT 'Duração média em minutos',
        total_sales_value DECIMAL(10,2) DEFAULT 0 COMMENT 'Valor total de vendas',
        
        -- Ranking
        rank_in_team INT DEFAULT NULL COMMENT 'Posição no ranking do time',
        rank_in_department INT DEFAULT NULL COMMENT 'Posição no ranking do setor',
        
        -- Custos
        total_cost DECIMAL(10,2) DEFAULT 0 COMMENT 'Custo total de análises',
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        
        UNIQUE KEY unique_agent_period (agent_id, period_type, period_start, period_end),
        INDEX idx_period (period_start, period_end),
        INDEX idx_overall_score (avg_overall_score),
        INDEX idx_agent_period_type (agent_id, period_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'agent_performance_summary' criada com sucesso!\n";
    
    // Tabela de badges/conquistas
    $sql = "CREATE TABLE IF NOT EXISTS agent_performance_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        badge_type VARCHAR(50) NOT NULL COMMENT 'Tipo de badge',
        badge_name VARCHAR(100) NOT NULL COMMENT 'Nome do badge',
        badge_description TEXT DEFAULT NULL COMMENT 'Descrição',
        badge_icon VARCHAR(50) DEFAULT NULL COMMENT 'Emoji/ícone',
        badge_level ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze' COMMENT 'Nível',
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando conquistou',
        related_data JSON DEFAULT NULL COMMENT 'Dados relacionados',
        
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        
        INDEX idx_agent_badges (agent_id, earned_at),
        INDEX idx_badge_type (badge_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'agent_performance_badges' criada com sucesso!\n";
    
    // Tabela de melhores práticas (golden conversations)
    $sql = "CREATE TABLE IF NOT EXISTS agent_performance_best_practices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        analysis_id INT NOT NULL,
        category VARCHAR(50) NOT NULL COMMENT 'Categoria (ex: objection_handling)',
        title VARCHAR(200) NOT NULL COMMENT 'Título da prática',
        description TEXT DEFAULT NULL COMMENT 'Descrição',
        excerpt TEXT DEFAULT NULL COMMENT 'Trecho relevante da conversa',
        score DECIMAL(3,2) DEFAULT NULL COMMENT 'Nota que recebeu',
        is_featured BOOLEAN DEFAULT FALSE COMMENT 'Destacado para treino',
        views INT DEFAULT 0 COMMENT 'Quantas vezes foi visualizado',
        helpful_votes INT DEFAULT 0 COMMENT 'Votos de útil',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (analysis_id) REFERENCES agent_performance_analysis(id) ON DELETE CASCADE,
        
        INDEX idx_category (category),
        INDEX idx_featured (is_featured, score),
        INDEX idx_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'agent_performance_best_practices' criada com sucesso!\n";
    
    // Tabela de objetivos/metas
    $sql = "CREATE TABLE IF NOT EXISTS agent_performance_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        dimension VARCHAR(50) NOT NULL COMMENT 'Dimensão (ex: proactivity)',
        current_score DECIMAL(3,2) DEFAULT NULL COMMENT 'Nota atual',
        target_score DECIMAL(3,2) NOT NULL COMMENT 'Meta a atingir',
        deadline DATE DEFAULT NULL COMMENT 'Prazo',
        status ENUM('active', 'completed', 'failed', 'cancelled') DEFAULT 'active',
        created_by INT DEFAULT NULL COMMENT 'Quem criou (supervisor)',
        notes TEXT DEFAULT NULL COMMENT 'Observações',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        
        INDEX idx_agent_status (agent_id, status),
        INDEX idx_deadline (deadline)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'agent_performance_goals' criada com sucesso!\n";
}

function down_create_agent_performance_analysis_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $db->exec("DROP TABLE IF EXISTS agent_performance_goals");
    $db->exec("DROP TABLE IF EXISTS agent_performance_best_practices");
    $db->exec("DROP TABLE IF EXISTS agent_performance_badges");
    $db->exec("DROP TABLE IF EXISTS agent_performance_summary");
    $db->exec("DROP TABLE IF EXISTS agent_performance_analysis");
    
    echo "✅ Tabelas de performance removidas com sucesso!\n";
}
