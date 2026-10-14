<?php
/**
 * Migration: tabelas do PWA (app instalável)
 *
 * - user_remember_tokens: "Manter conectado" — um token por aparelho, guardado só como
 *   hash (selector + validator), rotacionado a cada uso e revogável pelo usuário.
 * - push_subscriptions: inscrições de Web Push (notificação com o app fechado), uma por
 *   aparelho/navegador.
 */

function up_create_pwa_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("CREATE TABLE IF NOT EXISTS user_remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        selector CHAR(24) NOT NULL,
        validator_hash CHAR(64) NOT NULL,
        previous_validator_hash CHAR(64) NULL COMMENT 'Aceito por alguns segundos após a rotação (requisições paralelas)',
        rotated_at DATETIME NULL,
        user_agent VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        expires_at DATETIME NOT NULL,
        last_used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_selector (selector),
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'user_remember_tokens' criada!\n";

    $db->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint VARCHAR(1000) NOT NULL,
        endpoint_hash CHAR(64) NOT NULL,
        p256dh VARCHAR(255) NOT NULL,
        auth_secret VARCHAR(255) NOT NULL,
        show_preview TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Mostrar o texto da mensagem na notificação',
        user_agent VARCHAR(255) NULL,
        failures INT NOT NULL DEFAULT 0,
        last_success_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_endpoint (endpoint_hash),
        INDEX idx_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'push_subscriptions' criada!\n";
}

function down_create_pwa_tables() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS push_subscriptions");
    $db->exec("DROP TABLE IF EXISTS user_remember_tokens");

    echo "✅ Tabelas do PWA removidas!\n";
}
