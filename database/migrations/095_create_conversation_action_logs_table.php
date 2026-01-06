<?php

function up_conversation_action_logs_table()
{
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS conversation_action_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        button_id INT NOT NULL,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        result VARCHAR(20) NOT NULL,
        steps_executed JSON NULL,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_action_logs_button FOREIGN KEY (button_id) REFERENCES conversation_action_buttons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_logs' criada com sucesso!\n";
}

function down_conversation_action_logs_table()
{
    global $pdo;
    $sql = "DROP TABLE IF EXISTS conversation_action_logs";
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_logs' removida com sucesso!\n";
}
