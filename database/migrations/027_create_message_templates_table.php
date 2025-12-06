<?php
/**
 * Migration: Criar tabela message_templates
 */

function up_message_templates_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS message_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NULL COMMENT 'Categoria: welcome, followup, support, etc',
        content TEXT NOT NULL COMMENT 'Conteúdo do template com variáveis {{variavel}}',
        description TEXT NULL,
        department_id INT NULL COMMENT 'Template específico de setor (NULL = global)',
        channel VARCHAR(50) NULL COMMENT 'Canal específico: whatsapp, email, chat (NULL = todos)',
        is_active BOOLEAN DEFAULT TRUE,
        usage_count INT DEFAULT 0 COMMENT 'Contador de uso',
        created_by INT NULL COMMENT 'ID do usuário que criou',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_category (category),
        INDEX idx_department_id (department_id),
        INDEX idx_channel (channel),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    echo "✅ Tabela 'message_templates' criada com sucesso!\n";
}

function down_message_templates_table() {
    $sql = "DROP TABLE IF EXISTS message_templates";
    \App\Helpers\Database::getInstance()->exec($sql);
    echo "✅ Tabela 'message_templates' removida!\n";
}

