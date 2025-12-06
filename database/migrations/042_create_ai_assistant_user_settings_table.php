<?php
/**
 * Migration: Criar tabela ai_assistant_user_settings
 * Configurações personalizadas do usuário para cada funcionalidade
 */

function up_ai_assistant_user_settings_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_assistant_user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'ID do usuário',
        feature_key VARCHAR(100) NOT NULL COMMENT 'Chave da funcionalidade',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Funcionalidade habilitada para este usuário',
        ai_agent_id INT NULL COMMENT 'Agente preferido do usuário para esta funcionalidade',
        custom_settings JSON COMMENT 'Configurações personalizadas do usuário',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        UNIQUE KEY unique_user_feature (user_id, feature_key),
        INDEX idx_user_id (user_id),
        INDEX idx_feature_key (feature_key),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_assistant_user_settings' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_assistant_user_settings' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_assistant_user_settings' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_assistant_user_settings' pode já existir\n";
        }
    }
}

function down_ai_assistant_user_settings_table() {
    $sql = "DROP TABLE IF EXISTS ai_assistant_user_settings";
    try {
        \App\Helpers\Database::getInstance()->exec($sql);
        echo "✅ Tabela 'ai_assistant_user_settings' removida!\n";
    } catch (\Exception $e) {
        echo "⚠️  Erro ao remover tabela 'ai_assistant_user_settings': " . $e->getMessage() . "\n";
    }
}

