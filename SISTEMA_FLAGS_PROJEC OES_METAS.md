# 🚨 Sistema de FLAGS e Projeções de Metas

**Data de Implementação**: 20/01/2026  
**Status**: ✅ Implementado e Completo

---

## 📋 VISÃO GERAL

Sistema avançado de alertas visuais (FLAGS) e projeções de atingimento para metas. Permite configurar thresholds personalizados por meta e calcula automaticamente se o agente está no ritmo esperado para atingir seus objetivos.

---

## 🎯 FUNCIONALIDADES

### 1. Sistema de FLAGS (Alertas Visuais)

Thresholds configuráveis por meta com 4 níveis:

- **🔴 Flag Crítica (Vermelho)** - Situação crítica
  - Padrão: Abaixo de 70% do esperado
  - Gera alertas automáticos
  - Requer atenção imediata

- **🟡 Flag Atenção (Amarelo)** - Atenção necessária
  - Padrão: Entre 70-85% do esperado
  - Alerta de risco
  - Requer acompanhamento

- **🟢 Flag Boa (Verde)** - No caminho certo
  - Padrão: Entre 85-95% do esperado
  - Progresso satisfatório
  - Manter ritmo

- **🔵 Flag Excelente (Azul)** - Meta atingida ou superada
  - Padrão: 100%+ atingido
  - Objetivo alcançado
  - Gamificação ativada

### 2. Cálculo de Projeção

O sistema calcula automaticamente:

- **Dias Decorridos**: Quantos dias já passaram desde o início
- **Dias Restantes**: Quantos dias faltam até o fim
- **% Esperado**: Quanto deveria ter atingido neste momento
- **Projeção Final**: Previsão de % que vai atingir no fim
- **Está no Ritmo?**: Compara real vs esperado
- **Desvio**: Diferença entre real e esperado
- **Necessário por Dia**: Quanto precisa fazer por dia para atingir

### 3. Alertas Automáticos

Gerados automaticamente quando:
- Meta está em situação crítica (< threshold crítico)
- Fora do ritmo esperado (desvio > 5%)
- Risco de não atingir meta (projeção < 100%)
- Marcos importantes (50%, 75%, 90%)

---

## 📐 FÓRMULAS E CÁLCULOS

### Percentual Esperado
```
% Esperado = (Dias Decorridos / Total de Dias) × 100
```

**Exemplo**:
- Meta: 01/01 a 31/01 (31 dias)
- Hoje: 16/01 (16 dias decorridos)
- % Esperado: (16 / 31) × 100 = **51,6%**

### Projeção Linear
```
Média Diária = Valor Atual / Dias Decorridos
Projeção Final = Média Diária × Total de Dias
% Projeção = (Projeção Final / Meta) × 100
```

**Exemplo**:
- Meta: R$ 50.000 em 31 dias
- Valor Atual (dia 16): R$ 20.000
- Média Diária: R$ 20.000 / 16 = R$ 1.250
- Projeção Final: R$ 1.250 × 31 = R$ 38.750
- % Projeção: (R$ 38.750 / R$ 50.000) × 100 = **77,5%**
- **Status**: ⚠ Fora do ritmo! Está em 40%, mas deveria ter 51,6%

### Está no Ritmo?
```
Está no Ritmo = (% Atual >= % Esperado × 0,95)
```

Tolerância de 5% abaixo do esperado.

**Exemplo**:
- % Esperado: 51,6%
- Tolerância: 51,6% × 0,95 = 49%
- % Atual: 40%
- **Resultado**: ❌ Não está no ritmo (40% < 49%)

### Necessário por Dia
```
Necessário/Dia = (Meta - Valor Atual) / Dias Restantes
```

**Exemplo**:
- Meta: R$ 50.000
- Valor Atual: R$ 20.000
- Falta: R$ 30.000
- Dias Restantes: 15
- **Necessário/Dia**: R$ 30.000 / 15 = **R$ 2.000/dia**

---

## 🗄️ ESTRUTURA DO BANCO

