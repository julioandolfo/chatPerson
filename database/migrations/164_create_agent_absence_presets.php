<?php
/**
 * Migration: ausências rápidas ("Sair para almoço")
 *
 * - agent_absence_presets: modelos de ausência com duração fixa (Almoço 1 h, Pausa 15 min...).
 *   user_id NULL = disponível para todos os agentes (criado por admin); senão, só do agente.
 * - agent_absences ganha label/icon/preset_id (para mostrar "🍽️ Almoço") e set_away
 *   (a ausência colocou o agente como Ausente; ao voltar, volta para Online).
 * - Cria "Almoço" (1 h) e "Pausa" (15 min) para todos, se ainda não houver modelos.
 */

function up_create_agent_absence_presets() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $hasBase = $db->query("SHOW TABLES LIKE 'agent_absences'")->fetch(\PDO::FETCH_ASSOC);
    if (!$hasBase) {
        throw new \RuntimeException("Tabela 'agent_absences' não existe: execute antes a migration 163 (php database/run_migrations.php 163)");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS agent_absence_presets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL COMMENT 'NULL = para todos os agentes',
        name VARCHAR(60) NOT NULL,
        icon VARCHAR(16) NULL,
        duration_minutes INT NOT NULL DEFAULT 60,
        message TEXT NOT NULL,
        delay_seconds INT NOT NULL DEFAULT 10,
        set_away TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Muda o status para Ausente enquanto durar',
        sort_order INT NOT NULL DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ Tabela 'agent_absence_presets' criada!\n";

    $columns = [
        'label' => "ADD COLUMN label VARCHAR(60) NULL AFTER user_id",
        'icon' => "ADD COLUMN icon VARCHAR(16) NULL AFTER label",
        'preset_id' => "ADD COLUMN preset_id INT NULL AFTER icon",
        'set_away' => "ADD COLUMN set_away TINYINT(1) NOT NULL DEFAULT 0 AFTER enabled",
    ];
    foreach ($columns as $column => $ddl) {
        $exists = $db->query("SHOW COLUMNS FROM agent_absences LIKE '{$column}'")->fetch(\PDO::FETCH_ASSOC);
        if (!$exists) {
            $db->exec("ALTER TABLE agent_absences {$ddl}");
            echo "✅ Coluna '{$column}' adicionada em agent_absences\n";
        }
    }

    $count = (int)$db->query("SELECT COUNT(*) FROM agent_absence_presets")->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare("INSERT INTO agent_absence_presets (user_id, name, icon, duration_minutes, message, delay_seconds, set_away, sort_order)
                              VALUES (NULL, ?, ?, ?, ?, 10, 1, ?)");
        $stmt->execute(['Almoço', '🍽️', 60, "Olá, {{contact.name}}! Saí para o almoço e retorno {{absence.return}}. Assim que voltar, respondo sua mensagem. 😊", 1]);
        $stmt->execute(['Pausa', '☕', 15, "Olá, {{contact.name}}! Estou em uma pausa rápida e retorno {{absence.return}}. Já já te respondo. 😊", 2]);
        echo "✅ Ausências rápidas padrão criadas (Almoço, Pausa)\n";
    }
}

function down_create_agent_absence_presets() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();

    $db->exec("DROP TABLE IF EXISTS agent_absence_presets");
    foreach (['label', 'icon', 'preset_id', 'set_away'] as $column) {
        try {
            $db->exec("ALTER TABLE agent_absences DROP COLUMN {$column}");
        } catch (\PDOException $e) {
            // pode não existir
        }
    }
    echo "✅ Ausências rápidas removidas\n";
}
