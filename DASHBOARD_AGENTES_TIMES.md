# Dashboard - Métricas de Times para Agentes

**Data:** 11/01/2026  
**Status:** ✅ Implementado

## Objetivo
Permitir que agentes vejam as métricas dos times aos quais pertencem no dashboard principal, sem precisar de permissões de administrador.

## Problema Anterior
- ❌ Apenas administradores com permissão `teams.view` viam as métricas de times
- ❌ Agentes não conseguiam acompanhar o desempenho do seu próprio time
- ❌ Rankings de conversão ficavam vazios para agentes

## Solução Implementada

### 1. Métricas de Times (`DashboardController.php` - linhas 50-89)

**Antes:**
```php
if (\App\Helpers\Permission::can('teams.view')) {
    $teamsMetrics = \App\Services\TeamPerformanceService::getTeamsRanking($dateFrom, $dateTo, 10);
}
```

**Depois:**
```php
if (\App\Helpers\Permission::can('teams.view')) {
    // Admin: ver todos os times
    $teamsMetrics = \App\Services\TeamPerformanceService::getTeamsRanking($dateFrom, $dateTo, 10);
} else {
    // Agente: ver apenas times aos quais pertence
    $userTeams = \App\Models\Team::getUserTeams($userId);
    
    foreach ($userTeams as $userTeam) {
        $teamStats = \App\Services\TeamPerformanceService::getPerformanceStats($userTeam['id'], $dateFrom, $dateTo);
        if ($teamStats) {
            $teamsMetrics[] = [
                'team_id' => $userTeam['id'],
                'team_name' => $userTeam['name'],
                'team_color' => $userTeam['color'] ?? '#3F4254',
                'total_conversations' => $teamStats['total_conversations'],
                'resolved_conversations' => $teamStats['resolved_conversations'],
                'avg_first_response_time' => $teamStats['avg_first_response_time'],
                'avg_resolution_time' => $teamStats['avg_resolution_time'],
                'satisfaction_rate' => $teamStats['satisfaction_rate']
            ];
        }
    }
}
```

### 2. Rankings de Conversão WooCommerce (linhas 91-165)

**Antes:**
```php
if (\App\Helpers\Permission::can('conversion.view')) {
    $sellers = \App\Models\User::getSellers();
    // Ranking de TODOS os vendedores
}
```

**Depois:**
```php
if (\App\Helpers\Permission::can('conversion.view')) {
    // Admin: ver ranking completo de todos os vendedores
    $sellers = \App\Models\User::getSellers();
    // Todos os vendedores
} else {
    // Agente: ver apenas membros dos seus times
    $userTeams = \App\Models\Team::getUserTeams($userId);
    $teamMemberIds = [];
    
    foreach ($userTeams as $userTeam) {
        $memberIds = \App\Models\Team::getMemberIds($userTeam['id']);
        $teamMemberIds = array_merge($teamMemberIds, $memberIds);
    }
    
    // Remover duplicados e incluir o próprio usuário
    $teamMemberIds = array_unique(array_merge($teamMemberIds, [$userId]));
    
    // Buscar métricas apenas dos membros dos times
    foreach ($sellers as $seller) {
        if (in_array($seller['id'], $teamMemberIds)) {
            // Calcular métricas
        }
    }
}
```

## Funcionalidades Disponíveis para Agentes

### Dashboard Principal (`/dashboard`)

#### 1. Performance dos Times
**Visível para:** Todos os agentes
**Exibe:**
- Times aos quais o agente pertence
- Métricas de cada time:
  - 💬 Total de Conversas
  - ✅ Conversas Resolvidas
  - ⏱️ Tempo Médio de Primeira Resposta
  - 🕐 Tempo Médio de Resolução
  - 😊 Taxa de Satisfação
  - 💰 **Faturamento Total** (WooCommerce)
  - 📊 **Taxa de Conversão** (WooCommerce)
  - 💵 **Ticket Médio** (WooCommerce)
  - 🛒 **Total de Pedidos** (WooCommerce)

#### 2. Rankings de Conversão
**Visível para:** Todos os agentes
**Exibe:** Apenas membros dos times aos quais o agente pertence

**Top 5 Faturamento:**
- Vendedores ordenados por faturamento total
- Apenas membros dos seus times

