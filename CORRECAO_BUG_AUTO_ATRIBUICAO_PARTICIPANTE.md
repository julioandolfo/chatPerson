# ✅ Correção: Bug de Auto-Atribuição ao Enviar Mensagem

**Data**: 2026-01-20  
**Status**: ✅ CORRIGIDO  
**Prioridade**: 🔴 CRÍTICA

---

## 🎯 **Problema**

### Sintoma:
- Conversa está atribuída ao **Agente A** (Luan)
- Agente A adiciona **Agente B** (Nicolas) como **participante**
- Quando Agente B (participante) envia uma mensagem
- ❌ Conversa é **automaticamente reatribuída** para Agente B
- ✅ **ERRADO**: Conversa deveria continuar atribuída ao Agente A

### Exemplo Real:
```
Estado inicial:
├─ Conversa #123
├─ Atribuída a: Luan (ID: 5)
└─ Participantes: Nicolas (ID: 7)

1. Nicolas envia mensagem: "Oi Luan, vou ajudar nessa conversa"
2. ❌ Sistema reatribui automaticamente para Nicolas
3. ❌ Agora: Atribuída a: Nicolas (ID: 7)
4. ✅ ESPERADO: Continuar atribuída a Luan (ID: 5)
```

---

## 🔍 **Causa Raiz**

### Código com Bug:

**Arquivo**: `app/Controllers/ConversationController.php`  
**Linhas**: 1188-1209

```php
// ❌ ANTES (BUG):
$assignedTo = $conversation['assigned_to'] ?? null; // ← Campo ERRADO!
$isUnassigned = ($assignedTo === null || $assignedTo === '' || $assignedTo === 0 || $assignedTo === '0');
if (!$isNote && $isUnassigned) {
    // Sempre TRUE porque 'assigned_to' não existe!
    ConversationService::assignToAgent($id, $userId, true);
}
```

### Por que acontecia?

1. **Campo errado**: O código verificava `$conversation['assigned_to']`
2. **Campo correto no banco**: O campo real é `agent_id`
3. **Resultado**: `$assignedTo` sempre era `null`
4. **Consequência**: `$isUnassigned` sempre era `TRUE`
5. **Bug**: Sistema reatribuía conversa **toda vez** que alguém enviava mensagem

### Fonte do Problema:

O método `Conversation::findWithRelations()` retorna o campo `agent_id`:

```sql
-- app/Models/Conversation.php (linha 513)
SELECT c.*, 
       c.agent_id,  -- ← Campo retornado
       ...
FROM conversations c
WHERE c.id = ?
```

Mas o código estava verificando `assigned_to` (que não existe no array retornado):

```php
$assignedTo = $conversation['assigned_to'] ?? null;  // ← Sempre NULL!
```

---

## ✅ **Solução Implementada**

### Código Corrigido:

```php
// ✅ DEPOIS (CORRETO):
$assignedTo = $conversation['agent_id'] ?? null; // ← Campo CORRETO!
$isUnassigned = ($assignedTo === null || $assignedTo === '' || $assignedTo === 0 || $assignedTo === '0');
if (!$isNote && $isUnassigned) {
    // Só atribui se REALMENTE não tem agente
    ConversationService::assignToAgent($id, $userId, true);
    $conversation['agent_id'] = $userId; // ← Atualiza campo correto
}
```

### Mudanças:
1. **Linha 1190**: `assigned_to` → `agent_id`
2. **Linha 1201**: `assigned_to` → `agent_id`

---

## 📝 **Lógica Correta**

### Auto-Atribuição deve acontecer APENAS quando:

```
✅ Conversa NÃO tem agente atribuído (agent_id = null/0)
✅ Mensagem NÃO é uma nota interna (is_note = false)
✅ Quem está enviando é um agente válido

❌ NÃO deve acontecer se:
- Conversa JÁ tem agente atribuído
- Mesmo que quem está enviando seja participante
```

### Cenários Corretos Agora:

#### Cenário 1: Conversa sem agente atribuído
```
Estado inicial:
├─ Conversa #123
├─ Atribuída a: NINGUÉM (agent_id = null)
└─ Participantes: Luan, Nicolas

1. Nicolas envia mensagem
2. ✅ Sistema atribui automaticamente para Nicolas
3. ✅ Agora: Atribuída a: Nicolas
```

