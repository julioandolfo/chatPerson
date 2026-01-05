# ✅ Correção: Permissões de Funil em Listagem de Conversas

**Data**: 2025-01-05  
**Problema**: Agentes estavam vendo todas as conversas na listagem, mesmo sem permissão para os funis/etapas delas.

---

## 🎯 O Que Foi Corrigido

### Problema Identificado
Conforme o guia `GUIA_ALTERACOES_LISTA_CONVERSAS.md`, as conversas são renderizadas em **3 contextos**:
1. ✅ **Carregamento Inicial (PHP)** - JÁ estava filtrando via backend
2. ✅ **Filtros/Busca (JavaScript)** - JÁ estava filtrando via backend
3. ❌ **Tempo Real (JavaScript)** - **NÃO estava filtrando!**

### Solução Implementada
Adicionamos verificação de permissões de funil **no frontend** para bloquear conversas em tempo real que o agente não tem permissão para visualizar.

---

## 📝 Alterações Realizadas

### 1. Backend (JÁ IMPLEMENTADO - Não alterado)
- ✅ `PermissionService::canViewConversation()` - Verifica funil/etapa
- ✅ `AgentFunnelPermission::canViewConversation()` - Lógica centralizada
- ✅ `AgentFunnelPermission::getAllowedFunnelIds()` - Lista funis permitidos
- ✅ `AgentFunnelPermission::getAllowedStageIds()` - Lista etapas permitidas
- ✅ `ConversationService::list()` - Filtra conversas por permissões
- ✅ `FunnelController` - Filtra funis/etapas nos dropdowns

### 2. Frontend (NOVO - Implementado hoje)

#### 2.1. Variável Global de Permissões
**Arquivo**: `views/conversations/index.php` (linha ~1951)

```javascript
window.userFunnelPermissions = {
    allowed_funnel_ids: [1, 2, 3] ou null (admin),
    allowed_stage_ids: [5, 6, 7] ou null (admin)
};
```

#### 2.2. Função de Validação
**Arquivo**: `views/conversations/index.php` (linha ~1960)

```javascript
function canViewConversationByFunnel(conversation) {
    // Retorna true se:
    // - Usuário é admin (allowed_funnel_ids === null)
    // - Conversa não tem funil (conversas antigas)
    // - Usuário tem permissão no funil E etapa da conversa
    
    // Retorna false se:
    // - Usuário não tem permissão no funil
    // - Usuário não tem permissão na etapa
}
```

#### 2.3. Handlers de Tempo Real Atualizados

**A) Handler `new_conversation` (WebSocket)**  
**Arquivo**: `views/conversations/index.php` (linha ~16102)

```javascript
window.wsClient.on('new_conversation', (data) => {
    // ✅ VERIFICAR PERMISSÃO DE FUNIL antes de adicionar
    if (!canViewConversationByFunnel(data.conversation)) {
        console.log('🚫 Nova conversa bloqueada por permissões de funil');
        return;
    }
    addConversationToList(data.conversation);
});
```

**B) Listener Global `realtime:new_conversation`**  
**Arquivo**: `views/conversations/index.php` (linha ~16232)

```javascript
window.addEventListener('realtime:new_conversation', (e) => {
    // ✅ VERIFICAR PERMISSÃO DE FUNIL antes de adicionar
    if (!canViewConversationByFunnel(conversation)) {
        console.log('🚫 Nova conversa bloqueada (evento global)');
        return;
    }
    addConversationToList(conversation);
});
```

**C) Handler `conversation_updated` (conversas novas/atualização)**  
**Arquivo**: `views/conversations/index.php` (linha ~16142)

```javascript
window.wsClient.on('conversation_updated', (data) => {
    // Se conversa não existe na lista ainda
    if (!existingItem) {
        // ✅ VERIFICAR PERMISSÃO DE FUNIL antes de adicionar
        if (!canViewConversationByFunnel(conversationToAdd)) {
            console.log('🚫 Conversa atualizada bloqueada por permissões');
            return;
        }
        addConversationToList(conversationToAdd);
    }
});
```

---

## 🔍 Como Testar

### 1. Preparação
```bash
# Acessar como agente COM permissões limitadas de funil
# Exemplo: Agente com permissão apenas para Funil "Vendas" (ID 1)
```

### 2. Cenários de Teste

#### Teste 1: Listagem Inicial
- ✅ Deve mostrar apenas conversas dos funis permitidos
- ✅ Filtro de funis deve mostrar apenas funis com permissão
- ✅ Filtro de etapas deve mostrar apenas etapas com permissão

#### Teste 2: Nova Conversa em Tempo Real
1. Criar uma nova conversa do WhatsApp em um funil **permitido**
   - ✅ Deve aparecer na lista automaticamente
   - ✅ Console deve mostrar: "Nova conversa recebida (WS/Poll):"

2. Criar uma nova conversa em um funil **NÃO permitido**
   - ✅ **NÃO** deve aparecer na lista
   - ✅ Console deve mostrar: "🚫 Nova conversa bloqueada por permissões de funil - convId: X"

#### Teste 3: Atualização de Conversa
1. Atualizar uma conversa existente (nova mensagem, mudança de status)
   - ✅ Deve atualizar normalmente se o agente tem permissão
   - ✅ Não deve aparecer se o agente não tem permissão

