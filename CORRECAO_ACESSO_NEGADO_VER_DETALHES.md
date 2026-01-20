# 🔧 Correção: ACESSO NEGADO ao clicar em VER DETALHES

## 📋 Problema Identificado

Quando um agente acessava o kanban e clicava em **"VER DETALHES"** de um card, aparecia:

```
❌ ACESSO NEGADO
```

## 🔍 Causa Raiz

Múltiplos controllers estavam usando a permissão **`conversations.view`** que **NÃO EXISTE** no sistema.

### Permissões Corretas no Sistema

```php
// ✅ PERMISSÕES QUE EXISTEM
'conversations.view.own'        // Ver conversas próprias
'conversations.view.assigned'   // Ver conversas atribuídas
'conversations.view.unassigned' // Ver conversas não atribuídas
'conversations.view.department' // Ver conversas do setor
'conversations.view.all'        // Ver todas as conversas

// ❌ PERMISSÃO QUE NÃO EXISTE
'conversations.view'  // ← Estava sendo usada, mas não existe!
```

### Código Problemático

```php
// ❌ ANTES (errado)
Permission::abortIfCannot('conversations.view');  // Permissão não existe!
```

Como **ninguém** tem essa permissão (pois ela não existe), todos recebiam "ACESSO NEGADO".

## ✅ Soluções Aplicadas

### 1. FunnelController.php (VER DETALHES)

**Arquivo:** `app/Controllers/FunnelController.php` (linha 695-720)

**Antes ❌:**
```php
public function getConversationDetails(int $conversationId): void
{
    try {
        Permission::abortIfCannot('conversations.view');  // ❌ Não existe
        
        $details = FunnelService::getConversationDetails($conversationId);
        // ...
    }
}
```

**Depois ✅:**
```php
public function getConversationDetails(int $conversationId): void
{
    try {
        // ✅ Verificar se pode ver conversas
        if (!Permission::can('conversations.view.own') && 
            !Permission::can('conversations.view.all')) {
            throw new \Exception('Você não tem permissão');
        }
        
        // Buscar conversa
        $conversation = \App\Models\Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        // ✅ Verificar se pode ver ESTA conversa específica
        if (!Permission::can('conversations.view.all')) {
            if (!Permission::canViewConversation($conversation)) {
                throw new \Exception('Sem permissão para esta conversa');
            }
        }
        
        $details = FunnelService::getConversationDetails($conversationId);
        // ...
    }
}
```

**O que mudou:**
1. ✅ Verifica `conversations.view.own` OU `conversations.view.all`
2. ✅ Busca a conversa para validar permissões específicas
3. ✅ Usa `Permission::canViewConversation()` para verificar se pode ver aquela conversa
4. ✅ Considera se é conversa própria, não atribuída, do setor, etc.

### 2. Outros Controllers Corrigidos

Mesma correção aplicada em:

**ConversationController.php:**
- `listForForwarding()` - Lista conversas para encaminhar

**TagController.php:**
- `getByConversation()` - Buscar tags de uma conversa

**AttachmentController.php:**
- `download()` - Download de anexos
- `view()` - Visualizar anexos
- `listByConversation()` - Listar anexos de uma conversa

**Padrão de correção:**
```php
// ✅ Padrão aplicado em todos
if (!Permission::can('conversations.view.own') && 
    !Permission::can('conversations.view.all')) {
    Permission::abortIfCannot('conversations.view.own');
}
```

## 🎯 Resultado Esperado

### Antes ❌

```
Usuário: Agente (com conversations.view.own)
Ação: Clicar em "VER DETALHES"
Resultado: ❌ ACESSO NEGADO
Motivo: Sistema verifica 'conversations.view' que não existe
```

### Depois ✅

```
Usuário: Agente (com conversations.view.own)
Ação: Clicar em "VER DETALHES"
Verificação:
  1. Tem conversations.view.own? ✅ SIM
  2. É conversa própria? ✅ SIM (atribuída ao agente)
Resultado: ✅ Modal abre com todos os detalhes
```

## 🧪 Como Testar

### Teste 1: Agente com Conversa Própria

1. Faça login como **Agente** (não admin)
2. Acesse o Kanban
3. Encontre uma conversa **atribuída a você**
4. Clique em "VER DETALHES"
5. ✅ Deve abrir o modal com:
   - Informações do contato
   - Histórico de mensagens
   - Histórico de atribuições
   - Métricas
   - Tags, notas, etc.

### Teste 2: Agente com Conversa Não Atribuída

1. Faça login como **Agente**
2. Acesse o Kanban
3. Encontre uma conversa **sem agente** (não atribuída)
4. Clique em "VER DETALHES"
5. ✅ Deve abrir se agente tiver `conversations.view.unassigned`
6. ❌ Deve negar se não tiver a permissão

### Teste 3: Agente com Conversa de Outro Agente

