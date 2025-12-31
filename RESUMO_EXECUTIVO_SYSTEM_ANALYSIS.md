# 📋 RESUMO EXECUTIVO - ANÁLISE DO SISTEMA

> **Resumo em formato de apresentação para entendimento rápido**
> 
> Data: 31/12/2025

---

## 🎯 O QUE É O SISTEMA?

Sistema completo de **atendimento multicanal** com **inteligência artificial** integrada.

### Principais Características

| Funcionalidade | Status | Descrição |
|---|---|---|
| 🤖 **Agentes de IA** | ✅ Funcional | Atendimento automatizado via OpenAI |
| 💬 **Multicanal** | ✅ Funcional | WhatsApp, Instagram, Facebook, Email, etc |
| 🛠️ **Tools (Ferramentas)** | ✅ Funcional | IA pode executar ações no sistema |
| 🎯 **Distribuição Inteligente** | ✅ Funcional | Atribuição automática (humanos + IA) |
| 💰 **Controle de Custos** | ✅ Funcional | Rate limiting e limites financeiros |
| 📊 **Analytics** | ✅ Funcional | Métricas, tokens, custos, performance |
| 🧠 **Memória de Agente** | ✅ Funcional | IA lembra conversas anteriores (RAG) |
| 🔀 **AI Branching** | ✅ Funcional | Intent detection + automações |

---

## 🏗️ ARQUITETURA SIMPLIFICADA

```
CLIENTE (WhatsApp, Instagram, etc)
         ↓
    WEBHOOK / API
         ↓
 CONVERSATION SERVICE
         ↓
    ┌────┴────┐
    │         │
 HUMANO      IA
    │         │
    │    ┌────┴────┐
    │    │         │
    │  OPENAI   TOOLS
    │    │         │
    └────┴─────────┘
         ↓
   RESPOSTA AO CLIENTE
```

---

## 📊 ESTRUTURA DE BANCO DE DADOS

### 4 Tabelas Principais

#### 1. `conversations` - Conversas
- Armazena todas as conversas
- Relaciona contato + agente + funil
- Status: open, pending, closed

#### 2. `ai_agents` - Agentes de IA
- Configuração dos agentes virtuais
- Prompt, modelo, temperatura
- Limites e configurações

#### 3. `ai_conversations` - Logs de IA
- Histórico de interações com IA
- Tokens, custos, tools usadas
- Status: active, completed, escalated

#### 4. `ai_tools` - Ferramentas
- Ferramentas que a IA pode usar
- Function schemas para OpenAI
- Tipos: system, woocommerce, api, etc

### Relacionamento N:N

```
ai_agents ←→ ai_tools (através de ai_agent_tools)
```

Um agente pode ter várias tools, e uma tool pode ser usada por vários agentes.

---

## 🔄 FLUXO BÁSICO

### 1. Cliente Envia Mensagem

```
Cliente → WhatsApp: "Olá, quero ajuda"
```

### 2. Webhook Recebe

```
WhatsApp → Sistema: POST /api/webhooks/whatsapp/message
```

### 3. Criar ou Buscar Conversa

```php
ConversationService::create([
    'contact_id' => 123,
    'channel' => 'whatsapp'
])
```

### 4. Distribuir Automaticamente

**Prioridades:**
1. Agente anterior do contato (se houver)
2. Distribuição automática:
   - Round-robin
   - Por carga
   - Por performance
   - Por porcentagem

**Resultado:**
- ID positivo (ex: 10) → Agente humano #10
- ID negativo (ex: -5) → Agente de IA #5

### 5A. Se Humano → Aguardar Resposta

```
Conversa fica na fila do agente humano
```

### 5B. Se IA → Processar Automaticamente

```php
// 1. Criar registro ai_conversation
AIConversation::create([
    'conversation_id' => $convId,
    'ai_agent_id' => 5,
    'status' => 'active'
])

// 2. Processar conversa
AIAgentService::processConversation($convId, 5)

// 3. Se tem mensagem, processar
// 4. Se não tem, enviar boas-vindas
```

### 6. Processar com OpenAI

```php
OpenAIService::processMessage($convId, $agentId, $message)
```

**O que acontece:**
1. Obter configuração do agente (prompt, model, etc)
2. Verificar limites (rate limiting, custos)
3. Obter tools disponíveis
4. Construir mensagens (histórico + contexto)
5. Chamar OpenAI API
6. Se precisar de tools → Executar e reenviar
7. Retornar resposta final

