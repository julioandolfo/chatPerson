-- ====================================================================
-- SISTEMA DE BONIFICAÇÕES OTE (On-Target Earnings)
-- Data: 20/01/2026
-- Execute tudo de uma vez
-- ====================================================================

-- 1. Adicionar campos de OTE na tabela goals
ALTER TABLE goals
    ADD COLUMN ote_base_salary DECIMAL(10,2) NULL COMMENT 'Salário base mensal (R$)' AFTER template_id,
    ADD COLUMN ote_target_commission DECIMAL(10,2) NULL COMMENT 'Comissão esperada ao atingir 100% (R$)' AFTER ote_base_salary,
    ADD COLUMN ote_total DECIMAL(10,2) NULL COMMENT 'OTE Total = Base + Target Commission (R$)' AFTER ote_target_commission,
    ADD COLUMN enable_bonus TINYINT(1) DEFAULT 0 COMMENT 'Habilitar sistema de bonificação' AFTER ote_total,
    ADD COLUMN bonus_calculation_type ENUM('fixed', 'percentage', 'tiered') DEFAULT 'tiered' COMMENT 'Tipo de cálculo do bonus' AFTER enable_bonus;

-- 2. Criar tabela de níveis de bonificação (tiers)
CREATE TABLE IF NOT EXISTS goal_bonus_tiers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Criar tabela de bonificações ganhas
CREATE TABLE IF NOT EXISTS goal_bonus_earned (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Criar tabela de histórico de pagamentos
CREATE TABLE IF NOT EXISTS goal_bonus_payments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- EXEMPLO: CRIAR META COM OTE E BONIFICAÇÕES
-- ====================================================================

/*
-- Exemplo de meta com OTE e tiers de bonificação

-- 1. Inserir meta
INSERT INTO goals (
    name, description, type, target_type, target_id,
    target_value, period_type, start_date, end_date,
    ote_base_salary, ote_target_commission, ote_total,
    enable_bonus, bonus_calculation_type,
    flag_critical_threshold, flag_warning_threshold, flag_good_threshold,
    created_by
) VALUES (
    'Meta de Vendas - Janeiro 2026',
    'Meta mensal de faturamento com sistema OTE',
    'revenue',
    'individual',
    1, -- ID do agente
    200000.00, -- R$ 200 mil de meta
    'monthly',
    '2026-01-01',
    '2026-01-31',
    3000.00, -- R$ 3.000 salário base
    2000.00, -- R$ 2.000 comissão ao atingir 100%
    5000.00, -- R$ 5.000 OTE total
    1, -- Bonus habilitado
    'tiered', -- Bonus escalonado
    70.0, 85.0, 95.0,
    1 -- ID do criador
);

-- Pegar o ID da meta criada
SET @goal_id = LAST_INSERT_ID();

-- 2. Criar tiers de bonificação
INSERT INTO goal_bonus_tiers (goal_id, threshold_percentage, bonus_amount, tier_name, tier_color, tier_order, is_cumulative) VALUES
    (@goal_id, 50.0,  600.00,  'Bronze 🥉',   '#CD7F32', 0, 0),  -- 50% = R$ 600
    (@goal_id, 70.0,  1000.00, 'Prata 🥈',    '#C0C0C0', 1, 0),  -- 70% = R$ 1.000
    (@goal_id, 90.0,  1600.00, 'Ouro 🥇',     '#FFD700', 2, 0),  -- 90% = R$ 1.600
    (@goal_id, 100.0, 2000.00, 'Platina 💎',  '#E5E4E2', 3, 0),  -- 100% = R$ 2.000
    (@goal_id, 120.0, 3000.00, 'Diamante 💠', '#B9F2FF', 4, 0);  -- 120% = R$ 3.000

-- Exemplo com bônus cumulativo (soma todos os tiers)
-- INSERT INTO goal_bonus_tiers (goal_id, threshold_percentage, bonus_amount, tier_name, is_cumulative) VALUES
--     (@goal_id, 50.0,  300.00, 'Tier 1', 1),  -- 50% = +R$ 300
--     (@goal_id, 75.0,  400.00, 'Tier 2', 1),  -- 75% = +R$ 400 (total: R$ 700)
--     (@goal_id, 100.0, 500.00, 'Tier 3', 1);  -- 100% = +R$ 500 (total: R$ 1.200)
*/

-- ====================================================================
-- CONSULTAS ÚTEIS
-- ====================================================================

-- Ver metas com OTE habilitado
SELECT 
    id, name, type, target_type,
    ote_base_salary, ote_target_commission, ote_total,
    enable_bonus
FROM goals 
WHERE enable_bonus = 1;

-- Ver tiers de uma meta
SELECT 
    tier_name,
    threshold_percentage,
    bonus_amount,
    is_cumulative
FROM goal_bonus_tiers
WHERE goal_id = 1  -- Substituir pelo ID da meta
ORDER BY threshold_percentage ASC;

-- Ver bonificações de um agente
SELECT 
    g.name as meta,
    gbe.percentage_achieved,
    gbe.bonus_amount,
    gbe.status,
    gbe.period_start,
    gbe.period_end,
    gbt.tier_name
FROM goal_bonus_earned gbe
INNER JOIN goals g ON gbe.goal_id = g.id
LEFT JOIN goal_bonus_tiers gbt ON gbe.tier_id = gbt.id
WHERE gbe.user_id = 1  -- ID do agente
ORDER BY gbe.earned_at DESC;

-- Total de bonificações por status
SELECT 
    user_id,
    u.name,
    status,
    COUNT(*) as quantidade,
    SUM(bonus_amount) as total
FROM goal_bonus_earned gbe
INNER JOIN users u ON gbe.user_id = u.id
GROUP BY user_id, status
ORDER BY total DESC;

-- Bonificações pendentes de aprovação
SELECT 
    u.name as agente,
    g.name as meta,
    gbe.bonus_amount,
    gbe.percentage_achieved,
    gbe.earned_at
FROM goal_bonus_earned gbe
INNER JOIN users u ON gbe.user_id = u.id
INNER JOIN goals g ON gbe.goal_id = g.id
WHERE gbe.status = 'pending'
ORDER BY gbe.earned_at DESC;

-- Total pago por mês
SELECT 
    DATE_FORMAT(paid_at, '%Y-%m') as mes,
    COUNT(*) as bonificacoes_pagas,
    SUM(bonus_amount) as total_pago
FROM goal_bonus_earned
WHERE status = 'paid'
GROUP BY DATE_FORMAT(paid_at, '%Y-%m')
ORDER BY mes DESC;

-- ====================================================================
-- VERIFICAÇÃO
-- ====================================================================

-- Verificar estrutura
SELECT 
    'goals' as tabela,
    COUNT(*) as total_colunas
FROM information_schema.columns
WHERE table_schema = DATABASE() 
AND table_name = 'goals'
AND column_name LIKE 'ote%' OR column_name LIKE '%bonus%'
UNION ALL
SELECT 'goal_bonus_tiers', COUNT(*)
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'goal_bonus_tiers'
UNION ALL
SELECT 'goal_bonus_earned', COUNT(*)
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'goal_bonus_earned'
UNION ALL
SELECT 'goal_bonus_payments', COUNT(*)
FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'goal_bonus_payments';

-- Pronto! Sistema de OTE e Bonificações instalado ✅
