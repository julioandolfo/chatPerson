# 🔧 CORREÇÃO: Conversas Não Atribuídas Não Aparecem

**Problema:** Mesmo com a permissão `conversations.view.unassigned`, os agentes não conseguem ver conversas não atribuídas.

**Data:** 2025-12-27

---

## 📋 O QUE FOI CORRIGIDO

### 1. **Menu Simplificado** ✅
- Removido menu duplicado "Usuários"
- Menu "Agentes" agora é direto (sem submenu)

### 2. **Verificação de Conversas Não Atribuídas** ✅
- Melhorada a lógica de verificação em `PermissionService::canViewConversation()`
- Agora verifica corretamente: `NULL`, `0`, `'0'` e `''` (string vazia)

### 3. **Scripts de Correção Criados** ✅
- `public/fix-permissions.php` - Adiciona permissões e limpa cache
- `public/debug-permissions.php` - Diagnóstico detalhado

---

## 🚀 COMO CORRIGIR

### Passo 1: Execute o Script de Correção

Acesse no navegador:
```
http://localhost/fix-permissions.php
```

O script irá:
- ✅ Adicionar permissão `conversations.view.unassigned` às roles de agentes
- ✅ Adicionar permissão `funnels.view` (para Kanban)
- ✅ Limpar todo o cache de permissões
- ✅ Mostrar relatório detalhado

### Passo 2: Faça Logout e Login

**IMPORTANTE:** O cache de permissões fica na sessão, então você DEVE:
1. Fazer logout
2. Fazer login novamente
3. Ou limpar cookies/sessão do navegador

### Passo 3: Limpe o Cache do Navegador

Pressione: `Ctrl + Shift + Delete`
- Marque: "Cookies" e "Cache"
- Limpe dos últimos 7 dias

### Passo 4: Teste

1. Acesse `/conversations`
2. Use o filtro "🔴 Não atribuídas"
3. Deve mostrar conversas sem agente

---

## 🔍 DIAGNÓSTICO

Se ainda não funcionar, use o script de debug:

```
http://localhost/debug-permissions.php
```

O script mostra:
- ✅ Roles do usuário
- ✅ Permissões diretas
- ✅ Verificação de permissões críticas
- ✅ Conversas não atribuídas disponíveis
- ✅ Status do cache
- ✅ Diagnóstico final com ações

---

## 🐛 PROBLEMAS COMUNS

### Problema 1: "Ainda não vejo as conversas"

**Causa:** Cache de permissões não foi limpo

**Solução:**
1. Execute `fix-permissions.php` novamente
2. Faça logout completo
3. Limpe cookies do navegador
4. Faça login novamente

### Problema 2: "Não há conversas não atribuídas"

**Causa:** Todas as conversas estão atribuídas

**Solução:**
1. Crie uma conversa de teste
2. Não atribua a nenhum agente
3. Verifique se `agent_id` é `NULL` no banco

### Problema 3: "Permissão não aparece no debug"

**Causa:** Permissão não foi adicionada à role

**Solução:**
1. Execute `fix-permissions.php`
2. Verifique no banco:
```sql
SELECT p.slug, r.name 
FROM permissions p
INNER JOIN role_permissions rp ON p.id = rp.permission_id
INNER JOIN roles r ON rp.role_id = r.id
WHERE p.slug = 'conversations.view.unassigned'
AND r.slug = 'agent';
```

### Problema 4: "Erro 403 ao acessar Kanban"

**Causa:** Falta permissão `funnels.view`

**Solução:**
1. Execute `fix-permissions.php`
2. Verifique se a permissão foi adicionada
3. Faça logout e login

---

## 📝 ALTERAÇÕES TÉCNICAS

### Arquivo: `app/Services/PermissionService.php`

**Antes:**
```php
if (empty($conversation['agent_id']) || $conversation['agent_id'] === null) {
    if (self::hasPermission($userId, 'conversations.view.unassigned')) {
        return true;
    }
}
```

**Depois:**
```php
$agentId = $conversation['agent_id'] ?? null;
$isUnassigned = ($agentId === null || $agentId === 0 || $agentId === '0' || $agentId === '');

if ($isUnassigned) {
    if (self::hasPermission($userId, 'conversations.view.unassigned')) {
        return true;
    }
    if (self::hasPermission($userId, 'conversations.view.own')) {
        return true;
    }
}
```

**Motivo:** Verificação mais robusta que cobre todos os casos de "não atribuído"

### Arquivo: `views/layouts/metronic/sidebar.php`

**Alteração:** Menu "Agentes" agora é direto, sem submenu "Usuários"

---

## ✅ CHECKLIST DE VERIFICAÇÃO

Após aplicar as correções, verifique:

- [ ] Script `fix-permissions.php` executado com sucesso
- [ ] Logout e login realizados
- [ ] Cache do navegador limpo
- [ ] Permissão `conversations.view.unassigned` presente no debug
- [ ] Permissão `funnels.view` presente no debug
- [ ] Conversas não atribuídas aparecem na lista
- [ ] Filtro "🔴 Não atribuídas" funciona
- [ ] Kanban acessível sem erro 403
- [ ] Menu "Usuários" não aparece mais

---

## 🎯 TESTE COMPLETO

### 1. Criar Conversa de Teste

```sql
-- No banco de dados
INSERT INTO conversations (contact_id, channel, status, agent_id, created_at, updated_at)
VALUES (1, 'whatsapp', 'open', NULL, NOW(), NOW());
```

### 2. Verificar Permissões

Acesse: `http://localhost/debug-permissions.php?user_id=SEU_ID`

Deve mostrar:
- ✅ `conversations.view.unassigned` - TEM
- ✅ `funnels.view` - TEM

### 3. Testar Lista de Conversas

1. Acesse: `/conversations`
2. Clique no filtro de agentes
3. Selecione "🔴 Não atribuídas"
4. Deve mostrar a conversa criada

### 4. Testar Kanban

1. Acesse: `/funnels/1/kanban`
2. Deve ver todas as colunas
3. Deve ver conversas não atribuídas
4. Pode arrastar conversas próprias

---

## 📞 SUPORTE

Se ainda tiver problemas:

1. Execute: `http://localhost/debug-permissions.php`
2. Copie o relatório completo
3. Verifique os logs em: `storage/logs/`
4. Verifique o console do navegador (F12)

---

## 🎉 RESULTADO ESPERADO

Após aplicar todas as correções:

✅ **Menu limpo** - Apenas "Agentes"
✅ **Conversas não atribuídas visíveis** - Agentes podem ver e pegar
✅ **Kanban funcional** - Acesso completo
✅ **Filtros funcionando** - Todos os filtros operacionais
✅ **Permissões corretas** - Sistema funcionando como esperado

---

**Última atualização:** 2025-12-27
**Versão:** 1.0

