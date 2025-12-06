<?php
/**
 * Migration: Adicionar campo user_id na tabela message_templates
 * Para permitir templates pessoais por agente (NULL = template global)
 */

function up_add_user_id_to_message_templates() {
    global $pdo;
    
    // Verificar se a coluna já existe
    $checkSql = "SELECT COUNT(*) as count 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'message_templates' 
                 AND COLUMN_NAME = 'user_id'";
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    $stmt = $db->query($checkSql);
    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if ($result && $result['count'] > 0) {
        echo "⚠️  Coluna 'user_id' já existe na tabela 'message_templates'\n";
        return;
    }
    
    // Adicionar coluna
    $sql = "ALTER TABLE message_templates 
            ADD COLUMN user_id INT NULL COMMENT 'ID do usuário/agente (NULL = template global)' AFTER created_by,
            ADD INDEX idx_user_id (user_id),
            ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE";
    
    try {
        $db->exec($sql);
        echo "✅ Coluna 'user_id' adicionada à tabela 'message_templates'!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Erro ao adicionar coluna: " . $e->getMessage() . "\n";
    }
}

function down_add_user_id_to_message_templates() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Remover foreign key primeiro
        $db->exec("ALTER TABLE message_templates DROP FOREIGN KEY message_templates_ibfk_2");
    } catch (\PDOException $e) {
        // Tentar outros nomes possíveis
        try {
            $db->exec("ALTER TABLE message_templates DROP FOREIGN KEY message_templates_ibfk_user");
        } catch (\PDOException $e2) {
            // Ignorar se não existir
        }
    }
    
    try {
        $db->exec("ALTER TABLE message_templates DROP INDEX idx_user_id");
    } catch (\PDOException $e) {
        // Ignorar se não existir
    }
    
    try {
        $db->exec("ALTER TABLE message_templates DROP COLUMN user_id");
        echo "✅ Coluna 'user_id' removida da tabela 'message_templates'!\n";
    } catch (\PDOException $e) {
        echo "⚠️  Erro ao remover coluna: " . $e->getMessage() . "\n";
    }
}

