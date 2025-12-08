<?php
/**
 * Migration: Criar tabela scheduled_messages (mensagens agendadas)
 */

function up_scheduled_messages_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS scheduled_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL COMMENT 'Quem agendou',
        content TEXT NOT NULL COMMENT 'Conteúdo da mensagem',
        attachments JSON NULL COMMENT 'Anexos (se houver)',
        scheduled_at DATETIME NOT NULL COMMENT 'Data/hora agendada',
        sent_at DATETIME NULL COMMENT 'Quando foi enviada (NULL = pendente)',
        status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, sent, cancelled, failed',
        cancel_if_resolved TINYINT(1) DEFAULT 0 COMMENT 'Cancelar se conversa foi resolvida',
        cancel_if_responded TINYINT(1) DEFAULT 0 COMMENT 'Cancelar se já foi respondida',
        error_message TEXT NULL COMMENT 'Erro ao enviar (se houver)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_scheduled_at (scheduled_at),
        INDEX idx_status (status),
        INDEX idx_status_scheduled (status, scheduled_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'scheduled_messages' criada com sucesso!\n";
        } catch (\PDOException $e) {
            // Tentar sem IF NOT EXISTS (MySQL antigo)
            try {
                $sql2 = "CREATE TABLE scheduled_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id INT NOT NULL,
                    user_id INT NOT NULL,
                    content TEXT NOT NULL,
                    attachments JSON NULL,
                    scheduled_at DATETIME NOT NULL,
                    sent_at DATETIME NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    cancel_if_resolved TINYINT(1) DEFAULT 0,
                    cancel_if_responded TINYINT(1) DEFAULT 0,
                    error_message TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_conversation_id (conversation_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_scheduled_at (scheduled_at),
                    INDEX idx_status (status),
                    INDEX idx_status_scheduled (status, scheduled_at),
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql2);
                echo "✅ Tabela 'scheduled_messages' criada com sucesso!\n";
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao criar tabela: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'scheduled_messages' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_scheduled_messages_table() {
    $sql = "DROP TABLE IF EXISTS scheduled_messages";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'scheduled_messages' removida!\n";
}

