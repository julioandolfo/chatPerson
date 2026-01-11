# Sistema de Histórico de Atribuições para Conversão

## 📋 Resumo

Sistema completo de histórico de atribuições de conversas a agentes, garantindo que as métricas de conversão WooCommerce considerem TODAS as conversas que passaram pelo agente, mesmo que tenham sido reatribuídas posteriormente.

---

## ✅ Implementado

### 1. **Migration: Tabela de Histórico**

**Arquivo:** `database/migrations/101_create_conversation_assignments_history.php`

**Tabela:** `conversation_assignments`

**Campos:**
- `id`: INT AUTO_INCREMENT PRIMARY KEY
- `conversation_id`: INT NOT NULL (FK → conversations)
- `agent_id`: INT NULL (FK → users) - NULL = conversa não atribuída
- `assigned_by`: INT NULL (FK → users) - NULL = sistema/automação
- `assigned_at`: TIMESTAMP DEFAULT CURRENT_TIMESTAMP

**Índices:**
- `idx_conversation_agent` (conversation_id, agent_id)
- `idx_agent_date` (agent_id, assigned_at)
- `idx_conversation_date` (conversation_id, assigned_at)

**Migração automática:**
- Popula com dados existentes: todas as conversas já atribuídas são inseridas no histórico usando `assigned_at` ou `created_at`.

---

### 2. **Model: ConversationAssignment**

**Arquivo:** `app/Models/ConversationAssignment.php`

**Métodos principais:**

```php
// Registrar atribuição
ConversationAssignment::recordAssignment(
    int $conversationId,
    ?int $agentId,
    ?int $assignedBy = null
): int

// Contar conversas únicas do agente no período
ConversationAssignment::countAgentConversations(
    int $agentId,
    ?string $dateFrom = null,
    ?string $dateTo = null
): int

// Obter histórico de uma conversa
ConversationAssignment::getConversationHistory(int $conversationId): array

// Verificar se agente já foi atribuído
ConversationAssignment::wasAgentAssigned(int $conversationId, int $agentId): bool

// Obter último agente atribuído
ConversationAssignment::getLastAssignedAgent(int $conversationId): ?array
```

---

### 3. **Integração no ConversationService**

**Arquivo:** `app/Services/ConversationService.php`

**Pontos de registro:**

1. **Criação de conversa com agente:**
   - Quando `$agentId` é definido na criação, registra no histórico automaticamente.
   - `assigned_by` = NULL (sistema/automação).

2. **Atribuição manual/automática:**
   - Método `assignToAgent()` registra toda mudança de agente.
   - `assigned_by` = ID do usuário logado (se disponível) ou NULL.

---

### 4. **Atualização do AgentConversionService**

**Arquivo:** `app/Services/AgentConversionService.php`

**Método alterado:**

```php
private static function getTotalConversations(int $agentId, string $dateFrom, string $dateTo): int
{
    // Agora usa o histórico de atribuições
    return \App\Models\ConversationAssignment::countAgentConversations(
        $agentId,
        $dateFrom,
        $dateTo
    );
}
```

**Impacto:**
- A taxa de conversão agora considera TODAS as conversas que foram atribuídas ao agente no período.
- Conversas reatribuídas para outros agentes continuam contando para o agente original.
- Mais preciso para medir desempenho real do agente.

---

## 🔄 Fluxo de Funcionamento

### Cenário 1: Nova Conversa com Atribuição Automática

1. Cliente envia mensagem
2. `ConversationService::create()` é chamado
3. Sistema atribui agente automaticamente (ex: round-robin)
4. Conversa criada com `agent_id = 5`
5. **Histórico registrado:** `conversation_assignments` recebe registro (conversation_id, agent_id=5, assigned_by=NULL)

### Cenário 2: Reatribuição Manual

1. Supervisor reatribui conversa do Agente 5 para Agente 8
2. `ConversationService::assignToAgent(conversationId, 8)` é chamado
3. Conversa atualizada: `agent_id = 8`
4. **Histórico registrado:** novo registro (conversation_id, agent_id=8, assigned_by=1)

