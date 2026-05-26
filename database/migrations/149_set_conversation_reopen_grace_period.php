<?php

function up_set_conversation_reopen_grace_period() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $stmt = $db->prepare(
        "INSERT INTO settings (`key`, `value`, `type`, `group`, `description`)
         VALUES ('conversation_reopen_grace_period_minutes', '240', 'integer', 'general',
                 'Janela (minutos) em que uma conversa fechada reabre mantendo agente/funil ao receber nova mensagem do contato. Depois disso, abre nova conversa.')
         ON DUPLICATE KEY UPDATE `value` = '240', updated_at = NOW()"
    );
    $stmt->execute();

    echo "✅ conversation_reopen_grace_period_minutes definido como 240 minutos (4h).\n";
}

function down_set_conversation_reopen_grace_period() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $stmt = $db->prepare(
        "UPDATE settings SET `value` = '10', updated_at = NOW()
         WHERE `key` = 'conversation_reopen_grace_period_minutes'"
    );
    $stmt->execute();

    echo "✅ conversation_reopen_grace_period_minutes revertido para 10 minutos.\n";
}
