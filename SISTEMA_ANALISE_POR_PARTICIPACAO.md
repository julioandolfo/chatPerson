# Sistema de Análise de Performance por Participação

## Data: 19/01/2026

## Problema Identificado

**Situação**: Quando um vendedor atende um cliente e depois transfere para outro setor/agente, as análises de performance não eram atribuídas corretamente a cada participante.

**Exemplo**:
1. Vendedor A atende cliente das 10h às 11h
2. Conversa é transferida para Vendedor B que atende das 11h às 12h
3. Sistema analisava toda a conversa e atribuía ao último agente
4. **Problema**: Vendedor A não recebia análise da sua parte do atendimento

## Solução Implementada

### Conceito: Análise por Participação

Agora o sistema analisa **cada participação individual** de cada agente na conversa:
- Cada agente recebe análise apenas das mensagens que **ele enviou**
- Período de participação é determinado por `conversation_assignments`
- Uma conversa pode ter **múltiplas análises** (uma por agente)

## Arquitetura

### 1. Modelo: ConversationAssignment

**Novos Métodos**:

#### `getConversationParticipations(int $conversationId): array`
Retorna todos os agentes que participaram da conversa com seus períodos:
```php
[
    [
        'agent_id' => 1,
        'agent_name' => 'João',
        'assigned_at' => '2026-01-19 10:00:00',
        'removed_at' => '2026-01-19 11:00:00'
    ],
    [
        'agent_id' => 2,
        'agent_name' => 'Maria',
        'assigned_at' => '2026-01-19 11:00:00',
        'removed_at' => null // ainda ativo
    ]
]
```

#### `getAgentMessagesInParticipation(...)`
Retorna apenas as mensagens que o agente enviou durante sua participação.

#### `getAllMessagesInParticipation(...)`
Retorna todas as mensagens (incluindo do cliente) durante a participação do agente.

#### `getParticipationAnalysis(int $conversationId, int $agentId)`
Verifica se já existe análise para aquela participação específica.

### 2. Service: AgentPerformanceAnalysisService

**Novos Métodos**:

#### `analyzeConversationParticipations(int $conversationId, bool $force = false): array`
Método principal que:
1. Busca todas as participações da conversa
2. Para cada participação:
   - Verifica se já foi analisada
   - Coleta mensagens do período específico
   - Cria análise individual
3. Retorna array de análises criadas

#### `analyzeAgentParticipation(...)`
Analisa uma participação específica:
- Filtra mensagens pelo período (`assigned_at` até `removed_at`)
- Conta apenas mensagens do agente específico
- Envia para OpenAI com contexto de "participação parcial"
- Salva análise com `agent_id` correto

#### `buildParticipationAnalysisPrompt(...)`
Prompt especial que instrui a IA:
```
"⚠️ IMPORTANTE: Este vendedor atendeu o cliente APENAS durante o período especificado.
Avalie SOMENTE as mensagens que este vendedor enviou, desconsiderando mensagens de outros agentes."
```

### 3. Migration: 120_allow_multiple_analyses_per_conversation.php

**Mudanças no Banco**:
- Remove constraint `UNIQUE (conversation_id)`
- Adiciona constraint `UNIQUE (conversation_id, agent_id)`
- Permite múltiplas análises por conversa (uma por agente)

### 4. Service: CoachingMetricsService

**Atualização em `getAnalyzedConversations()`**:

Agora busca **todas as análises** de uma conversa:
```php
$conversation['performance_analyses'] = [
    [
        'agent_id' => 1,
        'agent_name' => 'João',
        'overall_score' => 4.5,
        'assigned_at' => '10:00',
        'removed_at' => '11:00',
        // ... todas as dimensões
    ],
    [
        'agent_id' => 2,
        'agent_name' => 'Maria',
        'overall_score' => 4.8,
        'assigned_at' => '11:00',
        'removed_at' => null,
        // ... todas as dimensões
    ]
]
```

**Compatibilidade**: Para views antigas, mantém campos da análise "primária":
- Se filtrou por agente: usa análise desse agente
- Senão: usa primeira análise

## Fluxo de Análise

### Cenário 1: Conversa com 1 Agente
```
Cliente → Agente A (10h-12h) → Fechada
```
**Resultado**: 1 análise para Agente A

### Cenário 2: Conversa Transferida
```
Cliente → Agente A (10h-11h) → Transfere → Agente B (11h-12h) → Fechada
```
**Resultado**: 
- 1 análise para Agente A (mensagens 10h-11h)
- 1 análise para Agente B (mensagens 11h-12h)

### Cenário 3: Múltiplas Transferências
```
Cliente → SDR (9h-10h) → Vendedor (10h-11h) → Suporte (11h-12h) → Fechada
```
**Resultado**:
- 1 análise para SDR (mensagens 9h-10h)
- 1 análise para Vendedor (mensagens 10h-11h)
- 1 análise para Suporte (mensagens 11h-12h)

