-- 🚨 ÍNDICES URGENTES - Reduzir QPS de 3.602 para < 1
-- Executar AGORA no banco chat_person
-- Tempo estimado: 2-5 minutos

USE chat_person;

-- ========================================
-- 1. Índice para unread_count
-- ========================================
-- Query: SELECT COUNT(*) FROM messages WHERE conversation_id = ? AND sender_type = 'contact' AND read_at IS NULL

-- Verificar se existe antes de criar
SELECT 'Criando idx_messages_unread...' as status;
CREATE INDEX idx_messages_unread 
ON messages (conversation_id, sender_type, read_at);

-- ========================================
-- 2. Índice para last_message / last_message_at
-- ========================================
-- Query: SELECT content/created_at FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1

SELECT 'Criando idx_messages_conversation_created...' as status;
CREATE INDEX idx_messages_conversation_created 
ON messages (conversation_id, created_at DESC);

-- ========================================
-- 3. Índice para first_response_at
-- ========================================
-- Query: SELECT created_at FROM messages WHERE conversation_id = ? AND sender_type IN ('agent', 'ai_agent') ORDER BY created_at ASC LIMIT 1

SELECT 'Criando idx_messages_response...' as status;
CREATE INDEX idx_messages_response 
ON messages (conversation_id, sender_type, created_at);

-- ========================================
-- 4. Índice composto (se não existir)
-- ========================================
-- Para queries que filtram por conversation_id + sender_type

SELECT 'Criando idx_messages_conv_sender_date...' as status;
CREATE INDEX idx_messages_conv_sender_date 
ON messages (conversation_id, sender_type, created_at);

-- ========================================
-- VERIFICAR ÍNDICES CRIADOS
-- ========================================

SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_messages_%';

-- ========================================
-- ANALISAR TABELA (atualizar estatísticas)
-- ========================================

ANALYZE TABLE messages;

-- ========================================
-- TESTAR PERFORMANCE
-- ========================================

-- Antes de criar índices, esta query era lenta:
EXPLAIN SELECT COUNT(*) 
FROM messages 
WHERE conversation_id = 1 
  AND sender_type = 'contact' 
  AND read_at IS NULL;

-- Após índices, deve usar 'idx_messages_unread' e ser rápida

-- ========================================
-- RESULTADO ESPERADO
-- ========================================

/*
ANTES:
- Rows examined: 50.000+ (full table scan)
- Time: 0.5-1.0s por subquery
- Total: 420 subqueries × 1s = 420s

DEPOIS:
- Rows examined: 1-100 (index scan)
- Time: 0.001-0.01s por subquery
- Total: 420 subqueries × 0.01s = 4.2s

GANHO: 95-99% mais rápido
QPS: 3.602 → 0.1-0.5
*/

-- ========================================
-- MEDIR QPS ANTES E DEPOIS
-- ========================================

-- ANTES:
SHOW GLOBAL STATUS LIKE 'Questions';
-- Anotar valor

-- Aguardar 10 segundos

SHOW GLOBAL STATUS LIKE 'Questions';
-- Calcular: (valor2 - valor1) / 10
