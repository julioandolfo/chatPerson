<?php
/**
 * Migration: Adicionar Sistema de FLAGS e Projeções às Metas
 * Criado em: 20/01/2026
 * 
 * Adiciona:
 * - Thresholds configuráveis para flags (crítico, atenção, bom)
 * - Campos para projeção de atingimento
 * - Configurações de alertas
 */

function up_goal_flags_and_projections() {
    global $pdo;
    
    $sql = "ALTER TABLE goals
            ADD COLUMN flag_critical_threshold DECIMAL(5,2) DEFAULT 70.00 COMMENT 'Abaixo deste % = Flag Vermelha' AFTER notify_at_percentage,
            ADD COLUMN flag_warning_threshold DECIMAL(5,2) DEFAULT 85.00 COMMENT 'Abaixo deste % = Flag Amarela' AFTER flag_critical_threshold,
            ADD COLUMN flag_good_threshold DECIMAL(5,2) DEFAULT 95.00 COMMENT 'Abaixo deste % = Flag Verde' AFTER flag_warning_threshold,
            ADD COLUMN enable_projection TINYINT(1) DEFAULT 1 COMMENT 'Habilitar cálculo de projeção' AFTER flag_good_threshold,
            ADD COLUMN alert_on_risk TINYINT(1) DEFAULT 1 COMMENT 'Alertar quando em risco' AFTER enable_projection,
            ADD COLUMN template_id INT NULL COMMENT 'ID da meta template (para metas recorrentes)' AFTER alert_on_risk,
            ADD INDEX idx_template_id (template_id)";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de flags e projeções adicionados à tabela 'goals'!\n";
    
    // Adicionar campos de projeção ao goal_progress
    $sql = "ALTER TABLE goal_progress
            ADD COLUMN days_elapsed INT NULL COMMENT 'Dias decorridos desde início' AFTER status,
            ADD COLUMN days_total INT NULL COMMENT 'Total de dias do período' AFTER days_elapsed,
            ADD COLUMN expected_percentage DECIMAL(5,2) NULL COMMENT '% esperado para este momento' AFTER days_total,
            ADD COLUMN projection_percentage DECIMAL(5,2) NULL COMMENT 'Projeção de % final' AFTER expected_percentage,
            ADD COLUMN projection_value DECIMAL(12,2) NULL COMMENT 'Projeção de valor final' AFTER projection_percentage,
            ADD COLUMN is_on_track TINYINT(1) NULL COMMENT 'Está no ritmo esperado?' AFTER projection_value,
            ADD COLUMN flag_status ENUM('critical', 'warning', 'good', 'excellent') NULL COMMENT 'Status da flag' AFTER is_on_track";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de projeção adicionados à tabela 'goal_progress'!\n";
    
    // Criar tabela de alertas de metas
    $sql = "CREATE TABLE IF NOT EXISTS goal_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        
        -- Tipo e severidade
        alert_type ENUM('off_track', 'at_risk', 'critical', 'milestone_reached') NOT NULL,
        severity ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
        
        -- Mensagem
        message TEXT NOT NULL,
        details JSON NULL COMMENT 'Detalhes adicionais em JSON',
        
        -- Controle
        is_read TINYINT(1) DEFAULT 0,
        is_resolved TINYINT(1) DEFAULT 0,
        resolved_at TIMESTAMP NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_goal_unread (goal_id, is_read),
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_alerts' criada com sucesso!\n";
}

function down_goal_flags_and_projections() {
    global $pdo;
    
    // Remover tabela de alertas
    $sql = "DROP TABLE IF EXISTS goal_alerts";
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_alerts' removida!\n";
    
    // Remover campos de projeção do goal_progress
    $sql = "ALTER TABLE goal_progress
            DROP COLUMN days_elapsed,
            DROP COLUMN days_total,
            DROP COLUMN expected_percentage,
            DROP COLUMN projection_percentage,
            DROP COLUMN projection_value,
            DROP COLUMN is_on_track,
            DROP COLUMN flag_status";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de projeção removidos da tabela 'goal_progress'!\n";
    
    // Remover campos de flags da goals
    $sql = "ALTER TABLE goals
            DROP COLUMN flag_critical_threshold,
            DROP COLUMN flag_warning_threshold,
            DROP COLUMN flag_good_threshold,
            DROP COLUMN enable_projection,
            DROP COLUMN alert_on_risk,
            DROP COLUMN template_id,
            DROP INDEX idx_template_id";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de flags removidos da tabela 'goals'!\n";
}
