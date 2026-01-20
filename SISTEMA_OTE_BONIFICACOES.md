# Sistema de OTE (On-Target Earnings) e Bonificações

## 📋 Visão Geral

Sistema completo de bonificações baseado em metas com OTE (On-Target Earnings), permitindo configurar salário base, comissão esperada e níveis escalonados de bonificação por desempenho.

## 🎯 Conceito de OTE

**OTE (On-Target Earnings)** = Salário Base + Comissão Esperada ao atingir 100% da meta

### Exemplo:
- **Salário Base**: R$ 3.000/mês
- **Comissão Target (100%)**: R$ 2.000
- **OTE Total**: R$ 5.000/mês

Se o vendedor atingir 100% da meta, ganha R$ 5.000 no mês.

## 💰 Sistema de Bonificações (Tiers)

### Tipos de Cálculo:

1. **Escalonado (Tiered)** - Padrão
   - Substitui o tier anterior
   - Exemplo: 70% = R$ 1.000 (não acumula com 50%)

2. **Cumulativo**
   - Soma todos os tiers atingidos
   - Exemplo: 50% = +R$ 300, 75% = +R$ 400 (total: R$ 700)

3. **Fixo**
   - Valor único ao atingir meta

4. **Percentual**
   - % sobre o valor base

### Tiers Padrão Sugeridos:

| Tier | Threshold | Bônus | Emoji |
|------|-----------|-------|-------|
| Bronze | 50% | R$ 600 (30% da comissão) | 🥉 |
| Prata | 70% | R$ 1.000 (50% da comissão) | 🥈 |
| Ouro | 90% | R$ 1.600 (80% da comissão) | 🥇 |
| Platina | 100% | R$ 2.000 (100% da comissão) | 💎 |
| Diamante | 120% | R$ 3.000 (150% da comissão) | 💠 |

## 🗂️ Estrutura do Banco de Dados

### Tabelas Criadas:

1. **`goals`** - Campos adicionados:
   - `ote_base_salary` - Salário base mensal (R$)
   - `ote_target_commission` - Comissão ao atingir 100% (R$)
   - `ote_total` - OTE Total calculado
   - `enable_bonus` - Habilitar bonificação (0/1)
   - `bonus_calculation_type` - Tipo (fixed, percentage, tiered)

2. **`goal_bonus_tiers`** - Níveis de bonificação:
   - `goal_id` - Referência à meta
   - `threshold_percentage` - % necessário (ex: 70.0)
   - `bonus_amount` - Valor do bônus (R$)
   - `tier_name` - Nome (ex: "Prata 🥈")
   - `tier_color` - Cor hex (#C0C0C0)
   - `is_cumulative` - Se acumula (0/1)
   - `tier_order` - Ordem de exibição

3. **`goal_bonus_earned`** - Bonificações ganhas:
   - `goal_id`, `tier_id`, `user_id`
   - `bonus_amount` - Valor ganho
   - `percentage_achieved` - % atingido
   - `status` - pending/approved/paid/cancelled
   - `period_start`, `period_end` - Período da meta
   - `earned_at`, `paid_at` - Datas

4. **`goal_bonus_payments`** - Histórico de pagamentos:
   - `bonus_earned_id` - Referência
   - `payment_amount` - Valor pago
   - `payment_date`, `payment_method`
   - `paid_by` - Quem pagou

## 🚀 Como Usar

### 1. Criar Meta com OTE

1. Acesse `/goals/create` ou `/goals`
2. Configure a meta normalmente (nome, tipo, valor, período)
3. Na seção **"OTE e Bonificações"**:
   - ✅ Habilitar Sistema de Bonificações
   - Salário Base: R$ 3.000
   - Comissão Target (100%): R$ 2.000
   - OTE Total: R$ 5.000 (calculado automaticamente)
4. Escolha o tipo de cálculo: **Escalonado (Tiers)**
5. Salve a meta

### 2. Configurar Tiers de Bonificação

Duas opções:

**A) Criar Tiers Padrão Automaticamente** (Recomendado):
- No formulário, clique em "Criar Tiers Padrão Automaticamente"
- Sistema cria 5 níveis: Bronze (50%), Prata (70%), Ouro (90%), Platina (100%), Diamante (120%)

