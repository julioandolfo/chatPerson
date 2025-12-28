# 📚 DOCUMENTAÇÃO COMPLETA - SISTEMA DE AI AGENTS E AI TOOLS

**Data**: 2025-01-27  
**Status**: Sistema 95% Implementado

---

## ⚠️ IMPORTANTE: TIPOS DE AGENTES DE IA

Este sistema possui **DOIS TIPOS** de agentes de IA:

1. **Agentes de IA para Automações** (este documento)
   - Funcionam nas automações
   - Atendem conversas em tempo real
   - Processam mensagens quando recebidas
   - Integrados com sistema de distribuição

2. **Agentes de IA para Kanban** (documento separado)
   - Funcionam de forma agendada/periódica
   - Analisam múltiplas conversas de funis/etapas específicas
   - Executam ações baseadas em condições
   - **Ver**: `PLANO_AGENTES_IA_KANBAN.md` para detalhes completos

**Este documento trata APENAS dos Agentes de IA para Automações.**

---

## 📋 ÍNDICE

1. [Visão Geral](#visão-geral)
2. [Estrutura de Dados](#estrutura-de-dados)
3. [AI Agents - Funcionamento Completo](#ai-agents---funcionamento-completo)
4. [AI Tools - Funcionamento Completo](#ai-tools---funcionamento-completo)
5. [Fluxo de Processamento](#fluxo-de-processamento)
6. [Integração com OpenAI](#integração-com-openai)
7. [Tipos de Tools Disponíveis](#tipos-de-tools-disponíveis)
8. [Exemplos Práticos](#exemplos-práticos)

---

## 🎯 VISÃO GERAL

O sistema de **AI Agents** e **AI Tools** permite criar agentes virtuais especializados que podem:
- Atender conversas automaticamente usando IA (OpenAI GPT-4, GPT-3.5-turbo, etc)
- Executar ações no sistema através de **tools** (ferramentas)
- Integrar com serviços externos (WooCommerce, N8N, APIs, etc)
- Processar mensagens em tempo real
- Escalar para agentes humanos quando necessário

### Componentes Principais

1. **AI Agents** (`ai_agents`): Agentes virtuais com prompts personalizados
2. **AI Tools** (`ai_tools`): Ferramentas que os agentes podem usar
3. **AI Agent Tools** (`ai_agent_tools`): Relação entre agentes e tools
4. **AI Conversations** (`ai_conversations`): Logs e histórico de conversas com IA

---

## 🗄️ ESTRUTURA DE DADOS

### Tabela: `ai_agents`

Armazena os agentes de IA disponíveis no sistema.

```sql
CREATE TABLE ai_agents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,                    -- Nome do agente
    description TEXT NULL,                         -- Descrição
    agent_type VARCHAR(50) NOT NULL,               -- Tipo: SDR, CS, CLOSER, FOLLOWUP, SUPPORT, ONBOARDING, GENERAL
    prompt TEXT NOT NULL,                          -- Prompt do sistema para OpenAI
    model VARCHAR(100) DEFAULT 'gpt-4',          -- Modelo OpenAI (gpt-4, gpt-3.5-turbo, etc)
    temperature DECIMAL(3,2) DEFAULT 0.7,         -- Temperature (0.0 a 2.0)
    max_tokens INT DEFAULT 2000,                  -- Máximo de tokens na resposta
    enabled BOOLEAN DEFAULT TRUE,                 -- Se está ativo
    max_conversations INT NULL,                   -- Limite de conversas simultâneas
    current_conversations INT DEFAULT 0,          -- Conversas atuais
    settings JSON NULL,                            -- Configurações extras (welcome_message, etc)
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Importantes:**
- `agent_type`: Define o tipo de agente (SDR, CS, CLOSER, etc)
- `prompt`: Instruções do sistema para a IA (comportamento, tom, regras)
- `model`: Modelo OpenAI a usar (afeta custo e qualidade)
- `temperature`: Criatividade da resposta (0.0 = determinístico, 2.0 = muito criativo)
- `max_conversations`: Limite de conversas simultâneas (NULL = sem limite)
- `settings`: JSON com configurações extras (ex: `{"welcome_message": "Olá!"}`)

### Tabela: `ai_tools`

Armazena as ferramentas disponíveis para os agentes.

```sql
CREATE TABLE ai_tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,                   -- Nome da tool
    slug VARCHAR(100) NOT NULL UNIQUE,            -- Slug único (usado na função)
    description TEXT NULL,                         -- Descrição
    tool_type VARCHAR(50) NOT NULL,               -- Tipo: system, woocommerce, database, n8n, document, api, followup
    function_schema JSON NOT NULL,                 -- Schema OpenAI Function Calling
    config JSON NULL,                              -- Configuração (URLs, credenciais, etc)
    enabled BOOLEAN DEFAULT TRUE,                  -- Se está ativa
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Campos Importantes:**
- `slug`: Nome da função que a IA chamará (ex: `buscar_pedido_woocommerce`)
- `function_schema`: Schema JSON no formato OpenAI Function Calling
- `config`: Configurações específicas (ex: URL do WooCommerce, credenciais)
- `tool_type`: Categoria da tool (define qual método executar)

**Exemplo de `function_schema`:**
```json
{
  "type": "function",
  "function": {
    "name": "buscar_pedido_woocommerce",
    "description": "Busca informações de um pedido do WooCommerce",
    "parameters": {
      "type": "object",
      "properties": {
        "order_id": {
          "type": "integer",
          "description": "ID do pedido"
        }
      },
      "required": ["order_id"]
    }
  }
}
```

### Tabela: `ai_agent_tools`

Relação muitos-para-muitos entre agentes e tools.

```sql
CREATE TABLE ai_agent_tools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ai_agent_id INT NOT NULL,                      -- ID do agente
    ai_tool_id INT NOT NULL,                       -- ID da tool
    config JSON NULL,                               -- Config específica para este agente
    enabled BOOLEAN DEFAULT TRUE,                  -- Se está habilitada para este agente
    created_at TIMESTAMP,
    UNIQUE KEY unique_agent_tool (ai_agent_id, ai_tool_id),
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (ai_tool_id) REFERENCES ai_tools(id) ON DELETE CASCADE
);
```

**Funcionalidade:**
- Permite que cada agente tenha um conjunto específico de tools
- Cada relação pode ter configuração própria (`config`)
- Tools podem ser habilitadas/desabilitadas por agente

### Tabela: `ai_conversations`

Logs e histórico de conversas com agentes de IA.

```sql
CREATE TABLE ai_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,                  -- ID da conversa
    ai_agent_id INT NOT NULL,                      -- ID do agente usado
    messages JSON NULL,                             -- Histórico de mensagens
    tools_used JSON NULL,                           -- Tools utilizadas
    tokens_used INT DEFAULT 0,                     -- Total de tokens
    tokens_prompt INT DEFAULT 0,                   -- Tokens do prompt
    tokens_completion INT DEFAULT 0,               -- Tokens da resposta
    cost DECIMAL(10,4) DEFAULT 0,                  -- Custo em USD
    status VARCHAR(50) DEFAULT 'active',          -- active, completed, escalated, removed
    escalated_to_user_id INT NULL,                 -- Se escalado, ID do usuário
    metadata JSON NULL,                             -- Metadados extras
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id)
);
```

**Campos Importantes:**
- `messages`: Histórico completo de mensagens (user + assistant)
- `tools_used`: Array de tools utilizadas com argumentos e resultados
- `tokens_used`: Total de tokens consumidos (afeta custo)
- `cost`: Custo calculado em USD
- `status`: Estado da conversa de IA

---

## 🤖 AI AGENTS - FUNCIONAMENTO COMPLETO

### 1. Criação de Agente

**Arquivo**: `app/Services/AIAgentService.php`

```php
AIAgentService::create([
    'name' => 'Agente de Suporte',
    'description' => 'Atende dúvidas técnicas',
    'agent_type' => 'SUPPORT',
    'prompt' => 'Você é um agente de suporte técnico...',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'enabled' => true,
    'max_conversations' => 10,
    'settings' => [
        'welcome_message' => 'Olá! Como posso ajudar?'
    ]
]);
```

**Validações:**
- `agent_type` deve ser: SDR, CS, CLOSER, FOLLOWUP, SUPPORT, ONBOARDING, GENERAL
- `temperature` entre 0.0 e 2.0
- `max_tokens` mínimo 1
- `prompt` obrigatório

### 2. Atribuição de Tools

**Arquivo**: `app/Models/AIAgent.php`

```php
// Adicionar tool ao agente
AIAgent::addTool($agentId, $toolId, $config = [], $enabled = true);

// Remover tool
AIAgent::removeTool($agentId, $toolId);

// Obter tools do agente
$tools = AIAgent::getTools($agentId);
```

**Processo:**
1. Tool é adicionada à tabela `ai_agent_tools`
2. Configuração específica pode ser passada (`config`)
3. Tool pode ser habilitada/desabilitada por agente

### 3. Processamento de Conversas

**Arquivo**: `app/Services/AIAgentService.php`

#### Quando uma conversa é atribuída a um agente de IA:

```php
AIAgentService::processConversation($conversationId, $agentId);
```

**Fluxo:**
1. Verifica se há mensagens do contato
2. Se houver, processa a última mensagem
3. Se não houver, envia mensagem de boas-vindas (se configurado)

#### Quando uma mensagem é recebida:

**Arquivo**: `app/Services/ConversationService.php` (linha 1347)

```php
// Detectado automaticamente quando mensagem é do contato
if ($senderType === 'contact') {
    $aiConversation = AIConversation::getByConversationId($conversationId);
    if ($aiConversation && $aiConversation['status'] === 'active') {
        AIAgentService::processMessage(
            $conversationId,
            $aiConversation['ai_agent_id'],
            $content
        );
    }
}
```

**Processo:**
1. Busca conversa de IA ativa
2. Chama `AIAgentService::processMessage()`
3. Processa mensagem com OpenAI
4. Envia resposta automaticamente

### 4. Limites de Conversas

**Arquivo**: `app/Models/AIAgent.php`

```php
// Verificar se pode receber mais conversas
$canReceive = AIAgent::canReceiveMoreConversations($agentId);

// Atualizar contagem
AIAgent::updateConversationsCount($agentId);
```

**Lógica:**
- Se `max_conversations` é NULL → sem limite
- Se `current_conversations < max_conversations` → pode receber
- Contagem é atualizada automaticamente quando conversas são criadas/removidas

---

## 🛠️ AI TOOLS - FUNCIONAMENTO COMPLETO

### 1. Criação de Tool

**Arquivo**: `app/Services/AIToolService.php`

```php
AIToolService::create([
    'name' => 'Buscar Pedido WooCommerce',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca informações de pedido',
    'tool_type' => 'woocommerce',
    'function_schema' => [
        'type' => 'function',
        'function' => [
            'name' => 'buscar_pedido_woocommerce',
            'description' => 'Busca informações de um pedido',
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
        ]
    ],
    'config' => [
        'woocommerce_url' => 'https://loja.com',
        'consumer_key' => 'ck_xxx',
        'consumer_secret' => 'cs_xxx'
    ],
    'enabled' => true
]);
```

**Validações:**
- `tool_type` deve ser: woocommerce, database, n8n, document, system, api, followup
- `function_schema` deve ser um array válido no formato OpenAI
- `slug` deve ser único

### 2. Execução de Tools

**Arquivo**: `app/Services/OpenAIService.php`

#### Fluxo de Execução:

1. **IA decide usar uma tool** → Retorna `tool_calls` na resposta
2. **Sistema executa tools** → `executeToolCalls()`
3. **Resultados são enviados de volta** → Reenvia para OpenAI
4. **IA gera resposta final** → Com base nos resultados

```php
// Quando IA chama uma tool
$toolCalls = $assistantMessage['tool_calls'] ?? null;

if (!empty($toolCalls)) {
    // Executar todas as tools chamadas
    $functionResults = self::executeToolCalls(
        $toolCalls, 
        $conversationId, 
        $agentId, 
        $context
    );
    
    // Adicionar resultados ao histórico
    foreach ($functionResults as $result) {
        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $result['tool_call_id'],
            'content' => json_encode($result['result'], JSON_UNESCAPED_UNICODE)
        ];
    }
    
    // Reenviar para OpenAI com resultados
    $response = self::makeRequest($apiKey, $payload);
}
```

#### Validações de Segurança:

1. **Tool existe e está ativa**
2. **Tool está atribuída ao agente**
3. **Validação de argumentos** (conforme schema)
4. **Validação de permissões** (ex: tabelas permitidas no Database Tool)

### 3. Tipos de Tools e Execução

**Arquivo**: `app/Services/OpenAIService.php` (método `executeTool`)

Cada tipo de tool tem um método específico de execução:

```php
switch ($toolType) {
    case 'system':
        return self::executeSystemTool($tool, $arguments, $conversationId, $context);
    case 'followup':
        return self::executeFollowupTool($tool, $arguments, $conversationId, $context);
    case 'woocommerce':
        return self::executeWooCommerceTool($tool, $arguments, $config);
    case 'database':
        return self::executeDatabaseTool($tool, $arguments, $config);
    case 'n8n':
        return self::executeN8NTool($tool, $arguments, $config);
    case 'api':
        return self::executeAPITool($tool, $arguments, $config);
    case 'document':
        return self::executeDocumentTool($tool, $arguments, $config);
}
```

---

## 🔄 FLUXO DE PROCESSAMENTO COMPLETO

### Cenário: Mensagem do Contato em Conversa com Agente de IA

```
1. Contato envia mensagem
   ↓
2. ConversationService::sendMessage() detecta mensagem do contato
   ↓
3. Verifica se conversa tem agente de IA ativo
   ↓
4. AIAgentService::processMessage() é chamado
   ↓
5. OpenAIService::processMessage() é chamado
   ↓
6. Sistema monta contexto:
   - Prompt do agente
   - Informações do contato
   - Histórico de mensagens (últimas 10)
   - Tools disponíveis do agente
   ↓
7. Chama OpenAI API com:
   - Modelo configurado
   - Messages (system + histórico + mensagem atual)
   - Tools (function schemas)
   ↓
8. OpenAI retorna resposta:
   - Se há tool_calls → Executa tools
   - Se não há → Resposta direta
   ↓
9. Se houve tool_calls:
   a. Executa cada tool chamada
   b. Adiciona resultados ao histórico
   c. Reenvia para OpenAI
   d. Recebe resposta final
   ↓
10. Registra em ai_conversations:
    - Tokens usados
    - Custo calculado
    - Tools utilizadas
    - Mensagens
   ↓
11. Envia resposta ao contato via ConversationService::sendMessage()
   ↓
12. Notifica via WebSocket
```

### Exemplo Prático:

**Mensagem do Contato:**
> "Quero saber o status do pedido #12345"

**Processamento:**

1. **IA recebe mensagem** com contexto completo
2. **IA decide usar tool** `buscar_pedido_woocommerce` com `order_id: 12345`
3. **Sistema executa tool** → Busca pedido no WooCommerce
4. **Resultado retornado:**
   ```json
   {
     "success": true,
     "order": {
       "id": 12345,
       "status": "processing",
       "total": "R$ 299,90"
     }
   }
   ```
5. **IA recebe resultado** e gera resposta:
   > "Seu pedido #12345 está em processamento. O valor total é R$ 299,90. Deve ser enviado em breve!"

6. **Resposta é enviada** ao contato
7. **Log registrado** em `ai_conversations`

---

## 🔌 INTEGRAÇÃO COM OPENAI

### 1. Configuração

**Arquivo**: `app/Services/OpenAIService.php`

```php
// API Key é obtida de:
// 1. Settings table (chave: openai_api_key)
// 2. Variável de ambiente OPENAI_API_KEY
$apiKey = Setting::get('openai_api_key');
```

### 2. Montagem do Payload

```php
$payload = [
    'model' => $agent['model'],              // gpt-4, gpt-3.5-turbo, etc
    'messages' => $messages,                 // Array de mensagens
    'temperature' => $agent['temperature'],  // 0.0 a 2.0
    'max_tokens' => $agent['max_tokens'],    // Limite de tokens
    'tools' => $functions                    // Array de function schemas
];
```

### 3. Construção de Mensagens

**Arquivo**: `app/Services/OpenAIService.php` (método `buildMessages`)

```php
$messages = [
    [
        'role' => 'system',
        'content' => $systemPrompt  // Prompt do agente + contexto
    ],
    // ... mensagens do histórico (últimas 10)
    [
        'role' => 'user',
        'content' => $userMessage  // Mensagem atual do contato
    ]
];
```

### 4. Function Calling

Quando a IA decide usar uma tool:

```json
{
  "role": "assistant",
  "content": null,
  "tool_calls": [
    {
      "id": "call_abc123",
      "type": "function",
      "function": {
        "name": "buscar_pedido_woocommerce",
        "arguments": "{\"order_id\": 12345}"
      }
    }
  ]
}
```

### 5. Resposta com Resultados

Após executar tools, reenvia com resultados:

```json
{
  "role": "tool",
  "tool_call_id": "call_abc123",
  "content": "{\"success\": true, \"order\": {...}}"
}
```

### 6. Cálculo de Custo

**Arquivo**: `app/Services/OpenAIService.php` (método `calculateCost`)

```php
// Preços por 1K tokens (2024)
$prices = [
    'gpt-4' => [
        'prompt' => 0.03,      // $0.03 por 1K tokens
        'completion' => 0.06   // $0.06 por 1K tokens
    ],
    'gpt-3.5-turbo' => [
        'prompt' => 0.0015,
        'completion' => 0.002
    ]
];

$cost = ($promptTokens / 1000) * $prices[$model]['prompt'] +
        ($completionTokens / 1000) * $prices[$model]['completion'];
```

---

## 🛠️ TIPOS DE TOOLS DISPONÍVEIS

### 1. System Tools

**Tipo**: `system`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeSystemTool`)

#### Tools Disponíveis:

1. **`buscar_conversas_anteriores`**
   - Busca últimas 5 conversas do mesmo contato
   - Sem parâmetros (usa contexto da conversa)

2. **`buscar_informacoes_contato`**
   - Busca dados completos do contato atual
   - Sem parâmetros (usa contexto da conversa)

3. **`adicionar_tag` / `adicionar_tag_conversa`**
   - Adiciona tag à conversa
   - Parâmetros: `tag` (string) ou `tag_id` (integer)

4. **`mover_para_estagio`**
   - Move conversa para estágio do funil
   - Parâmetros: `stage_id` (integer, obrigatório)

5. **`escalar_para_humano`**
   - Escala conversa para agente humano
   - Sem parâmetros
   - Marca status como 'open' e remove agente de IA

### 2. Followup Tools

**Tipo**: `followup`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeFollowupTool`)

#### Tools Disponíveis:

1. **`verificar_status_conversa`**
   - Verifica status atual e última mensagem
   - Retorna: status, última mensagem, timestamps

2. **`verificar_ultima_interacao`**
   - Verifica quando foi última interação
   - Retorna: tempo decorrido (minutos, horas, dias)

### 3. WooCommerce Tools

**Tipo**: `woocommerce`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeWooCommerceTool`)

#### Configuração Necessária:

```json
{
  "woocommerce_url": "https://loja.com",
  "consumer_key": "ck_xxx",
  "consumer_secret": "cs_xxx"
}
```

#### Tools Disponíveis:

1. **`buscar_pedido_woocommerce`**
   - Parâmetros: `order_id` (integer)
   - Retorna: Dados completos do pedido

2. **`buscar_produto_woocommerce`**
   - Parâmetros: `product_id`, `sku` ou `search` (string)
   - Retorna: Lista de produtos encontrados

3. **`criar_pedido_woocommerce`**
   - Parâmetros: `line_items`, `billing`, `shipping`, `payment_method`, `status`
   - Retorna: Pedido criado

4. **`atualizar_status_pedido`**
   - Parâmetros: `order_id`, `status`
   - Retorna: Pedido atualizado

### 4. Database Tools

**Tipo**: `database`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeDatabaseTool`)

#### Configuração Necessária:

```json
{
  "allowed_tables": ["products", "orders", "customers"],
  "allowed_columns": {
    "products": ["id", "name", "price", "stock"],
    "orders": ["id", "status", "total", "created_at"]
  },
  "read_only": true
}
```

#### Tools Disponíveis:

1. **`consultar_banco_dados`**
   - Parâmetros: `table`, `where` (object), `limit`, `order_by`
   - Validações:
     - Tabela deve estar em `allowed_tables`
     - Colunas devem estar em `allowed_columns`
     - Apenas SELECT (read-only)
   - Retorna: Array de registros

### 5. N8N Tools

**Tipo**: `n8n`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeN8NTool`)

#### Configuração Necessária:

```json
{
  "n8n_url": "https://n8n.example.com",
  "webhook_id": "abc123",
  "api_key": "optional_api_key"
}
```

#### Tools Disponíveis:

1. **`executar_workflow_n8n`**
   - Parâmetros: `workflow_id`, `data` (object)
   - Executa workflow via webhook POST
   - Retorna: Resposta do workflow

2. **`buscar_dados_n8n`**
   - Parâmetros: `endpoint`, `query_params` (object)
   - Busca dados via API GET
   - Retorna: Dados da API

### 6. API Tools

**Tipo**: `api`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeAPITool`)

#### Configuração Necessária:

```json
{
  "api_url": "https://api.example.com",
  "api_key": "optional_api_key",
  "method": "GET"
}
```

#### Tools Disponíveis:

1. **`chamar_api_externa`**
   - Parâmetros: `endpoint`, `body` (object), `headers` (object)
   - Faz requisição HTTP genérica
   - Retorna: Resposta da API

### 7. Document Tools

**Tipo**: `document`  
**Arquivo**: `app/Services/OpenAIService.php` (método `executeDocumentTool`)

#### Configuração Necessária:

```json
{
  "documents_path": "/path/to/documents"
}
```

#### Tools Disponíveis:

1. **`buscar_documento`**
   - Parâmetros: `search_term`, `document_type` (pdf, docx, txt), `limit`
   - Busca arquivos no diretório
   - Retorna: Lista de documentos encontrados

2. **`extrair_texto_documento`**
   - Parâmetros: `document_path`
   - Extrai texto de documento
   - Requer bibliotecas externas para PDF/DOCX
   - Retorna: Texto extraído

---

## 📝 EXEMPLOS PRÁTICOS

### Exemplo 1: Criar Agente de Suporte

```php
use App\Services\AIAgentService;
use App\Models\AIAgent;
use App\Models\AITool;

// 1. Criar agente
$agentId = AIAgentService::create([
    'name' => 'Suporte Técnico',
    'description' => 'Atende dúvidas técnicas e problemas',
    'agent_type' => 'SUPPORT',
    'prompt' => 'Você é um agente de suporte técnico especializado. Seja prestativo, claro e objetivo. Se não souber algo, seja honesto e ofereça escalar para um humano.',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'enabled' => true,
    'max_conversations' => 20
]);

// 2. Adicionar tools
$systemTools = AITool::getByType('system');
foreach ($systemTools as $tool) {
    AIAgent::addTool($agentId, $tool['id']);
}

// 3. Adicionar tool específica (WooCommerce)
$wcTool = AITool::findBySlug('buscar_pedido_woocommerce');
if ($wcTool) {
    AIAgent::addTool($agentId, $wcTool['id'], [
        'woocommerce_url' => 'https://loja.com',
        'consumer_key' => 'ck_xxx',
        'consumer_secret' => 'cs_xxx'
    ]);
}
```

### Exemplo 2: Criar Tool Customizada

```php
use App\Services\AIToolService;

$toolId = AIToolService::create([
    'name' => 'Buscar Cliente no CRM',
    'slug' => 'buscar_cliente_crm',
    'description' => 'Busca informações de cliente no CRM externo',
    'tool_type' => 'api',
    'function_schema' => [
        'type' => 'function',
        'function' => [
            'name' => 'buscar_cliente_crm',
            'description' => 'Busca informações de um cliente no CRM',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'email' => [
                        'type' => 'string',
                        'description' => 'Email do cliente'
                    ],
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Telefone do cliente'
                    ]
                ],
                'required' => []
            ]
        ]
    ],
    'config' => [
        'api_url' => 'https://crm.example.com/api',
        'api_key' => 'secret_key_here',
        'method' => 'GET'
    ],
    'enabled' => true
]);
```

### Exemplo 3: Processar Mensagem Manualmente

```php
use App\Services\AIAgentService;

// Processar mensagem do contato
$response = AIAgentService::processMessage(
    $conversationId = 123,
    $agentId = 5,
    $message = "Quero saber o status do pedido #12345"
);

// Resposta contém:
// [
//     'content' => 'Seu pedido está em processamento...',
//     'tokens_used' => 150,
//     'tokens_prompt' => 100,
//     'tokens_completion' => 50,
//     'cost' => 0.0045,
//     'execution_time_ms' => 1250
// ]
```

### Exemplo 4: Adicionar Agente à Conversa

```php
use App\Services\ConversationAIService;

// Adicionar agente e processar imediatamente
$result = ConversationAIService::addAIAgent($conversationId, [
    'ai_agent_id' => 5,
    'process_immediately' => true,
    'assume_conversation' => false,  // Não remove agente humano se houver
    'only_if_unassigned' => false    // Permite mesmo se tiver agente humano
]);

// Resultado:
// [
//     'success' => true,
//     'ai_conversation_id' => 42,
//     'message' => 'Agente de IA adicionado com sucesso'
// ]
```

---

## 🔍 PONTOS IMPORTANTES

### 1. Rate Limiting e Controle de Custo

**Arquivo**: `app/Services/AICostControlService.php` (referenciado mas não lido)

- Verifica limites antes de processar mensagem
- Controla custos por agente/período
- Implementa rate limiting

### 2. Processamento Assíncrono

Atualmente o processamento é **síncrono**, mas pode ser convertido para assíncrono:

```php
// Em produção, usar fila de jobs:
Queue::push(new ProcessAIMessageJob($conversationId, $agentId, $message));
```

### 3. Escalação Automática

Quando tool `escalar_para_humano` é chamada:

1. Status da conversa muda para 'open'
2. `agent_id` é removido (NULL)
3. Status de `ai_conversations` muda para 'escalated'
4. Sistema de distribuição atribui a agente humano

### 4. Histórico de Mensagens

- Últimas 10 mensagens são incluídas no contexto
- Histórico completo é salvo em `ai_conversations.messages` (JSON)
- Mensagens são ordenadas cronologicamente

### 5. Validação de Tools

Antes de executar tool:
1. Verifica se tool existe e está ativa
2. Verifica se tool está atribuída ao agente
3. Valida argumentos conforme schema
4. Valida permissões (ex: tabelas permitidas)

---

## 📊 ESTATÍSTICAS E LOGS

### Obter Estatísticas do Agente

```php
use App\Models\AIConversation;

$stats = AIConversation::getAgentStats($agentId, $startDate, $endDate);

// Retorna:
// [
//     'total_conversations' => 150,
//     'total_tokens' => 45000,
//     'total_cost' => 12.50,
//     'avg_tokens' => 300,
//     'completed_conversations' => 120,
//     'escalated_conversations' => 30
// ]
```

### Logs de Tools Utilizadas

```php
// Tools utilizadas são registradas em:
$aiConversation = AIConversation::getByConversationId($conversationId);
$toolsUsed = json_decode($aiConversation['tools_used'], true);

// Formato:
// [
//     [
//         'tool' => 'buscar_pedido_woocommerce',
//         'call' => ['order_id' => 12345],
//         'result' => ['success' => true, 'order' => {...}],
//         'timestamp' => '2025-01-27 10:30:00'
//     ]
// ]
```

---

## 🎯 CONCLUSÃO

O sistema de **AI Agents** e **AI Tools** é uma implementação completa que permite:

✅ Criar agentes virtuais especializados  
✅ Atribuir tools específicas a cada agente  
✅ Processar mensagens automaticamente  
✅ Executar ações no sistema via tools  
✅ Integrar com serviços externos  
✅ Controlar custos e limites  
✅ Escalar para humanos quando necessário  
✅ Registrar logs e estatísticas completas  

**Status Atual**: 95% implementado  
**Próximos Passos**: Melhorias de performance, processamento assíncrono, mais tools

---

## 🔗 DOCUMENTAÇÃO RELACIONADA

- **Agentes de IA para Kanban**: Ver `PLANO_AGENTES_IA_KANBAN.md`
- **Sistema RAG**: Ver `PLANO_SISTEMA_RAG.md`
- **Progresso Geral**: Ver `PROGRESSO_AGENTES_IA.md`

---

**Documentação criada em**: 2025-01-27  
**Última atualização**: 2025-01-27