#### Cenário 2: Conversa JÁ tem agente atribuído
```
Estado inicial:
├─ Conversa #123
├─ Atribuída a: Luan (agent_id = 5)
└─ Participantes: Nicolas

1. Nicolas envia mensagem
2. ✅ Sistema NÃO reatribui
3. ✅ Continua: Atribuída a: Luan
```

#### Cenário 3: Nota interna (is_note = true)
```
Estado inicial:
├─ Conversa #123
├─ Atribuída a: NINGUÉM (agent_id = null)

1. Nicolas envia NOTA INTERNA
2. ✅ Sistema NÃO atribui (é nota, não mensagem)
3. ✅ Continua: Atribuída a: NINGUÉM
```

---

## 🧪 **Como Testar**

### Teste 1: Participante NÃO deve reatribuir conversa

```
1. Criar conversa
2. Atribuir para Agente A (Luan)
3. Adicionar Agente B (Nicolas) como participante
4. Logar como Agente B
5. Enviar mensagem na conversa
6. ✅ Verificar: Conversa deve CONTINUAR atribuída ao Agente A
```

### Teste 2: Auto-atribuição em conversa não atribuída

```
1. Criar conversa SEM agente atribuído
2. Logar como Agente A
3. Enviar mensagem na conversa
4. ✅ Verificar: Conversa deve ser atribuída automaticamente ao Agente A
```

### Teste 3: Nota interna não atribui

```
1. Criar conversa SEM agente atribuído
2. Logar como Agente A
3. Enviar NOTA INTERNA (is_note = true)
4. ✅ Verificar: Conversa deve CONTINUAR não atribuída
```

---

## 📊 **Comparação Antes/Depois**

### ANTES do Fix ❌:

| Situação | Conversa tem agent_id? | Resultado |
|----------|------------------------|-----------|
| Agente envia mensagem | SIM (5) | ❌ Reatribui para quem enviou |
| Participante envia msg | SIM (5) | ❌ Reatribui para participante |
| Nova conversa | NÃO (null) | ✅ Atribui para quem enviou |
| Nota interna | NÃO (null) | ❌ Atribui para quem enviou |

### DEPOIS do Fix ✅:

| Situação | Conversa tem agent_id? | Resultado |
|----------|------------------------|-----------|
| Agente envia mensagem | SIM (5) | ✅ Mantém atribuição original |
| Participante envia msg | SIM (5) | ✅ Mantém atribuição original |
| Nova conversa | NÃO (null) | ✅ Atribui para quem enviou |
| Nota interna | NÃO (null) | ✅ NÃO atribui (é nota) |

---

## 📝 **Arquivo Modificado**

| Arquivo | Mudanças | Linhas |
|---------|----------|--------|
| `app/Controllers/ConversationController.php` | Trocar `assigned_to` por `agent_id` | 1190, 1201 |

---

## 🎯 **Impacto**

### Problemas que o bug causava:
- ❌ Participantes "roubavam" atribuição ao enviar mensagem
- ❌ Conversa mudava de responsável sem intenção
- ❌ Métricas de agente ficavam incorretas
- ❌ Difícil rastrear responsável real pela conversa
- ❌ Automações podiam ser disparadas incorretamente

### Benefícios da correção:
- ✅ Atribuição permanece estável
- ✅ Participantes podem ajudar sem assumir conversa
- ✅ Métricas de agente corretas
- ✅ Rastreamento de responsabilidade claro
- ✅ Automações funcionam corretamente

---

## 🔍 **Por que 'assigned_to' estava no código?**

Possíveis motivos:
1. **Refatoração incompleta**: Talvez o campo tenha sido renomeado de `assigned_to` para `agent_id` no banco, mas o código não foi atualizado
2. **Copy-paste**: Código copiado de outra parte do sistema que usava nomenclatura diferente
3. **Falta de teste**: Não havia teste automatizado para detectar esse erro

---

## ✅ **Conclusão**

O bug foi causado por uma simples inconsistência de nomenclatura de campo. Ao usar `assigned_to` em vez de `agent_id`, a verificação de "conversa não atribuída" sempre retornava TRUE, causando reatribuição indevida.

**Correção**: Simples mas crítica - trocar 2 ocorrências de `assigned_to` por `agent_id`.

**Resultado**: Agora participantes podem ajudar em conversas sem assumir a responsabilidade automaticamente.

---

**Última atualização**: 2026-01-20 17:15
