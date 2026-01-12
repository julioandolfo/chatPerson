# 🔍 Análise Completa das Queries Pesadas

**Data**: 2026-01-12  
**Banco**: chat_person (MySQL 8.4)  
**Problema**: CPU alta (60-80%) devido a queries analíticas sem índices

---

## 📊 Resumo Executivo

Você tem **2 queries críticas** que estão matando o servidor:

### Query #1: Tempo Médio de Resposta (avg response time)
- **Arquivo**: `app/Services/DashboardService.php` (linha 457-495)
- **Problema**: Subquery correlacionada com `MIN(created_at)` 
- **Impacto**: 217k linhas examinadas, 3+ segundos
- **Frequência**: Toda vez que carrega o dashboard

### Query #2: Ranking de Agentes (top 5 agents)
- **Arquivo**: `app/Services/AgentPerformanceService.php` (linha 254-310)
- **Problema**: Joins sem índices + COUNT DISTINCT
- **Impacto**: 768k linhas examinadas, 1+ segundo
- **Frequência**: Load do dashboard + analytics

---

## 🎯 Solução em 3 Níveis

### Nível 1: IMEDIATO (Cache) ⚡
**Tempo**: 15 minutos  
**Ganho**: 95% de redução no tempo de resposta

✅ **JÁ IMPLEMENTADO** no seu sistema:
- `AgentPerformanceService::getAgentsRanking()` tem cache de 2 minutos (linha 260)
- Helper `Cache` existe em `app/Helpers/Cache.php`

❌ **FALTANDO**:
- Cache no `DashboardService::getAverageResponseTime()`

### Nível 2: MÉDIO PRAZO (Índices) 📊
**Tempo**: 30 minutos  
**Ganho**: 70-80% de redução no tempo de query (sem cache)

✅ **JÁ EXISTE** migration preparada:
- `database/migrations/021_create_performance_indexes.php`

❓ **PRECISA VERIFICAR**: Se a migration foi executada

### Nível 3: LONGO PRAZO (Reescrita) 🔧
**Tempo**: 2-4 horas  
**Ganho**: 90%+ de redução + escalabilidade

- Usar Window Functions (ROW_NUMBER) ao invés de subquery correlacionada
- Pré-agregar dados em tabelas materializadas
- Processar em background jobs

---

## 🔍 Análise Detalhada das Queries

### Query #1: getAverageResponseTime()

**Localização**: `app/Services/DashboardService.php:457-495`

```php
$sql = "SELECT AVG(response_time_seconds) as avg_seconds
        FROM (
            SELECT 
                TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
            FROM messages m1
            INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                AND m2.sender_type = 'agent'
                AND m2.created_at > m1.created_at
                AND m2.created_at = (
                    SELECT MIN(m3.created_at)          -- ⚠️ PROBLEMA AQUI
                    FROM messages m3
                    WHERE m3.conversation_id = m1.conversation_id
                    AND m3.sender_type = 'agent'
                    AND m3.created_at > m1.created_at
                )
            INNER JOIN conversations c ON c.id = m1.conversation_id
            WHERE m1.sender_type = 'contact'
            AND c.created_at >= ?
            AND c.created_at <= ?
            HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
        ) as response_times";
```

**Problema**: 
- Para cada mensagem do contato, executa `SELECT MIN(...)` → N² complexity
- Sem índice em `(conversation_id, sender_type, created_at)` → full table scan

**Índices Necessários**:
```sql
CREATE INDEX idx_messages_conv_sender_date 
ON messages(conversation_id, sender_type, created_at);
```

**Solução Imediata (Cache)**:
```php
private static function getAverageResponseTime(string $dateFrom, string $dateTo): ?array
{
    // ✅ ADICIONAR CACHE DE 5 MINUTOS
    $cacheKey = "avg_response_time_{$dateFrom}_{$dateTo}";
    
    return \App\Helpers\Cache::remember($cacheKey, 300, function() use ($dateFrom, $dateTo) {
        // ... query existente ...
    });
}
```

**Solução Longo Prazo (Window Function)**:
```sql
WITH contact_msgs AS (
  SELECT id, conversation_id, created_at
  FROM messages
  WHERE sender_type = 'contact'
),
agent_msgs AS (
  SELECT id, conversation_id, created_at
  FROM messages
  WHERE sender_type = 'agent'
),
pairs AS (
  SELECT
    cm.conversation_id,
    TIMESTAMPDIFF(SECOND, cm.created_at, am.created_at) AS diff_seconds,
    ROW_NUMBER() OVER (
      PARTITION BY cm.id
      ORDER BY am.created_at
    ) AS rn
  FROM contact_msgs cm
  JOIN agent_msgs am
    ON am.conversation_id = cm.conversation_id
   AND am.created_at > cm.created_at
)
SELECT AVG(diff_seconds) AS avg_seconds
FROM pairs
WHERE rn = 1;
```

---

### Query #2: getAgentsRanking()