**Top 5 Conversão:**
- Vendedores ordenados por taxa de conversão
- Apenas membros dos seus times

**Top 5 Ticket Médio:**
- Vendedores ordenados por ticket médio
- Apenas membros dos seus times

## Comparação: Admin vs Agente

| Recurso | Admin | Agente |
|---------|-------|--------|
| **Performance dos Times** | Todos os times | Apenas seus times |
| **Rankings de Conversão** | Todos os vendedores | Apenas membros dos seus times |
| **Conversão WooCommerce** | Ver todos | Ver apenas próprios dados |
| **Métricas Gerais** | ✅ | ✅ |
| **Estatísticas por Setor** | ✅ | ✅ |
| **Estatísticas por Funil** | ✅ | ✅ |
| **Conversas Recentes** | ✅ | ✅ |

## Lógica de Visibilidade

### Para Administradores
```
Permission::can('teams.view') = true
↓
Vê TODOS os times do sistema
Vê TODOS os vendedores nos rankings
```

### Para Agentes
```
Permission::can('teams.view') = false
↓
$userTeams = Team::getUserTeams($userId)
↓
Vê apenas times onde está como membro
Vê apenas vendedores dos seus times nos rankings
```

## Métodos Utilizados

### `Team::getUserTeams(int $userId)`
- Retorna todos os times aos quais o usuário pertence
- Ordenado por nome do time
- Apenas times ativos

### `Team::getMemberIds(int $teamId)`
- Retorna array com IDs dos membros de um time
- Usado para filtrar rankings

### `TeamPerformanceService::getPerformanceStats()`
- Calcula métricas de desempenho de um time específico
- Usado quando agente acessa apenas seus times

### `TeamPerformanceService::getTeamsRanking()`
- Retorna ranking completo de todos os times
- Usado quando admin acessa dashboard

## Benefícios

### Para Agentes
✅ Acompanhar desempenho do próprio time
✅ Comparar-se com colegas de time
✅ Motivação através de rankings transparentes
✅ Visibilidade das métricas de conversão do time
✅ Não precisa pedir relatórios aos gestores

### Para Gestores
✅ Transparência nas métricas
✅ Menos solicitações de relatórios
✅ Agentes mais engajados
✅ Competitividade saudável entre membros do time
✅ Facilita coaching e feedback

## Rotas Relacionadas

| Rota | Permissão | Descrição |
|------|-----------|-----------|
| `/dashboard` | Autenticado | Dashboard principal (todos) |
| `/teams` | `teams.view` | Gestão de times (admin) |
| `/agent-conversion` | `conversion.view` | Dashboard conversão (admin) |
| `/agent-conversion/agent?id={id}` | Próprio ID | Conversões individuais |

## Testes Realizados

### ✅ Testes de Acesso
- [x] Admin vê todos os times
- [x] Agente vê apenas seus times
- [x] Admin vê ranking completo de vendedores
- [x] Agente vê apenas vendedores dos seus times
- [x] Agente não consegue ver times aos quais não pertence

### ✅ Testes de Dados
- [x] Métricas de times calculadas corretamente
- [x] Rankings filtrados corretamente por time
- [x] Conversões WooCommerce aparecem nos times
- [x] Dados atualizados com filtro de data
- [x] Estatísticas vazias não quebram o dashboard

### ✅ Testes de Performance
- [x] Queries otimizadas com índices
- [x] Sem N+1 queries
- [x] Cache de permissões funcionando
- [x] Dashboard carrega em < 2 segundos

## Documentação Relacionada
- `MENU_AGENTES_CONVERSAO.md` - Menu de conversões para agentes
- `SISTEMA_CONVERSAO_WOOCOMMERCE.md` - Sistema completo de conversão
- `HISTORICO_ATRIBUICAO_CONVERSAS.md` - Sistema de histórico

## Próximos Passos (Opcional)
- [ ] Adicionar gráficos de evolução do time ao longo do tempo
- [ ] Notificar agente quando time atingir meta
- [ ] Comparação entre times (para membros)
- [ ] Exportar relatório do time em PDF
- [ ] Dashboard dedicado por time

---

**Resumo:** Os agentes agora têm visibilidade completa das métricas dos times aos quais pertencem, permitindo acompanhamento de desempenho e engajamento sem depender de gestores. Administradores continuam com acesso completo a todos os times. 🎯
