# 🗑️ O Que Acontece Quando Você Deleta uma Automação?

## 📋 **Resumo Executivo**

Quando você deleta uma automação, o sistema **automaticamente**:

1. ✅ **Deleta nós relacionados** (cascade)
2. ✅ **Deleta execuções registradas** (cascade)
3. ✅ **Cancela delays agendados** (cascade + limpeza manual)
4. ✅ **Limpa metadata de conversas** (novo - evita conversas "presas")
5. ✅ **Informa quantas conversas foram afetadas**

---

## 🔍 **Detalhamento**

### **1. Nós da Automação** ✅

**O que acontece:**
- Todos os nós (`automation_nodes`) são deletados automaticamente
- Foreign Key com `ON DELETE CASCADE` garante isso

**Impacto:** Nenhum - nós só existem dentro da automação

---

### **2. Execuções Registradas** ✅

**O que acontece:**
- Todas as execuções (`automation_executions`) são deletadas automaticamente
- Foreign Key com `ON DELETE CASCADE` garante isso

**Impacto:** 
- ⚠️ **Histórico perdido**: Logs de execução são removidos
- ✅ **Sem impacto funcional**: Execuções já foram concluídas ou falharam

**Exemplo:**
```
Antes: automation_executions tem 150 registros da automação #5
Depois: Todos os 150 registros são deletados
```

---

### **3. Delays Agendados** ✅

**O que acontece:**
- Delays pendentes (`automation_delays`) são cancelados automaticamente
- Foreign Key com `ON DELETE CASCADE` + limpeza manual garantem isso

**Impacto:**
- ⚠️ **Ações futuras canceladas**: Se havia um delay agendado para executar algo em 1 hora, essa ação não acontecerá
- ✅ **Status atualizado**: Delays ficam como `cancelled` com mensagem "Automação foi deletada"

**Exemplo:**
```
Antes: 3 delays agendados para executar em 30min, 1h e 2h
Depois: Todos os 3 delays são cancelados
```

---

### **4. Conversas Vinculadas** ⚠️ **CRÍTICO - NOVO**

**O que acontece:**
- Sistema busca todas as conversas que têm `metadata.ai_branching_automation_id = X` (onde X é a automação deletada)
- Limpa o metadata dessas conversas:
  - `ai_branching_active` → `false`
  - `ai_branching_automation_id` → removido
  - `ai_interaction_count` → `0`
  - `ai_intents` → `[]`
  - `ai_fallback_node_id` → removido

**Impacto:**
- ✅ **Conversas não ficam "presas"**: Se uma conversa estava esperando a automação continuar, ela é liberada
- ⚠️ **Ramificação de IA desativada**: Se a conversa estava em um fluxo de ramificação de IA, esse fluxo é interrompido
- ✅ **Conversa continua funcionando**: A conversa não é deletada, apenas o estado da automação é limpo

**Exemplo:**
```
Antes:
Conversa #123 tem metadata:
{
  "ai_branching_active": true,
  "ai_branching_automation_id": 5,
  "ai_interaction_count": 2,
  "ai_intents": ["vendas", "suporte"]
}

Depois (automação #5 deletada):
Conversa #123 tem metadata:
{
  "ai_branching_active": false,
  "ai_interaction_count": 0,
  "ai_intents": []
}
```

---

## 🎯 **Cenários Práticos**

### **Cenário 1: Automação Simples (Sem Ramificação IA)**

**Situação:**
- Automação que envia mensagem de boas-vindas
- 10 conversas já executaram esta automação

**Ao deletar:**
- ✅ 10 execuções são deletadas (histórico perdido)
- ✅ Conversas continuam funcionando normalmente
- ✅ Nenhuma conversa é afetada (não há metadata de ramificação)

---

### **Cenário 2: Automação com Ramificação IA Ativa**

**Situação:**
- Automação com nó "Assign AI Agent" com ramificação por intents
- 5 conversas estão **ativamente** esperando a IA detectar intents
- Metadata dessas conversas tem `ai_branching_active = true`

