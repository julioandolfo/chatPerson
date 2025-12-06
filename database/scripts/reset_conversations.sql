-- ============================================
-- Script SQL para Resetar Conversas e Dados de Teste
-- ============================================
-- ATENÇÃO: Este script irá DELETAR TODAS as conversas e mensagens!
-- Use apenas em ambiente de desenvolvimento/teste!
-- 
-- RECOMENDAÇÃO: Use o script PHP reset_conversations.php ao invés deste SQL
-- para melhor tratamento de erros e verificações.
-- ============================================

-- Desabilitar verificação de foreign keys temporariamente
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Deletar mensagens primeiro (devido à foreign key)
DELETE FROM messages;

-- 2. Deletar relacionamentos de tags com conversas
DELETE FROM conversation_tags;

-- 3. Deletar logs de automação relacionados (se existir)
DELETE FROM automation_logs WHERE conversation_id IS NOT NULL;

-- 4. Deletar conversas de IA relacionadas (se existir)
DELETE FROM ai_conversations WHERE conversation_id IS NOT NULL;

-- 5. Deletar todas as conversas
DELETE FROM conversations;

-- 6. Resetar auto increments
ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE conversations AUTO_INCREMENT = 1;

-- Reabilitar verificação de foreign keys
SET FOREIGN_KEY_CHECKS = 1;

-- Verificar contagem após limpeza
SELECT 
    (SELECT COUNT(*) FROM messages) as total_messages,
    (SELECT COUNT(*) FROM conversations) as total_conversations,
    (SELECT COUNT(*) FROM contacts) as total_contacts;

