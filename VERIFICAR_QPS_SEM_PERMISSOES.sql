-- 🔍 INVESTIGAR QPS ALTO - SEM PERFORMANCE SCHEMA
-- Para usuários sem permissão em performance_schema

-- ========================================
-- 1. Ver processos ativos (PROCESSLIST)
-- ========================================
SHOW FULL PROCESSLIST;

-- ========================================
-- 2. Contar conexões ativas
-- ========================================
SELECT 
    COUNT(*) as total_conexoes,
    SUM(CASE WHEN Command = 'Query' THEN 1 ELSE 0 END) as queries_ativas,
    SUM(CASE WHEN Command = 'Sleep' THEN 1 ELSE 0 END) as conexoes_idle
FROM information_schema.PROCESSLIST;

-- ========================================
-- 3. Ver comandos executados (totais)
-- ========================================
SHOW GLOBAL STATUS LIKE 'Com_select';
SHOW GLOBAL STATUS LIKE 'Com_insert';
SHOW GLOBAL STATUS LIKE 'Com_update';
SHOW GLOBAL STATUS LIKE 'Com_delete';

-- Anote esses valores, aguarde 10 segundos, execute novamente
-- Calcule: (valor_novo - valor_antigo) / 10 = queries/segundo

-- ========================================
-- 4. Ver total de queries
-- ========================================
SHOW GLOBAL STATUS LIKE 'Questions';
-- Este é o valor que você já está medindo

SHOW GLOBAL STATUS LIKE 'Queries';
-- Similar ao Questions, mas inclui comandos internos

-- ========================================
-- 5. Ver threads conectadas
-- ========================================
SHOW GLOBAL STATUS LIKE 'Threads_%';

-- ========================================
-- 6. Ver cache de queries do MySQL
-- ========================================
SHOW GLOBAL STATUS LIKE 'Qcache%';

-- ========================================
-- 7. Ver uso de tabelas temporárias
-- ========================================
SHOW GLOBAL STATUS LIKE 'Created_tmp%';

-- ========================================
-- 8. Ver slow queries
-- ========================================
SHOW GLOBAL STATUS LIKE 'Slow_queries';

-- ========================================
-- 9. Ver conexões abortadas
-- ========================================
SHOW GLOBAL STATUS LIKE 'Aborted_%';

-- ========================================
-- 10. Ver uptime (para calcular médias)
-- ========================================
SHOW GLOBAL STATUS LIKE 'Uptime';

-- ========================================
-- INTERPRETAÇÃO:
-- ========================================
-- 
-- QPS = Questions / Uptime
-- Se QPS > 1000, algo está muito errado
--
-- Se Threads_connected > 50, muitas conexões abertas
--
-- Se Com_select aumenta muito rápido (> 5000/10s), problema de leitura
-- Se Com_insert aumenta muito rápido (> 1000/10s), problema de escrita
