<?php
/**
 * Migration: Adicionar condições de ativação para bônus
 * 
 * Permite vincular bônus de metas a outras métricas como condição de ativação.
 * Ex: Bônus de faturamento só é liberado se taxa de conversão >= 15%
 */

use App\Helpers\Database;

return new class {
    
    public function up(): void
    {
        // Tabela de condições de ativação de bônus
        Database::execute("
            CREATE TABLE IF NOT EXISTS goal_bonus_conditions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                
                -- Referência ao tier de bônus ou à meta diretamente
                goal_id INT NOT NULL,
                bonus_tier_id INT NULL,
                
                -- Tipo da condição (métrica a verificar)
                condition_type ENUM(
                    'revenue', 'average_ticket', 'conversion_rate', 'sales_count',
                    'conversations_count', 'resolution_rate', 'response_time',
                    'csat_score', 'messages_sent', 'sla_compliance',
                    'first_response_time', 'resolution_time',
                    'goal_percentage'
                ) NOT NULL,
                
                -- Operador de comparação
                operator ENUM('>=', '>', '<=', '<', '=', '!=', 'between') NOT NULL DEFAULT '>=',
                
                -- Valor mínimo/máximo da condição
                min_value DECIMAL(12,2) NOT NULL,
                max_value DECIMAL(12,2) NULL,
                
                -- Se a condição se refere a outra meta específica
                reference_goal_id INT NULL,
                
                -- Se é obrigatória ou apenas aumenta o bônus
                is_required TINYINT(1) DEFAULT 1,
                
                -- Modificador do bônus se não for required
                bonus_modifier DECIMAL(5,2) DEFAULT 1.0 COMMENT 'Multiplicador se condição não atendida (ex: 0.5 = 50% do bônus)',
                
                -- Descrição legível
                description VARCHAR(255) NULL,
                
                -- Ordem de verificação
                check_order INT DEFAULT 0,
                
                -- Metadados
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
                FOREIGN KEY (bonus_tier_id) REFERENCES goal_bonus_tiers(id) ON DELETE CASCADE,
                FOREIGN KEY (reference_goal_id) REFERENCES goals(id) ON DELETE SET NULL,
                
                INDEX idx_goal_conditions (goal_id, is_active),
                INDEX idx_tier_conditions (bonus_tier_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Adicionar campo na goal_bonus_tiers para controle de condições
        $columns = Database::fetchAll("SHOW COLUMNS FROM goal_bonus_tiers LIKE 'has_conditions'");
        if (empty($columns)) {
            Database::execute("
                ALTER TABLE goal_bonus_tiers 
                ADD COLUMN has_conditions TINYINT(1) DEFAULT 0 AFTER tier_order,
                ADD COLUMN conditions_logic ENUM('AND', 'OR') DEFAULT 'AND' AFTER has_conditions
            ");
        }
        
        // Adicionar campos na goals para habilitar sistema de condições
        $columns = Database::fetchAll("SHOW COLUMNS FROM goals LIKE 'enable_bonus_conditions'");
        if (empty($columns)) {
            Database::execute("
                ALTER TABLE goals 
                ADD COLUMN enable_bonus_conditions TINYINT(1) DEFAULT 0 AFTER enable_bonus
            ");
        }
        
        // Log de verificação de condições
        Database::execute("
            CREATE TABLE IF NOT EXISTS goal_bonus_condition_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                
                goal_id INT NOT NULL,
                bonus_tier_id INT NULL,
                user_id INT NOT NULL,
                
                -- Resultado da verificação
                all_conditions_met TINYINT(1) NOT NULL,
                conditions_checked INT NOT NULL DEFAULT 0,
                conditions_passed INT NOT NULL DEFAULT 0,
                
                -- Detalhes em JSON
                condition_results JSON NULL,
                
                -- Bônus final aplicado
                original_bonus DECIMAL(12,2) NULL,
                final_bonus DECIMAL(12,2) NULL,
                modifier_applied DECIMAL(5,2) NULL,
                
                checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE,
                FOREIGN KEY (bonus_tier_id) REFERENCES goal_bonus_tiers(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                
                INDEX idx_goal_user_check (goal_id, user_id, checked_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    public function down(): void
    {
        Database::execute("DROP TABLE IF EXISTS goal_bonus_condition_logs");
        Database::execute("DROP TABLE IF EXISTS goal_bonus_conditions");
        
        // Remover colunas adicionadas
        try {
            Database::execute("ALTER TABLE goal_bonus_tiers DROP COLUMN has_conditions");
            Database::execute("ALTER TABLE goal_bonus_tiers DROP COLUMN conditions_logic");
            Database::execute("ALTER TABLE goals DROP COLUMN enable_bonus_conditions");
        } catch (\Exception $e) {
            // Ignorar se colunas não existem
        }
    }
};
