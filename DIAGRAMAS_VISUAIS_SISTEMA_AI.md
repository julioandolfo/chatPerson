# 📊 DIAGRAMAS VISUAIS: Sistema de Conversas & AI Agents

> **Representações visuais do funcionamento do sistema**

---

## 🔄 DIAGRAMA 1: Fluxo Completo de Mensagem

```mermaid
sequenceDiagram
    participant C as Cliente (WhatsApp)
    participant W as Webhook Quepasa
    participant CS as ConversationService
    participant AI as AIAgentService
    participant OAI as OpenAIService
    participant API as OpenAI API
    participant TE as ToolExecutor
    participant Q as QuepasaService
    
    C->>W: "Preciso de ajuda com pedido #123"
    W->>CS: processWebhook()
    CS->>CS: Criar/Buscar Conversa
    CS->>CS: Salvar mensagem
    
    alt Tem AI Agent Ativo
        CS->>AI: processMessage(convId, agentId, message)
        AI->>OAI: processMessage(convId, agentId, message, context)
        OAI->>OAI: Montar contexto (prompt + histórico)
        OAI->>API: POST /chat/completions
        
        alt OpenAI solicita tool
            API-->>OAI: tool_calls: buscar_pedido_woocommerce
            OAI->>TE: executeToolCalls(tool_calls)
            TE->>TE: Buscar pedido #123
            TE-->>OAI: Resultado: {status: "processing", total: "R$ 299,90"}
            OAI->>API: POST /chat/completions (com resultado)
            API-->>OAI: Resposta final baseada no resultado
        else OpenAI responde diretamente
            API-->>OAI: Resposta direta
        end
        
        OAI-->>AI: {content: "Seu pedido...", tokens, cost}
        AI->>CS: sendMessage(convId, resposta, 'agent', aiAgentId)
    else Sem AI Agent
        Note over CS: Aguarda agente humano responder
    end
    
    CS->>Q: sendMessage(chatId, text)
    Q->>C: Mensagem via WhatsApp
```

---

## 🏗️ DIAGRAMA 2: Arquitetura de Componentes

```mermaid
graph TB
    subgraph Frontend
        V[Views]
        JS[JavaScript]
        WS[WebSocket Client]
    end
    
    subgraph Controllers
        CC[ConversationController]
        AIC[AIAgentController]
        WC[WebhookController]
    end
    
    subgraph Services
        CS[ConversationService]
        CAS[ConversationAIService]
        AIS[AIAgentService]
        OAS[OpenAIService]
        ATS[AIToolService]
        CSS[ConversationSettingsService]
    end
    
    subgraph Models
        CONV[Conversation]
        AIAG[AIAgent]
        AICO[AIConversation]
        AITL[AITool]
        MSG[Message]
    end
    
    subgraph External
        OAI[OpenAI API]
        WA[WhatsApp/Quepasa]
        N8N[N8N Workflows]
        WOO[WooCommerce]
    end
    
    V --> CC
    JS --> CC
    WS --> CC
    
    CC --> CS
    AIC --> CAS
    WC --> CS
    
    CS --> CONV
    CAS --> AICO
    AIS --> AIAG
    
    CS --> AIS
    AIS --> OAS
    OAS --> OAI
    
    OAS --> ATS
    ATS --> N8N
    ATS --> WOO
    
    CONV --> MSG
    AICO --> AIAG
    AIAG --> AITL
    
    CS --> WA
```

---

## 💾 DIAGRAMA 3: Modelo de Dados

