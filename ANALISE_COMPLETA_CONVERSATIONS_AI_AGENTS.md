# 📊 ANÁLISE COMPLETA - SISTEMA DE CONVERSAS E AGENTES DE IA

> **Análise detalhada do funcionamento completo do sistema de conversas e agentes de IA**
> 
> Data: 31/12/2025

---

## 📑 ÍNDICE

1. [Visão Geral](#-visão-geral)
2. [Arquitetura do Sistema](#-arquitetura-do-sistema)
3. [Estrutura de Banco de Dados](#-estrutura-de-banco-de-dados)
4. [Fluxos Completos](#-fluxos-completos)
5. [Componentes Principais](#-componentes-principais)
6. [Integração com OpenAI](#-integração-com-openai)
7. [Sistema de Tools (Ferramentas)](#-sistema-de-tools-ferramentas)
8. [Distribuição Automática](#-distribuição-automática)
9. [Controle de Custos e Performance](#-controle-de-custos-e-performance)
10. [Recursos Avançados](#-recursos-avançados)

---

## 🎯 VISÃO GERAL

### O Que É?

Sistema completo de **atendimento automatizado por IA** integrado ao sistema de conversas. Permite criar agentes virtuais que podem:

- ✅ **Atender conversas automaticamente** via WhatsApp, Instagram, Facebook, etc
- ✅ **Executar ações no sistema** através de ferramentas (tools)
- ✅ **Integrar com serviços externos** (WooCommerce, APIs, N8N, etc)
- ✅ **Aprender e melhorar** com feedback e memórias
- ✅ **Escalar para humanos** quando necessário
- ✅ **Trabalhar em conjunto com automações** (branching inteligente)

### Características Principais

- 🤖 **Múltiplos agentes especializados** (SDR, CS, CLOSER, SUPPORT, etc)
- 🛠️ **Sistema extensível de tools** (ferramentas que a IA pode usar)
- 💰 **Controle de custos** (rate limiting, limites por agente)
- 📊 **Métricas e analytics** (tokens, custo, performance)
- 🔄 **Processamento assíncrono** com timer de contexto
- 🧠 **Memória de conversas** (RAG - Retrieval Augmented Generation)
- 📈 **Detecção de feedback** e melhoria contínua

---

## 🏗️ ARQUITETURA DO SISTEMA

### Camadas da Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                    INTERFACE / CANAIS                        │
│  WhatsApp | Instagram | Facebook | WebChat | Email | etc    │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                  CAMADA DE CONTROLLERS                       │
│         ConversationController | AIAgentController           │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                  CAMADA DE SERVICES                          │
│  ConversationService → AIAgentService → OpenAIService        │
│                              ↓                                │
│                       AIToolService                          │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                    CAMADA DE MODELS                          │
│  Conversation | AIAgent | AIConversation | AITool | Message │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                  BANCO DE DADOS (MySQL)                      │
│  conversations | ai_agents | ai_conversations | ai_tools    │
│  messages | ai_agent_tools | contacts | users               │
└─────────────────────────────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                  INTEGRAÇÕES EXTERNAS                        │
│       OpenAI API | WooCommerce | N8N | APIs Externas        │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗄️ ESTRUTURA DE BANCO DE DADOS

### Tabelas Principais

#### 1. `conversations` - Conversas

```sql
CREATE TABLE conversations (
    id INT PRIMARY KEY,
    contact_id INT NOT NULL,              -- Contato da conversa
    agent_id INT NULL,                    -- Agente humano (se atribuído)
    department_id INT NULL,               -- Setor/Departamento
    channel VARCHAR(50) NOT NULL,         -- Canal (whatsapp, instagram, etc)
    status VARCHAR(50) DEFAULT 'open',    -- Status (open, pending, closed)
    funnel_id INT NULL,                   -- Funil
    funnel_stage_id INT NULL,             -- Etapa do funil
    integration_account_id INT NULL,      -- Conta de integração (WhatsApp, etc)
    metadata JSON NULL,                   -- Metadados (ai_branching_active, etc)
    priority VARCHAR(20) DEFAULT 'normal',-- Prioridade
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (contact_id) REFERENCES contacts(id),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (funnel_id) REFERENCES funnels(id),
    FOREIGN KEY (funnel_stage_id) REFERENCES funnel_stages(id)
);
```

#### 2. `ai_agents` - Agentes de IA

```sql
CREATE TABLE ai_agents (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,           -- Nome do agente
    description TEXT NULL,                -- Descrição
    agent_type VARCHAR(50) NOT NULL,      -- Tipo (SDR, CS, CLOSER, etc)
    prompt TEXT NOT NULL,                 -- Prompt do sistema
    model VARCHAR(100) DEFAULT 'gpt-4',   -- Modelo OpenAI
    temperature DECIMAL(3,2) DEFAULT 0.7, -- Temperature (0.0 a 2.0)
    max_tokens INT DEFAULT 2000,          -- Máximo de tokens na resposta
    enabled BOOLEAN DEFAULT TRUE,         -- Se está ativo
    max_conversations INT NULL,           -- Limite de conversas simultâneas
    current_conversations INT DEFAULT 0,  -- Conversas atuais
    settings JSON NULL,                   -- Configurações adicionais
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_agent_type (agent_type),
    INDEX idx_enabled (enabled)
);
```

**Campos de `settings` (JSON):**
```json
{
    "response_delay_min": 2,              // Delay mínimo (segundos)
    "response_delay_max": 5,              // Delay máximo (segundos)
    "context_timer_seconds": 30,          // Timer de contexto
    "prefer_tools": true,                 // Preferir usar tools
    "welcome_message": "Olá! Como posso ajudar?"
}
```

#### 3. `ai_conversations` - Logs de Conversas com IA

```sql
CREATE TABLE ai_conversations (
    id INT PRIMARY KEY,
    conversation_id INT NOT NULL,         -- ID da conversa
    ai_agent_id INT NOT NULL,             -- ID do agente de IA
    messages JSON NOT NULL,               -- Histórico de mensagens
    tools_used JSON NULL,                 -- Tools utilizadas
    tokens_used INT DEFAULT 0,            -- Total de tokens
    tokens_prompt INT DEFAULT 0,          -- Tokens do prompt
    tokens_completion INT DEFAULT 0,      -- Tokens da completion
    cost DECIMAL(10,6) DEFAULT 0,         -- Custo em USD
    status VARCHAR(50) DEFAULT 'active',  -- Status (active, completed, escalated)
    escalated_to_user_id INT NULL,        -- Usuário escalado
    metadata JSON NULL,                   -- Metadados
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (escalated_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_ai_agent_id (ai_agent_id),
    INDEX idx_status (status)
);
```

**Campos de `messages` (JSON):**
```json
[
    {
        "role": "user",
        "content": "Olá, quero saber sobre...",
        "timestamp": "2025-12-31 10:00:00"
    },
    {
        "role": "assistant",
        "content": "Claro! Vou te ajudar...",
        "timestamp": "2025-12-31 10:00:05"
    }
]
```

**Campos de `tools_used` (JSON):**
```json
[
    {
        "tool": "buscar_pedido_woocommerce",
        "call": {"order_id": 12345},
        "result": {"status": "processing", "total": "R$ 299,90"},
        "timestamp": "2025-12-31 10:00:03"
    }
]
```

#### 4. `ai_tools` - Ferramentas Disponíveis

```sql
CREATE TABLE ai_tools (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,           -- Nome da tool
    slug VARCHAR(100) NOT NULL UNIQUE,    -- Slug único
    description TEXT NULL,                -- Descrição
    tool_type VARCHAR(50) NOT NULL,       -- Tipo (woocommerce, database, system, etc)
    function_schema JSON NOT NULL,        -- Schema para OpenAI Function Calling
    config JSON NULL,                     -- Configurações (URLs, credenciais, etc)
    enabled BOOLEAN DEFAULT TRUE,         -- Se está ativa
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_tool_type (tool_type),
    INDEX idx_slug (slug)
);
```

**Exemplo de `function_schema` (JSON):**
```json
{
    "type": "function",
    "function": {
        "name": "buscar_pedido_woocommerce",
        "description": "Busca informações de um pedido no WooCommerce",
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

#### 5. `ai_agent_tools` - Relação Agente ↔ Tool (N:N)

```sql
CREATE TABLE ai_agent_tools (
    id INT PRIMARY KEY,
    ai_agent_id INT NOT NULL,             -- ID do agente
    ai_tool_id INT NOT NULL,              -- ID da tool
    config JSON NULL,                     -- Configuração específica
    enabled BOOLEAN DEFAULT TRUE,         -- Se está ativa para este agente
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE CASCADE,
    FOREIGN KEY (ai_tool_id) REFERENCES ai_tools(id) ON DELETE CASCADE,
    UNIQUE KEY unique_agent_tool (ai_agent_id, ai_tool_id)
);
```

#### 6. `messages` - Mensagens da Conversa

```sql
CREATE TABLE messages (
    id INT PRIMARY KEY,
    conversation_id INT NOT NULL,         -- ID da conversa
    sender_id INT NULL,                   -- ID do remetente
    sender_type VARCHAR(50) NOT NULL,     -- Tipo (contact, agent, ai_agent, system)
    ai_agent_id INT NULL,                 -- ID do agente de IA (se aplicável)
    content TEXT NOT NULL,                -- Conteúdo da mensagem
    content_type VARCHAR(50) DEFAULT 'text', -- Tipo (text, image, audio, etc)
    status VARCHAR(50) DEFAULT 'sent',    -- Status (sent, delivered, read)
    read_at TIMESTAMP NULL,               -- Data de leitura
    metadata JSON NULL,                   -- Metadados
    created_at TIMESTAMP,
    
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (ai_agent_id) REFERENCES ai_agents(id) ON DELETE SET NULL,
    
    INDEX idx_conversation_id (conversation_id),
    INDEX idx_sender_type (sender_type),
    INDEX idx_ai_agent_id (ai_agent_id)
);
```

### Relacionamentos

```
conversations (1) ←→ (N) messages
conversations (1) ←→ (1) ai_conversations
conversations (N) →  (1) contacts
conversations (N) →  (1) users (agent_id)
conversations (N) →  (1) departments
conversations (N) →  (1) funnels
conversations (N) →  (1) funnel_stages

ai_agents (1) ←→ (N) ai_conversations
ai_agents (N) ←→ (N) ai_tools (através de ai_agent_tools)

ai_conversations (N) → (1) conversations
ai_conversations (N) → (1) ai_agents
ai_conversations (N) → (1) users (escalated_to_user_id)

messages (N) → (1) conversations
messages (N) → (1) ai_agents (opcional, se sender_type = 'ai_agent')
```

---

## 🔄 FLUXOS COMPLETOS

### 1. Criação de Conversa e Atribuição Automática

```
┌─────────────────────────────────────────────────────────────┐
│ 1. RECEBIMENTO DE MENSAGEM (Webhook/Integração)             │
│    - WhatsApp recebe mensagem do cliente                     │
│    - Webhook chama: POST /api/webhooks/whatsapp/message     │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 2. ConversationService::create()                             │
│    a) Validar dados (contact_id, channel)                   │
│    b) Verificar se contato existe                           │
│    c) Resolver funil/etapa padrão:                          │
│       - Prioridade 1: Integração (integration_account)      │
│       - Prioridade 2: WhatsApp Account (legacy)             │
│       - Prioridade 3: Sistema (settings)                    │
│       - Fallback: Primeira etapa "Entrada" do funil         │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 3. DISTRIBUIÇÃO AUTOMÁTICA                                  │
│    a) Verificar se deve atribuir automaticamente            │
│    b) PRIMEIRO: Verificar agente do contato (histórico)     │
│       - ContactAgentService::shouldAutoAssignOnConversation()│
│    c) SE NÃO: Verificar distribuição para agente de IA      │
│       - ConversationSettingsService::autoAssignConversation()│
│       - Métodos: round_robin, by_load, by_specialty, etc    │
│       - includeAI = true (permite selecionar agentes de IA) │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴────────────┐
         │                        │
    ┌────▼─────┐           ┌─────▼──────┐
    │ HUMANO   │           │ AGENTE IA  │
    │ agent_id │           │ ID NEGATIVO│
    └────┬─────┘           └─────┬──────┘
         │                        │
         │                  ┌─────▼──────────────────────────┐
         │                  │ 4. ConversationService::create()│
         │                  │    - Detecta ID negativo        │
         │                  │    - Converte: -X → X (ai_id)  │
         │                  │    - agent_id = NULL            │
         │                  │    - Cria conversa no banco     │
         │                  └─────┬──────────────────────────┘
         │                        │
         │                  ┌─────▼──────────────────────────┐
         │                  │ 5. Criar registro ai_conversation│
         │                  │    - conversation_id           │
         │                  │    - ai_agent_id               │
         │                  │    - status = 'active'         │
         │                  └─────┬──────────────────────────┘
         │                        │
         │                  ┌─────▼──────────────────────────┐
         │                  │ 6. AIAgentService::processConversation()│
         │                  │    - Buscar mensagens do contato│
         │                  │    - SE há mensagens:          │
         │                  │      → Processar última mensagem│
         │                  │    - SE NÃO há mensagens:       │
         │                  │      → Enviar welcome_message   │
         │                  └────────────────────────────────┘
         │                        │
         └────────────────────────┴──→ [CONVERSA CRIADA]
```

### 2. Processamento de Mensagem do Cliente com IA

```
┌─────────────────────────────────────────────────────────────┐
│ 1. MENSAGEM RECEBIDA (do cliente)                           │
│    - Cliente envia mensagem via WhatsApp                     │
│    - Webhook: POST /api/webhooks/whatsapp/message           │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 2. ConversationService::sendMessage()                       │
│    a) Criar mensagem no banco:                              │
│       - conversation_id, sender_type='contact'              │
│       - content, status='sent'                              │
│    b) Processar content (hashtags, mentions, etc)           │
│    c) Notificar via WebSocket (tempo real)                  │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 3. VERIFICAR SE É CONVERSA COM IA                           │
│    - Buscar ai_conversation por conversation_id             │
│    - Verificar se status = 'active'                         │
│    - Verificar se sender_type = 'contact'                   │
└────────────────────┬────────────────────────────────────────┘
                     │
                ┌────▼─────┐
                │ TEM IA?  │
                └────┬─────┘
                     │
         ┌───────────┴────────────┐
         │ NÃO                    │ SIM
         ▼                        ▼
    [FIM]              ┌───────────────────────────────┐
                       │ 4. VERIFICAR AI BRANCHING     │
                       │    (Intent Detection)         │
                       │    - Se metadata.ai_branching_active│
                       │    - AutomationService::      │
                       │      detectIntentInClientMessage()│
                       │    - SE intent detectado:     │
                       │      → Rotear e NÃO chamar IA │
                       │    - SE NÃO detectado:        │
                       │      → Continuar com IA       │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 5. AIAgentService::           │
                       │    processMessage()           │
                       │    - conversationId           │
                       │    - agentId                  │
                       │    - message (conteúdo)       │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 6. TIMER DE CONTEXTO          │
                       │    (Context Timer)            │
                       │    - Se configurado, aguardar │
                       │    - Buffer de mensagens      │
                       │    - Processar após timeout   │
                       │    - OU processar imediatamente│
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 7. OpenAIService::            │
                       │    processMessage()           │
                       │    (VER DETALHES ABAIXO)      │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 8. RESPOSTA GERADA            │
                       │    - content (texto resposta) │
                       │    - tokens_used              │
                       │    - cost                     │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 9. DELAY HUMANIZADO           │
                       │    - response_delay_min a max │
                       │    - sleep(random)            │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 10. ConversationService::     │
                       │     sendMessage()             │
                       │     - sender_type='agent'     │
                       │     - ai_agent_id=X           │
                       │     - content (resposta)      │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 11. ENVIAR VIA INTEGRAÇÃO     │
                       │     - WhatsApp API (Quepasa)  │
                       │     - Outros canais           │
                       └───────────┬───────────────────┘
                                   │
                       ┌───────────▼───────────────────┐
                       │ 12. Notificar WebSocket       │
                       │     - Atualizar interface     │
                       │     - Marcar como enviada     │
                       └───────────────────────────────┘
                                   │
                                   ▼
                           [MENSAGEM ENVIADA]
```

### 3. Processamento Interno OpenAI (Detalhado)

```
┌─────────────────────────────────────────────────────────────┐
│ OpenAIService::processMessage()                              │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 1. OBTER DADOS DO AGENTE                                    │
│    - AIAgent::find(agentId)                                 │
│    - Verificar se enabled = true                            │
│    - Obter: prompt, model, temperature, max_tokens          │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 2. VERIFICAR RATE LIMITING E CUSTOS                         │
│    - AICostControlService::canProcessMessage(agentId)       │
│    - Verificar limites por minuto/hora/dia                  │
│    - Verificar limites de custo                             │
│    - SE ultrapassou: throw Exception                        │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 3. OBTER API KEY                                            │
│    - Setting::get('openai_api_key')                         │
│    - Fallback: getenv('OPENAI_API_KEY')                     │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 4. OBTER TOOLS DO AGENTE                                    │
│    - AIAgent::getTools(agentId)                             │
│    - Filtrar apenas enabled = true                          │
│    - Extrair function_schema de cada tool                   │
│    - Normalizar schemas (corrigir properties: [])           │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 5. CONSTRUIR MENSAGENS (buildMessages)                      │
│    a) Mensagem do Sistema (system):                         │
│       - Prompt do agente                                    │
│       - Lista de tools disponíveis (descrição)              │
│       - Instruções sobre uso de tools                       │
│       - Contexto do contato (nome, email, phone)            │
│       - Contexto da conversa (status, assunto)              │
│                                                              │
│    b) Histórico de Mensagens (últimas 20):                  │
│       - Buscar messages WHERE conversation_id               │
│       - Condensar se muito longas (resumir)                 │
│       - Formato: {role: 'user/assistant', content}          │
│                                                              │
│    c) Memórias do Agente (se disponível):                   │
│       - AgentMemoryService::retrieve()                      │
│       - Buscar memórias relevantes (vetorial)               │
│       - Adicionar ao contexto                               │
│                                                              │
│    d) Mensagem Atual do Usuário:                            │
│       - {role: 'user', content: message}                    │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 6. PREPARAR PAYLOAD PARA OPENAI                             │
│    {                                                         │
│      "model": "gpt-4",                                       │
│      "messages": [...],                                      │
│      "temperature": 0.7,                                     │
│      "max_tokens": 2000,                                     │
│      "tools": [                                              │
│        {                                                     │
│          "type": "function",                                 │
│          "function": {                                       │
│            "name": "buscar_pedido",                          │
│            "description": "...",                             │
│            "parameters": {...}                               │
│          }                                                   │
│        }                                                     │
│      ],                                                      │
│      "tool_choice": "auto"                                   │
│    }                                                         │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 7. FAZER REQUISIÇÃO À OPENAI API                            │
│    - POST https://api.openai.com/v1/chat/completions        │
│    - Headers: Authorization: Bearer {api_key}               │
│    - Body: JSON payload                                     │
│    - Com retries (MAX_RETRIES = 3)                          │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 8. PROCESSAR RESPOSTA                                       │
│    - choices[0].message                                     │
│    - content (texto da resposta)                            │
│    - tool_calls (se houver chamadas de tools)               │
└────────────────────┬────────────────────────────────────────┘
                     │
              ┌──────┴──────┐
              │ TEM TOOLS?  │
              └──────┬──────┘
                     │
         ┌───────────┴────────────┐
         │ NÃO                    │ SIM
         ▼                        ▼
    ┌────────────┐     ┌──────────────────────────────┐
    │ RESPOSTA   │     │ 9. EXECUTAR TOOLS             │
    │ DIRETA     │     │    - executeToolCalls()       │
    └────┬───────┘     │    - Para cada tool_call:     │
         │             │      a) Identificar tool      │
         │             │      b) Extrair argumentos    │
         │             │      c) Executar tool handler │
         │             │      d) Coletar resultado     │
         │             └──────┬───────────────────────┘
         │                    │
         │             ┌──────▼───────────────────────┐
         │             │ 10. REENVIAR PARA OPENAI     │
         │             │     - Adicionar tool_calls    │
         │             │     - Adicionar resultados    │
         │             │     - Pedir resposta final    │
         │             │     - Contabilizar tokens     │
         │             └──────┬───────────────────────┘
         │                    │
         └────────────────────┴─→ [RESPOSTA FINAL]
                                  │
                        ┌─────────▼──────────────────┐
                        │ 11. CALCULAR TOKENS E CUSTO│
                        │     - usage.total_tokens   │
                        │     - usage.prompt_tokens  │
                        │     - usage.completion_tokens│
                        │     - cost (baseado no modelo)│
                        └─────────┬──────────────────┘
                                  │
                        ┌─────────▼──────────────────┐
                        │ 12. REGISTRAR EM           │
                        │     ai_conversations       │
                        │     - Atualizar stats      │
                        │     - Adicionar mensagens  │
                        │     - Registrar tools_used │
                        └─────────┬──────────────────┘
                                  │
                        ┌─────────▼──────────────────┐
                        │ 13. DETECÇÃO DE FEEDBACK   │
                        │     (se disponível)        │
                        │     - Analisar se resposta │
                        │       foi inadequada       │
                        │     - Registrar feedback   │
                        └─────────┬──────────────────┘
                                  │
                        ┌─────────▼──────────────────┐
                        │ 14. EXTRAIR MEMÓRIAS       │
                        │     (a cada 5 mensagens)   │
                        │     - AgentMemoryService:: │
                        │       extractAndSave()     │
                        └─────────┬──────────────────┘
                                  │
                        ┌─────────▼──────────────────┐
                        │ 15. RETORNAR RESPOSTA      │
                        │     {                      │
                        │       content: "...",      │
                        │       tokens_used: X,      │
                        │       cost: Y,             │
                        │       execution_time_ms: Z │
                        │     }                      │
                        └────────────────────────────┘
