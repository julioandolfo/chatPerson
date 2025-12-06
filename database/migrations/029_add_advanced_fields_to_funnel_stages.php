<?php
/**
 * Migration: Adicionar campos avançados aos estágios do funil
 * Campos para validações avançadas, limites e regras de movimentação
 */

function up_add_advanced_fields_to_funnel_stages() {
    global $pdo;
    
    $sql = "ALTER TABLE funnel_stages 
            ADD COLUMN IF NOT EXISTS max_conversations INT NULL DEFAULT NULL COMMENT 'Limite máximo de conversas simultâneas no estágio',
            ADD COLUMN IF NOT EXISTS allow_move_back BOOLEAN DEFAULT TRUE COMMENT 'Permitir mover conversas para estágios anteriores',
            ADD COLUMN IF NOT EXISTS allow_skip_stages BOOLEAN DEFAULT FALSE COMMENT 'Permitir pular estágios intermediários',
            ADD COLUMN IF NOT EXISTS blocked_stages JSON NULL COMMENT 'IDs dos estágios bloqueados (não pode mover para)',
            ADD COLUMN IF NOT EXISTS required_stages JSON NULL COMMENT 'IDs dos estágios obrigatórios (deve passar por antes)',
            ADD COLUMN IF NOT EXISTS required_tags JSON NULL COMMENT 'Tags obrigatórias para entrar no estágio',
            ADD COLUMN IF NOT EXISTS blocked_tags JSON NULL COMMENT 'Tags que bloqueiam entrada no estágio',
            ADD COLUMN IF NOT EXISTS auto_assign BOOLEAN DEFAULT FALSE COMMENT 'Auto-atribuir conversas ao entrar no estágio',
            ADD COLUMN IF NOT EXISTS auto_assign_department_id INT NULL DEFAULT NULL COMMENT 'Departamento para auto-atribuição',
            ADD COLUMN IF NOT EXISTS auto_assign_method VARCHAR(20) NULL DEFAULT 'round-robin' COMMENT 'Método de distribuição: round-robin, by-load, by-specialty',
            ADD COLUMN IF NOT EXISTS sla_hours INT NULL DEFAULT NULL COMMENT 'SLA em horas para conversas neste estágio',
            ADD COLUMN IF NOT EXISTS settings JSON NULL COMMENT 'Configurações adicionais do estágio'";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Campos avançados adicionados à tabela 'funnel_stages'!\n";
        } catch (\PDOException $e) {
            // Se der erro, tentar adicionar um por vez (MySQL pode não suportar IF NOT EXISTS)
            try {
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN max_conversations INT NULL DEFAULT NULL COMMENT 'Limite máximo de conversas simultâneas no estágio'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN allow_move_back BOOLEAN DEFAULT TRUE COMMENT 'Permitir mover conversas para estágios anteriores'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN allow_skip_stages BOOLEAN DEFAULT FALSE COMMENT 'Permitir pular estágios intermediários'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN blocked_stages JSON NULL COMMENT 'IDs dos estágios bloqueados'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN required_stages JSON NULL COMMENT 'IDs dos estágios obrigatórios'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN required_tags JSON NULL COMMENT 'Tags obrigatórias para entrar no estágio'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN blocked_tags JSON NULL COMMENT 'Tags que bloqueiam entrada no estágio'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN auto_assign BOOLEAN DEFAULT FALSE COMMENT 'Auto-atribuir conversas ao entrar no estágio'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN auto_assign_department_id INT NULL DEFAULT NULL COMMENT 'Departamento para auto-atribuição'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN auto_assign_method VARCHAR(20) NULL DEFAULT 'round-robin' COMMENT 'Método de distribuição'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN sla_hours INT NULL DEFAULT NULL COMMENT 'SLA em horas para conversas neste estágio'");
                $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN settings JSON NULL COMMENT 'Configurações adicionais do estágio'");
                echo "✅ Campos avançados adicionados à tabela 'funnel_stages'!\n";
            } catch (\PDOException $e2) {
                echo "⚠️  Campos podem já existir ou erro: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Campos avançados adicionados à tabela 'funnel_stages'!\n";
        } catch (\Exception $e) {
            echo "⚠️  Campos podem já existir\n";
        }
    }
}

function down_add_advanced_fields_to_funnel_stages() {
    $sql = "ALTER TABLE funnel_stages 
            DROP COLUMN IF EXISTS max_conversations,
            DROP COLUMN IF EXISTS allow_move_back,
            DROP COLUMN IF EXISTS allow_skip_stages,
            DROP COLUMN IF EXISTS blocked_stages,
            DROP COLUMN IF EXISTS required_stages,
            DROP COLUMN IF EXISTS required_tags,
            DROP COLUMN IF EXISTS blocked_tags,
            DROP COLUMN IF EXISTS auto_assign,
            DROP COLUMN IF EXISTS auto_assign_department_id,
            DROP COLUMN IF EXISTS auto_assign_method,
            DROP COLUMN IF EXISTS sla_hours,
            DROP COLUMN IF EXISTS settings";
    
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Campos avançados removidos da tabela 'funnel_stages'!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover campos: " . $e->getMessage() . "\n";
    }
}