**Ao deletar:**
- ✅ 5 conversas têm metadata limpo
- ✅ Ramificação de IA é desativada nessas conversas
- ⚠️ **Atenção**: Se a IA estava esperando detectar um intent, essa espera é cancelada
- ✅ Conversas continuam funcionando, mas sem ramificação automática

**Recomendação:**
- Se você deletar uma automação com ramificação IA ativa, verifique essas conversas manualmente
- Considere atribuir um agente humano se necessário

---

### **Cenário 3: Automação com Delays Agendados**

**Situação:**
- Automação que envia follow-up após 24 horas
- 3 delays agendados para executar amanhã

**Ao deletar:**
- ✅ 3 delays são cancelados
- ⚠️ **Atenção**: Follow-ups não serão enviados automaticamente
- ✅ Você pode criar uma nova automação ou enviar manualmente

---

## 📊 **Informações Retornadas**

Quando você deleta uma automação, a resposta inclui:

```json
{
  "success": true,
  "message": "Automação deletada com sucesso! 5 conversa(s) foram atualizadas (ramificação de IA desativada).",
  "affected_conversations": 5
}
```

**Campos:**
- `success`: `true` se deletou com sucesso
- `message`: Mensagem descritiva incluindo quantas conversas foram afetadas
- `affected_conversations`: Número de conversas que tiveram metadata limpo

---

## ⚠️ **Avisos Importantes**

### **1. Histórico Perdido**
- Execuções registradas são deletadas permanentemente
- Não há como recuperar logs de execução após deletar

### **2. Conversas em Ramificação IA**
- Se você deletar uma automação enquanto conversas estão esperando ramificação, essas conversas são "liberadas"
- A ramificação é desativada, mas a conversa continua funcionando normalmente
- Considere verificar manualmente essas conversas após deletar

### **3. Delays Agendados**
- Delays futuros são cancelados
- Se você precisar dessas ações, recrie a automação ou execute manualmente

### **4. Não Há "Desfazer"**
- A deleção é permanente
- Não há como recuperar uma automação deletada
- Considere desativar (`status = 'inactive'`) ao invés de deletar se quiser manter histórico

---

## 🔄 **Alternativa: Desativar ao Invés de Deletar**

Se você quiser manter histórico mas parar execuções:

1. **Edite a automação**
2. **Mude status para "Inativa"**
3. **Desmarque "Ativa"**

**Vantagens:**
- ✅ Histórico preservado
- ✅ Pode reativar depois
- ✅ Execuções futuras são bloqueadas

**Desvantagens:**
- ⚠️ Automação continua ocupando espaço no banco
- ⚠️ Aparece na lista (mas marcada como inativa)

---

## 📝 **Logs**

Todas as ações são registradas em `logs/automacao.log`:

```
[2024-01-15 10:30:00] Automação deletada: ID 5, Nome: Boas-vindas, Conversas afetadas: 3
[2024-01-15 10:30:00] Conversa 123: Metadata de ramificação IA limpo (automação 5 deletada)
[2024-01-15 10:30:00] Conversa 456: Metadata de ramificação IA limpo (automação 5 deletada)
[2024-01-15 10:30:00] Conversa 789: Metadata de ramificação IA limpo (automação 5 deletada)
[2024-01-15 10:30:00] 2 delay(s) pendente(s) cancelado(s) para automação 5
```

---

## ✅ **Conclusão**

O sistema foi projetado para **não deixar conversas "presas"** quando uma automação é deletada. Todas as referências são limpas automaticamente, garantindo que:

1. ✅ Conversas continuam funcionando
2. ✅ Não há referências órfãs no banco
3. ✅ Delays futuros são cancelados
4. ✅ Metadata é limpo corretamente

**Recomendação:** Sempre verifique quantas conversas serão afetadas antes de deletar uma automação com ramificação IA ativa.

