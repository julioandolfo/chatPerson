<?php
/**
 * Migration: coluna queue_auto_paused em users.
 *
 * Marca quando a fila do agente foi desativada AUTOMATICAMENTE por ausência,
 * para que possa ser religada ao voltar — sem sobrescrever desativações manuais.
 */

function up_add_queue_auto_paused_to_users() {
    $db = \App\Helpers\Database::getInstance();
    try {
        $db->exec("ALTER TABLE users ADD COLUMN queue_auto_paused TINYINT(1) DEFAULT 0 COMMENT 'Fila desativada automaticamente por ausencia'");
        echo "✅ Coluna 'queue_auto_paused' adicionada em users.\n";
    } catch (\Throwable $e) {
        echo "⚠️  queue_auto_paused pode já existir: " . $e->getMessage() . "\n";
    }
}

function down_add_queue_auto_paused_to_users() {
    $db = \App\Helpers\Database::getInstance();
    try {
        $db->exec("ALTER TABLE users DROP COLUMN queue_auto_paused");
        echo "✅ Coluna 'queue_auto_paused' removida.\n";
    } catch (\Throwable $e) {
        echo "⚠️  " . $e->getMessage() . "\n";
    }
}
