<?php
/**
 * Fonte única do menu principal (rail desktop + dock mobile + bottom sheet).
 *
 * Estrutura de cada item de nível superior:
 * - id          string  Identificador único (usado como âncora de flyout)
 * - label       string  Nome exibido
 * - icon        string  Classe do ícone keenicons duotone (ex.: 'ki-send')
 * - iconPaths   int     Quantidade de <span class="pathN"> exigida pelo ícone
 * - route       string  Rota direta (item sem filhos)
 * - dock        bool    Aparece no dock mobile (máx. 4 itens com dock=true)
 * - dockRoute   string  Rota usada no dock quando o item é um grupo
 * - permission  string|array  Permissão(ões) exigida(s) — todas (AND)
 * - permissionAny array  Basta uma permissão (OR)
 * - children    array   Subitens do flyout/bottom sheet
 *
 * Estrutura de cada subitem (children):
 * - label / route / permission / permissionAny  como acima
 * - heading     string  Em vez de link, cabeçalho de seção dentro do flyout
 * - separator   bool    Linha separadora antes do item
 * - badge       string  'pulse-success' exibe o ponto pulsante (ex.: Coaching IA)
 * - hidden      bool    Oculta o item do menu sem remover a configuração/lógica
 * - activeNot   array   Rotas que, se ativas, anulam o estado ativo deste item
 *                       (para distinguir prefixos como /goals vs /goals/dashboard)
 * - exact       bool    Rota ativa apenas em correspondência exata (com sufixo)
 *
 * Rotas com {userId} são substituídas pelo id do usuário logado no renderer.
 */

