<?php
/**
 * Migration: mensagem de ausência do agente
 *
 * - agent_absences: períodos de ausência de cada agente (início, fim, mensagem, atraso)
 * - agent_absence_replies: cada envio (ou envio pendente) da mensagem de ausência por conversa.
 *   UNIQUE (absence_id, conversation_id, round_no) garante uma mensagem por conversa e período
 *   (round_no > 0 só quando o agente configura reenviar depois de X horas).
 */

function up_create_agent_absences() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS agent_absences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NOT NULL,
        message TEXT NOT NULL,
        delay_seconds INT NOT NULL DEFAULT 10 COMMENT 'Espera após a mensagem do cliente',
        resend_after_hours INT NOT NULL DEFAULT 0 COMMENT '0 = uma vez por conversa no período',
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_period (user_id, enabled, starts_at, ends_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'agent_absences' criada!\n";

    $db->exec("CREATE TABLE IF NOT EXISTS agent_absence_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        absence_id INT NOT NULL,
        conversation_id INT NOT NULL,
        round_no INT NOT NULL DEFAULT 0,
        trigger_message_id INT NULL COMMENT 'Mensagem do cliente que disparou',
        status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | sending | sent | answered | cancelled | failed',
        run_at DATETIME NOT NULL,
        sent_message_id INT NULL,
        attempts INT NOT NULL DEFAULT 0,
        reason VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_absence_conversation_round (absence_id, conversation_id, round_no),
        INDEX idx_status_run (status, run_at),
        INDEX idx_conversation (conversation_id),
        INDEX idx_sent_message (sent_message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'agent_absence_replies' criada!\n";
}

function down_create_agent_absences() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS agent_absence_replies");
    $db->exec("DROP TABLE IF EXISTS agent_absences");
    echo "✅ Tabelas de ausência removidas\n";
}
