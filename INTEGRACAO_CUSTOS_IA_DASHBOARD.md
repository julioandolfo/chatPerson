# 💰 Integração de Custos de IA no Dashboard

## 📊 Resumo

Todos os custos de serviços de IA foram integrados ao Dashboard de IA (`/dashboard/ai`), proporcionando uma visão consolidada e detalhada dos gastos com Inteligência Artificial.

---

## 🎯 Serviços de IA Integrados

### 1. **🤖 Agentes de IA** (`ai_conversations`)
- Conversas atendidas por agentes de IA
- Mensagens enviadas pela IA
- Tokens utilizados e custo por conversa

### 2. **😊 Análise de Sentimento** (`conversation_sentiments`)
- Análises de sentimento realizadas
- Custo por análise de sentimento
- Tokens consumidos na análise

### 3. **📊 Análise de Performance** (`agent_performance_analysis`)
- Análises de performance de vendedores
- Avaliação em 10 dimensões
- Custo por análise de performance

### 4. **🎯 Coaching em Tempo Real** (`realtime_coaching_hints`)
- Dicas fornecidas durante conversas ativas
- Coaching instantâneo para vendedores
- Custo por dica gerada

### 5. **🎤 Transcrição de Áudio** (`audio_transcriptions`)
- Transcrições de áudio via Whisper (OpenAI)
- Conversão de voz para texto
- Custo por transcrição

---

## 🔧 Implementação Técnica

### Backend: `DashboardService::getAIMetrics()`

**Arquivo:** `app/Services/DashboardService.php`

```php
private static function getAIMetrics(string $dateFrom, string $dateTo): array
{
    // Agrega custos de TODAS as fontes de IA:
    // - ai_conversations
    // - conversation_sentiments
    // - agent_performance_analysis
    // - realtime_coaching_hints
    // - audio_transcriptions
    
    return [
        'total_tokens' => $totalTokens,        // Total de todos os serviços
        'total_cost' => $totalCost,            // Custo consolidado
        'breakdown' => [                       // Detalhamento por serviço
            'ai_agents' => [...],
            'sentiment_analysis' => [...],
            'performance_analysis' => [...],
            'realtime_coaching' => [...],
            'audio_transcription' => [...]
        ]
    ];
}
```

### Frontend: `views/dashboard/ai-dashboard.php`

**Card de Breakdown Detalhado:**
- ✅ Cards visuais para cada serviço de IA
- ✅ Custo individual e percentual
- ✅ Número de usos/análises
- ✅ Tokens consumidos
- ✅ Barra de progresso visual
- ✅ Resumo consolidado:
  - Custo Total
  - Tokens Totais
  - Custo Médio por Conversa
  - Custo Médio por Token
- ✅ Alerta de custo elevado (> $10.00)

---

## 📈 Visualização no Dashboard

### Cards Principais
1. **Conversas com IA** - Total de conversas atendidas
2. **Mensagens Enviadas** - Mensagens da IA
3. **Taxa de Resolução** - % resolvido sem escalonamento
4. **Taxa de Escalonamento** - % transferido para humano

### Breakdown de Custos (NOVO)
- **Agentes de IA** (🤖 Azul)
- **Análise de Sentimento** (😊 Vermelho)
- **Análise de Performance** (📊 Ciano)
- **Coaching Tempo Real** (🎯 Amarelo)
- **Transcrição de Áudio** (🎤 Verde)

### Métricas Consolidadas
- **Custo Total:** Soma de todos os serviços
- **Tokens Totais:** Total consumido
- **Custo Médio/Conversa:** Eficiência por atendimento
- **Custo Médio/Token:** Análise de precificação

---

## 🎨 Características Visuais

### Cores por Serviço
- 🤖 **Agentes de IA:** Azul (primary)
- 😊 **Sentimento:** Vermelho (danger)
- 📊 **Performance:** Ciano (info)
- 🎯 **Coaching:** Amarelo (warning)
- 🎤 **Áudio:** Verde (success)

### Elementos Visuais
- ✅ Ícones distintos por serviço
- ✅ Badges com percentual do custo total
- ✅ Barras de progresso coloridas
- ✅ Cards com fundo suave (light-*)
- ✅ Alerta visual para custos elevados

---