```

### 4. Execução de Tools (Function Calling)

```
┌─────────────────────────────────────────────────────────────┐
│ executeToolCalls(toolCalls, conversationId, agentId, context)│
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ PARA CADA tool_call:                                        │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 1. Extrair dados do tool_call                               │
│    - tool_call_id                                           │
│    - function.name (nome da tool)                           │
│    - function.arguments (JSON com parâmetros)               │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 2. Buscar tool no banco                                     │
│    - AITool::findBySlug(functionName)                       │
│    - Verificar se enabled = true                            │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 3. Identificar tipo da tool                                 │
│    - tool_type (woocommerce, database, system, api, etc)    │
└────────────────────┬────────────────────────────────────────┘
                     │
         ┌───────────┴────────────────┐
         │                            │
    ┌────▼─────────┐         ┌────────▼─────────┐
    │ SYSTEM TOOL  │         │ EXTERNAL TOOL    │
    └────┬─────────┘         └────────┬─────────┘
         │                            │
         │                            │
┌────────▼────────────┐    ┌──────────▼──────────────┐
│ EXECUTAR HANDLERS   │    │ EXECUTAR INTEGRAÇÕES    │
│                     │    │                         │
│ • buscar_conversas  │    │ • WooCommerce API       │
│ • transferir_agente │    │ • N8N Webhook           │
│ • encerrar_conversa │    │ • API Externa           │
│ • atualizar_contato │    │ • Database Query        │
│ • adicionar_tag     │    │                         │
│ • criar_atividade   │    │                         │
│ • mover_funil       │    │                         │
└────────┬────────────┘    └──────────┬──────────────┘
         │                            │
         └───────────┬────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 4. Registrar resultado                                      │
