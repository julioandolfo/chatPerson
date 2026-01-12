-- ========================================
-- VERIFICAR ÍNDICES EXISTENTES
-- ========================================
-- Execute este script no MySQL para verificar os índices atuais
-- Data: 2026-01-12

USE chat_person;

-- ========================================
-- 1. ÍNDICES DA TABELA MESSAGES
-- ========================================
SELECT 
    'MESSAGES' as tabela,
    INDEX_NAME as indice,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as colunas,
    NON_UNIQUE as nao_unico,
    INDEX_TYPE as tipo
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME = 'messages'
GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
ORDER BY INDEX_NAME;

-- ========================================
-- 2. ÍNDICES DA TABELA CONVERSATIONS
-- ========================================
SELECT 
    'CONVERSATIONS' as tabela,
    INDEX_NAME as indice,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as colunas,
    NON_UNIQUE as nao_unico,
    INDEX_TYPE as tipo
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME = 'conversations'
GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
ORDER BY INDEX_NAME;

-- ========================================
-- 3. ÍNDICES DA TABELA USERS
-- ========================================
SELECT 
    'USERS' as tabela,
    INDEX_NAME as indice,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as colunas,
    NON_UNIQUE as nao_unico,
    INDEX_TYPE as tipo
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME = 'users'
GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
ORDER BY INDEX_NAME;

-- ========================================
-- 4. VERIFICAR ÍNDICES NECESSÁRIOS
-- ========================================

-- Índices críticos para Query #1 (Histórico do Contato)
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = 'chat_person' 
              AND TABLE_NAME = 'messages'
              AND INDEX_NAME = 'idx_messages_conv_sender_date'
        ) THEN '✅ OK'
        ELSE '❌ FALTANDO'
    END as status,
    'idx_messages_conv_sender_date' as indice_necessario,
    'messages(conversation_id, sender_type, created_at)' as colunas,
    'Query #1 - Histórico do Contato' as uso
UNION ALL
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = 'chat_person' 
              AND TABLE_NAME = 'conversations'
              AND INDEX_NAME = 'idx_conversations_contact'
        ) THEN '✅ OK'
        ELSE '❌ FALTANDO'
    END,
    'idx_conversations_contact',
    'conversations(contact_id)',
    'Query #1 - Histórico do Contato'
    
-- Índices críticos para Query #2 (Ranking de Agentes)
UNION ALL
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = 'chat_person' 
              AND TABLE_NAME = 'conversations'
              AND INDEX_NAME = 'idx_conversations_agent_date_status'
        ) THEN '✅ OK'
        ELSE '❌ FALTANDO'
    END,
    'idx_conversations_agent_date_status',
    'conversations(agent_id, created_at, status, resolved_at)',
    'Query #2 - Ranking de Agentes'
UNION ALL
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = 'chat_person' 
              AND TABLE_NAME = 'messages'
              AND INDEX_NAME = 'idx_messages_sender_type_date'
        ) THEN '✅ OK'
        ELSE '❌ FALTANDO'
    END,
    'idx_messages_sender_type_date',
    'messages(sender_id, sender_type, created_at, ai_agent_id)',
    'Query #2 - Ranking de Agentes'
UNION ALL
SELECT 
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = 'chat_person' 
              AND TABLE_NAME = 'users'
              AND INDEX_NAME = 'idx_users_role_status'
        ) THEN '✅ OK'
        ELSE '❌ FALTANDO'
    END,
    'idx_users_role_status',
    'users(role, status)',
    'Query #2 - Ranking de Agentes';

-- ========================================
-- 5. ESTATÍSTICAS DAS TABELAS
-- ========================================
SELECT 
    TABLE_NAME as tabela,
    TABLE_ROWS as linhas_aproximadas,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) as tamanho_dados_mb,
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as tamanho_indices_mb,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as tamanho_total_mb
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME IN ('messages', 'conversations', 'users', 'contacts')
ORDER BY TABLE_ROWS DESC;