**B) Criar Manualmente**:
```sql
INSERT INTO goal_bonus_tiers (goal_id, threshold_percentage, bonus_amount, tier_name, tier_color, tier_order, is_cumulative) VALUES
    (1, 50.0,  600.00,  'Bronze 🥉',   '#CD7F32', 0, 0),
    (1, 70.0,  1000.00, 'Prata 🥈',    '#C0C0C0', 1, 0),
    (1, 90.0,  1600.00, 'Ouro 🥇',     '#FFD700', 2, 0),
    (1, 100.0, 2000.00, 'Platina 💎',  '#E5E4E2', 3, 0),
    (1, 120.0, 3000.00, 'Diamante 💠', '#B9F2FF', 4, 0);
```

### 3. Cálculo Automático

O sistema calcula automaticamente quando o progresso da meta é atualizado:

```php
// Ao atualizar progresso da meta
GoalService::calculateProgress($goalId, date('Y-m-d'));

// Sistema automaticamente:
// 1. Calcula % atingido
// 2. Determina qual tier foi atingido
// 3. Registra bonificação em goal_bonus_earned com status 'pending'
```

### 4. Aprovar e Pagar Bonificações

```php
// Aprovar bonificação
GoalBonusEarned::approve($bonusId, $approvedBy);

// Marcar como pago
GoalBonusEarned::markAsPaid($bonusId);
```

Ou via SQL:
```sql
-- Aprovar
UPDATE goal_bonus_earned 
SET status = 'approved', approved_by = 1, approved_at = NOW() 
WHERE id = 1;

-- Pagar
UPDATE goal_bonus_earned 
SET status = 'paid', paid_at = NOW() 
WHERE id = 1;
```

## 📊 Exemplo Prático Completo

### Cenário:
- **Vendedor**: João Silva
- **Meta**: R$ 200.000 em vendas (Janeiro 2026)
- **OTE**: R$ 3.000 (base) + R$ 2.000 (comissão) = **R$ 5.000**

### Tiers Configurados:
- 🥉 50% (R$ 100k) = R$ 600
- 🥈 70% (R$ 140k) = R$ 1.000
- 🥇 90% (R$ 180k) = R$ 1.600
- 💎 100% (R$ 200k) = R$ 2.000
- 💠 120% (R$ 240k) = R$ 3.000

### Resultados Possíveis:

**1. Vendeu R$ 110.000 (55%)**
- Salário: R$ 3.000
- Bonus (Bronze): R$ 600
- **Total: R$ 3.600**

**2. Vendeu R$ 150.000 (75%)**
- Salário: R$ 3.000
- Bonus (Prata): R$ 1.000
- **Total: R$ 4.000**

**3. Vendeu R$ 200.000 (100%)**
- Salário: R$ 3.000
- Bonus (Platina): R$ 2.000
- **Total: R$ 5.000** ✅ OTE completo!

**4. Vendeu R$ 250.000 (125%)**
- Salário: R$ 3.000
- Bonus (Diamante): R$ 3.000
- **Total: R$ 6.000** 🚀 Superou OTE!

## 📈 Relatórios e Consultas

### Bonificações de um Agente:
```php
$bonuses = GoalBonusEarned::getByAgent($userId);
```

```sql
SELECT 
    g.name as meta,
    gbe.percentage_achieved,
    gbe.bonus_amount,
    gbe.status,
    gbt.tier_name
FROM goal_bonus_earned gbe
INNER JOIN goals g ON gbe.goal_id = g.id
LEFT JOIN goal_bonus_tiers gbt ON gbe.tier_id = gbt.id
WHERE gbe.user_id = 1
ORDER BY gbe.earned_at DESC;
```

### Total de Bonificações por Status:
```sql
SELECT 
    u.name as agente,
    status,
    COUNT(*) as quantidade,
    SUM(bonus_amount) as total
FROM goal_bonus_earned gbe
INNER JOIN users u ON gbe.user_id = u.id
GROUP BY user_id, status
ORDER BY total DESC;
```

### Bonificações Pendentes de Aprovação:
```sql
SELECT 
    u.name as agente,
    g.name as meta,
    gbe.bonus_amount,
    gbe.percentage_achieved,
    gbe.earned_at
FROM goal_bonus_earned gbe
INNER JOIN users u ON gbe.user_id = u.id
INNER JOIN goals g ON gbe.goal_id = g.id
WHERE gbe.status = 'pending'
ORDER BY gbe.earned_at DESC;
```

### Resumo Mensal de um Agente:
```php
$summary = GoalBonusEarned::getAgentSummary($userId, 2026, 1);
// Retorna: total_bonuses, pending_amount, approved_amount, paid_amount, total_amount
```

