<?php
/**
 * Migration: Criar tabela de sumários de analytics de coaching
 */

function up_create_coaching_analytics_summary() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS coaching_analytics_summary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        agent_id INT NOT NULL,
        period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        
        -- Estatísticas de uso
        total_hints_received INT DEFAULT 0 COMMENT 'Total de hints recebidos',
        total_hints_viewed INT DEFAULT 0 COMMENT 'Total de hints visualizados',
        total_hints_helpful INT DEFAULT 0 COMMENT 'Marcados como útil',
        total_hints_not_helpful INT DEFAULT 0 COMMENT 'Marcados como não útil',
        total_suggestions_used INT DEFAULT 0 COMMENT 'Sugestões clicadas/usadas',
        
        -- Por tipo de hint
        hints_objection INT DEFAULT 0,
        hints_opportunity INT DEFAULT 0,
        hints_buying_signal INT DEFAULT 0,
        hints_negative_sentiment INT DEFAULT 0,
        hints_closing_opportunity INT DEFAULT 0,
        hints_escalation INT DEFAULT 0,
        hints_question INT DEFAULT 0,
        
        -- Taxa de conversão (antes vs depois de usar hint)
        conversations_with_hints INT DEFAULT 0,
        conversations_converted INT DEFAULT 0,
        conversion_rate_improvement DECIMAL(5,2) DEFAULT 0 COMMENT 'Melhoria % na conversão',
        
        -- Performance
        avg_response_time_seconds INT DEFAULT 0,
        avg_conversation_duration_minutes INT DEFAULT 0,
        sales_value_total DECIMAL(10,2) DEFAULT 0,
        
        -- Custos
        total_cost DECIMAL(10,4) DEFAULT 0,
        total_tokens INT DEFAULT 0,
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_agent_period (agent_id, period_type, period_start),
        INDEX idx_period (period_start, period_end),
        INDEX idx_period_type (period_type),
        UNIQUE KEY unique_agent_period (agent_id, period_type, period_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_analytics_summary' criada com sucesso!\n";
}

function down_create_coaching_analytics_summary() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS coaching_analytics_summary";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_analytics_summary' removida!\n";
}
