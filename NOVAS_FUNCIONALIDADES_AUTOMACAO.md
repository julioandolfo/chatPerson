# 🚀 Novas Funcionalidades - Sistema de Automações

## Data: 18/12/2025

---

## ✨ Funcionalidades Implementadas

### 1. **Handles Múltiplos para Chatbot Menu** 🎯

Agora, quando você configura um chatbot do tipo "Menu com Opções", cada opção ganha seu próprio **handle de saída** (bolinha de conexão) na lateral direita do nó.

#### **Antes:**
- Um único handle de saída no nó
- Não era claro qual opção conectava a qual nó
- Difícil de visualizar o fluxo

#### **Depois:**
- Um handle por opção na lateral direita
- Cada opção pode conectar a um nó diferente
- Visual claro e intuitivo

#### **Como funciona:**

1. **Criar nó Chatbot:**
   - Adicione um nó "Chatbot"
   - Configure como "Menu com Opções"

2. **Adicionar opções:**
   - `1 - Suporte Técnico`
   - `2 - Vendas`
   - `3 - Financeiro`

3. **Visualizar handles:**
   - Cada opção aparece listada no nó
   - Cada uma tem uma **bolinha de conexão** na direita
   - Arraste de cada bolinha para conectar ao nó desejado

4. **Resultado:**
   ```
   ┌─────────────────────┐
   │  🤖 Chatbot         │
   │  Menu               │
   │─────────────────────│
   │ 1 - Suporte     ○───┼─→ Nó A
   │ 2 - Vendas      ○───┼─→ Nó B
   │ 3 - Financeiro  ○───┼─→ Nó C
   └─────────────────────┘
   ```

#### **Armazenamento:**
Cada conexão agora armazena `option_index`:
```json
{
  "target_node_id": 5,
  "type": "next",
  "option_index": 0
}
```

---

### 2. **Logs Detalhados do Backend** 📋

Sistema de logging completo para depuração de automações.

#### **Locais de Log:**
- **Arquivo:** `storage/logs/automation.log`
- **Visualizador Web:** `/view-automation-logs.php` (cria arquivo automaticamente se não existir)

#### **O que é logado:**
```
[2025-12-18 16:30:45] ========================================
[2025-12-18 16:30:45] saveLayout - INÍCIO - Automation ID: 1
[2025-12-18 16:30:45] saveLayout - Método: POST
[2025-12-18 16:30:45] saveLayout - Content-Type: application/json
[2025-12-18 16:30:45] saveLayout - Tamanho do input: 2547 bytes
[2025-12-18 16:30:45] saveLayout - Raw input (primeiros 1000 chars): {"nodes":[...]}
[2025-12-18 16:30:45] saveLayout - Quantidade de nós recebidos: 5
[2025-12-18 16:30:45] saveLayout - Primeiro nó: {"id":1,"node_type":"trigger",...}
[2025-12-18 16:30:45] saveLayout - Atualizando nó existente: 1
[2025-12-18 16:30:45] saveLayout - Atualizando nó existente: 2
[2025-12-18 16:30:45] saveLayout - Criando novo nó (ID recebido: node_temp_123)
[2025-12-18 16:30:45] saveLayout - Novo nó criado com ID: 6
[2025-12-18 16:30:45] saveLayout - Deletando nós: [4]
[2025-12-18 16:30:45] saveLayout - Layout salvo com sucesso. Total de nós: 5
[2025-12-18 16:30:45] saveLayout - IDs dos nós salvos: [1,2,3,5,6]
```

#### **Console do Navegador:**
Logs detalhados em tempo real (F12):
```
=== saveLayout CHAMADO ===
saveLayout - Usando window.nodes
saveLayout - Array nodes antes de processar: [...]
saveLayout - Total de nós no array: 5
saveLayout - IDs dos nós que serão enviados: [1, 2, 3, 5, 6]
Salvando configuração do chatbot, tipo: menu
Opções combinadas: [{text: "1 - Suporte", target_node_id: null}, ...]
Conexão criada: {target_node_id: "5", type: "next", option_index: 0}
✅ Layout salvo com sucesso!
```

---

### 3. **Salvar e Carregar Opções do Chatbot** 💾

Sistema completo de persistência para configurações de chatbot.

#### **Ao Salvar:**
1. Captura texto de cada opção
2. Captura target_node_id (se conectado)
3. Armazena em `node_data.chatbot_options[]`
4. Preserva conexões existentes
5. Atualiza visualização do nó

