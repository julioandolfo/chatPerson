-- 🚀 CRIAR ÍNDICES - VERSÃO UNIVERSAL
-- Funciona em QUALQUER versão do MySQL (5.5+)
-- Se índice já existe, ignora o erro e continua

USE chat_person;

-- ========================================
-- ÍNDICE 1: idx_messages_unread
-- ========================================

-- Se der erro (índice já existe), apenas ignore
CREATE INDEX idx_messages_unread 
ON messages (conversation_id, sender_type, read_at);
-- Erro 1061 (Duplicate key name) será ignorado

-- ========================================
-- ÍNDICE 2: idx_messages_conversation_created
-- ========================================

CREATE INDEX idx_messages_conversation_created 
ON messages (conversation_id, created_at DESC);

-- ========================================
-- ÍNDICE 3: idx_messages_response
-- ========================================

CREATE INDEX idx_messages_response 
ON messages (conversation_id, sender_type, created_at);

-- ========================================
-- ÍNDICE 4: idx_messages_conv_sender_date
-- ========================================

CREATE INDEX idx_messages_conv_sender_date 
ON messages (conversation_id, sender_type, created_at);

-- ========================================
-- ATUALIZAR ESTATÍSTICAS
-- ========================================

ANALYZE TABLE messages;

-- ========================================
-- VERIFICAR ÍNDICES CRIADOS
-- ========================================

SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_messages_%';

-- ========================================
-- FIM
-- ========================================

SELECT 'Se viu mensagens de erro sobre duplicate key, IGNORE - significa que índice já existe!' as aviso;
SELECT 'Verifique a lista de índices acima.' as instrucao;
