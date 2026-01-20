# 🎯 Sistema de Metas Completo

**Data de Implementação**: 20/01/2026  
**Status**: ✅ Implementado e Completo

---

## 📋 VISÃO GERAL

Sistema completo de gerenciamento de metas para acompanhamento de desempenho de agentes, times, departamentos e empresa. Suporta múltiplos tipos de métricas, períodos personalizáveis, gamificação e cálculo automático de progresso.

---

## 🎯 FUNCIONALIDADES

### Tipos de Metas Suportadas

1. **Vendas e Conversão**
   - 💰 **Faturamento Total** (`revenue`) - Valor total em vendas (R$)
   - 🎫 **Ticket Médio** (`average_ticket`) - Valor médio por venda (R$)
   - 📈 **Taxa de Conversão** (`conversion_rate`) - Percentual de conversões (%)
   - 🛒 **Quantidade de Vendas** (`sales_count`) - Número de vendas realizadas

2. **Atendimento**
   - 💬 **Quantidade de Conversas** (`conversations_count`) - Total de conversas atendidas
   - ✅ **Taxa de Resolução** (`resolution_rate`) - Percentual de conversas resolvidas (%)
   - ⏱️ **Tempo de Resposta** (`response_time`) - Tempo médio de resposta (minutos)
   - ⚡ **Tempo de Primeira Resposta** (`first_response_time`) - Primeira resposta (minutos)
   - 🏁 **Tempo de Resolução** (`resolution_time`) - Tempo até resolver (minutos)

3. **Qualidade**
   - ⭐ **CSAT Médio** (`csat_score`) - Satisfação do cliente (1-5)
   - 📊 **Taxa de Cumprimento SLA** (`sla_compliance`) - Percentual dentro do SLA (%)
   - 📨 **Mensagens Enviadas** (`messages_sent`) - Total de mensagens

### Níveis de Metas

- **🧑 Individual**: Meta para um agente específico
- **👥 Time/Equipe**: Meta para um time inteiro
- **🏢 Departamento**: Meta para um departamento/setor
- **🌐 Global**: Meta para toda a empresa

### Períodos

- **📅 Diário**: Metas diárias
- **📆 Semanal**: Metas semanais
- **🗓️ Mensal**: Metas mensais
- **📊 Trimestral**: Metas trimestrais
- **📈 Anual**: Metas anuais
- **⚙️ Personalizado**: Período customizado

### Configurações Avançadas

- **Prioridade**: Baixa, Média, Alta, Crítica
- **Meta Desafiadora** (Stretch Goal): Metas mais ambiciosas
- **Notificações**: Alertas ao atingir X% da meta
- **Gamificação**: Pontos e badges ao completar
- **Status Automático**: not_started, in_progress, achieved, exceeded, failed

---

## 📁 ESTRUTURA DE ARQUIVOS

### Banco de Dados
```
database/migrations/121_create_goals_system.php
database/seeds/002_create_roles_and_permissions.php (permissões adicionadas)
```

### Models
```
app/Models/Goal.php               - Meta principal
app/Models/GoalProgress.php       - Progresso diário
app/Models/GoalAchievement.php    - Conquistas
```

### Services
```
app/Services/GoalService.php      - Lógica de negócio e cálculos
```

### Controller
```
app/Controllers/GoalController.php - CRUD e APIs
```

### Views
```
views/goals/index.php      - Listagem de metas
views/goals/form.php       - Criar/Editar meta
views/goals/dashboard.php  - Dashboard pessoal de metas
```

### Integrações
```
views/dashboard/index.php          - Metas no dashboard principal
views/agent-performance/agent.php  - Metas na performance individual
app/Controllers/DashboardController.php
app/Controllers/AgentPerformanceController.php
```

### Rotas
```php
GET  /goals                     - Listar metas
GET  /goals/create              - Formulário criar
POST /goals/store               - Salvar nova meta
GET  /goals/dashboard           - Dashboard pessoal
GET  /goals/show?id=X           - Detalhes da meta
GET  /goals/edit?id=X           - Formulário editar
POST /goals/update              - Atualizar meta
POST /goals/delete              - Deletar meta
GET  /api/goals/calculate?id=X  - Calcular progresso de uma meta
POST /api/goals/calculate-all   - Calcular todas as metas
GET  /api/goals/agent?agent_id=X - Metas de um agente (JSON)
```

---

## 🔐 PERMISSÕES

```php
'goals.view'     - Ver metas e progresso
'goals.create'   - Criar novas metas
'goals.edit'     - Editar metas existentes
'goals.delete'   - Deletar metas
```

**Roles com acesso:**
- Super Admin: ✅ Todas as permissões
- Admin: ✅ Todas as permissões
- Supervisor: Pode ver e criar metas para sua equipe
- Agentes: Podem ver suas próprias metas

---

## 🗄️ ESTRUTURA DO BANCO

### Tabela `goals`

