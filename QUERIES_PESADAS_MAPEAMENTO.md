# 🔍 Mapeamento das Queries Mais Pesadas

## 🥇 QUERY #1 - Tempo Médio de Resposta (MAIS PESADA)

### ⏱️ Impacto
- **Query_time**: até 3.23 segundos
- **Rows_examined**: ~217.000 linhas
- **Complexidade**: 🔥🔥🔥🔥🔥 (CRÍTICA)

### 📍 Onde Está

#### 1. **ContactController::getHistoryMetrics()**
**Arquivo**: `app/Controllers/ContactController.php` (linhas 315-339)

```php
public function getHistoryMetrics(int $id): void
{
    Permission::abortIfCannot('contacts.view');
    
    $stats = \App\Helpers\Database::fetch("
        SELECT 
            COUNT(DISTINCT c.id) AS total_conversations,
            AVG(response_times.response_time_minutes) AS avg_response_time_minutes
        FROM conversations c
        LEFT JOIN (
            SELECT 
                m1.conversation_id,
                AVG(TIMESTAMPDIFF(MINUTE, m1.created_at, m2.created_at)) as response_time_minutes
            FROM messages m1
            INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                AND m2.sender_type = 'agent'
                AND m2.created_at > m1.created_at
                AND m2.created_at = (
                    SELECT MIN(m3.created_at)      -- ⚠️ SUBQUERY CORRELACIONADA
                    FROM messages m3
                    WHERE m3.conversation_id = m1.conversation_id
                    AND m3.sender_type = 'agent'
                    AND m3.created_at > m1.created_at
                )
            WHERE m1.sender_type = 'contact'
            GROUP BY m1.conversation_id
        ) response_times ON response_times.conversation_id = c.id
        WHERE c.contact_id = ?
    ", [$id]);
    // ...
}
```

### 🌐 Rota
```php
// routes/web.php (linha 187)
Router::get('/contacts/{id}/history', [ContactController::class, 'getHistoryMetrics'], ['Authentication']);
```

### 🖥️ Frontend - Onde é Chamado

**Arquivo**: `views/conversations/index.php` (linha 9016-9034)

```javascript
function loadContactHistory(contactId) {
    if (!contactId) return;
    
    fetch(`/contacts/${contactId}/history`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        // Atualizar sidebar com histórico do contato
        // ...
    });
}
```

### 🎯 Quando é Disparada

Esta query é executada **TODA VEZ** que:

1. ✅ Usuário **SELECIONA uma conversa** na lista
   - Linha 8610: `loadContactHistory(conversation.contact_id);`
   - **Frequência**: A CADA clique em conversa diferente

2. ✅ Sidebar de contato é **recarregada/atualizada**
   - Especialmente ao trocar entre conversas
   - **Impacto**: Se usuário fica navegando entre conversas, dispara múltiplas vezes

3. ✅ **Problema Crítico**: 
   - Se o usuário tem o hábito de navegar rapidamente entre conversas
   - Cada clique = nova query de 3+ segundos
   - Resultado: CPU alta, travamentos, slow log lotado

### 🔥 Por Que é Tão Pesada?

1. **Subquery Correlacionada** (linha 329-334)
   ```sql
   SELECT MIN(m3.created_at) ...  -- Executa para CADA mensagem do contato
   ```

2. **Triple JOIN na tabela messages**
   - `messages m1` (mensagens do contato)
   - `messages m2` (respostas do agente)
   - `messages m3` (subquery para encontrar primeira resposta)

3. **Crescimento Linear**
   - Quanto mais mensagens o contato tiver, mais pesada fica
   - Contato com 628/794 já examina 217k linhas

---

## 🥈 QUERY #2 - Ranking de Agentes (SEGUNDA MAIS PESADA)

### ⏱️ Impacto
- **Query_time**: ~1.06 a 1.18 segundos
- **Rows_examined**: ~768.000 linhas
- **Complexidade**: 🔥🔥🔥 (ALTA)

