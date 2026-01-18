-- ============================================================================
-- QUERIES PARA ATUALIZAR SISTEMA DE KANBAN
-- Execute essas queries na ordem apresentada
-- ============================================================================

-- 1. SINCRONIZAR CAMPOS stage_order E position
-- ============================================================================
SET @funnel_id = NULL;
SET @row_number = 0;

UPDATE funnel_stages fs
INNER JOIN (
    SELECT 
        id,
        funnel_id,
        @row_number := IF(@funnel_id = funnel_id, @row_number + 1, 1) AS new_order,
        @funnel_id := funnel_id AS current_funnel
    FROM funnel_stages
    ORDER BY 
        funnel_id ASC,
        COALESCE(stage_order, 999999) ASC,
        COALESCE(position, 999999) ASC,
        id ASC
) AS ordered ON fs.id = ordered.id
SET 
    fs.position = ordered.new_order,
    fs.stage_order = ordered.new_order;

-- Verificar resultado
SELECT 
    f.name AS funnel_name,
    fs.id,
    fs.name AS stage_name,
    fs.position,
    fs.stage_order,
    fs.is_system_stage
FROM funnel_stages fs
INNER JOIN funnels f ON fs.funnel_id = f.id
WHERE f.status = 'active'
ORDER BY f.id, fs.stage_order ASC;

-- ============================================================================
-- 2. CRIAR TABELA: funnel_stage_history
-- Histórico de mudanças de etapas das conversas
-- ============================================================================
CREATE TABLE IF NOT EXISTS funnel_stage_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    from_stage_id INT NULL,
    to_stage_id INT NOT NULL,
    changed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_from_stage (from_stage_id),
    INDEX idx_to_stage (to_stage_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (from_stage_id) REFERENCES funnel_stages(id) ON DELETE SET NULL,
    FOREIGN KEY (to_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 3. CRIAR TABELA: conversation_assignments (ESTRUTURA ATUALIZADA)
-- Histórico de atribuições de agentes
-- IMPORTANTE: Estrutura corrigida - usa agent_id ao invés de from/to
-- ============================================================================
CREATE TABLE IF NOT EXISTS conversation_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL COMMENT 'ID da conversa',
    agent_id INT NULL COMMENT 'ID do agente atribuído (NULL = conversa não atribuída)',
    assigned_by INT NULL COMMENT 'ID do usuário que fez a atribuição (NULL = sistema/automação)',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi atribuído',
    removed_at TIMESTAMP NULL COMMENT 'Quando o agente foi removido da conversa',
    
    INDEX idx_conversation_agent (conversation_id, agent_id),
    INDEX idx_agent_date (agent_id, assigned_at),
    INDEX idx_conversation_date (conversation_id, assigned_at),
    
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popular com conversas atuais que têm agente atribuído
INSERT IGNORE INTO conversation_assignments (conversation_id, agent_id, assigned_at)
SELECT 
    c.id as conversation_id,
    c.agent_id,
    COALESCE(c.created_at, NOW()) as assigned_at
FROM conversations c
WHERE c.agent_id IS NOT NULL  -- ✅ Apenas conversas com agente
AND NOT EXISTS (
    SELECT 1 FROM conversation_assignments ca 
    WHERE ca.conversation_id = c.id AND ca.agent_id = c.agent_id
);

-- ============================================================================
-- 4. CRIAR TABELA: conversation_ratings
-- Avaliações de conversas pelos clientes
-- ============================================================================
CREATE TABLE IF NOT EXISTS conversation_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT NULL,
    rated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_conversation (conversation_id),
    INDEX idx_rating (rating),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. VERIFICAR TABELAS CRIADAS
-- ============================================================================
SHOW TABLES LIKE '%funnel_stage_history%';
SHOW TABLES LIKE '%conversation_assignments%';
SHOW TABLES LIKE '%conversation_ratings%';

-- ============================================================================
-- 6. VERIFICAR ESTRUTURA DAS TABELAS
-- ============================================================================
DESCRIBE funnel_stage_history;
DESCRIBE conversation_assignments;
DESCRIBE conversation_ratings;

-- ============================================================================
-- FIM DAS QUERIES
-- ============================================================================
