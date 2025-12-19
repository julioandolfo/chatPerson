# 🔍 DEBUG - Intenções Desaparecendo ao Reabrir Modal

**Data**: 2025-12-19  
**Status**: 🔧 DEBUG EM ANDAMENTO

---

## 🚨 Problema Reportado

**Sintoma**: Ao salvar um nó de "Atribuir Agente de IA" com intenções configuradas, ao reabrir o modal para edição, as intenções desaparecem.

---

## 🔧 Alterações Implementadas para Debug

### 1. Logs Detalhados no Salvamento

**Arquivo**: `views/automations/show.php` - Linhas 2708-2716

```javascript
// Após merge dos dados
console.log('node.node_data DEPOIS de merge:', node.node_data);

// Log específico para AI Agent
if (node.node_type === "action_assign_ai_agent") {
    console.log('AI Agent - Verificação final:');
    console.log('  ai_branching_enabled:', node.node_data.ai_branching_enabled);
    console.log('  ai_intents:', node.node_data.ai_intents);
    console.log('  ai_max_interactions:', node.node_data.ai_max_interactions);
    console.log('  ai_fallback_node_id:', node.node_data.ai_fallback_node_id);
}
```

### 2. Logs Detalhados ao Abrir Modal

**Arquivo**: `views/automations/show.php` - Linhas 1237-1248

```javascript
function openNodeConfig(nodeId) {
    const node = nodes.find(n => String(n.id) === String(nodeId));
    
    console.log('=== openNodeConfig chamado ===');
    console.log('Node ID:', nodeId);
    console.log('Node Type:', node.node_type);
    console.log('Node Data completo:', JSON.parse(JSON.stringify(node.node_data)));
    
    if (node.node_type === 'action_assign_ai_agent') {
        console.log('AI Agent - Dados ao abrir:');
        console.log('  ai_branching_enabled:', node.node_data.ai_branching_enabled);
        console.log('  ai_intents:', node.node_data.ai_intents);
        console.log('  ai_max_interactions:', node.node_data.ai_max_interactions);
        console.log('  ai_fallback_node_id:', node.node_data.ai_fallback_node_id);
    }
    // ...
}
```

### 3. ID do Nó nos Selects

**Alteração**: Agora os selects mostram o ID do nó junto com o nome

**Antes:**
```
Enviar Mensagem
Condição
```

**Depois:**
```
Enviar Mensagem (ID: node_1234)
Condição (ID: node_5678)
```

**Benefício**: Facilita identificar nós quando há múltiplos com o mesmo nome.

---

## 🧪 Teste Passo a Passo

### Etapa 1: Criar e Salvar Intenções

1. Abra uma automação existente
2. Adicione alguns nós (enviar mensagem, condição, etc) se ainda não tiver
3. Adicione um nó "Atribuir Agente de IA"
4. Clique para editar o nó
5. Marque "Habilitar ramificação baseada em intent"
6. Clique em "Adicionar Intent" 3 vezes
7. Preencha os 3 intents:

**Intent 1:**
- Nome: `status_pedido`
- Descrição: `Cliente perguntando sobre status do pedido`
- Keywords: `pedido, entrega, rastreamento`
- Nó de Destino: Selecione um nó (ex: "Enviar Mensagem (ID: node_123)")

**Intent 2:**
- Nome: `problema_entrega`
- Descrição: `Cliente com problema na entrega`
- Keywords: `problema, atrasado, não chegou`
- Nó de Destino: Selecione outro nó

**Intent 3:**
- Nome: `duvida_produto`
- Descrição: `Cliente com dúvida sobre produto`
- Keywords: `produto, especificação, tamanho`
- Nó de Destino: Selecione outro nó

8. Clique em "Salvar Configuração"

**Console deve mostrar:**
```
Salvando configuração do AI Agent
  ai_branching_enabled raw: on
  branchingEnabled processado: true
  Intent items encontrados: 3
  Intent 0:
    - name: "status_pedido"
    - desc: "Cliente perguntando sobre status do pedido"
    - keywords: [pedido, entrega, rastreamento]
    - target: "node_123"
  Intent 1:
    ...
  Intent 2:
    ...
  Total de intents válidos coletados: 3
  Intents: Array(3)
node.node_data ANTES de merge: {label: "Atribuir Agente de IA", ...}
nodeData coletado do form: {ai_agent_id: "", ai_branching_enabled: true, ai_intents: Array(3), ...}
node.node_data DEPOIS de merge: {label: "Atribuir Agente de IA", ai_branching_enabled: true, ai_intents: Array(3), ...}
AI Agent - Verificação final:
  ai_branching_enabled: true
  ai_intents: Array(3) [
    {intent: "status_pedido", description: "...", keywords: [...], target_node_id: "node_123"},
    {intent: "problema_entrega", ...},
    {intent: "duvida_produto", ...}
  ]
  ai_max_interactions: 5
  ai_fallback_node_id: ""
Configuração salva. Fechando modal...
```

### Etapa 2: Verificar Handles Visuais

9. Após fechar o modal, o nó deve mostrar:
   - 3 bolinhas roxas (handles) na lateral direita
   - Cada uma com emoji 🎯 e texto do intent

**Console deve mostrar:**
```
(Renderização do nó pode gerar logs)
```

### Etapa 3: Reabrir Modal para Edição

