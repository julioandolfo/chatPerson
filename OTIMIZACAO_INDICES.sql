-- ═══════════════════════════════════════════════════════════════════════════════
-- SCRIPT DE OTIMIZAÇÃO - CRIAÇÃO DE ÍNDICES
-- Data: 2026-01-12
-- Objetivo: Otimizar queries pesadas identificadas no slow.log
-- ═══════════════════════════════════════════════════════════════════════════════

-- IMPORTANTE: Execute este script em horário de baixo movimento
-- Criar índices pode travar temporariamente as tabelas

-- ═══════════════════════════════════════════════════════════════════════════════
-- 1. ÍNDICES PARA QUERY #1 - Histórico do Contato (ContactController)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 1.1 Índice composto para messages (usado na subquery correlacionada)
-- Acelera: WHERE conversation_id = ? AND sender_type = ? AND created_at > ?
CREATE INDEX idx_messages_conv_sender_date 
ON messages(conversation_id, sender_type, created_at);

-- 1.2 Índice para conversations por contact_id
-- Acelera: WHERE c.contact_id = ?
CREATE INDEX idx_conversations_contact 
ON conversations(contact_id);

-- 1.3 Índice composto para otimizar JOIN entre conversations e messages
-- Acelera: JOIN messages ON conversation_id
CREATE INDEX idx_messages_conversation_id 
ON messages(conversation_id, created_at);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 2. ÍNDICES PARA QUERY #2 - Ranking de Agentes (AgentPerformanceService)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 2.1 Índice composto para conversations (filtro por agente, data e status)
-- Acelera: WHERE agent_id = ? AND created_at BETWEEN ? AND ? AND status IN (...)
CREATE INDEX idx_conversations_agent_date_status 
ON conversations(agent_id, created_at, status, resolved_at);

-- 2.2 Índice composto para messages (filtro por sender, tipo, data)
-- Acelera: WHERE sender_id = ? AND sender_type = ? AND created_at BETWEEN ? AND ?
CREATE INDEX idx_messages_sender_type_date 
ON messages(sender_id, sender_type, created_at, ai_agent_id);

-- 2.3 Índice para users (filtro por role e status)
-- Acelera: WHERE role IN (...) AND status = 'active'
CREATE INDEX idx_users_role_status 
ON users(role, status);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 3. ÍNDICES ADICIONAIS (OTIMIZAÇÕES GERAIS)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 3.1 Índice para conversations por status (usado em várias queries)
-- Acelera: WHERE status = ?
CREATE INDEX idx_conversations_status 
ON conversations(status);

-- 3.2 Índice composto para conversations (department + status)
-- Acelera: WHERE department_id = ? AND status = ?
CREATE INDEX idx_conversations_dept_status 
ON conversations(department_id, status);

-- 3.3 Índice para messages por ai_agent_id (filtrar mensagens de IA)
-- Acelera: WHERE ai_agent_id IS NULL ou WHERE ai_agent_id = ?
CREATE INDEX idx_messages_ai_agent 
ON messages(ai_agent_id);

-- 3.4 Índice para contacts por email (buscas rápidas)
-- Acelera: WHERE email = ?
CREATE INDEX idx_contacts_email 
ON contacts(email);

-- 3.5 Índice para contacts por phone (buscas rápidas)
-- Acelera: WHERE phone = ?
CREATE INDEX idx_contacts_phone 
ON contacts(phone);

-- 3.6 Índice composto para messages (otimizar contagem de mensagens por conversa)
-- Acelera: SELECT COUNT(*) FROM messages WHERE conversation_id = ?
CREATE INDEX idx_messages_conv_count 
ON messages(conversation_id, id);

-- 3.7 Índice para conversations por created_at (relatórios por período)
-- Acelera: WHERE created_at BETWEEN ? AND ?
CREATE INDEX idx_conversations_created 
ON conversations(created_at);

-- 3.8 Índice para messages por created_at (relatórios por período)
-- Acelera: WHERE created_at BETWEEN ? AND ?
CREATE INDEX idx_messages_created 
ON messages(created_at);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 4. ÍNDICES PARA DASHBOARD E ANALYTICS
-- ═══════════════════════════════════════════════════════════════════════════════

