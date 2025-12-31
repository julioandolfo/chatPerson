# ⚡ RESUMO RÁPIDO: Sistema de Conversas & AI Agents

> **Guia de Referência Rápida**

---

## 🎯 CONCEITO BÁSICO

```
Cliente → WhatsApp → Sistema → IA ou Humano → Resposta
```

**Sistema híbrido:** Agentes humanos + Agentes de IA trabalhando juntos

---

## 🏗️ COMPONENTES PRINCIPAIS

### Models (Dados)
- **Conversation** - Conversas (1 por contato ativo)
- **AIAgent** - Agentes de IA cadastrados
- **AIConversation** - Logs de IA (1:1 com Conversation quando IA está ativa)
- **AITool** - Ferramentas que IA pode usar
- **Message** - Mensagens da conversa

### Services (Lógica)
- **ConversationService** - CRUD conversas, envio mensagens
- **ConversationAIService** - Adicionar/remover IA
- **AIAgentService** - Processar mensagens com IA
- **OpenAIService** - Integração OpenAI
- **ConversationSettingsService** - Distribuição automática

---

## 🔄 FLUXO BÁSICO

### 1. Mensagem Chega
```
Cliente → WhatsApp → Webhook Quepasa → ConversationService
```

### 2. Criar/Buscar Conversa
```php
$conversationId = ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp'
]);
```

### 3. Atribuição
```php
// Retorna ID positivo (humano) ou negativo (IA)
$assignedId = ConversationSettingsService::autoAssignConversation(...);

if ($assignedId < 0) {
    $aiAgentId = abs($assignedId);  // Ex: -5 → 5
    // Criar AIConversation
}
```

### 4. Se IA → Processar
```php
if ($aiConversation && $aiConversation['status'] === 'active') {
    AIAgentService::processMessage($convId, $agentId, $message);
}
```

### 5. OpenAI Processa
```php
OpenAIService::processMessage() {
    // 1. Montar contexto (prompt + histórico + mensagem)
    // 2. Obter tools do agente
    // 3. Chamar OpenAI API
    // 4. Se tool_calls → Executar → Reenviar
    // 5. Retornar resposta final
}
```

### 6. Responder Cliente
```php
ConversationService::sendMessage($convId, $resposta, 'agent', 0, [], 'text', $aiAgentId);
// → QuepasaService::sendMessage() → WhatsApp
```

---

## 📊 ESTRUTURA DO BANCO

```sql
conversations
├─ id, contact_id, agent_id (humano), status, metadata
│
└─ ai_conversations (1:1 quando IA ativa)
    ├─ id, conversation_id, ai_agent_id, status
    ├─ messages (JSON histórico), tools_used (JSON)
    └─ tokens_used, cost

ai_agents
├─ id, name, prompt, model, temperature
├─ max_conversations, current_conversations
└─ settings (JSON)

ai_tools
├─ id, name, slug, tool_type
└─ function_schema (JSON formato OpenAI)

ai_agent_tools (N:M)
└─ ai_agent_id, ai_tool_id
```

---

## 🤖 ATRIBUIR IA À CONVERSA

### Método 1: Automático (Nova Conversa)
```php
// Em conversation_settings
'distribution' => [
    'method' => 'by_load',
    'assign_to_ai_agent' => true  // ✅ Incluir IA
]

// Sistema atribui automaticamente ao criar conversa
```

### Método 2: Manual (Conversa Existente)
```php
ConversationAIService::addAIAgent(474, [
    'ai_agent_id' => 21,
    'process_immediately' => true,
    'assume_conversation' => true,  // Remove humano se houver
    'only_if_unassigned' => false
]);
```

### Método 3: Por Automação
```php
// Em automation_actions
{
    "action": "assign_ai_agent",
    "params": {
        "ai_agent_id": 21,
        "process_immediately": true
    }
}
```

---

## 🔧 CRIAR AGENTE DE IA

```php
use App\Services\AIAgentService;

$agentId = AIAgentService::create([
    'name' => 'Meu Agente',
    'description' => 'Descrição',
    'agent_type' => 'CS',  // SDR, CS, CLOSER, etc
    'prompt' => 'Você é um assistente que...',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'max_conversations' => 10,  // 0 = ilimitado
    'enabled' => true
]);

// Atribuir tools
$agent = AIAgent::find($agentId);
$agent->addTool($toolId);
```

---

## 🛠️ CRIAR TOOL

