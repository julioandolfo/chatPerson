<?php
/**
 * Migration: Criar tabela de configurações de som por usuário
 */

function up_create_user_sound_settings_table() {
    global $pdo;
    
    if (!isset($pdo)) {
        $pdo = \App\Helpers\Database::getInstance();
    }
    
    // Verificar se a tabela já existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'user_sound_settings'")->rowCount() > 0;
    
    if (!$tableExists) {
        $sql = "CREATE TABLE user_sound_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL COMMENT 'ID do usuário',
            
            -- Configurações gerais
            sounds_enabled TINYINT(1) DEFAULT 1 COMMENT 'Sons habilitados globalmente',
            volume INT DEFAULT 70 COMMENT 'Volume geral (0-100)',
            
            -- Sons específicos por evento
            new_conversation_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para nova conversa',
            new_conversation_sound VARCHAR(255) DEFAULT 'new-conversation.mp3' COMMENT 'Arquivo de som',
            
            new_message_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para nova mensagem',
            new_message_sound VARCHAR(255) DEFAULT 'new-message.mp3' COMMENT 'Arquivo de som',
            
            conversation_assigned_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para conversa atribuída',
            conversation_assigned_sound VARCHAR(255) DEFAULT 'assigned.mp3' COMMENT 'Arquivo de som',
            
            invite_received_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para convite recebido',
            invite_received_sound VARCHAR(255) DEFAULT 'invite.mp3' COMMENT 'Arquivo de som',
            
            sla_warning_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para aviso de SLA',
            sla_warning_sound VARCHAR(255) DEFAULT 'sla-warning.mp3' COMMENT 'Arquivo de som',
            
            sla_breached_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para SLA estourado',
            sla_breached_sound VARCHAR(255) DEFAULT 'sla-breached.mp3' COMMENT 'Arquivo de som',
            
            mention_received_enabled TINYINT(1) DEFAULT 1 COMMENT 'Som para menção recebida',
            mention_received_sound VARCHAR(255) DEFAULT 'mention.mp3' COMMENT 'Arquivo de som',
            
            -- Configurações avançadas
            quiet_hours_enabled TINYINT(1) DEFAULT 0 COMMENT 'Horário silencioso habilitado',
            quiet_hours_start TIME DEFAULT '22:00:00' COMMENT 'Início do horário silencioso',
            quiet_hours_end TIME DEFAULT '08:00:00' COMMENT 'Fim do horário silencioso',
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabela 'user_sound_settings' criada com sucesso!\n";
    } else {
        echo "⚠️ Tabela 'user_sound_settings' já existe.\n";
    }
    
    // Criar tabela para sons personalizados (uploads)
    $customSoundsTableExists = $pdo->query("SHOW TABLES LIKE 'custom_sounds'")->rowCount() > 0;
    
    if (!$customSoundsTableExists) {
        $sql = "CREATE TABLE custom_sounds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL COMMENT 'NULL = som do sistema, ID = som do usuário',
            name VARCHAR(100) NOT NULL COMMENT 'Nome do som',
            filename VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo',
            file_path VARCHAR(500) NOT NULL COMMENT 'Caminho completo',
            file_size INT DEFAULT 0 COMMENT 'Tamanho em bytes',
            duration DECIMAL(5,2) DEFAULT 0 COMMENT 'Duração em segundos',
            is_system TINYINT(1) DEFAULT 0 COMMENT 'Se é som padrão do sistema',
            category ENUM('notification', 'alert', 'success', 'error', 'custom') DEFAULT 'custom',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_user (user_id),
            INDEX idx_system (is_system),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "✅ Tabela 'custom_sounds' criada com sucesso!\n";
    } else {
        echo "⚠️ Tabela 'custom_sounds' já existe.\n";
    }
    
    // Adicionar configurações padrão do sistema na tabela settings
    try {
        $defaultSoundSettings = json_encode([
            'sounds_enabled' => true,
            'default_volume' => 70,
            'sounds' => [
                'new_conversation' => [
                    'enabled' => true,
                    'sound' => 'new-conversation.mp3',
                    'label' => 'Nova Conversa'
                ],
                'new_message' => [
                    'enabled' => true,
                    'sound' => 'new-message.mp3',
                    'label' => 'Nova Mensagem'
                ],
                'conversation_assigned' => [
                    'enabled' => true,
                    'sound' => 'assigned.mp3',
                    'label' => 'Conversa Atribuída'
                ],
                'invite_received' => [
                    'enabled' => true,
                    'sound' => 'invite.mp3',
                    'label' => 'Convite para Conversa'
                ],
                'sla_warning' => [
                    'enabled' => true,
                    'sound' => 'sla-warning.mp3',
                    'label' => 'Aviso de SLA'
                ],
                'sla_breached' => [
                    'enabled' => true,
                    'sound' => 'sla-breached.mp3',
                    'label' => 'SLA Estourado'
                ],
                'mention_received' => [
                    'enabled' => true,
                    'sound' => 'mention.mp3',
                    'label' => 'Menção Recebida'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`, `category`, `description`) 
                               VALUES (?, ?, 'json', 'notifications', 'Configurações padrão de sons do sistema')
                               ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute(['system_sound_settings', $defaultSoundSettings]);
        echo "✅ Configurações padrão de sons inseridas!\n";
    } catch (\Exception $e) {
        echo "⚠️ Aviso ao inserir configurações: " . $e->getMessage() . "\n";
    }
}

function down_create_user_sound_settings_table() {
    global $pdo;
    
    if (!isset($pdo)) {
        $pdo = \App\Helpers\Database::getInstance();
    }
    
    $pdo->exec("DROP TABLE IF EXISTS user_sound_settings");
    $pdo->exec("DROP TABLE IF EXISTS custom_sounds");
    $pdo->exec("DELETE FROM settings WHERE `key` = 'system_sound_settings'");
    
    echo "✅ Tabelas de sons removidas!\n";
}

// Executar se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require_once __DIR__ . '/../../config/database.php';
    up_create_user_sound_settings_table();
}

