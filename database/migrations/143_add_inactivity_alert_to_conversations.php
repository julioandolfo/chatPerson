<?php

function up_add_inactivity_alert_to_conversations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("ALTER TABLE conversations ADD COLUMN inactivity_alert_at TIMESTAMP NULL DEFAULT NULL AFTER last_reassignment_at");

    $db->exec("CREATE INDEX idx_conversations_inactivity_alert ON conversations (inactivity_alert_at)");

    echo "Coluna inactivity_alert_at adicionada a conversations.\n";
}

function down_add_inactivity_alert_to_conversations() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP INDEX idx_conversations_inactivity_alert ON conversations");
    $db->exec("ALTER TABLE conversations DROP COLUMN inactivity_alert_at");

    echo "Coluna inactivity_alert_at removida de conversations.\n";
}