│    - AIConversation::logToolUsage()                         │
│    - {                                                      │
│        tool: "nome_da_tool",                                │
│        call: {...argumentos...},                            │
│        result: {...resultado...},                           │
│        timestamp: "2025-12-31 10:00:00"                     │
│      }                                                      │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│ 5. Retornar para OpenAI                                     │
│    {                                                        │
│      tool_call_id: "call_abc123",                           │
│      result: {                                              │
│        success: true,                                       │
│        data: {...}                                          │
│      }                                                      │
│    }                                                        │
└─────────────────────────────────────────────────────────────┘
```

---

## 🧩 COMPONENTES PRINCIPAIS

### 1. **ConversationService**

**Localização:** `app/Services/ConversationService.php`

**Responsabilidades:**
- ✅ Criar conversas
- ✅ Enviar mensagens (humanos e IA)
- ✅ Gerenciar status de conversas
- ✅ Notificar via WebSocket
- ✅ Integrar com canais (WhatsApp, etc)
- ✅ Executar automações (branching)

**Métodos Principais:**

```php
// Criar nova conversa
public static function create(array $data, bool $executeAutomationsNow = true): array

// Enviar mensagem
public static function sendMessage(
    int $conversationId,
    string $content,
    string $senderType,
    ?int $senderId = null,
    array $options = []
): int

