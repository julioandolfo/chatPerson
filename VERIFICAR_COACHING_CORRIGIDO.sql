-- ═══════════════════════════════════════════════════════════════════════════════
-- VERIFICAR STATUS DO COACHING NO BANCO DE DADOS (CORRIGIDO)
-- Sem erros de colunas inexistentes
-- ═══════════════════════════════════════════════════════════════════════════════

-- ════════════════════════════════════════════════════════════════════════════════
-- 1. VERIFICAR SE COACHING ESTÁ HABILITADO OU DESABILITADO
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    CASE 
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = true THEN '✅ HABILITADO'
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = false THEN '❌ DESABILITADO'
        ELSE '⚠️ NÃO CONFIGURADO'
    END as status_coaching
FROM settings 
WHERE `key` = 'conversation_settings';

-- ════════════════════════════════════════════════════════════════════════════════
-- 2. VER CONFIGURAÇÃO COMPLETA DE COACHING
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    JSON_EXTRACT(value, '$.realtime_coaching.enabled') as enabled,
    JSON_EXTRACT(value, '$.realtime_coaching.model') as model,
    JSON_EXTRACT(value, '$.realtime_coaching.temperature') as temperature,
    JSON_EXTRACT(value, '$.realtime_coaching.check_interval_seconds') as check_interval,
    JSON_EXTRACT(value, '$.realtime_coaching.max_cost_per_day') as max_cost_per_day
FROM settings 
WHERE `key` = 'conversation_settings';

-- ════════════════════════════════════════════════════════════════════════════════
-- 3. VERIFICAR SE HÁ HINTS RECENTES (últimos 30 minutos)
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    COUNT(*) as hints_ultimos_30min,
    MAX(created_at) as ultimo_hint_criado,
    TIMESTAMPDIFF(MINUTE, MAX(created_at), NOW()) as minutos_desde_ultimo
FROM realtime_coaching_hints
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE);

-- ⚠️ Se hints_ultimos_30min > 0, coaching ainda está criando hints!

-- ════════════════════════════════════════════════════════════════════════════════
-- 4. ESTATÍSTICAS GERAIS DE HINTS
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    COUNT(*) as total_hints,
    COUNT(DISTINCT conversation_id) as total_conversations,
    COUNT(DISTINCT agent_id) as total_agents,
    MIN(created_at) as hint_mais_antigo,
    MAX(created_at) as hint_mais_recente,
    COUNT(CASE WHEN viewed_at IS NOT NULL THEN 1 END) as hints_visualizados,
    COUNT(CASE WHEN viewed_at IS NULL THEN 1 END) as hints_nao_visualizados,
    COUNT(CASE WHEN feedback = 'helpful' THEN 1 END) as feedback_positivo,
    COUNT(CASE WHEN feedback = 'not_helpful' THEN 1 END) as feedback_negativo
FROM realtime_coaching_hints;

-- ════════════════════════════════════════════════════════════════════════════════
-- 5. HINTS POR TIPO
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    hint_type,
    COUNT(*) as quantidade,
    COUNT(CASE WHEN viewed_at IS NOT NULL THEN 1 END) as visualizados,
    COUNT(CASE WHEN viewed_at IS NULL THEN 1 END) as nao_visualizados
FROM realtime_coaching_hints
GROUP BY hint_type
ORDER BY quantidade DESC;

-- ════════════════════════════════════════════════════════════════════════════════
-- 6. ÚLTIMOS 10 HINTS CRIADOS
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    id,
    conversation_id,
    agent_id,
    hint_type,
    SUBSTRING(hint_text, 1, 50) as preview_hint,
    CASE 
        WHEN viewed_at IS NULL THEN '❌ Não visto'
        ELSE CONCAT('✅ Visto em ', DATE_FORMAT(viewed_at, '%d/%m %H:%i'))
    END as visualizado,
    CASE 
        WHEN feedback IS NULL THEN '-'
        WHEN feedback = 'helpful' THEN '👍 Útil'
        WHEN feedback = 'not_helpful' THEN '👎 Não útil'
    END as feedback_status,
    created_at,
    CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), ' min atrás') as tempo_criacao
FROM realtime_coaching_hints
ORDER BY created_at DESC
LIMIT 10;

