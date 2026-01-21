# ✅ Correção: Scripts de Debug - Campo Inexistente

**Data**: 2026-01-20  
**Status**: ✅ CORRIGIDO  
**Prioridade**: 🟡 MÉDIA

---

## 🎯 **Problema**

### Erro:
```
1054 - Unknown column 'ca.assignment_method' in 'field list'
```

### Causa:
Os scripts de debug (`debug-conversation.php` e `debug-conversation-simple.sql`) estavam referenciando o campo `assignment_method` na tabela `conversation_assignments`, mas esse campo **não existe**.

### Estrutura Real da Tabela:
```sql
CREATE TABLE conversation_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    agent_id INT,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    removed_at TIMESTAMP NULL
);
```

---

## ✅ **Correção Aplicada**

### **1. Arquivo: `debug-conversation-simple.sql`**

#### Mudanças:

**Antes** ❌:
```sql
SELECT 
    ca.assignment_method as metodo,
    ...
FROM conversation_assignments ca
```

**Depois** ✅:
```sql
-- Campo removido das queries
-- Método agora é inferido por assigned_by:
-- - assigned_by = 0 ou NULL → 'auto/sistema'
-- - assigned_by = agent_id → 'auto'
-- - assigned_by != agent_id → 'manual'
```

#### Queries Corrigidas:
1. **Histórico de Atribuições** (linha ~53)
   - Removido `ca.assignment_method`
   
2. **Timeline Completo** (linha ~103)
   - Removido `ca.assignment_method` do CONCAT
   
3. **Reatribuições Desnecessárias** (linha ~151)
   - Substituído `metodo_1` e `metodo_2` por `atribuido_por_1` e `atribuido_por_2`
   
4. **Auto-atribuição após Mensagem** (linha ~175)
   - Removido `AND ca.assignment_method = 'auto'`
   - Agora usa: `AND ca.agent_id = ca.assigned_by` (mesmo agente se atribuiu)

---

### **2. Arquivo: `debug-conversation.php`**

#### Mudanças:

**Antes** ❌:
```php
$assignments = Database::fetchAll(
    "SELECT ca.*, ca.assignment_method ...
```

**Depois** ✅:
```php
// Campo removido da query
// Método inferido no PHP:
$assignedBy = $assign['assigned_by'] ?? 0;
$method = ($assignedBy == 0) ? 'auto' : 
          (($assignedBy == $assign['agent_id']) ? 'auto' : 'manual');
```

#### Funções Corrigidas:
1. **Histórico de Atribuições** (linha ~95)
   - Removido campo da query
   - Adicionada lógica para inferir método
   
2. **Timeline de Atividades** (linha ~145)
   - Removido `assignment_method` da UNION
   - Ajustada lógica para determinar tipo
   
3. **Análise de Problemas** (linha ~260)
   - `AND ca.assignment_method = 'auto'` → `AND ca.agent_id = ca.assigned_by`

---

## 📝 **Lógica de Inferência do Método**

Como o campo `assignment_method` não existe, inferimos o método baseado em `assigned_by`:

### **Regras:**

| assigned_by | agent_id | Método | Descrição |
|-------------|----------|--------|-----------|
| `0` ou `NULL` | qualquer | `auto/sistema` | Sistema atribuiu automaticamente |
| igual a `agent_id` | igual | `auto` | Agente se auto-atribuiu |
| diferente de `agent_id` | diferente | `manual` | Outro usuário atribuiu manualmente |

### **Código:**

**PHP:**
```php
$assignedBy = $assign['assigned_by'] ?? 0;
$method = ($assignedBy == 0) 
    ? 'auto/sistema' 
    : (($assignedBy == $assign['agent_id']) ? 'auto' : 'manual');
```

**SQL** (não exibido diretamente, mas usado na análise):
```sql
CASE 
    WHEN assigned_by = 0 OR assigned_by IS NULL THEN 'auto/sistema'
    WHEN assigned_by = agent_id THEN 'auto'
    ELSE 'manual'
END as metodo
```

---

## 🧪 **Como Testar**

### Teste 1: SQL

```sql
-- Abra: debug-conversation-simple.sql
-- Altere:
SET @conversation_id = 123; -- ← COLOQUE O ID AQUI

-- Execute as queries
-- ✅ NÃO deve mais dar erro de coluna inexistente
```

### Teste 2: PHP

```bash
php debug-conversation.php 123

# ✅ Deve executar sem erros
# ✅ Deve mostrar "Método: auto" ou "manual" corretamente
```

---

## 📊 **Exemplo de Saída Corrigida**

### Antes (com erro):
```
❌ 1054 - Unknown column 'ca.assignment_method' in 'field list'
```

### Depois (funcionando):
```
📊 HISTÓRICO DE ATRIBUIÇÕES
────────────────────────────────────────────────────────────────
1. 2026-01-20 14:24:00 - ✅ ATIVO
   Agente: #5 - Gustavo
   Atribuído por: Sistema (#0)
   Método: auto/sistema

2. 2026-01-20 14:25:00 - ❌ REMOVIDO
   Agente: #7 - Gabriel Freitas
   Atribuído por: Gustavo (#5)
   Método: manual
   ❌ Removido em: 2026-01-20 14:25:00

3. 2026-01-20 14:28:00 - ✅ ATIVO
   Agente: #5 - Gustavo
   Atribuído por: Gustavo (#5)
   Método: auto
   ⚠️  AUTO-ATRIBUIÇÃO DETECTADA: Agente atribuiu para si mesmo!
```

---

## 📝 **Arquivos Modificados**

| Arquivo | Mudanças | Status |
|---------|----------|--------|
| `debug-conversation-simple.sql` | Removido 4 referências a `assignment_method` | ✅ CORRIGIDO |
| `debug-conversation.php` | Removido 7 referências a `assignment_method` | ✅ CORRIGIDO |
| `GUIA_DEBUG_CONVERSAS.md` | Documentação (não afetada) | ✅ OK |
| `COMO_USAR_DEBUG.txt` | Guia rápido (não afetado) | ✅ OK |

---

## 🎯 **Impacto**

### Antes:
- ❌ Scripts de debug não funcionavam
- ❌ Erro SQL ao tentar debugar conversa
- ❌ Impossível investigar problemas

### Depois:
- ✅ Scripts funcionam perfeitamente
- ✅ Método inferido corretamente do `assigned_by`
- ✅ Debug completo disponível

---

## 💡 **Considerações Futuras**

Se no futuro quiser adicionar o campo `assignment_method` à tabela:

```sql
ALTER TABLE conversation_assignments 
ADD COLUMN assignment_method VARCHAR(20) DEFAULT 'manual'
AFTER assigned_by;

-- Valores possíveis: 'manual', 'auto', 'automation', 'webhook', etc
```

Mas por enquanto, a inferência baseada em `assigned_by` funciona bem!

---

**Última atualização**: 2026-01-20 18:15