### 7. Enviar Resposta ao Cliente

```php
ConversationService::sendMessage(
    $convId,
    $content = "Resposta da IA",
    $senderType = 'agent',
    $aiAgentId = 5
)
```

### 8. Cliente Recebe no WhatsApp

```
WhatsApp → Cliente: "Resposta da IA"
```

---

## 🤖 COMO FUNCIONA UM AGENTE DE IA

### Configuração Básica

```php
[
    'name' => 'Suporte Técnico',
    'agent_type' => 'SUPPORT',
    'prompt' => 'Você é um agente de suporte técnico...',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000,
    'enabled' => true,
    'max_conversations' => 50,  // Limite simultâneo
    'settings' => [
        'response_delay_min' => 2,      // Delay humanizado
        'response_delay_max' => 5,
        'context_timer_seconds' => 30,  // Timer de contexto
        'prefer_tools' => true,         // Preferir usar tools
        'welcome_message' => 'Olá! Como posso ajudar?'
    ]
]
```

### Tipos Disponíveis

| Tipo | Descrição |
|---|---|
| `SDR` | Sales Development Representative (prospecção) |
| `CS` | Customer Success (sucesso do cliente) |
| `CLOSER` | Fechamento de vendas |
| `FOLLOWUP` | Follow-up automático |
| `SUPPORT` | Suporte técnico |
| `ONBOARDING` | Onboarding de clientes |
| `GENERAL` | Propósito geral |

### Prompt System

O prompt é o "cérebro" do agente. Exemplo:

```
Você é um agente de suporte técnico especializado em produtos eletrônicos.

REGRAS:
- Seja empático e educado
- Resolva problemas de forma prática
- Use as ferramentas disponíveis para buscar informações
- NUNCA invente dados - sempre use as tools
- Se não conseguir resolver, escale para humano

CONTEXTO:
- Empresa: TechStore
- Produtos: Notebooks, Smartphones, Tablets
- Horário: 9h às 18h
```

---

## 🛠️ SISTEMA DE TOOLS (FERRAMENTAS)

### O Que São Tools?

Ferramentas que a IA pode **chamar** para executar ações ou buscar informações.

### Tipos de Tools

#### 1. System Tools (Internas)

| Tool | Descrição |
|---|---|
| `buscar_conversas_anteriores` | Histórico do contato |
| `transferir_para_agente` | Escalar para humano |
| `encerrar_conversa` | Fechar conversa |
| `adicionar_tag` | Adicionar tag |
| `mover_para_etapa` | Mover no funil |
| `criar_atividade` | Criar tarefa |

#### 2. External Tools

| Tool | Descrição |
|---|---|
| `buscar_pedido_woocommerce` | Buscar pedido WooCommerce |
| `consultar_dados_cliente` | Consultar banco de dados |
| `chamar_api_externa` | Chamar API externa |
| `webhook_n8n` | Enviar para N8N |

### Como Funciona (Exemplo Real)

**Cliente:**
> "Quero saber o status do pedido #12345"

**Processamento:**

1. **IA recebe mensagem** + contexto
2. **IA identifica** que precisa buscar pedido
3. **IA chama tool:** `buscar_pedido_woocommerce(order_id: 12345)`
4. **Sistema executa** a tool:
   ```php
   // GET https://meusite.com/wp-json/wc/v3/orders/12345
   $order = WooCommerce::getOrder(12345);
   ```
5. **Retorna resultado:**
   ```json
   {
     "success": true,
     "order": {
       "id": 12345,
       "status": "processing",
       "total": "299.90"
     }
   }
   ```
6. **IA formula resposta:**
   > "Seu pedido #12345 está em processamento. O valor total é R$ 299,90. Deve ser enviado em breve! 📦"

### Criar uma Tool

```php
AIToolService::create([
    'name' => 'Buscar Pedido',
    'slug' => 'buscar_pedido_woocommerce',
    'description' => 'Busca pedido no WooCommerce',
    'tool_type' => 'woocommerce',
    'function_schema' => [
        'type' => 'function',
        'function' => [
            'name' => 'buscar_pedido_woocommerce',
            'description' => 'Busca um pedido pelo ID',
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
        'api_url' => 'https://meusite.com/wp-json/wc/v3',
        'consumer_key' => 'ck_...',
        'consumer_secret' => 'cs_...'
    ],
    'enabled' => true
]);
```

### Associar Tool ao Agente