// Atribuir agente
public static function assignAgent(int $conversationId, int $agentId): bool

// Fechar conversa
public static function close(int $conversationId, ?string $reason = null): bool
```

**Fluxo de `sendMessage()`:**

1. Validar dados
2. Criar mensagem no banco
3. Processar content (hashtags, mentions)
4. Se sender_type = 'contact':
   - Verificar se tem agente de IA ativo
   - SE SIM: Verificar AI branching (intent detection)
     - SE intent detectado: Rotear e não chamar IA
     - SE NÃO: Chamar `AIAgentService::processMessage()`
5. Notificar WebSocket
6. Executar automações (se configurado)
7. Enviar para canal (se necessário)

### 2. **AIAgentService**

**Localização:** `app/Services/AIAgentService.php`

**Responsabilidades:**
- ✅ CRUD de agentes de IA
- ✅ Processar conversas com IA
- ✅ Gerenciar buffer de mensagens (timer de contexto)
- ✅ Atualizar contagens de conversas
- ✅ Escalar para humanos

**Métodos Principais:**

```php
// Criar agente
public static function create(array $data): int

// Processar conversa (nova atribuição)
public static function processConversation(int $conversationId, int $aiAgentId): void

// Processar mensagem
public static function processMessage(
    int $conversationId,
    int $agentId,
    string $message
): array

// Escalar para humano
public static function escalateToHuman(
    int $conversationId,
    int $userId,
    string $reason
): bool
```

**Timer de Contexto:**

Permite aguardar múltiplas mensagens do cliente antes de responder:

```php
// Configuração no agente (settings)
{
    "context_timer_seconds": 30  // Aguardar 30s antes de processar
}

// Funcionamento:
1. Cliente envia mensagem 1 → Adiciona ao buffer, inicia timer
2. Cliente envia mensagem 2 (dentro de 30s) → Adiciona ao buffer
3. Cliente envia mensagem 3 (dentro de 30s) → Adiciona ao buffer
4. Timer expira (30s) → Processa todas as 3 mensagens juntas
5. IA responde considerando contexto completo
```

### 3. **OpenAIService**

**Localização:** `app/Services/OpenAIService.php`

**Responsabilidades:**
- ✅ Comunicação com OpenAI API
- ✅ Construir prompts e contexto
- ✅ Executar function calling (tools)
- ✅ Calcular tokens e custos
- ✅ Gerenciar retries
- ✅ Normalizar schemas

**Métodos Principais:**

```php
// Processar mensagem com OpenAI
public static function processMessage(
    int $conversationId,
    int $agentId,
    string $message,
    array $context = []
): array

// Construir mensagens para API
private static function buildMessages(
    array $agent,
    string $userMessage,
    array $context,
    array $toolDescriptions = []
): array

// Executar tool calls
private static function executeToolCalls(
    array $toolCalls,
    int $conversationId,
    int $agentId,
    array $context
): array

// Calcular custo
private static function calculateCost(
    string $model,
    int $promptTokens,
    int $completionTokens
): float
```

**Cálculo de Custos (Dezembro 2025):**

```php
'gpt-4' => [
    'prompt' => 0.03 / 1000,      // $0.03 por 1K tokens
    'completion' => 0.06 / 1000   // $0.06 por 1K tokens
],
'gpt-4-turbo' => [
    'prompt' => 0.01 / 1000,      // $0.01 por 1K tokens
    'completion' => 0.03 / 1000   // $0.03 por 1K tokens
],
'gpt-3.5-turbo' => [
    'prompt' => 0.001 / 1000,     // $0.001 por 1K tokens
    'completion' => 0.002 / 1000  // $0.002 por 1K tokens
]
```

### 4. **AIToolService**

**Localização:** `app/Services/AIToolService.php`

**Responsabilidades:**
- ✅ CRUD de tools
- ✅ Normalizar schemas para OpenAI
- ✅ Fornecer tools padrão do sistema
- ✅ Validar configurações

**Métodos Principais:**

```php
// Criar tool
public static function create(array $data): int

// Listar tools
public static function list(array $filters = []): array

// Obter tools padrão
public static function getDefaultTools(): array

// Normalizar schema
private static function normalizeFunctionSchema(array $schema): array
```

### 5. **ConversationSettingsService**

**Localização:** `app/Services/ConversationSettingsService.php`

**Responsabilidades:**
- ✅ Configurações de distribuição
- ✅ Atribuição automática (humanos + IA)
- ✅ Algoritmos de distribuição
- ✅ Limites e SLA

**Métodos de Distribuição:**

```php
// Distribuir automaticamente
public static function autoAssignConversation(
    int $conversationId,
    ?int $departmentId = null,
    ?int $funnelId = null,
    ?int $stageId = null
): ?int

// Round-robin
public static function assignRoundRobin(..., bool $includeAI = false): ?int

// Por carga
public static function assignByLoad(..., bool $includeAI = false): ?int

// Por especialidade
public static function assignBySpecialty(..., bool $includeAI = false): ?int

// Por performance
public static function assignByPerformance(..., bool $includeAI = false): ?int

// Por porcentagem
public static function assignByPercentage(..., bool $includeAI = false): ?int
```

**Como Funciona o `includeAI`:**

Quando `includeAI = true`, a distribuição considera tanto agentes humanos quanto agentes de IA:

```php
// Buscar agentes disponíveis (humanos)
$humanAgents = User::where('enabled', '=', 1)
    ->where('can_receive_conversations', '=', 1)
    ->get();

// Buscar agentes de IA disponíveis
if ($includeAI) {
    $aiAgents = AIAgent::getAvailableAgents();
    
    // Transformar em formato compatível
    foreach ($aiAgents as $aiAgent) {
        $humanAgents[] = [
            'id' => -1 * $aiAgent['id'],  // ID NEGATIVO para identificar IA
            'name' => $aiAgent['name'] . ' (IA)',
            'agent_type' => 'ai',
            'ai_agent_id' => $aiAgent['id'],
            'current_conversations' => $aiAgent['current_conversations'],
            'max_conversations' => $aiAgent['max_conversations']
        ];
    }
}

