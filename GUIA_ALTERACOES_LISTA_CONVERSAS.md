# 📋 Guia: Alterações na Lista de Conversas

> **Documento de Referência**: Este guia documenta TODAS as funções e locais que precisam ser alterados quando houver mudanças na estrutura HTML, dados ou comportamento da lista de conversas.
> 
> **Última atualização**: 2025-12-07

---

## 📍 Localização do Código

**Arquivo principal**: `views/conversations/index.php`

---

## 🎯 Quando usar este guia?

Use este guia quando precisar fazer alterações em:

- ✅ Estrutura HTML dos itens da lista de conversas
- ✅ Dados exibidos (nome, preview, tempo, badges, etc)
- ✅ Estilos ou classes CSS dos itens
- ✅ Adicionar/remover elementos visuais (ícones, botões, etc)
- ✅ Alterar lógica de renderização de avatares, tags, status, etc

---

## 📂 Estrutura de Renderização

A lista de conversas é renderizada em **3 contextos diferentes**:

1. **Carregamento Inicial (PHP)** - Primeira vez que a página é carregada
2. **Filtros/Busca (JavaScript)** - Quando usuário aplica filtros
3. **Tempo Real (JavaScript)** - Novas conversas ou atualizações via WebSocket/Polling

---

## 🔧 Funções que SEMPRE devem ser alteradas

### 1️⃣ **Renderização Inicial em PHP**

**Localização**: `views/conversations/index.php` - Linhas **~1300-1450**

**Contexto**:
```php
<?php foreach ($conversations as $conv): ?>
    <div class="conversation-item ...">
        <!-- Estrutura HTML aqui -->
    </div>
<?php endforeach; ?>
```

**Ordenação (backend) – manter igual ao frontend**:
- Use `ORDER BY COALESCE(c.pinned,0) DESC, c.pinned_at DESC, c.updated_at DESC` em `Conversation::getAll()` para que a lista inicial já venha com fixadas no topo e depois por mais recentes. Isso evita “pular” de ordem após alguns segundos quando o JS reordena.

**Quando alterar**:
- Sempre que mudar a estrutura HTML de um item da lista
- Quando adicionar/remover campos exibidos
- Quando alterar classes CSS ou atributos `data-*`

**Exemplo de alteração**:
```php
// Antes
<div class="conversation-item-name"><?= $conv['contact_name'] ?></div>

// Depois (com avatar)
<div class="conversation-item-name">
    <?php if (!empty($conv['contact_avatar'])): ?>
        <img src="<?= htmlspecialchars($conv['contact_avatar']) ?>" />
    <?php endif; ?>
    <?= htmlspecialchars($conv['contact_name']) ?>
</div>
```

---

### 2️⃣ **`applyConversationUpdate(conversations)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~3900-4100**

**Função**: Renderiza a lista completa de conversas (usada em filtros, busca, refresh)

**Contexto**:
```javascript
function applyConversationUpdate(conversations) {
    let html = '';
    conversations.forEach(conv => {
        // Criar HTML para cada conversa
        html += `<div class="conversation-item ...">...</div>`;
    });
    conversationListEl.innerHTML = html;
}
```

**Quando alterar**:
- Sempre que alterar a renderização inicial PHP
- Deve manter a MESMA estrutura HTML que a renderização PHP
- Quando adicionar novos campos dinâmicos

**⚠️ IMPORTANTE**:
- A estrutura HTML aqui DEVE ser idêntica à renderização PHP.
- A ordenação deve seguir o mesmo critério do backend (fixadas primeiro, depois mais recentes). Se ajustar sorting aqui, alinhe também o SQL.

---

### 3️⃣ **`addConversationToList(conv)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~8320-8450**

**Função**: Adiciona uma nova conversa dinamicamente ao topo da lista (tempo real)

**Contexto**:
```javascript
function addConversationToList(conv) {
    const conversationHtml = `
        <div class="conversation-item ...">
            <!-- Mesma estrutura das funções anteriores -->
        </div>
    `;
    conversationList.insertAdjacentHTML('afterbegin', conversationHtml);
}
```

**Quando alterar**:
- Sempre que alterar as funções 1️⃣ e 2️⃣
- Deve manter a MESMA estrutura HTML
- Quando adicionar lógica de novas conversas em tempo real

---

## 🔄 Funções de Atualização Parcial

Estas funções atualizam **partes específicas** de itens já existentes na lista.

### 4️⃣ **`refreshConversationBadges()` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~8420-8520**

**Função**: Atualiza badges, preview, tempo e metadados de conversas existentes

**Quando alterar**:
- Quando mudar a estrutura de badges de não lidas
- Quando alterar o preview da última mensagem
- Quando adicionar novos metadados dinâmicos

**Exemplo**:
```javascript
// Atualiza preview
const preview = conversationItem.querySelector('.conversation-item-preview');
if (preview) {
    preview.textContent = conv.last_message.substring(0, 37) + '...';
}
```

**⚠️ Nota**: Esta função chama `ensurePinButton` e `updateConversationMeta`