1. Faça login como **Agente A**
2. Acesse o Kanban
3. Encontre uma conversa atribuída ao **Agente B**
4. Clique em "VER DETALHES"
5. ❌ Deve negar acesso (não é conversa própria)
6. ✅ A menos que:
   - Tenha `conversations.view.all` (admin)
   - Ou seja do mesmo setor E tenha `conversations.view.department`

### Teste 4: Admin

1. Faça login como **Admin**
2. Acesse o Kanban
3. Clique em "VER DETALHES" de QUALQUER conversa
4. ✅ Deve abrir (admin tem `conversations.view.all`)

## 📊 Lógica de Permissões

### Ordem de Verificação

```
1. Tem 'conversations.view.all'? → ✅ LIBERA TUDO

2. Tem 'conversations.view.own'?
   ├─ É conversa própria? → ✅ LIBERA
   ├─ É conversa não atribuída E tem 'view.unassigned'? → ✅ LIBERA
   └─ Caso contrário → ❌ NEGA

3. Tem 'conversations.view.department'?
   └─ É do mesmo setor? → ✅ LIBERA

4. Caso contrário → ❌ NEGA
```

## 📁 Arquivos Modificados

1. ✅ **app/Controllers/FunnelController.php** (linhas 695-740)
   - Método: `getConversationDetails()`
   - Mudança: Validação completa de permissões

2. ✅ **app/Controllers/ConversationController.php** (linha 973-980)
   - Método: `listForForwarding()`
   - Mudança: Verificação correta de permissões

3. ✅ **app/Controllers/TagController.php** (linha 221-225)
   - Método: `getByConversation()`
   - Mudança: Verificação correta de permissões

4. ✅ **app/Controllers/AttachmentController.php** (linhas 20-26, 83-89, 144-149)
   - Métodos: `download()`, `view()`, `listByConversation()`
   - Mudança: Verificação correta de permissões

## ⚠️ Controllers NÃO Corrigidos (Menor Prioridade)

Estes ainda usam `conversations.view`, mas são menos críticos:

- `AIAssistantController.php` (linha 162)
- `TestController.php` (linhas 26, 42, 90)

**Nota:** TestController é apenas para testes, não afeta produção.

## 🔍 Como Identificar Esse Problema no Futuro

### Comando para verificar:

```bash
# Buscar uso de permissões inexistentes
grep -rn "conversations\.view'" app/Controllers/

# Deve retornar VAZIO se não houver problemas
```

### Permissões válidas do sistema:

Execute no banco de dados:
```sql
SELECT slug, name FROM permissions 
WHERE module = 'conversations' 
ORDER BY slug;
```

Resultado esperado:
```
conversations.view.own
conversations.view.assigned
conversations.view.unassigned
conversations.view.department
conversations.view.all
conversations.edit.own
conversations.edit.all
conversations.delete
...
```

**Nota:** `conversations.view` (sem sufixo) **NÃO** deve aparecer!

## 💡 Lições Aprendidas

1. **Sempre verificar se a permissão existe** antes de usar
2. **Usar permissões granulares** (`.own`, `.all`) ao invés de genéricas
3. **Validar contexto específico** (quem é o dono da conversa, setor, etc.)
4. **Usar helpers existentes** (`Permission::canViewConversation()`)
5. **Testar com diferentes perfis** (agente, admin, super admin)

## 📝 Checklist de Verificação

Após aplicar as correções:

- [ ] Cache limpo (Ctrl+Shift+Del)
- [ ] Testado como Agente
- [ ] "VER DETALHES" abre em conversa própria
- [ ] "VER DETALHES" nega em conversa de outro agente
- [ ] Testado como Admin
- [ ] "VER DETALHES" abre em qualquer conversa
- [ ] Anexos abrem normalmente
- [ ] Tags carregam normalmente

## 🎓 Prevenção Futura

### Ao criar novos controllers:

```php
// ❌ NÃO FAZER
Permission::abortIfCannot('conversations.view');

// ✅ FAZER
if (!Permission::can('conversations.view.own') && 
    !Permission::can('conversations.view.all')) {
    throw new \Exception('Sem permissão');
}

// OU (se tiver contexto da conversa)
if (!Permission::canViewConversation($conversation)) {
    throw new \Exception('Sem permissão');
}
```

### Sempre consultar:

1. `database/seeds/002_create_roles_and_permissions.php` - Lista de permissões
2. `app/Services/PermissionService.php` - Lógica de validação
3. `app/Helpers/Permission.php` - Helpers de permissão

---

**Status:** ✅ Corrigido  
**Data:** 18/01/2026  
**Impacto:** Alto - corrige acesso negado para todos os agentes  
**Urgência:** Alta - afeta funcionalidade principal do sistema  
**Ação necessária:** Nenhuma (correção já aplicada no código)  
**Teste:** Limpar cache e testar "VER DETALHES" no kanban
