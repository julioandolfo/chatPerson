-- ============================================================================
-- Script SQL para Debug Rápido de Conversa
-- ============================================================================
-- INSTRUÇÕES:
-- 1. Substitua [CONVERSATION_ID] pelo ID da conversa que quer investigar
-- 2. Execute cada bloco separadamente no seu cliente MySQL
-- ============================================================================

SET @conversation_id = [CONVERSATION_ID]; -- ← ALTERE AQUI!

-- ============================================================================
-- 1. INFORMAÇÕES BÁSICAS DA CONVERSA
-- ============================================================================
SELECT 
    '🔍 CONVERSA' as secao,
    c.id,
    c.status,
    c.channel,
    c.agent_id as agente_atual,
    u.name as nome_agente_atual,
    ct.name as nome_contato,
    ct.phone as telefone_contato,
    c.created_at as criada_em,
    c.updated_at as atualizada_em
FROM conversations c
LEFT JOIN users u ON c.agent_id = u.id
LEFT JOIN contacts ct ON c.contact_id = ct.id
WHERE c.id = @conversation_id;

-- ============================================================================
-- 2. AGENTES DO CONTATO (Quem deve ser atribuído automaticamente?)
-- ============================================================================
SELECT 
    '👥 AGENTES DO CONTATO' as secao,
    ca.id,
    ca.agent_id,
    u.name as nome_agente,
    ca.is_primary as eh_principal,
    ca.auto_assign_on_reopen as auto_atribuir_ao_reabrir,
    ca.priority as prioridade,
    ca.created_at as adicionado_em
FROM contact_agents ca
LEFT JOIN users u ON ca.agent_id = u.id
WHERE ca.contact_id = (SELECT contact_id FROM conversations WHERE id = @conversation_id)
ORDER BY ca.is_primary DESC, ca.priority DESC;

-- ============================================================================
-- 3. HISTÓRICO DE ATRIBUIÇÕES (Quem foi atribuído quando?)
-- ============================================================================
SELECT 
    '📊 HISTÓRICO DE ATRIBUIÇÕES' as secao,
    ca.id,
    ca.assigned_at as quando,
    ca.agent_id as para_agente,
    u.name as nome_agente,
    ca.assigned_by as por_quem,
    u2.name as nome_quem_atribuiu,
    CASE 
        WHEN ca.removed_at IS NOT NULL THEN '❌ REMOVIDO'
        ELSE '✅ ATIVO'
    END as status,
    ca.removed_at as removido_em,
    -- 🔍 ANÁLISE
    CASE 
        WHEN ca.agent_id = ca.assigned_by THEN '⚠️ AUTO-ATRIBUIÇÃO'
        ELSE ''
    END as analise
FROM conversation_assignments ca
LEFT JOIN users u ON ca.agent_id = u.id
LEFT JOIN users u2 ON ca.assigned_by = u2.id
WHERE ca.conversation_id = @conversation_id
ORDER BY ca.assigned_at ASC;

-- ============================================================================
-- 4. MENSAGENS (Quem enviou mensagens quando?)
-- ============================================================================
SELECT 
    '💬 MENSAGENS' as secao,
    m.id,
    m.created_at as quando,
    m.sender_type as tipo_remetente,
    m.sender_id as id_remetente,
    CASE 
        WHEN m.sender_type = 'contact' THEN ct.name
        WHEN m.sender_type = 'agent' THEN u.name
        ELSE 'Sistema'
    END as nome_remetente,
    m.message_type as tipo_mensagem,
    LEFT(m.content, 60) as conteudo_preview
FROM messages m
LEFT JOIN users u ON m.sender_id = u.id AND m.sender_type = 'agent'
LEFT JOIN contacts ct ON m.conversation_id IN (SELECT id FROM conversations WHERE contact_id = ct.id)
WHERE m.conversation_id = @conversation_id
ORDER BY m.created_at ASC;