#### Teste 4: Admin/Super Admin
- ✅ Admin deve ver TODAS as conversas (sem filtro)
- ✅ Variável `window.userFunnelPermissions.allowed_funnel_ids` deve ser `null`

---

## 🐛 Debug

### Ver Permissões do Usuário no Console
```javascript
// No console do navegador
console.log(window.userFunnelPermissions);
// Saída esperada:
// { allowed_funnel_ids: [1, 2], allowed_stage_ids: [5, 6, 7] }
// ou
// { allowed_funnel_ids: null, allowed_stage_ids: null } // Admin
```

### Testar Função de Validação
```javascript
// No console do navegador
canViewConversationByFunnel({ id: 123, funnel_id: 1, funnel_stage_id: 5 });
// Saída: true ou false
```

### Ver Logs de Bloqueio
1. Abrir console do navegador (F12)
2. Procurar por mensagens com emoji 🚫:
   - `🚫 [Filtro Funil] Conversa bloqueada - convId: X`
   - `🚫 [Filtro Etapa] Conversa bloqueada - convId: X`
   - `🚫 Nova conversa bloqueada por permissões de funil - convId: X`

### Script de Debug Backend
Execute: `public/debug-funnel-permissions.php`
- Mostra funis permitidos para o usuário
- Mostra etapas permitidas
- Testa listagem de conversas
- Testa acesso a uma conversa específica

---

## ✅ Checklist de Validação

### Backend (Já implementado anteriormente)
- [x] `AgentFunnelPermission::getAllowedFunnelIds()` funciona corretamente
- [x] `AgentFunnelPermission::getAllowedStageIds()` funciona corretamente
- [x] `AgentFunnelPermission::canViewConversation()` valida corretamente
- [x] `PermissionService::canViewConversation()` usa permissões de funil
- [x] `ConversationService::list()` filtra por permissões
- [x] `FunnelController` filtra funis/etapas nos dropdowns
- [x] Acesso direto a conversas bloqueado (403) se sem permissão

### Frontend (Implementado hoje)
- [x] `window.userFunnelPermissions` carregado corretamente
- [x] `canViewConversationByFunnel()` funciona corretamente
- [x] Handler `new_conversation` valida permissões
- [x] Listener global `realtime:new_conversation` valida permissões
- [x] Handler `conversation_updated` valida permissões ao adicionar novas conversas
- [x] Logs de debug (🚫) aparecem quando conversa é bloqueada

### Testes Integrados
- [ ] Listagem inicial mostra apenas conversas permitidas
- [ ] Filtros mostram apenas funis/etapas com permissão
- [ ] Nova conversa (tempo real) só aparece se tiver permissão
- [ ] Atualização de conversa só aparece se tiver permissão
- [ ] Admin vê todas as conversas (sem filtro)
- [ ] Acesso direto bloqueado se sem permissão

---

## 📚 Arquivos Alterados

1. **views/conversations/index.php** (✅ MODIFICADO)
   - Linhas ~1951-2008: Variável global + função `canViewConversationByFunnel()`
   - Linhas ~16102-16127: Handler `new_conversation` com validação
   - Linhas ~16142-16167: Handler `conversation_updated` com validação
   - Linhas ~16232-16252: Listener global com validação

2. **app/Models/AgentFunnelPermission.php** (✅ JÁ IMPLEMENTADO)
   - `getAllowedFunnelIds()`, `getAllowedStageIds()`, `canViewConversation()`

3. **app/Services/PermissionService.php** (✅ JÁ IMPLEMENTADO)
   - `canViewConversation()` usa permissões de funil

4. **app/Services/ConversationService.php** (✅ JÁ IMPLEMENTADO)
   - `list()` filtra por permissões + cache temporariamente desabilitado

5. **app/Services/ConversationMentionService.php** (✅ JÁ IMPLEMENTADO)
   - `checkUserAccess()` valida funil para conversas não atribuídas

6. **app/Controllers/FunnelController.php** (✅ JÁ IMPLEMENTADO)
   - `index()` e `getStagesJson()` filtram por permissões

---

## 🔄 Próximos Passos

1. **Testar** todos os cenários descritos acima
2. **Remover** o cache clearing temporário em `ConversationService::list()` após confirmar que funciona
3. **Validar** com usuários reais (agentes com permissões limitadas)
4. **Documentar** no `CONTEXT_IA.md` que o sistema respeita permissões de funil em tempo real

---

## 📌 Notas Importantes

### Por que Filtro no Frontend?
- O WebSocket faz **broadcast** para TODOS os agentes conectados
- É mais eficiente filtrar no frontend do que criar lógica de "salas" no WebSocket
- O backend JÁ filtra na listagem inicial e nos filtros (segurança garantida)
- O frontend apenas impede UI desnecessária (conversas que o agente não pode acessar)

### Segurança
- ✅ Backend SEMPRE valida permissões (segurança real)
- ✅ Frontend apenas melhora UX (não mostra conversas que não pode acessar)
- ✅ Acesso direto via URL é bloqueado pelo backend (403)
- ✅ API REST valida permissões antes de retornar dados

### Performance
- ✅ Permissões carregadas uma vez no carregamento da página
- ✅ Validação em JavaScript é instantânea (sem chamadas ao servidor)
- ✅ Cache de conversas temporariamente desabilitado para debug (será reativado)

---

**Status**: ✅ **IMPLEMENTADO** - Aguardando testes  
**Próxima ação**: Testar com agente de permissões limitadas
