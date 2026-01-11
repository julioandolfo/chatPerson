<?php
/**
 * Migration: Atualizar campos da tabela agent_performance_goals
 * Adiciona start_date, end_date e feedback
 */

function up_update_agent_performance_goals_fields() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Adicionar start_date
        $db->exec("ALTER TABLE agent_performance_goals 
                   ADD COLUMN start_date DATE DEFAULT NULL COMMENT 'Data de início' AFTER target_score");
        echo "✅ Campo 'start_date' adicionado com sucesso!\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            echo "⚠️ Erro ao adicionar start_date: " . $e->getMessage() . "\n";
        } else {
            echo "ℹ️ Campo 'start_date' já existe\n";
        }
    }
    
    try {
        // Renomear deadline para end_date
        $db->exec("ALTER TABLE agent_performance_goals 
                   CHANGE COLUMN deadline end_date DATE DEFAULT NULL COMMENT 'Data de término'");
        echo "✅ Campo 'deadline' renomeado para 'end_date' com sucesso!\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), "Unknown column 'deadline'") !== false) {
            echo "ℹ️ Campo 'deadline' já foi renomeado\n";
        } else {
            echo "⚠️ Erro ao renomear deadline: " . $e->getMessage() . "\n";
        }
    }
    
    try {
        // Renomear notes para feedback
        $db->exec("ALTER TABLE agent_performance_goals 
                   CHANGE COLUMN notes feedback TEXT DEFAULT NULL COMMENT 'Feedback e orientações'");
        echo "✅ Campo 'notes' renomeado para 'feedback' com sucesso!\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), "Unknown column 'notes'") !== false) {
            echo "ℹ️ Campo 'notes' já foi renomeado\n";
        } else {
            echo "⚠️ Erro ao renomear notes: " . $e->getMessage() . "\n";
        }
    }
    
    echo "✅ Migration concluída!\n";
}

function down_update_agent_performance_goals_fields() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    // Reverter mudanças
    try {
        $db->exec("ALTER TABLE agent_performance_goals DROP COLUMN start_date");
        $db->exec("ALTER TABLE agent_performance_goals CHANGE COLUMN end_date deadline DATE DEFAULT NULL COMMENT 'Prazo'");
        $db->exec("ALTER TABLE agent_performance_goals CHANGE COLUMN feedback notes TEXT DEFAULT NULL COMMENT 'Observações'");
        echo "✅ Rollback concluído!\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro no rollback: " . $e->getMessage() . "\n";
    }
}
