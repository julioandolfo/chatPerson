<?php
/**
 * Migration: Adicionar campo priority à tabela conversations
 * Para permitir SLA diferenciado por prioridade
 */

function up_add_priority_to_conversations() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🔧 Adicionando campo 'priority' à tabela conversations...\n";
    
    // Verificar se coluna já existe
    $checkSql = "SHOW COLUMNS FROM conversations LIKE 'priority'";
    $result = $db->query($checkSql)->fetchAll();
    
    if (empty($result)) {
        $sql = "ALTER TABLE conversations 
                ADD COLUMN priority VARCHAR(50) DEFAULT 'normal' 
                COMMENT 'urgent, high, normal, low' 
                AFTER status";
        
        $db->exec($sql);
        echo "   ✅ Coluna 'priority' adicionada\n";
        
        // Adicionar índice
        try {
            $db->exec("CREATE INDEX idx_priority ON conversations(priority)");
            echo "   ✅ Índice 'idx_priority' criado\n";
        } catch (\PDOException $e) {
            echo "   ℹ️  Índice já existe ou erro: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ℹ️  Coluna 'priority' já existe\n";
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
}

function down_add_priority_to_conversations() {
    $db = \App\Helpers\Database::getInstance();
    
    echo "🔧 Removendo campo 'priority' da tabela conversations...\n";
    
    $db->exec("ALTER TABLE conversations DROP COLUMN IF EXISTS priority");
    echo "   ✅ Coluna removida\n";
    
    echo "\n✅ Rollback concluído!\n";
}
