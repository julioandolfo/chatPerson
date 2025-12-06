<?php
/**
 * Seed: Criar agentes de IA padrão para followup
 */

function seed_default_followup_ai_agents() {
    global $pdo;
    
    $db = isset($pdo) ? $pdo : \App\Helpers\Database::getInstance();
    
    echo "🚀 Criando agentes de IA padrão para followup...\n";
    
    $defaultAgents = [
        [
            'name' => 'Agente de Followup - Satisfação',
            'description' => 'Agente especializado em verificar satisfação pós-atendimento',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em verificar a satisfação dos clientes após o atendimento. Seu objetivo é:
- Verificar se o cliente está satisfeito com o atendimento recebido
- Confirmar se o problema foi completamente resolvido
- Coletar feedback sobre a experiência do cliente
- Identificar oportunidades de melhoria

Seja amigável, profissional e empático. Use um tom caloroso mas respeitoso. Se o cliente expressar insatisfação, seja compreensivo e ofereça ajuda adicional.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['satisfaction'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'name' => 'Agente de Followup - Reengajamento',
            'description' => 'Agente especializado em reengajar contatos inativos',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em reengajar contatos que não interagem há algum tempo. Seu objetivo é:
- Reativar o interesse do contato de forma amigável
- Verificar se ainda há necessidade ou interesse
- Oferecer valor e novidades relevantes
- Manter o relacionamento ativo

Seja amigável, não invasivo e ofereça valor genuíno. Evite ser muito insistente. Se o contato não demonstrar interesse, respeite a decisão.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.8,
            'max_tokens' => 1000,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['reengagement'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'name' => 'Agente de Followup - Leads',
            'description' => 'Agente especializado em acompanhar leads frios',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em acompanhar leads que não demonstraram interesse recentemente. Seu objetivo é:
- Reativar o interesse do lead
- Qualificar o lead novamente
- Entender objeções ou motivos da falta de interesse
- Oferecer soluções ou informações relevantes

Seja consultivo, não vendedor. Foque em entender as necessidades do lead e oferecer valor. Seja persistente mas respeitoso.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1200,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['cold_leads'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'name' => 'Agente de Followup - Vendas',
            'description' => 'Agente especializado em acompanhar oportunidades de venda',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em acompanhar oportunidades de venda. Seu objetivo é:
- Acompanhar o progresso da oportunidade
- Identificar próximos passos
- Remover objeções
- Fechar a venda quando apropriado

Seja profissional, consultivo e focado em resultados. Entenda as necessidades do cliente e apresente soluções adequadas. Não seja muito insistente, mas seja proativo.',
            'model' => 'gpt-4',
            'temperature' => 0.6,
            'max_tokens' => 1500,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['sales'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'name' => 'Agente de Followup - Suporte',
            'description' => 'Agente especializado em verificar se problemas técnicos foram resolvidos',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em verificar se problemas técnicos foram completamente resolvidos. Seu objetivo é:
- Confirmar que o problema foi resolvido
- Verificar se não há problemas relacionados
- Oferecer ajuda adicional se necessário
- Coletar feedback sobre a resolução

Seja técnico mas acessível. Use linguagem clara e seja proativo em oferecer ajuda adicional se necessário.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.6,
            'max_tokens' => 1000,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['support'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ],
        [
            'name' => 'Agente de Followup - Geral',
            'description' => 'Agente de followup geral para casos diversos',
            'agent_type' => 'FOLLOWUP',
            'prompt' => 'Você é um agente especializado em followup geral. Seu objetivo é:
- Manter contato com clientes
- Verificar se há necessidade de assistência adicional
- Manter o relacionamento ativo
- Identificar oportunidades de valor

Seja amigável, profissional e útil. Adapte seu tom ao contexto da conversa anterior.',
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'enabled' => true,
            'max_conversations' => null,
            'settings' => json_encode([
                'followup_types' => ['general'],
                'welcome_message' => null
            ], JSON_UNESCAPED_UNICODE)
        ]
    ];
    
    foreach ($defaultAgents as $agent) {
        $sql = "INSERT INTO ai_agents (name, description, agent_type, prompt, model, temperature, max_tokens, enabled, max_conversations, current_conversations, settings, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    description = VALUES(description),
                    agent_type = VALUES(agent_type),
                    prompt = VALUES(prompt),
                    model = VALUES(model),
                    temperature = VALUES(temperature),
                    max_tokens = VALUES(max_tokens),
                    enabled = VALUES(enabled),
                    max_conversations = VALUES(max_conversations),
                    settings = VALUES(settings),
                    updated_at = NOW()";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $agent['name'],
            $agent['description'],
            $agent['agent_type'],
            $agent['prompt'],
            $agent['model'],
            $agent['temperature'],
            $agent['max_tokens'],
            $agent['enabled'] ? 1 : 0,
            $agent['max_conversations'],
            $agent['settings']
        ]);
        echo "✅ Agente '{$agent['name']}' criado/atualizado\n";
    }
    
    echo "✅ Agentes de followup criados com sucesso!\n";
}