```php
AIAgent::addTool($agentId, $toolId, [], true);
```

---

## 🎯 DISTRIBUIÇÃO AUTOMÁTICA

### Como Funciona

Quando uma conversa é criada **sem agente definido**, o sistema distribui automaticamente.

### Métodos Disponíveis

#### 1. Round-Robin (Revezamento)

Distribui de forma circular:

```
Agentes: A, B, C, D

Conversa 1 → A
Conversa 2 → B
Conversa 3 → C
Conversa 4 → D
Conversa 5 → A (volta ao início)
```

#### 2. By Load (Por Carga)

Distribui para quem tem **menos conversas** ativas:

```
A (5 conversas)
B (2 conversas)  ← SELECIONADO
C (8 conversas)
D (3 conversas)
```

#### 3. By Performance (Por Performance)

Distribui para quem tem **melhor desempenho**:

```
A (95% satisfação, 5min médio)  ← SELECIONADO
B (90% satisfação, 8min médio)
C (85% satisfação, 10min médio)
```

#### 4. By Percentage (Por Porcentagem)

Distribui baseado em **porcentagens** configuradas:

```json
{
    "rules": [
        {"agent_id": 10, "percentage": 50},  // 50%
        {"agent_id": 20, "percentage": 30},  // 30%
        {"department_id": 5, "percentage": 20}  // 20%
    ]
}
```

### Incluir Agentes de IA

**Configuração:**

```php
ConversationSettingsService::updateSettings([
    'distribution' => [
        'assign_to_ai_agent' => true  // ← HABILITAR IA
    ]
]);
```

**Como funciona:**

1. Sistema busca agentes **humanos** disponíveis
2. Sistema busca agentes de **IA** disponíveis
3. **Combina** em um único array
4. Agentes de IA recebem **ID negativo** (ex: -5)
5. Aplica algoritmo de distribuição normalmente
6. Se selecionar ID negativo → É agente de IA

**Vantagens:**

- ✅ Balancear carga entre humanos e IA
- ✅ IA atende volume alto
- ✅ Humanos focam em casos complexos
- ✅ 24/7 com IA, horário comercial com humanos

---

## 💰 CONTROLE DE CUSTOS

### 1. Rate Limiting

Limite de requisições por tempo:

```php
[
    'requests_per_minute' => 10,
    'requests_per_hour' => 100,
    'requests_per_day' => 1000
]
```

**Verificação:**

```php
$check = AICostControlService::canProcessMessage($agentId);

if (!$check['allowed']) {
    // "Limite de 10 req/min atingido. Aguarde 45s."
}
```

### 2. Limites de Custo

Limites financeiros:

```php
[
    'daily_limit_usd' => 10.00,    // US$ 10/dia
    'monthly_limit_usd' => 200.00  // US$ 200/mês
]
```

### 3. Limites de Conversas Simultâneas

Na tabela `ai_agents`:

```sql
max_conversations INT NULL            -- Limite
current_conversations INT DEFAULT 0   -- Atual
```

**Verificação automática:**

```php
if (!AIAgent::canReceiveMoreConversations($agentId)) {
    // Agente no limite, buscar outro
}
```

### Cálculo de Custos (Dez 2025)

| Modelo | Prompt | Completion |
|---|---|---|
| gpt-4 | $0.03/1K tokens | $0.06/1K tokens |
| gpt-4-turbo | $0.01/1K tokens | $0.03/1K tokens |
| gpt-3.5-turbo | $0.001/1K tokens | $0.002/1K tokens |

**Exemplo:**

```
Conversa com 500 tokens prompt + 200 tokens completion (gpt-4):
= (500 * 0.03/1000) + (200 * 0.06/1000)
= 0.015 + 0.012
= US$ 0.027 por conversa
```

### Métricas Disponíveis

```php
$stats = AIConversation::getAgentStats($agentId, '2025-12-01', '2025-12-31');

// Retorna:
[
    'total_conversations' => 150,
    'total_tokens' => 500000,
    'total_cost' => 25.50,      // US$ 25.50
    'avg_tokens' => 3333,
    'completed_conversations' => 140,
    'escalated_conversations' => 10
]
```

---

## 🚀 RECURSOS AVANÇADOS

### 1. AI Branching (Intent Detection)

**O que é:**
Sistema que detecta intenção do cliente **ANTES** de processar com OpenAI.

**Como funciona:**