// Aplicar algoritmo de distribuição no array combinado
// ...

// Retornar ID (se negativo, é agente de IA)
return $selectedAgent['id'];  // Ex: -5 = AI Agent ID 5
```

---

## 🔌 INTEGRAÇÃO COM OPENAI

### 1. Autenticação

```php
// Obter API Key
$apiKey = Setting::get('openai_api_key');
if (empty($apiKey)) {
    $apiKey = getenv('OPENAI_API_KEY');
}

// Headers da requisição
$headers = [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
];
```

### 2. Endpoint e Payload

**Endpoint:**
```
POST https://api.openai.com/v1/chat/completions
```

**Payload Exemplo:**

```json
{
    "model": "gpt-4",
    "messages": [
        {
            "role": "system",
            "content": "Você é um agente de suporte..."
        },
        {
            "role": "user",
            "content": "Olá, preciso de ajuda"
        }
    ],
    "temperature": 0.7,
    "max_tokens": 2000,
    "tools": [
        {
            "type": "function",
            "function": {
                "name": "buscar_pedido",
                "description": "Busca informações de um pedido",
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
    ],
    "tool_choice": "auto"
}
```

### 3. Resposta da OpenAI

**Sem Tool Calls:**

```json
{
    "id": "chatcmpl-abc123",
    "object": "chat.completion",
    "created": 1735660800,
    "model": "gpt-4",
    "choices": [
        {
            "index": 0,
            "message": {
                "role": "assistant",
                "content": "Claro! Vou te ajudar com isso..."
            },
            "finish_reason": "stop"
        }
    ],
    "usage": {
        "prompt_tokens": 50,
        "completion_tokens": 20,
        "total_tokens": 70
    }
}
```

**Com Tool Calls:**

```json
{
    "id": "chatcmpl-def456",
    "object": "chat.completion",
    "created": 1735660805,
    "model": "gpt-4",
    "choices": [
        {
            "index": 0,
            "message": {
                "role": "assistant",
                "content": null,
                "tool_calls": [
                    {
                        "id": "call_abc123",
                        "type": "function",
                        "function": {
                            "name": "buscar_pedido",
                            "arguments": "{\"order_id\": 12345}"
                        }
                    }
                ]
            },
            "finish_reason": "tool_calls"
        }
    ],
    "usage": {
        "prompt_tokens": 100,
        "completion_tokens": 25,
        "total_tokens": 125
    }
}
```

### 4. Fluxo com Tool Calls

```
1. Enviar mensagem inicial → OpenAI retorna tool_calls

2. Executar cada tool:
   - buscar_pedido(12345) → {status: "processing", total: "R$ 299,90"}

3. Adicionar resultados e reenviar:
   {
     "messages": [
       {...mensagens anteriores...},
       {
         "role": "assistant",
         "tool_calls": [...]
       },
       {
         "role": "tool",
         "tool_call_id": "call_abc123",
         "content": "{\"status\":\"processing\",\"total\":\"R$ 299,90\"}"
       }
     ]
   }

4. OpenAI retorna resposta final:
   "Seu pedido #12345 está em processamento. O valor é R$ 299,90."
```

### 5. Tratamento de Erros

```php
try {
    $response = self::makeRequest($apiKey, $payload);
} catch (\Exception $e) {
    // Erro na API
    if (strpos($e->getMessage(), 'rate_limit') !== false) {
        // Rate limit atingido
        throw new \Exception('Limite de requisições atingido. Tente novamente em alguns segundos.');
    } elseif (strpos($e->getMessage(), 'insufficient_quota') !== false) {
        // Cota insuficiente
        throw new \Exception('Cota da OpenAI esgotada. Contate o administrador.');
    } elseif (strpos($e->getMessage(), 'invalid_api_key') !== false) {
        // API Key inválida
        throw new \Exception('API Key da OpenAI inválida ou expirada.');
    } else {
        // Outro erro
        throw new \Exception('Erro ao processar com OpenAI: ' . $e->getMessage());
    }
}
```

---

## 🛠️ SISTEMA DE TOOLS (FERRAMENTAS)

### Tipos de Tools Disponíveis

#### 1. **System Tools** (Sistema)

Ferramentas internas do sistema:

- ✅ `buscar_conversas_anteriores` - Busca histórico do contato
- ✅ `transferir_para_agente` - Transfere para agente humano
- ✅ `encerrar_conversa` - Encerra a conversa
- ✅ `atualizar_contato` - Atualiza dados do contato
- ✅ `adicionar_tag` - Adiciona tag à conversa
- ✅ `criar_atividade` - Cria atividade/tarefa
- ✅ `mover_para_etapa` - Move conversa no funil
- ✅ `agendar_followup` - Agenda follow-up automático

#### 2. **WooCommerce Tools**

Integração com WooCommerce:

- ✅ `buscar_pedido_woocommerce` - Busca pedido por ID
- ✅ `listar_pedidos_cliente_woocommerce` - Lista pedidos do cliente
- ✅ `buscar_produto_woocommerce` - Busca produto
- ✅ `verificar_estoque_woocommerce` - Verifica estoque

#### 3. **Database Tools**

Consultas seguras no banco:

- ✅ `consultar_dados_cliente` - Busca dados do cliente
- ✅ `obter_estatisticas_conversas` - Estatísticas de conversas
- ✅ `buscar_atividades` - Busca atividades/tarefas

#### 4. **API Tools**

Integrações com APIs externas:

- ✅ `chamar_api_externa` - Chama API externa configurada
- ✅ `webhook_n8n` - Envia dados para N8N

#### 5. **Document Tools**

Processamento de documentos:

- ✅ `buscar_documento` - Busca em base de conhecimento
- ✅ `extrair_informacao_documento` - Extrai informações

### Criar uma Tool

**Exemplo: Tool para buscar pedido no WooCommerce**

```php
// 1. Criar tool no banco
AIToolService::create([
    'name' => 'Buscar Pedido WooCommerce',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca informações detalhadas de um pedido no WooCommerce',
    'tool_type' => 'woocommerce',
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
        'consumer_secret' => 'cs_...'
    ],
    'enabled' => true
]);
```

```php
// 2. Criar handler em OpenAIService.php
case 'buscar_pedido_woocommerce':
    $orderId = $args['order_id'] ?? null;
    
    if (!$orderId) {
        return ['error' => 'ID do pedido não fornecido'];
    }
    
    // Buscar configuração da tool
    $tool = AITool::findBySlug('buscar_pedido_woocommerce');
    $config = json_decode($tool['config'], true);
    
    // Fazer requisição ao WooCommerce
    $url = $config['api_url'] . '/orders/' . $orderId;
    $auth = base64_encode($config['consumer_key'] . ':' . $config['consumer_secret']);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['error' => 'Pedido não encontrado'];
    }
    
    $order = json_decode($response, true);
    
    return [
        'success' => true,
        'order' => [
            'id' => $order['id'],
            'status' => $order['status'],
            'total' => $order['total'],
            'date_created' => $order['date_created'],
            'billing' => $order['billing'],
            'items' => $order['line_items']
        ]
    ];
```

```php
// 3. Associar tool ao agente
$agentId = 5;  // ID do agente de IA
$toolId = 10;  // ID da tool

AIAgent::addTool($agentId, $toolId, [], true);
```

### Exemplo de Uso pela IA

**Cliente:**
> "Oi, quero saber o status do meu pedido #12345"

**Processamento:**

1. **IA recebe mensagem** com contexto
2. **IA identifica** que precisa buscar pedido
3. **IA chama tool:** `buscar_pedido_woocommerce(order_id: 12345)`
4. **Sistema executa** a tool
5. **Resultado:**
   ```json
   {
     "success": true,
     "order": {
       "id": 12345,
       "status": "processing",
       "total": "299.90",
       "date_created": "2025-12-30T10:00:00"
     }
   }
   ```
6. **IA formula resposta:**
   > "Seu pedido #12345 está em processamento. O valor total é R$ 299,90. Foi criado em 30/12/2025 às 10h. Deve ser enviado em breve! 📦"

---

## 🎯 DISTRIBUIÇÃO AUTOMÁTICA

### Configurações de Distribuição

```php
// Obter configurações
$settings = ConversationSettingsService::getSettings();