### Novos Campos em `goals`

```sql
flag_critical_threshold DECIMAL(5,2) DEFAULT 70.00
flag_warning_threshold  DECIMAL(5,2) DEFAULT 85.00
flag_good_threshold     DECIMAL(5,2) DEFAULT 95.00
enable_projection       TINYINT(1) DEFAULT 1
alert_on_risk           TINYINT(1) DEFAULT 1
template_id             INT NULL  -- Para metas recorrentes
```

### Novos Campos em `goal_progress`

```sql
days_elapsed            INT NULL
days_total              INT NULL
expected_percentage     DECIMAL(5,2) NULL
projection_percentage   DECIMAL(5,2) NULL
projection_value        DECIMAL(12,2) NULL
is_on_track             TINYINT(1) NULL
flag_status             ENUM('critical','warning','good','excellent')
```

### Nova Tabela `goal_alerts`

```sql
CREATE TABLE goal_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    alert_type ENUM('off_track', 'at_risk', 'critical', 'milestone_reached'),
    severity ENUM('info', 'warning', 'critical'),
    message TEXT NOT NULL,
    details JSON NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_resolved TINYINT(1) DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);
```

---

## 💻 EXEMPLOS DE USO

### Criar Meta com Flags Personalizadas

```php
use App\Services\GoalService;

$goalId = GoalService::create([
    'name' => 'Meta de Vendas - Janeiro 2026',
    'type' => 'revenue',
    'target_type' => 'individual',
    'target_id' => 5,
    'target_value' => 200000.00,  // R$ 200 mil
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
    
    // FLAGS personalizadas
    'flag_critical_threshold' => 70.0,  // Vermelho < 70%
    'flag_warning_threshold' => 80.0,   // Amarelo < 80%
    'flag_good_threshold' => 90.0,      // Verde < 90%
    
    // Projeções e alertas
    'enable_projection' => 1,
    'alert_on_risk' => 1
]);
```

### Calcular Progresso com Projeção

```php
$progress = GoalService::calculateProgress($goalId);

/*
Retorna:
[
    'goal_id' => 1,
    'current_value' => 140000.00,  // R$ 140k atingido
    'target_value' => 200000.00,   // R$ 200k meta
    'percentage' => 70.00,          // 70% atingido
    'status' => 'in_progress',
    'flag_status' => 'warning',     // 🟡 Amarelo
    'projection' => [
        'days_total' => 31,
        'days_elapsed' => 20,
        'days_remaining' => 11,
        'expected_percentage' => 64.52,  // Deveria ter 64,52%
        'projected_value' => 217000.00,  // Projeção: R$ 217k
        'projected_percentage' => 108.50, // Projeção: 108,5%
        'is_on_track' => true,           // ✓ No ritmo!
        'deviation' => 5.48,              // 5,48% acima do esperado
        'needs_daily' => 5454.55          // R$ 5.454,55/dia
    ]
]
*/
```

### Obter Alertas do Agente

```php
use App\Models\GoalAlert;

$alerts = GoalAlert::getAlertsForAgent($agentId, $onlyUnread = true);

foreach ($alerts as $alert) {
    echo "📣 {$alert['goal_name']}: {$alert['message']}\n";
}

/*
Output:
📣 Meta de Vendas - Janeiro: Fora do ritmo esperado! Desvio de 10%. 
   Esperado: 65%, Atual: 55%.
   
📣 Meta de Conversão: Risco de não atingir meta! Projeção atual: 85%.
*/
```

### Duplicar Meta para Próximo Mês

```php
// Duplicar meta de Janeiro para Fevereiro
$newGoalId = Goal::duplicateAsTemplate(
    $goalId,           // ID da meta original
    '2026-02-01',      // Início do novo período
    '2026-02-28',      // Fim do novo período
    'Meta de Vendas - Fevereiro 2026'  // Novo nome
);
```

### Criar Metas Mensais (Janeiro a Dezembro)

