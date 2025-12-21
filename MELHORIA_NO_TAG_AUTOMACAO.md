# 🏷️ Melhoria do Nó de Tag nas Automações

## 📋 Problema Anterior

O nó "Adicionar Tag" nas automações tinha várias limitações:

### ❌ Antes

1. **Input de texto livre** - O usuário digitava o nome da tag manualmente
2. **Sem validação** - Não verificava se a tag existia no sistema
3. **Inconsistência** - Nome digitado poderia não coincidir com tags existentes
4. **Sem visualização** - Não mostrava as tags disponíveis
5. **Apenas adicionar** - Não tinha opção de remover tags
6. **Sem cores** - Não exibia as cores das tags para facilitar identificação

### 🔧 Código Anterior

```html
<input type="text" name="tag" placeholder="Nome da tag" required />
```

**Resultado:** Usuário tinha que lembrar/adivinhar o nome exato da tag!

## ✅ Solução Implementada

Agora o nó de tag está **integrado ao sistema de tags** (`/tags`):

### ✨ Melhorias

1. **✅ Select2 com tags do sistema** - Lista todas as tags criadas em `/tags`
2. **✅ Busca em tempo real** - Pesquisa por nome da tag
3. **✅ Cores visuais** - Mostra as cores das tags (badges coloridas)
4. **✅ Adicionar OU Remover** - Suporte para ambas as ações
5. **✅ Validação automática** - Garante que a tag existe
6. **✅ Carregamento dinâmico** - Busca tags via AJAX da API
7. **✅ Edição preservada** - Ao editar nó existente, tag e ação são mantidas

### 🎨 Interface Nova

```html
<select name="tag_id" id="kt_tag_id" data-control="select2">
  <option value="">Selecione uma tag...</option>
  <!-- Tags carregadas dinamicamente via AJAX -->
</select>

<select name="tag_action" id="kt_tag_action">
  <option value="add">Adicionar Tag</option>
  <option value="remove">Remover Tag</option>
</select>
```

**Resultado:** Interface moderna com Select2, cores e ações!

## 🚀 Implementação Técnica

### 1. Frontend (views/automations/show.php)

#### Carregamento das Tags

```javascript
// Buscar tags do sistema via AJAX
fetch('/tags/all')
  .then(response => response.json())
  .then(data => {
    if (data.success && data.tags) {
      data.tags.forEach(tag => {
        const option = document.createElement('option');
        option.value = tag.id;  // ✅ Usa ID ao invés do nome
        option.textContent = tag.name;
        option.setAttribute('data-color', tag.color);
        tagSelect.appendChild(option);
      });
      
      // Inicializar Select2 com template customizado
      $(tagSelect).select2({
        templateResult: formatTag,  // Badge colorida
        templateSelection: formatTag
      });
    }
  });
```

#### Template Customizado (Badge Colorida)

```javascript
function formatTag(tag) {
  if (!tag.element) return tag.text;
  
  const color = tag.element.getAttribute('data-color');
  if (color) {
    return $('<span class="badge" style="background-color: ' + color + '20; color: ' + color + '; border: 1px solid ' + color + ';">' + tag.text + '</span>');
  }
  return tag.text;
}
```

#### Carregar Dados Salvos (Edição)

```javascript
// Ao editar nó existente
const savedTagId = currentNodeRefForModal?.node_data?.tag_id || null;
const savedTagAction = currentNodeRefForModal?.node_data?.tag_action || 'add';

// Após carregar tags, selecionar a salva
if (savedTagId) {
  $(tagSelect).val(savedTagId).trigger('change');
}

// Selecionar ação salva
if (tagActionSelect && savedTagAction) {
  tagActionSelect.value = savedTagAction;
}
```

### 2. Backend (app/Services/AutomationService.php)

#### Método `executeSetTag()` Melhorado

```php
private static function executeSetTag(array $nodeData, int $conversationId, ?int $executionId = null): void
{
    $tagId = $nodeData['tag_id'] ?? null;  // ✅ Usa tag_id
    $tagAction = $nodeData['tag_action'] ?? 'add';  // ✅ Suporta add/remove
    
    if (!$tagId) {
        Logger::automation("⚠️ Tag ID não informado, pulando ação");
        return;
    }

    try {
        // Verificar se tag existe
        $tag = Tag::find($tagId);
        if (!$tag) {
            throw new Exception("Tag ID {$tagId} não encontrada");
        }

        Logger::automation("Tag: {$tag['name']} (ID: {$tagId}), Ação: {$tagAction}");
        
        // Executar ação
        if ($tagAction === 'remove') {
            // ✅ NOVO: Remover tag
            $sql = "DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?";
            Database::execute($sql, [$conversationId, $tagId]);
            Logger::automation("✅ Tag '{$tag['name']}' removida");
        } else {
            // ✅ Adicionar tag (padrão)
            $sql = "INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)";
            Database::execute($sql, [$conversationId, $tagId]);
            Logger::automation("✅ Tag '{$tag['name']}' adicionada");
        }
    } catch (Exception $e) {
        Logger::automation("❌ Erro: " . $e->getMessage());
        throw $e;
    }
}
```

### 3. API Utilizada

**Rota:** `GET /tags/all`

**Controller:** `TagController::getAll()`

**Resposta:**
```json
{
  "success": true,
  "tags": [
    {
      "id": 1,
      "name": "VIP",
      "color": "#ff0000"
    },
    {
      "id": 2,
      "name": "Suporte",
      "color": "#00ff00"
    }
  ]
}
```

