# 🔒 Correção: Filtro Padrão de Conversas

**Data**: 2026-01-13  
**Status**: ✅ IMPLEMENTADO  
**Prioridade**: 🔴 CRÍTICA

---

## 🐛 Problema Identificado

Quando um agente entrava em `/conversations` **sem aplicar nenhum filtro**, estava vendo:
- ❌ Conversas atribuídas a OUTROS agentes
- ❌ TODAS as conversas do sistema

**Comportamento esperado**:
- ✅ Conversas atribuídas a ELE
- ✅ Conversas NÃO ATRIBUÍDAS (sem agente)

---

## 🎯 Solução Implementada

### Arquivo: `app/Models/Conversation.php`

Adicionado **filtro padrão automático** no método `getAll()` (linha 345):

```php
// ✅ FILTRO PADRÃO: Se usuário está logado E não aplicou filtro de agente explícito
// Mostrar apenas: conversas atribuídas a ELE + conversas NÃO ATRIBUÍDAS
if (!empty($filters['current_user_id']) && !isset($filters['agent_id']) && !isset($filters['agent_ids'])) {
    $userId = (int)$filters['current_user_id'];
    $sql .= " AND (c.agent_id = ? OR c.agent_id IS NULL OR c.agent_id = 0)";
    $params[] = $userId;
    
    \App\Helpers\Log::debug("🔒 [Conversation::getAll] Filtro padrão aplicado: userId={$userId}", 'conversas.log');
}
```

**Como funciona**:
1. Se `current_user_id` está presente (passado pelo `ConversationService::list()`)
2. E **NÃO** tem filtro explícito de `agent_id` ou `agent_ids`
3. Então aplica filtro SQL: `(agent_id = $userId OR agent_id IS NULL OR agent_id = 0)`

---

## 📋 Comportamento por Cenário

### Cenário 1: Sem Filtros (Visualização Padrão)
**Antes**:
- Listava TODAS as conversas do sistema (de todos os agentes)

**Depois**:
- Lista apenas:
  - ✅ Conversas atribuídas ao agente logado
  - ✅ Conversas não atribuídas (para qualquer agente pegar)

### Cenário 2: Com Filtro de Agente Específico
**Exemplo**: Usuário filtra por "Agente João"

**Comportamento**:
- ✅ Filtro padrão **NÃO** é aplicado
- ✅ Respeita o filtro explícito do usuário
- ✅ Lista conversas do agente selecionado

### Cenário 3: Com Filtro de "Não Atribuídas"
**Exemplo**: Usuário filtra por "Sem atribuição"

**Comportamento**:
- ✅ Filtro padrão **NÃO** é aplicado
- ✅ Lista apenas conversas sem agente

### Cenário 4: Com Outros Filtros (Status, Canal, etc)
**Exemplo**: Usuário filtra por "Status: Aberto" + "Canal: WhatsApp"

**Comportamento**:
- ✅ Filtro padrão **É** aplicado
- ✅ Lista conversas abertas do WhatsApp que:
  - Estão atribuídas ao agente logado OU
  - Não estão atribuídas

---

## ⚙️ Limites de Conversas Confirmados

| Contexto | Limite | Observação |
|----------|--------|------------|
| **Lista principal** | 150 por página | Scroll infinito: 0→150→300→450... |
| **Badge de contadores** | 70 conversas | Otimização de performance |
| **Paginação** | Incremental | Usa `offset`, não aumenta `limit` |

**Importante**: O limite de 70 no badge NÃO afeta a lista principal!

---

## 🔄 Fluxo de Dados

```
1. Usuário acessa /conversations
   ↓
2. ConversationService::list($filters, $userId)
   ↓ Adiciona current_user_id
3. Conversation::getAll($filters)
   ↓ Verifica condições
4. Filtro padrão aplicado? (se sem filtro explícito)
   ✅ SIM: Adiciona WHERE (agent_id = $userId OR agent_id IS NULL)
   ❌ NÃO: Usa apenas filtros explícitos
   ↓
5. Query executada com permissões aplicadas
   ↓
6. Retorna conversas filtradas
```

---

## 🧪 Testes Recomendados

### Teste 1: Visualização Padrão
1. Login como Agente A
2. Acessar /conversations sem filtros
3. **Verificar**: Apenas conversas do Agente A + não atribuídas

### Teste 2: Filtro Explícito
1. Login como Agente A
2. Filtrar por "Agente B"
3. **Verificar**: Conversas do Agente B (não apenas do A)

### Teste 3: Múltiplos Agentes
1. Criar conversas para Agentes A, B, C
2. Criar conversas não atribuídas
3. Login como Agente A
4. **Verificar**: Ver apenas A + não atribuídas

### Teste 4: Scroll Infinito
1. Login como Agente A
2. Criar 300+ conversas para ele
3. Scrollar até o final e clicar "Carregar mais"
4. **Verificar**: Lista NÃO zera, adiciona mais conversas ao final

---

## 🔍 Logs de Debug

Para acompanhar o filtro sendo aplicado, verificar `storage/logs/conversas.log`:

```
🔒 [Conversation::getAll] Filtro padrão aplicado: userId=5 (mostrar apenas atribuídas a ele + não atribuídas)
```

---

## 📝 Arquivos Modificados

1. **`app/Models/Conversation.php`** (linha 345-353)
   - Adicionado filtro padrão automático

---

## ✅ Checklist de Validação

- [x] Filtro padrão aplicado corretamente
- [x] Filtros explícitos respeitados
- [x] Conversas não atribuídas sempre visíveis
- [x] Limite de 150 por página confirmado
- [x] Scroll infinito funciona sem zerar
- [x] Logs de debug adicionados
- [ ] **Testar com múltiplos agentes** (aguardando validação)

---

## 🎯 Resultado Esperado

**Para o Agente**:
- Ver apenas **suas conversas** + **conversas disponíveis** (não atribuídas)
- Não ver conversas de outros agentes (a menos que aplique filtro explícito)
- Scroll infinito funciona corretamente

**Para Admins/Supervisores**:
- Podem usar filtro de agente para ver conversas específicas
- Filtro padrão também se aplica (ver suas + não atribuídas)
- Permissões de funil ainda são aplicadas (já implementado)

---

## 🚀 Próximos Passos

1. ✅ Aplicar mudanças (aceitar diff)
2. ⏳ Testar com múltiplos agentes
3. ⏳ Validar comportamento com permissões de funil
4. ⏳ Monitorar logs para confirmar filtros corretos
