# Melhoria do Prompt de Análise de Performance

## Problemas Identificados

### 1. Tempo de Resposta
**Problema Atual**:
- Critério vago: "Responde rapidamente? Não deixa cliente esperando?"
- IA não tem benchmark de referência
- Não recebe métricas reais de tempo

**Exemplo do Erro**:
- Agente responde em 3-5 minutos (excelente!)
- IA diz: "Tempo de resposta alto" ❌

### 2. Follow-up
**Problema Atual**:
- Conceito errado: sistema pensava que era "agendar reunião"
- Critério vago: "Define próximos passos?"
- **CORRETO**: Follow-up é PERSISTÊNCIA e IR ATRÁS do cliente!

**Exemplo do Erro**:
- Cliente diz "vou pensar" e some
- Vendedor retorna 2 dias depois cobrando
- IA diz: "Não houve follow-up" ❌

**Conceito Correto**:
- Cliente some → Vendedor reativa? ✅
- Cliente enrola → Vendedor insiste? ✅
- Cliente adia → Vendedor cobra? ✅

## Solução Proposta

### Calcular Métricas Reais

Antes de enviar para IA, calcular:
1. **Tempo médio de resposta do agente** (em minutos)
2. **Tempo máximo de resposta** (maior gap)
3. **Total de interações** (quantas vezes o agente respondeu)
4. **Padrões de follow-up** (buscar palavras-chave)

### Adicionar Benchmarks no Prompt

Informar a IA sobre o que é "bom" ou "ruim":

```
MÉTRICAS DE REFERÊNCIA:
- Tempo de Resposta:
  * EXCELENTE: < 3 minutos
  * BOM: 3-5 minutos  
  * ACEITÁVEL: 5-10 minutos
  * PRECISA MELHORAR: > 10 minutos

- Follow-up (Persistência):
  * EXCELENTE: Cliente sumiu/enrolou e vendedor retornou MÚLTIPLAS vezes
  * BOM: Cliente disse "vou pensar" e vendedor cobrou depois
  * ACEITÁVEL: Vendedor tentou reativar pelo menos uma vez
  * INSUFICIENTE: Vendedor só disse "me chama" mas não foi atrás
  * CRÍTICO: Vendedor deixou conversa morrer, desistiu fácil
```

## Implementação Realizada

### 1. Nova Função: `calculateConversationMetrics()`

Calcula métricas REAIS da conversa:
```php
[
    'total_messages' => 25,
    'agent_messages' => 12,
    'client_messages' => 13,
    'avg_response_time' => 3.5,  // minutos
    'max_response_time' => 8.2,  // minutos
    'conversation_duration' => 45.0, // minutos
    'response_count' => 12
]
```

### 2. Nova Função: `formatMinutes()`

Formata tempo em texto legível:
- `3.5` → "3.5 minutos"
- `0.5` → "30 segundos"
- `125` → "2h 5min"

### 3. Prompt Melhorado

**ANTES**:
```
Tempo de Resposta:
- Responde rapidamente?
- Não deixa cliente esperando?
```

**DEPOIS**:
```
📊 MÉTRICAS CALCULADAS:
- Tempo médio de resposta: 3.5 minutos
- Tempo máximo de resposta: 8.2 minutos

📋 BENCHMARKS:
  • 5.0 = EXCELENTE (< 3 minutos)
  • 4.0 = BOM (3-5 minutos)  
  • 3.0 = ACEITÁVEL (5-10 minutos)
  • 2.0 = PRECISA MELHORAR (10-20 minutos)
  • 1.0 = CRÍTICO (> 20 minutos)

⚠️ USE AS MÉTRICAS ACIMA! Não invente valores!
```

### 4. Follow-up Mais Específico

**ANTES**:
```
- Define próximos passos?
- Agenda follow-up?
```

