<?php
/**
 * Migration: Criar tabela media_rate_log
 * Log de envio de mídia (Evolution/Quepasa) para rate limiting
 */

function up_create_media_rate_log_table() {
    $db = \App\Helpers\Database::getInstance();

    $tables = $db->query("SHOW TABLES LIKE 'media_rate_log'")->fetchAll();
    if (empty($tables)) {
        $db->exec("
            CREATE TABLE media_rate_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                conversation_id INT NULL,
                user_id INT NULL,
                message_id INT NULL,
                media_type VARCHAR(20) NOT NULL,
                provider VARCHAR(20) NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account_sent (account_id, sent_at),
                INDEX idx_conversation_sent (conversation_id, sent_at),
                INDEX idx_user_sent (user_id, sent_at),
                INDEX idx_sent_at (sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela 'media_rate_log' criada com sucesso!\n";
    } else {
        echo "⏭️ Tabela 'media_rate_log' já existe.\n";
    }

    // Tabela de pausas automáticas (auto-throttle por 502)
    $tables = $db->query("SHOW TABLES LIKE 'media_rate_pauses'")->fetchAll();
    if (empty($tables)) {
        $db->exec("
            CREATE TABLE media_rate_pauses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                paused_until DATETIME NOT NULL,
                reason VARCHAR(255) NULL,
                consecutive_failures INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_account (account_id),
                INDEX idx_paused_until (paused_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Tabela 'media_rate_pauses' criada com sucesso!\n";
    } else {
        echo "⏭️ Tabela 'media_rate_pauses' já existe.\n";
    }

    // Defaults nas settings
    $defaults = [
        ['rate_limit_media_account_per_minute', '15', 'Mídias por minuto, por número WhatsApp (Evolution/Quepasa)'],
        ['rate_limit_media_account_per_hour', '200', 'Mídias por hora, por número WhatsApp (Evolution/Quepasa)'],
        ['rate_limit_media_conversation_per_minute', '5', 'Mídias por minuto, por conversa'],
        ['rate_limit_media_conversation_per_hour', '30', 'Mídias por hora, por conversa'],
        ['rate_limit_media_user_per_minute', '10', 'Mídias por minuto, por agente (usuário)'],
        ['rate_limit_media_auto_pause_enabled', '1', 'Pausar envio automaticamente após N falhas 502 consecutivas (1=ativo, 0=inativo)'],
        ['rate_limit_media_auto_pause_threshold', '3', 'Quantos 502 consecutivos para acionar a pausa automática'],
        ['rate_limit_media_auto_pause_minutes', '10', 'Duração da pausa automática em minutos'],
    ];
    foreach ($defaults as [$key, $value, $description]) {
        $exists = $db->prepare("SELECT 1 FROM settings WHERE `key` = ? LIMIT 1");
        $exists->execute([$key]);
        if (!$exists->fetch()) {
            $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `type`, `group`, `description`) VALUES (?, ?, 'integer', 'rate_limit', ?)");
            $stmt->execute([$key, $value, $description]);
        }
    }
    echo "✅ Settings padrão de rate limit criados!\n";
}

function down_create_media_rate_log_table() {
    $db = \App\Helpers\Database::getInstance();
    $db->exec("DROP TABLE IF EXISTS media_rate_log");
    $db->exec("DROP TABLE IF EXISTS media_rate_pauses");
    echo "✅ Tabelas de rate limit removidas!\n";
}
