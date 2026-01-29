<?php
/**
 * Migration: Adicionar suporte a múltiplas contas vinculadas em conversas
 * 
 * Permite que uma conversa seja associada a múltiplos números de WhatsApp
 * quando o mesmo contato fala por diferentes números do sistema.
 */

use App\Helpers\Database;

return new class {
    public function up(): void
    {
        $pdo = Database::getInstance();
        
        // Adicionar campo para armazenar IDs das contas vinculadas (JSON)
        // Isso permite que uma conversa tenha múltiplos números associados
        try {
            $pdo->exec("ALTER TABLE conversations 
                ADD COLUMN linked_account_ids JSON NULL 
                COMMENT 'IDs das contas de integração vinculadas (quando mesclado)' 
                AFTER integration_account_id");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
        
        // Adicionar campo para rastrear o último número usado pelo cliente
        try {
            $pdo->exec("ALTER TABLE conversations 
                ADD COLUMN last_customer_account_id INT NULL 
                COMMENT 'ID da conta pelo qual o cliente enviou a última mensagem' 
                AFTER linked_account_ids");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
        
        // Adicionar campo para marcar se a conversa foi mesclada
        try {
            $pdo->exec("ALTER TABLE conversations 
                ADD COLUMN is_merged TINYINT(1) DEFAULT 0 
                COMMENT 'Indica se esta conversa foi mesclada de outras' 
                AFTER last_customer_account_id");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
        
        // Adicionar campo na tabela messages para rastrear por qual conta a mensagem foi enviada/recebida
        try {
            $pdo->exec("ALTER TABLE messages 
                ADD COLUMN via_account_id INT NULL 
                COMMENT 'ID da conta de integração por onde a mensagem passou' 
                AFTER conversation_id");
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
        
        // Índice para busca rápida
        try {
            $pdo->exec("CREATE INDEX idx_conversations_merged ON conversations(is_merged)");
        } catch (\PDOException $e) {
            // Índice já existe
        }
    }

    public function down(): void
    {
        $pdo = Database::getInstance();
        
        try {
            $pdo->exec("ALTER TABLE conversations DROP COLUMN linked_account_ids");
        } catch (\PDOException $e) {}
        
        try {
            $pdo->exec("ALTER TABLE conversations DROP COLUMN last_customer_account_id");
        } catch (\PDOException $e) {}
        
        try {
            $pdo->exec("ALTER TABLE conversations DROP COLUMN is_merged");
        } catch (\PDOException $e) {}
        
        try {
            $pdo->exec("ALTER TABLE messages DROP COLUMN via_account_id");
        } catch (\PDOException $e) {}
        
        try {
            $pdo->exec("DROP INDEX idx_conversations_merged ON conversations");
        } catch (\PDOException $e) {}
    }
};