```
Cliente: "Quero cancelar"
    ↓
Sistema detecta intent "cancelar"
    ↓
Executa automação configurada
    ↓
NÃO chama OpenAI (economia!)
```

**Vantagens:**
- ✅ Economia de tokens
- ✅ Respostas mais rápidas
- ✅ Controle fino do fluxo

### 2. RAG (Retrieval Augmented Generation)

**O que é:**
Base de conhecimento com busca semântica.

**Como funciona:**

```
1. Adicionar documentos:
   - Política de trocas
   - Manual do produto
   - FAQ

2. Quando IA processa mensagem:
   - Busca documentos relevantes
   - Adiciona ao contexto
   - IA responde baseada nos docs

3. Resultado:
   - Respostas precisas
   - Baseadas em documentação oficial
   - Sem alucinações
```

### 3. Agent Memory (Memória)

**O que é:**
IA lembra de conversas anteriores do cliente.

**Exemplo:**

```
Conversa 1 (3 meses atrás):
Cliente: "Meu produto chegou com defeito"
IA: "Vamos enviar um novo"

Conversa 2 (hoje):
Cliente: "Olá"
IA: "Olá! Como vai? O produto que enviamos há 3 meses está OK?"
     ↑ LEMBROU da conversa anterior!
```

### 4. Timer de Contexto

**O que é:**
Aguarda múltiplas mensagens antes de responder.

**Exemplo:**

```
Configuração: context_timer_seconds = 30

Cliente (10:00:00): "Oi"
Cliente (10:00:05): "Quero ajuda"
Cliente (10:00:10): "Com meu pedido #12345"
    ↓
Timer expira (30s)
    ↓
IA processa as 3 mensagens JUNTAS
    ↓
Resposta mais contextualizada
```

### 5. Escalonamento Inteligente

**Triggers:**

| Situação | Ação |
|---|---|
| Cliente pede humano | Escalar automaticamente |
| Conversa muito longa (20+ msgs) | Escalar |
| IA não consegue resolver | Escalar |
| Sentimento negativo | Escalar |
| Tool falha 3x | Escalar |

**Fluxo:**

```
IA detecta necessidade
    ↓
AIAgentService::escalateToHuman()
    ↓
1. ai_conversations.status = 'escalated'
2. conversations.agent_id = user_id
3. Enviar msg: "Transferindo para humano..."
4. Notificar agente humano
    ↓
Humano assume conversa
```

---

## 📊 MÉTRICAS E ANALYTICS

### Por Conversa

```php
$aiConv = AIConversation::getHistory($id);

// Retorna:
- Histórico completo de mensagens
- Tools utilizadas (quais, quando, resultado)
- Tokens usados
- Custo total
- Tempo de execução
- Status final
```

### Por Agente

```php
$stats = AIConversation::getAgentStats($agentId, $startDate, $endDate);

// Retorna:
- Total de conversas
- Total de tokens
- Total de custos
- Taxa de conclusão
- Taxa de escalonamento
- Tempo médio de resposta
```

### Dashboard Sugerido