```php
// Via API
POST /api/goals/create-monthly
{
    "goal_id": 123,
    "year": 2026
}

// Cria automaticamente 12 metas (uma para cada mês)
// Com nomes: "Meta Original - Janeiro/2026", "Meta Original - Fevereiro/2026", etc.
```

---

## 📊 INTERFACE DO USUÁRIO

### Formulário de Meta - Seção de FLAGS

```
┌────────────────────────────────────────────────────────┐
│ Configuração de Flags e Alertas                       │
├────────────────────────────────────────────────────────┤
│                                                        │
│ 🔴 Flag Crítica (Vermelho)     [Abaixo de] [70] [%]  │
│    Situação crítica                                    │
│                                                        │
│ 🟡 Flag Atenção (Amarelo)      [Abaixo de] [85] [%]  │
│    Requer atenção                                      │
│                                                        │
│ 🟢 Flag Boa (Verde)            [Abaixo de] [95] [%]  │
│    No caminho certo                                    │
│                                                        │
│ ☑ Habilitar Projeção de Atingimento                   │
│ ☑ Alertar Quando em Risco                            │
└────────────────────────────────────────────────────────┘
```

### Performance do Agente - Widget de Alertas

```
┌────────────────────────────────────────────────────────┐
│ 🚨 Alertas de Metas (3)                               │
├────────────────────────────────────────────────────────┤
│ ⚠ Meta de Vendas - Janeiro                           │
│   Fora do ritmo esperado! Desvio de 10%.             │
│   Esperado: 65%, Atual: 55%                           │
│   20/01/2026 10:30                                    │
├────────────────────────────────────────────────────────┤
│ 🔴 Meta de Ticket Médio                              │
│   Meta em situação crítica! Apenas 45% atingido.     │
│   11 dias restantes.                                  │
│   19/01/2026 15:45                                    │
└────────────────────────────────────────────────────────┘
```

### Card de Meta com Projeção

```
┌────────────────────────────────────────────────────────┐
│ 🟡 Meta de Vendas - Janeiro                          │
│    Faturamento Total                                   │
│    ✓ No ritmo (Projeção: 108%)                        │
│                                            70% [55%]   │
│ ████████████████░░░░░░░░                              │
│ R$ 140.000                              R$ 200.000    │
│                                                        │
│ Esperado hoje: 64,5% | Projeção: 108,5%              │
│ Necessário/dia: R$ 5.454,55                           │
└────────────────────────────────────────────────────────┘
```

---

## 🎨 CORES E BADGES

### Cores por Flag
- `critical` → `bg-danger` / `text-danger` (Vermelho)
- `warning` → `bg-warning` / `text-warning` (Amarelo)
- `good` → `bg-success` / `text-success` (Verde)
- `excellent` → `bg-primary` / `text-primary` (Azul)

### Badges de Status
- ❌ Fora do ritmo - `badge-light-danger`
- ✓ No ritmo - `badge-light-success`
- 🔴 Flag Crítica - `badge-danger`
- 🟡 Flag Atenção - `badge-warning`
- 🟢 Flag Boa - `badge-success`
- 🔵 Meta Atingida - `badge-primary`

---

## 🔔 TIPOS DE ALERTAS

### 1. Off Track (Fora do Ritmo)
**Severidade**: Warning  
**Quando**: Desvio > 5% do esperado  
**Mensagem**: "Fora do ritmo esperado! Desvio de X%."

### 2. At Risk (Em Risco)
**Severidade**: Warning  
**Quando**: Projeção < 100%  
**Mensagem**: "Risco de não atingir meta! Projeção atual: X%."

### 3. Critical (Crítico)
**Severidade**: Critical  
**Quando**: % < threshold crítico  
**Mensagem**: "Meta em situação crítica! Apenas X% atingido."

### 4. Milestone Reached (Marco Atingido)
**Severidade**: Info  
**Quando**: Atinge 25%, 50%, 75%, 90%, 100%  
**Mensagem**: "Parabéns! Você atingiu X% da meta!"

