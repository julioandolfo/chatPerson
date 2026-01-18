-- ============================================================================
-- FIX: Corrigir estrutura da tabela conversation_assignments
-- ============================================================================
-- Problema: Tabela pode estar com estrutura antiga (from_agent_id, to_agent_id)
--          ou com registros NULL que causam "Desconhecido" no histórico
-- ============================================================================

-- 1. Verificar estrutura atual
SELECT 'Estrutura atual da tabela:' as info;
DESCRIBE conversation_assignments;

-- 2. Backup da tabela (se existir dados)
CREATE TABLE IF NOT EXISTS conversation_assignments_backup_20260118 
SELECT * FROM conversation_assignments;

SELECT CONCAT('✅ Backup criado com ', COUNT(*), ' registros') as info 
FROM conversation_assignments_backup_20260118;

-- 3. Dropar tabela antiga
DROP TABLE IF EXISTS conversation_assignments;

-- 4. Criar tabela com estrutura correta
CREATE TABLE conversation_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    agent_id INT NULL COMMENT 'ID do agente atribuído (NULL = conversa não atribuída)',
    assigned_by INT NULL COMMENT 'ID do usuário que fez a atribuição (NULL = sistema/automação)',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL COMMENT 'Quando o agente foi removido da conversa',
    
    INDEX idx_conversation_agent (conversation_id, agent_id),
    INDEX idx_agent_date (agent_id, assigned_at),
    INDEX idx_conversation_date (conversation_id, assigned_at),
    
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ Tabela conversation_assignments criada com estrutura correta' as info;

-- 5. Popular com dados do backup (se houver)
-- IMPORTANTE: Só inserir registros onde agent_id NÃO é NULL
INSERT INTO conversation_assignments (id, conversation_id, agent_id, assigned_by, assigned_at, removed_at)
SELECT 
    id, 
    conversation_id, 
    agent_id, 
    assigned_by, 
    assigned_at,
    NULL as removed_at
FROM conversation_assignments_backup_20260118
WHERE agent_id IS NOT NULL;  -- ✅ Filtrar registros NULL

SELECT CONCAT('✅ Restaurados ', ROW_COUNT(), ' registros válidos do backup') as info;

-- 6. Popular com conversas atuais que têm agente (se ainda não estiverem)
INSERT IGNORE INTO conversation_assignments (conversation_id, agent_id, assigned_at)
SELECT 
    c.id as conversation_id,
    c.agent_id,
    COALESCE(c.created_at, NOW()) as assigned_at
FROM conversations c
WHERE c.agent_id IS NOT NULL
AND NOT EXISTS (
    SELECT 1 
    FROM conversation_assignments ca 
    WHERE ca.conversation_id = c.id 
    AND ca.agent_id = c.agent_id
);

SELECT CONCAT('✅ Adicionados ', ROW_COUNT(), ' registros de conversas atuais') as info;

-- 7. Verificar resultado final
SELECT 'Registros por agente:' as info;
SELECT 
    u.name as agente,
    COUNT(*) as total_atribuicoes
FROM conversation_assignments ca
INNER JOIN users u ON ca.agent_id = u.id
GROUP BY ca.agent_id, u.name
ORDER BY total_atribuicoes DESC;

-- 8. Limpar registros órfãos (agentes deletados)
SELECT 'Verificando registros órfãos:' as info;
SELECT 
    COUNT(*) as registros_orfaos
FROM conversation_assignments ca
LEFT JOIN users u ON ca.agent_id = u.id
WHERE u.id IS NULL;

-- Se quiser deletar os órfãos (descomente a linha abaixo)
-- DELETE FROM conversation_assignments WHERE agent_id NOT IN (SELECT id FROM users);

-- 9. Mostrar últimas atribuições
SELECT 'Últimas 10 atribuições:' as info;
SELECT 
    c.id as conversa_id,
    ct.name as contato,
    u.name as agente,
    u2.name as atribuido_por,
    ca.assigned_at as data
FROM conversation_assignments ca
INNER JOIN conversations c ON ca.conversation_id = c.id
LEFT JOIN contacts ct ON c.contact_id = ct.id
INNER JOIN users u ON ca.agent_id = u.id
LEFT JOIN users u2 ON ca.assigned_by = u2.id
ORDER BY ca.assigned_at DESC
LIMIT 10;

SELECT '✅ FIX CONCLUÍDO!' as info;
SELECT '⚠️  Se tudo estiver OK, pode deletar a tabela conversation_assignments_backup_20260118' as info;