**DEPOIS**:
```
- Define data/hora ESPECÍFICA?
- Agenda reunião ou ligação futura?
- Deixa calendário marcado?
- Cliente confirma agendamento?
- Ou apenas 'entro em contato' sem definição?
```

## Exemplos de Análise

### Exemplo 1: Resposta Rápida ✅
```
Métricas: Tempo médio 3.2 minutos
Avaliação IA: 4.5/5.0 (BOM - respostas consistentes e rápidas)
```

### Exemplo 2: Resposta Muito Rápida ✅
```
Métricas: Tempo médio 1.8 minutos
Avaliação IA: 5.0/5.0 (EXCELENTE - respostas quase instantâneas)
```

### Exemplo 3: Follow-up Excelente ✅
```
Cliente (dia 1): "Vou pensar e te retorno"
[Cliente não retorna]
Vendedor (dia 3): "E aí, conseguiu avaliar?"
[Cliente não responde]
Vendedor (dia 5): "Oi! Vi que não respondeu. Tem alguma dúvida?"
Cliente: "Desculpa, tava corrido aqui..."
Avaliação IA: 5.0/5.0 (Persistiu múltiplas vezes, não desistiu)
```

### Exemplo 4: Follow-up Fraco ❌
```
Cliente: "Vou pensar e te retorno"
Vendedor: "Ok, qualquer coisa me chama"
[Conversa morre, vendedor não retorna]
Avaliação IA: 1.0/5.0 (Desistiu fácil, não foi atrás)
```

### Exemplo 5: Follow-up Bom ✅
```
Cliente: "Preciso conversar com meu sócio, volto amanhã"
[Cliente não retorna]
Vendedor (2 dias depois): "E aí, conseguiu conversar com o sócio?"
Cliente: "Consegui sim! Vamos fechar"
Avaliação IA: 4.5/5.0 (Foi atrás e recuperou a venda)
```

## Resultados Esperados

### ✅ Tempo de Resposta
- Avaliações baseadas em dados reais
- IA não "inventa" que foi lento
- Benchmarks claros e objetivos

### ✅ Follow-up
- IA identifica agendamentos específicos
- Diferencia "vou ligar" de "ligo quinta às 15h"
- Pontuação mais justa

### ✅ Outras Dimensões
- Todas recebem contexto do que é esperado
- Avaliações mais consistentes
- Feedback mais preciso

## Arquivos Modificados

✅ `app/Services/AgentPerformanceAnalysisService.php`
- Nova função `calculateConversationMetrics()`
- Nova função `formatMinutes()`
- Prompt melhorado em `buildAnalysisPrompt()`
- Prompt melhorado em `buildParticipationAnalysisPrompt()`
- Critérios detalhados em `getDimensionCriteria()`

## Como Testar

1. **Re-analisar uma conversa**:
```php
AgentPerformanceAnalysisService::analyzeConversation(936, true);
```

2. **Verificar métricas no log**:
```
📊 MÉTRICAS CALCULADAS:
- Tempo médio de resposta: 3.5 minutos
```

3. **Comparar scores**:
- Antes: 2.0/5.0 (inventado)
- Depois: 4.5/5.0 (baseado em dados reais)

## Próximas Melhorias Sugeridas

1. **Adicionar contexto de horário comercial**
   - Resposta em 10 min às 2h da manhã = normal
   - Resposta em 10 min às 14h = lento

2. **Considerar volume de mensagens**
   - Cliente enviou 10 mensagens seguidas
   - Agente respondeu todas de uma vez
   - Não penalizar tempo de resposta

3. **Detectar padrões de follow-up automaticamente**
   - Identificar quando cliente some (gap > 24h)
   - Verificar se agente retornou antes do cliente
   - Contar quantas vezes agente reativou
   - Palavras-chave: "conseguiu", "e aí", "viu minha mensagem", "retornando"

4. **Adicionar análise de sentimento**
   - Cliente satisfeito = menos crítico no tempo
   - Cliente frustrado = considerar no contexto

