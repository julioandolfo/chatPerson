# 🔧 Correção: Histórico de Atribuições mostrando "Desconhecido"

## 📋 Problema Identificado

No modal "VER DETALHES" dos cards do kanban, o histórico de atribuições estava mostrando:
- **"Desconhecido"** para alguns agentes
- Registros duplicados ou inconsistentes

## 🔍 Causa Raiz

Foram identificados **dois problemas principais**:

### 1. Incompatibilidade entre Migrações

Existiam duas migrações conflitantes para a tabela `conversation_assignments`:

**Migration 091 (antiga):**
```sql
CREATE TABLE conversation_assignments (
    from_agent_id INT,  -- ❌ Estrutura antiga
    to_agent_id INT,    -- ❌ Estrutura antiga
    ...
)
```

**Migration 101 (atual):**
```sql
CREATE TABLE conversation_assignments (
    agent_id INT,       -- ✅ Estrutura correta
    assigned_by INT,    -- ✅ Estrutura correta
    assigned_at TIMESTAMP,
    removed_at TIMESTAMP
)
```

### 2. Registros com `agent_id = NULL`

A migration 101, ao popular dados existentes, incluía conversas não atribuídas:

```sql
INSERT INTO conversation_assignments (conversation_id, agent_id, assigned_at)
SELECT id, agent_id, COALESCE(created_at, NOW())
FROM conversations
WHERE agent_id IS NOT NULL  -- ❌ Filtro faltava
```

Isso criava registros onde `agent_id = NULL`, fazendo o LEFT JOIN retornar NULL para `agent_name`.

## ✅ Soluções Aplicadas

### 1. Script SQL de Correção

Criado: `FIX_CONVERSATION_ASSIGNMENTS.sql`

**O que o script faz:**
1. ✅ Faz backup da tabela atual
2. ✅ Remove a tabela antiga
3. ✅ Cria tabela com estrutura correta
4. ✅ Restaura apenas registros válidos (com `agent_id NOT NULL`)
5. ✅ Adiciona campo `removed_at` (para controle futuro)
6. ✅ Verifica registros órfãos (agentes deletados)
7. ✅ Mostra relatório de atribuições

**Como executar:**
```bash
# No MySQL/phpMyAdmin
mysql -u root -p nome_do_banco < FIX_CONVERSATION_ASSIGNMENTS.sql
```

### 2. Melhoria na Query SQL (FunnelService.php)

**Antes:**
```php
FROM conversation_assignments ca
LEFT JOIN users u ON ca.agent_id = u.id  // ❌ LEFT JOIN permitia NULL
WHERE ca.conversation_id = ?
```

**Depois:**
```php
FROM conversation_assignments ca
INNER JOIN users u ON ca.agent_id = u.id  // ✅ INNER JOIN garante user existe
WHERE ca.conversation_id = ?
AND ca.agent_id IS NOT NULL              // ✅ Filtro explícito
AND ca.removed_at IS NULL                // ✅ Apenas atribuições ativas
```

### 3. Melhoria na Interface (conversation-details.js)

**Antes:**
```javascript
<td>${assignment.agent_name || 'Não atribuído'}</td>
<td>${assignment.assigned_by_name || 'Sistema'}</td>
```

**Depois:**
```javascript
// Agente
const agentName = assignment.agent_name 
    || '<span class="text-muted">Não atribuído</span>';

// Atribuído por
const assignedBy = assignment.assigned_by_name 
    ? `<span class="fw-semibold">${assignment.assigned_by_name}</span>`
    : '<span class="badge badge-light-info">Sistema/Automação</span>';

// Data formatada
const date = new Date(assignment.assigned_at).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
});
```

## 🎯 Resultado Esperado

Após aplicar as correções, o histórico de atribuições deve mostrar:

```
┌─────────────────────────────────────────────────────┐
│ 📊 Histórico de Atribuições                         │
├──────────────┬──────────────────┬──────────────────┤
│ Agente       │ Atribuído Por    │ Data             │
├──────────────┼──────────────────┼──────────────────┤
│ Monique      │ Monique          │ 15/01/26, 11:32  │
│ Monique      │ Sistema/Automação│ 15/01/26, 11:29  │
│ João Silva   │ Admin Master     │ 15/01/26, 08:20  │
└──────────────┴──────────────────┴──────────────────┘
```

**Não deve mais aparecer:**
- ❌ "Desconhecido" como nome de agente
- ❌ Registros duplicados consecutivos
- ❌ Registros de conversas não atribuídas (agent_id NULL)

## 📝 Checklist de Verificação

Após executar o script, verifique:

- [ ] Backup foi criado (`conversation_assignments_backup_20260118`)
- [ ] Tabela `conversation_assignments` tem apenas 4 colunas: `id`, `conversation_id`, `agent_id`, `assigned_by`, `assigned_at`, `removed_at`
- [ ] Nenhum registro com `agent_id = NULL`
- [ ] Todos os `agent_id` existem na tabela `users`
- [ ] No modal "VER DETALHES", o histórico mostra nomes corretos
- [ ] A data está formatada corretamente (DD/MM/AA, HH:MM)

## 🗑️ Limpeza (Opcional)

Após confirmar que tudo está funcionando:

```sql
-- Deletar backup (SOMENTE se tudo estiver OK!)
DROP TABLE IF EXISTS conversation_assignments_backup_20260118;
```

## 🚀 Prevenção Futura

Para evitar esse problema no futuro:

1. ✅ Sempre use `INNER JOIN` quando o relacionamento for obrigatório
2. ✅ Valide `NOT NULL` antes de inserir em tabelas de histórico
3. ✅ Use migrações versionadas (evitar conflitos)
4. ✅ Adicione validações no Model antes de gravar
5. ✅ Implemente testes para histórico de atribuições

## 📚 Arquivos Modificados

1. ✅ `FIX_CONVERSATION_ASSIGNMENTS.sql` (novo)
2. ✅ `app/Services/FunnelService.php` (linha 1472-1485)
3. ✅ `public/assets/js/conversation-details.js` (linha 361-368)
4. ✅ `CORRECAO_HISTORICO_ATRIBUICOES.md` (este arquivo)

---

**Criado em:** 18/01/2026  
**Problema:** Histórico mostrando "Desconhecido"  
**Status:** ✅ Resolvido
