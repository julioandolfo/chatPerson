<?php
/**
 * Migration: Adicionar suporte a solicitações de participação
 * 
 * Adiciona campo 'type' para diferenciar convites de solicitações:
 * - 'invite': Convite enviado por um agente para outro participar
 * - 'request': Solicitação feita por um agente para entrar na conversa
 */

function up_add_request_type_to_mentions() {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Verificar se coluna já existe
    $columnExists = $pdo->query("SHOW COLUMNS FROM conversation_mentions LIKE 'type'")->rowCount() > 0;
    
    if (!$columnExists) {
        // Adicionar coluna type
        $pdo->exec("ALTER TABLE conversation_mentions 
                    ADD COLUMN type ENUM('invite', 'request') NOT NULL DEFAULT 'invite' 
                    COMMENT 'Tipo: invite=convite, request=solicitação' 
                    AFTER mentioned_user_id");
        
        echo "✅ Coluna 'type' adicionada à tabela 'conversation_mentions'!\n";
    } else {
        echo "⏭️ Coluna 'type' já existe.\n";
    }
    
    // Adicionar índice para type
    $indexExists = $pdo->query("SHOW INDEX FROM conversation_mentions WHERE Key_name = 'idx_type'")->rowCount() > 0;
    
    if (!$indexExists) {
        $pdo->exec("CREATE INDEX idx_type ON conversation_mentions(type)");
        echo "✅ Índice 'idx_type' criado!\n";
    }
    
    // Adicionar índice composto para buscar solicitações pendentes
    $indexExists2 = $pdo->query("SHOW INDEX FROM conversation_mentions WHERE Key_name = 'idx_pending_requests'")->rowCount() > 0;
    
    if (!$indexExists2) {
        $pdo->exec("CREATE INDEX idx_pending_requests ON conversation_mentions(conversation_id, type, status)");
        echo "✅ Índice 'idx_pending_requests' criado!\n";
    }
}

function down_add_request_type_to_mentions() {
    $pdo = \App\Helpers\Database::getInstance();
    
    // Remover índices
    $pdo->exec("DROP INDEX IF EXISTS idx_type ON conversation_mentions");
    $pdo->exec("DROP INDEX IF EXISTS idx_pending_requests ON conversation_mentions");
    
    // Remover coluna
    $pdo->exec("ALTER TABLE conversation_mentions DROP COLUMN IF EXISTS type");
    
    echo "✅ Coluna 'type' removida da tabela 'conversation_mentions'.\n";
}

