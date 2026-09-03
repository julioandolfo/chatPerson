<?php
/**
 * Migration: Criar tabela whatsapp_webhook_audit
 *
 * Registra TODO webhook de WhatsApp recebido (Quepasa/Evolution/native) ANTES
 * de qualquer validação, e o desfecho do processamento.
 *
 * Motivo: hoje o processWebhook tem ~15 pontos de `return` silencioso. Quando
 * uma mensagem "não chega ao CRM" não existe nenhum registro persistente do que
 * aconteceu — só linhas em logs/quepasa.log (sem rotação, GBs, difícil de ler).
 * Com esta tabela é possível responder em 1 query: "o webhook do número X
 * chegou? foi descartado? por qual regra? deu erro fatal?"
 */

function up_create_whatsapp_webhook_audit_table() {
    // Aceita os dois runners: database/run_migrations.php (define $pdo global e
    // NÃO carrega o autoloader) e os scripts de public/ (que sobem o bootstrap).
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $tables = $db->query("SHOW TABLES LIKE 'whatsapp_webhook_audit'")->fetchAll();
    if (!empty($tables)) {
        echo "⏭️ Tabela 'whatsapp_webhook_audit' já existe.\n";
        return;
    }

    $db->exec("
        CREATE TABLE whatsapp_webhook_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            request_id VARCHAR(16) NOT NULL COMMENT 'ID único da request para correlacionar com os logs',
            provider VARCHAR(30) NULL COMMENT 'quepasa, evolution, native',
            entrypoint VARCHAR(60) NULL COMMENT 'arquivo/rota que recebeu o webhook',
            account_id INT NULL COMMENT 'integration_accounts.id resolvido (se resolvido)',
            external_id VARCHAR(255) NULL COMMENT 'ID da mensagem no provider',
            from_raw VARCHAR(255) NULL COMMENT 'campo from/chat.id bruto (pode ser @lid)',
            phone VARCHAR(32) NULL COMMENT 'telefone normalizado quando identificado',
            message_type VARCHAR(50) NULL,
            direction ENUM('incoming', 'outgoing', 'unknown') DEFAULT 'unknown',
            status ENUM('received', 'processed', 'dropped', 'error', 'fatal') DEFAULT 'received',
            drop_reason VARCHAR(120) NULL COMMENT 'regra que descartou o webhook',
            detail TEXT NULL COMMENT 'contexto do desfecho (mensagem de erro, ids, etc)',
            conversation_id INT NULL,
            message_id INT NULL,
            duration_ms INT NULL,
            payload MEDIUMTEXT NULL COMMENT 'payload bruto recebido (truncado)',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_from_raw (from_raw),
            INDEX idx_external_id (external_id),
            INDEX idx_status (status, created_at),
            INDEX idx_created (created_at),
            INDEX idx_request (request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo "✅ Tabela 'whatsapp_webhook_audit' criada!\n";
}

function down_create_whatsapp_webhook_audit_table() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    $db->exec("DROP TABLE IF EXISTS whatsapp_webhook_audit");
    echo "✅ Tabela 'whatsapp_webhook_audit' removida!\n";
}