---

### 5️⃣ **`updateConversationListPreview(conversationId, data)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7370-7410**

**Função**: Atualiza preview, tempo e badge de UMA conversa específica

**Quando alterar**:
- Quando mudar como o preview da mensagem é exibido
- Quando alterar formato de tempo/data
- Quando mudar lógica de badges

---

### 6️⃣ **`updateConversationInList(conversationId, updates)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7410-7450**

**Função**: Atualiza campos específicos de uma conversa (genérico)

**Quando alterar**:
- Quando adicionar novos campos editáveis dinamicamente
- Quando mudar atributos `data-*` que precisam ser atualizados

---

## 🛠️ Funções Auxiliares/Helpers

### 7️⃣ **`ensurePinButton(conversationItem, pinned, conversationId)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7330-7370**

**Função**: Garante que o botão de fixar está presente e com estado correto

**Quando alterar**:
- Quando mudar a estrutura HTML do botão de fixar
- Quando alterar classes CSS do botão
- Quando mudar ícones ou tooltips

**Exemplo**:
```javascript
function ensurePinButton(conversationItem, pinned, conversationId) {
    let pinBtn = conversationItem.querySelector('.conversation-item-pin');
    if (!pinBtn) {
        // Criar botão se não existir
    }
    // Atualizar estado
}
```

---

### 8️⃣ **`updateConversationMeta(conversationItem, conv)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7450-7480**

**Função**: Atualiza metadados (pinned, updated_at) de um item

**Quando alterar**:
- Quando adicionar novos atributos `data-*` para metadados
- Quando mudar lógica de pinned/classes

---

### 9️⃣ **`updateConversationTimes()` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7480-7500**

**Função**: Atualiza todos os timestamps relativos na lista ("5min", "Agora", etc)

**Quando alterar**:
- Quando mudar o seletor CSS do elemento de tempo (`.conversation-item-time`)
- Quando mudar formato de exibição de tempo
- Quando mudar atributo `data-timestamp` ou `data-updated-at`

**⚠️ Nota**: Esta função roda automaticamente a cada 30 segundos via `setInterval`

---

### 🔟 **`sortConversationList()` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7500-7530**

**Função**: Reordena a lista por: 1) Pinned, 2) Data (mais recente primeiro)

**Quando alterar**:
- Quando mudar critérios de ordenação
- Quando adicionar novos campos para sorting (prioridade, SLA, etc)

---

### 1️⃣1️⃣ **`moveConversationToTop(conversationId)` - JavaScript**

**Localização**: `views/conversations/index.php` - Linhas **~7530-7560**

**Função**: Move uma conversa específica para o topo da lista

**Quando alterar**:
- Raramente precisa ser alterado (lógica simples de DOM)
- Apenas se mudar a estrutura do container da lista

---

## 🎨 Handlers de Eventos em Tempo Real

### WebSocket/Polling Handlers

**Localização**: `views/conversations/index.php` - Linhas **~8000-8300**

**Funções**:
- `new_message` handler (linha ~8078)
- `conversation_updated` handler (linha ~8197)
- `new_conversation` handler (evento global, linha ~8718)

**Quando alterar**:
- Quando adicionar novos campos que devem ser atualizados em tempo real
- Quando mudar lógica de badges/preview ao receber mensagens
- Quando adicionar novos eventos de WebSocket

**Exemplo de handler `new_message`**:
```javascript
window.wsClient.on('new_message', (data) => {
    // Atualizar preview na lista
    const preview = conversationItem.querySelector('.conversation-item-preview');
    if (preview) {
        preview.textContent = data.message.content.substring(0, 37) + '...';
    }
    
    // Chamar helpers
    ensurePinButton(conversationItem, pinned, data.conversation_id);
    sortConversationList();
});
```

---

## ✅ Checklist de Consistência

Use este checklist ao fazer alterações:

### Estrutura HTML:
- [ ] Renderização inicial PHP atualizada
- [ ] `applyConversationUpdate` com MESMA estrutura
- [ ] `addConversationToList` com MESMA estrutura
- [ ] Classes CSS consistentes em todas as 3 funções
- [ ] Atributos `data-*` presentes em todas as 3

### Dados/Campos:
- [ ] Novos campos adicionados nas 3 funções de renderização
- [ ] `refreshConversationBadges` atualiza os novos campos
- [ ] Handlers de tempo real processam os novos campos

### Elementos Dinâmicos:
- [ ] Botão de fixar: `ensurePinButton` atualizado
- [ ] Timestamps: `updateConversationTimes` funcionando
- [ ] Badges: Lógica em todas as funções de atualização

### Tempo Real:
- [ ] Handlers WebSocket/Polling atualizados
- [ ] `new_message` handler atualiza corretamente
- [ ] `conversation_updated` handler reflete mudanças
- [ ] `new_conversation` handler renderiza nova conversa

### Backend (se necessário):
- [ ] `Conversation::getAll()` retorna novos campos na query SQL
- [ ] `ConversationService::list()` processa corretamente
- [ ] `ConversationController::index()` passa dados para a view