```
┌─────────────────────────────────────────────────┐
│ DASHBOARD DE AGENTES DE IA                      │
├─────────────────────────────────────────────────┤
│                                                 │
│ 📊 HOJE                                         │
│ • Conversas: 150                                │
│ • Tokens: 500K                                  │
│ • Custo: US$ 12.50                              │
│ • Taxa de Sucesso: 92%                          │
│                                                 │
│ 📈 ÚLTIMOS 30 DIAS                              │
│ • Conversas: 4.500                              │
│ • Tokens: 15M                                   │
│ • Custo: US$ 375.00                             │
│ • Economia vs Humanos: ~80%                     │
│                                                 │
│ 🤖 POR AGENTE                                   │
│ ┌───────────┬──────────┬──────────┬──────────┐ │
│ │ Agente    │ Conversas│ Custo    │ Satisfação│ │
│ ├───────────┼──────────┼──────────┼──────────┤ │
│ │ Suporte   │ 2.000    │ $150.00  │ 95%      │ │
│ │ Vendas    │ 1.500    │ $125.00  │ 90%      │ │
│ │ CS        │ 1.000    │ $100.00  │ 93%      │ │
│ └───────────┴──────────┴──────────┴──────────┘ │
│                                                 │
│ 🛠️ TOOLS MAIS USADAS                            │
│ 1. buscar_pedido (450x)                        │
│ 2. adicionar_tag (320x)                        │
│ 3. transferir_agente (45x)                     │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### Para Começar a Usar

- [ ] 1. Configurar API Key da OpenAI em Settings
- [ ] 2. Criar primeiro agente de IA
- [ ] 3. Configurar prompt do agente
- [ ] 4. Habilitar agente (`enabled = true`)
- [ ] 5. Configurar limites (custos, conversas)
- [ ] 6. Criar tools básicas (system tools)
- [ ] 7. Associar tools ao agente
- [ ] 8. Configurar distribuição automática
- [ ] 9. Habilitar `assign_to_ai_agent = true`
- [ ] 10. Testar com conversa real
- [ ] 11. Monitorar métricas
- [ ] 12. Ajustar prompt baseado em feedback

### Para Produção

- [ ] 1. Configurar rate limiting
- [ ] 2. Configurar limites de custo
- [ ] 3. Implementar monitoramento (dashboard)
- [ ] 4. Configurar alertas de custo
- [ ] 5. Definir SLA de escalonamento
- [ ] 6. Treinar equipe em supervisão de IA
- [ ] 7. Criar documentação interna
- [ ] 8. Implementar feedback loop
- [ ] 9. Configurar backup de conversas
- [ ] 10. Testar cenários de falha

---

## 🎯 CENÁRIOS DE USO

### 1. Suporte Técnico 24/7

**Configuração:**
- Agente tipo `SUPPORT`
- Tools: buscar_pedido, buscar_faq
- Escalonamento: após 20 mensagens ou sentimento negativo

**Resultado:**
- 80% das dúvidas resolvidas pela IA
- Humanos focam em casos complexos
- Disponibilidade 24/7

### 2. Vendas e Qualificação (SDR)

**Configuração:**
- Agente tipo `SDR`
- Tools: consultar_estoque, calcular_frete
- Escalonamento: quando lead qualificado

**Resultado:**
- Qualificação automática de leads
- Respostas imediatas sobre produtos
- Closers focam em fechamento

### 3. Follow-up Automático

**Configuração:**
- Agente tipo `FOLLOWUP`
- Tools: agendar_followup, verificar_status
- Trigger: 3 dias após compra

**Resultado:**
- 100% dos clientes recebem follow-up
- Aumento na satisfação
- Detecção precoce de problemas

---

## 🏆 PRINCIPAIS BENEFÍCIOS

### Para o Negócio

| Benefício | Impacto |
|---|---|
| **Redução de Custos** | 60-80% vs equipe humana |
| **Disponibilidade** | 24/7 sem custo adicional |
| **Escalabilidade** | Atender 1000+ conversas simultâneas |
| **Consistência** | Respostas padronizadas |
| **Velocidade** | Resposta em 3-5 segundos |

### Para a Equipe

| Benefício | Impacto |
|---|---|
| **Menos Repetitivo** | IA resolve dúvidas simples |
| **Foco em Complexo** | Tempo para casos difíceis |
| **Menos Burnout** | Volume gerenciável |
| **Melhor Performance** | Métricas melhores |

### Para o Cliente

| Benefício | Impacto |
|---|---|
| **Resposta Rápida** | Sem espera |
| **24/7** | Atendimento a qualquer hora |
| **Resolução Eficaz** | IA com acesso a dados |
| **Humano quando Necessário** | Escalonamento inteligente |

---

## 📚 PRÓXIMOS PASSOS

### 1. Implementar Dashboard Analytics
- Visualização de custos
- Comparação de agentes
- Gráficos de performance

### 2. Expandir Sistema de Tools
- Mais integrações (Zendesk, HubSpot)
- Tools personalizadas
- Marketplace de tools

### 3. Melhorar IA
- Fine-tuning de modelos
- A/B testing de prompts
- Otimização de custos

### 4. Automações Avançadas
- Workflows complexos
- Triggers sofisticados
- Integração com CRM

---

## 📖 DOCUMENTAÇÃO COMPLETA

Para análise detalhada, consulte:

- **`ANALISE_COMPLETA_CONVERSATIONS_AI_AGENTS.md`** - Análise técnica completa
- **`DIAGRAMAS_SYSTEM_FLOW.md`** - Diagramas visuais detalhados
- **`DOCUMENTACAO_AI_AGENTS_E_TOOLS.md`** - Documentação específica
- **`ARQUITETURA.md`** - Arquitetura do sistema

---

**Última Atualização:** 31/12/2025
**Versão:** 1.0.0
**Status:** ✅ Sistema Funcional e em Produção