// Estrutura:
[
    'distribution' => [
        'enable_auto_assignment' => true,        // Habilitar atribuição automática
        'method' => 'round_robin',               // Método (round_robin, by_load, etc)
        'assign_to_ai_agent' => true,            // Incluir agentes de IA
        'consider_availability' => true,         // Considerar disponibilidade
        'consider_agent_limits' => true,         // Considerar limites
        'redistribute_after_sla' => true         // Redistribuir após SLA
    ],
    'limits' => [
        'max_conversations_per_agent' => 20,     // Limite por agente
        'max_conversations_per_department' => 100
    ],
    'sla' => [
        'first_response_minutes' => 5,           // Tempo para primeira resposta
        'resolution_hours' => 24                 // Tempo para resolução
    ]
]
```

### Métodos de Distribuição

#### 1. **Round-Robin**

Distribui de forma circular, alternando entre agentes disponíveis:

```
Agentes disponíveis: [A, B, C, D]

Conversa 1 → A
Conversa 2 → B
Conversa 3 → C
Conversa 4 → D
Conversa 5 → A (volta ao início)
```

**Implementação:**

```php
public static function assignRoundRobin(..., bool $includeAI = false): ?int
{
    // Buscar agentes disponíveis (humanos + IA se includeAI = true)
    $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI);
    
    if (empty($agents)) {
        return null;
    }
    
    // Ordenar por última atribuição (mais antiga primeiro)
    usort($agents, function($a, $b) {
        $aTime = strtotime($a['last_assignment_at'] ?? '1970-01-01');
        $bTime = strtotime($b['last_assignment_at'] ?? '1970-01-01');
        return $aTime <=> $bTime;
    });
    
    $selectedAgent = $agents[0] ?? null;
    
    // Se for agente de IA, retornar ID negativo
    if (($selectedAgent['agent_type'] ?? 'human') === 'ai') {
        return -1 * ($selectedAgent['ai_agent_id'] ?? 0);
    }
    
    return $selectedAgent['id'] ?? null;
}
```

#### 2. **By Load (Por Carga)**

Distribui para o agente com menor carga atual:

```
Agentes:
A (5 conversas)
B (2 conversas)  ← SELECIONADO (menor carga)
C (8 conversas)
D (3 conversas)
```

**Implementação:**

```php
public static function assignByLoad(..., bool $includeAI = false): ?int
{
    $agents = self::getAvailableAgents($departmentId, $funnelId, $stageId, $includeAI);
    
    if (empty($agents)) {
        return null;
    }
    
    // Ordenar por carga atual (menor primeiro)
    usort($agents, function($a, $b) {
        $aLoad = $a['current_conversations'] ?? 0;
        $bLoad = $b['current_conversations'] ?? 0;
        return $aLoad <=> $bLoad;
    });
    
    return $agents[0]['id'] ?? null;
}
```

#### 3. **By Specialty (Por Especialidade)**

Distribui baseado na especialidade do agente para o tipo de conversa:

```
Conversa do funil "Vendas" → Agente especialista em vendas
Conversa do funil "Suporte" → Agente especialista em suporte
```

#### 4. **By Performance (Por Performance)**

Distribui para agentes com melhor performance:

```
Agentes (ordenados por performance):
A (95% satisfação, tempo médio 5min)  ← SELECIONADO
B (90% satisfação, tempo médio 8min)
C (85% satisfação, tempo médio 10min)
```

#### 5. **By Percentage (Por Porcentagem)**

Distribui baseado em porcentagens configuradas:

```json
{
    "rules": [
        {
            "agent_id": 10,
            "percentage": 50    // 50% das conversas
        },
        {
            "agent_id": 20,
            "percentage": 30    // 30% das conversas
        },
        {
            "department_id": 5,
            "percentage": 20    // 20% para qualquer agente do setor 5
        }
    ]
}
```

### Incluir Agentes de IA na Distribuição

```php
// Ao configurar distribuição
ConversationSettingsService::updateSettings([
    'distribution' => [
        'assign_to_ai_agent' => true  // HABILITAR AGENTES DE IA
    ]
]);

// Sistema automaticamente:
1. Busca agentes humanos disponíveis
2. Busca agentes de IA disponíveis (AIAgent::getAvailableAgents())
3. Combina em um único array
4. Agentes de IA recebem ID negativo (Ex: -5 = AI Agent ID 5)
5. Aplica algoritmo de distribuição normalmente
6. Retorna ID (negativo = IA, positivo = humano)
```

### Fluxo Completo de Atribuição

```
Nova Conversa
    ↓
ConversationService::create()
    ↓
VERIFICAR ATRIBUIÇÃO AUTOMÁTICA:
    ↓
1. Agente do contato (histórico)?
   SIM → Atribuir
   NÃO → Continuar
    ↓
2. Distribuição automática habilitada?
   NÃO → Deixar sem atribuição
   SIM → Continuar
    ↓
3. ConversationSettingsService::autoAssignConversation()
    ↓
4. Aplicar método configurado:
   - round_robin
   - by_load
   - by_specialty
   - by_performance
   - percentage
    ↓
5. Retornar ID do agente
   - Positivo: Agente humano
   - Negativo: Agente de IA
    ↓
6. SE for agente de IA:
   - Converter ID negativo para positivo
   - Criar registro em ai_conversations
   - Chamar AIAgentService::processConversation()
    ↓
7. Conversa atribuída ✅
```

---

## 💰 CONTROLE DE CUSTOS E PERFORMANCE

### 1. **AICostControlService**

**Localização:** `app/Services/AICostControlService.php`

**Funcionalidades:**

#### Rate Limiting

Limites de requisições por tempo:

```php
// Configurar limites por agente
[
    'rate_limits' => [
        'requests_per_minute' => 10,
        'requests_per_hour' => 100,
        'requests_per_day' => 1000
    ]
]
```

**Verificação antes de processar:**

```php
$check = AICostControlService::canProcessMessage($agentId);

if (!$check['allowed']) {
    throw new \Exception($check['reason']);
}

// Resultado:
[
    'allowed' => false,
    'reason' => 'Limite de 10 requisições por minuto atingido. Aguarde 45 segundos.'
]
```

#### Limites de Custo

Limites financeiros por período:

```php
// Configurar limites
[
    'cost_limits' => [
        'daily_limit_usd' => 10.00,    // US$ 10 por dia
        'monthly_limit_usd' => 200.00  // US$ 200 por mês
    ]
]
```

**Verificação:**

```php
$check = AICostControlService::checkCostLimit($agentId, $estimatedCost);

if (!$check['allowed']) {
    // Custo estimado ultrapassaria limite
    // Não processar
}
```

#### Controle por Conversas Simultâneas

```php
// Na tabela ai_agents
max_conversations INT NULL            // Limite de conversas simultâneas
current_conversations INT DEFAULT 0   // Conversas atuais

// Verificar antes de atribuir
if (!AIAgent::canReceiveMoreConversations($agentId)) {
    // Agente no limite
    return null;
}

// Ao atribuir
AIAgent::updateConversationsCount($agentId);  // current_conversations++

// Ao encerrar
AIAgent::updateConversationsCount($agentId);  // current_conversations--
```

### 2. **Métricas e Analytics**

#### Tokens e Custos por Conversa

```php
// Registrado em ai_conversations
tokens_used INT              // Total de tokens
tokens_prompt INT            // Tokens do prompt
tokens_completion INT        // Tokens da resposta
cost DECIMAL(10,6)           // Custo em USD

