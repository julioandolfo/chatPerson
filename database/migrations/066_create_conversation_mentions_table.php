<?php
/**
 * Migration: Criar tabela conversation_mentions
 * 
 * Sistema de menções/convites de agentes em conversas.
 * Permite que um agente mencione outro agente em uma conversa,
 * criando um convite para participar.
 */

function up_create_conversation_mentions_table() {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Verificar se a tabela já existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'conversation_mentions'")->rowCount() > 0;
    
    if (!$tableExists) {
        $sql = "CREATE TABLE conversation_mentions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL COMMENT 'Conversa onde a menção foi feita',
            mentioned_by INT NOT NULL COMMENT 'Usuário que fez a menção',
            mentioned_user_id INT NOT NULL COMMENT 'Usuário que foi mencionado',
            message_id INT NULL COMMENT 'Mensagem onde a menção foi feita (opcional)',
            status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending' COMMENT 'Status do convite',
            note TEXT NULL COMMENT 'Nota/contexto da menção',
            responded_at TIMESTAMP NULL COMMENT 'Quando o usuário respondeu ao convite',
            expires_at TIMESTAMP NULL COMMENT 'Data de expiração do convite (opcional)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_conversation (conversation_id),
            INDEX idx_mentioned_by (mentioned_by),
            INDEX idx_mentioned_user (mentioned_user_id),
            INDEX idx_status (status),
            INDEX idx_pending_user (mentioned_user_id, status),
            
            CONSTRAINT fk_mention_conversation 
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            CONSTRAINT fk_mention_by_user 
                FOREIGN KEY (mentioned_by) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_mention_user 
                FOREIGN KEY (mentioned_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_mention_message 
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabela 'conversation_mentions' criada com sucesso!\n";
    } else {
        echo "⏭️ Tabela 'conversation_mentions' já existe.\n";
    }
}

function down_create_conversation_mentions_table() {
    $pdo = \App\Helpers\Database::getInstance();
    $pdo->exec("DROP TABLE IF EXISTS conversation_mentions");
    echo "✅ Tabela 'conversation_mentions' removida.\n";
}

