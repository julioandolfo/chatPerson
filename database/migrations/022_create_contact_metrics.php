<?php
/**
 * Migration 022 - Criar Tabela de Métricas de Contatos
 * 
 * Objetivo: Armazenar métricas pré-calculadas de contatos
 * para evitar queries pesadas em tempo real
 * 
 * Estratégia:
 * - CRON calcula métricas periodicamente
 * - ContactController apenas busca dados já calculados
 * - Recalcula apenas quando há mudanças (novas mensagens)
 * 
 * Data: 2026-01-12
 */

function up_create_contact_metrics() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS contact_metrics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT NOT NULL,
        
        -- Métricas calculadas
        total_conversations INT DEFAULT 0,
        open_conversations INT DEFAULT 0,
        closed_conversations INT DEFAULT 0,
        avg_response_time_minutes DECIMAL(10,2) DEFAULT NULL,
        last_message_at TIMESTAMP NULL,
        
        -- Controle de recálculo
        last_calculated_at TIMESTAMP NULL,
        needs_recalculation TINYINT(1) DEFAULT 1,
        calculation_priority INT DEFAULT 0,
        
        -- Status da conversa
        has_open_conversations TINYINT(1) DEFAULT 0,
        last_conversation_status VARCHAR(50) DEFAULT NULL,
        
        -- Timestamps
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Índices
        UNIQUE KEY idx_contact_metrics_contact (contact_id),
        INDEX idx_contact_metrics_needs_recalc (needs_recalculation, calculation_priority),
        INDEX idx_contact_metrics_open (has_open_conversations, needs_recalculation),
        INDEX idx_contact_metrics_calculated (last_calculated_at),
        
        -- Foreign key
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    
    echo "✅ Tabela 'contact_metrics' criada com sucesso!\n";
    echo "\n";
    echo "📊 PRÓXIMOS PASSOS:\n";
    echo "  1. Rodar cálculo inicial: php cron/calculate-contact-metrics.php\n";
    echo "  2. Adicionar ao crontab: */30 * * * * php cron/calculate-contact-metrics.php\n";
    echo "\n";
}

function down_create_contact_metrics() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $db->exec("DROP TABLE IF EXISTS contact_metrics");
    
    echo "✅ Tabela 'contact_metrics' removida com sucesso!\n";
}