---

## 📝 Exemplo Prático: Adicionar um novo campo

**Cenário**: Adicionar campo "prioridade" na lista de conversas

### Passo 1: Backend
```php
// app/Models/Conversation.php - Adicionar na query SQL
SELECT c.*, c.priority, ...
```

### Passo 2: Renderização Inicial PHP
```php
<!-- views/conversations/index.php (~linha 1350) -->
<div class="conversation-item-priority">
    <?php if ($conv['priority'] === 'high'): ?>
        <i class="ki-duotone ki-triangle text-danger"></i>
    <?php endif; ?>
</div>
```

### Passo 3: `applyConversationUpdate`
```javascript
// views/conversations/index.php (~linha 3950)
html += `
    <div class="conversation-item-priority">
        ${conv.priority === 'high' ? '<i class="ki-duotone ki-triangle text-danger"></i>' : ''}
    </div>
`;
```

### Passo 4: `addConversationToList`
```javascript
// views/conversations/index.php (~linha 8360)
const conversationHtml = `
    <div class="conversation-item-priority">
        ${conv.priority === 'high' ? '<i class="ki-duotone ki-triangle text-danger"></i>' : ''}
    </div>
`;
```

### Passo 5: Atualização em Tempo Real
```javascript
// views/conversations/index.php (~linha 8200)
window.wsClient.on('conversation_updated', (data) => {
    if (data.changes && data.changes.priority) {
        // Atualizar visualmente
        const priorityEl = conversationItem.querySelector('.conversation-item-priority');
        if (priorityEl && data.conversation.priority === 'high') {
            priorityEl.innerHTML = '<i class="ki-duotone ki-triangle text-danger"></i>';
        }
    }
});
```

---

## 🚨 Erros Comuns

### ❌ Erro 1: Estrutura HTML diferente entre PHP e JavaScript
**Problema**: Renderização inicial mostra avatar, mas filtros não  
**Solução**: Garantir que as 3 funções principais (1️⃣, 2️⃣, 3️⃣) tenham HTML idêntico

### ❌ Erro 2: Botão de fixar desaparece após polling
**Problema**: `refreshConversationBadges` não chama `ensurePinButton`  
**Solução**: Sempre chamar `ensurePinButton` após atualizar itens

### ❌ Erro 3: Tempo não atualiza automaticamente
**Problema**: `updateConversationTimes` não está rodando ou seletor CSS errado  
**Solução**: Verificar `setInterval` e seletor `.conversation-item-time`

### ❌ Erro 4: Nova conversa não aparece sem refresh
**Problema**: Handler `new_conversation` não está chamando `addConversationToList`  
**Solução**: Garantir que evento global `realtime:new_conversation` está sendo escutado

### ❌ Erro 5: Avatar não carrega no primeiro acesso
**Problema**: Renderização PHP não verifica `$conv['contact_avatar']`  
**Solução**: Adicionar verificação condicional para mostrar avatar ou iniciais

---

## 📚 Referências Rápidas

### Variáveis Globais Importantes:
- `currentConversationId` - ID da conversa selecionada
- `currentContactAvatar` - Avatar do contato atual
- `lastMessageId` - ID da última mensagem (polling incremental)
- `window.wsClient` - Cliente WebSocket/Polling

### Seletores CSS Importantes:
- `.conversation-item` - Container de cada conversa
- `.conversation-item-name` - Nome do contato (max 25 caracteres)
- `.conversation-item-preview` - Preview da mensagem (max 37 caracteres)
- `.conversation-item-time` - Tempo relativo
- `.conversation-item-badge` - Badge de não lidas
- `.conversation-item-pin` - Botão de fixar
- `[data-conversation-id]` - Atributo para identificar conversa

### Funções Auxiliares Úteis:
- `escapeHtml(text)` - Escapar HTML em JavaScript
- `formatTime(timestamp)` - Formatar timestamp relativo
- `getInitials(name)` - Obter iniciais de um nome

---

## 🔗 Arquivos Relacionados

- `views/conversations/index.php` - **Arquivo principal** (este guia)
- `app/Controllers/ConversationController.php` - Controller de conversas
- `app/Services/ConversationService.php` - Lógica de negócio
- `app/Models/Conversation.php` - Model e queries SQL
- `public/assets/js/realtime-client.js` - Cliente WebSocket/Polling

---

## 📌 Notas Finais

1. **Sempre manter consistência**: As 3 funções principais devem ter HTML idêntico
2. **Testar em 3 cenários**: Carregamento inicial, filtros e tempo real
3. **Limitar textos**: Nome (25 chars), Preview (37 chars)
4. **Verificar avatares**: Sempre ter fallback para iniciais
5. **Atualizar helpers**: `ensurePinButton`, `updateConversationMeta`, etc
6. **Handlers em tempo real**: Atualizar `new_message`, `conversation_updated`, `new_conversation`

---

**Última revisão**: 2025-12-07  
**Responsável pela documentação**: Sistema de Chat Multiatendimento

