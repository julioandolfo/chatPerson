<?php
/**
 * Migration: Sistema de Bonificações OTE (On-Target Earnings)
 * Criado em: 20/01/2026
 * 
 * Sistema de bonificações escalonadas por meta:
 * - Configurar OTE base (salário + comissão esperada)
 * - Níveis de bonificação (tiers): 50% = R$500, 100% = R$2000, etc
 * - Registro automático de bonificações ganhas
 * - Relatórios de OTE por agente/período
 */

function up_goal_bonus_system() {
    global $pdo;
    
    // 1. Adicionar campos de OTE na tabela goals
    $sql = "ALTER TABLE goals
            ADD COLUMN ote_base_salary DECIMAL(10,2) NULL COMMENT 'Salário base mensal (R$)' AFTER template_id,
            ADD COLUMN ote_target_commission DECIMAL(10,2) NULL COMMENT 'Comissão esperada ao atingir 100% (R$)' AFTER ote_base_salary,
            ADD COLUMN ote_total DECIMAL(10,2) NULL COMMENT 'OTE Total = Base + Target Commission (R$)' AFTER ote_target_commission,
            ADD COLUMN enable_bonus TINYINT(1) DEFAULT 0 COMMENT 'Habilitar sistema de bonificação' AFTER ote_total,
            ADD COLUMN bonus_calculation_type ENUM('fixed', 'percentage', 'tiered') DEFAULT 'tiered' COMMENT 'Tipo de cálculo do bonus' AFTER enable_bonus";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de OTE adicionados à tabela 'goals'!\n";
    
    // 2. Criar tabela de níveis de bonificação (tiers)
    $sql = "CREATE TABLE IF NOT EXISTS goal_bonus_tiers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        
        -- Threshold e Valor
        threshold_percentage DECIMAL(5,2) NOT NULL COMMENT '% da meta necessário para atingir',
        bonus_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do bônus (R$)',
        bonus_percentage DECIMAL(5,2) NULL COMMENT 'Ou % sobre o valor base',
        
        -- Tipo
        is_cumulative TINYINT(1) DEFAULT 0 COMMENT 'Bonus é cumulativo ou substitui anterior',
        tier_name VARCHAR(100) NULL COMMENT 'Nome do nível (ex: Bronze, Prata, Ouro)',
        tier_color VARCHAR(20) NULL COMMENT 'Cor hex para visualização',
        
        -- Ordem
        tier_order INT DEFAULT 0 COMMENT 'Ordem de exibição',
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_goal (goal_id),
        INDEX idx_threshold (goal_id, threshold_percentage),
        
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_bonus_tiers' criada com sucesso!\n";
    
    // 3. Criar tabela de bonificações ganhas
    $sql = "CREATE TABLE IF NOT EXISTS goal_bonus_earned (
        id INT AUTO_INCREMENT PRIMARY KEY,
        goal_id INT NOT NULL,
        tier_id INT NULL COMMENT 'ID do tier atingido',
        
        -- Agente e Valor
        user_id INT NOT NULL COMMENT 'Agente que ganhou',
        bonus_amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do bônus ganho (R$)',
        percentage_achieved DECIMAL(5,2) NOT NULL COMMENT '% da meta atingido',
        
        -- Período e Status
        earned_at TIMESTAMP NOT NULL COMMENT 'Quando foi conquistado',
        period_start DATE NOT NULL COMMENT 'Início do período da meta',
        period_end DATE NOT NULL COMMENT 'Fim do período da meta',
        
        status ENUM('pending', 'approved', 'paid', 'cancelled') DEFAULT 'pending',
        paid_at TIMESTAMP NULL,
        
        -- Observações
        notes TEXT NULL,
        approved_by INT NULL,
        approved_at TIMESTAMP NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_user_period (user_id, period_start, period_end),
        INDEX idx_goal (goal_id),
        INDEX idx_status (status),
        INDEX idx_earned_at (earned_at),
        
        FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
        FOREIGN KEY (tier_id) REFERENCES goal_bonus_tiers(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_bonus_earned' criada com sucesso!\n";
    
    // 4. Criar tabela de histórico de pagamentos
    $sql = "CREATE TABLE IF NOT EXISTS goal_bonus_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        
        -- Referência
        bonus_earned_id INT NOT NULL,
        
        -- Pagamento
        payment_amount DECIMAL(10,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method ENUM('cash', 'transfer', 'pix', 'check', 'salary') DEFAULT 'salary',
        payment_reference VARCHAR(255) NULL COMMENT 'Número do documento/transação',
        
        -- Controle
        paid_by INT NULL,
        notes TEXT NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_bonus (bonus_earned_id),
        INDEX idx_date (payment_date),
        
        FOREIGN KEY (bonus_earned_id) REFERENCES goal_bonus_earned(id) ON DELETE CASCADE,
        FOREIGN KEY (paid_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'goal_bonus_payments' criada com sucesso!\n";
}

function down_goal_bonus_system() {
    global $pdo;
    
    // Remover tabelas na ordem inversa (por causa das foreign keys)
    $tables = ['goal_bonus_payments', 'goal_bonus_earned', 'goal_bonus_tiers'];
    
    foreach ($tables as $table) {
        $sql = "DROP TABLE IF EXISTS $table";
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Tabela '$table' removida!\n";
    }
    
    // Remover campos de OTE da goals
    $sql = "ALTER TABLE goals
            DROP COLUMN ote_base_salary,
            DROP COLUMN ote_target_commission,
            DROP COLUMN ote_total,
            DROP COLUMN enable_bonus,
            DROP COLUMN bonus_calculation_type";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Campos de OTE removidos da tabela 'goals'!\n";
}
