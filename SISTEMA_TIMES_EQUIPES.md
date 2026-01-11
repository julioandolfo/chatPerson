# 👥 Sistema de Times/Equipes

**Data de Implementação**: 2026-01-11  
**Status**: ✅ Implementado e Completo

---

## 📋 VISÃO GERAL

Sistema completo para organização de agentes em times/equipes, com métricas agregadas e dashboard comparativo.

---

## 🎯 FUNCIONALIDADES

### Gerenciamento de Times
- ✅ Criar times com nome, descrição e cor
- ✅ Definir líder do time
- ✅ Associar time a um setor
- ✅ Adicionar/remover múltiplos agentes
- ✅ Ativar/desativar times
- ✅ Editar informações do time
- ✅ Deletar times (remove todos os membros)

### Métricas Agregadas
- ✅ Total de conversas do time
- ✅ Conversas resolvidas/fechadas
- ✅ Taxa de resolução (%)
- ✅ Tempo médio de primeira resposta
- ✅ Tempo médio de resolução
- ✅ Conversas por status
- ✅ Performance individual de cada membro
- ✅ Ranking de times

### Dashboard de Times
- ✅ Visão geral de todos os times
- ✅ Ranking ordenável (por conversas, taxa de resolução, etc)
- ✅ Filtros por período
- ✅ Comparação entre times
- ✅ Estatísticas consolidadas

---

## 📁 ESTRUTURA DE ARQUIVOS

### Banco de Dados
```
database/migrations/098_create_teams_tables.php
database/seeds/002_create_roles_and_permissions.php (permissões adicionadas)
```

### Models
```
app/Models/Team.php
app/Models/TeamMember.php
```

### Services
```
app/Services/TeamService.php           - CRUD e gerenciamento
app/Services/TeamPerformanceService.php - Métricas agregadas
```

### Controller
```
app/Controllers/TeamController.php
```

### Views
```
views/teams/index.php      - Listagem de times
views/teams/form.php       - Criar/Editar
views/teams/show.php       - Detalhes + Métricas
views/teams/dashboard.php  - Dashboard comparativo
```

### Rotas
```php
GET  /teams                  - Listar times
GET  /teams/create           - Formulário criar
POST /teams                  - Salvar novo time
GET  /teams/show?id=X        - Detalhes do time
GET  /teams/edit?id=X        - Formulário editar
POST /teams/update           - Atualizar time
POST /teams/delete           - Deletar time
GET  /teams/dashboard        - Dashboard de times
GET  /teams/performance      - API: Performance de um time (JSON)
POST /teams/compare          - API: Comparar times (JSON)
```

---

## 🔐 PERMISSÕES

```php
'teams.view'            - Ver times/equipes
'teams.create'          - Criar times/equipes
'teams.edit'            - Editar times/equipes
'teams.delete'          - Deletar times/equipes
'teams.manage_members'  - Gerenciar membros de times
```

**Roles com acesso:**
- Super Admin: ✅ Todas as permissões
- Admin: ✅ Todas as permissões
- Supervisor: Pode ser concedido conforme necessário
- Agentes: Podem apenas visualizar seus próprios times

---

## 🗄️ ESTRUTURA DO BANCO

### Tabela `teams`
```sql
CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    color VARCHAR(7) NULL,              -- Cor hex (#009ef7)
    leader_id INT NULL,                  -- ID do líder
    department_id INT NULL,              -- Setor ao qual pertence
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);
```

### Tabela `team_members`
```sql
CREATE TABLE team_members (
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 💻 EXEMPLOS DE USO

### Criar Time
```php
use App\Services\TeamService;

$teamId = TeamService::create([
    'name' => 'Time de Vendas A',
    'description' => 'Time focado em vendas de produtos premium',
    'color' => '#FF5733',
    'leader_id' => 5,
    'department_id' => 2,
    'is_active' => 1
]);

// Adicionar membros
TeamService::addMembers($teamId, [5, 8, 12, 15]);
```

### Obter Métricas do Time
```php
use App\Services\TeamPerformanceService;

$performance = TeamPerformanceService::getPerformanceStats(
    $teamId,
    '2026-01-01',    // Data início
    '2026-01-31'     // Data fim
);

echo "Total de conversas: " . $performance['total_conversations'];
echo "Taxa de resolução: " . $performance['resolution_rate'] . "%";
echo "Membros: " . $performance['members_count'];

// Performance individual dos membros
foreach ($performance['members_performance'] as $member) {
    echo $member['user_name'] . ": " . $member['total_conversations'] . " conversas";
}
```

### Ranking de Times
```php
$ranking = TeamPerformanceService::getTeamsRanking(
    '2026-01-01',
    '2026-01-31',
    10  // Top 10 times
);

