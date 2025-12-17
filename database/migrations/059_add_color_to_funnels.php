<?php
/**
 * Migration: Adicionar coluna color na tabela funnels
 */

require_once __DIR__ . '/../../app/Helpers/Database.php';

function up_add_color_to_funnels() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        // Verificar se coluna já existe
        $columns = $db->query("SHOW COLUMNS FROM funnels LIKE 'color'")->fetchAll();
        
        if (empty($columns)) {
            // Adicionar coluna color
            $db->exec("ALTER TABLE funnels ADD COLUMN color VARCHAR(20) DEFAULT '#009ef7' COMMENT 'Cor do funil' AFTER status");
            echo "✅ Coluna 'color' adicionada à tabela funnels\n";
        } else {
            echo "ℹ️ Coluna 'color' já existe na tabela funnels\n";
        }
        
        echo "✅ Migration 059_add_color_to_funnels executada com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao adicionar coluna: " . $e->getMessage() . "\n";
        throw $e;
    }
}

function down_add_color_to_funnels() {
    global $pdo;
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    try {
        $db->exec("ALTER TABLE funnels DROP COLUMN IF EXISTS color");
        echo "✅ Coluna 'color' removida da tabela funnels\n";
        echo "✅ Rollback da migration 059 executado com sucesso!\n";
        
    } catch (PDOException $e) {
        echo "❌ Erro ao reverter migration: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Executar se chamado diretamente
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'] ?? '')) {
    require_once __DIR__ . '/../../config/database.php';
    up_add_color_to_funnels();
}