#### **Ao Carregar:**
1. Detecta tipo de chatbot
2. Reconstrói lista de opções
3. Preenche inputs com textos
4. Preenche selects com targets
5. Popular handles múltiplos no diagrama

#### **Estrutura de Dados:**
```json
{
  "node_type": "action_chatbot",
  "node_data": {
    "chatbot_type": "menu",
    "chatbot_message": "Olá! Como posso ajudar?",
    "chatbot_options": [
      { "text": "1 - Suporte Técnico", "target_node_id": "2" },
      { "text": "2 - Vendas", "target_node_id": "3" },
      { "text": "3 - Financeiro", "target_node_id": null }
    ],
    "connections": [
      { "target_node_id": "2", "type": "next", "option_index": 0 },
      { "target_node_id": "3", "type": "next", "option_index": 1 }
    ]
  }
}
```

---

### 4. **Deletar Nós com Persistência** 🗑️

Sistema aprimorado para deletar nós do diagrama.

#### **Fluxo:**
1. Usuário clica no botão de deletar
2. Confirma ação
3. **Frontend:**
   - Remove conexões que apontam para o nó
   - Remove nó do array `nodes`
   - Atualiza `window.nodes`
   - Remove elemento do DOM
   - Renderiza conexões
   - Logs detalhados no console

4. **Ao Salvar Layout:**
   - Backend compara `oldNodeIds` vs `sentNodeIds`
   - Deleta nós que não foram enviados
   - Log: `saveLayout - Deletando nós: [4]`

#### **Logs Esperados:**
```
deleteNode - Deletando nó: 4
deleteNode - Array antes: 5 [...]
deleteNode - Array depois: 4 [...]
deleteNode - window.nodes atualizado: 4
=== saveLayout CHAMADO ===
saveLayout - IDs dos nós que serão enviados: [1, 2, 3, 5]
[BACKEND] saveLayout - Deletando nós: [4]
✅ Layout salvo com sucesso!
```

---

## 🧪 Como Testar Tudo

### **Teste 1: Handles Múltiplos de Chatbot**

1. **Criar automação:**
   - Vá em Automações
   - Crie ou edite uma automação

2. **Adicionar chatbot:**
   - Arraste "Chatbot" para o canvas
   - Clique na engrenagem (⚙️)
   - Selecione "Menu com Opções"

3. **Configurar opções:**
   - Mensagem: "Escolha uma opção:"
   - Opção 1: "1 - Suporte"
   - Opção 2: "2 - Vendas"
   - Opção 3: "3 - Financeiro"
   - Clique em "Salvar"

4. **Visualizar handles:**
   - O nó deve mostrar as 3 opções
   - Cada opção tem uma bolinha (○) na direita

5. **Conectar opções:**
   - Adicione 3 nós "Enviar Mensagem" (A, B, C)
   - Arraste da bolinha da "Opção 1" até o nó A
   - Arraste da bolinha da "Opção 2" até o nó B
   - Arraste da bolinha da "Opção 3" até o nó C

6. **Salvar:**
   - Clique em "Salvar Layout"
   - Deve aparecer: "✅ Layout salvo com sucesso!"

7. **Verificar:**
   - Recarregue a página
   - As conexões devem persistir
   - Cada opção deve estar conectada ao nó correto

---

### **Teste 2: Deletar Nós**

1. **Abra o console** (F12)
2. **Delete um nó:**
   - Clique no ícone de lixeira em um nó
   - Confirme a ação
   - Veja logs no console:
     ```
     deleteNode - Deletando nó: X
     deleteNode - Array antes: Y
     deleteNode - Array depois: Z
     ```

3. **Salvar layout:**
   - Clique em "Salvar Layout"
   - Veja logs no console:
     ```
     saveLayout - IDs dos nós que serão enviados: [...]
     ```

4. **Verificar backend:**
   - Acesse `/view-automation-logs.php`
   - Deve mostrar: `saveLayout - Deletando nós: [X]`

5. **Recarregar página:**
   - O nó deletado não deve aparecer

---

### **Teste 3: Persistência de Opções**

1. **Configure chatbot menu** (como Teste 1)
2. **Salve o layout**
3. **Recarregue a página**
4. **Abra configuração do chatbot:**
   - Clique na engrenagem (⚙️)
   - As opções devem estar preenchidas
   - Os targets devem estar selecionados

