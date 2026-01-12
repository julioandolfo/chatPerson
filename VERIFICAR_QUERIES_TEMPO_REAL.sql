-- 🔍 Ver queries rodando AGORA (tempo real)
-- Execute este comando VÁRIAS VEZES para ver o padrão

-- 1. Ver processos ativos
SHOW FULL PROCESSLIST;

-- 2. Ver top 20 queries mais executadas nos últimos minutos
SELECT 
    LEFT(DIGEST_TEXT, 100) as query_resumo,
    COUNT_STAR as execucoes,
    ROUND(AVG_TIMER_WAIT/1000000000, 2) as tempo_medio_ms,
    ROUND(SUM_TIMER_WAIT/1000000000000, 2) as tempo_total_seg,
    SCHEMA_NAME as banco
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'chat_person'
ORDER BY COUNT_STAR DESC
LIMIT 20;

-- 3. Ver queries específicas (últimas 100)
SELECT 
    EVENT_NAME,
    SQL_TEXT,
    CURRENT_SCHEMA,
    TIMER_WAIT/1000000000 as tempo_ms
FROM performance_schema.events_statements_history
WHERE CURRENT_SCHEMA = 'chat_person'
ORDER BY TIMER_START DESC
LIMIT 100;

-- 4. Ver contadores de comandos
SHOW GLOBAL STATUS LIKE 'Com_%';

-- 5. Ver threads conectadas
SHOW GLOBAL STATUS LIKE 'Threads_%';

-- 6. Ver queries mais lentas agora
SELECT * FROM performance_schema.events_statements_current
WHERE SQL_TEXT IS NOT NULL
AND CURRENT_SCHEMA = 'chat_person'
ORDER BY TIMER_WAIT DESC
LIMIT 20;
