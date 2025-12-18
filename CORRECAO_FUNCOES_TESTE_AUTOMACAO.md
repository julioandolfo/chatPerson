# ✅ CORREÇÃO: Funções de Teste de Automação Não Definidas

## Data: 18/12/2025

---

## 🐛 Problema

No navegador, ao tentar usar os botões de teste na página de automações, ocorriam erros:

```javascript
Uncaught ReferenceError: testAutomation is not defined
Uncaught ReferenceError: advancedTestAutomation is not defined
```

**Console:**
```
testAutomation is not defined at HTMLButtonElement.onclick (2:1249:100)
advancedTestAutomation is not defined at HTMLAnchorElement.onclick (2:1267:109)
```

---

## 🔍 Causa Raiz

As funções `testAutomation` e `advancedTestAutomation` (e outras relacionadas) estavam definidas dentro do script, mas:

1. **Definição tardia:** Funções eram definidas no meio/final do script
2. **Exportação final:** A exportação para `window.*` acontecia apenas no final do script
3. **Timing:** Os botões HTML tentavam chamar as funções **antes** do script completar a carga

**Estrutura Problemática:**
```javascript
<script>
// ... muito código ...

// Lá no meio (linha ~2973)
function testAutomation() { ... }

// ... mais código ...

// Lá no final (linha ~3450)
window.testAutomation = testAutomation; // ❌ Muito tarde!
</script>
```

**HTML dos Botões:**
```html
<button onclick="testAutomation()">Teste Rápido</button>
<!-- ❌ Erro: testAutomation ainda não está em window.* -->
```

---

## ✅ Solução

### **1. Declaração Antecipada (Hoisting Manual)**

No topo do segundo bloco `<script>`, declaramos os slots globais:

```javascript
<script>
// ===== FUNÇÕES GLOBAIS (EXPORT NO TOPO) =====
window.testAutomation = null;
window.advancedTestAutomation = null;
window.validateAutomationForm = null;
window.validateAutomationConnections = null;
window.validateRequiredField = null;
window.previewVariables = null;
window.showVariablesModal = null;
window.previewMessageVariables = null;

// ... resto do código ...
</script>
```

### **2. Atribuição Direta na Definição**

Cada função agora é **diretamente atribuída** a `window.*` no momento da definição:

**Antes:**
```javascript
function testAutomation() {
    const automationId = 123;
    // ...
}

// ... muito código depois ...

window.testAutomation = testAutomation; // ❌ Tarde demais
```

**Depois:**
```javascript
window.testAutomation = function testAutomation() {
    const automationId = 123;
    // ...
}; // ✅ Disponível imediatamente
```

### **3. Funções Convertidas**

Todas as funções críticas foram convertidas:

1. ✅ `window.testAutomation`
2. ✅ `window.advancedTestAutomation`
3. ✅ `window.validateAutomationForm`
4. ✅ `window.validateAutomationConnections`
5. ✅ `window.validateRequiredField`
6. ✅ `window.previewVariables`

### **4. Verificação Final**

No final do script, adicionamos um `console.log` para confirmar:

```javascript
console.log('Funções globais de automação carregadas:', {
    testAutomation: typeof window.testAutomation,
    advancedTestAutomation: typeof window.advancedTestAutomation,
    validateAutomationForm: typeof window.validateAutomationForm,
    validateAutomationConnections: typeof window.validateAutomationConnections,
    validateRequiredField: typeof window.validateRequiredField,
    previewVariables: typeof window.previewVariables
});
```

**Saída Esperada no Console:**
```javascript
Funções globais de automação carregadas: {
    testAutomation: "function",
    advancedTestAutomation: "function",
    validateAutomationForm: "function",
    validateAutomationConnections: "function",
    validateRequiredField: "function",
    previewVariables: "function"
}
```

---

## 🎯 Resultado

### **Antes:**
```
1. HTML carrega
2. Botão tenta chamar testAutomation()
3. ❌ ReferenceError: testAutomation is not defined
4. Script continua carregando...
5. Função é definida (tarde demais)
```

