-- ============================================
-- Criação de Tabelas para API REST
-- Execute este arquivo no seu banco de dados
-- ============================================

-- Tabela: api_tokens
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL COMMENT 'Usuário dono do token',
  `name` VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do token (ex: Integração CRM)',
  `token` VARCHAR(500) UNIQUE NOT NULL COMMENT 'Token gerado (hash)',
  `permissions` JSON COMMENT 'Permissões específicas do token (opcional, sobrescreve permissões do usuário)',
  `rate_limit` INT DEFAULT 100 COMMENT 'Limite de requisições por minuto',
  `allowed_ips` TEXT COMMENT 'IPs permitidos (separados por vírgula, vazio = todos)',
  `last_used_at` TIMESTAMP NULL COMMENT 'Última vez que o token foi usado',
  `last_used_ip` VARCHAR(45) NULL COMMENT 'Último IP que usou o token',
  `expires_at` TIMESTAMP NULL COMMENT 'Data de expiração (NULL = sem expiração)',
  `is_active` BOOLEAN DEFAULT true COMMENT 'Token ativo ou revogado',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_token` (`token`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_is_active` (`is_active`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela: api_logs
CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `token_id` INT NULL COMMENT 'Token usado (se autenticado)',
  `user_id` INT NULL COMMENT 'Usuário que fez a requisição',
  `endpoint` VARCHAR(500) NOT NULL COMMENT 'Endpoint chamado (ex: /api/v1/conversations)',
  `method` VARCHAR(10) NOT NULL COMMENT 'Método HTTP (GET, POST, etc)',
  `request_body` TEXT COMMENT 'Corpo da requisição (JSON)',
  `request_headers` TEXT COMMENT 'Headers da requisição (JSON)',
  `response_code` INT COMMENT 'Código HTTP da resposta (200, 404, etc)',
  `response_body` TEXT COMMENT 'Corpo da resposta (JSON)',
  `error_message` TEXT COMMENT 'Mensagem de erro (se houver)',
  `ip_address` VARCHAR(45) COMMENT 'IP de origem',
  `user_agent` TEXT COMMENT 'User Agent',
  `execution_time_ms` INT COMMENT 'Tempo de execução em milissegundos',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`token_id`) REFERENCES `api_tokens`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_token_id` (`token_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_endpoint` (`endpoint`(255)),
  INDEX `idx_method` (`method`),
  INDEX `idx_response_code` (`response_code`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verificar se foram criadas
-- ============================================
SELECT 'Tabelas criadas com sucesso!' AS status;
SELECT COUNT(*) AS api_tokens_count FROM api_tokens;
SELECT COUNT(*) AS api_logs_count FROM api_logs;