## 📊 Estrutura de Dados

### Dados Salvos no Nó

```json
{
  "node_type": "action_set_tag",
  "node_data": {
    "label": "Adicionar Tag VIP",
    "tag_id": 1,
    "tag_action": "add",
    "connections": [...]
  }
}
```

### Campos do Formulário

| Campo | Nome | Tipo | Descrição |
|-------|------|------|-----------|
| Tag | `tag_id` | select (select2) | ID da tag do sistema |
| Ação | `tag_action` | select | `add` ou `remove` |

## 🎯 Casos de Uso

### Caso 1: Adicionar Tag Automaticamente

**Cenário:** Cliente menciona palavra "urgente"

**Automação:**
```
Trigger: Mensagem recebida contém "urgente"
↓
Ação: Adicionar Tag "Urgente" (tag_id=5, action=add)
↓
Ação: Notificar supervisor
```

**Resultado:** Conversa marcada com tag "Urgente" automaticamente!

### Caso 2: Remover Tag Após Resolução

**Cenário:** Conversa foi resolvida

**Automação:**
```
Trigger: Conversa movida para "Resolvidas"
↓
Ação: Remover Tag "Pendente" (tag_id=3, action=remove)
↓
Ação: Adicionar Tag "Resolvida" (tag_id=8, action=add)
```

**Resultado:** Tags atualizadas conforme o fluxo!

### Caso 3: Categorização por Departamento

**Cenário:** Atribuição a departamento específico

**Automação:**
```
Trigger: Conversa atribuída a "Setor Financeiro"
↓
Ação: Adicionar Tag "Financeiro" (tag_id=10, action=add)
↓
Ação: Enviar template de boas-vindas
```

**Resultado:** Conversa categorizada automaticamente!

## ✨ Benefícios

### Para o Usuário

1. **✅ Interface intuitiva** - Select visual com cores
2. **✅ Sem erros de digitação** - Seleciona de lista validada
3. **✅ Busca rápida** - Select2 com pesquisa
4. **✅ Visualização clara** - Badges coloridas
5. **✅ Mais controle** - Pode adicionar OU remover

### Para o Sistema

1. **✅ Consistência de dados** - Apenas IDs válidos
2. **✅ Validação automática** - Tag deve existir
3. **✅ Logs detalhados** - Mostra qual tag e ação
4. **✅ Integração real** - Usa sistema de tags oficial
5. **✅ Manutenibilidade** - Código mais limpo

### Para Automações

1. **✅ Mais flexibilidade** - Add e Remove
2. **✅ Workflows complexos** - Gerenciar tags dinamicamente
3. **✅ Debug facilitado** - Logs mostram tag por nome e ID
4. **✅ Escalabilidade** - Carrega todas as tags disponíveis
5. **✅ Confiabilidade** - Validação em frontend e backend

## 🧪 Como Testar

### Teste 1: Criar Nova Automação com Tag

1. Acesse **Automações** → Criar nova
2. Adicione nó **"Adicionar Tag"**
3. Clique para configurar
4. ✅ Deve aparecer **select com todas as tags** do sistema
5. ✅ Tags devem ter **cores visuais** (badges)
6. Selecione uma tag e escolha "Adicionar"
7. Salve a automação
8. ✅ Ao reabrir, tag e ação devem estar **selecionadas**

### Teste 2: Remover Tag via Automação

1. Crie automação: "Conversa resolvida"
2. Adicione nó **"Adicionar Tag"**
3. Selecione tag "Pendente"
4. Escolha ação **"Remover Tag"**
5. Salve e teste resolvendo uma conversa
6. ✅ Tag "Pendente" deve ser **removida**

### Teste 3: Tags com Cores

1. Em `/tags`, crie tags com cores diferentes:
   - VIP (vermelho #ff0000)
   - Normal (azul #0000ff)
   - Urgente (laranja #ff9900)
2. Crie automação com nó de tag
3. ✅ Todas as tags devem aparecer com suas **cores no select**
4. ✅ Tag selecionada deve mostrar **badge colorida**

### Teste 4: Editar Nó Existente

1. Crie automação com nó de tag (tag_id=5, action=add)
2. Salve e saia
3. Reabra a automação
4. Clique para editar o nó de tag
5. ✅ Tag ID 5 deve estar **selecionada**
6. ✅ Ação "Adicionar" deve estar **selecionada**

## 📝 Logs de Debug

Ao executar, os logs mostram:

```log
[hora] Executando: definir tag
[hora] Tag: VIP (ID: 1), Ação: add
[hora] ✅ Tag 'VIP' adicionada à conversa 325
```

Ou ao remover:

```log
[hora] Executando: definir tag
[hora] Tag: Pendente (ID: 3), Ação: remove
[hora] ✅ Tag 'Pendente' removida da conversa 325
```

## 🔧 Arquivos Modificados

```
views/
└── automations/
    └── show.php  ✅ Select2 + AJAX + Template colorido

app/
└── Services/
    └── AutomationService.php  ✅ Suporte para add/remove
```

## 📚 Relacionado

- Sistema de Tags: `/tags`
- API de Tags: `TagController::getAll()`
- Model: `App\Models\Tag`
- Service: `App\Services\TagService`

## 🎉 Resultado Final

**Antes:** Input de texto solto, apenas adicionar, sem validação

**Depois:** Select2 integrado, add/remove, cores visuais, validação completa! 🚀

---

✅ **Melhoria implementada com sucesso!**

*Data: 21/12/2024*
*Versão: 1.0*