### **Depois:**
```
1. HTML carrega
2. Script inicia: window.testAutomation = null (slot criado)
3. Script define: window.testAutomation = function() {...}
4. ✅ Botão pode chamar testAutomation() a qualquer momento
```

---

## 📝 Alterações no Código

**Arquivo:** `views/automations/show.php`

### **Linha ~2870 (Início do script):**
```javascript
// ===== FUNÇÕES GLOBAIS (EXPORT NO TOPO) =====
window.testAutomation = null;
window.advancedTestAutomation = null;
// ... etc
```

### **Linha ~2983 (testAutomation):**
```javascript
// ANTES
function testAutomation() { ... }

// DEPOIS
window.testAutomation = function testAutomation() { ... };
```

### **Linha ~3291 (advancedTestAutomation):**
```javascript
// ANTES
function advancedTestAutomation() { ... }

// DEPOIS
window.advancedTestAutomation = function advancedTestAutomation() { ... };
```

### **Linha ~3205 (validateAutomationForm):**
```javascript
// ANTES
function validateAutomationForm() { ... }

// DEPOIS
window.validateAutomationForm = function validateAutomationForm() { ... };
```

### **Linha ~3228 (validateAutomationConnections):**
```javascript
// ANTES
function validateAutomationConnections() { ... }

// DEPOIS
window.validateAutomationConnections = function validateAutomationConnections() { ... };
```

### **Linha ~3175 (validateRequiredField):**
```javascript
// ANTES
function validateRequiredField(field) { ... }

// DEPOIS
window.validateRequiredField = function validateRequiredField(field) { ... };
```

### **Linha ~3117 (previewVariables):**
```javascript
// ANTES
function previewVariables(message, conversationId) { ... }

// DEPOIS
window.previewVariables = function previewVariables(message, conversationId) { ... };
```

---

## 🧪 Como Testar

1. **Abra a página de automações** (`/automations/{id}`)
2. **Abra o Console do navegador** (F12)
3. **Verifique a mensagem:** 
   ```
   Funções globais de automação carregadas: { ... }
   ```
4. **Clique em "Teste Rápido"**
   - ✅ Deve abrir modal de teste
   - ❌ **NÃO** deve mostrar erro no console
5. **Clique no dropdown → "Modo Avançado"**
   - ✅ Deve abrir modal avançado
   - ❌ **NÃO** deve mostrar erro no console
6. **No console, digite:**
   ```javascript
   typeof window.testAutomation
   ```
   - **Resultado esperado:** `"function"`

---

## ✅ Checklist

- ✅ Slots `window.*` declarados no topo do script
- ✅ `testAutomation` convertida para `window.*`
- ✅ `advancedTestAutomation` convertida para `window.*`
- ✅ `validateAutomationForm` convertida para `window.*`
- ✅ `validateAutomationConnections` convertida para `window.*`
- ✅ `validateRequiredField` convertida para `window.*`
- ✅ `previewVariables` convertida para `window.*`
- ✅ Chamada a `window.validateAutomationConnections()` atualizada em `advancedTestAutomation`
- ✅ Verificação final via `console.log`
- ✅ Sem erros de linting

---

## 📋 Lições Aprendidas

### **Problema:**
Funções usadas em `onclick` devem estar em `window.*` **antes** do HTML carregar.

### **Solução:**
1. **Declarar slots globais no topo** do script
2. **Atribuir diretamente** `window.funcao = function() {...}`
3. **Evitar atribuição tardia** no final do script

### **Pattern Recomendado:**
```javascript
<script>
// 1. Declarar slots
window.minhaFuncao = null;

// 2. Definir diretamente
window.minhaFuncao = function minhaFuncao() {
    // ...
};

// 3. (Opcional) Verificar no final
console.log('Função carregada:', typeof window.minhaFuncao);
</script>
```

---

## 📚 Arquivos Modificados

- `views/automations/show.php`

---

**Correção concluída! 🎉**

**Teste os botões agora!** ✅

