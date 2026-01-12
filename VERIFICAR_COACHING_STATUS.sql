-- ═══════════════════════════════════════════════════════════════════════════════
-- VERIFICAR STATUS DO COACHING NO BANCO DE DADOS
-- ═══════════════════════════════════════════════════════════════════════════════

-- 1. Ver a configuração completa (JSON)
SELECT 
    id,
    `key`,
    value,
    created_at,
    updated_at
FROM settings 
WHERE `key` = 'conversation_settings';

-- ═══════════════════════════════════════════════════════════════════════════════

-- 2. Extrair apenas a parte de realtime_coaching (MySQL 5.7+)
SELECT 
    `key`,
    JSON_EXTRACT(value, '$.realtime_coaching.enabled') as coaching_enabled,
    JSON_EXTRACT(value, '$.realtime_coaching') as coaching_config
FROM settings 
WHERE `key` = 'conversation_settings';

-- ═══════════════════════════════════════════════════════════════════════════════

-- 3. Verificar se está habilitado (formato legível)
SELECT 
    CASE 
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = true THEN '✅ HABILITADO'
        WHEN JSON_EXTRACT(value, '$.realtime_coaching.enabled') = false THEN '❌ DESABILITADO'
        ELSE '⚠️ NÃO CONFIGURADO'
    END as status,
    JSON_EXTRACT(value, '$.realtime_coaching.enabled') as enabled_value
FROM settings 
WHERE `key` = 'conversation_settings';

-- ═══════════════════════════════════════════════════════════════════════════════
-- RESULTADO ESPERADO:
-- ═══════════════════════════════════════════════════════════════════════════════
-- Se HABILITADO:
-- | status          | enabled_value |
-- |-----------------|---------------|
-- | ✅ HABILITADO   | true          |
--
-- Se DESABILITADO:
-- | status          | enabled_value |
-- |-----------------|---------------|
-- | ❌ DESABILITADO | false         |
-- ═══════════════════════════════════════════════════════════════════════════════

-- 4. Ver todas as configurações de coaching (detalhado)
SELECT 
    JSON_EXTRACT(value, '$.realtime_coaching.enabled') as enabled,
    JSON_EXTRACT(value, '$.realtime_coaching.model') as model,
    JSON_EXTRACT(value, '$.realtime_coaching.temperature') as temperature,
    JSON_EXTRACT(value, '$.realtime_coaching.check_interval_seconds') as check_interval,
    JSON_EXTRACT(value, '$.realtime_coaching.max_cost_per_day') as max_cost_per_day,
    JSON_EXTRACT(value, '$.realtime_coaching.analyze_on_agent_message') as analyze_on_agent_message,
    JSON_EXTRACT(value, '$.realtime_coaching.analyze_on_customer_message') as analyze_on_customer_message
FROM settings 
WHERE `key` = 'conversation_settings';

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARA DESABILITAR VIA SQL (caso precise):
-- ═══════════════════════════════════════════════════════════════════════════════

/*
-- ⚠️ CUIDADO: Isso vai modificar o JSON
-- Faça backup antes!

UPDATE settings 
SET value = JSON_SET(
    value, 
    '$.realtime_coaching.enabled', 
    false
)
WHERE `key` = 'conversation_settings';

-- Verificar se mudou:
SELECT JSON_EXTRACT(value, '$.realtime_coaching.enabled') as coaching_enabled
FROM settings WHERE `key` = 'conversation_settings';
*/

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARA HABILITAR VIA SQL:
-- ═══════════════════════════════════════════════════════════════════════════════

/*
UPDATE settings 
SET value = JSON_SET(
    value, 
    '$.realtime_coaching.enabled', 
    true
)
WHERE `key` = 'conversation_settings';
*/

-- ═══════════════════════════════════════════════════════════════════════════════
-- OUTRAS VERIFICAÇÕES ÚTEIS:
-- ═══════════════════════════════════════════════════════════════════════════════

-- Verificar quantos hints existem no banco
SELECT 
    COUNT(*) as total_hints,
    COUNT(DISTINCT conversation_id) as total_conversations,
    COUNT(DISTINCT agent_id) as total_agents,
    MIN(created_at) as oldest_hint,
    MAX(created_at) as newest_hint
FROM realtime_coaching_hints;

-- Verificar hints por tipo
SELECT 
    hint_type,
    COUNT(*) as total
FROM realtime_coaching_hints
GROUP BY hint_type;

-- Verificar hints visualizados vs não visualizados
SELECT 
    CASE 
        WHEN viewed_at IS NULL THEN 'Não visualizado'
        ELSE 'Visualizado'
    END as status_visualizacao,
    COUNT(*) as total
FROM realtime_coaching_hints
GROUP BY CASE WHEN viewed_at IS NULL THEN 'Não visualizado' ELSE 'Visualizado' END;

-- Verificar feedback dos hints
SELECT 
    CASE 
        WHEN feedback IS NULL THEN 'Sem feedback'
        WHEN feedback = 'helpful' THEN '👍 Útil'
        WHEN feedback = 'not_helpful' THEN '👎 Não útil'
        ELSE feedback
    END as feedback_status,
    COUNT(*) as total
FROM realtime_coaching_hints
GROUP BY feedback;

-- Verificar últimos hints criados (se tiver hints recentes, coaching pode estar rodando)
SELECT 
    id,
    conversation_id,
    agent_id,
    hint_type,
    CASE 
        WHEN viewed_at IS NULL THEN '❌ Não visto'
        ELSE '✅ Visto'
    END as visualizado,
    feedback,
    created_at,
    TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_atras
FROM realtime_coaching_hints
ORDER BY created_at DESC
LIMIT 10;

-- ═══════════════════════════════════════════════════════════════════════════════
