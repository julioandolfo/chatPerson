<?php
/**
 * Migration: Criar tabela system_alerts
 * Sistema de alertas para notificar administradores sobre problemas críticos
 */

function up_system_alerts_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS system_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(100) NOT NULL COMMENT 'Tipo do alerta (openai_quota_exceeded, etc)',
        severity ENUM('info', 'warning', 'critical') DEFAULT 'info' COMMENT 'Nível de severidade',
        title VARCHAR(255) NOT NULL COMMENT 'Título do alerta',
        message TEXT NOT NULL COMMENT 'Mensagem detalhada do alerta',
        action_url VARCHAR(500) NULL COMMENT 'URL para ação relacionada ao alerta',
        is_read BOOLEAN DEFAULT FALSE COMMENT 'Se o alerta foi lido',
        is_resolved BOOLEAN DEFAULT FALSE COMMENT 'Se o problema foi resolvido',
        read_by INT NULL COMMENT 'ID do usuário que leu o alerta',
        read_at TIMESTAMP NULL COMMENT 'Data/hora da leitura',
        resolved_by INT NULL COMMENT 'ID do usuário que resolveu',
        resolved_at TIMESTAMP NULL COMMENT 'Data/hora da resolução',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criação',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora de atualização',
        
        INDEX idx_alerts_type (type),
        INDEX idx_alerts_severity (severity),
        INDEX idx_alerts_is_read (is_read),
        INDEX idx_alerts_is_resolved (is_resolved),
        INDEX idx_alerts_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'system_alerts' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'system_alerts' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'system_alerts' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'system_alerts' pode já existir\n";
        }
    }
}

function down_system_alerts_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS system_alerts";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'system_alerts' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'system_alerts': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'system_alerts' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'system_alerts'\n";
        }
    }
}