-- 4.1 Índice composto para funnel stats
-- Acelera: JOIN conversations ON funnel_id + filtros de data
CREATE INDEX idx_conversations_funnel_date 
ON conversations(funnel_id, created_at, status);

-- 4.2 Índice composto para agent departments (relacionamento N:N)
-- Acelera: JOIN agent_departments ON user_id
CREATE INDEX idx_agent_departments_user 
ON agent_departments(user_id, department_id);

-- 4.3 Índice composto para agent departments (reverso)
-- Acelera: JOIN agent_departments ON department_id
CREATE INDEX idx_agent_departments_dept 
ON agent_departments(department_id, user_id);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 5. ÍNDICES PARA WEBSOCKET E TEMPO REAL
-- ═══════════════════════════════════════════════════════════════════════════════

-- 5.1 Índice para conversations não atribuídas
-- Acelera: WHERE agent_id IS NULL AND status = 'open'
CREATE INDEX idx_conversations_unassigned 
ON conversations(agent_id, status);

-- 5.2 Índice para última mensagem de conversa
-- Acelera: SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1
CREATE INDEX idx_messages_last 
ON messages(conversation_id, created_at DESC);

-- 5.3 Índice para realtime_coaching_hints (getHintsByConversation)
-- Acelera: WHERE conversation_id = ? AND agent_id = ? ORDER BY created_at DESC
CREATE INDEX idx_coaching_hints_conv_agent 
ON realtime_coaching_hints(conversation_id, agent_id, created_at DESC);

-- ═══════════════════════════════════════════════════════════════════════════════
-- 6. VERIFICAÇÃO E ANÁLISE
-- ═══════════════════════════════════════════════════════════════════════════════

-- 6.1 Listar todos os índices criados
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    INDEX_TYPE,
    CARDINALITY
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('conversations', 'messages', 'users', 'contacts', 'agent_departments')
    AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- 6.2 Verificar tamanho dos índices
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    ROUND(SUM(stat_value * @@innodb_page_size) / 1024 / 1024, 2) AS size_mb
FROM mysql.innodb_index_stats
WHERE database_name = DATABASE()
    AND TABLE_NAME IN ('conversations', 'messages', 'users', 'contacts')
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY size_mb DESC;

-- 6.3 Analisar uso de índices (após algumas horas de produção)
-- EXPLAIN SELECT ... suas queries aqui para verificar se índices estão sendo usados

-- ═══════════════════════════════════════════════════════════════════════════════
-- 7. MANUTENÇÃO E OTIMIZAÇÃO
-- ═══════════════════════════════════════════════════════════════════════════════

-- 7.1 Otimizar tabelas após criar índices (OPCIONAL - pode demorar)
-- ANALYZE TABLE conversations;
-- ANALYZE TABLE messages;
-- ANALYZE TABLE users;
-- ANALYZE TABLE contacts;

-- 7.2 Rebuild de índices se necessário (OPCIONAL)
-- ALTER TABLE messages ENGINE=InnoDB;
-- ALTER TABLE conversations ENGINE=InnoDB;

-- ═══════════════════════════════════════════════════════════════════════════════
-- NOTAS IMPORTANTES
-- ═══════════════════════════════════════════════════════════════════════════════
--
-- ✅ ANTES DE EXECUTAR:
--    1. Fazer backup do banco de dados
--    2. Executar em horário de baixo movimento
--    3. Monitorar o processo
--
-- ✅ APÓS EXECUTAR:
--    1. Verificar slow.log (deve ter menos queries)
--    2. Monitorar CPU (deve estar mais baixa)
--    3. Testar performance no frontend
--
-- ✅ IMPACTO ESPERADO:
--    - Query #1: de 3s para ~0.5s (sem cache) ou 0.01s (com cache)
--    - Query #2: de 1s para ~0.3s (sem cache) ou 0.05s (com cache)
--    - CPU: redução de 60-70% para 20-30%
--    - Slow log: redução de 90%+
--
-- ✅ MANUTENÇÃO:
--    - Executar ANALYZE TABLE mensalmente
--    - Monitorar crescimento de índices
--    - Revisar índices não utilizados após 3 meses
--
-- ═══════════════════════════════════════════════════════════════════════════════

-- Fim do script
