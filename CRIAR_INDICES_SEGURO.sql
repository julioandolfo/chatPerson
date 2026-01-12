-- 🚀 CRIAR ÍNDICES - VERSÃO SEGURA
-- Este script ignora erros se índice já existir
-- Execute: mysql -u root -p chat_person < CRIAR_INDICES_SEGURO.sql

USE chat_person;

-- Desabilitar erro se índice já existir (apenas aviso)
SET SQL_NOTES = 0;

-- ========================================
-- LIMPAR ÍNDICES ANTIGOS (se existirem)
-- ========================================

DROP INDEX IF EXISTS idx_messages_unread ON messages;
DROP INDEX IF EXISTS idx_messages_conversation_created ON messages;
DROP INDEX IF EXISTS idx_messages_response ON messages;
DROP INDEX IF EXISTS idx_messages_conv_sender_date ON messages;

-- ========================================
-- CRIAR ÍNDICES NOVOS
-- ========================================

SELECT 'Criando índice 1/4: idx_messages_unread...' as status;
CREATE INDEX idx_messages_unread 
ON messages (conversation_id, sender_type, read_at);

SELECT 'Criando índice 2/4: idx_messages_conversation_created...' as status;
CREATE INDEX idx_messages_conversation_created 
ON messages (conversation_id, created_at DESC);

SELECT 'Criando índice 3/4: idx_messages_response...' as status;
CREATE INDEX idx_messages_response 
ON messages (conversation_id, sender_type, created_at);

SELECT 'Criando índice 4/4: idx_messages_conv_sender_date...' as status;
CREATE INDEX idx_messages_conv_sender_date 
ON messages (conversation_id, sender_type, created_at);

-- ========================================
-- ATUALIZAR ESTATÍSTICAS
-- ========================================

SELECT 'Atualizando estatísticas da tabela messages...' as status;
ANALYZE TABLE messages;

-- ========================================
-- VERIFICAR ÍNDICES CRIADOS
-- ========================================

SELECT 'Índices criados com sucesso!' as status;
SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_messages_%';

-- Reabilitar notas SQL
SET SQL_NOTES = 1;

SELECT '✅ CONCLUÍDO! Índices criados. Agora teste o QPS.' as resultado;