```sql
CREATE TABLE goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificação
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    
    -- Tipo e Nível
    type ENUM('revenue', 'average_ticket', 'conversion_rate', 'sales_count', 
              'conversations_count', 'resolution_rate', 'response_time', 
              'csat_score', 'messages_sent', 'sla_compliance', 
              'first_response_time', 'resolution_time') NOT NULL,
    target_type ENUM('individual', 'team', 'department', 'global') NOT NULL,
    target_id INT NULL,
    
    -- Valor Alvo e Período
    target_value DECIMAL(12,2) NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    
    -- Configurações
    is_active TINYINT(1) DEFAULT 1,
    is_stretch TINYINT(1) DEFAULT 0,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    
    -- Gamificação
    notify_at_percentage INT DEFAULT 90,
    reward_points INT DEFAULT 0,
    reward_badge VARCHAR(50) NULL,
    
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Tabela `goal_progress`

```sql
CREATE TABLE goal_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    
    -- Progresso
    date DATE NOT NULL,
    current_value DECIMAL(12,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    status ENUM('not_started', 'in_progress', 'achieved', 'exceeded', 'failed') DEFAULT 'in_progress',
    
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_goal_date (goal_id, date),
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);
```

### Tabela `goal_achievements`

```sql
CREATE TABLE goal_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goal_id INT NOT NULL,
    
    achieved_at TIMESTAMP NOT NULL,
    final_value DECIMAL(12,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    days_to_achieve INT NOT NULL,
    
    points_awarded INT DEFAULT 0,
    badge_awarded VARCHAR(50) NULL,
    
    notification_sent TINYINT(1) DEFAULT 0,
    notification_sent_at TIMESTAMP NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (goal_id) REFERENCES goals(id) ON DELETE CASCADE
);
```

---

## 💻 EXEMPLOS DE USO

### Criar Meta de Vendas para Agente

```php
use App\Services\GoalService;

$goalId = GoalService::create([
    'name' => 'Meta de Vendas - Janeiro 2026',
    'description' => 'Atingir 50 mil em vendas no mês',
    'type' => 'revenue',
    'target_type' => 'individual',
    'target_id' => 5, // ID do agente
    'target_value' => 50000.00,
    'period_type' => 'monthly',
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
    'priority' => 'high',
    'reward_points' => 100,
    'reward_badge' => 'top_seller_jan',
    'created_by' => 1
]);
```

### Criar Meta de Conversão para Time

```php
$goalId = GoalService::create([
    'name' => 'Meta de Conversão - Time A',
    'type' => 'conversion_rate',
    'target_type' => 'team',
    'target_id' => 3, // ID do time
    'target_value' => 25.0, // 25%
    'period_type' => 'quarterly',
    'start_date' => '2026-01-01',
    'end_date' => '2026-03-31',
    'is_stretch' => true,
    'notify_at_percentage' => 80
]);
```

### Calcular Progresso

```php
// Calcular progresso de uma meta específica
$progress = GoalService::calculateProgress($goalId);
/*
Retorna:
[
    'goal_id' => 1,
    'current_value' => 32500.00,
    'target_value' => 50000.00,
    'percentage' => 65.00,
    'status' => 'in_progress'
]
*/

// Calcular todas as metas ativas
$results = GoalService::calculateAllProgress();
```

### Obter Metas de um Agente

```php
use App\Models\Goal;

$goals = Goal::getAgentGoals($agentId);
/*
Retorna:
[
    'individual' => [...], // Metas individuais
    'team' => [...],       // Metas dos times
    'department' => [...], // Metas do departamento
    'global' => [...]      // Metas globais
]
*/
```

### Dashboard Summary

```php
use App\Services\GoalService;

$summary = GoalService::getDashboardSummary($userId);
/*
Retorna:
[
    'total_goals' => 12,
    'achieved' => 3,
    'in_progress' => 7,
    'at_risk' => 2,
    'goals_by_level' => [
        'individual' => [...],
        'team' => [...],
        'department' => [...],
        'global' => [...]
    ]
]
*/
```

---

## ⚙️ CÁLCULO AUTOMÁTICO

### Como Funciona

O sistema calcula automaticamente o progresso das metas baseado nos dados reais:

1. **Identifica os agentes**: Determina quais agentes fazem parte da meta (individual, time, departamento, global)
2. **Busca dados**: Consulta o banco de dados para o período especificado
3. **Calcula valor atual**: Soma/média/percentual conforme o tipo de meta
4. **Atualiza progresso**: Salva em `goal_progress` com data e percentual
5. **Verifica conquista**: Se atingiu 100%, registra em `goal_achievements`

### Fontes de Dados

- **Vendas**: `woocommerce_conversions` via `conversation_assignments`
- **Conversas**: `conversations` + `conversation_assignments`
- **Mensagens**: `messages` filtrado por agente
- **Tempo de Resposta**: Cálculo entre mensagens do cliente e agente
- **CSAT**: `conversation_surveys`
- **SLA**: Comparação de tempos com configurações

### Gatilhos de Atualização

- Manualmente via admin (`/api/goals/calculate-all`)
- Via cron job (recomendado: diário às 00:00)
- Automaticamente ao visualizar dashboard de metas

---

## 📊 INTEGRAÇÃO NO SISTEMA

### Dashboard Principal

Exibe resumo das metas do usuário:
- Total de metas
- Metas atingidas
- Metas em progresso
- Metas em risco
- Top 4 metas individuais com progresso visual

### Performance Individual do Agente

Exibe no card "Metas":
- Resumo de conquistas e progresso
- Top 3 metas mais relevantes
- Link para dashboard completo de metas

### Gamificação

Integra com sistema de badges e pontos:
- Ao atingir meta, concede pontos configurados
- Pode atribuir badge específico
- Registra conquista em timeline

---

## 🔄 FLUXO COMPLETO

### 1. Criação da Meta

```mermaid
Admin/Supervisor
    → Acessa /goals/create
    → Preenche formulário
    → Define tipo, nível, valor alvo, período
    → Configura gamificação (pontos, badge)
    → Salva
    → Meta criada com progresso inicial calculado
