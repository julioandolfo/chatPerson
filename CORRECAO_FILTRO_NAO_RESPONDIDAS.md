# ✅ Correção: Filtro "Não Respondidas" Considera sender_id

**Data**: 2026-01-19  
**Status**: ✅ CORRIGIDO  
**Prioridade**: 🟡 MÉDIA

---

## 🎯 **Problema**

### Sintoma:
- Conversa tem última mensagem de `sender_type = 'agent'`
- MAS foi enviada pelo **sistema** (`sender_id = 0` ou `null`)
- ❌ Aparecia como **"RESPONDIDA"**
- ✅ Deveria aparecer como **"NÃO RESPONDIDA"** (pois não foi um agente humano real)

### Exemplo Real:
```
Conversa #123:
├─ Mensagem 1: Contato: "Olá, preciso de ajuda"
├─ Mensagem 2: Sistema: "Aguarde, estamos conectando..."
│                sender_type = 'agent'
│                sender_id = 0  ← Sistema, não agente real
│                ai_agent_id = null
└─ Status: ❌ Aparecia como "Respondida"
           ✅ Deveria ser "Não Respondida"
```

---

## 🔍 **Causa Raiz**

### Código Anterior:
```sql
-- ❌ ANTES: Só verificava sender_type e ai_agent_id
SELECT COALESCE(MAX(m3.created_at), '1970-01-01')
FROM messages m3
WHERE m3.conversation_id = c.id
  AND m3.sender_type = 'agent'
  AND m3.ai_agent_id IS NULL  -- apenas agente humano
  -- ❌ FALTAVA: AND m3.sender_id > 0
```

**Problema**: Mensagens do sistema têm:
- ✅ `sender_type = 'agent'` (passa)
- ✅ `ai_agent_id IS NULL` (passa)
- ❌ `sender_id = 0` (deveria reprovar!)

---

## ✅ **Solução Implementada**

### Código Corrigido:

#### 1. Filtro "NÃO RESPONDIDAS" (linha 273-295):
```sql
-- ✅ DEPOIS: Adiciona verificação de sender_id
SELECT COALESCE(MAX(m3.created_at), '1970-01-01')
FROM messages m3
WHERE m3.conversation_id = c.id
  AND m3.sender_type = 'agent'
  AND m3.ai_agent_id IS NULL -- apenas agente humano
  AND m3.sender_id > 0 -- ✅ NOVO: Excluir mensagens do sistema
```

#### 2. Filtro "RESPONDIDAS" (linha 297-317):
```sql
-- ✅ DEPOIS: Também ajustado para consistência
SELECT 1
FROM messages m_agent
WHERE m_agent.conversation_id = c.id
  AND m_agent.sender_type = 'agent'
  AND m_agent.ai_agent_id IS NULL
  AND m_agent.sender_id > 0 -- ✅ NOVO: Excluir mensagens do sistema
  AND m_agent.created_at = (
    SELECT MAX(m2.created_at)
    FROM messages m2
    WHERE m2.conversation_id = c.id
      AND (
        (m2.sender_type = 'agent' AND m2.ai_agent_id IS NULL AND m2.sender_id > 0) -- ✅ agente real
        OR m2.sender_type = 'contact'
      )
  )
```

---

## 📝 **Lógica Completa**

### O que é considerado "Resposta de Agente Humano"?
```
✅ sender_type = 'agent'
✅ ai_agent_id IS NULL (não é IA)
✅ sender_id > 0 (não é sistema)
```

### O que NÃO é considerado "Resposta de Agente Humano"?
```
❌ sender_type = 'contact' (mensagem do contato)
❌ ai_agent_id IS NOT NULL (resposta de IA)
❌ sender_id = 0 ou NULL (resposta do sistema)
```

---

## 🧪 **Cenários de Teste**

### Cenário 1: Conversa com mensagem do sistema
```sql
-- Mensagens da conversa:
1. Contato: "Olá" (10:00)
2. Sistema: "Aguarde..." (10:01) - sender_id=0

-- Resultado esperado:
✅ Filtro "Não Respondidas": Deve INCLUIR
❌ Filtro "Respondidas": Não deve INCLUIR
```

