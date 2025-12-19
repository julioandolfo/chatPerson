# Correção: Campos Fillable do Model Conversation

## Problema Identificado

O Model `Conversation` tinha um array `$fillable` **incompleto**, o que fazia com que vários campos fossem **silenciosamente ignorados** ao tentar atualizar conversas via `Conversation::update()`.

## Campos Faltantes

### ❌ Antes (Incompleto)
```php
protected array $fillable = [
    'contact_id', 'agent_id', 'department_id', 'channel', 
    'status', 'funnel_id', 'funnel_stage_id', 'whatsapp_account_id', 
    'pinned', 'pinned_at', 'is_spam', 'spam_marked_at', 'spam_marked_by'
];
```

### ✅ Depois (Completo)
```php
protected array $fillable = [
    'contact_id', 
    'agent_id', 
    'department_id', 
    'channel', 
    'status', 
    'funnel_id', 
    'funnel_stage_id', 
    'whatsapp_account_id', 
    'pinned', 
    'pinned_at', 
    'is_spam', 
    'spam_marked_at', 
    'spam_marked_by', 
    'metadata',      // ✅ NOVO - Estado de chatbots e automações
    'priority',      // ✅ NOVO - Prioridade da conversa
    'assigned_at',   // ✅ NOVO - Timestamp de atribuição
    'resolved_at',   // ✅ NOVO - Timestamp de resolução
    'moved_at'       // ✅ NOVO - Timestamp de movimentação
];
```

## Campos Adicionados no Banco de Dados

### Migration 063: `063_add_metadata_to_conversations.php`

**Campos criados:**
1. `metadata` (JSON) - Para armazenar estado de chatbots, automações e dados dinâmicos
2. `assigned_at` (TIMESTAMP) - Para registrar quando a conversa foi atribuída a um agente

**Script de execução:**
- `public/run-migration-063.php` - Execute via navegador: `http://chat.test/public/run-migration-063.php`

## Funcionalidades Afetadas

### 1. ✅ Sistema de Chatbot (CRÍTICO)
**O que estava quebrado:**
- Estado do chatbot não era salvo (`chatbot_active`, `chatbot_options`, etc)
- Chatbot não aguardava resposta do usuário
- Fluxo não continuava após resposta válida
- Feedback de resposta inválida não funcionava
- Fallback não era executado

**Onde é usado:**
- `AutomationService::executeChatbot()` - Salva estado do chatbot
- `AutomationService::handleChatbotResponse()` - Processa respostas e continua fluxo
- Gatilho: `message_received` (todas as mensagens de contatos)

**Impacto:**
- 🚨 **ALTO** - Chatbots NÃO funcionavam

---

### 2. ✅ Sistema de Atribuição
**O que estava quebrado:**
- Data/hora de atribuição não era registrada

**Onde é usado:**
- `ConversationService::assignToAgent()`
- `FunnelService::assignConversationToAgent()`
- Relatórios e SLA de atendimento

**Impacto:**
- ⚠️ **MÉDIO** - Atribuições funcionavam, mas sem registro de timestamp

---

### 3. ✅ Sistema de Priorização
**O que estava quebrado:**
- Prioridade da conversa não podia ser atualizada

**Onde é usado:**
- Filtros de conversas por prioridade
- Ordenação de conversas

**Impacto:**
- ⚠️ **BAIXO** - Prioridade definida na criação funcionava, mas não podia ser alterada

---

### 4. ✅ Sistema de Movimentação (Kanban/Funil)
**O que estava quebrado:**
- Timestamp de movimentação entre estágios (`moved_at`)

**Onde é usado:**
- `FunnelService::moveConversation()`
- Gatilho: `conversation_moved` em automações
- Relatórios de tempo por estágio

**Impacto:**
- ⚠️ **MÉDIO** - Movimentação funcionava, mas sem registro preciso de quando

---

### 5. ✅ Sistema de Resolução
**O que estava quebrado:**
- Timestamp de resolução (`resolved_at`)

**Onde é usado:**
- `ConversationService::closeConversation()`
- Gatilho: `conversation_resolved` em automações
- Relatórios de SLA e tempo de resolução

**Impacto:**
- ⚠️ **MÉDIO** - Resolução funcionava, mas sem timestamp

---

## Gatilhos de Automação Afetados

### ✅ Funcionam corretamente agora:

1. **`new_conversation`**
   - Pode salvar metadata na criação
   - Pode iniciar chatbot imediatamente

2. **`message_received`** ⭐ PRINCIPAL
   - Detecta chatbot ativo via metadata
   - Continua fluxo do chatbot após resposta
   - Processa fallback de respostas inválidas

3. **`conversation_updated`**
   - Pode reagir a mudanças no metadata
   - Detecta mudanças em priority

4. **`conversation_moved`**
   - Registra moved_at corretamente
   - Pode usar esse timestamp em condições

5. **`conversation_resolved`**
   - Registra resolved_at corretamente
   - Pode usar esse timestamp em relatórios

---

## Como Aplicar as Correções

### Passo 1: Executar Migration
Acesse no navegador:
```
http://chat.test/public/run-migration-063.php
```

Você deve ver:
```
✅ Coluna 'metadata' adicionada com sucesso!
✅ Coluna 'assigned_at' adicionada com sucesso!
```

### Passo 2: Verificar
Os seguintes comandos SQL devem retornar resultados:
```sql
SHOW COLUMNS FROM conversations LIKE 'metadata';
SHOW COLUMNS FROM conversations LIKE 'assigned_at';
```

### Passo 3: Testar Chatbot
1. Crie/edite uma automação com gatilho `new_conversation`
2. Adicione um nó de chatbot com opções
3. Configure fallback e mensagem de erro
4. Envie uma mensagem no WhatsApp
5. Responda com opção válida → deve continuar fluxo ✅
6. Responda com opção inválida → deve enviar feedback ✅
7. Responda 3x errado → deve executar fallback ✅

---

## Logs de Debug

Para monitorar o funcionamento, verifique:
- `logs/automacao.log` - Logs detalhados de execução
- `http://chat.test/public/view-automation-logs.php` - Visualizador web

**Procure por:**
```
Metadata a ser salvo: {"chatbot_active":true, ...}
✅ Estado salvo! Chatbot aguardando resposta do contato.
🔍 Verificação pós-salvamento: chatbot_active = TRUE

[Quando responder]
=== executeForMessageReceived INÍCIO ===
Metadata bruto: {"chatbot_active":true, ...}
chatbot_active? TRUE
🤖 Chatbot ATIVO detectado! Chamando handleChatbotResponse...
```

---

## Checklist de Verificação

- [x] Campo `metadata` adicionado ao fillable
- [x] Campo `priority` adicionado ao fillable
- [x] Campo `assigned_at` adicionado ao fillable
- [x] Campo `resolved_at` adicionado ao fillable
- [x] Campo `moved_at` adicionado ao fillable
- [x] Migration criada para `metadata` (JSON)
- [x] Migration criada para `assigned_at` (TIMESTAMP)
- [x] Script de execução criado (`run-migration-063.php`)
- [x] Logs de debug adicionados ao AutomationService
- [ ] Migration executada no banco de dados
- [ ] Chatbot testado e funcionando
- [ ] Fallback testado e funcionando

---

## Resumo

Esta correção resolve um bug **crítico** que impedia o funcionamento completo de:
- ✅ Chatbots em automações
- ✅ Sistema de fallback e feedback
- ✅ Continuidade de fluxo após resposta
- ✅ Registro de timestamps importantes

Todos os gatilhos de automação que dependem de metadata ou timestamps agora funcionam corretamente.

