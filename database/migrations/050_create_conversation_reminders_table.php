<?php
/**
 * Migration: Criar tabela conversation_reminders (lembretes de conversas)
 */

function up_conversation_reminders_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS conversation_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL COMMENT 'Quem criou o lembrete',
        reminder_at DATETIME NOT NULL COMMENT 'Data/hora do lembrete',
        note TEXT NULL COMMENT 'Nota opcional',
        is_resolved TINYINT(1) DEFAULT 0 COMMENT 'Se foi resolvido/marcado como feito',
        resolved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_user_id (user_id),
        INDEX idx_reminder_at (reminder_at),
        INDEX idx_is_resolved (is_resolved),
        INDEX idx_user_resolved (user_id, is_resolved, reminder_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'conversation_reminders' criada com sucesso!\n";
        } catch (\PDOException $e) {
            // Tentar sem IF NOT EXISTS (MySQL antigo)
            try {
                $sql2 = "CREATE TABLE conversation_reminders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    conversation_id INT NOT NULL,
                    user_id INT NOT NULL,
                    reminder_at DATETIME NOT NULL,
                    note TEXT NULL,
                    is_resolved TINYINT(1) DEFAULT 0,
                    resolved_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    
                    INDEX idx_conversation_id (conversation_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_reminder_at (reminder_at),
                    INDEX idx_is_resolved (is_resolved),
                    INDEX idx_user_resolved (user_id, is_resolved, reminder_at),
                    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $pdo->exec($sql2);
                echo "✅ Tabela 'conversation_reminders' criada com sucesso!\n";
            } catch (\PDOException $e2) {
                echo "⚠️ Erro ao criar tabela: " . $e2->getMessage() . "\n";
            }
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'conversation_reminders' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️ Erro ao criar tabela: " . $e->getMessage() . "\n";
        }
    }
}

function down_conversation_reminders_table() {
    $sql = "DROP TABLE IF EXISTS conversation_reminders";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'conversation_reminders' removida!\n";
}

