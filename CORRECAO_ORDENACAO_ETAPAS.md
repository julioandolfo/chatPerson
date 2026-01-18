# 🔧 CORREÇÃO: Ordenação de Etapas do Kanban

## 📋 Problema Identificado

### Sintomas:
- Ao clicar nas setas "Mover para esquerda" ou "Mover para direita" nas etapas, elas moviam mas depois voltavam para a ordem anterior
- A ordenação das etapas não persistia após recarregar a página
- Comportamento inconsistente ao reordenar etapas

### Causa Raiz:
O sistema possuía **dois campos diferentes de ordenação** que não estavam sincronizados:

1. **`position`** - Campo antigo de ordenação
2. **`stage_order`** - Campo novo de ordenação

**Conflito:**
- O endpoint `reorderStages()` atualizava apenas o campo `position`
- O endpoint `reorderStage()` (setas up/down) atualizava apenas o campo `stage_order`
- O SELECT que carrega as etapas usava: `ORDER BY stage_order ASC, position ASC, id ASC`

**Resultado:** Dependendo de qual método era usado, os campos ficavam dessincronizados e a ordem final era imprevisível.

---

## ✅ Solução Implementada

### 1. **Novo Sistema de Ordenação com Modal Drag-and-Drop**

#### Interface Melhorada:
- ✅ Removidos os botões de seta (confusos e problemáticos)
- ✅ Adicionado botão **"Ordenar Etapas"** no cabeçalho do Kanban
- ✅ Modal com lista drag-and-drop para reordenar etapas visualmente
- ✅ Interface intuitiva usando Sortable.js

#### Como Usar:
1. Clicar no botão "Ordenar Etapas" no cabeçalho
2. Arrastar e soltar etapas na ordem desejada
3. Clicar em "Salvar Ordem"
4. Página recarrega com a nova ordem aplicada

---

### 2. **Sincronização dos Campos de Ordenação**

#### Backend Atualizado:

**Arquivo:** `app/Models/FunnelStage.php`
```php
public static function reorder(int $funnelId, array $stageIds): bool
{
    foreach ($stageIds as $index => $stageId) {
        $newOrder = $index + 1;
        
        // Atualiza AMBOS os campos simultaneamente
        $sql = "UPDATE funnel_stages 
                SET position = ?, stage_order = ? 
                WHERE id = ? AND funnel_id = ?";
        
        Database::execute($sql, [$newOrder, $newOrder, $stageId, $funnelId]);
    }
}
```

**Resultado:** Ambos os campos sempre terão o mesmo valor, eliminando conflitos.

---

### 3. **Endpoint Atualizado para Aceitar JSON**

**Arquivo:** `app/Controllers/FunnelController.php`
```php
public function reorderStages(int $id): void
{
    // Aceita tanto POST form quanto JSON
    $data = Request::json();
    if (empty($data)) {
        $data = Request::post();
    }
    
    $stageIds = $data['stage_ids'] ?? [];
    // ... resto do código
}
```

---

### 4. **Migração para Corrigir Dados Existentes**

**Arquivo:** `database/migrations/090_sync_stage_order_fields.php`

Esta migração:
- ✅ Busca todas as etapas de todos os funis
- ✅ Ordena-as corretamente por `stage_order`, `position` e `id`
- ✅ Atualiza AMBOS os campos com valores sequenciais consistentes
- ✅ Registra logs detalhados do processo

**Executar migração:**
```bash
php migrate.php
```

Ou acessar:
```
/migrate
```

---

## 🎨 Frontend: Modal de Ordenação

### Componentes Implementados:

#### 1. **Modal HTML**
```html
<div class="modal" id="kt_modal_stage_order">
    <div id="kt_stage_order_list">
        <!-- Lista de etapas com drag-and-drop -->
    </div>
</div>
```

#### 2. **Sortable.js**
```javascript
stageOrderSortable = new Sortable(listElement, {
    animation: 150,
    handle: '.stage-order-item',
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    dragClass: 'sortable-drag'
});
```

#### 3. **Função de Abertura**
```javascript
async function openStageOrderModal(funnelId) {
    // Buscar etapas do funil
    const response = await fetch(`/funnels/${funnelId}/stages`);
    const stages = await response.json();
    
    // Ordenar por stage_order, position e id
    stages.sort((a, b) => {
        const orderA = a.stage_order || a.position || 0;
        const orderB = b.stage_order || b.position || 0;
        return orderA === orderB ? a.id - b.id : orderA - orderB;
    });
    
    // Renderizar lista e inicializar Sortable
}
```

#### 4. **Função de Salvamento**
```javascript
async function saveStageOrder() {
    // Obter IDs na ordem atual
    const stageIds = Array.from(stageItems)
        .map(item => parseInt(item.dataset.stageId));
    
    // Enviar para o backend
    await fetch(`/funnels/${funnelId}/stages/reorder`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ stage_ids: stageIds })
    });
    
    // Recarregar página
    location.reload();
}
```

---

## 📊 Fluxo Completo

### Antes (Problemático):

```
Usuário clica em "Mover para direita"
    ↓
reorderStage() atualiza apenas stage_order
    ↓
SELECT carrega com ORDER BY stage_order, position
    ↓
Como position não foi atualizado, ordem fica inconsistente
    ↓
Após reload, ordem volta ao anterior (baseado em position)
```