```

### 2. Cálculo de Progresso

```mermaid
Sistema (Cron Diário)
    → Busca todas as metas ativas
    → Para cada meta:
        → Identifica agentes envolvidos
        → Busca dados do período
        → Calcula valor atual
        → Calcula percentual
        → Determina status
        → Salva em goal_progress
        → Se atingiu 100%:
            → Registra em goal_achievements
            → Concede pontos e badge
            → Envia notificação
```

### 3. Visualização

```mermaid
Usuário
    → Acessa Dashboard ou /goals/dashboard
    → Vê resumo de suas metas
    → Clica em meta específica
    → Vê detalhes, histórico, progresso gráfico
    → Pode compartilhar conquistas
```

---

## 🎨 INTERFACE DO USUÁRIO

### Listagem de Metas (`/goals`)

- Tabela com todas as metas
- Filtros por tipo, nível, período
- Barra de progresso visual
- Badges de status
- Ações: Ver, Editar, Deletar

### Formulário (`/goals/create` e `/goals/edit`)

- Campos organizados em seções:
  - Informações Básicas
  - Configuração da Meta
  - Período
  - Opções Avançadas
- Seleção dinâmica de target (agente/time/departamento)
- Auto-preenchimento de datas baseado no período
- Unidades dinâmicas (R$, %, min, etc)

### Dashboard Pessoal (`/goals/dashboard`)

- 4 Cards de resumo (Total, Atingidas, Em Progresso, Em Risco)
- Seções por nível (Individual, Time, Departamento, Global)
- Progresso visual com cores (verde, azul, amarelo, vermelho)
- Timeline de conquistas recentes
- Link para ver todas as metas

---

## 🚀 INSTALAÇÃO E CONFIGURAÇÃO

### 1. Rodar Migration

```bash
cd /var/www/html
php database/migrate.php
```

### 2. Rodar Seed (Permissões)

```bash
php database/seed.php
```

### 3. Configurar Cron Job (Recomendado)

```bash
# Calcular progresso diariamente às 00:05
5 0 * * * cd /var/www/html && php -r "require 'bootstrap.php'; \App\Services\GoalService::calculateAllProgress();"
```

### 4. Atribuir Permissões

```sql
-- Permitir que supervisores gerenciem metas
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.slug = 'supervisor'
AND p.slug IN ('goals.view', 'goals.create', 'goals.edit');
```

---

## 📈 MÉTRICAS E KPIs

### Métricas do Sistema

- Total de metas ativas
- Taxa de atingimento global
- Tempo médio para atingir metas
- Metas mais frequentes (por tipo)
- Agentes com mais conquistas

### Relatórios Disponíveis

- Ranking de atingimento de metas
- Evolução do progresso (histórico)
- Comparação entre times
- Análise de stretch goals

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Histórico de Atribuições**: O sistema usa `conversation_assignments` para garantir que métricas sejam atribuídas corretamente mesmo quando há transferências entre setores/agentes.

2. **Cálculo Sob Demanda**: Progresso é calculado sob demanda (não pré-computado), garantindo dados sempre atualizados.

3. **Performance**: Queries otimizadas com índices nas tabelas de metas e progresso.

4. **Múltiplas Metas**: Um agente pode ter múltiplas metas simultâneas de diferentes tipos e níveis.

5. **Conquistas Únicas**: Cada meta só pode ser conquistada uma vez (registro único em `goal_achievements`).

6. **Notificações**: Sistema preparado para enviar notificações (TODO: integrar com sistema de notificações existente).

---

## 🔮 POSSÍVEIS EXPANSÕES FUTURAS

- [ ] Gráficos de evolução do progresso
- [ ] Comparação entre períodos
- [ ] Metas em cascata (meta do time influencia meta individual)
- [ ] Ajuste automático de metas baseado em desempenho histórico
- [ ] Integração com sistema de comissões
- [ ] Exportação de relatórios de metas (PDF/Excel)
- [ ] Metas baseadas em fórmulas customizadas
- [ ] Alertas proativos (meta em risco de não ser atingida)
- [ ] Competições entre times

---

## 📞 SUPORTE

Sistema integrado ao multiatendimento/multicanal.  
Documentado em: `SISTEMA_METAS_COMPLETO.md`

**Desenvolvido em**: 20/01/2026  
**Versão**: 1.0.0
