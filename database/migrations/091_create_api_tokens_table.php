<?php
/**
 * Migration: Criar tabela de tokens de API
 */

function up_create_api_tokens_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL COMMENT 'Usuário dono do token',
        name VARCHAR(255) NOT NULL COMMENT 'Nome descritivo do token (ex: Integração CRM)',
        token VARCHAR(500) UNIQUE NOT NULL COMMENT 'Token gerado (hash)',
        permissions JSON COMMENT 'Permissões específicas do token (opcional, sobrescreve permissões do usuário)',
        rate_limit INT DEFAULT 100 COMMENT 'Limite de requisições por minuto',
        allowed_ips TEXT COMMENT 'IPs permitidos (separados por vírgula, vazio = todos)',
        last_used_at TIMESTAMP NULL COMMENT 'Última vez que o token foi usado',
        last_used_ip VARCHAR(45) NULL COMMENT 'Último IP que usou o token',
        expires_at TIMESTAMP NULL COMMENT 'Data de expiração (NULL = sem expiração)',
        is_active BOOLEAN DEFAULT true COMMENT 'Token ativo ou revogado',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_user_id (user_id),
        INDEX idx_is_active (is_active),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'api_tokens' criada com sucesso!\n";
}

function down_create_api_tokens_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS api_tokens";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'api_tokens' removida com sucesso!\n";
}
