<?php
/**
 * Migration: Criar tabela de histórico de atribuições de conversas
 * Registra TODAS as atribuições de agentes às conversas
 */

function up_create_conversation_assignments_history() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        agent_id INT NULL COMMENT 'ID do agente atribuído (NULL = conversa não atribuída)',
        assigned_by INT NULL COMMENT 'ID do usuário que fez a atribuição (NULL = sistema/automação)',
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation_agent (conversation_id, agent_id),
        INDEX idx_agent_date (agent_id, assigned_at),
        INDEX idx_conversation_date (conversation_id, assigned_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "✅ Tabela 'conversation_assignments' criada com sucesso!\n";
    
    // Popular com dados existentes (conversas já atribuídas)
    $sql = "INSERT INTO conversation_assignments (conversation_id, agent_id, assigned_at)
            SELECT id, agent_id, COALESCE(assigned_at, created_at, NOW())
            FROM conversations
            WHERE agent_id IS NOT NULL
            AND id NOT IN (SELECT conversation_id FROM conversation_assignments)";
    
    $db->exec($sql);
    echo "✅ Histórico inicial populado com conversas existentes!\n";
}

function down_create_conversation_assignments_history() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    $sql = "DROP TABLE IF EXISTS conversation_assignments";
    $db->exec($sql);
    
    echo "✅ Tabela 'conversation_assignments' removida!\n";
}
