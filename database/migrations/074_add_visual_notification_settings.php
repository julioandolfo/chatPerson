<?php
/**
 * Migration: Adicionar configurações de notificações visuais
 */

function up_add_visual_notification_settings() {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Verificar se as colunas já existem
    $columns = $pdo->query("SHOW COLUMNS FROM user_sound_settings")->fetchAll(PDO::FETCH_COLUMN);
    
    $newColumns = [
        'visual_notifications_enabled' => "ALTER TABLE user_sound_settings ADD COLUMN visual_notifications_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar notificações visuais (toast)'",
        'browser_notifications_enabled' => "ALTER TABLE user_sound_settings ADD COLUMN browser_notifications_enabled TINYINT(1) DEFAULT 1 COMMENT 'Habilitar notificações do navegador'",
        'notification_position' => "ALTER TABLE user_sound_settings ADD COLUMN notification_position VARCHAR(20) DEFAULT 'bottom-right' COMMENT 'Posição: bottom-right, bottom-left, top-right, top-left'",
        'notification_duration' => "ALTER TABLE user_sound_settings ADD COLUMN notification_duration INT DEFAULT 8000 COMMENT 'Duração em milissegundos'",
        'show_notification_preview' => "ALTER TABLE user_sound_settings ADD COLUMN show_notification_preview TINYINT(1) DEFAULT 1 COMMENT 'Mostrar preview da mensagem'",
        'max_visible_notifications' => "ALTER TABLE user_sound_settings ADD COLUMN max_visible_notifications INT DEFAULT 5 COMMENT 'Máximo de notificações visíveis'"
    ];
    
    foreach ($newColumns as $column => $sql) {
        if (!in_array($column, $columns)) {
            $pdo->exec($sql);
            echo "✅ Coluna '{$column}' adicionada com sucesso!\n";
        } else {
            echo "⚠️ Coluna '{$column}' já existe.\n";
        }
    }
    
    echo "✅ Migration 074 executada com sucesso!\n";
}

// Executar migration se chamado diretamente
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    require_once __DIR__ . '/../../app/Helpers/autoload.php';
    up_add_visual_notification_settings();
}