5. **Edite uma opção:**
   - Mude "1 - Suporte" para "1 - Suporte Premium"
   - Salve
   - O nó deve atualizar: `Menu (3 opções)`

6. **Salve o layout**
7. **Recarregue e verifique:**
   - A opção editada deve persistir

---

## 🔍 Verificar Logs

### **Console do Navegador (F12):**

1. Abra a aba **Console**
2. Realize ações (salvar, deletar, configurar)
3. Procure por:
   - `=== saveLayout CHAMADO ===`
   - `deleteNode -`
   - `Salvando configuração do chatbot`
   - `Opções combinadas:`
   - `Conexão criada:`

### **Logs do Backend:**

1. Acesse: **`/view-automation-logs.php`**
2. Clique em "🔄 Atualizar" após cada ação
3. Procure por:
   - `saveLayout - INÍCIO`
   - `saveLayout - Quantidade de nós recebidos`
   - `saveLayout - Atualizando nó existente`
   - `saveLayout - Criando novo nó`
   - `saveLayout - Deletando nós`
   - `saveLayout - Layout salvo com sucesso`

---

## 🎨 Aparência dos Handles

### **Nó Normal:**
```
┌───────────────┐
│   ⚙️ Ação     │  ← Handle de entrada (topo)
│               │
└───────────────┘
        ○           ← Handle de saída (base)
```

### **Chatbot Menu:**
```
        ○           ← Handle de entrada (topo)
┌───────────────┐
│ 🤖 Chatbot    │
│ Menu          │
│───────────────│
│ 1 - Suporte ○─┼─→ (Handle específico)
│ 2 - Vendas  ○─┼─→ (Handle específico)
│ 3 - Fin.    ○─┼─→ (Handle específico)
└───────────────┘
```

---

## 📊 Estrutura de Conexões

### **Conexão Normal:**
```json
{
  "target_node_id": 5,
  "type": "next"
}
```

### **Conexão de Opção de Chatbot:**
```json
{
  "target_node_id": 5,
  "type": "next",
  "option_index": 0
}
```

O `option_index` indica qual opção do menu está conectada:
- `0` = Primeira opção
- `1` = Segunda opção
- `2` = Terceira opção
- etc.

---

## ⚠️ Possíveis Problemas

### **1. "Nenhum log encontrado"**
- **Causa:** Diretório `storage/logs/` não existe
- **Solução:** Acesse `/view-automation-logs.php` (cria automaticamente)
- **Ou:** Execute manualmente:
  ```powershell
  New-Item -ItemType Directory -Path storage\logs -Force
  New-Item -ItemType File -Path storage\logs\automation.log -Force
  ```

### **2. Handles não aparecem**
- **Causa:** Opções não foram salvas no `node_data`
- **Solução:** 
  - Abra console (F12)
  - Configure chatbot
  - Veja logs: `Opções combinadas:`
  - Se vazio, verifique se preencheu os inputs

### **3. Conexões não persistem**
- **Causa:** Layout não foi salvo, ou erro no backend
- **Solução:**
  - Salve o layout
  - Verifique `/view-automation-logs.php`
  - Procure por erros ou `saveLayout - Layout salvo com sucesso`

### **4. "Não consegue deletar nós"**
- **Causa:** Possível erro no frontend ao atualizar array
- **Solução:**
  - Abra console (F12)
  - Delete o nó
  - Veja se `deleteNode - Array depois` reduziu
  - Salve o layout
  - Veja se backend recebeu menos nós

---

## 📚 Arquivos Modificados

1. ✏️ `views/automations/show.php` - Handles múltiplos, logs, salvamento
2. ✏️ `app/Controllers/AutomationController.php` - Logs detalhados
3. ✏️ `public/view-automation-logs.php` - Criação automática de diretório
4. ✏️ `app/Services/AutomationService.php` - Função `processVariables` consolidada
5. 📄 `NOVAS_FUNCIONALIDADES_AUTOMACAO.md` - Este arquivo

---

## 🚀 Próximos Passos

1. ✅ Testar handles múltiplos
2. ✅ Testar deletar nós
3. ✅ Testar persistência de opções
4. ✅ Verificar logs (frontend + backend)
5. ⏳ Validar runtime (quando usuário responde chatbot)
6. ⏳ Testar com automações reais em produção

---

**Última atualização:** 18/12/2025 16:45  
**Status:** ✅ **PRONTO PARA TESTES COMPLETOS**

