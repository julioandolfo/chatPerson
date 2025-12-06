<?php
/**
 * Migration: Adicionar campo pinned (fixar/destacar) à tabela conversations
 */

function up_add_pinned_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            ADD COLUMN IF NOT EXISTS pinned TINYINT(1) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS pinned_at TIMESTAMP NULL,
            ADD INDEX IF NOT EXISTS idx_pinned (pinned)";
    
    try {
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Campo 'pinned' adicionado à tabela 'conversations'!\n";
    } catch (\Exception $e) {
        // Tentar sem IF NOT EXISTS (MySQL antigo)
        try {
            $sql1 = "ALTER TABLE conversations ADD COLUMN pinned TINYINT(1) DEFAULT 0";
            if (isset($pdo)) {
                $pdo->exec($sql1);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql1);
            }
            
            try {
                $sql2 = "ALTER TABLE conversations ADD COLUMN pinned_at TIMESTAMP NULL";
                if (isset($pdo)) {
                    $pdo->exec($sql2);
                } else {
                    \App\Helpers\Database::getInstance()->exec($sql2);
                }
            } catch (\Exception $e2) {
                // Coluna pode já existir
            }
            
            try {
                $indexSql = "CREATE INDEX idx_pinned ON conversations(pinned)";
                if (isset($pdo)) {
                    $pdo->exec($indexSql);
                } else {
                    \App\Helpers\Database::getInstance()->exec($indexSql);
                }
            } catch (\Exception $e3) {
                // Índice pode já existir
            }
            
            echo "✅ Campo 'pinned' adicionado à tabela 'conversations'!\n";
        } catch (\Exception $e2) {
            echo "⚠️ Erro ao adicionar campo (pode já existir): " . $e2->getMessage() . "\n";
        }
    }
}

function down_add_pinned_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            DROP INDEX IF EXISTS idx_pinned,
            DROP COLUMN IF EXISTS pinned_at,
            DROP COLUMN IF EXISTS pinned";
    
    try {
        if (isset($pdo)) {
            $pdo->exec($sql);
        } else {
            \App\Helpers\Database::getInstance()->exec($sql);
        }
        echo "✅ Campo 'pinned' removido da tabela 'conversations'!\n";
    } catch (\Exception $e) {
        // Tentar sem IF EXISTS
        try {
            $sql1 = "ALTER TABLE conversations DROP INDEX idx_pinned";
            if (isset($pdo)) {
                $pdo->exec($sql1);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql1);
            }
        } catch (\Exception $e1) {
            // Ignorar
        }
        
        try {
            $sql2 = "ALTER TABLE conversations DROP COLUMN pinned_at, DROP COLUMN pinned";
            if (isset($pdo)) {
                $pdo->exec($sql2);
            } else {
                \App\Helpers\Database::getInstance()->exec($sql2);
            }
            echo "✅ Campo 'pinned' removido da tabela 'conversations'!\n";
        } catch (\Exception $e2) {
            echo "⚠️ Erro ao remover campo: " . $e2->getMessage() . "\n";
        }
    }
}

