<?php
/**
 * Migration: Tabelas do canal de Email (ingestão por regras)
 *
 * - email_ingestion_rules: regras de validação por conta de email (assunto/corpo contém X, etc.)
 * - email_ingestion_log:  auditoria + idempotência da ingestão (dedup por Message-ID)
 *
 * A conta de email em si é armazenada em `integration_accounts`
 * (provider = 'imap', channel = 'email'), reaproveitando todo o fluxo
 * de conversas/mensagens/automações dos demais canais.
 */

function up_create_email_ingestion_tables()
{
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS email_ingestion_rules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        integration_account_id INT NOT NULL COMMENT 'FK integration_accounts (conta de email)',
        name VARCHAR(255) NOT NULL,
        priority INT NOT NULL DEFAULT 0 COMMENT 'Menor = avaliada primeiro',
        match_type VARCHAR(10) NOT NULL DEFAULT 'any' COMMENT 'any (OU) | all (E)',
        conditions JSON NULL COMMENT 'Lista de condicoes [{field,op,value}]',
        actions JSON NULL COMMENT 'Acoes {ingest,funnel_id,stage_id,department_id,agent_id,priority,tag}',
        stop_on_match TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Para na primeira regra que casar',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_account (integration_account_id),
        INDEX idx_active (is_active),
        INDEX idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'email_ingestion_rules' criada\n";

    $db->exec("CREATE TABLE IF NOT EXISTS email_ingestion_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        integration_account_id INT NOT NULL,
        email_message_id VARCHAR(255) NULL COMMENT 'Header Message-ID (dedup)',
        email_uid INT NULL COMMENT 'UID IMAP',
        from_email VARCHAR(320) NULL,
        subject VARCHAR(998) NULL,
        decision VARCHAR(20) NOT NULL COMMENT 'ingested|ignored|duplicate|error',
        matched_rule_id INT NULL,
        conversation_id INT NULL,
        message_id INT NULL,
        reason VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_account_msgid (integration_account_id, email_message_id),
        INDEX idx_account (integration_account_id),
        INDEX idx_decision (decision),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'email_ingestion_log' criada\n";
}

function down_create_email_ingestion_tables()
{
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS email_ingestion_rules");
    $db->exec("DROP TABLE IF EXISTS email_ingestion_log");
    echo "✅ Tabelas de ingestão de email removidas\n";
}
