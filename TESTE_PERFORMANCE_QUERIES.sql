-- ========================================
-- TESTE DE PERFORMANCE DAS QUERIES
-- ========================================
-- Execute este script ANTES e DEPOIS de criar os índices
-- para comparar o ganho de performance
-- Data: 2026-01-12

USE chat_person;

-- ========================================
-- PREPARAÇÃO: Habilitar profiling
-- ========================================
SET profiling = 1;
SET profiling_history_size = 100;

-- ========================================
-- TESTE #1: Query de Tempo Médio de Resposta
-- ========================================
-- Esta é a query mais pesada (217k linhas examinadas)

SELECT '=== TESTE #1: Tempo Médio de Resposta ===' as teste;

-- Executar a query
SELECT AVG(response_time_seconds) as avg_seconds
FROM (
    SELECT 
        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
    FROM messages m1
    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
        AND m2.sender_type = 'agent'
        AND m2.created_at > m1.created_at
        AND m2.created_at = (
            SELECT MIN(m3.created_at)
            FROM messages m3
            WHERE m3.conversation_id = m1.conversation_id
            AND m3.sender_type = 'agent'
            AND m3.created_at > m1.created_at
        )
    INNER JOIN conversations c ON c.id = m1.conversation_id
    WHERE m1.sender_type = 'contact'
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
    HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
) as response_times;

-- Ver plano de execução
EXPLAIN 
SELECT AVG(response_time_seconds) as avg_seconds
FROM (
    SELECT 
        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
    FROM messages m1
    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
        AND m2.sender_type = 'agent'
        AND m2.created_at > m1.created_at
        AND m2.created_at = (
            SELECT MIN(m3.created_at)
            FROM messages m3
            WHERE m3.conversation_id = m1.conversation_id
            AND m3.sender_type = 'agent'
            AND m3.created_at > m1.created_at
        )
    INNER JOIN conversations c ON c.id = m1.conversation_id
    WHERE m1.sender_type = 'contact'
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
    HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
) as response_times;

-- ========================================
-- TESTE #2: Query de Ranking de Agentes
-- ========================================
-- Esta query examina 768k linhas

SELECT '=== TESTE #2: Ranking de Agentes ===' as teste;

-- Executar a query
SELECT 
    u.id,
    u.name,
    u.email,
    u.avatar,
    COUNT(DISTINCT c.id) as total_conversations,
    COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations,
    COUNT(DISTINCT m.id) as total_messages,
    AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) as avg_resolution_time
FROM users u
LEFT JOIN conversations c ON u.id = c.agent_id 
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
LEFT JOIN messages m ON u.id = m.sender_id 
    AND m.sender_type = 'agent'
    AND m.ai_agent_id IS NULL
    AND m.created_at >= '2026-01-01'
    AND m.created_at <= '2026-01-12 23:59:59'
WHERE u.role IN ('agent', 'admin', 'supervisor')
    AND u.status = 'active'
GROUP BY u.id, u.name, u.email, u.avatar
HAVING total_conversations > 0
ORDER BY closed_conversations DESC, total_conversations DESC
LIMIT 5;

-- Ver plano de execução
EXPLAIN 
SELECT 
    u.id,
    u.name,
    u.email,
    u.avatar,
    COUNT(DISTINCT c.id) as total_conversations,
    COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations,
    COUNT(DISTINCT m.id) as total_messages,
    AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) as avg_resolution_time
FROM users u
LEFT JOIN conversations c ON u.id = c.agent_id 
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
LEFT JOIN messages m ON u.id = m.sender_id 
    AND m.sender_type = 'agent'
    AND m.ai_agent_id IS NULL
    AND m.created_at >= '2026-01-01'
    AND m.created_at <= '2026-01-12 23:59:59'
WHERE u.role IN ('agent', 'admin', 'supervisor')
    AND u.status = 'active'
GROUP BY u.id, u.name, u.email, u.avatar
HAVING total_conversations > 0
ORDER BY closed_conversations DESC, total_conversations DESC
LIMIT 5;

-- ========================================
-- RESULTADOS DO PROFILING
-- ========================================

SELECT '=== RESULTADOS DO PROFILING ===' as resultado;

-- Ver tempo de execução das queries
SHOW PROFILES;

-- Ver detalhes da última query
SHOW PROFILE FOR QUERY 1;

-- Ver uso de CPU/memória
SHOW PROFILE CPU, BLOCK IO FOR QUERY 1;

-- ========================================
-- ANÁLISE DE ÍNDICES USADOS
-- ========================================

SELECT '=== ÍNDICES DISPONÍVEIS ===' as analise;

-- Ver todos os índices em messages
SELECT 
    'messages' as tabela,
    INDEX_NAME as indice,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as colunas,
    CARDINALITY as cardinalidade,
    INDEX_TYPE as tipo
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME = 'messages'
GROUP BY INDEX_NAME, CARDINALITY, INDEX_TYPE
ORDER BY INDEX_NAME;

-- Ver todos os índices em conversations
SELECT 
    'conversations' as tabela,
    INDEX_NAME as indice,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as colunas,
    CARDINALITY as cardinalidade,
    INDEX_TYPE as tipo
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME = 'conversations'
GROUP BY INDEX_NAME, CARDINALITY, INDEX_TYPE
ORDER BY INDEX_NAME;

-- ========================================
-- COMPARAÇÃO ANTES/DEPOIS
-- ========================================

SELECT '=== INSTRUÇÕES PARA COMPARAÇÃO ===' as instrucoes;

SELECT 
    'ANTES DOS ÍNDICES:' as etapa,
    '1. Execute este script completo' as passo_1,
    '2. Anote os tempos do SHOW PROFILES' as passo_2,
    '3. Anote o "rows" do EXPLAIN' as passo_3
UNION ALL
SELECT 
    'CRIAR ÍNDICES:',
    '4. Execute CRIAR_INDICES_OTIMIZADOS.sql',
    '5. Aguarde a criação dos índices',
    '6. Execute ANALYZE TABLE'
UNION ALL
SELECT 
    'DEPOIS DOS ÍNDICES:',
    '7. Execute este script novamente',
    '8. Compare os tempos e rows',
    '9. Ganho esperado: 70-90% mais rápido';

-- ========================================
-- MÉTRICAS ESPERADAS
-- ========================================

SELECT '=== MÉTRICAS ESPERADAS ===' as metricas;

SELECT 
    'Query #1' as query,
    'ANTES' as momento,
    '3+ segundos' as tempo,
    '217k linhas' as rows_examined,
    'Full table scan' as tipo_acesso
UNION ALL
SELECT 
    'Query #1',
    'DEPOIS',
    '0.5 segundos',
    '1-5k linhas',
    'Index range scan'
UNION ALL
SELECT 
    'Query #2',
    'ANTES',
    '1+ segundo',
    '768k linhas',
    'Full table scan'
UNION ALL
SELECT 
    'Query #2',
    'DEPOIS',
    '0.3 segundos',
    '5-10k linhas',
    'Index range scan';

-- ========================================
-- LIMPEZA
-- ========================================
SET profiling = 0;
