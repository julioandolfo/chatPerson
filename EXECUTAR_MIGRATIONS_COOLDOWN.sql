-- ==============================================
-- MIGRATIONS PARA SISTEMA DE COOLDOWN
-- Execute este arquivo no MySQL do Laragon
-- ==============================================

USE chat_person;

-- Migration 1: Adicionar campos de cooldown aos agentes (se não existirem)
SET @sql1 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'chat_person' 
     AND TABLE_NAME = 'ai_kanban_agents' 
     AND COLUMN_NAME = 'cooldown_hours') = 0,
    'ALTER TABLE ai_kanban_agents ADD COLUMN cooldown_hours INT DEFAULT 24 COMMENT "Horas de cooldown entre execuções na mesma conversa"',
    'SELECT "Campo cooldown_hours já existe" as info'
);
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'chat_person' 
     AND TABLE_NAME = 'ai_kanban_agents' 
     AND COLUMN_NAME = 'allow_reexecution_on_change') = 0,
    'ALTER TABLE ai_kanban_agents ADD COLUMN allow_reexecution_on_change BOOLEAN DEFAULT 1 COMMENT "Permitir re-execução se houver mudanças significativas"',
    'SELECT "Campo allow_reexecution_on_change já existe" as info'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Migration 2: Adicionar snapshot aos logs de ação (se não existir)
SET @sql3 = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'chat_person' 
     AND TABLE_NAME = 'ai_kanban_agent_actions_log' 
     AND COLUMN_NAME = 'conversation_snapshot') = 0,
    'ALTER TABLE ai_kanban_agent_actions_log ADD COLUMN conversation_snapshot JSON DEFAULT NULL COMMENT "Estado da conversa no momento da execução"',
    'SELECT "Campo conversation_snapshot já existe" as info'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- Verificar se os campos foram criados
SELECT 
    'ai_kanban_agents' as tabela,
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    IS_NULLABLE as nulo,
    COLUMN_DEFAULT as padrao
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'chat_person' 
  AND TABLE_NAME = 'ai_kanban_agents'
  AND COLUMN_NAME IN ('cooldown_hours', 'allow_reexecution_on_change')

UNION ALL

SELECT 
    'ai_kanban_agent_actions_log' as tabela,
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    IS_NULLABLE as nulo,
    COLUMN_DEFAULT as padrao
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'chat_person' 
  AND TABLE_NAME = 'ai_kanban_agent_actions_log'
  AND COLUMN_NAME = 'conversation_snapshot';

-- ==============================================
-- ✅ MIGRATIONS EXECUTADAS COM SUCESSO!
-- ==============================================
