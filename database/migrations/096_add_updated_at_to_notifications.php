<?php
/**
 * Migration: Adicionar coluna updated_at à tabela notifications
 */

function up_add_updated_at_to_notifications()
{
    global $pdo;

    $sql = "ALTER TABLE notifications 
            ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }

    echo "✅ Coluna 'updated_at' adicionada à tabela 'notifications'\n";
}

function down_add_updated_at_to_notifications()
{
    $sql = "ALTER TABLE notifications DROP COLUMN updated_at";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Coluna 'updated_at' removida da tabela 'notifications'\n";
}

