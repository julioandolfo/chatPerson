<?php

function up_conversation_action_buttons_table()
{
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS conversation_action_buttons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        color VARCHAR(30) DEFAULT '#009ef7',
        icon VARCHAR(50) DEFAULT 'ki-bolt',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        visibility JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_buttons' criada com sucesso!\n";
}

function down_conversation_action_buttons_table()
{
    global $pdo;
    $sql = "DROP TABLE IF EXISTS conversation_action_buttons";
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_buttons' removida com sucesso!\n";
}
