<?php
/**
 * Migration: Sistema de Metas
 * Criado em: 20/01/2026
 * 
 * Sistema completo de metas para:
 * - Agentes individuais
 * - Times/Equipes
 * - Departamentos
 * - Empresa (global)
 * 
 * Tipos de metas:
 * - Vendas/Faturamento
 * - Ticket médio
 * - Taxa de conversão
 * - Quantidade de vendas
 * - Quantidade de conversas
 * - Taxa de resolução
 * - Tempo de resposta
 * - CSAT
 * - Mensagens enviadas
 * - SLA cumprido
 */

function up_goals_system() {
    global $pdo;
    
    // Tabela principal de metas
    $sql = "CREATE TABLE IF NOT EXISTS goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Identificação
        name VARCHAR(255) NOT NULL COMMENT 'Nome da meta',
        description TEXT NULL COMMENT 'Descrição detalhada',
        
        -- Tipo de meta
        type ENUM(
            'revenue',              -- Faturamento total (R$)
            'average_ticket',       -- Ticket médio (R$)
            'conversion_rate',      -- Taxa de conversão (%)
            'sales_count',          -- Quantidade de vendas
            'conversations_count',  -- Quantidade de conversas
            'resolution_rate',      -- Taxa de resolução (%)
            'response_time',        -- Tempo médio de resposta (minutos)
            'csat_score',           -- CSAT médio (1-5)
            'messages_sent',        -- Mensagens enviadas
            'sla_compliance',       -- Taxa de cumprimento SLA (%)
            'first_response_time',  -- Tempo de primeira resposta (minutos)
            'resolution_time'       -- Tempo de resolução (minutos)
        ) NOT NULL COMMENT 'Tipo de métrica',
        
        -- Nível da meta
        target_type ENUM('individual', 'team', 'department', 'global') NOT NULL COMMENT 'A quem se aplica',
        target_id INT NULL COMMENT 'ID do agente/time/departamento (NULL = global)',
        
        -- Valor alvo
        target_value DECIMAL(12,2) NOT NULL COMMENT 'Valor a ser atingido',
        
        -- Período
        period_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom') NOT NULL DEFAULT 'monthly',
        start_date DATE NOT NULL COMMENT 'Data de início',
        end_date DATE NOT NULL COMMENT 'Data de término',
        
        -- Configurações adicionais
        is_active TINYINT(1) DEFAULT 1 COMMENT 'Meta ativa',
        is_stretch TINYINT(1) DEFAULT 0 COMMENT 'Meta desafiadora (stretch goal)',
        priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium' COMMENT 'Prioridade',
        
        -- Notificações e gamificação
        notify_at_percentage INT DEFAULT 90 COMMENT 'Notificar ao atingir X%',
        reward_points INT DEFAULT 0 COMMENT 'Pontos ao completar',
        reward_badge VARCHAR(50) NULL COMMENT 'Badge ao completar',
        
        -- Metadados
        created_by INT NULL COMMENT 'Quem criou a meta',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Índices
        INDEX idx_target (target_type, target_id),
        INDEX idx_period (start_date, end_date),
        INDEX idx_type (type),
        INDEX idx_active (is_active),
        
        -- Foreign Keys
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goals' criada com sucesso!\n";
    
    // Tabela de progresso das metas (histórico diário)
    $sql = "CREATE TABLE IF NOT EXISTS goal_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        
        -- Data e valores
        date DATE NOT NULL COMMENT 'Data do registro',
        current_value DECIMAL(12,2) NOT NULL COMMENT 'Valor atual',
        percentage DECIMAL(5,2) NOT NULL COMMENT 'Percentual atingido',
        
        -- Status
        status ENUM('not_started', 'in_progress', 'achieved', 'exceeded', 'failed') NOT NULL DEFAULT 'in_progress',
        
        -- Metadados
        calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Índices
        INDEX idx_goal_date (goal_id, date),
        UNIQUE KEY unique_goal_date (goal_id, date),
        
        -- Foreign Keys
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_progress' criada com sucesso!\n";
    
    // Tabela de conquistas de metas (quando completa)
    $sql = "CREATE TABLE IF NOT EXISTS goal_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        
        -- Detalhes da conquista
        achieved_at TIMESTAMP NOT NULL COMMENT 'Quando foi atingida',
        final_value DECIMAL(12,2) NOT NULL COMMENT 'Valor final atingido',
        percentage DECIMAL(5,2) NOT NULL COMMENT 'Percentual final',
        days_to_achieve INT NOT NULL COMMENT 'Dias para atingir',
        
        -- Recompensas concedidas
        points_awarded INT DEFAULT 0,
        badge_awarded VARCHAR(50) NULL,
        
        -- Notificações
        notification_sent TINYINT(1) DEFAULT 0,
        notification_sent_at TIMESTAMP NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        -- Índices
        INDEX idx_goal (goal_id),
        INDEX idx_achieved_at (achieved_at),
        
        -- Foreign Keys
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_achievements' criada com sucesso!\n";
}

function down_goals_system() {
    global $pdo;
    
    $tables = ['goal_achievements', 'goal_progress', 'goals'];
    
    foreach ($tables as $table) {
        $sql = "DROP TABLE IF EXISTS $table";
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Tabela '$table' removida!\n";
    }
}