// Consultar
$aiConv = AIConversation::find($id);
echo "Tokens: {$aiConv['tokens_used']}\n";
echo "Custo: US$ {$aiConv['cost']}\n";
```

#### Estatísticas por Agente

```php
// Obter estatísticas
$stats = AIConversation::getAgentStats(
    $agentId,
    $startDate = '2025-12-01',
    $endDate = '2025-12-31'
);

// Resultado:
[
    'total_conversations' => 150,
    'total_tokens' => 500000,
    'total_cost' => 25.50,       // US$ 25.50
    'avg_tokens' => 3333,
    'completed_conversations' => 140,
    'escalated_conversations' => 10
]
```

#### Dashboard de Custos

```php
// Custos por dia (últimos 30 dias)
$dailyCosts = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $costs = AIConversation::where('created_at', 'LIKE', "$date%")
        ->sum('cost');
    $dailyCosts[$date] = $costs;
}

// Custos por agente (mês atual)
$agentCosts = [];
$agents = AIAgent::all();
foreach ($agents as $agent) {
    $stats = AIConversation::getAgentStats($agent['id'], date('Y-m-01'), date('Y-m-31'));
    $agentCosts[$agent['name']] = $stats['total_cost'];
}
```

### 3. **Otimizações de Performance**

#### Cache de Contexto

```php
// Cachear histórico de mensagens (evitar buscar sempre)
$cacheKey = "conversation_context_{$conversationId}";
$context = Cache::get($cacheKey);

if (!$context) {
    $context = self::buildContext($conversationId);
    Cache::set($cacheKey, $context, 300); // 5 minutos
}
```

#### Limitar Histórico

```php
// Buscar apenas últimas 20 mensagens (não todas)
$messages = Message::where('conversation_id', '=', $conversationId)
    ->orderBy('id', 'DESC')
    ->limit(20)
    ->get();
```

#### Condensar Mensagens Longas

```php
// Se mensagem muito longa, resumir
if (strlen($content) > 500) {
    $content = substr($content, 0, 450) . '... [mensagem condensada]';
}
```

#### Processar em Background

```php
// Para conversas com timer de contexto
// Processar de forma assíncrona
$job = new ProcessAIMessageJob($conversationId, $agentId, $message);
Queue::push($job);
```

---

## 🚀 RECURSOS AVANÇADOS

### 1. **AI Branching (Intent Detection)**

Sistema que permite a IA detectar intenções do cliente e rotear para automações específicas **ANTES** de processar com OpenAI.

**Como Funciona:**

```php
// 1. Ativar AI Branching na conversa
$conversation = Conversation::find($conversationId);
$metadata = json_decode($conversation['metadata'] ?? '{}', true);
$metadata['ai_branching_active'] = true;
Conversation::update($conversationId, ['metadata' => json_encode($metadata)]);

// 2. Configurar intents na automação
Automation::create([
    'name' => 'Detectar Cancelamento',
    'trigger_type' => 'intent_detected',
    'trigger_config' => [
        'intents' => ['cancelar', 'encerrar', 'desistir']
    ],
    'actions' => [
        ['type' => 'send_message', 'content' => 'Entendi que deseja cancelar...'],
        ['type' => 'move_to_stage', 'stage_id' => 10]
    ]
]);

// 3. Fluxo:
// Cliente: "Quero cancelar meu pedido"
//   ↓
// ConversationService::sendMessage() detecta ai_branching_active = true
//   ↓
// AutomationService::detectIntentInClientMessage()
//   ↓
// SE detectar intent "cancelar":
//   - Executar ações da automação
//   - NÃO chamar OpenAI (economia)
//   ↓
// SE NÃO detectar intent:
//   - Processar normalmente com OpenAI
```

**Vantagens:**
- ✅ Economia de tokens (não processa com OpenAI quando não necessário)
- ✅ Respostas mais rápidas (automações são instantâneas)
- ✅ Controle fino do fluxo
- ✅ Combinar IA com regras de negócio

### 2. **RAG (Retrieval Augmented Generation)**

Sistema de busca semântica em base de conhecimento para melhorar respostas da IA.

**Como Funciona:**

```php
// 1. Adicionar documentos à base
RAGService::indexDocument([
    'title' => 'Política de Trocas e Devoluções',
    'content' => 'Nossa política permite trocas em até 30 dias...',
    'metadata' => ['category' => 'policies']
]);

// 2. Buscar documentos relevantes
$query = "Como faço para trocar um produto?";
$relevantDocs = RAGService::search($query, $limit = 3);

// 3. Adicionar ao contexto da IA
$context['knowledge_base'] = $relevantDocs;

// 4. IA usa documentos para formular resposta precisa
```

**Benefícios:**
- ✅ Respostas baseadas em documentação oficial
- ✅ Reduz alucinações da IA
- ✅ Sempre atualizado (basta atualizar documentos)

### 3. **Agent Memory (Memória do Agente)**

Sistema de memória persistente por agente usando embeddings vetoriais.

**Como Funciona:**

```php
// 1. Extrair memórias automaticamente
// (a cada 5 mensagens)
AgentMemoryService::extractAndSave($agentId, $conversationId);

// Sistema usa OpenAI para identificar informações importantes:
// - Preferências do cliente
// - Histórico de problemas
// - Contexto relevante

// 2. Recuperar memórias relevantes
$query = "Qual o problema que o cliente teve antes?";
$memories = AgentMemoryService::retrieve($agentId, $query, $limit = 5);

// 3. Adicionar ao contexto da IA
$context['agent_memories'] = $memories;

// 4. IA lembra de conversas anteriores
```

**Exemplo:**

```
Cliente (3 meses atrás): "Meu produto chegou com defeito"
IA: "Enviamos um novo produto"

Cliente (hoje): "Olá"
IA: "Olá! Como vai? O produto que enviamos há 3 meses está funcionando bem?"
     ↑ LEMBROU da conversa anterior através das memórias
```

### 4. **Feedback Detection**

Sistema que detecta automaticamente quando a resposta da IA foi inadequada.

**Como Funciona:**

```php
// Após processar com OpenAI
FeedbackDetectionService::detectAndRegister(
    $agentId,
    $conversationId,
    $messageId,
    $userMessage,
    $aiResponse
);

// Sistema analisa:
// - Cliente reclamou da resposta?
// - Cliente pediu para falar com humano?
// - Resposta foi genérica demais?
// - IA não conseguiu ajudar?

// Se detectar feedback negativo:
// 1. Registrar em ai_feedback_loop
// 2. Notificar supervisores
// 3. Usar para melhorar agente
```

### 5. **Performance Tracking**

Monitoramento de performance dos agentes de IA.

**Métricas:**

```php
AIAgentPerformanceService::getMetrics($agentId, $period = '30d');

// Retorna:
[
    'total_conversations' => 150,
    'avg_response_time_seconds' => 3.5,
    'escalation_rate' => 0.08,          // 8% escaladas para humano
    'customer_satisfaction' => 0.92,     // 92% satisfação
    'cost_per_conversation' => 0.17,     // US$ 0.17 por conversa
    'tokens_per_conversation' => 2500,
    'tools_usage' => [
        'buscar_pedido' => 45,
        'transferir_agente' => 12
    ]
]
```

### 6. **Escalonamento Inteligente**

Sistema de escalonamento automático para humanos.

**Triggers de Escalonamento:**

```php
// 1. Cliente pede explicitamente
"Quero falar com um humano"

// 2. IA não consegue resolver
if ($toolFailed && $retryCount > 2) {
    AIAgentService::escalateToHuman($conversationId, $userId, 'Tool falhou após 3 tentativas');
}

// 3. Conversa muito longa
if ($messageCount > 20) {
    AIAgentService::escalateToHuman($conversationId, $userId, 'Conversa muito longa');
}

