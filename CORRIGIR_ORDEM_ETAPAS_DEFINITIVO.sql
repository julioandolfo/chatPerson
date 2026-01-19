-- ============================================================================
-- CORREÇÃO DEFINITIVA: Ordem das Etapas do Kanban
-- ============================================================================
-- Problema: Etapas com stage_order NULL fazem o sistema reordenar tudo
--          automaticamente toda vez que alguém move uma etapa
-- ============================================================================

-- 1. Verificar etapas problemáticas
SELECT '🔍 ETAPAS COM PROBLEMAS:' as info;

SELECT 
    fs.id,
    f.name as funnel_name,
    fs.name as stage_name,
    fs.stage_order,
    fs.position,
    fs.is_system_stage,
    fs.system_stage_type
FROM funnel_stages fs
INNER JOIN funnels f ON fs.funnel_id = f.id
WHERE fs.stage_order IS NULL 
   OR fs.stage_order = 0
   OR fs.stage_order = ''
ORDER BY f.id, fs.id;

-- 2. Contar problemas
SELECT '📊 TOTAL DE PROBLEMAS:' as info;

SELECT 
    COUNT(*) as total_etapas_com_problema
FROM funnel_stages
WHERE stage_order IS NULL 
   OR stage_order = 0
   OR stage_order = '';

-- 3. CORRIGIR: Definir stage_order para TODAS as etapas
-- Agrupa por funil e define ordem respeitando prioridade
SELECT '🔧 APLICANDO CORREÇÃO:' as info;

-- Remover tabela temporária se já existir
DROP TEMPORARY TABLE IF EXISTS temp_stage_orders;

-- Criar tabela temporária com a ordem correta
CREATE TEMPORARY TABLE temp_stage_orders AS
SELECT 
    fs.id,
    fs.funnel_id,
    @row_num := IF(@funnel_id = fs.funnel_id, @row_num + 1, 1) as new_order,
    @funnel_id := fs.funnel_id as current_funnel
FROM funnel_stages fs
CROSS JOIN (SELECT @row_num := 0, @funnel_id := NULL) AS vars
ORDER BY 
    fs.funnel_id,
    -- Prioridade: Entrada primeiro, etapas normais no meio, sistema no final
    CASE 
        WHEN fs.system_stage_type = 'entrada' THEN 1
        WHEN fs.is_system_stage = 0 OR fs.is_system_stage IS NULL THEN 2
        WHEN fs.system_stage_type = 'fechadas' THEN 3
        WHEN fs.system_stage_type = 'perdidas' THEN 4
        ELSE 5
    END,
    -- Dentro de cada grupo, ordenar por stage_order existente (se houver)
    COALESCE(fs.stage_order, 999999),
    COALESCE(fs.position, 999999),
    fs.id;

-- Aplicar atualização
UPDATE funnel_stages fs
INNER JOIN temp_stage_orders tso ON fs.id = tso.id
SET 
    fs.stage_order = tso.new_order,
    fs.position = tso.new_order;

SELECT CONCAT('✅ ', ROW_COUNT(), ' etapas atualizadas') as info;

-- 4. Verificar resultado
SELECT '📊 RESULTADO FINAL:' as info;

SELECT 
    f.name as funnel_name,
    fs.name as stage_name,
    fs.stage_order,
    fs.position,
    CASE 
        WHEN fs.system_stage_type = 'entrada' THEN '🛡️ Entrada (Sistema)'
        WHEN fs.system_stage_type = 'fechadas' THEN '🛡️ Fechadas (Sistema)'
        WHEN fs.system_stage_type = 'perdidas' THEN '🛡️ Perdidas (Sistema)'
        ELSE '📝 Personalizada'
    END as tipo
FROM funnel_stages fs
INNER JOIN funnels f ON fs.funnel_id = f.id
WHERE f.status = 'active'
ORDER BY f.id, fs.stage_order;

-- 5. Verificar se ainda há problemas
SELECT '✅ VERIFICAÇÃO FINAL:' as info;

SELECT 
    COUNT(*) as total_ainda_com_problema
FROM funnel_stages
WHERE stage_order IS NULL 
   OR stage_order = 0
   OR stage_order = '';

-- 6. Estatísticas por funil
SELECT '📈 ESTATÍSTICAS POR FUNIL:' as info;

SELECT 
    f.name as funnel_name,
    COUNT(*) as total_stages,
    MIN(fs.stage_order) as primeira_ordem,
    MAX(fs.stage_order) as ultima_ordem,
    COUNT(DISTINCT fs.stage_order) as ordens_unicas
FROM funnel_stages fs
INNER JOIN funnels f ON fs.funnel_id = f.id
WHERE f.status = 'active'
GROUP BY f.id, f.name
ORDER BY f.id;

-- 7. Detectar duplicatas
SELECT '⚠️  VERIFICAR DUPLICATAS:' as info;

SELECT 
    fs.funnel_id,
    f.name as funnel_name,
    fs.stage_order,
    COUNT(*) as qtd_etapas_com_mesma_ordem,
    GROUP_CONCAT(fs.name SEPARATOR ', ') as etapas
FROM funnel_stages fs
INNER JOIN funnels f ON fs.funnel_id = f.id
GROUP BY fs.funnel_id, fs.stage_order, f.name
HAVING COUNT(*) > 1
ORDER BY fs.funnel_id, fs.stage_order;

-- Limpar tabela temporária
DROP TEMPORARY TABLE IF EXISTS temp_stage_orders;

SELECT '✅ CORREÇÃO CONCLUÍDA!' as info;
SELECT '💡 Agora a ordem das etapas não deve mais mudar sozinha' as info;
SELECT '⚠️  IMPORTANTE: Limpe o cache do navegador (Ctrl+Shift+Del)' as info;