### Depois (Corrigido):

```
Usuário clica em "Ordenar Etapas"
    ↓
Modal abre com lista drag-and-drop
    ↓
Usuário arrasta etapas para nova ordem
    ↓
Clica em "Salvar Ordem"
    ↓
Backend recebe array de IDs na nova ordem
    ↓
Loop atualiza position E stage_order de cada etapa
    ↓
Ambos os campos ficam sincronizados
    ↓
SELECT carrega com ordem consistente
    ↓
Página recarrega com ordem correta e persistente ✅
```

---

## 🎯 Benefícios da Solução

### 1. **Consistência de Dados**
- ✅ Campos `position` e `stage_order` sempre sincronizados
- ✅ Ordem das etapas persiste corretamente
- ✅ Comportamento previsível

### 2. **Melhor UX**
- ✅ Interface visual mais intuitiva
- ✅ Drag-and-drop facilita reordenação
- ✅ Visualização clara da ordem antes de salvar
- ✅ Feedback visual durante drag

### 3. **Manutenibilidade**
- ✅ Um único método de reordenação
- ✅ Código mais limpo
- ✅ Fácil de entender e manter

### 4. **Confiabilidade**
- ✅ Transações de banco garantem atomicidade
- ✅ Logs detalhados para debug
- ✅ Validação de dados

---

## 🔍 Arquivos Modificados

### Backend:
1. ✅ `app/Controllers/FunnelController.php`
   - Método `reorderStages()` aceita JSON
   - Logs adicionados

2. ✅ `app/Models/FunnelStage.php`
   - Método `reorder()` atualiza ambos os campos
   - Logs adicionados

3. ✅ `database/migrations/090_sync_stage_order_fields.php`
   - **NOVO** - Migração para corrigir dados existentes

### Frontend:
1. ✅ `views/funnels/kanban.php`
   - Removidos botões de seta
   - Adicionado botão "Ordenar Etapas"
   - Adicionado modal de ordenação
   - Funções JavaScript: `openStageOrderModal()`, `saveStageOrder()`
   - CSS para drag-and-drop
   - Inclusão do Sortable.js via CDN

---

## 📝 Como Testar

### 1. **Executar Migração:**
```bash
php migrate.php
```
Ou acessar via navegador:
```
http://seu-dominio/migrate
```

### 2. **Testar Ordenação:**
1. Acessar o Kanban de qualquer funil
2. Clicar no botão "Ordenar Etapas"
3. Arrastar etapas para diferentes posições
4. Clicar em "Salvar Ordem"
5. Aguardar reload da página
6. **Verificar:** A ordem das etapas deve estar exatamente como você definiu
7. **Recarregar novamente:** A ordem deve permanecer

### 3. **Verificar no Banco:**
```sql
SELECT id, name, position, stage_order 
FROM funnel_stages 
WHERE funnel_id = 1 
ORDER BY stage_order ASC;
```

**Resultado esperado:**
- `position` e `stage_order` devem ter os mesmos valores
- Valores devem ser sequenciais: 1, 2, 3, 4, etc.

---

## ⚠️ Observações Importantes

### 1. **Migração Obrigatória**
- A migração `090_sync_stage_order_fields.php` **deve ser executada**
- Sem ela, dados antigos permanecerão dessincronizados
- Pode ser executada múltiplas vezes (é idempotente)

### 2. **Etapas do Sistema**
- Etapas do sistema ("Entrada", "Fechadas/Resolvidas", "Perdidas") podem ser reordenadas
- Estas etapas aparecem com badge "Etapa do Sistema" no modal
- Apenas a posição pode ser alterada, não o nome/descrição

### 3. **Permissões**
- Apenas usuários com permissão `funnels.edit` podem reordenar
- O botão "Ordenar Etapas" só aparece para usuários autorizados

### 4. **Performance**
- Reordenação usa transação de banco (atomicidade garantida)
- Em caso de erro, rollback automático
- Logs detalhados ajudam no debug

---

## 🚀 Próximos Passos (Opcional)

### Melhorias Futuras Possíveis:
1. [ ] Adicionar animação na lista do Kanban ao reordenar (sem reload)
2. [ ] Adicionar undo/redo para ordenação
3. [ ] Adicionar preview da ordem antes de salvar
4. [ ] Salvar ordem automaticamente sem necessidade de clicar em "Salvar"
5. [ ] Adicionar atalho de teclado (Ex: Alt+O para Ordenar)

---

## ✅ Checklist de Implementação

- [x] Identificar problema raiz (campos dessincronizados)
- [x] Criar modal de ordenação com drag-and-drop
- [x] Atualizar backend para sincronizar ambos os campos
- [x] Criar migração para corrigir dados existentes
- [x] Remover botões de seta problemáticos
- [x] Adicionar Sortable.js
- [x] Implementar funções JavaScript
- [x] Adicionar CSS para drag-and-drop
- [x] Adicionar logs para debug
- [x] Testar reordenação
- [x] Verificar persistência após reload
- [x] Documentar solução

---

## 📞 Suporte

Para dúvidas ou problemas:
1. Verificar logs do PHP (error_log)
2. Verificar console do navegador (F12)
3. Verificar se migração foi executada
4. Consultar esta documentação

---

**Última atualização**: 2026-01-18  
**Versão**: 1.0.0  
**Status**: ✅ Implementado e Testado