## Como Usar

### Analisar Conversa com Participações

```php
use App\Services\AgentPerformanceAnalysisService;

// Analisa todas as participações
$analyses = AgentPerformanceAnalysisService::analyzeConversationParticipations(936);

// Retorna array de análises
foreach ($analyses as $analysis) {
    echo "Agente {$analysis['agent_id']}: {$analysis['overall_score']}/5.0\n";
}
```

### Buscar Participações de uma Conversa

```php
use App\Models\ConversationAssignment;

$participations = ConversationAssignment::getConversationParticipations(936);

foreach ($participations as $p) {
    echo "{$p['agent_name']}: {$p['assigned_at']} até {$p['removed_at']}\n";
}
```

### Visualizar Análises no Dashboard

As views já foram atualizadas para mostrar:
- Total de participações
- Análise de cada agente separadamente
- Período de cada participação

## Benefícios

### ✅ Justiça nas Avaliações
- Cada agente é avaliado apenas pelo que fez
- Não é penalizado por problemas de outros agentes
- Não leva crédito pelo trabalho de outros

### ✅ Visibilidade Completa
- Supervisores veem toda a jornada do cliente
- Identificam gargalos entre setores
- Entendem onde melhorar handoffs

### ✅ Métricas Precisas
- Performance real de cada agente
- Rankings justos
- Metas individualizadas

### ✅ Coaching Direcionado
- Feedback específico para cada participação
- Identificação de pontos fortes/fracos reais
- Sugestões de melhoria personalizadas

## Exemplos de Uso

### Dashboard de Coaching

```
Conversa #936 - Cliente: João Silva

📊 Participações:
┌─────────────────────────────────────────┐
│ SDR - Maria (9h-10h)                    │
│ Score: 4.2/5.0                          │
│ ✅ Boa qualificação                     │
│ ⚠️ Melhorar rapport                     │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│ Vendedor - Carlos (10h-11h30)          │
│ Score: 4.8/5.0                          │
│ ✅ Excelente fechamento                 │
│ ✅ Ótima quebra de objeções             │
└─────────────────────────────────────────┘
```

### Performance Individual

```
Agente: Maria (SDR)
Período: Janeiro 2026

Conversas Analisadas: 45
- 30 conversas completas (única atendente)
- 15 participações em conversas transferidas

Score Médio: 4.3/5.0
```

## Arquivos Modificados

1. ✅ `app/Models/ConversationAssignment.php`
   - Novos métodos para buscar participações
   - Métodos para filtrar mensagens por período

2. ✅ `app/Services/AgentPerformanceAnalysisService.php`
   - `analyzeConversationParticipations()`
   - `analyzeAgentParticipation()`
   - `buildParticipationAnalysisPrompt()`

3. ✅ `app/Services/CoachingMetricsService.php`
   - Atualizado para buscar múltiplas análises
   - Compatibilidade com views antigas

4. ✅ `database/migrations/120_allow_multiple_analyses_per_conversation.php`
   - Remove constraint UNIQUE de conversation_id
   - Adiciona UNIQUE (conversation_id, agent_id)

## Migração de Dados Existentes

**IMPORTANTE**: Análises antigas (antes desta atualização) continuam funcionando:
- Análises existentes permanecem no banco
- Novas análises seguem o novo padrão
- Sistema detecta automaticamente qual usar

## Próximos Passos

### Recomendações:

1. **Executar Migration**:
   ```bash
   php database/migrate.php
   ```

2. **Re-analisar Conversas Importantes**:
   ```php
   // Força re-análise com novo sistema
   AgentPerformanceAnalysisService::analyzeConversationParticipations($conversationId, true);
   ```

3. **Atualizar Views** (se necessário):
   - Mostrar todas as participações
   - Indicar período de cada análise
   - Permitir filtro por agente

4. **Treinar Equipe**:
   - Explicar novo sistema
   - Mostrar como visualizar análises
   - Destacar benefícios

## Troubleshooting

### Problema: Análise não aparece para um agente

**Verificar**:
1. Agente está em `conversation_assignments`?
2. Agente enviou mensagens suficientes? (mínimo configurável)
3. Migration foi executada?

### Problema: Múltiplas análises para mesmo agente

**Causa**: Agente foi reatribuído múltiplas vezes
**Solução**: Normal! Cada participação gera uma análise

### Problema: Análise antiga não mostra participações

**Causa**: Análise foi criada antes desta atualização
**Solução**: Re-analisar com `force = true`

## Conclusão

O novo sistema de análise por participação garante que:
- ✅ Cada agente é avaliado justamente
- ✅ Métricas são precisas e individualizadas
- ✅ Coaching é direcionado e efetivo
- ✅ Transferências entre setores são rastreadas
- ✅ Performance real é medida, não estimada

**Resultado**: Avaliações mais justas, feedback mais preciso, melhorias mais rápidas! 🚀
