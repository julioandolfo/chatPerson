<?php
/**
 * Migration: Adicionar campos de descrição para IA em funis e etapas
 * 
 * Estes campos permitem que a IA entenda o propósito de cada funil/etapa
 * para fazer classificações inteligentes automaticamente.
 */

function up_add_ai_description_to_funnels() {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Adicionar ai_description à tabela funnels
    try {
        $pdo->exec("ALTER TABLE funnels ADD COLUMN ai_description TEXT NULL AFTER description");
        echo "✅ Campo 'ai_description' adicionado à tabela 'funnels'\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠️ Campo 'ai_description' já existe em 'funnels'\n";
        } else {
            throw $e;
        }
    }
    
    // Adicionar ai_description à tabela funnel_stages
    try {
        $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN ai_description TEXT NULL AFTER description");
        echo "✅ Campo 'ai_description' adicionado à tabela 'funnel_stages'\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠️ Campo 'ai_description' já existe em 'funnel_stages'\n";
        } else {
            throw $e;
        }
    }
    
    // Adicionar ai_keywords à tabela funnel_stages
    try {
        $pdo->exec("ALTER TABLE funnel_stages ADD COLUMN ai_keywords VARCHAR(500) NULL AFTER ai_description");
        echo "✅ Campo 'ai_keywords' adicionado à tabela 'funnel_stages'\n";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "⚠️ Campo 'ai_keywords' já existe em 'funnel_stages'\n";
        } else {
            throw $e;
        }
    }
    
    echo "✅ Migration 065_add_ai_description_to_funnels concluída!\n";
}

function down_add_ai_description_to_funnels() {
    $pdo = \App\Helpers\Database::getInstance();
    
    try {
        $pdo->exec("ALTER TABLE funnels DROP COLUMN ai_description");
        echo "✅ Campo 'ai_description' removido de 'funnels'\n";
    } catch (\PDOException $e) {
        echo "⚠️ Não foi possível remover 'ai_description' de 'funnels': " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE funnel_stages DROP COLUMN ai_description");
        echo "✅ Campo 'ai_description' removido de 'funnel_stages'\n";
    } catch (\PDOException $e) {
        echo "⚠️ Não foi possível remover 'ai_description' de 'funnel_stages': " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE funnel_stages DROP COLUMN ai_keywords");
        echo "✅ Campo 'ai_keywords' removido de 'funnel_stages'\n";
    } catch (\PDOException $e) {
        echo "⚠️ Não foi possível remover 'ai_keywords' de 'funnel_stages': " . $e->getMessage() . "\n";
    }
}

// Executar migration se chamado diretamente
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === basename(__FILE__)) {
    require_once __DIR__ . '/../../config/config.php';
    up_add_ai_description_to_funnels();
}

