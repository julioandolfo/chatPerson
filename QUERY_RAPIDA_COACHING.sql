-- ═══════════════════════════════════════════════════════════════════════════════
-- QUERY RÁPIDA - Verificar Status do Coaching (SEM ERROS)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 1️⃣ COACHING ESTÁ HABILITADO OU DESABILITADO?
SELECT 
    CASE 
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = true THEN '✅ HABILITADO'
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = false THEN '❌ DESABILITADO'
        ELSE '⚠️ NÃO CONFIGURADO'
    END as status_coaching
FROM settings 
WHERE `key` = 'conversation_settings';

-- ═══════════════════════════════════════════════════════════════════════════════

-- 2️⃣ HÁ HINTS SENDO CRIADOS RECENTEMENTE? (últimos 30 minutos)
SELECT 
    COUNT(*) as hints_ultimos_30min,
    MAX(created_at) as ultimo_hint,
    CASE 
        WHEN MAX(created_at) IS NULL THEN '✅ Nenhum hint'
        WHEN MAX(created_at) > DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN '🔴 AINDA CRIANDO HINTS!'
        ELSE '✅ Não há hints recentes'
    END as analise
FROM realtime_coaching_hints
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE);

-- ═══════════════════════════════════════════════════════════════════════════════

-- 3️⃣ HÁ ITENS PENDENTES NA FILA?
SELECT 
    COUNT(*) as total_fila,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendentes,
    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processando,
    CASE 
        WHEN COUNT(CASE WHEN status IN ('pending','processing') THEN 1 END) > 0 
        THEN '🔴 WORKER ESTÁ PROCESSANDO!'
        ELSE '✅ Fila limpa'
    END as analise
FROM coaching_queue;

-- ═══════════════════════════════════════════════════════════════════════════════

-- 4️⃣ RESUMO FINAL (COPIE APENAS ESTA SE QUISER ALGO RÁPIDO)
SELECT 
    -- Status da configuração
    (SELECT 
        CASE 
            WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = true THEN '✅ HABILITADO'
            WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = false THEN '❌ DESABILITADO'
            ELSE '⚠️ NÃO CONFIGURADO'
        END
    FROM settings WHERE `key` = 'conversation_settings') as config_status,
    
    -- Hints recentes
    (SELECT COUNT(*) 
     FROM realtime_coaching_hints 
     WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) as hints_ultimos_30min,
    
    -- Fila pendente
    (SELECT COUNT(*) 
     FROM coaching_queue 
     WHERE status IN ('pending','processing')) as itens_fila_ativa,
    
    -- Análise final
    CASE 
        WHEN (SELECT JSON_EXTRACT(value, '$.realtime_coaching.enabled') FROM settings WHERE `key` = 'conversation_settings') = false
         AND (SELECT COUNT(*) FROM realtime_coaching_hints WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) = 0
         AND (SELECT COUNT(*) FROM coaching_queue WHERE status IN ('pending','processing')) = 0
        THEN '✅ COACHING REALMENTE DESABILITADO'
        
        WHEN (SELECT JSON_EXTRACT(value, '$.realtime_coaching.enabled') FROM settings WHERE `key` = 'conversation_settings') = false
         AND ((SELECT COUNT(*) FROM realtime_coaching_hints WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) > 0
              OR (SELECT COUNT(*) FROM coaching_queue WHERE status IN ('pending','processing')) > 0)
        THEN '🔴 DESABILITADO MAS AINDA RODANDO!'
        
        WHEN (SELECT JSON_EXTRACT(value, '$.realtime_coaching.enabled') FROM settings WHERE `key` = 'conversation_settings') = true
        THEN '✅ HABILITADO E FUNCIONANDO'
        
        ELSE '⚠️ STATUS DESCONHECIDO'
    END as diagnostico;

-- ═══════════════════════════════════════════════════════════════════════════════
-- INTERPRETAÇÃO:
-- ═══════════════════════════════════════════════════════════════════════════════
--
-- ✅ COACHING REALMENTE DESABILITADO:
--    config_status = '❌ DESABILITADO'
--    hints_ultimos_30min = 0
--    itens_fila_ativa = 0
--    diagnostico = '✅ COACHING REALMENTE DESABILITADO'
--
-- 🔴 DESABILITADO MAS AINDA RODANDO (PROBLEMA):
--    config_status = '❌ DESABILITADO'
--    hints_ultimos_30min > 0  OU  itens_fila_ativa > 0
--    diagnostico = '🔴 DESABILITADO MAS AINDA RODANDO!'
--
--    SOLUÇÃO:
--    1. Parar worker: touch storage/coaching-worker-stop.txt
--    2. Limpar fila: DELETE FROM coaching_queue WHERE status IN ('pending','processing');
--    3. Limpar cache: rm -rf storage/cache/queries/*
--
-- ✅ HABILITADO E FUNCIONANDO:
--    config_status = '✅ HABILITADO'
--    diagnostico = '✅ HABILITADO E FUNCIONANDO'
--
-- ═══════════════════════════════════════════════════════════════════════════════
