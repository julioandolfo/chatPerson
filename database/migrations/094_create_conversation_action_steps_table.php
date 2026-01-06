<?php

function up_conversation_action_steps_table()
{
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS conversation_action_steps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        button_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        payload JSON NULL,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_action_steps_button FOREIGN KEY (button_id) REFERENCES conversation_action_buttons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_steps' criada com sucesso!\n";
}

function down_conversation_action_steps_table()
{
    global $pdo;
    $sql = "DROP TABLE IF EXISTS conversation_action_steps";
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'conversation_action_steps' removida com sucesso!\n";
}
