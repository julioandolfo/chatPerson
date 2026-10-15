<?php
/**
 * Migration: ritmo de envio do follow-up por etapa (anti-spam)
 *
 * - conversation_followup_messages.account_key: por qual número/conta a mensagem saiu,
 *   para contar os envios por hora/dia de cada número.
 * - followup_send_pacing: quando cada número pode enviar a próxima mensagem de follow-up
 *   (intervalo aleatório entre envios).
 */

function up_add_followup_send_pacing() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $hasColumn = $db->query("SHOW COLUMNS FROM conversation_followup_messages LIKE 'account_key'")->fetch(\PDO::FETCH_ASSOC);
    if (!$hasColumn) {
        $db->exec("ALTER TABLE conversation_followup_messages
            ADD COLUMN account_key VARCHAR(40) NULL COMMENT 'Número/conta de envio (i:<integration_account_id> | w:<whatsapp_account_id>)' AFTER conversation_id,
            ADD INDEX idx_account_created (account_key, created_at)");
        echo "✅ Coluna 'account_key' adicionada em conversation_followup_messages\n";
    } else {
        echo "ℹ️  Coluna 'account_key' já existe\n";
    }

    $db->exec("CREATE TABLE IF NOT EXISTS followup_send_pacing (
        account_key VARCHAR(40) NOT NULL PRIMARY KEY,
        next_allowed_at DATETIME NOT NULL,
        version INT NOT NULL DEFAULT 0 COMMENT 'Reserva atômica da vez de envio',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $hasVersion = $db->query("SHOW COLUMNS FROM followup_send_pacing LIKE 'version'")->fetch(\PDO::FETCH_ASSOC);
    if (!$hasVersion) {
        $db->exec("ALTER TABLE followup_send_pacing ADD COLUMN version INT NOT NULL DEFAULT 0 AFTER next_allowed_at");
    }
    echo "✅ Tabela 'followup_send_pacing' criada!\n";
}

function down_add_followup_send_pacing() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS followup_send_pacing");
    try {
        $db->exec("ALTER TABLE conversation_followup_messages DROP INDEX idx_account_created, DROP COLUMN account_key");
    } catch (\PDOException $e) {
        // pode não existir
    }
    echo "✅ Ritmo de envio do follow-up removido\n";
}