foreach ($ranking as $position => $team) {
    echo ($position + 1) . "º lugar: " . $team['team_name'];
    echo " - " . $team['closed_conversations'] . " conversas resolvidas";
}
```

### Comparar Times
```php
$comparison = TeamPerformanceService::compareTeams(
    [1, 2, 3],  // IDs dos times
    '2026-01-01',
    '2026-01-31'
);

foreach ($comparison as $team) {
    echo $team['team_name'] . ":";
    echo " - Conversas: " . $team['total_conversations'];
    echo " - Taxa: " . $team['resolution_rate'] . "%";
}
```

---

## 🎨 INTERFACE

### Dashboard de Times
- Cards com overview geral (total de times, agentes, conversas)
- Tabela de ranking com:
  - Posição no ranking
  - Nome do time com cor
  - Número de membros
  - Total de conversas
  - Conversas resolvidas
  - Taxa de resolução com barra de progresso
  - Tempo médio de resposta
  - Tempo médio de resolução
  - Link para detalhes

### Detalhes do Time
- Informações do time (descrição, líder, setor)
- Cards com métricas principais
- Tabela de membros com performance individual
- Indicação visual do líder
- Links para performance de cada agente

### Listagem de Times
- Grid de cards coloridos
- Busca por nome
- Informações resumidas (membros, líder, setor)
- Ações: Ver detalhes, Editar, Deletar

---

## 📊 MÉTRICAS CALCULADAS

### Agregadas do Time
```
total_conversations          - Total de conversas (todos os membros)
closed_conversations         - Conversas fechadas/resolvidas
open_conversations          - Conversas abertas atualmente
total_messages              - Total de mensagens enviadas
avg_first_response_time     - TM de primeira resposta (minutos)
avg_resolution_time         - TM de resolução (minutos)
resolution_rate             - Taxa de resolução (%)
conversations_per_day       - Média de conversas por dia
avg_messages_per_conversation - Média de mensagens por conversa
conversations_by_status     - Conversas agrupadas por status
```

### Performance Individual dos Membros
Cada membro do time tem suas métricas calculadas individualmente usando o `AgentPerformanceService` existente.

---

## 🔄 FLUXO DE DADOS

```
1. Time criado → TeamService::create()
2. Membros adicionados → Team::addMember()
3. Agentes atendem conversas (normalmente)
4. Dashboard acessa → TeamPerformanceService::getPerformanceStats()
5. Service busca IDs dos membros → Team::getMemberIds()
6. Agrega métricas de todos os membros → SQL com SUM/AVG
7. Calcula performance individual → AgentPerformanceService
8. Retorna dados consolidados
```

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

1. **Relação com Departments**: Times podem pertencer a setores, mas são conceitos diferentes:
   - **Setor**: Divisão organizacional (Vendas, Suporte, Financeiro)
   - **Time**: Grupo de agentes para gestão e métricas (Time A, Time B, Time Noturno)

2. **Líder do Time**: 
   - É automaticamente adicionado como membro ao ser definido
   - Pode ser diferente do supervisor do setor
   - Atualmente é apenas informativo (pode ser expandido para permissões específicas)

3. **Métricas em Tempo Real**: 
   - Calculadas sob demanda (não são pré-computadas)
   - Período pode ser filtrado por data
   - Performance otimizada com queries agregadas

4. **Soft Delete**: 
   - Times podem ser desativados (is_active = 0)
   - Ou completamente deletados (CASCADE remove membros)

5. **Membros Múltiplos Times**:
   - Um agente pode estar em múltiplos times
   - Suas conversas serão contabilizadas em todos os times

---

## 🔮 POSSÍVEIS EXPANSÕES FUTURAS

- [ ] Metas por time (não apenas por agente)
- [ ] Gamificação: Competições entre times
- [ ] Notificações de performance do time
- [ ] Relatórios exportáveis de times
- [ ] Permissões específicas de líder
- [ ] Atribuição automática de conversas por time
- [ ] Distribuição de carga balanceada por time
- [ ] Comparação histórica (este mês vs mês passado)
- [ ] Gráficos de evolução da performance

---

## 🚀 COMO INSTALAR

### 1. Rodar Migration
```bash
php public/run-migrations.php
```

### 2. Rodar Seed (permissões)
```bash
php public/run-seeds.php
```

### 3. Acessar
- `/teams` - Gerenciar times
- `/teams/dashboard` - Dashboard de times

---

## 📞 SUPORTE

Sistema integrado ao multiatendimento.
Documentado em: `SISTEMA_TIMES_EQUIPES.md`
