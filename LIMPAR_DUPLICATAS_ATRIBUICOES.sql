-- ============================================================================
-- LIMPAR REGISTROS DUPLICADOS DE ATRIBUIÇÕES
-- ============================================================================
-- Remove registros duplicados em sequência (mesmo agente atribuído várias vezes
-- em poucos segundos) mantendo apenas o primeiro registro de cada sequência
-- ============================================================================

-- 1. Criar backup antes de deletar
CREATE TABLE IF NOT EXISTS conversation_assignments_backup_duplicatas 
SELECT * FROM conversation_assignments;

SELECT CONCAT('✅ Backup criado com ', COUNT(*), ' registros') as info 
FROM conversation_assignments_backup_duplicatas;

-- 2. Mostrar estatísticas ANTES da limpeza
SELECT '📊 ESTATÍSTICAS ANTES DA LIMPEZA:' as info;

SELECT 
    COUNT(*) as total_registros,
    COUNT(DISTINCT conversation_id) as conversas_unicas,
    COUNT(DISTINCT agent_id) as agentes_unicos
FROM conversation_assignments;

-- 3. Identificar duplicatas (mesmo conversation_id + agent_id em menos de 10 segundos)
SELECT '🔍 DUPLICATAS IDENTIFICADAS (menos de 10 segundos de diferença):' as info;

SELECT 
    ca1.conversation_id,
    ca1.agent_id,
    u.name as agente,
    COUNT(*) as quantidade_duplicatas,
    MIN(ca1.assigned_at) as primeira_atribuicao,
    MAX(ca1.assigned_at) as ultima_atribuicao,
    TIMESTAMPDIFF(SECOND, MIN(ca1.assigned_at), MAX(ca1.assigned_at)) as diferenca_segundos
FROM conversation_assignments ca1
INNER JOIN users u ON ca1.agent_id = u.id
GROUP BY ca1.conversation_id, ca1.agent_id
HAVING COUNT(*) > 1 
AND TIMESTAMPDIFF(SECOND, MIN(ca1.assigned_at), MAX(ca1.assigned_at)) < 60
ORDER BY quantidade_duplicatas DESC, conversation_id;

-- 4. Contar quantos registros serão deletados
SELECT '🗑️ REGISTROS A SEREM DELETADOS:' as info;

SELECT COUNT(*) as registros_a_deletar
FROM conversation_assignments ca1
WHERE EXISTS (
    SELECT 1 
    FROM conversation_assignments ca2 
    WHERE ca2.conversation_id = ca1.conversation_id
    AND ca2.agent_id = ca1.agent_id
    AND ca2.assigned_at < ca1.assigned_at
    AND TIMESTAMPDIFF(SECOND, ca2.assigned_at, ca1.assigned_at) < 60
);

-- 5. DELETAR DUPLICATAS (mantém apenas o primeiro registro de cada sequência)
-- Remove registros onde existe um registro anterior com mesmo conversation_id + agent_id
-- em menos de 60 segundos
DELETE ca1 FROM conversation_assignments ca1
WHERE EXISTS (
    SELECT 1 
    FROM conversation_assignments ca2 
    WHERE ca2.conversation_id = ca1.conversation_id
    AND ca2.agent_id = ca1.agent_id
    AND ca2.assigned_at < ca1.assigned_at
    AND TIMESTAMPDIFF(SECOND, ca2.assigned_at, ca1.assigned_at) < 60
);

SELECT CONCAT('✅ ', ROW_COUNT(), ' registros duplicados removidos') as info;

-- 6. Mostrar estatísticas DEPOIS da limpeza
SELECT '📊 ESTATÍSTICAS DEPOIS DA LIMPEZA:' as info;

SELECT 
    COUNT(*) as total_registros,
    COUNT(DISTINCT conversation_id) as conversas_unicas,
    COUNT(DISTINCT agent_id) as agentes_unicos
FROM conversation_assignments;

-- 7. Verificar registros por agente (deve estar limpo agora)
SELECT '📈 REGISTROS POR AGENTE (após limpeza):' as info;

SELECT 
    u.name as agente,
    COUNT(*) as total_atribuicoes,
    COUNT(DISTINCT ca.conversation_id) as conversas_unicas
FROM conversation_assignments ca
INNER JOIN users u ON ca.agent_id = u.id
GROUP BY ca.agent_id, u.name
ORDER BY total_atribuicoes DESC;

-- 8. Mostrar últimas 20 atribuições (não deve ter duplicatas consecutivas)
SELECT '🕐 ÚLTIMAS 20 ATRIBUIÇÕES:' as info;

SELECT 
    c.id as conversa_id,
    ct.name as contato,
    u.name as agente,
    u2.name as atribuido_por,
    ca.assigned_at as data,
    ca.removed_at
FROM conversation_assignments ca
INNER JOIN conversations c ON ca.conversation_id = c.id
LEFT JOIN contacts ct ON c.contact_id = ct.id
INNER JOIN users u ON ca.agent_id = u.id
LEFT JOIN users u2 ON ca.assigned_by = u2.id
ORDER BY ca.assigned_at DESC
LIMIT 20;

-- 9. Verificar se ainda há duplicatas
SELECT '✅ VERIFICAÇÃO FINAL - Duplicatas restantes:' as info;

SELECT 
    ca1.conversation_id,
    ca1.agent_id,
    u.name as agente,
    COUNT(*) as quantidade
FROM conversation_assignments ca1
INNER JOIN users u ON ca1.agent_id = u.id
GROUP BY ca1.conversation_id, ca1.agent_id
HAVING COUNT(*) > 1 
AND TIMESTAMPDIFF(SECOND, MIN(ca1.assigned_at), MAX(ca1.assigned_at)) < 60;

SELECT '✅ LIMPEZA CONCLUÍDA!' as info;
SELECT '💡 O código foi atualizado para prevenir novos registros duplicados' as info;
SELECT '⚠️  Se tudo estiver OK, pode deletar: conversation_assignments_backup_duplicatas' as info;