## 🎨 Interface do Usuário

### Formulário de Metas (`/goals/create`):

1. **Seletor Visual de Badges**: 
   - Ícones clicáveis (🏆 🥇 🥈 🥉 ⭐ 🔥 🚀 💎 👑 🎯 💰 📈)
   - Não é mais campo de texto manual

2. **Seção OTE e Bonificações**:
   - Toggle para habilitar
   - Campos para OTE Base e Comissão
   - Cálculo automático do OTE Total
   - Seletor de tipo de bonificação
   - Botão para criar tiers padrão

### Performance do Agente (`/agent-performance/agent?id=X`):

- Widget de OTE mostrando:
  - Salário base
  - Comissão target
  - OTE Total
  - Bonificações ganhas no mês
  - Status (pending/approved/paid)

## 🔧 Models Criados

### `GoalBonusTier`
```php
// Obter tiers de uma meta
$tiers = GoalBonusTier::getByGoal($goalId);

// Calcular bonus total
$result = GoalBonusTier::calculateBonus($goalId, $percentage);
// Retorna: total_bonus, achieved_tiers, last_tier, next_tier

// Criar tiers padrão
GoalBonusTier::createDefaultTiers($goalId, $targetCommission);
```

### `GoalBonusEarned`
```php
// Registrar bonus ganho
$id = GoalBonusEarned::recordBonus($goalId, $userId, $bonusAmount, $percentage, $tierId);

// Obter bonificações do agente
$bonuses = GoalBonusEarned::getByAgent($userId, 'pending');

// Total por período
$total = GoalBonusEarned::getTotalByPeriod($userId, $startDate, $endDate, 'paid');

// Resumo do mês
$summary = GoalBonusEarned::getAgentSummary($userId, 2026, 1);

// Aprovar/Pagar
GoalBonusEarned::approve($bonusId, $approvedBy);
GoalBonusEarned::markAsPaid($bonusId);
```

## 🔄 Fluxo de Trabalho

```
1. Admin cria meta com OTE configurado
   ↓
2. Sistema ou Admin configura tiers de bonificação
   ↓
3. Agente trabalha e gera vendas/conversões
   ↓
4. Sistema atualiza progresso da meta automaticamente
   ↓
5. GoalService calcula bonificação automaticamente
   ↓
6. Bonificação registrada com status 'pending'
   ↓
7. Admin aprova bonificação (status: 'approved')
   ↓
8. Financeiro marca como pago (status: 'paid')
   ↓
9. Agente visualiza bonificação no dashboard
```

## ✅ Status de Bonificação

- **`pending`**: Aguardando aprovação
- **`approved`**: Aprovado, aguardando pagamento
- **`paid`**: Pago
- **`cancelled`**: Cancelado

## 🎯 Onde Está no Sistema

- **Configurar Meta com OTE**: `/goals/create` ou `/goals/edit?id=X`
- **Ver Metas**: `/goals` (Admin/Supervisor)
- **Ver Minhas Metas**: `/goals/dashboard` (Agente)
- **Performance Individual**: `/agent-performance/agent?id=X`

## 📝 Permissões

```php
// Criar/editar metas com OTE (Admin/Supervisor)
'goals.create'
'goals.edit'

// Ver próprias metas e bonificações (Agente)
'agent_performance.goals.view'

// Aprovar bonificações (Admin/Financeiro)
'goals.approve_bonus' // Criar esta permissão se necessário
```

## 🚀 Próximos Passos (Futuro)

- [ ] Interface para gerenciar tiers (CRUD visual)
- [ ] Interface para aprovar bonificações pendentes
- [ ] Notificações quando bonificação é aprovada/paga
- [ ] Dashboard de bonificações para financeiro
- [ ] Exportar relatório de bonificações (PDF/Excel)
- [ ] Gráficos de bonificações ganhas ao longo do tempo
- [ ] Sistema de aprovação em múltiplos níveis

## 📚 Documentação Relacionada

- `SISTEMA_METAS_COMPLETO.md` - Sistema de Metas base
- `SISTEMA_FLAGS_PROJECOES_METAS.md` - Flags e Projeções
- `ARQUITETURA.md` - Arquitetura geral do sistema

---

**Criado em**: 20/01/2026  
**Status**: ✅ Implementado e Funcional  
**Versão**: 1.0
