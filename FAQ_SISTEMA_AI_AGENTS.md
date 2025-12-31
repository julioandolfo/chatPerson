# ❓ FAQ: Perguntas Frequentes - Sistema de AI Agents

> **Respostas rápidas para dúvidas comuns**

---

## 📑 ÍNDICE

1. [Conceitos Básicos](#-conceitos-básicos)
2. [Atribuição e Distribuição](#-atribuição-e-distribuição)
3. [Agentes de IA](#-agentes-de-ia)
4. [Tools e Function Calling](#-tools-e-function-calling)
5. [Custos e Rate Limiting](#-custos-e-rate-limiting)
6. [Logs e Monitoramento](#-logs-e-monitoramento)
7. [Troubleshooting](#-troubleshooting)
8. [Configurações](#-configurações)
9. [Integrações](#-integrações)
10. [Performance](#-performance)

---

## 🎯 CONCEITOS BÁSICOS

### P: O que é um AI Agent?

**R:** Um AI Agent é um "agente virtual" que usa Inteligência Artificial (OpenAI GPT-4 ou GPT-3.5-turbo) para atender clientes automaticamente. Ele pode:
- Responder perguntas
- Buscar informações (pedidos, produtos, etc)
- Executar ações (criar conversa, mover etapa de funil)
- Escalonar para humano quando necessário

---

### P: Qual a diferença entre `agent_id` e `ai_agent_id`?

**R:**
- **`agent_id`** (tabela `conversations`) → ID do agente **HUMANO** (tabela `users`)
- **`ai_agent_id`** (tabela `ai_conversations`) → ID do agente de **IA** (tabela `ai_agents`)

Uma conversa pode ter:
- `agent_id` = 10, `ai_agent_id` = NULL → Atendida por humano
- `agent_id` = NULL, `ai_agent_id` = 5 → Atendida por IA
- `agent_id` = NULL, `ai_agent_id` = NULL → Não atribuída

---

### P: Como sei se uma conversa está sendo atendida por IA?

**R:** Verifique se existe registro ativo em `ai_conversations`:

```sql
SELECT * FROM ai_conversations 
WHERE conversation_id = 474 
  AND status = 'active';
```

Ou via código:

```php
$aiConversation = AIConversation::getByConversationId(474);
$isAIActive = $aiConversation && $aiConversation['status'] === 'active';
```

---

### P: O que significa ID negativo na atribuição?

**R:** Quando a distribuição automática retorna ID **negativo**, significa que foi atribuído um **AI Agent**:

```php
$assignedId = -5;  // Negativo!
$aiAgentId = abs($assignedId);  // 5 (AI Agent ID 5)
```

Isso é usado para diferenciar agentes humanos (IDs positivos) de agentes de IA (IDs negativos) no mesmo fluxo de atribuição.

---

## 🤝 ATRIBUIÇÃO E DISTRIBUIÇÃO

### P: Quais são os métodos de distribuição disponíveis?

**R:**
1. **Manual** - Admin atribui manualmente
2. **Agente do Contato** - Reatribui último agente que atendeu (prioridade máxima)
3. **Round-Robin** - Próximo na fila
4. **By Load** - Menor carga de conversas
5. **By Performance** - Melhor performance (satisfação, tempo)
6. **Percentage** - Por porcentagem configurada

---

### P: Como incluir AI Agents na distribuição automática?

**R:** Configure `assign_to_ai_agent = true` nas configurações:

```php
ConversationSettingsService::updateSettings([
    'distribution' => [
        'enabled' => true,
        'method' => 'by_load',
        'assign_to_ai_agent' => true,  // ✅ Incluir IA
        'ai_agent_priority' => 'high'  // low, normal, high
    ]
]);
```

---

### P: Posso ter regras diferentes por funil/departamento?

**R:** Sim! As configurações suportam filtros:

```php
'distribution' => [
    'enabled' => true,
    'method' => 'by_load',
    'filters' => [
        'funnel_id' => 4,           // Apenas funil 4
        'department_id' => null,    // Todos departamentos
        'funnel_stage_id' => null   // Todas etapas
    ]
]
```

---

### P: Como priorizar AI Agents sobre humanos (ou vice-versa)?

**R:** Use `ai_agent_priority`:

```php
'ai_agent_priority' => 'high'   // IA tem prioridade
'ai_agent_priority' => 'normal' // Igual peso
'ai_agent_priority' => 'low'    // Humanos têm prioridade
```

Quando `high`, IA aparece primeiro na lista de agentes disponíveis.

---

## 🤖 AGENTES DE IA

### P: Quantas conversas simultâneas um AI Agent pode atender?

**R:** Depende do `max_conversations`:
- **0** (padrão) = **Ilimitado**
- **10** = Máximo 10 conversas simultâneas
- **50** = Máximo 50 conversas simultâneas

Configure conforme capacidade/custo:

```php
AIAgent::update($agentId, [
    'max_conversations' => 0  // Ilimitado
]);
```

---

### P: Como a IA sabe quando escalonar para humano?

**R:** Existem 3 formas:

1. **Keywords configuradas** no agente:
   ```php
   'settings' => [
       'escalate_keywords' => ['falar com humano', 'atendente', 'pessoa']
   ]
   ```

2. **AI Branching** - Sistema detecta "intents" configurados na automação

3. **IA decide sozinha** - Se o prompt instruir: "Se não conseguir resolver, diga que vai transferir"

---

### P: Qual modelo OpenAI devo usar? GPT-4 ou GPT-3.5-turbo?

**R:**

| Modelo | Custo | Qualidade | Velocidade | Quando Usar |
|--------|-------|-----------|------------|-------------|
| **GPT-4** | Alto (~20x mais caro) | Excelente | Lento (~3-5s) | Vendas, casos complexos, raciocínio |
| **GPT-3.5-turbo** | Baixo | Boa | Rápido (~1-2s) | Suporte, FAQs, volume alto |

**Recomendação:**
- Use **GPT-3.5-turbo** para maioria dos casos
- Reserve **GPT-4** para agentes específicos (ex: closer de vendas)

---

### P: O que é Temperature e qual valor usar?

**R:** Temperature controla a "criatividade" da IA:

| Valor | Comportamento | Quando Usar |
|-------|---------------|-------------|
| **0.0 - 0.3** | Determinístico, previsível | Suporte técnico, informações precisas |
| **0.4 - 0.7** | Balanceado | Uso geral, conversas naturais |
| **0.8 - 1.0** | Criativo, variado | Marketing, conteúdo criativo |
| **1.1 - 2.0** | Muito criativo, arriscado | Raramente usado |

**Recomendação:** Use **0.7** como padrão.

---

### P: Posso ter múltiplos AI Agents ativos na mesma conversa?

**R:** **Não**. Apenas 1 AI Agent por vez. Para trocar:

```php
// Remover IA atual
ConversationAIService::removeAIAgent(474);

// Adicionar nova IA
ConversationAIService::addAIAgent(474, [
    'ai_agent_id' => 22
]);
```

---

## 🛠️ TOOLS E FUNCTION CALLING

### P: O que são Tools?

**R:** Tools são "ferramentas" que a IA pode usar para executar ações:
- **Buscar pedido** no WooCommerce
- **Consultar banco de dados**
- **Chamar API externa**
- **Executar workflow N8N**
- **Criar conversa, atribuir agente** (system)

---

### P: Como a IA decide quando usar uma Tool?

**R:** A OpenAI analisa:
1. **Descrição da tool** no `function_schema`
2. **Prompt do agente** (instruções)
3. **Contexto da conversa**
4. **Mensagem do cliente**

Se a IA determinar que precisa de informação/ação que a tool fornece, ela faz o "tool call".

---

### P: Quantas tools posso atribuir a um agente?

**R:** **Ilimitado**, mas recomendações:
- **3-5 tools** para agentes simples
- **8-10 tools** para agentes complexos
- **Evite >15 tools** (aumenta tokens e confusão)

---

### P: O que é `use_raw_response` em tools N8N?

**R:** Quando `use_raw_response = true`, o N8N retorna uma **resposta pronta** que é enviada diretamente ao cliente **sem reenviar para OpenAI**.

**Vantagens:**
- ✅ Economiza tokens (não reenvia para OpenAI)
- ✅ Mais rápido
- ✅ Resposta consistente

**Quando usar:**
- Workflows que já geram resposta completa formatada
- Integrações com outros sistemas que têm suas próprias lógicas

---

### P: Posso criar Tools personalizadas?

**R:** **Sim!** Crie via código:

```php
AIToolService::create([
    'name' => 'Minha Tool',
    'slug' => 'minha_tool',
    'tool_type' => 'api',  // ou 'custom'
    'function_schema' => [
        'name' => 'minha_tool',
        'description' => 'Descrição clara do que faz',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'param1' => [
                    'type' => 'string',
                    'description' => 'Descrição do parâmetro'
                ]
            ],
            'required' => ['param1']
        ]
    ],
    'config' => [
        'url' => 'https://api.exemplo.com/endpoint',
        'method' => 'POST'
    ]
]);
```

---

## 💰 CUSTOS E RATE LIMITING

### P: Quanto custa usar GPT-4 vs GPT-3.5-turbo?

**R:** Preços aproximados (2025):

**GPT-4:**
- Prompt: $0.03 / 1K tokens
- Completion: $0.06 / 1K tokens
- **Média por conversa:** $0.005 - $0.02

**GPT-3.5-turbo:**
- Prompt: $0.0015 / 1K tokens
- Completion: $0.002 / 1K tokens
- **Média por conversa:** $0.0002 - $0.001

**GPT-4 é ~20x mais caro que GPT-3.5-turbo**

---

### P: Como calcular o custo de uma conversa?

**R:** Fórmula:

```
Custo = (prompt_tokens / 1000) × preço_prompt +
        (completion_tokens / 1000) × preço_completion
```

**Exemplo GPT-4:**
- 1.126 tokens prompt × $0.03 = $0.03378
- 48 tokens completion × $0.06 = $0.00288
- **Total = $0.03666** (~R$ 0,18)

---

### P: Como configurar limites de custo?

**R:**

```php
Setting::set('ai_rate_limiting', json_encode([
    'max_requests_per_minute' => 60,
    'max_cost_per_hour' => 10.00,    // USD
    'max_cost_per_day' => 100.00,    // USD
    'max_cost_per_month' => 2000.00  // USD
]));
```

Sistema bloqueia automaticamente se atingir limites.

---

### P: Como reduzir custos com IA?

**R:**

1. **Use GPT-3.5-turbo** ao invés de GPT-4 quando possível
2. **Limite histórico de mensagens** (ex: últimas 10 ao invés de 20)
3. **Use tools N8N com `use_raw_response`** (evita reenvio)
4. **Reduza `max_tokens`** (ex: 1000 ao invés de 2000)
5. **Configure `temperature` menor** (respostas mais curtas)
6. **Use prompts concisos** (menos tokens prompt)

---

### P: Onde vejo os custos por agente/período?

**R:**

```sql
SELECT 
    ai_agent_id,
    COUNT(*) as conversas,
    SUM(tokens_used) as total_tokens,
    SUM(cost) as total_cost
FROM ai_conversations
WHERE created_at >= '2025-12-01'
  AND created_at <= '2025-12-31'
GROUP BY ai_agent_id;
```

Ou via código:

```php
$status = ConversationAIService::getAIStatus(474);
echo "Custo: $" . $status['ai_conversation']['cost'];
```

---

## 📊 LOGS E MONITORAMENTO

### P: Onde ficam os logs do sistema?

**R:**

```
logs/application.log   - Geral
logs/conversas.log     - Conversas
logs/ai-agents.log     - AI Agents
logs/ai-tools.log      - Tools
logs/automation.log    - Automações
logs/quepasa.log       - WhatsApp (Quepasa)
```

Acesse via interface: **Sistema → Logs**

---

### P: Como ver o que a IA está "pensando"?

**R:** Ative `debug_mode` temporariamente:

```php
// Em OpenAIService.php, adicione antes do processamento:
\App\Helpers\Logger::debug("Prompt enviado: " . $systemPrompt);
\App\Helpers\Logger::debug("Histórico: " . json_encode($messages));
```

Ou veja em `ai_conversations.messages` (JSON completo).

---

### P: Como rastrear uma conversa específica pelos logs?

**R:** Use `grep`:

```bash
# Buscar por conversation_id
grep "conversationId=474" logs/ai-agents.log

# Buscar por external_id (WhatsApp)
grep "3EB0805D31E2BDC33AD79D" logs/quepasa.log
```

---

### P: O que são os logs `[TOOL EXECUTION]`?

**R:** Logs de execução de tools pela IA:

```
[TOOL EXECUTION] Iniciando execução de 1 tool calls
[TOOL EXECUTION] Tool Call: function=buscar_pedido, args={"order_id":123}
[TOOL EXECUTION] Tool executada com sucesso
```

Úteis para debugar se tools estão sendo chamadas/executadas corretamente.

---

## 🐛 TROUBLESHOOTING

### P: IA não responde. O que fazer?

**R:** Checklist:

1. **Verificar AIConversation ativa:**
   ```sql
   SELECT * FROM ai_conversations WHERE conversation_id = 474 AND status = 'active';
   ```

2. **Verificar AIAgent habilitado:**
   ```sql
   SELECT * FROM ai_agents WHERE id = 21 AND enabled = 1;
   ```

3. **Verificar API Key configurada:**
   ```sql
   SELECT value FROM settings WHERE key = 'openai_api_key';
   ```

4. **Ver logs:**
   ```bash
   tail -f logs/ai-agents.log
   ```

5. **Verificar rate limiting:**
   ```php
   $check = AICostControlService::canProcessMessage($agentId);
   var_dump($check);
   ```

---

### P: Tool não está sendo chamada. Por quê?

**R:** Possíveis causas:

1. **Tool não atribuída ao agente:**
   ```sql
   SELECT * FROM ai_agent_tools WHERE ai_agent_id = 21 AND ai_tool_id = 10;
   ```

2. **Tool desabilitada:**
   ```sql
   SELECT * FROM ai_tools WHERE id = 10 AND enabled = 1;
   ```

3. **Descrição da tool ruim** - IA não entende quando usar
4. **Prompt do agente não menciona** - Adicione instrução no prompt
5. **OpenAI decidiu não usar** - Resposta direta era suficiente

---

### P: Erro "Rate limit atingido". Como resolver?

**R:**

1. **Aguardar:** Limite é por tempo (ex: 60 req/minuto)
2. **Aumentar limite:**
   ```php
   Setting::set('ai_rate_limiting', json_encode([
       'max_requests_per_minute' => 120  // Dobrar
   ]));
   ```
3. **Distribuir entre múltiplos agentes** (se possível)
4. **Usar GPT-3.5-turbo** (menos demanda)

---

### P: Custo está muito alto. Como descobrir o motivo?

**R:**

1. **Ver conversas mais caras:**
   ```sql
   SELECT 
       conversation_id, 
       ai_agent_id, 
       tokens_used, 
       cost 
   FROM ai_conversations 
   WHERE cost > 0.01 
   ORDER BY cost DESC 
   LIMIT 10;
   ```

2. **Ver agente com maior custo:**
   ```sql
   SELECT 
       ai_agent_id, 
       SUM(cost) as total 
   FROM ai_conversations 
   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
   GROUP BY ai_agent_id 
   ORDER BY total DESC;
   ```

3. **Analisar tokens:**
   - Muitos tokens prompt → Histórico grande, prompt longo
   - Muitos tokens completion → Respostas muito longas

---

### P: Como testar uma Tool sem envolver a IA?

**R:** Execute diretamente:

```php
use App\Services\AIToolExecutor;

$tool = \App\Models\AITool::find(10);
$result = AIToolExecutor::execute(
    'buscar_pedido_woocommerce',
    ['order_id' => 123],
    ['conversation_id' => 474]
);

var_dump($result);
```

---

## ⚙️ CONFIGURAÇÕES

### P: Como habilitar/desabilitar distribuição automática?

**R:**

```php
ConversationSettingsService::updateSettings([
    'distribution' => [
        'enabled' => true  // ou false
    ]
]);
```

---

### P: Como forçar sempre usar IA (nunca humano)?

**R:**

```php
'distribution' => [
    'enabled' => true,
    'method' => 'round_robin',  // Qualquer método
    'assign_to_ai_agent' => true,
    'ai_agent_priority' => 'high',
    'human_agents_enabled' => false  // ✅ Desabilitar humanos
]
```

---

### P: Posso ter configurações diferentes por horário?

**R:** Atualmente não diretamente, mas pode usar **automações**:

1. Crie automação com gatilho `time_based`
2. Ação: Atribuir AI Agent
3. Configure horário (ex: 18h-08h = IA, 08h-18h = humano)

---

### P: Como configurar delay entre mensagens da IA?

**R:**

```php
AIAgent::update($agentId, [
    'settings' => [
        'human_delay' => 2  // 2 segundos antes de responder
    ]
]);
```

Simula "digitando..." para parecer mais humano.

---

## 🔌 INTEGRAÇÕES

### P: Como integrar com WhatsApp?

**R:** Sistema usa **Quepasa**. Configuração:

1. Cadastrar conta WhatsApp em **Contas WhatsApp**
2. Configurar webhook do Quepasa:
   ```
   https://seu-dominio.com/api/webhooks/quepasa/message
   ```
3. Testar enviando mensagem

---

### P: Posso usar com Instagram/Telegram?

**R:** Arquitetura suporta, mas apenas WhatsApp está implementado. Para adicionar outros canais:

1. Criar webhook handler
2. Criar provider service (ex: `InstagramService`)
3. Adaptar `ConversationService` para novo canal

---

### P: Como integrar com meu CRM?

**R:** Via **Tools**:

1. Crie tool tipo `api`
2. Configure endpoint do CRM
3. Atribua ao agente

IA poderá buscar/atualizar dados no CRM automaticamente.

---

### P: N8N é obrigatório?

**R:** **Não**. N8N é apenas um dos tipos de tools. Você pode usar:
- WooCommerce direto
- APIs personalizadas
- Database queries
- System functions

Mas N8N é útil para workflows complexos sem código.

---

## 🚀 PERFORMANCE

### P: Quanto tempo leva para IA responder?

**R:** Média:
- **GPT-3.5-turbo:** 1-2 segundos
- **GPT-4:** 3-5 segundos

Se usar tools:
- +1-2 segundos por tool

---

### P: Sistema suporta quantas conversas simultâneas?

**R:** Depende da infraestrutura:

- **Agentes Humanos:** Limitados por capacidade humana (5-10/agente)
- **Agentes de IA:** Praticamente ilimitado (centenas ou milhares)

Gargalo é OpenAI API rate limit (~60 req/min).

---

### P: Como otimizar performance?

**R:**

1. **Use cache** para respostas frequentes
2. **Minimize histórico** (10 mensagens ao invés de 20)
3. **Use GPT-3.5-turbo** (mais rápido)
4. **Tools N8N com `use_raw_response`** (não reenvia)
5. **Configure `max_tokens` menor** (respostas mais rápidas)

---

### P: WebSocket vs Polling. Qual usar?

**R:**

| Método | Vantagens | Desvantagens |
|--------|-----------|--------------|
| **WebSocket** | Tempo real, menos requisições | Requer servidor separado |
| **Polling** | Mais simples, funciona sempre | Mais requisições, delay |

**Recomendação:** Use **Polling** inicialmente, migre para **WebSocket** se precisar de tempo real absoluto.

---

## 🎓 BÔNUS: MELHORES PRÁTICAS

### P: Como escrever um bom prompt para AI Agent?

**R:**

```
✅ BOM PROMPT:
Você é um assistente de pós-venda da Loja X.

Sua função é:
- Responder dúvidas sobre pedidos
- Verificar status de entrega
- Resolver problemas de produtos
- Escalonar para humano se não conseguir resolver

Seja educado, prestativo e objetivo.
Use as tools disponíveis para buscar informações.

Cliente: {nome}
Pedido mais recente: {order_id}

❌ PROMPT RUIM:
Você é um assistente. Seja legal.
```

**Dicas:**
- Seja específico sobre o papel
- Liste responsabilidades claramente
- Instrua quando escalonar
- Inclua contexto relevante

---

### P: Quantos AI Agents criar?

**R:**

**Recomendação inicial:**
- **1-2 agentes** para começar (ex: SDR, CS)
- Teste e otimize
- Adicione mais conforme necessário

**Escalável:**
- **5-10 agentes** especializados (SDR, CS, Closer, Suporte Técnico, etc)
- Cada um com tools específicas
- Distribua por funil/departamento

---

### P: Como garantir que IA não vai "inventar" informações?

**R:**

1. **Prompt claro:**
   ```
   NUNCA invente informações.
   Se não souber, diga "Não tenho essa informação" e use a tool apropriada.
   ```

2. **Use temperature baixo** (0.3-0.5)

3. **Forneça tools** para buscar dados reais

4. **Instrua para escalonar** quando incerto

---

## 📞 AINDA TEM DÚVIDAS?

### Recursos Adicionais

📖 **Documentação Completa:** `SISTEMA_COMPLETO_CONVERSATIONS_AI_AGENTS.md`  
⚡ **Guia Rápido:** `RESUMO_RAPIDO_SISTEMA_AI.md`  
📊 **Diagramas:** `DIAGRAMAS_VISUAIS_SISTEMA_AI.md`  
🔍 **Análise de Logs:** `ANALISE_LOGS_SISTEMA.md`  
📑 **Índice:** `INDICE_DOCUMENTACAO_SISTEMA_AI.md`

### Contato

Para dúvidas não cobertas neste FAQ, consulte a documentação completa ou entre em contato com o time de desenvolvimento.

---

**Última atualização:** 31/12/2025  
**Versão:** 1.0  
**Total de perguntas:** 70+