```mermaid
erDiagram
    CONVERSATIONS ||--o| AI_CONVERSATIONS : "1:1 quando IA ativa"
    CONVERSATIONS ||--o{ MESSAGES : "1:N"
    AI_CONVERSATIONS }o--|| AI_AGENTS : "N:1"
    AI_AGENTS ||--o{ AI_AGENT_TOOLS : "1:N"
    AI_TOOLS ||--o{ AI_AGENT_TOOLS : "1:N"
    AI_CONVERSATIONS ||--o{ MESSAGES : "1:N via ai_agent_id"
    
    CONVERSATIONS {
        int id PK
        int contact_id FK
        int agent_id FK "Agente HUMANO (NULL se IA)"
        int funnel_id FK
        int funnel_stage_id FK
        varchar channel
        varchar status
        json metadata
        timestamp created_at
        timestamp updated_at
    }
    
    AI_CONVERSATIONS {
        int id PK
        int conversation_id FK
        int ai_agent_id FK
        json messages "Histórico completo"
        json tools_used "Tools utilizadas"
        int tokens_used
        int tokens_prompt
        int tokens_completion
        decimal cost
        varchar status "active, completed, escalated"
        int escalated_to_user_id FK
        timestamp created_at
        timestamp updated_at
    }
    
    AI_AGENTS {
        int id PK
        varchar name
        text description
        varchar agent_type "SDR, CS, CLOSER"
        text prompt
        varchar model "gpt-4, gpt-3.5-turbo"
        decimal temperature
        int max_tokens
        int max_conversations "0 = ilimitado"
        int current_conversations
        tinyint enabled
        json settings
        timestamp created_at
        timestamp updated_at
    }
    
    AI_TOOLS {
        int id PK
        varchar name
        varchar slug
        text description
        varchar tool_type "woocommerce, n8n, api, system"
        json function_schema "OpenAI format"
        json config
        tinyint enabled
        timestamp created_at
        timestamp updated_at
    }
    
    AI_AGENT_TOOLS {
        int ai_agent_id FK
        int ai_tool_id FK
    }
    
    MESSAGES {
        int id PK
        int conversation_id FK
        varchar sender_type "contact, agent, system"
        int sender_id FK
        text content
        int ai_agent_id FK "Se enviada por IA"
        varchar external_id
        varchar status
        json metadata
        timestamp created_at
    }
```

---

## 🔀 DIAGRAMA 4: Decisão de Atribuição

```mermaid
flowchart TD
    START([Nova Conversa Criada]) --> CHECK_HISTORY{Contato tem<br/>histórico?}
    
    CHECK_HISTORY -->|SIM| GET_PREV[Buscar agente anterior<br/>ContactAgentService]
    CHECK_HISTORY -->|NÃO| CHECK_AUTO{Distribuição<br/>automática<br/>habilitada?}
    
    GET_PREV --> HAS_PREV{Encontrou<br/>agente?}
    HAS_PREV -->|SIM| ASSIGN_PREV[Atribuir mesmo agente]
    HAS_PREV -->|NÃO| CHECK_AUTO
    
    CHECK_AUTO -->|NÃO| NO_ASSIGN[Deixar sem atribuição<br/>agent_id = NULL]
    CHECK_AUTO -->|SIM| GET_METHOD{Qual método?}
    
    GET_METHOD -->|round_robin| RR[Próximo na fila]
    GET_METHOD -->|by_load| BL[Menor carga]
    GET_METHOD -->|by_performance| BP[Melhor performance]
    GET_METHOD -->|percentage| PCT[Por porcentagem]
    
    RR --> CHECK_ID{ID retornado}
    BL --> CHECK_ID
    BP --> CHECK_ID
    PCT --> CHECK_ID
    
    CHECK_ID -->|Positivo| HUMAN[Atribuir Agente Humano<br/>agent_id = ID]
    CHECK_ID -->|Negativo| AI[Criar AIConversation<br/>ai_agent_id = abs(ID)]
    CHECK_ID -->|NULL| NO_ASSIGN
    
    HUMAN --> END([Conversa Atribuída])
    AI --> PROCESS{process_immediately?}
    PROCESS -->|SIM| AI_PROCESS[AIAgentService::processMessage]
    PROCESS -->|NÃO| END
    AI_PROCESS --> END
    NO_ASSIGN --> END
    ASSIGN_PREV --> END
```

---

## 🤖 DIAGRAMA 5: Processamento OpenAI