return [
    [
        'id' => 'dashboard',
        'label' => 'Dashboard',
        'icon' => 'ki-element-11',
        'iconPaths' => 4,
        'dock' => true,
        'dockRoute' => '/dashboard',
        'children' => [
            ['label' => 'Geral', 'route' => '/dashboard', 'activeNot' => ['/dashboard/ai']],
            ['label' => '🤖 Inteligência Artificial', 'route' => '/dashboard/ai'],
        ],
    ],
    [
        'id' => 'conversations',
        'label' => 'Conversas',
        'icon' => 'ki-message-text-2',
        'iconPaths' => 3,
        'route' => '/conversations',
        'dock' => true,
    ],
    [
        'id' => 'contacts',
        'label' => 'Contatos',
        'icon' => 'ki-profile-user',
        'iconPaths' => 4,
        'route' => '/contacts',
        'dock' => true,
    ],
    [
        'id' => 'funnels',
        'label' => 'Funis',
        'icon' => 'ki-category',
        'iconPaths' => 4,
        'dock' => true,
        'dockRoute' => '/funnels',
        'children' => [
            ['label' => 'Todos os Funis', 'route' => '/funnels', 'activeNot' => ['/funnels/kanban']],
            ['label' => 'Kanban', 'route' => '/funnels/kanban'],
        ],
    ],
    [
        'id' => 'campaigns',
        'label' => 'Campanhas',
        'icon' => 'ki-send',
        'iconPaths' => 2,
        'permission' => 'campaigns.view',
        'children' => [
            ['label' => 'Painel de Controle', 'route' => '/campaigns/control-panel'],
            ['label' => 'Todas as Campanhas', 'route' => '/campaigns', 'activeNot' => ['/campaigns/']],
            ['label' => 'Nova Campanha', 'route' => '/campaigns/create'],
            ['label' => 'Listas de Contatos', 'route' => '/contact-lists', 'separator' => true],
            ['label' => 'Fontes Externas', 'route' => '/external-sources'],
            ['label' => 'Sequências Drip', 'route' => '/drip-sequences'],
            ['label' => 'Dashboard Campanhas', 'route' => '/campaigns/dashboard', 'separator' => true],
            ['label' => 'Analytics Avançado', 'route' => '/campaigns/analytics'],
            ['label' => 'Monitor em Tempo Real', 'route' => '/campaigns/realtime'],
            ['label' => 'Templates', 'route' => '/campaigns/templates', 'separator' => true],
            ['label' => '🚀 Guia de Início Rápido', 'route' => '/campaigns/quick-start'],
            ['label' => 'Central de Ajuda', 'route' => '/campaigns/help'],
        ],
    ],
    [
        'id' => 'performance',
        'label' => 'Performance',
        'icon' => 'ki-chart-line-up',
        'iconPaths' => 2,
        'children' => [
            ['label' => 'Dashboard', 'route' => '/agent-performance', 'permission' => 'agent_performance.view.all',
             'activeNot' => ['/agent-performance/ranking', '/agent-performance/compare', '/agent-performance/agent', '/agent-performance/best-practices']],
            ['label' => 'Ranking', 'route' => '/agent-performance/ranking', 'permission' => 'agent_performance.view.all'],
            ['label' => 'Comparar', 'route' => '/agent-performance/compare', 'permission' => 'agent_performance.view.all'],
            ['label' => 'Gerar Manuais (IA)', 'route' => '/manuals', 'permission' => ['agent_performance.view.all', 'conversations.view.all']],
            ['label' => '🔎 Análise de Conversas', 'route' => '/conversation-insights', 'permission' => 'conversation_insights.view',
             'activeMatch' => '/conversation-insights'],
            ['label' => 'Minha Performance', 'route' => '/agent-performance/agent?id={userId}'],
            ['label' => 'Minhas Conversões', 'route' => '/agent-conversion/agent?id={userId}'],
            // Metas ocultadas da UI (lógica preservada) — reative removendo 'hidden'
            ['label' => 'Minha Meta', 'route' => '/goals/my', 'hidden' => true],
            ['label' => 'Melhores Práticas', 'route' => '/agent-performance/best-practices', 'permission' => 'agent_performance.best_practices'],
            ['label' => 'Minhas Metas', 'route' => '/goals/dashboard', 'permission' => 'goals.view', 'hidden' => true],
            ['label' => '🎯 Gerenciar Metas', 'route' => '/goals', 'permissionAny' => ['goals.create', 'goals.edit'],
             'activeNot' => ['/goals/dashboard', '/goals/my'], 'hidden' => true],
            ['label' => '🚀 Coaching IA', 'route' => '/coaching/dashboard', 'permission' => 'coaching.view',
             'badge' => 'pulse-success', 'activeMatch' => '/coaching'],
        ],
    ],
    [
        'id' => 'ai',
        'label' => 'Inteligência Artificial',
        'icon' => 'ki-abstract-26',
        'iconPaths' => 2,
        'children' => [
            ['label' => 'Copiloto IA', 'route' => '/copilot'],
            ['heading' => 'Agentes de IA', 'permission' => 'ai_agents.view'],
            ['label' => 'Agentes', 'route' => '/ai-agents', 'permission' => 'ai_agents.view'],
            ['label' => 'Agentes Kanban', 'route' => '/kanban-agents', 'permission' => 'ai_agents.view'],
            ['label' => 'Tools', 'route' => '/ai-tools', 'permission' => ['ai_agents.view', 'ai_tools.view']],
        ],
    ],
    [
        'id' => 'more',
        'label' => 'Mais',
        'icon' => 'ki-dots-square',
        'iconPaths' => 4,
        'wide' => true, // flyout em 2 colunas no desktop
        'children' => [
            ['heading' => 'Equipe'],
            ['label' => 'Agentes', 'route' => '/agents', 'activeNot' => ['/ai-agents', '/kanban-agents']],
            ['label' => 'Tags', 'route' => '/tags', 'permission' => 'tags.view'],
            ['label' => 'Templates', 'route' => '/message-templates', 'permission' => 'message_templates.view'],

            ['heading' => 'Automações'],
            ['label' => 'Gerenciar Automações', 'route' => '/automations'],
            ['label' => 'Botões de Ações', 'route' => '/settings/action-buttons'],

            ['heading' => 'Integrações'],
            ['label' => 'WhatsApp', 'route' => '/integrations/whatsapp'],
            ['label' => 'Notificame', 'route' => '/integrations/notificame', 'permission' => 'notificame.view'],
            ['label' => 'Email', 'route' => '/email-integration', 'permission' => 'integrations.view'],
            ['label' => 'Meta (Instagram + WhatsApp)', 'route' => '/integrations/meta', 'permission' => 'integrations.view',
             'activeNot' => ['/integrations/whatsapp-coex']],
            ['label' => 'WhatsApp CoEx (API Oficial)', 'route' => '/integrations/whatsapp-coex', 'permission' => 'integrations.view'],
            ['label' => 'Api4Com', 'route' => '/integrations/api4com'],
            ['label' => 'Chamadas Api4Com', 'route' => '/api4com-calls', 'permission' => 'api4com_calls.view'],
            ['label' => 'WooCommerce', 'route' => '/integrations/woocommerce', 'permission' => 'integrations.view'],
            ['label' => 'Conversão WooCommerce', 'route' => '/agent-conversion', 'permission' => 'conversion.view',
             'activeNot' => ['/agent-conversion/agent']],
            ['label' => 'Todas as Integrações', 'route' => '/integrations', 'exact' => true],

            ['heading' => 'Permissões'],
            ['label' => 'Roles', 'route' => '/roles'],
            ['label' => 'Setores', 'route' => '/departments'],
            ['label' => 'Times', 'route' => '/teams', 'permission' => 'teams.view'],
            ['label' => 'Logs do Sistema', 'route' => '/logs', 'permission' => 'admin.logs'],

            ['heading' => 'Analytics', 'permission' => 'admin.settings'],
            ['label' => 'Analytics Geral', 'route' => '/analytics', 'permission' => 'admin.settings',
             'activeNot' => ['/analytics/sentiment']],
            ['label' => 'Sentimento', 'route' => '/analytics/sentiment', 'permission' => 'admin.settings'],

            ['heading' => 'Configurações'],
            ['label' => 'Configurações Gerais', 'route' => '/settings', 'exact' => true],
            ['label' => 'API & Tokens', 'route' => '/settings/api-tokens', 'permission' => 'settings.manage'],
        ],
    ],
];
