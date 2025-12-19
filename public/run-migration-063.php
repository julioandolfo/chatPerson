<?php
/**
 * Script para executar migration 063 - Adicionar metadata
 */

require_once __DIR__ . '/../app/Helpers/autoload.php';

use App\Helpers\Database;

try {
    echo "<h1>Executando Migration 063 - Adicionar campos metadata e assigned_at</h1>";
    
    $db = Database::getInstance();
    
    // 1. METADATA
    echo "<h2>1. Campo metadata</h2>";
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'metadata'";
    $stmt = $db->query($checkSql);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: orange;'>⚠️ Coluna 'metadata' já existe! Pulando...</p>";
    } else {
        $sql = "ALTER TABLE conversations 
                ADD COLUMN metadata JSON NULL COMMENT 'Estado de chatbots, automações e dados dinâmicos' AFTER priority";
        $db->exec($sql);
        echo "<p style='color: green;'>✅ Coluna 'metadata' adicionada com sucesso!</p>";
    }
    
    // 2. ASSIGNED_AT
    echo "<h2>2. Campo assigned_at</h2>";
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'assigned_at'";
    $stmt = $db->query($checkSql);
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: orange;'>⚠️ Coluna 'assigned_at' já existe! Pulando...</p>";
    } else {
        $sql = "ALTER TABLE conversations 
                ADD COLUMN assigned_at TIMESTAMP NULL COMMENT 'Data/hora da atribuição ao agente' AFTER moved_at";
        $db->exec($sql);
        echo "<p style='color: green;'>✅ Coluna 'assigned_at' adicionada com sucesso!</p>";
    }
    
    // VERIFICAÇÃO FINAL
    echo "<h2>✅ Verificação Final</h2>";
    $checkSql = "SHOW COLUMNS FROM conversations WHERE Field IN ('metadata', 'assigned_at')";
    $stmt = $db->query($checkSql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>";
        foreach ($results as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