## 📊 Métricas Disponíveis

### Por Serviço
```php
[
    'tokens' => 0,      // Tokens consumidos
    'cost' => 0.0000,   // Custo em USD
    'count' => 0        // Número de usos/análises
]
```

### Consolidado
```php
[
    'total_tokens' => 0,           // Total de todos os serviços
    'total_cost' => 0.0000,        // Custo total
    'total_ai_conversations' => 0  // Conversas com IA
]
```

---

## 🔍 Filtragem por Período

O dashboard permite filtrar por período:
- **Data Início:** Início do período de análise
- **Data Fim:** Fim do período de análise
- **Padrão:** Mês atual

---

## 🚨 Alertas Automáticos

### Custo Elevado
- **Threshold:** $10.00 no período
- **Ação:** Exibe alerta visual sugerindo revisão de configurações
- **Objetivo:** Controle de gastos com IA

---

## 📍 Como Acessar

### URL
`https://seu-dominio.com/dashboard/ai`

### Menu
**Dashboard → Dashboard de IA**

---

## 🔐 Permissões

**Permissão necessária:** 
- `dashboard.view` ou `dashboard.ai.view`

**Nível mínimo:** Agente

---

## 📊 Exemplo de Dados

### Período: Janeiro 2026

```
Custo Total: $15.23

Breakdown:
- Agentes de IA:          $10.50 (68.9%) - 245 conversas
- Análise de Sentimento:  $2.35  (15.4%) - 89 análises
- Análise de Performance: $1.85  (12.1%) - 11 análises
- Coaching Tempo Real:    $0.45  (2.9%)  - 127 dicas
- Transcrição de Áudio:   $0.08  (0.5%)  - 3 transcrições

Tokens Totais: 425,890
Custo Médio/Conversa: $0.0622
Custo Médio/Token: $0.000036
```

---

## 🎯 Benefícios

### Visibilidade
- ✅ Visão consolidada de todos os custos de IA
- ✅ Identificação de serviços mais custosos
- ✅ Comparação de eficiência entre serviços

### Controle
- ✅ Monitoramento de gastos em tempo real
- ✅ Alertas de custo elevado
- ✅ Análise de ROI por serviço

### Otimização
- ✅ Identificação de oportunidades de economia
- ✅ Ajuste de configurações baseado em dados
- ✅ Previsão de custos futuros

---

## 🔧 Manutenção

### Adicionar Novo Serviço de IA

**1. Atualizar `DashboardService::getAIMetrics()`:**
```php
// Adicionar query SQL para novo serviço
$sqlNovoServico = "SELECT 
                      COALESCE(SUM(ns.tokens_used), 0) as tokens,
                      COALESCE(SUM(ns.cost), 0) as cost,
                      COUNT(*) as count
                   FROM novo_servico ns
                   WHERE ns.created_at >= ? AND ns.created_at <= ?";
$novoServicoCost = \App\Helpers\Database::fetch($sqlNovoServico, [$dateFrom, $dateTo]);

// Adicionar ao breakdown
'breakdown' => [
    // ... serviços existentes ...
    'novo_servico' => [
        'tokens' => (int)($novoServicoCost['tokens'] ?? 0),
        'cost' => (float)($novoServicoCost['cost'] ?? 0),
        'count' => (int)($novoServicoCost['count'] ?? 0)
    ]
]
```

**2. Atualizar `views/dashboard/ai-dashboard.php`:**
```php
$breakdownItems = [
    // ... serviços existentes ...
    'novo_servico' => [
        'title' => 'Nome do Novo Serviço',
        'icon' => 'ki-icon-name',
        'color' => 'primary',
        'emoji' => '🆕'
    ]
];
```

---

## 🎉 Conclusão

O Dashboard de IA agora fornece uma **visão completa e consolidada** de todos os custos relacionados a serviços de Inteligência Artificial, permitindo:

- 📊 **Monitoramento centralizado**
- 💰 **Controle de gastos**
- 🎯 **Otimização de recursos**
- 📈 **Análise de ROI**

Todos os serviços de IA do sistema estão integrados e seus custos são automaticamente calculados e exibidos no dashboard!

---

**Data da Implementação:** 2026-01-10  
**Versão:** 1.0  
**Status:** ✅ Implementado e Funcional
