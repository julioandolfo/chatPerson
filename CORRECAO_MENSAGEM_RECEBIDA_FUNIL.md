# Correção: Vincular Funil/Estágio ao Gatilho "Mensagem Recebida"

## 🐛 Problema

O gatilho **"Mensagem Recebida"** não exibia as opções de vincular a **Funil** e **Estágio** no modal de criação de automação.

## ✅ Solução

Adicionado `"message_received"` ao array `triggersWithFunnel` no JavaScript.

### Arquivo Modificado:
**`views/automations/index.php`**

### Mudança:
```javascript
// ANTES
const triggersWithFunnel = [
    "new_conversation", 
    "conversation_moved", 
    "conversation_updated", 
    "conversation_resolved", 
    "no_customer_response", 
    "no_agent_response"
];

// DEPOIS
const triggersWithFunnel = [
    "new_conversation", 
    "message_received",      // ← ADICIONADO
    "conversation_moved", 
    "conversation_updated", 
    "conversation_resolved", 
    "no_customer_response", 
    "no_agent_response"
];
```

## 🎯 Comportamento Agora

### Ao Selecionar "Mensagem Recebida":

**Antes:**
- ❌ Campos de Funil/Estágio **não apareciam**
- ❌ Não era possível filtrar por funil ou estágio

**Depois:**
- ✅ Campos de Funil/Estágio **aparecem**
- ✅ Possível vincular a funil específico
- ✅ Possível vincular a estágio específico
- ✅ Ou deixar vazio para aplicar a todos

## 📊 Casos de Uso

### Exemplo 1: Automação por Funil
```
Nome: "Boas-vindas Vendas"
Gatilho: Mensagem Recebida
Funil: Vendas
Estágio: (Todos)
Ação: Enviar mensagem de boas-vindas
```
→ Executa apenas para mensagens em conversas do funil "Vendas"

### Exemplo 2: Automação por Estágio
```
Nome: "Alerta Negociação"
Gatilho: Mensagem Recebida
Funil: Vendas
Estágio: Negociação
Ação: Notificar gerente
```
→ Executa apenas para mensagens no estágio "Negociação" do funil "Vendas"

### Exemplo 3: Global (Todos os Funis)
```
Nome: "Log Global de Mensagens"
Gatilho: Mensagem Recebida
Funil: (Todos)
Estágio: (Todos)
Ação: Adicionar nota interna
```
→ Executa para mensagens em qualquer funil/estágio

## 🔄 Gatilhos que Suportam Funil/Estágio

Agora **TODOS** os gatilhos relevantes suportam vinculação:

| Gatilho | Suporta Funil/Estágio |
|---------|----------------------|
| ✅ Nova Conversa | Sim |
| ✅ **Mensagem Recebida** | **Sim** ← Corrigido |
| ✅ Conversa Atualizada | Sim |
| ✅ Conversa Movida | Sim |
| ✅ Conversa Resolvida | Sim |
| ✅ Tempo sem Resposta Cliente | Sim |
| ✅ Tempo sem Resposta Agente | Sim |
| ❌ Baseado em Tempo | Sim (mas via config) |
| ❌ Contato Criado | Não |
| ❌ Contato Atualizado | Não |
| ❌ Atividade do Agente | Não |
| ❌ Webhook Externo | Não |

## 🧪 Como Testar

1. **Acesse:** `/automations`
2. **Clique:** "Nova Automação"
3. **Selecione Gatilho:** "Mensagem Recebida"
4. **Verificar:**
   - ✅ Campos "Vincular a Funil/Estágio" aparecem
   - ✅ Pode selecionar funil
   - ✅ Pode selecionar estágio
   - ✅ Ou deixar vazio

## 📝 Benefícios

1. **Maior Granularidade:**
   - Criar automações específicas por funil
   - Criar automações específicas por estágio

2. **Organização:**
   - Automações focadas em cada etapa do processo
   - Evitar automações muito abrangentes

3. **Performance:**
   - Executar apenas onde necessário
   - Menos processamento desnecessário

4. **Casos de Uso:**
   - Mensagens de boas-vindas por funil
   - Alertas específicos por estágio
   - Ações diferentes para cada pipeline

## ✅ Status

- [x] Problema identificado
- [x] Correção implementada
- [x] Documentação atualizada
- [x] Pronto para uso

---

**Data:** 21/12/2025  
**Arquivo Modificado:** `views/automations/index.php`  
**Linhas Alteradas:** 1 linha (adicionar "message_received")  
**Breaking Changes:** Nenhum  
**Status:** ✅ Implementado