// 4. Sentimento negativo detectado
if ($sentiment === 'very_negative') {
    AIAgentService::escalateToHuman($conversationId, $userId, 'Sentimento negativo');
}
```

**Fluxo de Escalonamento:**

```
IA detecta necessidade de escalonamento
    ↓
AIAgentService::escalateToHuman($conversationId, $userId, $reason)
    ↓
1. Atualizar ai_conversations:
   - status = 'escalated'
   - escalated_to_user_id = $userId
    ↓
2. Atualizar conversations:
   - agent_id = $userId
    ↓
3. Enviar mensagem de transição:
   "Vou transferir você para um atendente humano. Aguarde um momento."
    ↓
4. Notificar agente humano (WebSocket)
    ↓
5. Agente humano assume conversa
```

---

## 📊 ESTATÍSTICAS E RELATÓRIOS

### Dados Disponíveis

```php
// 1. Por Conversa
$aiConv = AIConversation::getHistory($id);
// - Histórico completo de mensagens
// - Tools utilizadas
// - Tokens e custos
// - Tempo de execução

// 2. Por Agente
$stats = AIConversation::getAgentStats($agentId, $startDate, $endDate);
// - Total de conversas
// - Total de tokens
// - Total de custos
// - Taxa de conclusão
// - Taxa de escalonamento

// 3. Por Período
$dailyStats = [];
for ($i = 0; $i < 30; $i++) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $stats = AIConversation::where('created_at', 'LIKE', "$date%")
        ->aggregate(['COUNT(*)', 'SUM(tokens_used)', 'SUM(cost)']);
    $dailyStats[$date] = $stats;
}

// 4. Performance
$performance = AIAgentPerformanceService::getMetrics($agentId, '30d');
// - Tempo médio de resposta
// - Taxa de satisfação
// - Custo por conversa
// - Uso de tools
```

---

## 🎓 BOAS PRÁTICAS

### 1. **Configuração de Agentes**

```php
// ✅ BOM: Prompt claro e específico
$prompt = "Você é um agente de suporte técnico especializado em problemas de software. 
Seja empático, objetivo e sempre tente resolver o problema do cliente. 
Se não conseguir resolver, escale para um humano.
Nunca invente informações - use as ferramentas disponíveis para buscar dados.";

// ❌ RUIM: Prompt genérico
$prompt = "Você é um assistente.";
```

### 2. **Uso de Tools**

```php
// ✅ BOM: Tools específicas e bem definidas
'buscar_pedido' => [
    'description' => 'Busca um pedido específico pelo ID',
    'parameters' => [
        'order_id' => ['type' => 'integer', 'required' => true]
    ]
]

// ❌ RUIM: Tool genérica demais
'fazer_algo' => [
    'description' => 'Faz algo',
    'parameters' => []
]
```

### 3. **Limites e Custos**

```php
// ✅ BOM: Sempre configurar limites
[
    'max_conversations' => 50,          // Limite de conversas
    'rate_limits' => [
        'requests_per_minute' => 10
    ],
    'cost_limits' => [
        'daily_limit_usd' => 10.00
    ]
]

// ❌ RUIM: Sem limites (risco de custo elevado)
```

### 4. **Monitoramento**

```php
// ✅ BOM: Monitorar regularmente
- Verificar custos diários
- Analisar taxa de escalonamento
- Revisar feedbacks negativos
- Otimizar prompts baseado em performance

// ❌ RUIM: "Set and forget" (configurar e esquecer)
```

---

## 🔧 TROUBLESHOOTING

### Problemas Comuns

#### 1. IA não responde

**Causas possíveis:**
- ✅ Agente desabilitado (`enabled = false`)
- ✅ Limite de conversas atingido
- ✅ Rate limit atingido
- ✅ API Key inválida

**Solução:**
```php
// Verificar status do agente
$agent = AIAgent::find($agentId);
echo "Enabled: {$agent['enabled']}\n";
echo "Current: {$agent['current_conversations']}\n";
echo "Max: {$agent['max_conversations']}\n";

// Verificar rate limit
$check = AICostControlService::canProcessMessage($agentId);
if (!$check['allowed']) {
    echo "Motivo: {$check['reason']}\n";
}

// Verificar API Key
$apiKey = Setting::get('openai_api_key');
if (empty($apiKey)) {
    echo "API Key não configurada!\n";
}
```

#### 2. Custos muito altos

**Causas:**
- ✅ Prompt muito longo
- ✅ Histórico muito extenso
- ✅ Tools sendo chamadas desnecessariamente
- ✅ Modelo muito caro (gpt-4 vs gpt-3.5)

**Solução:**
```php
// 1. Reduzir tamanho do prompt
// 2. Limitar histórico (max 20 mensagens)
// 3. Usar modelos mais baratos quando possível
// 4. Implementar cache de contexto
// 5. Configurar limites de custo
```

#### 3. IA fornece informações incorretas

**Causas:**
- ✅ Alucinação (IA inventa)
- ✅ Contexto insuficiente
- ✅ Tools não funcionando

**Solução:**
```php
// 1. Melhorar prompt (instruir a não inventar)
$prompt .= "\n\nIMPORTANTE: NUNCA invente informações. 
Se não souber, diga que não sabe e use as ferramentas disponíveis.";

// 2. Adicionar RAG (base de conhecimento)
$relevantDocs = RAGService::search($query);
$context['knowledge_base'] = $relevantDocs;

// 3. Verificar tools
$tools = AIAgent::getTools($agentId);
foreach ($tools as $tool) {
    echo "Tool: {$tool['name']} - Enabled: {$tool['enabled']}\n";
}
```

---

## 📝 CONCLUSÃO

O sistema de **Conversas e Agentes de IA** é uma solução completa e robusta que permite:

### ✅ **Funcionalidades Implementadas**

1. **Atendimento Automatizado**
   - Múltiplos agentes especializados
   - Processamento inteligente com OpenAI
   - Respostas humanizadas (delay configurável)
   
2. **Sistema de Tools**
   - Ferramentas extensíveis
   - Function calling da OpenAI
   - Integração com serviços externos
   
3. **Distribuição Inteligente**
   - Múltiplos algoritmos
   - Inclusão de agentes de IA
   - Balanceamento de carga
   
4. **Controle de Custos**
   - Rate limiting
   - Limites financeiros
   - Monitoramento em tempo real
   
5. **Recursos Avançados**
   - AI Branching (intent detection)
   - RAG (base de conhecimento)
   - Agent Memory (memória persistente)
   - Feedback detection
   - Performance tracking

### 🎯 **Próximos Passos Sugeridos**

1. **Implementar Dashboard de Analytics**
   - Visualização de custos
   - Métricas de performance
   - Comparação entre agentes
   
2. **Melhorar Sistema de Tools**
   - Mais integrações (Zendesk, HubSpot, etc)
   - Tools personalizadas por cliente
   - Marketplace de tools
   
3. **Otimizações de IA**
   - Fine-tuning de modelos
   - Prompt engineering avançado
   - A/B testing de prompts
   
4. **Automações Avançadas**
   - Triggers mais sofisticados
   - Workflows complexos
   - Integração com CRM

---

## 📚 DOCUMENTAÇÃO RELACIONADA

- `CONTEXT_IA.md` - Contexto completo do sistema
- `ARQUITETURA.md` - Arquitetura técnica detalhada
- `DOCUMENTACAO_AI_AGENTS_E_TOOLS.md` - Documentação específica de AI Agents
- `RESUMO_EXECUTIVO_AI_AGENTS_TOOLS.md` - Resumo executivo
- `GUIA_FLUXO_ATENDIMENTO_AUTOMATIZADO.md` - Guia de fluxo de atendimento

---

**Última Atualização:** 31/12/2025
**Versão:** 1.0.0
**Status:** ✅ Completo e Funcional
