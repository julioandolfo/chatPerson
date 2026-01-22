-- ============================================================================
-- ATUALIZAÇÃO DAS TOOLS DE SISTEMA PARA AGENTES DE IA
-- Execute este SQL diretamente no banco de dados
-- Data: 2026-01-19
-- ============================================================================

-- ============================================================================
-- 1. TOOL: Buscar Conversas Anteriores
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Buscar Conversas Anteriores',
    'buscar_conversas_anteriores',
    'Busca conversas anteriores do mesmo contato para contexto',
    'system',
    '{"type":"function","function":{"name":"buscar_conversas_anteriores","description":"Busca conversas anteriores do mesmo contato para contexto histórico","parameters":{"type":"object","properties":{},"required":[]}}}',
    NULL,
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 2. TOOL: Buscar Informações do Contato (NOVA)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Buscar Informações do Contato',
    'buscar_informacoes_contato',
    'Busca informações detalhadas do contato atual (nome, email, telefone, atributos customizados)',
    'system',
    '{"type":"function","function":{"name":"buscar_informacoes_contato","description":"Busca informações detalhadas do contato atual incluindo nome, email, telefone e atributos customizados","parameters":{"type":"object","properties":{},"required":[]}}}',
    NULL,
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 3. TOOL: Adicionar Tag (ATUALIZADA)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Adicionar Tag',
    'adicionar_tag',
    'Adiciona uma tag à conversa atual para organização e categorização',
    'system',
    '{"type":"function","function":{"name":"adicionar_tag","description":"Adiciona uma tag à conversa atual para organização e filtragem. Use tags existentes do sistema.","parameters":{"type":"object","properties":{"tag":{"type":"string","description":"Nome da tag a ser adicionada (ex: urgente, vip, suporte, vendas)"}},"required":["tag"]}}}',
    '{"auto_create_tag":false,"notify_on_add":false}',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 4. TOOL: Mover para Estágio (ATUALIZADA)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Mover para Estágio',
    'mover_para_estagio',
    'Move a conversa para um estágio específico do funil de vendas',
    'system',
    '{"type":"function","function":{"name":"mover_para_estagio","description":"Move a conversa para um estágio específico do funil de vendas. Use quando o cliente avançar no processo.","parameters":{"type":"object","properties":{"stage_id":{"type":"integer","description":"ID do estágio de destino no funil"}},"required":["stage_id"]}}}',
    '{"keep_agent":true,"trigger_automations":true,"add_note":true,"notify_agent":false}',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 5. TOOL: Escalar para Humano (CORRIGIDA - MAIS IMPORTANTE)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Escalar para Humano',
    'escalar_para_humano',
    'Escala a conversa para um agente humano quando necessário, com opções de atribuição inteligente',
    'system',
    '{"type":"function","function":{"name":"escalar_para_humano","description":"Escala a conversa para um agente humano quando a situação requer intervenção humana. Pode especificar motivo e observações.","parameters":{"type":"object","properties":{"reason":{"type":"string","description":"Motivo da escalação (ex: Cliente solicitou falar com gerente, Situação complexa que requer análise humana, Negociação de valores)"},"notes":{"type":"string","description":"Observações adicionais ou contexto importante para o agente humano"}},"required":["reason"]}}}',
    '{"escalation_type":"auto","agent_id":null,"department_id":null,"distribution_method":"round_robin","consider_availability":true,"consider_limits":true,"allow_ai_agents":false,"force_assign":false,"remove_ai_after":true,"send_notification":true,"escalation_message":"Vou transferir você para um de nossos especialistas. Aguarde um momento, por favor.","priority":"normal"}',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 6. TOOL: Verificar Status da Conversa (ATUALIZADA)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Verificar Status da Conversa',
    'verificar_status_conversa',
    'Verifica o status atual da conversa e informações de acompanhamento',
    'followup',
    '{"type":"function","function":{"name":"verificar_status_conversa","description":"Verifica o status atual da conversa, última interação e informações relevantes para follow-up","parameters":{"type":"object","properties":{},"required":[]}}}',
    '{"include_last_message":true,"include_agent_info":true,"include_timestamps":true}',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- 7. TOOL: Verificar Última Interação (ATUALIZADA)
-- ============================================================================
INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
VALUES (
    'Verificar Última Interação',
    'verificar_ultima_interacao',
    'Verifica quando foi a última interação na conversa com cálculo de tempo decorrido',
    'followup',
    '{"type":"function","function":{"name":"verificar_ultima_interacao","description":"Verifica quando foi a última mensagem ou interação na conversa, com cálculo automático de tempo decorrido em minutos, horas e dias","parameters":{"type":"object","properties":{},"required":[]}}}',
    '{"include_message_content":true,"include_sender_info":true,"calculate_time_ago":true}',
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    tool_type = VALUES(tool_type),
    function_schema = VALUES(function_schema),
    config = VALUES(config),
    enabled = VALUES(enabled),
    updated_at = NOW();

-- ============================================================================
-- VERIFICAÇÃO: Listar todas as tools de sistema cadastradas
-- ============================================================================
SELECT 
    id,
    name,
    slug,
    tool_type,
    enabled,
    created_at,
    updated_at
FROM ai_tools 
WHERE tool_type IN ('system', 'followup')
ORDER BY 
    FIELD(tool_type, 'system', 'followup'),
    name ASC;

-- ============================================================================
-- VERIFICAÇÃO: Contar tools por tipo
-- ============================================================================
SELECT 
    tool_type,
    COUNT(*) as total,
    SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled_count
FROM ai_tools
GROUP BY tool_type
ORDER BY tool_type;

-- ============================================================================
-- FIM DO SCRIPT
-- ============================================================================
-- ✅ Todas as 7 tools de sistema foram atualizadas/criadas
-- ✅ Configs corrigidos e expandidos
-- ✅ Tool "Escalar para Humano" agora usa implementação completa
-- ✅ Nova tool "Buscar Informações do Contato" adicionada
-- ============================================================================