-- ════════════════════════════════════════════════════════════════════════════════
-- 7. HINTS CRIADOS NAS ÚLTIMAS HORAS (para detectar se está rodando)
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hora,
    COUNT(*) as hints_criados
FROM realtime_coaching_hints
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00')
ORDER BY hora DESC;

-- ⚠️ Se houver hints nas últimas horas, coaching está rodando mesmo desabilitado!

-- ════════════════════════════════════════════════════════════════════════════════
-- 8. CUSTO TOTAL DE COACHING (tokens e $)
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    SUM(tokens_used) as total_tokens,
    SUM(cost) as custo_total_usd,
    AVG(tokens_used) as media_tokens_por_hint,
    AVG(cost) as custo_medio_por_hint,
    COUNT(*) as total_hints
FROM realtime_coaching_hints;

-- ════════════════════════════════════════════════════════════════════════════════
-- 9. CUSTO POR DIA (últimos 7 dias)
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    DATE(created_at) as data,
    COUNT(*) as hints_criados,
    SUM(tokens_used) as tokens,
    SUM(cost) as custo_usd
FROM realtime_coaching_hints
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY data DESC;

-- ════════════════════════════════════════════════════════════════════════════════
-- 10. VERIFICAR SE HÁ ITENS NA FILA (coaching_queue)
-- ════════════════════════════════════════════════════════════════════════════════

SELECT 
    COUNT(*) as itens_na_fila,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pendentes,
    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processando,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completados,
    COUNT(CASE WHEN status = 'failed' THEN 1 END) as falhados,
    MIN(added_at) as item_mais_antigo,
    MAX(added_at) as item_mais_recente,
    SUM(attempts) as total_tentativas
FROM coaching_queue;

-- ⚠️ Se houver itens 'pending' ou 'processing', o worker está processando!

-- ════════════════════════════════════════════════════════════════════════════════
-- 🔧 COMANDOS PARA DESABILITAR DEFINITIVAMENTE
-- ════════════════════════════════════════════════════════════════════════════════

/*
-- ⚠️ ATENÇÃO: Execute apenas se quiser DESABILITAR coaching

-- 1. Desabilitar nas configurações
UPDATE settings 
SET value = JSON_SET(value, '$.realtime_coaching.enabled', false)
WHERE `key` = 'conversation_settings';

-- 2. Limpar fila de processamento
DELETE FROM coaching_queue WHERE status IN ('pending', 'processing');

-- 3. (Opcional) Limpar hints antigos (mais de 30 dias)
DELETE FROM realtime_coaching_hints 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- 4. Verificar se foi desabilitado
SELECT 
    JSON_EXTRACT(value, '$.realtime_coaching.enabled') as coaching_enabled
FROM settings 
WHERE `key` = 'conversation_settings';
-- Deve retornar: false

-- 5. Limpar cache do servidor (executar no terminal)
-- rm -rf storage/cache/queries/*
*/

-- ════════════════════════════════════════════════════════════════════════════════
-- INTERPRETAÇÃO DOS RESULTADOS
-- ════════════════════════════════════════════════════════════════════════════════

/*
✅ COACHING REALMENTE DESABILITADO:
   - Query 1: status_coaching = '❌ DESABILITADO'
   - Query 3: hints_ultimos_30min = 0
   - Query 7: Nenhum hint nas últimas horas
   - Query 10: itens_na_fila = 0 ou apenas 'completed'

⚠️ COACHING AINDA RODANDO (mesmo "desabilitado"):
   - Query 1: status_coaching = '❌ DESABILITADO'
   - Query 3: hints_ultimos_30min > 0  ← PROBLEMA!
   - Query 7: Hints sendo criados nas últimas horas
   - Query 10: itens 'pending' ou 'processing' na fila

🔧 AÇÕES:
   - Se status = DESABILITADO mas ainda cria hints:
     1. Parar o worker: touch storage/coaching-worker-stop.txt
     2. Limpar fila: DELETE FROM coaching_queue WHERE status IN ('pending','processing')
     3. Limpar cache: rm -rf storage/cache/queries/*
     4. Recarregar página com Ctrl+Shift+R
*/

-- ════════════════════════════════════════════════════════════════════════════════