### Cenário 3: Cálculo de Conversão

1. Agente 5 teve 100 conversas atribuídas no mês
2. 20 dessas conversas foram reatribuídas para outros agentes
3. **Antes:** Contava apenas 80 conversas (as que ainda estão com ele)
4. **Agora:** Conta 100 conversas (todas que passaram por ele)
5. Se ele converteu 25 vendas: taxa = 25/100 = 25% (correto)
6. **Antes seria:** 25/80 = 31,25% (inflado incorretamente)

---

## 📊 Consultas Úteis

### Ver histórico de uma conversa

```sql
SELECT ca.*, 
       u.name as agent_name,
       assigned.name as assigned_by_name
FROM conversation_assignments ca
LEFT JOIN users u ON ca.agent_id = u.id
LEFT JOIN users assigned ON ca.assigned_by = assigned.id
WHERE ca.conversation_id = 123
ORDER BY ca.assigned_at ASC;
```

### Conversas únicas de um agente no período

```sql
SELECT COUNT(DISTINCT conversation_id) as total
FROM conversation_assignments
WHERE agent_id = 5
  AND assigned_at BETWEEN '2026-01-01' AND '2026-01-31 23:59:59';
```

### Agentes que atenderam uma conversa

```sql
SELECT DISTINCT u.id, u.name, ca.assigned_at
FROM conversation_assignments ca
INNER JOIN users u ON ca.agent_id = u.id
WHERE ca.conversation_id = 123
ORDER BY ca.assigned_at ASC;
```

---

## 🎯 Benefícios

1. **Precisão nas métricas:** Taxa de conversão reflete o trabalho real do agente
2. **Histórico completo:** Rastreabilidade de todas as atribuições
3. **Auditoria:** Saber quem atribuiu e quando
4. **Análise de reatribuições:** Identificar conversas que "pulam" entre agentes
5. **Performance justa:** Agentes não perdem crédito por conversas reatribuídas

---

## 🚀 Como Usar

### 1. Executar Migration

```bash
php database/migrate.php
```

Isso cria a tabela e popula com dados existentes.

### 2. Verificar Dados

Após migration, todas as conversas já atribuídas estarão no histórico.

### 3. Métricas de Conversão

As métricas já usam automaticamente o histórico. Nenhuma ação adicional necessária.

### 4. Consultar Histórico (Opcional)

```php
// Ver histórico de uma conversa
$history = \App\Models\ConversationAssignment::getConversationHistory(123);

// Verificar se agente já atendeu
$wasAssigned = \App\Models\ConversationAssignment::wasAgentAssigned(123, 5);

// Último agente
$lastAgent = \App\Models\ConversationAssignment::getLastAssignedAgent(123);
```

---

## 📝 Observações Importantes

1. **Retroativo:** A migration popula o histórico com conversas existentes
2. **Automático:** Todas as novas atribuições são registradas automaticamente
3. **Performance:** Índices otimizados para consultas rápidas por agente/data
4. **Integridade:** Foreign keys garantem consistência dos dados
5. **Cascata:** Se conversa for deletada, histórico também é removido (ON DELETE CASCADE)

---

## 🔧 Arquivos Modificados

1. **`database/migrations/101_create_conversation_assignments_history.php`** - Nova migration
2. **`app/Models/ConversationAssignment.php`** - Novo model
3. **`app/Services/ConversationService.php`** - Integração do registro de histórico
4. **`app/Services/AgentConversionService.php`** - Uso do histórico para contagem

---

## ✅ Status dos Pedidos para Conversão

Apenas pedidos com os seguintes status contam para conversão:
- `processing`
- `completed`
- `producao`
- `designer`
- `pedido-enviado`
- `pedido-entregue`

Pedidos cancelados, reembolsados, falhados, etc. **não** contam.

---

**Data:** 11/01/2026  
**Status:** ✅ Completo e Pronto para Uso  
**Migration:** `101_create_conversation_assignments_history.php`