**Localização**: `app/Services/AgentPerformanceService.php:254-310`

```php
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
```

**Problema**:
- Join de 3 tabelas sem índices adequados
- COUNT DISTINCT em tabelas grandes
- Filtros de data sem índice

**Índices Necessários**:
```sql
-- Para conversations
CREATE INDEX idx_conversations_agent_date_status 
ON conversations(agent_id, created_at, status, resolved_at);

-- Para messages
CREATE INDEX idx_messages_sender_type_date 
ON messages(sender_id, sender_type, created_at, ai_agent_id);

-- Para users
CREATE INDEX idx_users_role_status 
ON users(role, status);
```

**Solução Atual (Cache)**:
✅ **JÁ IMPLEMENTADO** (linha 260):
```php
$cacheKey = "agents_ranking_{$dateFrom}_{$dateTo}_{$limit}";
return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($dateFrom, $dateTo, $limit) {
    // ... query ...
});
```

**Solução Longo Prazo (Pré-agregação)**:
```sql
WITH conv AS (
  SELECT
    agent_id,
    COUNT(*) AS total_conversations,
    SUM(status IN ('closed','resolved')) AS closed_conversations,
    AVG(CASE
      WHEN status IN ('closed','resolved') AND resolved_at IS NOT NULL
      THEN TIMESTAMPDIFF(MINUTE, created_at, resolved_at)
    END) AS avg_resolution_time
  FROM conversations
  WHERE created_at >= ? AND created_at <= ?
  GROUP BY agent_id
),
msg AS (
  SELECT
    sender_id AS agent_id,
    COUNT(*) AS total_messages
  FROM messages
  WHERE sender_type = 'agent'
    AND ai_agent_id IS NULL
    AND created_at >= ? AND created_at <= ?
  GROUP BY sender_id
)
SELECT
  u.id, u.name, u.email, u.avatar,
  conv.total_conversations,
  conv.closed_conversations,
  COALESCE(msg.total_messages,0) AS total_messages,
  conv.avg_resolution_time
FROM users u
JOIN conv ON conv.agent_id = u.id
LEFT JOIN msg ON msg.agent_id = u.id
WHERE u.role IN ('agent','admin','supervisor')
  AND u.status = 'active'
ORDER BY conv.closed_conversations DESC, conv.total_conversations DESC
LIMIT ?;
```

---

## 📋 Checklist de Implementação

### ✅ Passo 1: Verificar Índices Existentes

Execute no MySQL:
```sql
-- Copiar e executar: VERIFICAR_INDICES_EXISTENTES.sql
```

Ou via terminal:
```bash
php check_indexes.php
```

### ✅ Passo 2: Criar Índices (se não existirem)

```bash
php database/migrate.php
```

Ou manualmente no MySQL:
```sql
-- messages
CREATE INDEX idx_messages_conv_sender_date 
ON messages(conversation_id, sender_type, created_at);

CREATE INDEX idx_messages_sender_type_date 
ON messages(sender_id, sender_type, created_at, ai_agent_id);

-- conversations
CREATE INDEX idx_conversations_contact 
ON conversations(contact_id);

CREATE INDEX idx_conversations_agent_date_status 
ON conversations(agent_id, created_at, status, resolved_at);

-- users
CREATE INDEX idx_users_role_status 
ON users(role, status);

-- Atualizar estatísticas
ANALYZE TABLE messages;
ANALYZE TABLE conversations;
ANALYZE TABLE users;
```

### ✅ Passo 3: Adicionar Cache Faltante

Editar `app/Services/DashboardService.php`:

```php
private static function getAverageResponseTime(string $dateFrom, string $dateTo): ?array
{
    // ✅ ADICIONAR ESTA LINHA
    $cacheKey = "avg_response_time_{$dateFrom}_{$dateTo}";
    
    return \App\Helpers\Cache::remember($cacheKey, 300, function() use ($dateFrom, $dateTo) {
        // Usar SEGUNDOS para maior precisão (IA responde em segundos)
        $sql = "SELECT AVG(response_time_seconds) as avg_seconds
                FROM (
                    SELECT 
                        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
                    FROM messages m1
                    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
                        AND m2.sender_type = 'agent'
                        AND m2.created_at > m1.created_at
                        AND m2.created_at = (
                            SELECT MIN(m3.created_at)
                            FROM messages m3
                            WHERE m3.conversation_id = m1.conversation_id
                            AND m3.sender_type = 'agent'
                            AND m3.created_at > m1.created_at
                        )
                    INNER JOIN conversations c ON c.id = m1.conversation_id
                    WHERE m1.sender_type = 'contact'
                    AND c.created_at >= ?
                    AND c.created_at <= ?
                    HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
                ) as response_times";
        
        $result = \App\Helpers\Database::fetch($sql, [$dateFrom, $dateTo]);
        
        // Retornar segundos e minutos
        if ($result && isset($result['avg_seconds']) && $result['avg_seconds'] !== null) {
            $seconds = (float)$result['avg_seconds'];
            $minutes = $seconds / 60;
            return ['seconds' => round($seconds, 2), 'minutes' => round($minutes, 2)];
        }
        
        return ['seconds' => 0, 'minutes' => 0];
    });
}
```