```mermaid
flowchart TD
    START([AIAgentService::processMessage]) --> GET_AGENT[Obter AIAgent]
    GET_AGENT --> GET_CONTEXT[Montar contexto<br/>Conversation, Contact, Message]
    GET_CONTEXT --> CALL_OAI[OpenAIService::processMessage]
    
    CALL_OAI --> CHECK_KEY{API Key<br/>configurada?}
    CHECK_KEY -->|NÃO| ERROR_KEY[Erro: API Key não configurada]
    CHECK_KEY -->|SIM| CHECK_RATE{Rate limit<br/>OK?}
    
    CHECK_RATE -->|NÃO| ERROR_RATE[Erro: Limite atingido]
    CHECK_RATE -->|SIM| BUILD_MSG[Construir mensagens<br/>System + Histórico + Atual]
    
    BUILD_MSG --> GET_TOOLS[Obter tools do agente]
    GET_TOOLS --> BUILD_PAYLOAD[Montar payload OpenAI]
    BUILD_PAYLOAD --> API_CALL[POST /chat/completions]
    
    API_CALL --> CHECK_RESP{Resposta OK?}
    CHECK_RESP -->|NÃO| ERROR_API[Erro da API]
    CHECK_RESP -->|SIM| HAS_TOOLS{Tem<br/>tool_calls?}
    
    HAS_TOOLS -->|NÃO| DIRECT[Resposta direta]
    HAS_TOOLS -->|SIM| EXEC_TOOLS[Executar tools]
    
    EXEC_TOOLS --> CHECK_RAW{use_raw_response?}
    CHECK_RAW -->|SIM| USE_RAW[Usar raw_message<br/>sem reenviar]
    CHECK_RAW -->|NÃO| ADD_RESULTS[Adicionar resultados<br/>às mensagens]
    
    ADD_RESULTS --> API_CALL2[POST /chat/completions<br/>com resultados]
    API_CALL2 --> FINAL_RESP[Resposta final]
    
    DIRECT --> CALC_COST[Calcular custo e tokens]
    USE_RAW --> CALC_COST
    FINAL_RESP --> CALC_COST
    
    CALC_COST --> SAVE_LOG[Salvar em ai_conversations]
    SAVE_LOG --> SEND[ConversationService::sendMessage]
    SEND --> END([Resposta enviada])
    
    ERROR_KEY --> END
    ERROR_RATE --> END
    ERROR_API --> END
```

---

## 🛠️ DIAGRAMA 6: Execução de Tools

```mermaid
flowchart TD
    START([OpenAI retornou tool_calls]) --> LOOP{Para cada<br/>tool_call}
    
    LOOP --> GET_FUNC[Extrair function name<br/>e arguments]
    GET_FUNC --> FIND_TOOL[Buscar AITool por slug]
    
    FIND_TOOL --> FOUND{Tool<br/>encontrada?}
    FOUND -->|NÃO| ERROR[Erro: Tool não encontrada]
    FOUND -->|SIM| CHECK_TYPE{Qual tipo?}
    
    CHECK_TYPE -->|woocommerce| WOO[WooCommerceToolExecutor]
    CHECK_TYPE -->|n8n| N8N[N8NToolExecutor]
    CHECK_TYPE -->|database| DB[DatabaseToolExecutor]
    CHECK_TYPE -->|api| API[ApiToolExecutor]
    CHECK_TYPE -->|system| SYS[SystemToolExecutor]
    
    WOO --> EXEC[Executar tool<br/>com arguments]
    N8N --> EXEC
    DB --> EXEC
    API --> EXEC
    SYS --> EXEC
    
    EXEC --> SUCCESS{Sucesso?}
    SUCCESS -->|NÃO| LOG_ERROR[Log erro]
    SUCCESS -->|SIM| LOG_SUCCESS[Log sucesso]
    
    LOG_ERROR --> RESULT[Retornar resultado]
    LOG_SUCCESS --> RESULT
    ERROR --> RESULT
    
    RESULT --> MORE{Mais<br/>tool_calls?}
    MORE -->|SIM| LOOP
    MORE -->|NÃO| RETURN[Retornar array<br/>de resultados]
    RETURN --> END([Fim])
```

---

## 📊 DIAGRAMA 7: Métodos de Distribuição

```mermaid
graph LR
    subgraph "Round Robin"
        RR_START[Início] --> RR_SORT[Ordenar por<br/>last_assignment_at]
        RR_SORT --> RR_SELECT[Selecionar primeiro]
        RR_SELECT --> RR_END[Atribuir]
    end
    
    subgraph "By Load"
        BL_START[Início] --> BL_SORT[Ordenar por<br/>current_conversations]
        BL_SORT --> BL_SELECT[Selecionar menor carga]
        BL_SELECT --> BL_END[Atribuir]
    end
    
    subgraph "By Performance"
        BP_START[Início] --> BP_CALC[Calcular scores<br/>satisfação, tempo médio]
        BP_CALC --> BP_SORT[Ordenar por score]
        BP_SORT --> BP_SELECT[Selecionar melhor]
        BP_SELECT --> BP_END[Atribuir]
    end
    
    subgraph "Percentage"
        PCT_START[Início] --> PCT_RAND[Gerar número aleatório]
        PCT_RAND --> PCT_MATCH[Buscar regra matching]
        PCT_MATCH --> PCT_END[Atribuir conforme regra]
    end
```