### Cenário 2: Conversa com resposta de agente real
```sql
-- Mensagens da conversa:
1. Contato: "Olá" (10:00)
2. Agente João: "Olá, como posso ajudar?" (10:02) - sender_id=5

-- Resultado esperado:
❌ Filtro "Não Respondidas": Não deve INCLUIR
✅ Filtro "Respondidas": Deve INCLUIR
```

### Cenário 3: Conversa com sistema + contato
```sql
-- Mensagens da conversa:
1. Contato: "Olá" (10:00)
2. Sistema: "Aguarde..." (10:01) - sender_id=0
3. Contato: "Ainda aí?" (10:10)

-- Resultado esperado:
✅ Filtro "Não Respondidas": Deve INCLUIR (última do contato é mais recente que agente real)
❌ Filtro "Respondidas": Não deve INCLUIR
```

### Cenário 4: Conversa com sistema + agente + contato
```sql
-- Mensagens da conversa:
1. Contato: "Olá" (10:00)
2. Sistema: "Aguarde..." (10:01) - sender_id=0
3. Agente João: "Como posso ajudar?" (10:02) - sender_id=5
4. Contato: "Preciso de ajuda" (10:05)

-- Resultado esperado:
✅ Filtro "Não Respondidas": Deve INCLUIR (mensagem do contato 10:05 > agente real 10:02)
❌ Filtro "Respondidas": Não deve INCLUIR
```

---

## 📊 **Tipos de Mensagens**

| sender_type | ai_agent_id | sender_id | Descrição | Conta como Resposta? |
|-------------|-------------|-----------|-----------|---------------------|
| `contact` | - | - | Mensagem do contato | ❌ Não |
| `agent` | NOT NULL | - | Resposta de IA | ❌ Não |
| `agent` | NULL | 0 ou NULL | **Sistema** | ❌ **Não** (NOVO) |
| `agent` | NULL | > 0 | **Agente Real** | ✅ **Sim** |

---

## 📝 **Arquivo Modificado**

| Arquivo | Mudanças | Linhas |
|---------|----------|--------|
| `app/Models/Conversation.php` | Adicionar verificação `sender_id > 0` nos filtros | 273-317 |

---

## 🔍 **Exemplos de Mensagens do Sistema**

Mensagens que têm `sender_id = 0` ou `null`:
- ✉️ "Aguarde, estamos conectando você a um agente..."
- ✉️ "Sua conversa foi atribuída a [Agente]"
- ✉️ "Horário de atendimento: Segunda a Sexta, 9h-18h"
- ✉️ "Conversa transferida para o setor [Nome]"
- ✉️ Mensagens automáticas de boas-vindas
- ✉️ Notificações de status

---

## ✅ **Resultado**

### Antes da correção:
```
❌ Conversa com última msg do sistema → Aparecia como "Respondida"
❌ Filtro "Não Respondidas" não incluía essas conversas
😡 Agentes perdiam conversas que precisavam de resposta
```

### Depois da correção:
```
✅ Conversa com última msg do sistema → Aparece como "Não Respondida"
✅ Filtro "Não Respondidas" inclui essas conversas
✅ Agentes conseguem identificar conversas que precisam de resposta real
```

---

## 🎯 **Impacto**

| Aspecto | Antes | Depois |
|---------|-------|--------|
| **Mensagens do sistema** | ❌ Contavam como resposta | ✅ Não contam |
| **Filtro "Não Respondidas"** | ❌ Incompleto | ✅ Preciso |
| **Filtro "Respondidas"** | ❌ Incluía sistema | ✅ Só agentes reais |
| **Identificação de conversas** | ❌ Imprecisa | ✅ Correta |

---

## ✅ **Conclusão**

Filtros de "Respondidas" e "Não Respondidas" agora distinguem corretamente entre:
- ✅ **Agente Real** (`sender_id > 0`) → Conta como resposta
- ❌ **Sistema** (`sender_id = 0` ou `null`) → NÃO conta como resposta
- ❌ **IA** (`ai_agent_id IS NOT NULL`) → NÃO conta como resposta

Isso garante que conversas que só receberam mensagens automáticas do sistema apareçam corretamente como "Não Respondidas".

---

**Última atualização**: 2026-01-19 16:45