### 📍 Onde Está

#### 1. **AgentPerformanceService::getAgentsRanking()**
**Arquivo**: `app/Services/AgentPerformanceService.php` (linhas 253-284)

```php
public static function getAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
{
    $dateFrom = $dateFrom ?? date('Y-m-01');
    $dateTo = $dateTo ?? date('Y-m-d H:i:s');

    $sql = "SELECT 
                u.id,
                u.name,
                u.email,
                u.avatar,
                COUNT(DISTINCT c.id) as total_conversations,
                COUNT(DISTINCT CASE WHEN c.status IN ('closed', 'resolved') THEN c.id END) as closed_conversations,
                COUNT(DISTINCT m.id) as total_messages,
                AVG(CASE WHEN c.status IN ('closed', 'resolved') AND c.resolved_at IS NOT NULL 
                    THEN TIMESTAMPDIFF(MINUTE, c.created_at, c.resolved_at) END) as avg_resolution_time
            FROM users u
            LEFT JOIN conversations c ON u.id = c.agent_id 
                AND c.created_at >= ? 
                AND c.created_at <= ?
            LEFT JOIN messages m ON u.id = m.sender_id 
                AND m.sender_type = 'agent'
                AND m.ai_agent_id IS NULL
                AND m.created_at >= ? 
                AND m.created_at <= ?
            WHERE u.role IN ('agent', 'admin', 'supervisor')
                AND u.status = 'active'
            GROUP BY u.id, u.name, u.email, u.avatar
            HAVING total_conversations > 0
            ORDER BY closed_conversations DESC, total_conversations DESC
            LIMIT ?";
    
    $agents = \App\Helpers\Database::fetchAll($sql, [$dateFrom, $dateTo, $dateFrom, $dateTo, $limit]);
    // ...
}
```

### 🌐 Onde é Chamada

#### A) DashboardService::getTopAgents()
**Arquivo**: `app/Services/DashboardService.php` (linha 232)
```php
public static function getTopAgents(?string $dateFrom = null, ?string $dateTo = null, int $limit = 5): array
{
    return \App\Services\AgentPerformanceService::getAgentsRanking($dateFrom, $dateTo, $limit);
}
```

#### B) DashboardController::index()
**Arquivo**: `app/Controllers/DashboardController.php` (linha 45)
```php
public function index(): void
{
    // ...
    $topAgents = \App\Services\DashboardService::getTopAgents($dateFrom, $dateTo, 5);
    // ...
}
```

#### C) AnalyticsController::getAgentsPerformance()
**Arquivo**: `app/Controllers/AnalyticsController.php` (linhas 306-310)
```php
public function getAgentsPerformance(): void
{
    // ...
    $ranking = AgentPerformanceService::getAgentsRanking(
        $filters['start_date'],
        $filters['end_date'] . ' 23:59:59',
        20  // Top 20 agentes
    );
    // ...
}
```

### 🎯 Quando é Disparada

Esta query é executada **TODA VEZ** que:

1. ✅ **Dashboard é carregado/recarregado**
   - Rota: `GET /dashboard`
   - Carrega Top 5 agentes
   - **Frequência**: A cada acesso/refresh do dashboard

2. ✅ **Página de Analytics é acessada**
   - Rota: `GET /api/analytics/agents`
   - Carrega Top 20 agentes
   - **Frequência**: A cada acesso à página de Analytics

3. ✅ **Filtros de data são alterados**
   - No dashboard ou analytics
   - Nova requisição AJAX
   - **Impacto**: Usuários que ficam testando filtros disparam múltiplas vezes

### 🔥 Por Que é Pesada?

1. **Múltiplos JOINs com tabelas grandes**
   ```sql
   users -> conversations -> messages
   ```

2. **Filtragem por data em duas tabelas**
   - `conversations.created_at` 
   - `messages.created_at`
   - Examina ~768k linhas

3. **Agregações complexas**
   - COUNT DISTINCT em múltiplas colunas
   - AVG com CASE WHEN
   - GROUP BY e HAVING

