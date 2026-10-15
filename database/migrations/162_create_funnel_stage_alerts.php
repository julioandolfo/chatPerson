<?php
/**
 * Migration: alerta na tela (com som) para conversas paradas numa etapa do funil
 *
 * funnel_stage_alerts: configuração por etapa — depois de X minutos com o cliente esperando
 * resposta, avisa o agente da conversa (e, se configurado, quem mais deve ser avisado).
 */

function up_create_funnel_stage_alerts() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS funnel_stage_alerts (
        funnel_stage_id INT NOT NULL PRIMARY KEY,
        enabled TINYINT(1) NOT NULL DEFAULT 0,
        after_minutes INT NOT NULL DEFAULT 30 COMMENT 'Minutos com o cliente sem resposta',
        sound VARCHAR(60) NULL COMMENT 'Arquivo em public/assets/sounds (NULL = sem som)',
        repeat_minutes INT NOT NULL DEFAULT 0 COMMENT 'Repetir o som a cada N minutos (0 = uma vez)',
        include_unassigned TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Conversas sem agente: avisa quem pode vê-las',
        notify_user_ids TEXT NULL COMMENT 'JSON: usuários avisados de todas as conversas da etapa (supervisores)',
        only_business_hours TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Conta só o horário de atendimento e não alerta fora dele',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_enabled (enabled)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'funnel_stage_alerts' criada!\n";
}

function down_create_funnel_stage_alerts() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS funnel_stage_alerts");
    echo "✅ Tabela 'funnel_stage_alerts' removida\n";
}