```php
use App\Services\AIToolService;

$toolId = AIToolService::create([
    'name' => 'Buscar Pedido',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca pedido por ID',
    'tool_type' => 'woocommerce',
    'function_schema' => [
        'name' => 'buscar_pedido_woocommerce',
        'description' => 'Busca pedido no WooCommerce',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'order_id' => [
                    'type' => 'integer',
                    'description' => 'ID do pedido'
                ]
            ],
            'required' => ['order_id']
        ]
    ],
    'config' => [
        'integration_id' => 1,
        'endpoint' => 'orders'
    ],
    'enabled' => true
]);
```

---

## 📝 REMOVER IA E ATRIBUIR HUMANO

```php
ConversationAIService::removeAIAgent(474, [
    'assign_to_human' => true,
    'human_agent_id' => 10,  // Específico ou null (auto)
    'reason' => 'Cliente solicitou humano'
]);
```

---

## 🎛️ CONFIGURAÇÕES PRINCIPAIS

### OpenAI
```php
Setting::set('openai_api_key', 'sk-...');
```

### Distribuição
```php
Setting::set('conversation_settings', json_encode([
    'distribution' => [
        'enabled' => true,
        'method' => 'by_load',  // round_robin, by_load, by_performance, percentage
        'assign_to_ai_agent' => true,
        'ai_agent_priority' => 'normal'
    ]
]));
```

### Rate Limiting
```php
Setting::set('ai_rate_limiting', json_encode([
    'max_requests_per_minute' => 60,
    'max_cost_per_hour' => 10.00
]));
```

---

## 📊 CONSULTAS ÚTEIS

### Ver status da IA na conversa
```php
$status = ConversationAIService::getAIStatus(474);
// Retorna: has_ai, ai_agent, tokens_used, cost, etc
```

### Ver agentes disponíveis
```php
$agents = AIAgent::getAvailableAgents();
// Retorna agentes habilitados com vagas
```

### Ver custos por período
```sql
SELECT 
    ai_agent_id,
    COUNT(*) as conversations,
    SUM(tokens_used) as tokens,
    SUM(cost) as cost
FROM ai_conversations
WHERE created_at >= '2025-12-01'
GROUP BY ai_agent_id;
```

---

## 🔍 DEBUG

### Logs
```
logs/application.log     - Geral
logs/conversas.log       - Conversas
logs/ai-agents.log       - IA
logs/ai-tools.log        - Tools
logs/automation.log      - Automações
```

### Via Interface
```
Sistema → Logs → [Selecionar tipo]
```

### Via SQL
```sql
-- Ver histórico de uma conversa com IA
SELECT * FROM ai_conversations WHERE conversation_id = 474;

-- Ver tools usadas
SELECT 
    tools_used,
    tokens_used,
    cost 
FROM ai_conversations 
WHERE conversation_id = 474;
```

---

## ⚡ DICAS RÁPIDAS

### 1. ID Negativo = IA
```php
if ($agentId < 0) {
    $aiAgentId = abs($agentId);  // Converter para positivo
}
```

### 2. Verificar se tem IA ativa
```php
$aiConv = AIConversation::getByConversationId($convId);
$hasActiveAI = $aiConv && $aiConv['status'] === 'active';
```

### 3. Custo aproximado GPT-4
```
1.000 tokens prompt = $0.03
1.000 tokens completion = $0.06
Média por conversa = $0.001 - $0.005
```

### 4. Limites recomendados
```
max_conversations_per_ai_agent = 0 (ilimitado)
max_tokens = 2000
temperature = 0.7 (balanceado)
```

### 5. Tools mais comuns
- **WooCommerce** - Buscar pedidos/produtos
- **N8N** - Workflows personalizados
- **Database** - Consultas SQL
- **System** - Funções do sistema

---

## 🚨 TROUBLESHOOTING

### IA não responde
1. Verificar `AIConversation.status` = 'active'
2. Verificar `AIAgent.enabled` = 1
3. Verificar `OpenAI API Key` configurada
4. Ver logs em `logs/ai-agents.log`

### Tool não executa
1. Verificar tool está habilitada
2. Verificar tool está atribuída ao agente
3. Ver `function_schema` está correto
4. Ver logs em `logs/ai-tools.log`

### Custo alto
1. Ver `ai_conversations` → `cost` por período
2. Verificar `temperature` (menor = mais barato)
3. Usar `gpt-3.5-turbo` ao invés de `gpt-4`
4. Limitar `max_tokens`

---

## 📚 DOCUMENTAÇÃO COMPLETA

- **SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md** - Documentação técnica detalhada
- **ANALISE_LOGS_SISTEMA.md** - Análise dos logs em funcionamento
- **DOCUMENTACAO_AI_AGENTS_E_TOOLS.md** - Documentação de agentes e tools
- **ARQUITETURA.md** - Arquitetura geral do sistema

---

**Última atualização:** 31/12/2025
