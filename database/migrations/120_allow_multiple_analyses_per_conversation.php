<?php
/**
 * Migration: Permitir múltiplas análises por conversa
 * 
 * Remove a constraint UNIQUE de conversation_id para permitir que
 * múltiplos agentes tenham análises separadas da mesma conversa
 */

function up_allow_multiple_analyses_per_conversation() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "Removendo constraint UNIQUE de conversation_id...\n";
    
    // Verificar se a constraint existe
    $sql = "SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'agent_performance_analysis'
            AND CONSTRAINT_TYPE = 'UNIQUE'
            AND CONSTRAINT_NAME LIKE '%conversation%'";
    
    $constraints = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    
    foreach ($constraints as $constraint) {
        $constraintName = $constraint['CONSTRAINT_NAME'];
        echo "Removendo constraint: {$constraintName}\n";
        
        try {
            $db->exec("ALTER TABLE agent_performance_analysis DROP INDEX {$constraintName}");
            echo "✅ Constraint '{$constraintName}' removida\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao remover constraint '{$constraintName}': " . $e->getMessage() . "\n";
        }
    }
    
    // Criar novo índice composto (conversation_id + agent_id)
    echo "Criando índice composto (conversation_id, agent_id)...\n";
    
    try {
        $db->exec("ALTER TABLE agent_performance_analysis 
                   ADD UNIQUE KEY unique_conversation_agent (conversation_id, agent_id)");
        echo "✅ Índice composto criado com sucesso!\n";
    } catch (\Exception $e) {
        echo "⚠️ Índice pode já existir: " . $e->getMessage() . "\n";
    }
    
    echo "✅ Migration concluída! Agora é possível ter múltiplas análises por conversa (uma por agente)\n";
}

function down_allow_multiple_analyses_per_conversation() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "Revertendo changes...\n";
    
    // Remover índice composto
    try {
        $db->exec("ALTER TABLE agent_performance_analysis DROP INDEX unique_conversation_agent");
        echo "✅ Índice composto removido\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro: " . $e->getMessage() . "\n";
    }
    
    // Recriar constraint original (se possível)
    try {
        $db->exec("ALTER TABLE agent_performance_analysis 
                   ADD UNIQUE KEY unique_conversation (conversation_id)");
        echo "✅ Constraint original recriada\n";
    } catch (\Exception $e) {
        echo "⚠️ Erro: " . $e->getMessage() . "\n";
    }
}