---

## ⚙️ CONFIGURAÇÃO

### Valores Padrão

```php
$defaults = [
    'flag_critical_threshold' => 70.0,  // < 70% = Crítico
    'flag_warning_threshold' => 85.0,   // < 85% = Atenção
    'flag_good_threshold' => 95.0,      // < 95% = Bom
    'enable_projection' => 1,            // Projeção habilitada
    'alert_on_risk' => 1                 // Alertas habilitados
];
```

### Exemplos de Configurações

**Meta Agressiva (Startup)**:
```php
'flag_critical_threshold' => 80.0,  // Mais exigente
'flag_warning_threshold' => 90.0,
'flag_good_threshold' => 97.0
```

**Meta Realista (Empresa Estabelecida)**:
```php
'flag_critical_threshold' => 60.0,  // Mais tolerante
'flag_warning_threshold' => 75.0,
'flag_good_threshold' => 90.0
```

**Meta Desafiadora (Stretch Goal)**:
```php
'is_stretch' => 1,
'flag_critical_threshold' => 50.0,  // Muito tolerante
'flag_warning_threshold' => 70.0,
'flag_good_threshold' => 85.0
```

---

## 📈 CASOS DE USO

### Caso 1: Vendedor Fora do Ritmo

**Situação**:
- Meta: R$ 100k em Janeiro (31 dias)
- Dia 20: R$ 40k atingido (40%)
- Esperado: 64,5%
- Desvio: -24,5%

**Sistema Detecta**:
- 🔴 Flag Crítica (40% < 70%)
- ❌ Fora do ritmo
- Projeção: 62% (R$ 62k)
- Alerta gerado automaticamente

**Ação Sugerida**:
- Necessário: R$ 5.454/dia (vs atual R$ 2.000/dia)
- Supervisor notificado
- Reunião de alinhamento

### Caso 2: Vendedor no Caminho Certo

**Situação**:
- Meta: 50 vendas em Fevereiro (28 dias)
- Dia 14: 28 vendas (56%)
- Esperado: 50%
- Desvio: +6%

**Sistema Detecta**:
- 🟢 Flag Boa (56% entre 50-95%)
- ✓ No ritmo
- Projeção: 112% (56 vendas)
- Sem alertas

**Ação Sugerida**:
- Manter ritmo atual
- Possível incentivo extra

### Caso 3: Meta Atingida Antecipadamente

**Situação**:
- Meta: Taxa de Resolução 90% em Março
- Dia 20: 92% atingido
- Esperado: 64,5%

**Sistema Detecta**:
- 🔵 Flag Excelente (100%+)
- ✓ Meta atingida
- Conquista registrada
- Gamificação ativada

**Ação Sugerida**:
- Pontos concedidos
- Badge atribuído
- Reconhecimento público

---

## 🚀 INSTALAÇÃO

```bash
# 1. Rodar migration
cd /var/www/html
php database/migrate.php

# 2. Verificar tabelas criadas
mysql> SHOW TABLES LIKE 'goal%';
# goal_achievements
# goal_alerts
# goal_progress
# goals

# 3. Testar cálculo
php -r "require 'bootstrap.php'; \App\Services\GoalService::calculateAllProgress();"
```

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Projeção Linear**: Assume ritmo constante. Não considera sazonalidade ou eventos pontuais.

2. **Tolerância de 5%**: Sistema considera "no ritmo" se está até 5% abaixo do esperado.

3. **Alertas Não Duplicam**: Mesmo alerta não é gerado duas vezes em 24h.

4. **Metas Recorrentes**: Use `template_id` para rastrear séries de metas (Janeiro, Fevereiro, etc).

5. **Performance**: Cálculo de projeção é feito sob demanda (não em tempo real).

---

## 📞 SUPORTE

Sistema integrado ao multiatendimento.  
Documentado em: `SISTEMA_FLAGS_PROJECOES_METAS.md`

**Desenvolvido em**: 20/01/2026  
**Versão**: 1.1.0