10. Clique novamente no nó de "Atribuir Agente de IA"
11. Modal deve abrir

**Console deve mostrar:**
```
=== openNodeConfig chamado ===
Node ID: node_xyz
Node Type: action_assign_ai_agent
Node Data completo: {
  label: "Atribuir Agente de IA",
  ai_agent_id: "",
  ai_branching_enabled: true,
  ai_intents: [
    {intent: "status_pedido", description: "...", keywords: [...], target_node_id: "node_123"},
    {intent: "problema_entrega", ...},
    {intent: "duvida_produto", ...}
  ],
  ai_max_interactions: 5,
  ai_fallback_node_id: "",
  connections: []
}
AI Agent - Dados ao abrir:
  ai_branching_enabled: true
  ai_intents: Array(3)
  ai_max_interactions: 5
  ai_fallback_node_id: ""
```

12. Aguarde o modal carregar completamente

**Console deve continuar:**
```
Timeout executado - populando fallback e intents
ai_fallback_node_id: ""
ai_intents: Array(3) [...]
populateAIIntents chamado com: Array(3)
Lista limpa
Carregando 3 intent(s)
Adicionando intent 0: {intent: "status_pedido", ...}
populateAIIntentTargetNodes: Total de nós disponíveis: 5
populateAIIntentTargetNodes: Nós adicionados ao select: 4
Preenchendo valores do intent 0
  - Intent name: status_pedido
  - Description: Cliente perguntando sobre status do pedido
  - Keywords: pedido, entrega, rastreamento
  - Target node: node_123
Adicionando intent 1: ...
Adicionando intent 2: ...
populateAIIntents concluído
```

13. Verificar visualmente:
    - ✅ Checkbox "Habilitar ramificação" deve estar marcado
    - ✅ Container de intents deve estar visível
    - ✅ Devem aparecer 3 cards de intent
    - ✅ Cada card deve ter:
      - Nome do intent preenchido
      - Descrição preenchida
      - Keywords preenchidas
      - Select de nó com opção selecionada (mostrando ID do nó)

---

## 📊 Pontos de Verificação

### ✅ Se Tudo Estiver OK:

**No salvamento:**
- [x] Console mostra "Total de intents válidos coletados: 3"
- [x] Console mostra "AI Agent - Verificação final" com ai_intents: Array(3)
- [x] Handles visuais aparecem no nó

**Ao reabrir:**
- [x] Console mostra "Node Data completo" com ai_intents: Array(3)
- [x] Console mostra "Carregando 3 intent(s)"
- [x] Console mostra "Preenchendo valores" para cada intent
- [x] Cards de intent aparecem no modal
- [x] Dados estão preenchidos corretamente

### ❌ Se Dados Estiverem Desaparecendo:

#### Cenário A: Dados não são salvos
**Sintoma**: Console mostra "ai_intents: []" ou "ai_intents: undefined" no salvamento

**Possível causa**:
- Checkbox não está sendo processado
- Intents não estão sendo coletados do DOM

**Verificar**:
- Log "Intent items encontrados" - deve ser > 0
- Log "Total de intents válidos coletados" - deve ser > 0

#### Cenário B: Dados são salvos mas não carregam
**Sintoma**: Console mostra ai_intents correto no salvamento, mas vazio ao abrir

**Possível causa**:
- Dados não estão sendo persistidos no array `nodes`
- Re-render do nó está limpando os dados

**Verificar**:
- Log "openNodeConfig" - deve mostrar ai_intents com dados
- Verificar se `window.nodes` está atualizado após salvar

#### Cenário C: Dados carregam mas não preenchem o DOM
**Sintoma**: Console mostra dados corretos, mas campos no modal estão vazios

**Possível causa**:
- Timeout insuficiente
- Elementos não encontrados no DOM
- Seletores incorretos

**Verificar**:
- Log "populateAIIntents" - deve mostrar "Carregando X intent(s)"
- Log "Preenchendo valores" - deve aparecer para cada intent
- Erros como "Item X não encontrado no DOM"

---

## 🔧 Ações Corretivas Possíveis

### Se Cenário A:
```javascript
// Adicionar validação antes de coletar intents
console.log('Container visível?', container.style.display);
console.log('Checkbox marcado?', checkbox.checked);
```

### Se Cenário B:
```javascript
// Verificar se window.nodes está sendo atualizado
console.log('window.nodes antes:', window.nodes.find(n => n.id === nodeId));
// ... salvar ...
console.log('window.nodes depois:', window.nodes.find(n => n.id === nodeId));
```

### Se Cenário C:
```javascript
// Aumentar timeout ou verificar DOM
setTimeout(() => {
    const list = document.getElementById('ai_intents_list');
    console.log('Lista existe?', !!list);
    console.log('Lista children:', list?.children.length);
}, 300); // Aumentar para 300ms
```

---

## 📝 Próximos Passos

1. **Executar teste completo** seguindo o passo a passo acima
2. **Copiar TODOS os logs do console** e enviar
3. **Tirar prints** mostrando:
   - Modal com intents preenchidos antes de salvar
   - Nó com handles visuais após salvar
   - Modal ao reabrir (com intents ou sem)
4. **Informar qual cenário** (A, B ou C) está acontecendo

Com essas informações detalhadas, poderemos identificar exatamente onde os dados estão se perdendo.

