<?php
/**
 * Migration: Adicionar campo is_spam na tabela conversations
 */

function up_add_is_spam_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            ADD COLUMN IF NOT EXISTS is_spam TINYINT(1) DEFAULT 0 COMMENT 'Indica se a conversa foi marcada como spam' AFTER status,
            ADD COLUMN IF NOT EXISTS spam_marked_at TIMESTAMP NULL COMMENT 'Data/hora em que foi marcada como spam' AFTER is_spam,
            ADD COLUMN IF NOT EXISTS spam_marked_by INT NULL COMMENT 'ID do usuário que marcou como spam' AFTER spam_marked_at,
            ADD INDEX IF NOT EXISTS idx_is_spam (is_spam)";
    
    if (isset($pdo)) {
        try {
            // Verificar se coluna já existe
            $checkSql = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'conversations' 
                        AND COLUMN_NAME = 'is_spam'";
            $result = $pdo->query($checkSql)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $pdo->exec("ALTER TABLE conversations ADD COLUMN is_spam TINYINT(1) DEFAULT 0 COMMENT 'Indica se a conversa foi marcada como spam' AFTER status");
                echo "✅ Campo 'is_spam' adicionado à tabela 'conversations'!\n";
            } else {
                echo "ℹ️ Campo 'is_spam' já existe na tabela 'conversations'\n";
            }
            
            // Verificar se coluna spam_marked_at já existe
            $checkSql2 = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'conversations' 
                         AND COLUMN_NAME = 'spam_marked_at'";
            $result2 = $pdo->query($checkSql2)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result2['count'] == 0) {
                $pdo->exec("ALTER TABLE conversations ADD COLUMN spam_marked_at TIMESTAMP NULL COMMENT 'Data/hora em que foi marcada como spam' AFTER is_spam");
                echo "✅ Campo 'spam_marked_at' adicionado à tabela 'conversations'!\n";
            }
            
            // Verificar se coluna spam_marked_by já existe
            $checkSql3 = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'conversations' 
                         AND COLUMN_NAME = 'spam_marked_by'";
            $result3 = $pdo->query($checkSql3)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result3['count'] == 0) {
                $pdo->exec("ALTER TABLE conversations ADD COLUMN spam_marked_by INT NULL COMMENT 'ID do usuário que marcou como spam' AFTER spam_marked_at");
                $pdo->exec("ALTER TABLE conversations ADD FOREIGN KEY (spam_marked_by) REFERENCES users(id) ON DELETE SET NULL");
                echo "✅ Campo 'spam_marked_by' adicionado à tabela 'conversations'!\n";
            }
            
            // Adicionar índice se não existir
            try {
                $pdo->exec("ALTER TABLE conversations ADD INDEX idx_is_spam (is_spam)");
                echo "✅ Índice 'idx_is_spam' adicionado!\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
                echo "ℹ️ Índice 'idx_is_spam' já existe\n";
            }
        } catch (\PDOException $e) {
            echo "⚠️ Erro ao adicionar campos: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            $db = \App\Helpers\Database::getInstance();
            
            // Verificar se coluna já existe
            $checkSql = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'conversations' 
                        AND COLUMN_NAME = 'is_spam'";
            $result = $db->query($checkSql)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                $db->exec("ALTER TABLE conversations ADD COLUMN is_spam TINYINT(1) DEFAULT 0 COMMENT 'Indica se a conversa foi marcada como spam' AFTER status");
                echo "✅ Campo 'is_spam' adicionado à tabela 'conversations'!\n";
            } else {
                echo "ℹ️ Campo 'is_spam' já existe na tabela 'conversations'\n";
            }
            
            // Verificar se coluna spam_marked_at já existe
            $checkSql2 = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'conversations' 
                         AND COLUMN_NAME = 'spam_marked_at'";
            $result2 = $db->query($checkSql2)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result2['count'] == 0) {
                $db->exec("ALTER TABLE conversations ADD COLUMN spam_marked_at TIMESTAMP NULL COMMENT 'Data/hora em que foi marcada como spam' AFTER is_spam");
                echo "✅ Campo 'spam_marked_at' adicionado à tabela 'conversations'!\n";
            }
            
            // Verificar se coluna spam_marked_by já existe
            $checkSql3 = "SELECT COUNT(*) as count FROM information_schema.COLUMNS 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = 'conversations' 
                         AND COLUMN_NAME = 'spam_marked_by'";
            $result3 = $db->query($checkSql3)->fetch(\PDO::FETCH_ASSOC);
            
            if ($result3['count'] == 0) {
                $db->exec("ALTER TABLE conversations ADD COLUMN spam_marked_by INT NULL COMMENT 'ID do usuário que marcou como spam' AFTER spam_marked_at");
                $db->exec("ALTER TABLE conversations ADD FOREIGN KEY (spam_marked_by) REFERENCES users(id) ON DELETE SET NULL");
                echo "✅ Campo 'spam_marked_by' adicionado à tabela 'conversations'!\n";
            }
            
            // Adicionar índice se não existir
            try {
                $db->exec("ALTER TABLE conversations ADD INDEX idx_is_spam (is_spam)");
                echo "✅ Índice 'idx_is_spam' adicionado!\n";
            } catch (\PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    throw $e;
                }
                echo "ℹ️ Índice 'idx_is_spam' já existe\n";
            }
        } catch (\PDOException $e) {
            echo "⚠️ Erro ao adicionar campos: " . $e->getMessage() . "\n";
        }
    }
}

function down_add_is_spam_to_conversations() {
    global $pdo;
    
    $sql = "ALTER TABLE conversations 
            DROP INDEX IF EXISTS idx_is_spam,
            DROP FOREIGN KEY IF EXISTS conversations_ibfk_spam_marked_by,
            DROP COLUMN IF EXISTS spam_marked_by,
            DROP COLUMN IF EXISTS spam_marked_at,
            DROP COLUMN IF EXISTS is_spam";
    
    if (isset($pdo)) {
        try {
            $pdo->exec("ALTER TABLE conversations DROP INDEX idx_is_spam");
        } catch (\PDOException $e) {
            // Ignorar se não existir
        }
        try {
            $pdo->exec("ALTER TABLE conversations DROP FOREIGN KEY conversations_ibfk_spam_marked_by");
        } catch (\PDOException $e) {
            // Ignorar se não existir
        }
        try {
            $pdo->exec("ALTER TABLE conversations DROP COLUMN spam_marked_by, DROP COLUMN spam_marked_at, DROP COLUMN is_spam");
            echo "✅ Campos de spam removidos da tabela 'conversations'!\n";
        } catch (\PDOException $e) {
            echo "⚠️ Erro ao remover campos: " . $e->getMessage() . "\n";
        }
    } else {
        try {
            $db = \App\Helpers\Database::getInstance();
            $db->exec("ALTER TABLE conversations DROP INDEX idx_is_spam");
        } catch (\PDOException $e) {
            // Ignorar
        }
        try {
            $db->exec("ALTER TABLE conversations DROP FOREIGN KEY conversations_ibfk_spam_marked_by");
        } catch (\PDOException $e) {
            // Ignorar
        }
        try {
            $db->exec("ALTER TABLE conversations DROP COLUMN spam_marked_by, DROP COLUMN spam_marked_at, DROP COLUMN is_spam");
            echo "✅ Campos de spam removidos da tabela 'conversations'!\n";
        } catch (\PDOException $e) {
            echo "⚠️ Erro ao remover campos: " . $e->getMessage() . "\n";
        }
    }
}