---

## 🎯 DIAGRAMA 8: Estados da Conversa

```mermaid
stateDiagram-v2
    [*] --> open: Conversa criada
    
    open --> pending: Aguardando atribuição
    pending --> open: Agente atribuído
    
    open --> active_ai: AI Agent atribuído
    active_ai --> escalated: Cliente pede humano
    escalated --> open: Humano assume
    
    open --> resolved: Problema resolvido
    resolved --> closed: Tempo expirou
    
    open --> closed: Manualmente fechada
    closed --> [*]
    
    note right of active_ai
        status = 'active' em ai_conversations
        agent_id = NULL em conversations
    end note
    
    note right of escalated
        status = 'escalated' em ai_conversations
        agent_id = ID humano em conversations
    end note
```

---

## 🔄 DIAGRAMA 9: Ciclo de Vida AIConversation

```mermaid
stateDiagram-v2
    [*] --> creating: addAIAgent()
    creating --> active: Criado com sucesso
    
    active --> processing: processMessage()
    processing --> active: Resposta enviada
    
    active --> escalated: removeAIAgent(assign_to_human=true)
    active --> removed: removeAIAgent(assign_to_human=false)
    active --> completed: Conversa resolvida
    
    escalated --> [*]: Histórico mantido
    removed --> [*]: Histórico mantido
    completed --> [*]: Histórico mantido
    
    note right of active
        IA responde mensagens
        automaticamente
    end note
    
    note right of escalated
        Agente humano assume
        IA não responde mais
    end note
```

---

## 🌊 DIAGRAMA 10: Fluxo de AI Branching (Intents)

```mermaid
flowchart TD
    START([Cliente envia mensagem]) --> CHECK_AI{Tem IA<br/>ativa?}
    CHECK_AI -->|NÃO| NORMAL[Fluxo normal]
    CHECK_AI -->|SIM| CHECK_BRANCH{AI Branching<br/>habilitado?}
    
    CHECK_BRANCH -->|NÃO| PROCESS_AI[Processar com IA<br/>normalmente]
    CHECK_BRANCH -->|SIM| DETECT[Detectar intent<br/>na mensagem]
    
    DETECT --> CHECK_INTENT{Intent<br/>detectado?}
    CHECK_INTENT -->|NÃO| PROCESS_AI
    CHECK_INTENT -->|SIM| SEND_EXIT[Enviar exit_message]
    
    SEND_EXIT --> ROUTE[Rotear para node destino]
    ROUTE --> CHECK_REMOVE{Deve remover<br/>IA?}
    
    CHECK_REMOVE -->|SIM| REMOVE_AI[ConversationAIService::removeAIAgent]
    CHECK_REMOVE -->|NÃO| KEEP_AI[Manter IA mas<br/>roteou fluxo]
    
    REMOVE_AI --> ASSIGN{Atribuir<br/>humano?}
    ASSIGN -->|SIM| HUMAN[Atribuir agente humano]
    ASSIGN -->|NÃO| END_UNASSIGN[Deixar sem atribuição]
    
    HUMAN --> END([Fim])
    KEEP_AI --> END
    END_UNASSIGN --> END
    PROCESS_AI --> END
    NORMAL --> END
```

---

## 💰 DIAGRAMA 11: Cálculo de Custo

```mermaid
flowchart LR
    START([OpenAI retorna tokens]) --> EXTRACT[Extrair tokens<br/>prompt_tokens<br/>completion_tokens]
    
    EXTRACT --> GET_PRICES[Obter preços por modelo]
    GET_PRICES --> CALC_PROMPT[Custo Prompt =<br/>prompt_tokens/1000 * price_prompt]
    CALC_PROMPT --> CALC_COMP[Custo Completion =<br/>completion_tokens/1000 * price_completion]
    
    CALC_COMP --> SUM[Total = Custo Prompt + Custo Completion]
    SUM --> SAVE[Salvar em ai_conversations<br/>tokens_prompt, tokens_completion<br/>tokens_used, cost]
    
    SAVE --> UPDATE[Atualizar estatísticas<br/>do agente]
    UPDATE --> END([Fim])
    
    subgraph Preços GPT-4
        P1["Prompt: $0.03/1K tokens"]
        P2["Completion: $0.06/1K tokens"]
    end
    
    subgraph Preços GPT-3.5-turbo
        P3["Prompt: $0.0015/1K tokens"]
        P4["Completion: $0.002/1K tokens"]
    end
```