-- ============================================================================
-- 5. TIMELINE COMPLETO (Mensagens + Atribuições intercalados)
-- ============================================================================
SELECT * FROM (
    -- Mensagens
    SELECT 
        m.created_at as quando,
        '💬 MENSAGEM' as evento,
        CONCAT(
            m.sender_type, 
            ' #', m.sender_id,
            CASE 
                WHEN m.sender_type = 'agent' THEN CONCAT(' (', u.name, ')')
                ELSE ''
            END,
            CASE 
                WHEN m.message_type = 'note' THEN ' [NOTA]'
                ELSE ''
            END
        ) as detalhes,
        LEFT(m.content, 40) as conteudo
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = @conversation_id
    
    UNION ALL
    
    -- Atribuições
    SELECT 
        ca.assigned_at as quando,
        CASE 
            WHEN ca.removed_at IS NULL THEN '✅ ATRIBUIÇÃO'
            ELSE '❌ REMOÇÃO'
        END as evento,
        CONCAT(
            'Agente #', ca.agent_id, ' (', u.name, ')',
            ' por ', 
            CASE 
                WHEN ca.assigned_by > 0 THEN CONCAT('#', ca.assigned_by, ' - ', u2.name)
                ELSE 'Sistema'
            END
        ) as detalhes,
        CASE 
            WHEN ca.agent_id = ca.assigned_by THEN '⚠️ AUTO-ATRIBUIÇÃO'
            ELSE ''
        END as conteudo
    FROM conversation_assignments ca
    LEFT JOIN users u ON ca.agent_id = u.id
    LEFT JOIN users u2 ON ca.assigned_by = u2.id
    WHERE ca.conversation_id = @conversation_id
) as timeline
ORDER BY quando ASC;

-- ============================================================================
-- 6. ANÁLISE: Reatribuições Desnecessárias (mesmo agente)
-- ============================================================================
SELECT 
    '🔴 REATRIBUIÇÕES DESNECESSÁRIAS' as analise,
    ca1.assigned_at as primeira_atribuicao,
    ca2.assigned_at as segunda_atribuicao,
    TIMESTAMPDIFF(SECOND, ca1.assigned_at, ca2.assigned_at) as segundos_entre,
    ca1.agent_id as agente,
    u.name as nome_agente,
    ca1.assigned_by as atribuido_por_1,
    ca2.assigned_by as atribuido_por_2,
    '⚠️ MESMO AGENTE ATRIBUÍDO 2X' as problema
FROM conversation_assignments ca1
INNER JOIN conversation_assignments ca2 
    ON ca1.conversation_id = ca2.conversation_id 
    AND ca2.assigned_at > ca1.assigned_at
    AND ca1.agent_id = ca2.agent_id
LEFT JOIN users u ON ca1.agent_id = u.id
WHERE ca1.conversation_id = @conversation_id
ORDER BY ca1.assigned_at ASC;

-- ============================================================================
-- 7. ANÁLISE: Auto-atribuições após envio de mensagem
-- ============================================================================
SELECT 
    '🔴 AUTO-ATRIBUIÇÃO APÓS MENSAGEM' as analise,
    m.created_at as mensagem_enviada,
    ca.assigned_at as atribuicao_feita,
    TIMESTAMPDIFF(SECOND, m.created_at, ca.assigned_at) as segundos_diferenca,
    m.sender_id as quem_enviou_msg,
    ca.agent_id as quem_foi_atribuido,
    u.name as nome_agente,
    LEFT(m.content, 60) as conteudo_mensagem,
    '⚠️ ATRIBUIÇÃO LOGO APÓS ENVIAR MENSAGEM' as problema
FROM messages m
INNER JOIN conversation_assignments ca
    ON ca.conversation_id = m.conversation_id
    AND ca.agent_id = m.sender_id
    AND ca.assigned_at >= m.created_at
    AND ca.assigned_at <= DATE_ADD(m.created_at, INTERVAL 3 SECOND)
LEFT JOIN users u ON ca.agent_id = u.id
WHERE m.conversation_id = @conversation_id
    AND m.sender_type = 'agent'
ORDER BY m.created_at ASC;

-- ============================================================================
-- 8. PARTICIPANTES DA CONVERSA
-- ============================================================================
SELECT 
    '👥 PARTICIPANTES' as secao,
    cp.user_id as agente_id,
    u.name as nome_agente,
    cp.added_at as adicionado_em,
    cp.added_by as adicionado_por,
    u2.name as nome_quem_adicionou,
    CASE 
        WHEN cp.removed_at IS NULL THEN '✅ ATIVO'
        ELSE '❌ REMOVIDO'
    END as status,
    cp.removed_at as removido_em
FROM conversation_participants cp
LEFT JOIN users u ON cp.user_id = u.id
LEFT JOIN users u2 ON cp.added_by = u2.id
WHERE cp.conversation_id = @conversation_id
ORDER BY cp.added_at ASC;

-- ============================================================================
-- FIM DO DEBUG
-- ============================================================================
