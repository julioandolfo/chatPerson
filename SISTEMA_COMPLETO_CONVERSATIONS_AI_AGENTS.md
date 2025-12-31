# 🤖 SISTEMA COMPLETO: CONVERSAS & AI AGENTS

> **Documentação Técnica Completa**  
> Sistema multiatendimento com Agentes de IA integrados

---

## 📑 ÍNDICE

1. [Visão Geral](#-visão-geral)
2. [Arquitetura do Sistema](#-arquitetura-do-sistema)
3. [Estrutura do Banco de Dados](#-estrutura-do-banco-de-dados)
4. [Fluxo Completo](#-fluxo-completo)
5. [Atribuição de Agentes](#-atribuição-de-agentes)
6. [Processamento com IA](#-processamento-com-ia)
7. [Integração OpenAI](#-integração-openai)
8. [Sistema de Tools](#-sistema-de-tools)
9. [Logs e Monitoramento](#-logs-e-monitoramento)
10. [Configurações](#-configurações)
11. [Exemplos Práticos](#-exemplos-práticos)

---

## 🎯 VISÃO GERAL

### O Que é o Sistema?

Este é um **sistema multiatendimento multicanal** que permite atendimento por:
- **Agentes Humanos** - Atendentes reais
- **Agentes de IA** - Bots inteligentes usando OpenAI GPT-4/3.5

### Principais Características

✅ **Distribuição Automática** - Round-robin, por carga, performance, etc.  
✅ **IA Integrada** - Agentes de IA processam mensagens automaticamente  
✅ **Function Calling** - IA pode usar ferramentas (buscar pedidos, etc)  
✅ **Tempo Real** - WebSocket para atualizações instantâneas  
✅ **Multicanal** - WhatsApp, Instagram, Telegram, etc  
✅ **Controle de Custos** - Rate limiting e limites de custo OpenAI  
✅ **Logs Completos** - Rastreabilidade total das operações

---

## 🏗️ ARQUITETURA DO SISTEMA

### Componentes Principais

```
┌─────────────────────────────────────────────────────────────┐
│                      FRONTEND                                │
│  (Views, JavaScript, WebSocket Client)                      │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                   CONTROLLERS                                │
│  ConversationController, AIAgentController, etc             │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                     SERVICES                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ConversationService         ← Lógica de conversas   │   │
│  │ ConversationAIService       ← Gerenciamento de IA   │   │
│  │ AIAgentService              ← Processamento IA      │   │
│  │ OpenAIService               ← Integração OpenAI     │   │
│  │ AIToolService               ← Gerenciamento tools   │   │
│  │ ConversationSettingsService ← Distribuição          │   │
│  └─────────────────────────────────────────────────────┘   │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                      MODELS                                  │
│  Conversation, AIAgent, AIConversation, AITool, Message     │
└──────────────────┬──────────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────────┐
│                     DATABASE                                 │
│  MySQL 8.0+ com tabelas relacionadas                        │
└─────────────────────────────────────────────────────────────┘
```

### Fluxo de Dados

```
Cliente (WhatsApp)
    ↓
Webhook Externo
    ↓
QuepasaWebhookController
    ↓
ConversationService::sendMessage()
    ↓
Detecta se é mensagem do contato
    ↓
┌───────────────────────────────────┐
│ Tem AI Agent ativo?               │
├─────────────┬─────────────────────┤
│ SIM         │ NÃO                 │
│ ↓           │ ↓                   │
│ AIAgent     │ Aguarda agente      │
│ processa    │ humano responder    │
└─────────────┴─────────────────────┘
```

---

## 💾 ESTRUTURA DO BANCO DE DADOS

### Tabelas Principais

#### 1. **conversations** - Conversas

```sql
CREATE TABLE conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contact_id INT NOT NULL,
    agent_id INT NULL,              -- ID do agente HUMANO (users)
    department_id INT NULL,
    funnel_id INT NULL,
    funnel_stage_id INT NULL,
    channel VARCHAR(50),            -- whatsapp, instagram, etc
    whatsapp_account_id INT NULL,
    status VARCHAR(50),             -- open, closed, resolved
    metadata JSON,                  -- Dados adicionais
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    FOREIGN KEY (agent_id) REFERENCES users(id)
);
```

**Campos Importantes:**
- `agent_id` - **Apenas para agentes humanos** (NULL se IA está atendendo)
- `metadata` - Armazena configurações de IA branching, intents, etc
- `status` - Controla estado da conversa

#### 2. **ai_agents** - Agentes de IA

```sql
CREATE TABLE ai_agents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    agent_type VARCHAR(50),         -- SDR, CS, CLOSER, etc
    prompt TEXT,                    -- Prompt base do agente
    model VARCHAR(100),             -- gpt-4, gpt-3.5-turbo
    temperature DECIMAL(3,2),       -- 0.00 a 2.00
    max_tokens INT,
    max_conversations INT DEFAULT 0,  -- Limite de conversas simultâneas (0 = ilimitado)
    current_conversations INT DEFAULT 0,  -- Contador atual
    enabled TINYINT(1) DEFAULT 1,
    settings JSON,                  -- Configurações adicionais
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Importantes:**
- `prompt` - Define personalidade e instruções do agente
- `model` - Modelo OpenAI a usar
- `temperature` - Criatividade (0 = determinístico, 2 = criativo)
- `max_conversations` - **0 = ilimitado**, >0 = limite
- `current_conversations` - Atualizado automaticamente

#### 3. **ai_conversations** - Logs de IA

```sql
CREATE TABLE ai_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    ai_agent_id INT NOT NULL,
    messages JSON,                  -- Histórico completo
    tools_used JSON,                -- Tools utilizadas
    tokens_used INT DEFAULT 0,      -- Total de tokens
    tokens_prompt INT DEFAULT 0,
    tokens_completion INT DEFAULT 0,
    cost DECIMAL(10,4) DEFAULT 0,   -- Custo em USD
    status VARCHAR(50),             -- active, completed, escalated
    escalated_to_user_id INT NULL,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id)
);
```

**Campos Importantes:**
- `status = 'active'` - IA está atendendo
- `status = 'escalated'` - Foi escalonado para humano
- `tokens_used`, `cost` - Controle de custos
- `messages` - Histórico completo em formato OpenAI

#### 4. **ai_tools** - Ferramentas

```sql
CREATE TABLE ai_tools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    description TEXT,
    tool_type VARCHAR(50),          -- woocommerce, database, api, n8n, system
    function_schema JSON NOT NULL,  -- Schema OpenAI Function Calling
    config JSON,                    -- Configurações da tool
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Importantes:**
- `function_schema` - Definição completa no formato OpenAI
- `tool_type` - Categorização da tool
- `config` - Configurações específicas (URLs, credenciais, etc)

#### 5. **ai_agent_tools** - Relacionamento N:N

```sql
CREATE TABLE ai_agent_tools (
    ai_agent_id INT NOT NULL,
    ai_tool_id INT NOT NULL,
    PRIMARY KEY (ai_agent_id, ai_tool_id),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id),
    FOREIGN KEY (ai_tool_id) REFERENCES ai_tools(id)
);
```

#### 6. **messages** - Mensagens

```sql
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT NOT NULL,
    sender_type VARCHAR(50),        -- contact, agent, system
    sender_id INT NULL,
    content TEXT,
    ai_agent_id INT NULL,           -- Se foi enviada por IA
    external_id VARCHAR(255),       -- ID externo (WhatsApp, etc)
    status VARCHAR(50),             -- sent, delivered, read, failed
    metadata JSON,
    created_at TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id)
);
```

### Relacionamentos

```
conversations (1) ←─→ (1) ai_conversations
                ↓
              messages
              
ai_agents (N) ←─→ (N) ai_tools
       ↓
  ai_conversations
```

---

## 🔄 FLUXO COMPLETO

### 1. Cliente Envia Mensagem

```
Cliente → WhatsApp: "Olá, preciso de ajuda com meu pedido"
```

### 2. Webhook Recebe

```php
// QuepasaWebhookController::processWebhook()
POST /api/webhooks/quepasa/message
{
    "from": "5511999999999@s.whatsapp.net",
    "text": "Olá, preciso de ajuda com meu pedido",
    "timestamp": "2025-12-31T12:00:00Z"
}
```

### 3. Criar ou Buscar Conversa

```php
// ConversationService::create() ou findByContactAndChannel()
$conversationId = ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp',
    'whatsapp_account_id' => 7
]);
```

**O que acontece:**
1. Busca conversa existente aberta do contato
2. Se não existe, cria nova conversa
3. Determina funil/etapa padrão
4. Verifica atribuição automática

### 4. Atribuição Automática

```php
// ConversationService::create() linha 192-228

// PRIORIDADE 1: Agente do contato (histórico)
$agentId = ContactAgentService::shouldAutoAssignOnConversation($contactId);

// PRIORIDADE 2: Distribuição automática
if (!$agentId) {
    $assignedId = ConversationSettingsService::autoAssignConversation(
        0,
        $departmentId,
        $funnelId,
        $stageId
    );
    
    // Se ID for NEGATIVO → Agente de IA
    if ($assignedId < 0) {
        $aiAgentId = abs($assignedId);
        $agentId = null;
    } else {
        $agentId = $assignedId;
    }
}
```

**Resultado:**
- `agent_id` positivo = Agente humano
- `agent_id` negativo = Agente de IA (salvo como `$aiAgentId`)

### 5. Salvar Mensagem

```php
// ConversationService::sendMessage()
$messageId = Message::create([
    'conversation_id' => $conversationId,
    'sender_type' => 'contact',
    'content' => "Olá, preciso de ajuda com meu pedido",
    'external_id' => $externalId
]);
```

### 6. Processar com IA (se aplicável)

```php
// ConversationService::sendMessage() linha 1718-1750

// Verificar se tem AIConversation ativa
$aiConversation = AIConversation::getByConversationId($conversationId);

if ($aiConversation && $aiConversation['status'] === 'active') {
    // ✅ IA ATIVA! Processar mensagem
    
    // 1. Verificar intent (AI Branching)
    $intentDetected = AutomationService::detectIntentInClientMessage(
        $conversation,
        $content
    );
    
    if ($intentDetected) {
        // Intent detectado → Rotear para fluxo específico
        return $messageId;
    }
    
    // 2. Processar com IA
    AIAgentService::processMessage(
        $conversationId,
        $aiConversation['ai_agent_id'],
        $content
    );
}
```

### 7. IA Processa e Responde

```php
// AIAgentService::processMessage()
public static function processMessage(
    int $conversationId,
    int $agentId,
    string $message
): array {
    // 1. Obter contexto
    $conversation = Conversation::findWithRelations($conversationId);
    $contact = Contact::find($conversation['contact_id']);
    $agent = AIAgent::find($agentId);
    
    $context = [
        'conversation' => $conversation,
        'contact' => ['name' => $contact['name'], 'email' => $contact['email']],
        'user_message' => $message
    ];
    
    // 2. Chamar OpenAI
    $response = OpenAIService::processMessage(
        $conversationId,
        $agentId,
        $message,
        $context
    );
    
    // 3. Delay humanizado (opcional)
    $humanDelay = $agent['settings']['human_delay'] ?? 0;
    if ($humanDelay > 0) {
        sleep($humanDelay);
    }
    
    // 4. Enviar resposta ao cliente
    ConversationService::sendMessage(
        $conversationId,
        $response['content'],
        'agent',
        0, // sender_id = 0 para IA
        [],
        'text',
        $agentId // ai_agent_id
    );
    
    return $response;
}
```

### 8. Resposta Enviada ao Cliente

```php
// ConversationService::sendMessage() → QuepasaService::sendMessage()
POST https://whats.orbichat.com.br/send
{
    "chatId": "5511999999999@s.whatsapp.net",
    "text": "Olá! Claro, vou te ajudar com seu pedido. Qual o número do pedido?"
}
```

### 9. Notificação WebSocket

```php
WebSocket::notifyNewMessage($conversationId, $messageData);
WebSocket::notifyConversationUpdated($conversationId, $conversationData);
```

---

## 🤝 ATRIBUIÇÃO DE AGENTES

### Métodos de Distribuição

#### 1. **Manual**
Administrador atribui manualmente agente à conversa.

#### 2. **Agente do Contato** (Prioridade Máxima)
Se contato já teve conversa anterior, reatribui mesmo agente.

```php
ContactAgentService::shouldAutoAssignOnConversation($contactId);
```

#### 3. **Round-Robin**
Distribui para próximo agente na fila (último a receber).

```php
ConversationSettingsService::assignRoundRobin(
    $departmentId,
    $funnelId,
    $stageId,
    $includeAI = true  // ✅ Incluir agentes de IA
);
```

#### 4. **Por Carga (By Load)**
Atribui para agente com menor número de conversas ativas.

```php
ConversationSettingsService::assignByLoad(
    $departmentId,
    $funnelId,
    $stageId,
    $includeAI = true
);
```

#### 5. **Por Performance**
Atribui para agente com melhor performance (satisfação, tempo médio).

```php
ConversationSettingsService::assignByPerformance(
    $departmentId,
    $funnelId,
    $stageId,
    $includeAI = true
);
```

#### 6. **Por Porcentagem**
Distribui baseado em porcentagens configuradas.

```json
{
    "rules": [
        {"agent_id": 10, "percentage": 50},
        {"agent_id": 20, "percentage": 30},
        {"ai_agent_id": 5, "percentage": 20}
    ]
}
```

### Incluir Agentes de IA na Distribuição

Para incluir agentes de IA nos algoritmos de distribuição:

```php
// Configuração
ConversationSettingsService::updateSettings([
    'distribution' => [
        'method' => 'by_load',
        'assign_to_ai_agent' => true,  // ✅ HABILITAR IA
        'ai_agent_priority' => 'low'   // low, normal, high
    ]
]);

// Sistema faz automaticamente:
$agents = ConversationSettingsService::getAvailableAgents(
    $departmentId,
    $funnelId,
    $stageId,
    true  // includeAI
);

// Retorna array combinado:
[
    ['id' => 10, 'agent_type' => 'human', ...],
    ['ai_agent_id' => 5, 'agent_type' => 'ai', ...],
    ['id' => 20, 'agent_type' => 'human', ...]
]

// Agentes de IA recebem ID NEGATIVO
// Ex: -5 = AI Agent ID 5
```

---

## 🤖 PROCESSAMENTO COM IA

### 1. Fluxo OpenAI

```
AIAgentService::processMessage()
    ↓
OpenAIService::processMessage()
    ↓
1. Obter configuração do agente
2. Verificar rate limiting e custos
3. Obter tools disponíveis
4. Construir mensagens (histórico + contexto)
5. Chamar OpenAI API
6. Processar resposta:
   a. Se tem tool_calls → Executar tools
   b. Se não → Resposta direta
7. Calcular custo e tokens
8. Salvar em ai_conversations
9. Retornar resposta
```

### 2. Construção de Mensagens

```php
// OpenAIService::buildMessages()
$messages = [
    // 1. System Prompt
    [
        'role' => 'system',
        'content' => $agent['prompt'] . "\n\n" . $systemContext
    ],
    
    // 2. Histórico (últimas 10 mensagens)
    [
        'role' => 'user',
        'content' => 'Boa tarde'
    ],
    [
        'role' => 'assistant',
        'content' => 'Boa tarde! Como posso ajudar?'
    ],
    
    // 3. Mensagem Atual
    [
        'role' => 'user',
        'content' => 'Preciso de ajuda com meu pedido'
    ]
];
```

### 3. Contexto do Sistema

O contexto automático inclui:

```
Informações do Contato:
- Nome: João Silva
- Email: joao@email.com
- Telefone: +55 11 99999-9999

Informações da Conversa:
- ID: 123
- Canal: WhatsApp
- Status: open
- Criada em: 2025-12-31 10:00

Informações Adicionais:
[dados específicos do agente/tools]
```

---

## 🔌 INTEGRAÇÃO OPENAI

### 1. Configuração

**API Key** é obtida de:
1. Tabela `settings` (chave: `openai_api_key`)
2. Variável de ambiente `OPENAI_API_KEY`

```php
$apiKey = \App\Models\Setting::get('openai_api_key');
```

### 2. Payload da Requisição

```php
$payload = [
    'model' => 'gpt-4',                // ou gpt-3.5-turbo
    'messages' => $messages,           // Array de mensagens
    'temperature' => 0.7,              // 0.0 a 2.0
    'max_tokens' => 2000,
    'tools' => $functions,             // Array de function schemas
    'tool_choice' => 'auto'            // auto, none, ou {type: function, function: {name: ...}}
];

// Fazer requisição
$response = self::makeRequest($apiKey, $payload);
```

### 3. Function Calling

Quando IA decide usar uma tool:

**Resposta da OpenAI:**
```json
{
  "choices": [{
    "message": {
      "role": "assistant",
      "content": null,
      "tool_calls": [{
        "id": "call_abc123",
        "type": "function",
        "function": {
          "name": "buscar_pedido_woocommerce",
          "arguments": "{\"order_id\": 12345}"
        }
      }]
    }
  }]
}
```

**Execução da Tool:**
```php
// OpenAIService::executeToolCalls()
$result = AIToolExecutor::execute(
    'buscar_pedido_woocommerce',
    ['order_id' => 12345],
    $context
);

// Resultado:
[
    'success' => true,
    'order' => [
        'id' => 12345,
        'status' => 'processing',
        'total' => 'R$ 299,90'
    ]
]
```

**Reenviar com Resultados:**
```php
// Adicionar mensagem do assistente com tool calls
$messages[] = $assistantMessage;

// Adicionar resultado da tool
$messages[] = [
    'role' => 'tool',
    'tool_call_id' => 'call_abc123',
    'content' => json_encode($result)
];

// Reenviar para OpenAI
$finalResponse = self::makeRequest($apiKey, [
    'model' => 'gpt-4',
    'messages' => $messages
]);

// IA responde com base no resultado:
// "Seu pedido #12345 está em processamento. O valor total é R$ 299,90."
```

### 4. Cálculo de Custo

```php
// OpenAIService::calculateCost()
$prices = [
    'gpt-4' => [
        'prompt' => 0.03,      // $0.03 por 1K tokens
        'completion' => 0.06   // $0.06 por 1K tokens
    ],
    'gpt-3.5-turbo' => [
        'prompt' => 0.0015,    // $0.0015 por 1K tokens
        'completion' => 0.002  // $0.002 por 1K tokens
    ]
];

$cost = ($promptTokens / 1000) * $prices[$model]['prompt'] +
        ($completionTokens / 1000) * $prices[$model]['completion'];

// Exemplo:
// GPT-4: 1000 prompt tokens + 500 completion tokens
// $0.03 + $0.03 = $0.06
```

### 5. Rate Limiting

```php
// AICostControlService::canProcessMessage()
$check = AICostControlService::canProcessMessage($agentId);

if (!$check['allowed']) {
    throw new \Exception($check['reason']);
}

// Verifica:
// - Limite de requisições por minuto
// - Limite de custo por hora/dia
// - Limites por agente
```

---

## 🛠️ SISTEMA DE TOOLS

### 1. Tipos de Tools

- **system** - Funções do sistema (criar conversa, atribuir agente)
- **woocommerce** - Integração WooCommerce (buscar pedidos, produtos)
- **database** - Consultas ao banco de dados
- **api** - Chamadas HTTP para APIs externas
- **n8n** - Workflows do N8N
- **document** - Busca em base de conhecimento

### 2. Function Schema (Formato OpenAI)

```json
{
    "name": "buscar_pedido_woocommerce",
    "description": "Busca informações de um pedido no WooCommerce pelo ID",
    "parameters": {
        "type": "object",
        "properties": {
            "order_id": {
                "type": "integer",
                "description": "ID do pedido no WooCommerce"
            }
        },
        "required": ["order_id"]
    }
}
```

### 3. Criar Tool

```php
AIToolService::create([
    'name' => 'Buscar Pedido WooCommerce',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca pedido por ID',
    'tool_type' => 'woocommerce',
    'function_schema' => [
        'name' => 'buscar_pedido_woocommerce',
        'description' => '...',
        'parameters' => [...]
    ],
    'config' => [
        'endpoint' => 'orders',
        'method' => 'GET'
    ],
    'enabled' => true
]);
```

### 4. Atribuir Tool a Agente

```php
$agent = AIAgent::find(5);
$agent->addTool(10);  // Tool ID 10
```

### 5. Execução de Tool

```php
// AIToolExecutor::execute()
switch ($tool['tool_type']) {
    case 'woocommerce':
        return WooCommerceToolExecutor::execute($tool, $arguments);
        
    case 'n8n':
        return N8NToolExecutor::execute($tool, $arguments);
        
    case 'database':
        return DatabaseToolExecutor::execute($tool, $arguments);
        
    case 'api':
        return ApiToolExecutor::execute($tool, $arguments);
        
    case 'system':
        return SystemToolExecutor::execute($tool, $arguments);
}
```

### 6. Tool N8N (Caso Especial)

Tools N8N podem retornar **resposta direta** sem reenviar para OpenAI:

```json
{
    "success": true,
    "use_raw_response": true,
    "raw_message": "Seu pedido está em processamento!",
    "workflow_id": "abc-123"
}
```

Se `use_raw_response = true`, o sistema usa `raw_message` diretamente, economizando tokens.

---

## 📊 LOGS E MONITORAMENTO

### 1. Tipos de Logs

#### Aplicação Geral
```
logs/application.log
```

#### Conversas
```
logs/conversas.log
```

#### AI Agents
```
logs/ai-agents.log
```

#### AI Tools
```
logs/ai-tools.log
```

#### Automação
```
logs/automation.log
```

### 2. Logs nos Arquivos

**Exemplo - logs/ai-agents.log:**
```
[2025-12-31 12:00:00] === AIAgentService::processMessage INÍCIO === conv=474, agent=21, msgLen=25
[2025-12-31 12:00:00] AIAgentService::processMessage - Chamando OpenAIService::processMessage
[2025-12-31 12:00:01] AIAgentService::processMessage - OpenAI respondeu (contentLen=150)
[2025-12-31 12:00:01] AIAgentService::processMessage - Resposta enviada com sucesso
```

**Exemplo - logs/ai-tools.log:**
```
[2025-12-31 12:00:00] [TOOL EXECUTION] Iniciando execução de 1 tool calls para conversationId=474, agentId=21
[2025-12-31 12:00:00] [TOOL EXECUTION] Tool Call: id=call_abc123, function=buscar_pedido_woocommerce, args={"order_id":12345}
[2025-12-31 12:00:01] [TOOL EXECUTION] Tool executada com sucesso: buscar_pedido_woocommerce
```

### 3. Debug no Console do Sistema

Acesse: **Sistema → Logs**

Mostra logs em tempo real de:
- Aplicação
- Conversas
- Quepasa (WhatsApp)
- Automação
- AI Agent
- AI Tools

### 4. Dados Salvos em `ai_conversations`

```sql
SELECT 
    id,
    conversation_id,
    ai_agent_id,
    tokens_used,
    cost,
    tools_used,
    messages,
    created_at,
    updated_at
FROM ai_conversations
WHERE conversation_id = 474;
```

**Resultado:**
```json
{
    "id": 151,
    "conversation_id": 474,
    "ai_agent_id": 21,
    "tokens_used": 1174,
    "tokens_prompt": 1126,
    "tokens_completion": 48,
    "cost": 0.0018,
    "tools_used": [
        {
            "tool": "n8n-portfel",
            "call": [],
            "result": {"success": true, ...},
            "timestamp": "2025-12-31 13:19:54"
        }
    ],
    "messages": [
        {"role": "user", "content": "Boa tarde", "timestamp": "..."},
        {"role": "assistant", "content": "Boa tarde! Como posso ajudar?", "timestamp": "..."}
    ]
}
```

---

## ⚙️ CONFIGURAÇÕES

### 1. Configurações de Distribuição

```php
// Salvar em settings table
Setting::set('conversation_settings', json_encode([
    'distribution' => [
        'enabled' => true,
        'method' => 'by_load',  // round_robin, by_load, by_performance, percentage
        'assign_to_ai_agent' => true,
        'ai_agent_priority' => 'normal',  // low, normal, high
        'only_business_hours' => false
    ],
    'limits' => [
        'max_conversations_per_agent' => 10,
        'max_conversations_per_ai_agent' => 0  // 0 = ilimitado
    ]
]));
```

### 2. Configurações OpenAI

```php
// API Key
Setting::set('openai_api_key', 'sk-...');

// Rate Limiting
Setting::set('ai_rate_limiting', json_encode([
    'max_requests_per_minute' => 60,
    'max_cost_per_hour' => 10.00,  // USD
    'max_cost_per_day' => 100.00
]));
```

### 3. Configurações por Agente

```php
AIAgent::update($agentId, [
    'settings' => [
        'human_delay' => 2,  // Delay de 2s antes de responder
        'max_messages_per_conversation' => 50,
        'auto_escalate_after_minutes' => 30,
        'escalate_keywords' => ['falar com humano', 'atendente'],
        'business_hours_only' => false
    ]
]);
```

### 4. AI Branching (Intents)

```php
// Salvar em conversation.metadata
Conversation::update($conversationId, [
    'metadata' => json_encode([
        'ai_branching_active' => true,
        'ai_branching_automation_id' => 5,
        'ai_intents' => [
            [
                'intent' => 'falar_com_humano',
                'keywords' => ['falar com humano', 'atendente'],
                'description' => 'Cliente quer falar com humano',
                'exit_message' => 'Te transferindo para um especialista!',
                'target_node_id' => '31'
            ]
        ],
        'ai_intent_confidence' => 0.35,  // 35% de confiança mínima
        'ai_intent_semantic_enabled' => true,
        'ai_max_interactions' => 25
    ])
]);
```

---

## 💡 EXEMPLOS PRÁTICOS

### Exemplo 1: Criar Agente de Pós-Venda

```php
use App\Services\AIAgentService;
use App\Services\AIToolService;

// 1. Criar agente
$agentId = AIAgentService::create([
    'name' => 'Pos Venda Portfel',
    'description' => 'Agente especializado em pós-venda',
    'agent_type' => 'CS',
    'prompt' => 'Você é um assistente de pós-venda da Portfel Store. 
                 Seja educado, prestativo e resolva problemas de pedidos.',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'max_conversations' => 10,
    'enabled' => true
]);

// 2. Criar tool de busca de pedidos
$toolId = AIToolService::create([
    'name' => 'Buscar Pedido WooCommerce',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca informações de pedido',
    'tool_type' => 'woocommerce',
    'function_schema' => [
        'name' => 'buscar_pedido_woocommerce',
        'description' => 'Busca pedido por ID',
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
        'endpoint' => 'orders',
        'method' => 'GET'
    ],
    'enabled' => true
]);

// 3. Atribuir tool ao agente
$agent = \App\Models\AIAgent::find($agentId);
$agent->addTool($toolId);

// Pronto! Agente está ativo e pode buscar pedidos automaticamente.
```

### Exemplo 2: Atribuir IA a Conversa Existente

```php
use App\Services\ConversationAIService;

// Conversa já existe, adicionar IA
ConversationAIService::addAIAgent(474, [
    'ai_agent_id' => 21,
    'process_immediately' => true,    // Processar última mensagem agora
    'assume_conversation' => true,    // Remover agente humano se houver
    'only_if_unassigned' => false
]);

// IA vai processar a última mensagem do contato automaticamente
```

### Exemplo 3: Remover IA e Atribuir Humano

```php
use App\Services\ConversationAIService;

// Remover IA e atribuir para humano
ConversationAIService::removeAIAgent(474, [
    'assign_to_human' => true,
    'human_agent_id' => 10,  // Agente específico
    'reason' => 'Cliente solicitou atendimento humano'
]);

// OU atribuição automática:
ConversationAIService::removeAIAgent(474, [
    'assign_to_human' => true,
    'human_agent_id' => null,  // Sistema escolhe automaticamente
    'reason' => 'Escalado pela IA'
]);
```

### Exemplo 4: Configurar Distribuição com IA

```php
use App\Services\ConversationSettingsService;

ConversationSettingsService::updateSettings([
    'distribution' => [
        'enabled' => true,
        'method' => 'by_load',
        'assign_to_ai_agent' => true,
        'ai_agent_priority' => 'high',  // IA tem prioridade
        'filters' => [
            'department_id' => null,     // Todos setores
            'funnel_id' => 4,            // Apenas funil 4
            'funnel_stage_id' => null    // Todas etapas
        ]
    ],
    'limits' => [
        'max_conversations_per_agent' => 10,
        'max_conversations_per_ai_agent' => 0  // Ilimitado
    ]
]);

// Agora novas conversas no funil 4 serão distribuídas
// por carga, priorizando agentes de IA
```

### Exemplo 5: Monitorar Custos de IA

```php
use App\Models\AIConversation;

// Buscar conversas por período
$sql = "SELECT 
            ai_agent_id,
            COUNT(*) as total_conversations,
            SUM(tokens_used) as total_tokens,
            SUM(cost) as total_cost
        FROM ai_conversations
        WHERE created_at >= ?
          AND created_at <= ?
        GROUP BY ai_agent_id";

$stats = \App\Helpers\Database::fetchAll($sql, [
    '2025-12-01 00:00:00',
    '2025-12-31 23:59:59'
]);

// Resultado:
// [
//     ['ai_agent_id' => 21, 'total_conversations' => 150, 'total_tokens' => 45000, 'total_cost' => 0.68],
//     ['ai_agent_id' => 22, 'total_conversations' => 89, 'total_tokens' => 28000, 'total_cost' => 0.42]
// ]
```

---

## 📈 DIAGRAMAS

### Fluxo de Mensagem com IA

```
┌──────────────┐
│   Cliente    │
│  (WhatsApp)  │
└──────┬───────┘
       │
       │ "Preciso de ajuda"
       ▼
┌──────────────┐
│   Webhook    │
│   Quepasa    │
└──────┬───────┘
       │
       ▼
┌──────────────────────────┐
│ ConversationService      │
│ ::sendMessage()          │
└──────┬───────────────────┘
       │
       │ Tem AIConversation ativa?
       │
       ├─── SIM ──────────────────┐
       │                           │
       │                           ▼
       │                    ┌──────────────────┐
       │                    │ AIAgentService   │
       │                    │ ::processMessage │
       │                    └────────┬─────────┘
       │                             │
       │                             ▼
       │                    ┌──────────────────┐
       │                    │ OpenAIService    │
       │                    │ ::processMessage │
       │                    └────────┬─────────┘
       │                             │
       │                             ├─ Tool calls? ─┐
       │                             │                │
       │                             │ SIM            │ NÃO
       │                             │                │
       │                             ▼                ▼
       │                    ┌─────────────┐   ┌──────────┐
       │                    │ Execute     │   │ Resposta │
       │                    │ Tools       │   │ Direta   │
       │                    └──────┬──────┘   └────┬─────┘
       │                           │               │
       │                           └───────┬───────┘
       │                                   │
       │                                   ▼
       │                           ┌────────────────┐
       │                           │ Resposta Final │
       │                           └────────┬───────┘
       │                                    │
       └────────────────────────────────────┘
                                            │
                                            ▼
                                    ┌───────────────┐
                                    │ Send WhatsApp │
                                    └───────┬───────┘
                                            │
                                            ▼
                                        Cliente
```

### Arquitetura de Dados

```
┌─────────────────────────────────────────────────────────────┐
│                     CONVERSATIONS                            │
│  id | contact_id | agent_id | status | metadata | ...       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ 1:1
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   AI_CONVERSATIONS                           │
│  id | conversation_id | ai_agent_id | status | cost | ...   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ N:1
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                      AI_AGENTS                               │
│  id | name | prompt | model | max_conversations | ...       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ N:M
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  AI_AGENT_TOOLS                              │
│  ai_agent_id | ai_tool_id                                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ N:1
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                      AI_TOOLS                                │
│  id | name | slug | tool_type | function_schema | ...       │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎓 CONCLUSÃO

Este sistema oferece uma plataforma completa de atendimento multicanal com IA integrada, permitindo:

✅ **Atendimento Híbrido** - Humanos + IA trabalhando juntos  
✅ **Escalabilidade** - IA pode atender ilimitadas conversas  
✅ **Inteligência** - OpenAI GPT-4 com Function Calling  
✅ **Controle Total** - Logs, custos, limites configuráveis  
✅ **Flexibilidade** - Tools personalizadas, prompts ajustáveis  
✅ **Tempo Real** - WebSocket para atualizações instantâneas

### Próximos Passos

1. **Análise de Sentimento** - Detectar emoções do cliente
2. **Treinamento de Modelos** - Fine-tuning específico
3. **Métricas Avançadas** - CSAT, NPS automáticos
4. **Integrações** - Mais canais e ferramentas
5. **IA Multimodal** - Processar imagens, voz, vídeo

---

**Documentação criada em:** 31/12/2025  
**Versão:** 1.0  
**Autor:** Sistema de Documentação Automática
