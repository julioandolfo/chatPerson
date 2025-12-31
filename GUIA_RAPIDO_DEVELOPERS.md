# 🚀 GUIA RÁPIDO - DESENVOLVEDORES

> **Referência rápida para desenvolvimento e troubleshooting**
> 
> Data: 31/12/2025

---

## 📝 ÍNDICE RÁPIDO

- [Criar Agente de IA](#criar-agente-de-ia)
- [Criar Tool](#criar-tool)
- [Criar Conversa](#criar-conversa)
- [Enviar Mensagem](#enviar-mensagem)
- [Processar com IA](#processar-com-ia)
- [Distribuição Automática](#distribuição-automática)
- [Verificar Métricas](#verificar-métricas)
- [Troubleshooting](#troubleshooting)
- [Exemplos Práticos](#exemplos-práticos)

---

## 🤖 CRIAR AGENTE DE IA

### Código Básico

```php
use App\Services\AIAgentService;

$agentId = AIAgentService::create([
    'name' => 'Suporte Técnico',
    'description' => 'Agente especializado em suporte',
    'agent_type' => 'SUPPORT',  // SDR, CS, CLOSER, FOLLOWUP, SUPPORT, ONBOARDING, GENERAL
    'prompt' => 'Você é um agente de suporte técnico especializado...',
    'model' => 'gpt-4',         // gpt-4, gpt-4-turbo, gpt-3.5-turbo
    'temperature' => 0.7,        // 0.0 a 2.0 (quanto maior, mais criativo)
    'max_tokens' => 2000,        // Máximo de tokens na resposta
    'enabled' => true,           // Se está ativo
    'max_conversations' => 50,   // Limite simultâneo (null = sem limite)
    'response_delay_min' => 2,   // Delay mínimo (segundos)
    'response_delay_max' => 5,   // Delay máximo (segundos)
    'context_timer_seconds' => 30 // Timer de contexto (0 = desabilitado)
]);

echo "Agente criado com ID: {$agentId}\n";
```

### Atualizar Agente

```php
AIAgentService::update($agentId, [
    'prompt' => 'Novo prompt...',
    'enabled' => false  // Desabilitar temporariamente
]);
```

### Listar Agentes

```php
$agents = AIAgentService::list([
    'agent_type' => 'SUPPORT',  // Filtrar por tipo (opcional)
    'enabled' => true           // Apenas ativos (opcional)
]);

foreach ($agents as $agent) {
    echo "#{$agent['id']} - {$agent['name']} ({$agent['agent_type']})\n";
}
```

---

## 🛠️ CRIAR TOOL

### Exemplo Completo

```php
use App\Services\AIToolService;

$toolId = AIToolService::create([
    'name' => 'Buscar Pedido WooCommerce',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca informações de um pedido no WooCommerce',
    'tool_type' => 'woocommerce', // system, woocommerce, database, api, n8n, document, followup
    'function_schema' => [
        'type' => 'function',
        'function' => [
            'name' => 'buscar_pedido_woocommerce',
            'description' => 'Busca um pedido no WooCommerce pelo ID',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'order_id' => [
                        'type' => 'integer',
                        'description' => 'ID do pedido no WooCommerce'
                    ]
                ],
                'required' => ['order_id']
            ]
        ]
    ],
    'config' => [
        'api_url' => 'https://meusite.com/wp-json/wc/v3',
        'consumer_key' => 'ck_...',
        'consumer_secret' => 'cs_...',
        'timeout' => 30
    ],
    'enabled' => true
]);

echo "Tool criada com ID: {$toolId}\n";
```

### Associar Tool ao Agente

```php
use App\Models\AIAgent;

AIAgent::addTool(
    $agentId = 5,
    $toolId = 10,
    $config = [],      // Config específica para este agente (opcional)
    $enabled = true    // Se está ativa
);
```

### Remover Tool do Agente

```php
AIAgent::removeTool($agentId, $toolId);
```

### Listar Tools de um Agente

```php
$tools = AIAgent::getTools($agentId);

foreach ($tools as $tool) {
    echo "- {$tool['name']} ({$tool['slug']})\n";
}
```

---

## 💬 CRIAR CONVERSA

### Código Básico

```php
use App\Services\ConversationService;

$result = ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp', // whatsapp, instagram, facebook, email, etc
    'agent_id' => null,      // null = distribuir automaticamente
    'department_id' => 5,    // Setor (opcional)
    'funnel_id' => 10,       // Funil (opcional)
    'stage_id' => 20,        // Etapa do funil (opcional)
    'whatsapp_account_id' => 3, // Conta WhatsApp (se aplicável)
    'status' => 'open'
]);

$conversationId = $result['conversation_id'];
$agentId = $result['agent_id']; // Pode ser NULL, positivo (humano) ou negativo (IA)

echo "Conversa criada: #{$conversationId}\n";

if ($agentId) {
    if ($agentId < 0) {
        echo "Atribuída ao agente de IA: " . abs($agentId) . "\n";
    } else {
        echo "Atribuída ao agente humano: {$agentId}\n";
    }
} else {
    echo "Sem atribuição\n";
}
```

### Com Distribuição Automática para IA

```php
// IMPORTANTE: Habilitar distribuição para IA primeiro
use App\Services\ConversationSettingsService;

ConversationSettingsService::updateSettings([
    'distribution' => [
        'enable_auto_assignment' => true,
        'assign_to_ai_agent' => true  // ← HABILITAR IA
    ]
]);

// Agora criar conversa (será distribuída para humano OU IA)
$result = ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp'
]);
```

---

## 📨 ENVIAR MENSAGEM

### Mensagem do Cliente

```php
use App\Services\ConversationService;

$messageId = ConversationService::sendMessage(
    $conversationId = 100,
    $content = 'Olá, preciso de ajuda',
    $senderType = 'contact',
    $senderId = 123  // contact_id
);

echo "Mensagem criada: #{$messageId}\n";

// Se conversa tem agente de IA ativo, ele processará automaticamente!
```

### Mensagem do Agente (Humano)

```php
$messageId = ConversationService::sendMessage(
    $conversationId = 100,
    $content = 'Claro! Como posso ajudar?',
    $senderType = 'agent',
    $senderId = 5,    // user_id
    $options = [
        'send_via_channel' => true  // Enviar via WhatsApp/integração
    ]
);
```

### Mensagem do Sistema

```php
$messageId = ConversationService::sendMessage(
    $conversationId = 100,
    $content = 'Conversa atribuída ao agente João',
    $senderType = 'system',
    $senderId = null
);
```

---

## 🤖 PROCESSAR COM IA

### Processar Mensagem Específica

```php
use App\Services\AIAgentService;

$response = AIAgentService::processMessage(
    $conversationId = 100,
    $agentId = 5,
    $message = 'Quero saber sobre meu pedido #12345'
);

echo "Resposta: {$response['content']}\n";
echo "Tokens: {$response['tokens_used']}\n";
echo "Custo: US$ {$response['cost']}\n";
```

### Processar Conversa Completa (Nova Atribuição)

```php
// Quando conversa é atribuída a um agente de IA
AIAgentService::processConversation(
    $conversationId = 100,
    $aiAgentId = 5
);

// Sistema automaticamente:
// 1. Busca mensagens do contato
// 2. SE tem mensagens: Processa última mensagem
// 3. SE NÃO tem: Envia welcome_message
```

### Escalar para Humano

```php
AIAgentService::escalateToHuman(
    $conversationId = 100,
    $userId = 10,              // Agente humano
    $reason = 'Cliente pediu atendimento humano'
);

// Sistema automaticamente:
// 1. Atualiza ai_conversations.status = 'escalated'
// 2. Atualiza conversations.agent_id = $userId
// 3. Envia mensagem de transição
// 4. Notifica agente humano via WebSocket
```

---

## 🎯 DISTRIBUIÇÃO AUTOMÁTICA

### Configurar Distribuição

```php
use App\Services\ConversationSettingsService;

ConversationSettingsService::updateSettings([
    'distribution' => [
        'enable_auto_assignment' => true,
        'method' => 'round_robin',  // round_robin, by_load, by_specialty, by_performance, percentage
        'assign_to_ai_agent' => true,   // Incluir agentes de IA
        'consider_availability' => true,
        'consider_agent_limits' => true
    ],
    'limits' => [
        'max_conversations_per_agent' => 20,
        'max_conversations_per_department' => 100
    ]
]);
```

### Forçar Distribuição Manual

```php
$agentId = ConversationSettingsService::autoAssignConversation(
    $conversationId = 100,
    $departmentId = 5,     // Filtrar por setor (opcional)
    $funnelId = 10,        // Filtrar por funil (opcional)
    $stageId = 20          // Filtrar por etapa (opcional)
);

if ($agentId) {
    // Atribuir conversa
    ConversationService::assignAgent($conversationId, $agentId);
}
```

### Verificar Agentes Disponíveis

```php
// Agentes humanos
$humanAgents = User::where('enabled', '=', 1)
    ->where('can_receive_conversations', '=', 1)
    ->get();

// Agentes de IA
$aiAgents = AIAgent::getAvailableAgents();

foreach ($aiAgents as $agent) {
    echo "IA: {$agent['name']} - {$agent['current_conversations']}/{$agent['max_conversations']}\n";
}
```

---

## 📊 VERIFICAR MÉTRICAS

### Estatísticas de um Agente de IA

```php
use App\Models\AIConversation;

$stats = AIConversation::getAgentStats(
    $agentId = 5,
    $startDate = '2025-12-01',
    $endDate = '2025-12-31'
);

echo "Total de Conversas: {$stats['total_conversations']}\n";
echo "Total de Tokens: {$stats['total_tokens']}\n";
echo "Total de Custo: US$ {$stats['total_cost']}\n";
echo "Média de Tokens: {$stats['avg_tokens']}\n";
echo "Concluídas: {$stats['completed_conversations']}\n";
echo "Escaladas: {$stats['escalated_conversations']}\n";
```

### Histórico de uma Conversa com IA

```php
$history = AIConversation::getHistory($aiConversationId);

echo "Conversa #{$history['conversation_id']}\n";
echo "Agente: {$history['agent_name']}\n";
echo "Status: {$history['status']}\n";
echo "Tokens: {$history['tokens_used']}\n";
echo "Custo: US$ {$history['cost']}\n";

// Mensagens
foreach ($history['messages'] as $msg) {
    echo "[{$msg['role']}] {$msg['content']}\n";
}

// Tools usadas
foreach ($history['tools_used'] as $tool) {
    echo "Tool: {$tool['tool']} - {$tool['timestamp']}\n";
}
```

### Buscar ai_conversation por conversation_id

```php
$aiConv = AIConversation::getByConversationId($conversationId);

if ($aiConv) {
    echo "Agente de IA ativo: {$aiConv['ai_agent_id']}\n";
    echo "Status: {$aiConv['status']}\n";
} else {
    echo "Sem agente de IA\n";
}
```

### Verificar Rate Limiting

```php
use App\Services\AICostControlService;

$check = AICostControlService::canProcessMessage($agentId = 5);

if ($check['allowed']) {
    echo "✅ Pode processar\n";
} else {
    echo "❌ {$check['reason']}\n";
}
```

---

## 🐛 TROUBLESHOOTING

### IA não está respondendo

```php
// 1. Verificar se agente está habilitado
$agent = AIAgent::find($agentId);
if (!$agent['enabled']) {
    echo "❌ Agente desabilitado\n";
}

// 2. Verificar limite de conversas
if ($agent['max_conversations'] && 
    $agent['current_conversations'] >= $agent['max_conversations']) {
    echo "❌ Agente no limite de conversas\n";
}

// 3. Verificar rate limiting
$check = AICostControlService::canProcessMessage($agentId);
if (!$check['allowed']) {
    echo "❌ Rate limit: {$check['reason']}\n";
}

// 4. Verificar API Key
$apiKey = Setting::get('openai_api_key');
if (empty($apiKey)) {
    echo "❌ API Key não configurada\n";
}

// 5. Verificar ai_conversation ativa
$aiConv = AIConversation::getByConversationId($conversationId);
if (!$aiConv || $aiConv['status'] !== 'active') {
    echo "❌ Sem ai_conversation ativa\n";
}
```

### Custos muito altos

```php
// 1. Verificar stats do agente
$stats = AIConversation::getAgentStats($agentId, date('Y-m-01'), date('Y-m-d'));
echo "Custo do mês: US$ {$stats['total_cost']}\n";
echo "Média por conversa: US$ " . ($stats['total_cost'] / $stats['total_conversations']) . "\n";

// 2. Verificar modelo usado
$agent = AIAgent::find($agentId);
echo "Modelo: {$agent['model']}\n";
// Considerar trocar gpt-4 → gpt-3.5-turbo para reduzir custos

// 3. Verificar prompt muito longo
echo "Tamanho do prompt: " . strlen($agent['prompt']) . " caracteres\n";
// Considerar reduzir prompt

// 4. Configurar limites
ConversationSettingsService::updateSettings([
    'ai_cost_control' => [
        'daily_limit_usd' => 10.00,
        'monthly_limit_usd' => 200.00
    ]
]);
```

### Tools não estão funcionando

```php
// 1. Verificar se tool existe e está ativa
$tool = AITool::findBySlug('buscar_pedido_woocommerce');
if (!$tool) {
    echo "❌ Tool não encontrada\n";
} elseif (!$tool['enabled']) {
    echo "❌ Tool desabilitada\n";
}

// 2. Verificar se tool está associada ao agente
$tools = AIAgent::getTools($agentId);
$found = false;
foreach ($tools as $t) {
    if ($t['slug'] === 'buscar_pedido_woocommerce') {
        $found = true;
        if (!$t['tool_enabled']) {
            echo "❌ Tool desabilitada para este agente\n";
        }
    }
}
if (!$found) {
    echo "❌ Tool não associada ao agente\n";
    // Associar:
    AIAgent::addTool($agentId, $tool['id'], [], true);
}

// 3. Verificar logs de execução
$aiConv = AIConversation::getHistory($aiConvId);
foreach ($aiConv['tools_used'] as $toolUse) {
    echo "Tool: {$toolUse['tool']}\n";
    if (isset($toolUse['result']['error'])) {
        echo "Erro: {$toolUse['result']['error']}\n";
    }
}
```

### Distribuição não está atribuindo para IA

```php
// 1. Verificar se está habilitado
$settings = ConversationSettingsService::getSettings();
if (!$settings['distribution']['assign_to_ai_agent']) {
    echo "❌ assign_to_ai_agent = false\n";
    echo "Habilitar:\n";
    echo "ConversationSettingsService::updateSettings(['distribution' => ['assign_to_ai_agent' => true]]);\n";
}

// 2. Verificar se há agentes de IA disponíveis
$aiAgents = AIAgent::getAvailableAgents();
if (empty($aiAgents)) {
    echo "❌ Nenhum agente de IA disponível\n";
    echo "Criar ou habilitar agentes\n";
}

// 3. Testar distribuição manualmente
$agentId = ConversationSettingsService::autoAssignConversation($conversationId);
if ($agentId && $agentId < 0) {
    echo "✅ Selecionou agente de IA: " . abs($agentId) . "\n";
} elseif ($agentId) {
    echo "⚠️ Selecionou agente humano: {$agentId}\n";
} else {
    echo "❌ Nenhum agente selecionado\n";
}
```

---

## 💡 EXEMPLOS PRÁTICOS

### Exemplo 1: Sistema de Suporte Completo

```php
// 1. Criar agente de suporte
$agentId = AIAgentService::create([
    'name' => 'Suporte Técnico',
    'agent_type' => 'SUPPORT',
    'prompt' => 'Você é um agente de suporte técnico especializado em produtos eletrônicos.
REGRAS:
- Seja empático e educado
- Use as ferramentas para buscar informações reais
- NUNCA invente dados
- Se não conseguir resolver, escale para humano
- Sempre pergunte o número do pedido se o cliente mencionar problemas com produto',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'max_conversations' => 50,
    'response_delay_min' => 2,
    'response_delay_max' => 5
]);

// 2. Criar tool para buscar pedidos
$toolId = AIToolService::create([
    'name' => 'Buscar Pedido',
    'slug' => 'buscar_pedido_woocommerce',
    'tool_type' => 'woocommerce',
    'function_schema' => [...], // Schema completo
    'config' => [...],          // Config WooCommerce
    'enabled' => true
]);

// 3. Associar tool ao agente
AIAgent::addTool($agentId, $toolId);

// 4. Habilitar distribuição para IA
ConversationSettingsService::updateSettings([
    'distribution' => [
        'enable_auto_assignment' => true,
        'method' => 'by_load',
        'assign_to_ai_agent' => true
    ]
]);

// 5. Criar conversa (será atribuída automaticamente)
$result = ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp'
]);

// 6. Cliente envia mensagem
ConversationService::sendMessage(
    $result['conversation_id'],
    'Meu produto chegou com defeito',
    'contact',
    123
);

// IA processará automaticamente e responderá!
```

### Exemplo 2: Bot de Vendas (SDR)

```php
// 1. Criar agente SDR
$sdrId = AIAgentService::create([
    'name' => 'Vendas - Qualificação',
    'agent_type' => 'SDR',
    'prompt' => 'Você é um SDR (Sales Development Representative).
OBJETIVO: Qualificar leads e agendar demos.
REGRAS:
- Seja cordial mas objetivo
- Faça perguntas qualificadoras (empresa, cargo, necessidade)
- Use tools para verificar estoque e calcular valores
- Quando lead qualificado, escale para closer humano
- Nunca force venda - qualifique primeiro',
    'model' => 'gpt-4',
    'max_conversations' => 30
]);

// 2. Criar tools
$toolEstoqueId = AIToolService::create([
    'name' => 'Verificar Estoque',
    'slug' => 'verificar_estoque',
    'tool_type' => 'database',
    'function_schema' => [
        'type' => 'function',
        'function' => [
            'name' => 'verificar_estoque',
            'description' => 'Verifica disponibilidade de produto',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'product_id' => ['type' => 'integer']
                ],
                'required' => ['product_id']
            ]
        ]
    ]
]);

AIAgent::addTool($sdrId, $toolEstoqueId);

// 3. Configurar escalonamento automático
// (via settings do agente)
AIAgentService::update($sdrId, [
    'settings' => json_encode([
        'auto_escalate' => [
            'enabled' => true,
            'conditions' => [
                'lead_qualified' => true  // Quando lead qualificado
            ],
            'escalate_to_department_id' => 5  // Vendas (closers)
        ]
    ])
]);
```

### Exemplo 3: Follow-up Automático Pós-Compra

```php
// 1. Criar agente de follow-up
$followupId = AIAgentService::create([
    'name' => 'Follow-up Pós-Compra',
    'agent_type' => 'FOLLOWUP',
    'prompt' => 'Você faz follow-up com clientes após compra.
OBJETIVO: Verificar satisfação e detectar problemas precocemente.
REGRAS:
- Seja amigável e genuíno
- Pergunte sobre a experiência com produto/entrega
- Se houver problema, busque detalhes e escale para suporte
- Agradeça pelo feedback positivo
- Ofereça ajuda se necessário',
    'model' => 'gpt-3.5-turbo',  // Mais barato para follow-up simples
    'max_conversations' => 100
]);

// 2. Criar automação para trigger após 3 dias
// (em AutomationService)
Automation::create([
    'name' => 'Follow-up Pós-Compra',
    'trigger_type' => 'time_based',
    'trigger_config' => [
        'days_after_purchase' => 3
    ],
    'actions' => [
        [
            'type' => 'create_conversation',
            'channel' => 'whatsapp',
            'assign_to_ai_agent_id' => $followupId
        ],
        [
            'type' => 'send_message',
            'content' => 'Olá! Seu pedido chegou bem? Estamos à disposição para ajudar! 😊'
        ]
    ]
]);

// 3. Sistema executará automaticamente após 3 dias da compra!
```

---

## 🔑 VARIÁVEIS DE AMBIENTE

### Configurar .env

```env
# OpenAI
OPENAI_API_KEY=sk-...

# Rate Limiting
AI_RATE_LIMIT_PER_MINUTE=10
AI_RATE_LIMIT_PER_HOUR=100
AI_RATE_LIMIT_PER_DAY=1000

# Custos
AI_DAILY_COST_LIMIT=10.00
AI_MONTHLY_COST_LIMIT=200.00

# Logs
AI_LOG_LEVEL=info  # debug, info, warning, error
```

---

## 📚 REFERÊNCIAS RÁPIDAS

### Tabelas Principais

```
conversations      - Conversas
ai_agents          - Agentes de IA
ai_conversations   - Logs de IA
ai_tools           - Ferramentas
ai_agent_tools     - Relação N:N
messages           - Mensagens
contacts           - Contatos
users              - Agentes humanos
```

### Models

```php
App\Models\Conversation
App\Models\AIAgent
App\Models\AIConversation
App\Models\AITool
App\Models\Message
App\Models\Contact
App\Models\User
```

### Services

```php
App\Services\ConversationService
App\Services\AIAgentService
App\Services\OpenAIService
App\Services\AIToolService
App\Services\ConversationSettingsService
App\Services\AICostControlService
```

### Helpers

```php
App\Helpers\Logger
App\Helpers\ConversationDebug
App\Helpers\Database
App\Helpers\Validator
```

---

## 🎯 COMANDOS ÚTEIS

### Logs

```bash
# Ver logs de IA
tail -f logs/ai.log

# Ver logs de automações
tail -f logs/automations.log

# Ver logs de conversas
tail -f logs/conversas.log
```

### Migrations

```bash
# Rodar migrations
php database/migrate.php

# Rodar seed
php database/seed.php
```

### Cache

```bash
# Limpar cache de permissões
rm -rf storage/cache/permissions/*

# Limpar cache de conversas
rm -rf storage/cache/conversations/*
```

---

## 📞 SUPORTE

### Documentação Completa

- `ANALISE_COMPLETA_CONVERSATIONS_AI_AGENTS.md`
- `DIAGRAMAS_SYSTEM_FLOW.md`
- `RESUMO_EXECUTIVO_SYSTEM_ANALYSIS.md`

### Logs Importantes

```php
// Habilitar debug
\App\Helpers\Logger::setLevel('debug');

// Log específico
\App\Helpers\Logger::info("Mensagem", 'arquivo.log');

// Debug de conversas
\App\Helpers\ConversationDebug::aiAgent($convId, "Evento", $data);
```

---

**Última Atualização:** 31/12/2025
**Versão:** 1.0.0
