-- =====================================================
-- Script SQL: Criar Tabela system_alerts
-- Propósito: Sistema de alertas para problemas críticos
-- Data: 2026-01-21
-- =====================================================

-- Criar tabela system_alerts
CREATE TABLE IF NOT EXISTS system_alerts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Verificação
-- =====================================================
SELECT 'Tabela system_alerts criada com sucesso!' as message;

-- Ver estrutura da tabela
DESCRIBE system_alerts;

-- =====================================================
-- Queries Úteis
-- =====================================================

-- Ver todos os alertas
-- SELECT * FROM system_alerts ORDER BY created_at DESC;

-- Ver apenas alertas ativos
-- SELECT * FROM system_alerts WHERE is_resolved = FALSE;

-- Ver alertas críticos não resolvidos
-- SELECT * FROM system_alerts WHERE severity = 'critical' AND is_resolved = FALSE;

-- Marcar alerta como lido
-- UPDATE system_alerts SET is_read = TRUE, read_by = 1, read_at = NOW() WHERE id = ?;

-- Marcar alerta como resolvido
-- UPDATE system_alerts SET is_resolved = TRUE, resolved_by = 1, resolved_at = NOW() WHERE id = ?;

-- Deletar alertas antigos (mais de 30 dias e resolvidos)
-- DELETE FROM system_alerts WHERE is_resolved = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- =====================================================
-- Inserir alerta de teste (opcional)
-- =====================================================
/*
INSERT INTO system_alerts (
    type,
    severity,
    title,
    message,
    action_url,
    created_at
) VALUES (
    'test_alert',
    'info',
    'Alerta de Teste',
    'Este é um alerta de teste do sistema. Você pode marcá-lo como lido ou resolvido.',
    'https://exemplo.com',
    NOW()
);
*/

-- =====================================================
-- Rollback (caso necessite remover a tabela)
-- =====================================================
-- DROP TABLE IF EXISTS system_alerts;
