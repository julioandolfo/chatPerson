<?php
/**
 * Migration: Adicionar campo ai_agent_id na tabela messages
 * Para identificar mensagens enviadas por agentes de IA
 */

function up_add_ai_agent_id_to_messages() {
    global $pdo;
    
    // Verificar se a coluna já existe
    $checkSql = "SELECT COUNT(*) as count 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'messages' 
                 AND COLUMN_NAME = 'ai_agent_id'";
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    $stmt = $db->query($checkSql);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] > 0) {
        echo "⚠️  Coluna 'ai_agent_id' já existe na tabela 'messages'\n";
        return;
    }
    
    // Adicionar coluna primeiro
    $sql1 = "ALTER TABLE messages 
             ADD COLUMN ai_agent_id INT NULL COMMENT 'ID do agente de IA que enviou a mensagem (NULL = humano)'";
    
    try {
        $db->exec($sql1);
        echo "✅ Coluna 'ai_agent_id' adicionada à tabela 'messages'!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Erro ao adicionar coluna: " . $e->getMessage() . "\n";
        return;
    }
    
    // Adicionar índice
    try {
        $db->exec("ALTER TABLE messages ADD INDEX idx_ai_agent_id (ai_agent_id)");
        echo "✅ Índice 'idx_ai_agent_id' criado!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Aviso ao criar índice: " . $e->getMessage() . "\n";
    }
    
    // Adicionar foreign key
    try {
        $db->exec("ALTER TABLE messages ADD FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL");
        echo "✅ Foreign key criada!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Aviso ao criar foreign key: " . $e->getMessage() . "\n";
    }
}

function down_add_ai_agent_id_to_messages() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Remover foreign key primeiro
        $db->exec("ALTER TABLE messages DROP FOREIGN KEY messages_ibfk_1");
    } catch (\PDOException $e) {
        // Tentar outros nomes possíveis
        try {
            $db->exec("ALTER TABLE messages DROP FOREIGN KEY messages_ibfk_ai_agent");
        } catch (\PDOException $e2) {
            // Ignorar se não existir
        }
    }
    
    try {
        $db->exec("ALTER TABLE messages DROP INDEX idx_ai_agent_id");
    } catch (\PDOException $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE messages DROP COLUMN ai_agent_id");
        echo "✅ Coluna 'ai_agent_id' removida da tabela 'messages'!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Erro ao remover coluna: " . $e->getMessage() . "\n";
    }
}
