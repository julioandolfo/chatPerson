<?php
/**
 * Migration: Criar tabela api4com_calls (chamadas Api4Com)
 */

function up_api4com_calls_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS api4com_calls (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NULL COMMENT 'Conversa relacionada (opcional)',
        contact_id INT NOT NULL COMMENT 'Contato que recebeu/iniciou a chamada',
        agent_id INT NULL COMMENT 'Agente que fez a chamada',
        api4com_account_id INT NOT NULL COMMENT 'Conta Api4Com usada',
        api4com_extension_id INT NULL COMMENT 'Ramal usado',
        api4com_call_id VARCHAR(255) NULL COMMENT 'ID da chamada na Api4Com',
        direction VARCHAR(20) NOT NULL COMMENT 'inbound, outbound',
        status VARCHAR(50) DEFAULT 'initiated' COMMENT 'initiated, ringing, answered, ended, failed, cancelled',
        duration INT DEFAULT 0 COMMENT 'Duração em segundos',
        started_at TIMESTAMP NULL COMMENT 'Quando a chamada começou',
        answered_at TIMESTAMP NULL COMMENT 'Quando a chamada foi atendida',
        ended_at TIMESTAMP NULL COMMENT 'Quando a chamada terminou',
        from_number VARCHAR(50) NOT NULL COMMENT 'Número de origem',
        to_number VARCHAR(50) NOT NULL COMMENT 'Número de destino',
        recording_url VARCHAR(500) NULL COMMENT 'URL da gravação (se disponível)',
        error_message TEXT NULL COMMENT 'Mensagem de erro (se falhou)',
        metadata JSON NULL COMMENT 'Metadados adicionais da chamada',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (api4com_account_id) REFERENCES api4com_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (api4com_extension_id) REFERENCES api4com_extensions(id) ON DELETE SET NULL,
        INDEX idx_conversation_id (conversation_id),
        INDEX idx_contact_id (contact_id),
        INDEX idx_agent_id (agent_id),
        INDEX idx_api4com_account_id (api4com_account_id),
        INDEX idx_status (status),
        INDEX idx_api4com_call_id (api4com_call_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'api4com_calls' criada com sucesso!\n";
}

function down_api4com_calls_table() {
    $sql = "DROP TABLE IF EXISTS api4com_calls";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'api4com_calls' removida!\n";
}