### ✅ Passo 4: Testar

```bash
# 1. Limpar cache
rm -rf storage/cache/queries/*

# 2. Acessar dashboard
# - 1ª vez: deve demorar ~1 segundo (com índices)
# - 2ª vez: deve ser instantâneo (< 0.1s)

# 3. Monitorar slow log
tail -f /var/log/mysql/slow.log

# 4. Verificar CPU
top
```

### ✅ Passo 5: Validar com EXPLAIN

```sql
-- Query #1
EXPLAIN ANALYZE
SELECT AVG(response_time_seconds) as avg_seconds
FROM (
    SELECT 
        TIMESTAMPDIFF(SECOND, m1.created_at, m2.created_at) as response_time_seconds
    FROM messages m1
    INNER JOIN messages m2 ON m2.conversation_id = m1.conversation_id
        AND m2.sender_type = 'agent'
        AND m2.created_at > m1.created_at
        AND m2.created_at = (
            SELECT MIN(m3.created_at)
            FROM messages m3
            WHERE m3.conversation_id = m1.conversation_id
            AND m3.sender_type = 'agent'
            AND m3.created_at > m1.created_at
        )
    INNER JOIN conversations c ON c.id = m1.conversation_id
    WHERE m1.sender_type = 'contact'
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
    HAVING response_time_seconds IS NOT NULL AND response_time_seconds > 0
) as response_times;

-- Query #2
EXPLAIN ANALYZE
SELECT 
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
    AND c.created_at >= '2026-01-01'
    AND c.created_at <= '2026-01-12 23:59:59'
LEFT JOIN messages m ON u.id = m.sender_id 
    AND m.sender_type = 'agent'
    AND m.ai_agent_id IS NULL
    AND m.created_at >= '2026-01-01'
    AND m.created_at <= '2026-01-12 23:59:59'
WHERE u.role IN ('agent', 'admin', 'supervisor')
    AND u.status = 'active'
GROUP BY u.id, u.name, u.email, u.avatar
HAVING total_conversations > 0
ORDER BY closed_conversations DESC, total_conversations DESC
LIMIT 5;
```

---

## 📊 Impacto Esperado

### Antes (Sem Otimizações)
```
Query #1: 3+ segundos, 217k linhas examinadas
Query #2: 1+ segundo, 768k linhas examinadas
CPU: 60-80% constante
Slow log: 100+ queries/hora
Dashboard load: 5-10 segundos
```

### Depois (Com Índices + Cache)
```
Query #1: 0.01 segundos (cache hit), 0.5s (cache miss com índice)
Query #2: 0.01 segundos (cache hit), 0.3s (cache miss com índice)
CPU: 20-30% normal
Slow log: 5-10 queries/hora (apenas cache misses)
Dashboard load: 0.5-1 segundo
```

### Ganhos
- ⚡ **95%** de redução no tempo de resposta médio
- 🎯 **70%** de redução no uso de CPU
- 📉 **90%** de redução em queries no slow log
- 🚀 **10x** mais rápido no dashboard

---

## 🔧 Manutenção

### Limpar Cache
```bash
# Via terminal
rm -rf storage/cache/queries/*

# Via código
\App\Helpers\Cache::clear();
```

### Ajustar Tempo de Cache
```php
// DashboardService::getAverageResponseTime()
// Atual: 300 segundos (5 minutos)
// Pode aumentar para 600 (10 min) ou 900 (15 min)

// AgentPerformanceService::getAgentsRanking()
// Atual: 120 segundos (2 minutos)
// Pode aumentar para 300 (5 min)
```

### Monitorar Performance
```sql
-- Ver queries lentas
SELECT * FROM mysql.slow_log 
ORDER BY start_time DESC 
LIMIT 20;

-- Ver uso de índices
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    CARDINALITY,
    SEQ_IN_INDEX,
    COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'chat_person'
  AND TABLE_NAME IN ('messages', 'conversations')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

---

## 📝 Próximos Passos (Longo Prazo)

1. **Tabelas Materializadas**
   - Criar `agent_performance_daily` com métricas pré-calculadas
   - Atualizar via cron job a cada hora/dia

2. **Window Functions**
   - Reescrever queries usando ROW_NUMBER() ao invés de subqueries correlacionadas

3. **Background Jobs**
   - Processar métricas pesadas em background
   - Armazenar resultados em cache/banco

4. **Redis/Memcached**
   - Migrar de cache em arquivo para Redis
   - Melhor para ambientes com múltiplos servidores

5. **Particionamento**
   - Particionar `messages` por data (se > 10M registros)
   - Melhorar performance de queries com filtros de data

---

**Autor**: Análise baseada no slow.log  
**Versão**: 1.0  
**Prioridade**: 🔴 CRÍTICA
