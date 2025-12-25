<?php
/**
 * Script para executar a migration 075 - Adicionar coluna type
 * Acesse: /run-migration-075.php
 * REMOVA ESTE ARQUIVO APÓS EXECUTAR!
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "<h1>Migration 075 - Adicionar coluna type</h1>";

try {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Verificar se coluna já existe
    $stmt = $pdo->query("SHOW COLUMNS FROM conversation_mentions LIKE 'type'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        // Adicionar coluna type
        $pdo->exec("ALTER TABLE conversation_mentions 
                    ADD COLUMN type ENUM('invite', 'request') NOT NULL DEFAULT 'invite' 
                    COMMENT 'Tipo: invite=convite, request=solicitação' 
                    AFTER mentioned_user_id");
        
        echo "<p style='color: green;'>✅ Coluna 'type' adicionada com sucesso!</p>";
    } else {
        echo "<p style='color: blue;'>⏭️ Coluna 'type' já existe.</p>";
    }
    
    // Adicionar índice para type
    $stmt = $pdo->query("SHOW INDEX FROM conversation_mentions WHERE Key_name = 'idx_type'");
    $indexExists = $stmt->rowCount() > 0;
    
    if (!$indexExists) {
        $pdo->exec("CREATE INDEX idx_type ON conversation_mentions(type)");
        echo "<p style='color: green;'>✅ Índice 'idx_type' criado!</p>";
    } else {
        echo "<p style='color: blue;'>⏭️ Índice 'idx_type' já existe.</p>";
    }
    
    // Adicionar índice composto para buscar solicitações pendentes
    $stmt = $pdo->query("SHOW INDEX FROM conversation_mentions WHERE Key_name = 'idx_pending_requests'");
    $indexExists2 = $stmt->rowCount() > 0;
    
    if (!$indexExists2) {
        $pdo->exec("CREATE INDEX idx_pending_requests ON conversation_mentions(conversation_id, type, status)");
        echo "<p style='color: green;'>✅ Índice 'idx_pending_requests' criado!</p>";
    } else {
        echo "<p style='color: blue;'>⏭️ Índice 'idx_pending_requests' já existe.</p>";
    }
    
    echo "<h2 style='color: green;'>Migration concluída com sucesso!</h2>";
    echo "<p><strong>⚠️ IMPORTANTE:</strong> Delete este arquivo após a execução!</p>";
    echo "<p><a href='/conversations'>Voltar para Conversas</a></p>";
    
} catch (\Exception $e) {
    echo "<p style='color: red;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

