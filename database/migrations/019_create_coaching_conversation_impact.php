<?php
/**
 * Migration: Criar tabela de impacto do coaching em conversas
 */

function up_create_coaching_conversation_impact() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS coaching_conversation_impact (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NOT NULL,
        
        -- Antes do coaching
        avg_response_time_before INT DEFAULT NULL COMMENT 'Tempo médio resposta antes (segundos)',
        messages_count_before INT DEFAULT 0,
        
        -- Depois do coaching
        avg_response_time_after INT DEFAULT NULL COMMENT 'Tempo médio resposta depois (segundos)',
        messages_count_after INT DEFAULT 0,
        
        -- Hints utilizados
        total_hints INT DEFAULT 0,
        hints_helpful INT DEFAULT 0,
        hints_not_helpful INT DEFAULT 0,
        suggestions_used INT DEFAULT 0,
        
        -- Resultado da conversa
        conversation_outcome VARCHAR(50) DEFAULT NULL COMMENT 'closed, converted, escalated, abandoned',
        sales_value DECIMAL(10,2) DEFAULT 0,
        conversion_time_minutes INT DEFAULT NULL,
        
        -- Performance comparativa
        performance_improvement_score DECIMAL(3,2) DEFAULT 0 COMMENT '0-5 score de melhoria',
        
        -- Timestamps
        first_hint_at TIMESTAMP NULL,
        last_hint_at TIMESTAMP NULL,
        conversation_ended_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_conversation (conversation_id),
        INDEX idx_agent (agent_id),
        INDEX idx_outcome (conversation_outcome),
        INDEX idx_created (created_at),
        UNIQUE KEY unique_conversation (conversation_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_conversation_impact' criada com sucesso!\n";
}

function down_create_coaching_conversation_impact() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS coaching_conversation_impact";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'coaching_conversation_impact' removida!\n";
}
