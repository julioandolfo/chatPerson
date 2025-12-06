<?php
/**
 * Migration: Criar tabela ai_tools
 * Tools disponíveis para agentes de IA
 */

function up_ai_tools_table() {
    global $pdo;
    
    $sql = "CREATE TABLE IF NOT EXISTS ai_tools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL COMMENT 'Nome da tool',
        slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'Slug único da tool',
        description TEXT NULL COMMENT 'Descrição da tool',
        tool_type VARCHAR(50) NOT NULL COMMENT 'Tipo: woocommerce, database, n8n, document, system, api, followup',
        function_schema JSON NOT NULL COMMENT 'Schema da função para OpenAI Function Calling',
        config JSON NULL COMMENT 'Configuração específica da tool (URLs, credenciais, etc)',
        enabled BOOLEAN DEFAULT TRUE COMMENT 'Se a tool está ativa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_tool_type (tool_type),
        INDEX idx_enabled (enabled),
        INDEX idx_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_tools' criada com sucesso!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Tabela 'ai_tools' pode já existir ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_tools' criada com sucesso!\n";
        } catch (\Exception $e) {
            echo "⚠️  Tabela 'ai_tools' pode já existir\n";
        }
    }
}

function down_ai_tools_table() {
    global $pdo;
    
    $sql = "DROP TABLE IF EXISTS ai_tools";
    
    if (isset($pdo)) {
        try {
            $pdo->exec($sql);
            echo "✅ Tabela 'ai_tools' removida!\n";
        } catch (\PDOException $e) {
            echo "⚠️  Erro ao remover tabela 'ai_tools': " . $e->getMessage() . "\n";
        }
    } else {
        try {
            \App\Helpers\Database::getInstance()->exec($sql);
            echo "✅ Tabela 'ai_tools' removida!\n";
        } catch (\Exception $e) {
            echo "⚠️  Erro ao remover tabela 'ai_tools'\n";
        }
    }
}