---

## 🎭 DIAGRAMA 12: Tipos de Agentes e Uso

```mermaid
mindmap
  root((AI Agents))
    SDR
      Qualificação de leads
      Captura de informações
      Agendamento inicial
    CS
      Suporte pós-venda
      Resolução de dúvidas
      Acompanhamento
    CLOSER
      Negociação final
      Fechamento de vendas
      Upsell
    SUPPORT
      Suporte técnico
      Troubleshooting
      Documentação
    CUSTOM
      Personalizado
      Uso específico
```

---

## 🔧 DIAGRAMA 13: Tipos de Tools

```mermaid
graph TD
    TOOLS[AI Tools] --> WOOCOMMERCE[WooCommerce]
    TOOLS --> N8N[N8N]
    TOOLS --> DATABASE[Database]
    TOOLS --> API[API]
    TOOLS --> SYSTEM[System]
    TOOLS --> DOCUMENT[Document]
    
    WOOCOMMERCE --> W1[Buscar Pedidos]
    WOOCOMMERCE --> W2[Buscar Produtos]
    WOOCOMMERCE --> W3[Atualizar Status]
    
    N8N --> N1[Workflows Personalizados]
    N8N --> N2[Integrações Complexas]
    N8N --> N3[Resposta Direta]
    
    DATABASE --> D1[Consultas SQL]
    DATABASE --> D2[Buscar Dados]
    DATABASE --> D3[Relatórios]
    
    API --> A1[HTTP Requests]
    API --> A2[APIs Externas]
    API --> A3[Webhooks]
    
    SYSTEM --> S1[Criar Conversa]
    SYSTEM --> S2[Atribuir Agente]
    SYSTEM --> S3[Mover Funil]
    
    DOCUMENT --> DO1[Base de Conhecimento]
    DOCUMENT --> DO2[FAQs]
    DOCUMENT --> DO3[Busca Semântica]
```

---

## 📈 DIAGRAMA 14: Monitoramento e Métricas

```mermaid
flowchart TD
    subgraph Coleta
        MSGS[Mensagens] --> LOG[ai_conversations]
        TOOLS[Tools Usadas] --> LOG
        TOKENS[Tokens] --> LOG
        COST[Custo] --> LOG
    end
    
    subgraph Processamento
        LOG --> AGG[Agregação por período]
        AGG --> CALC[Cálculo de métricas]
    end
    
    subgraph Métricas
        CALC --> M1[Conversas/dia]
        CALC --> M2[Tokens/dia]
        CALC --> M3[Custo/dia]
        CALC --> M4[Tools mais usadas]
        CALC --> M5[Tempo médio resposta]
        CALC --> M6[Taxa de escalação]
    end
    
    subgraph Dashboard
        M1 --> DASH[Dashboard]
        M2 --> DASH
        M3 --> DASH
        M4 --> DASH
        M5 --> DASH
        M6 --> DASH
    end
    
    DASH --> ALERT{Alertas?}
    ALERT -->|Custo alto| A1[Notificar Admin]
    ALERT -->|Rate limit| A2[Notificar Admin]
    ALERT -->|OK| END([Fim])
    A1 --> END
    A2 --> END
```

---

## 🎓 LEGENDA

### Símbolos Usados

- 🔷 **Decisão** - Diamante (sim/não)
- 📦 **Processo** - Retângulo
- 🔵 **Início/Fim** - Oval
- ➡️ **Fluxo** - Seta
- 🔴 **Estado** - Circle/Node

### Cores (quando aplicável)

- 🟢 **Verde** - Sucesso/OK
- 🔴 **Vermelho** - Erro/Falha
- 🟡 **Amarelo** - Atenção/Processando
- 🔵 **Azul** - Informação/Normal

---

**Documentação criada em:** 31/12/2025  
**Versão:** 1.0  
**Formato:** Mermaid Diagrams
