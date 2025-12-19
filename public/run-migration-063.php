<?php
/**
 * Script para executar migration 063 - Adicionar metadata
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Database;

try {
    echo "<h1>Executando Migration 063</h1>";
    
    $db = Database::getInstance();
    
    // Verificar se a coluna já existe
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'metadata'";
    $stmt = $db->query($checkSql);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: orange;'>⚠️ Coluna 'metadata' já existe! Nada a fazer.</p>";
    } else {
        // Adicionar coluna
        $sql = "ALTER TABLE conversations 
                ADD COLUMN metadata JSON NULL COMMENT 'Estado de chatbots, automações e dados dinâmicos' AFTER priority";
        
        $db->exec($sql);
        echo "<p style='color: green;'>✅ Coluna 'metadata' adicionada com sucesso à tabela 'conversations'!</p>";
    }
    
    // Verificar resultado
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'metadata'";
    $stmt = $db->query($checkSql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "<h2>✅ Verificação</h2>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