---

## 🥉 QUERY #3 - MAX Role Level (MENOR IMPACTO)

### ⏱️ Impacto
- **Query_time**: < 0.1 segundo
- **Complexidade**: 🔥 (BAIXA)

**Observação**: Esta query não está no slow.log pois é rápida. Incluída aqui apenas para completude.

---

## 📊 Resumo do Impacto

| Query | Onde Roda | Frequência | Impacto | Rows Examined |
|-------|-----------|------------|---------|---------------|
| 🥇 Tempo Médio Resposta | Sidebar → A CADA conversa clicada | 🔴 **MUITO ALTA** | 🔥🔥🔥🔥🔥 | ~217k |
| 🥈 Ranking Agentes | Dashboard/Analytics → Load/Filtros | 🟡 **MÉDIA** | 🔥🔥🔥 | ~768k |
| 🥉 MAX Role Level | Auth/Permissions → Load | 🟢 **BAIXA** | 🔥 | ~100 |

---

## 🎯 Conclusões e Próximos Passos

### Query #1 - PRIORIDADE MÁXIMA ⚠️

**Problema**: 
- Executa a CADA clique em conversa
- Usuário navegando rapidamente = múltiplas queries de 3+ segundos simultâneas
- Subquery correlacionada muito pesada

**Soluções Sugeridas**:

1. **Cache por contato** (ganho imediato)
   ```php
   // Cachear por 5 minutos
   $cacheKey = "contact_history_{$contactId}";
   $stats = Cache::remember($cacheKey, 300, function() { ... });
   ```

2. **Calcular e armazenar na tabela** (médio prazo)
   - Criar coluna `contacts.avg_response_time_minutes`
   - Atualizar via trigger ou job assíncrono
   - Eliminar query completamente no frontend

3. **Otimizar query** (curto prazo)
   - Adicionar índices compostos
   - Substituir subquery por window function (MySQL 8.0+)
   - Usar LEFT JOIN LATERAL ou CTE

### Query #2 - PRIORIDADE MÉDIA

**Problema**:
- Examina muitas linhas (768k)
- Executa no dashboard e analytics
- Porém, menos frequente que Query #1

**Soluções Sugeridas**:

1. **Cache de 1 minuto** (ganho imediato)
   ```php
   $cacheKey = "agents_ranking_{$dateFrom}_{$dateTo}";
   $ranking = Cache::remember($cacheKey, 60, function() { ... });
   ```

2. **Tabela materializada** (longo prazo)
   - Criar `agent_performance_daily`
   - Atualizar via cron (1x por dia ou a cada hora)
   - Dashboard consulta tabela pré-calculada

3. **Índices compostos**
   ```sql
   CREATE INDEX idx_conv_agent_date ON conversations(agent_id, created_at, status);
   CREATE INDEX idx_msg_sender_date ON messages(sender_id, sender_type, created_at);
   ```

---

## 🛠️ Como Monitorar

Para confirmar o impacto das otimizações:

```bash
# Antes da otimização
tail -f /var/log/mysql/slow.log | grep -E "Query_time|SELECT COUNT"

# Teste de carga
# Simular usuário navegando entre 10 conversas rapidamente
for i in {1..10}; do
    curl -s "http://localhost/contacts/{id}/history" &
done

# Após otimização, verificar se Query_time diminuiu
```

---

## 📝 Notas Importantes

1. **Query #1 é a vilã principal**
   - Foco total em resolver esta primeiro
   - Cache simples já daria alívio imediato

2. **Query #2 é secundária**
   - Importante, mas menos crítica
   - Pode ser resolvida após Query #1

3. **Índices são fundamentais**
   - Adicionar índices compostos nas colunas filtradas
   - Ver arquivo `OTIMIZACOES_QUERIES_PESADAS.md` (a ser criado)

---

**Data**: 2026-01-12  
**Versão**: 1.0  
**Status**: ✅ Mapeamento Completo
