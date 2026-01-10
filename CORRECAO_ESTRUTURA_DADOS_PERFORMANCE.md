# Correção de Estrutura de Dados - Performance - 2026-01-10

## ❌ Problemas Encontrados

### 1. **Erro `number_format(): Argument #1 must be of type float, array given`**

**Causa:** Mapeamento incorreto entre a estrutura de dados retornada pelos Services e o esperado pelas Views.

---

## ✅ Correções Aplicadas

### 1. **View `agent.php` - Performance Individual**

#### Problema 1: Nota Geral
```php
// ❌ ANTES (ERRADO)
$report['overall_score']  // Não existia

// ✅ DEPOIS (CORRETO)
$report['averages']['avg_overall']
```

#### Problema 2: Scores das Dimensões
```php
// ❌ ANTES (ERRADO)
$score = $report['dimensions'][$key] ?? 0;  // 'dimensions' não existia

// ✅ DEPOIS (CORRETO)
$score = $report['averages']['avg_' . $key] ?? 0;
```

#### Problema 3: Evolução
```php
// ❌ ANTES (ERRADO)
$evolution = $report['evolution'][$key] ?? 0;  // Era um array, não um número
number_format($evolution, 2);  // ❌ Erro: tentando formatar array

// ✅ DEPOIS (CORRETO)
$evolutionData = $report['evolution'][$key] ?? [];
$evolution = $evolutionData['change'] ?? 0;  // Pega apenas o 'change'
number_format($evolution, 2);  // ✅ OK: agora é um número
```

---

### 2. **Service `PerformanceReportService::generateAgentReport()`**

#### Problema: Faltavam `top_strengths` e `top_weaknesses`

**Adicionado:**
```php
// Extrair pontos fortes e fracos mais comuns
$allStrengths = [];
$allWeaknesses = [];
foreach ($analyses as $analysis) {
    $strengths = json_decode($analysis['strengths'] ?? '[]', true);
    $weaknesses = json_decode($analysis['weaknesses'] ?? '[]', true);
    $allStrengths = array_merge($allStrengths, $strengths);
    $allWeaknesses = array_merge($allWeaknesses, $weaknesses);
}

// Contar frequência e pegar top 5
$strengthsCount = array_count_values($allStrengths);
$weaknessesCount = array_count_values($allWeaknesses);
arsort($strengthsCount);
arsort($weaknessesCount);
$topStrengths = array_slice(array_keys($strengthsCount), 0, 5);
$topWeaknesses = array_slice(array_keys($weaknessesCount), 0, 5);
```

**Retorno atualizado:**
```php
return [
    'agent' => $agent,
    'period' => ['from' => $dateFrom, 'to' => $dateTo],
    'averages' => $averages,
    'analyses' => $analyses,
    'evolution' => $evolution,
    'badges' => $badges,
    'goals' => $goals,
    'total_analyses' => count($analyses),
    'top_strengths' => $topStrengths,      // ✅ NOVO
    'top_weaknesses' => $topWeaknesses     // ✅ NOVO
];
```

---

### 3. **Service `PerformanceReportService::compareAgents()`**

#### Problema: Estrutura incompatível com a view

**❌ ANTES:**
```php
return [
    'agents' => [
        [
            'agent_id' => 1,
            'agent_name' => 'João',
            'averages' => ['avg_proactivity' => 4.5, ...]
        ]
    ],
    'period' => ['from' => ..., 'to' => ...]
];
```

**✅ DEPOIS:**
```php
return [
    [
        'agent' => ['id' => 1, 'name' => 'João', ...],
        'overall_score' => 4.5,
        'dimensions' => [
            'proactivity' => 4.5,
            'objection_handling' => 4.3,
            // ... outras dimensões
        ],
        'total_analyses' => 10
    ],
    // ... outros agentes
];
```

---

## 📊 Estrutura de Dados Documentada

### `generateAgentReport()` retorna:
```php
[
    'agent' => [id, name, email, ...],
    'period' => ['from' => '2024-01-01', 'to' => '2024-01-31'],
    'averages' => [
        'avg_proactivity' => 4.5,
        'avg_objection_handling' => 4.3,
        'avg_rapport' => 4.7,
        // ... outras dimensões
        'avg_overall' => 4.5,
        'total_analyses' => 10
    ],
    'analyses' => [...],  // Array de análises individuais
    'evolution' => [
        'proactivity' => [
            'first' => 4.2,      // Média da 1ª metade do período
            'second' => 4.8,     // Média da 2ª metade do período
            'change' => 0.6,     // Diferença absoluta
            'percent' => 14.3    // Diferença percentual
        ],
        // ... outras dimensões
    ],
    'badges' => [...],
    'goals' => [...],
    'total_analyses' => 10,
    'top_strengths' => ['Proativo', 'Empático', ...],
    'top_weaknesses' => ['Tempo de resposta', ...]
]
```

### `compareAgents()` retorna:
```php
[
    [
        'agent' => ['id' => 1, 'name' => 'João', ...],
        'overall_score' => 4.5,
        'dimensions' => [
            'proactivity' => 4.5,
            'objection_handling' => 4.3,
            // ... todas as 10 dimensões
        ],
        'total_analyses' => 10
    ],
    // ... outros agentes
]
```

---

## 🔄 Mapeamento de Campos

| View espera | Service retorna | Conversão |
|-------------|----------------|-----------|
| `$report['overall_score']` | `$report['averages']['avg_overall']` | Direto |
| `$report['dimensions']['proactivity']` | `$report['averages']['avg_proactivity']` | Remover prefixo `avg_` |
| `$report['evolution']['proactivity']` (número) | `$report['evolution']['proactivity']['change']` | Acessar chave `change` |
| `$report['top_strengths']` | Agregado de `$analyses[]['strengths']` | Array de strings |
| `$report['top_weaknesses']` | Agregado de `$analyses[]['weaknesses']` | Array de strings |

---

## ✅ Status Final

- ✅ Erro `number_format()` corrigido
- ✅ Estrutura de dados normalizada
- ✅ Pontos fortes e fracos implementados
- ✅ Comparação de agentes corrigida
- ✅ Todas as views funcionando

---

## 🧪 Como Testar

### 1. Criar algumas análises de teste
```bash
php public/scripts/analyze-performance.php
```

### 2. Acessar as páginas
- **Performance Individual:** https://chat.personizi.com.br/agent-performance/agent/{seu_id}
- **Comparar Agentes:** https://chat.personizi.com.br/agent-performance/compare?agents[]=1&agents[]=2

### 3. Verificar se:
- ✅ Notas aparecem corretamente (não arrays)
- ✅ Evolução mostra setas e valores (+0.50, -0.20, etc)
- ✅ Pontos fortes e fracos listados
- ✅ Comparação mostra dados de todos os agentes
