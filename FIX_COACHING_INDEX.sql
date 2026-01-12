-- ═══════════════════════════════════════════════════════════════════════════════
-- FIX URGENTE: Índice para Coaching Hints
-- Data: 2026-01-12
-- Problema: getHintsByConversation() estava travando o sistema
-- ═══════════════════════════════════════════════════════════════════════════════

-- Criar índice para realtime_coaching_hints
-- Acelera: WHERE conversation_id = ? AND agent_id = ? ORDER BY created_at DESC
CREATE INDEX idx_coaching_hints_conv_agent 
ON realtime_coaching_hints(conversation_id, agent_id, created_at DESC);

-- Verificar se foi criado
SELECT 
    'Índice criado com sucesso!' as status,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'realtime_coaching_hints'
    AND INDEX_NAME = 'idx_coaching_hints_conv_agent';

-- ═══════════════════════════════════════════════════════════════════════════════
-- RESULTADO ESPERADO:
-- ═══════════════════════════════════════════════════════════════════════════════
-- | status                        | INDEX_NAME                     | COLUMN_NAME     | SEQ_IN_INDEX |
-- |-------------------------------|--------------------------------|-----------------|--------------|
-- | Índice criado com sucesso!    | idx_coaching_hints_conv_agent  | conversation_id | 1            |
-- | Índice criado com sucesso!    | idx_coaching_hints_conv_agent  | agent_id        | 2            |
-- | Índice criado com sucesso!    | idx_coaching_hints_conv_agent  | created_at      | 3            |
-- ═══════════════════════════════════════════════════════════════════════════════
