<?php
/**
 * Migration: Follow-up automático por etapa do funil
 *
 * - funnel_stage_followups: configuração da sequência de mensagens de cada etapa
 *   (ex.: após 2h "Vamos continuar?", após 6h "Ainda está por aí?").
 * - conversation_followups: estado da sequência em cada conversa (uma linha por
 *   conversa). É "armada" toda vez que sai uma mensagem nossa e para quando o
 *   cliente responde, a conversa fecha/muda de etapa ou o agente cancela no chat.
 * - conversation_followup_messages: histórico de cada mensagem de follow-up
 *   disparada (ou que falhou), para auditoria e para exibir no chat.
 */

function up_create_stage_followup_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS funnel_stage_followups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funnel_stage_id INT NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        only_business_hours TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Se a hora cair fora do expediente, adia para o próximo horário útil',
        only_human_messages TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Armar apenas após mensagens de agentes humanos (ignora IA/automações)',
        steps JSON NULL COMMENT '[{delay_value, delay_unit, message}] — delay contado a partir da última mensagem enviada',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_stage (funnel_stage_id),
        FOREIGN KEY (funnel_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'funnel_stage_followups' criada!\n";

    $db->exec("CREATE TABLE IF NOT EXISTS conversation_followups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        funnel_stage_id INT NULL COMMENT 'Etapa cuja sequência está em uso',
        anchor_message_id INT NULL COMMENT 'Mensagem nossa que armou a sequência',
        anchor_at DATETIME NOT NULL COMMENT 'Base de contagem dos delays',
        next_step INT NOT NULL DEFAULT 0 COMMENT 'Índice (0-based) do próximo passo a enviar',
        next_run_at DATETIME NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active|cancelled|replied|completed|stopped',
        stop_reason VARCHAR(255) NULL,
        attempts INT NOT NULL DEFAULT 0 COMMENT 'Falhas consecutivas no passo atual',
        cancelled_by INT NULL,
        cancelled_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_conversation (conversation_id),
        INDEX idx_status_next_run (status, next_run_at),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (funnel_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
        FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'conversation_followups' criada!\n";

    $db->exec("CREATE TABLE IF NOT EXISTS conversation_followup_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_followup_id INT NOT NULL,
        conversation_id INT NOT NULL,
        funnel_stage_id INT NULL,
        anchor_message_id INT NULL,
        step_index INT NOT NULL,
        message_id INT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'sent' COMMENT 'sent|failed',
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conversation (conversation_id, created_at),
        INDEX idx_followup (conversation_followup_id),
        FOREIGN KEY (conversation_followup_id) REFERENCES conversation_followups(id) ON DELETE CASCADE,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'conversation_followup_messages' criada!\n";
}

function down_create_stage_followup_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS conversation_followup_messages");
    $db->exec("DROP TABLE IF EXISTS conversation_followups");
    $db->exec("DROP TABLE IF EXISTS funnel_stage_followups");

    echo "✅ Tabelas de follow-up por etapa removidas!\n";
}
