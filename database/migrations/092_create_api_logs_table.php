<?php
/**
 * Migration: Criar tabela de logs de API
 */

function up_create_api_logs_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS api_logs (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        token_id INT NULL COMMENT 'Token usado (se autenticado)',
        user_id INT NULL COMMENT 'Usuário que fez a requisição',
        endpoint VARCHAR(500) NOT NULL COMMENT 'Endpoint chamado (ex: /api/v1/conversations)',
        method VARCHAR(10) NOT NULL COMMENT 'Método HTTP (GET, POST, etc)',
        request_body TEXT COMMENT 'Corpo da requisição (JSON)',
        request_headers TEXT COMMENT 'Headers da requisição (JSON)',
        response_code INT COMMENT 'Código HTTP da resposta (200, 404, etc)',
        response_body TEXT COMMENT 'Corpo da resposta (JSON)',
        error_message TEXT COMMENT 'Mensagem de erro (se houver)',
        ip_address VARCHAR(45) COMMENT 'IP de origem',
        user_agent TEXT COMMENT 'User Agent',
        execution_time_ms INT COMMENT 'Tempo de execução em milissegundos',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_token_id (token_id),
        INDEX idx_user_id (user_id),
        INDEX idx_endpoint (endpoint(255)),
        INDEX idx_method (method),
        INDEX idx_response_code (response_code),
        INDEX idx_created_at (created_at),
        INDEX idx_ip_address (ip_address)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'api_logs' criada com sucesso!\n";
}

function down_create_api_logs_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS api_logs";
    
    if (isset($pdo)) {
        $pdo->exec($sql);
    } else {
        \App\Helpers\Database::getInstance()->exec($sql);
    }
    
    echo "✅ Tabela 'api_logs' removida com sucesso!\n";
}
