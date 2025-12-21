# 📋 RESUMO EXECUTIVO - AI AGENTS E AI TOOLS

## 🎯 O QUE É?

Sistema que permite criar **agentes virtuais de IA** que podem:
- Atender conversas automaticamente
- Executar ações no sistema através de **tools** (ferramentas)
- Integrar com serviços externos (WooCommerce, N8N, APIs)

---

## 🏗️ ARQUITETURA

### 4 Tabelas Principais:

1. **`ai_agents`** - Agentes virtuais (prompts, modelos, configurações)
2. **`ai_tools`** - Ferramentas disponíveis (WooCommerce, Database, etc)
3. **`ai_agent_tools`** - Relação agente ↔ tool (muitos-para-muitos)
4. **`ai_conversations`** - Logs e histórico (tokens, custo, tools usadas)

---

## 🔄 FLUXO PRINCIPAL

```
Mensagem do Contato
    ↓
ConversationService detecta agente de IA
    ↓
AIAgentService::processMessage()
    ↓
OpenAIService::processMessage()
    ↓
Monta contexto (prompt + histórico + tools)
    ↓
Chama OpenAI API
    ↓
Se IA chama tool → Executa tool → Reenvia para OpenAI
    ↓
Resposta final enviada ao contato
    ↓
Registra em ai_conversations (tokens, custo, tools)
```

---

## 🤖 AI AGENTS

### Criar Agente:

```php
AIAgentService::create([
    'name' => 'Suporte',
    'agent_type' => 'SUPPORT',
    'prompt' => 'Você é um agente de suporte...',
    'model' => 'gpt-4',
    'temperature' => 0.7,
    'max_tokens' => 2000
]);
```

### Tipos Disponíveis:
- `SDR` - Sales Development Representative
- `CS` - Customer Success
- `CLOSER` - Fechamento de vendas
- `FOLLOWUP` - Followup automático
- `SUPPORT` - Suporte técnico
- `ONBOARDING` - Onboarding
- `GENERAL` - Geral

### Processamento Automático:

- **Quando conversa é atribuída**: Processa última mensagem ou envia boas-vindas
- **Quando mensagem é recebida**: Processa automaticamente se agente está ativo

---

## 🛠️ AI TOOLS

### Criar Tool:

```php
AIToolService::create([
    'name' => 'Buscar Pedido',
    'slug' => 'buscar_pedido_woocommerce',
    'tool_type' => 'woocommerce',
    'function_schema' => [...],  // Schema OpenAI
    'config' => ['url' => '...', 'key' => '...']
]);
```

### Tipos de Tools:

1. **System** - Ações no sistema (tags, estágios, escalação)
2. **Followup** - Verificação de status e interações
3. **WooCommerce** - Integração com WooCommerce
4. **Database** - Consultas seguras ao banco
5. **N8N** - Execução de workflows
6. **API** - Chamadas genéricas a APIs
7. **Document** - Busca e extração de documentos

### Execução:

1. IA decide usar tool → Retorna `tool_calls`
2. Sistema executa tool → Valida permissões
3. Resultado retornado → Reenvia para OpenAI
4. IA gera resposta final → Com base nos resultados

---

## 📊 EXEMPLO PRÁTICO

**Mensagem**: "Quero saber o status do pedido #12345"

**Processo**:
1. IA recebe mensagem
2. IA chama tool `buscar_pedido_woocommerce(order_id: 12345)`
3. Sistema busca pedido no WooCommerce
4. Resultado retornado: `{status: "processing", total: "R$ 299,90"}`
5. IA gera resposta: "Seu pedido está em processamento. Total: R$ 299,90"
6. Resposta enviada ao contato
7. Log registrado (tokens, custo, tool usada)

---

## 🔑 PONTOS CHAVE

### Segurança:
- ✅ Validação de tools antes de executar
- ✅ Verificação de permissões (tabelas, colunas)
- ✅ Read-only para Database Tools
- ✅ Validação de argumentos conforme schema

### Performance:
- ✅ Limite de conversas por agente
- ✅ Histórico limitado (últimas 10 mensagens)
- ✅ Rate limiting e controle de custo
- ⏳ Processamento assíncrono (planejado)

### Custo:
- ✅ Cálculo automático por modelo
- ✅ Logs detalhados de tokens e custo
- ✅ Estatísticas por agente
- ✅ Controle de limites

### Escalação:
- ✅ Tool `escalar_para_humano` disponível
- ✅ Remoção automática de agente de IA
- ✅ Atribuição a agente humano

---

## 📁 ARQUIVOS PRINCIPAIS

### Models:
- `app/Models/AIAgent.php` - Model do agente
- `app/Models/AITool.php` - Model da tool
- `app/Models/AIConversation.php` - Model de conversa de IA

### Services:
- `app/Services/AIAgentService.php` - Lógica de agentes
- `app/Services/AIToolService.php` - Lógica de tools
- `app/Services/OpenAIService.php` - Integração OpenAI + execução de tools
- `app/Services/ConversationAIService.php` - Gerenciamento de IA em conversas

### Controllers:
- `app/Controllers/AIAgentController.php` - API de agentes
- `app/Controllers/AIToolController.php` - API de tools

---

## 📈 ESTATÍSTICAS

```php
// Obter estatísticas do agente
$stats = AIConversation::getAgentStats($agentId);

// Retorna:
// - total_conversations
// - total_tokens
// - total_cost
// - avg_tokens
// - completed_conversations
// - escalated_conversations
```

---

## 🎓 PRÓXIMOS PASSOS

1. ✅ Sistema base implementado (75%)
2. ⏳ Processamento assíncrono (fila de jobs)
3. ⏳ Mais tools (WooCommerce completo, etc)
4. ⏳ Interface de criação/edição melhorada
5. ⏳ Analytics e dashboards

---

**Para documentação completa, ver**: `DOCUMENTACAO_AI_AGENTS_E_TOOLS.md`

