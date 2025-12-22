<?php
/**
 * Seed: Criar tools padrão do sistema para agentes de IA
 */

function seed_default_ai_tools() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Criando tools padrão de IA...\n";
    
    $defaultTools = [
        [
            'name' => 'Buscar Conversas Anteriores',
            'slug' => 'buscar_conversas_anteriores',
            'description' => 'Busca conversas anteriores do mesmo contato para contexto',
            'tool_type' => 'system',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'buscar_conversas_anteriores',
                    'description' => 'Busca conversas anteriores do mesmo contato para contexto histórico',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => null,
            'enabled' => true
        ],
        [
            'name' => 'Adicionar Tag',
            'slug' => 'adicionar_tag',
            'description' => 'Adiciona uma tag à conversa atual',
            'tool_type' => 'system',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'adicionar_tag',
                    'description' => 'Adiciona uma tag à conversa atual para organização e filtragem',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'tag' => [
                                'type' => 'string',
                                'description' => 'Nome da tag a ser adicionada'
                            ]
                        ],
                        'required' => ['tag']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => null,
            'enabled' => true
        ],
        [
            'name' => 'Mover para Estágio',
            'slug' => 'mover_para_estagio',
            'description' => 'Move a conversa para um estágio específico do funil',
            'tool_type' => 'system',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'mover_para_estagio',
                    'description' => 'Move a conversa para um estágio específico do funil de vendas',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'stage_id' => [
                                'type' => 'integer',
                                'description' => 'ID do estágio para onde mover a conversa'
                            ]
                        ],
                        'required' => ['stage_id']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => null,
            'enabled' => true
        ],
        [
            'name' => 'Escalar para Humano',
            'slug' => 'escalar_para_humano',
            'description' => 'Escala a conversa para um agente humano quando necessário, com opções de atribuição inteligente',
            'tool_type' => 'system',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'escalar_para_humano',
                    'description' => 'Escala a conversa para um agente humano quando a situação requer intervenção humana. Pode especificar motivo e observações.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'description' => 'Motivo da escalação (ex: "Cliente solicitou falar com gerente", "Situação complexa que requer análise humana", "Negociação de valores")'
                            ],
                            'notes' => [
                                'type' => 'string',
                                'description' => 'Observações adicionais ou contexto importante para o agente humano'
                            ]
                        ],
                        'required' => ['reason']
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => json_encode([
                'escalation_type' => 'auto',
                'priority' => 'normal',
                'add_escalation_note' => true,
                'notify_agent' => false,
                'send_transition_message' => true,
                'transition_message' => 'Vou transferir você para um de nossos especialistas. Aguarde um momento, por favor.'
            ], JSON_UNESCAPED_UNICODE),
            'enabled' => true
        ],
        [
            'name' => 'Verificar Status da Conversa',
            'slug' => 'verificar_status_conversa',
            'description' => 'Verifica o status atual da conversa',
            'tool_type' => 'followup',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'verificar_status_conversa',
                    'description' => 'Verifica o status atual da conversa e última interação',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => null,
            'enabled' => true
        ],
        [
            'name' => 'Verificar Última Interação',
            'slug' => 'verificar_ultima_interacao',
            'description' => 'Verifica quando foi a última interação na conversa',
            'tool_type' => 'followup',
            'function_schema' => json_encode([
                'type' => 'function',
                'function' => [
                    'name' => 'verificar_ultima_interacao',
                    'description' => 'Verifica quando foi a última mensagem ou interação na conversa',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => []
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE),
            'config' => null,
            'enabled' => true
        ]
    ];
    
    foreach ($defaultTools as $tool) {
        $sql = "INSERT INTO ai_tools (name, slug, description, tool_type, function_schema, config, enabled, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    description = VALUES(description), 
                    tool_type = VALUES(tool_type),
                    function_schema = VALUES(function_schema),
                    config = VALUES(config),
                    enabled = VALUES(enabled),
                    updated_at = NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $tool['name'],
            $tool['slug'],
            $tool['description'],
            $tool['tool_type'],
            $tool['function_schema'],
            $tool['config'],
            $tool['enabled'] ? 1 : 0
        ]);
        echo "✅ Tool '{$tool['name']}' criada/atualizada\n";
    }
    
    echo "✅ Tools padrão criadas com sucesso!\n";
}

