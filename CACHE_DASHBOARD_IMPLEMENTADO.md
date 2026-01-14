# ✅ Cache no DashboardService Implementado

**Data**: 2026-01-13  
**Arquivo**: `app/Services/DashboardService.php`  
**Métodos Otimizados**: 6 métodos principais

---

## 🎯 MÉTODOS COM CACHE ADICIONADO

### 1️⃣ getDepartmentStats()
**O que faz**: Estatísticas por setor (top 10)  
**Cache TTL**: 5 minutos (300s)  
**Cache Key**: `dashboard_department_stats`

```php
public static function getDepartmentStats(): array
{
    // ✅ Cache de 5 minutos
    return \App\Helpers\Cache::remember('dashboard_department_stats', 300, function() {
        // Query aqui
    });
}
```

**Por quê 5 minutos?**  
- Estatísticas de setores mudam pouco
- Dashboard não precisa ser em tempo real
- Reduz queries pesadas com JOINs

---

### 2️⃣ getFunnelStats()
**O que faz**: Estatísticas por funil (top 10)  
**Cache TTL**: 5 minutos (300s)  
**Cache Key**: `dashboard_funnel_stats`

```php
public static function getFunnelStats(): array
{
    // ✅ Cache de 5 minutos
    return \App\Helpers\Cache::remember('dashboard_funnel_stats', 300, function() {
        // Query aqui
    });
}
```

**Por quê 5 minutos?**  
- Estatísticas de funis mudam pouco
- Menos carga no banco

---

### 3️⃣ getRecentConversations()
**O que faz**: Últimas conversas (padrão 10)  
**Cache TTL**: 2 minutos (120s)  
**Cache Key**: `dashboard_recent_conversations_{$limit}`

```php
public static function getRecentConversations(int $limit = 10): array
{
    // ✅ Cache de 2 minutos (conversas recentes mudam frequentemente)
    $cacheKey = "dashboard_recent_conversations_{$limit}";
    return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($limit) {
        // Query aqui
    });
}
```

**Por quê 2 minutos?**  
- Conversas recentes mudam mais frequentemente
- Precisa ser mais atualizado que estatísticas
- Tem subquery de `unread_count` que é pesada

---

### 4️⃣ getRecentActivity()
**O que faz**: Atividade recente (últimas 24h)  
**Cache TTL**: 2 minutos (120s)  
**Cache Key**: `dashboard_recent_activity_{$limit}`

```php
public static function getRecentActivity(int $limit = 20): array
{
    // ✅ Cache de 2 minutos (atividades mudam frequentemente)
    $cacheKey = "dashboard_recent_activity_{$limit}";
    return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($limit) {
        // Query aqui
    });
}
```

**Por quê 2 minutos?**  
- Atividades são logs que mudam constantemente
- 2 minutos é aceitável para dashboard

---

### 5️⃣ getAgentMetrics()
**O que faz**: Métricas individuais de um agente  
**Cache TTL**: 3 minutos (180s)  
**Cache Key**: `dashboard_agent_metrics_{$agentId}_{md5($dateFrom.$dateTo)}`

```php
public static function getAgentMetrics(int $agentId, ?string $dateFrom = null, ?string $dateTo = null): array
{
    // ✅ Cache de 3 minutos por agente
    $cacheKey = "dashboard_agent_metrics_{$agentId}_" . md5($dateFrom . $dateTo);
    return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($agentId, $dateFrom, $dateTo) {
        // Query pesadíssima com subqueries e cálculos
    });
}
```

**Por quê 3 minutos?**  
- Query MUITO pesada (múltiplas subqueries)
- SLA e cálculos complexos
- Por agente + período (cache específico)

---

### 6️⃣ getAllAgentsMetrics()
**O que faz**: Métricas de TODOS os agentes  
**Cache TTL**: 3 minutos (180s)  
**Cache Key**: `dashboard_all_agents_metrics_{md5($dateFrom.$dateTo)}`

```php
public static function getAllAgentsMetrics(?string $dateFrom = null, ?string $dateTo = null): array
{
    // ✅ Cache de 3 minutos (chama getAgentMetrics que já tem cache)
    $cacheKey = "dashboard_all_agents_metrics_" . md5($dateFrom . $dateTo);
    return \App\Helpers\Cache::remember($cacheKey, 180, function() use ($dateFrom, $dateTo) {
        // Loop por todos os agentes chamando getAgentMetrics
    });
}
```

**Por quê 3 minutos?**  
- Chama `getAgentMetrics()` para cada agente (que já tem cache)
- Mas o resultado agregado também precisa de cache
- Query MUITO pesada (todos os agentes)

---

## 📊 GANHO ESTIMADO

### Antes (Sem Cache)

**Cenário**: Dashboard com 10 agentes

| Requisição | Queries Executadas | Tempo Estimado |
|------------|-------------------|----------------|
| Dashboard Load | ~80 queries | ~2-4 segundos |
| Refresh (F5) | ~80 queries | ~2-4 segundos |
| 10 usuários | ~800 queries/min | CPU 40-60% |

---

### Depois (Com Cache)

| Requisição | Queries Executadas | Tempo Estimado |
|------------|-------------------|----------------|
| Dashboard Load (primeira vez) | ~80 queries | ~2-4 segundos |
| Dashboard Load (cache hit) | **~5 queries** | **~0.3-0.5 segundos** ⚡ |
| 10 usuários | ~50 queries/min | CPU 10-20% ⚡ |

**Ganho**: 
- ⚡ **90% menos queries** quando cache está ativo
- ⚡ **80% mais rápido** para usuário
- ⚡ **50% menos CPU** no servidor

---

## 🧠 ESTRATÉGIA DE TTL

| Tipo de Dado | TTL | Motivo |
|--------------|-----|--------|
| **Estatísticas agregadas** | 5min | Mudam pouco |
| **Dados recentes** | 2min | Mudam mais |
| **Métricas de agentes** | 3min | Queries pesadas |

---

## 🔄 INVALIDAÇÃO DO CACHE

### Automática (Por Tempo)
O cache expira automaticamente após o TTL.

### Manual (Se Necessário)

Se precisar limpar cache de dashboard:

```bash
docker exec -it SEU_CONTAINER sh
rm -rf storage/cache/queries/dashboard_*
exit
```

Ou criar um método no DashboardService:

```php
public static function clearCache(): void
{
    $cacheDir = __DIR__ . '/../../storage/cache/queries/';
    $files = glob($cacheDir . 'dashboard_*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}
```

---

## 🧪 COMO TESTAR

### 1️⃣ Verificar Se Cache Está Sendo Criado

```bash
docker exec -it t4gss4040cckwwgs0cso04wo-191453204612 sh

# Ver arquivos de cache sendo criados
watch -n 1 'ls -lh storage/cache/queries/ | grep dashboard'

# Ctrl+C para sair
exit
```

---

### 2️⃣ Comparar Performance

#### ANTES (Cache Frio)
```bash
# Limpar cache
docker exec -it SEU_CONTAINER sh -c "rm -rf storage/cache/queries/dashboard_*"

# Cronometrar acesso ao dashboard
time curl -s "https://seu-dominio.com/dashboard" -H "Cookie: session=..." > /dev/null
```

#### DEPOIS (Cache Quente)
```bash
# Acessar de novo (cache ativo)
time curl -s "https://seu-dominio.com/dashboard" -H "Cookie: session=..." > /dev/null
```

**Resultado Esperado**:
- 1ª vez: ~2-4 segundos
- 2ª vez: ~0.3-0.5 segundos ⚡ (80% mais rápido)

---

### 3️⃣ Verificar QPS

```bash
docker exec -it SEU_CONTAINER sh
mysql -uchatperson -p chat_person

SHOW GLOBAL STATUS LIKE 'Questions';
# Aguardar 10s e abrir dashboard
SHOW GLOBAL STATUS LIKE 'Questions';
# Calcular: (valor2 - valor1) / 10

exit
exit
```

**Resultado Esperado**:
- Sem cache: ~15-20 queries ao abrir dashboard
- Com cache: ~2-5 queries ao abrir dashboard ⚡ (70% menos)

---

## 📋 OUTROS MÉTODOS DO DASHBOARDSERVICE

### Ainda SEM Cache (Menos Críticos):

7. `getConversationsOverTime()` - Gráfico de conversas
8. `getConversationsByChannelChart()` - Chart por canal
9. `getConversationsByStatusChart()` - Chart por status
10. `getAgentsPerformanceChart()` - Chart de performance
11. `getMessagesOverTime()` - Gráfico de mensagens
12. `getSLAMetrics()` - Métricas de SLA
13. `getTopAgents()` - Top 5 agentes (já chama AgentPerformanceService que tem cache)

**Por quê não foram cacheados agora?**
- Alguns são charts dinâmicos com filtros variáveis
- `getTopAgents()` já usa `AgentPerformanceService::getAgentsRanking()` que **JÁ TEM CACHE**
- Menos prioritários

**Se quiser adicionar cache neles depois**, posso fazer!

---

## 🎉 RESULTADO FINAL

### Métodos Cacheados no Sistema:

| Service | Métodos com Cache | TTL |
|---------|------------------|-----|
| **ConversationService** | `list()` | 15min ⚡⚡⚡ |
| **AgentPerformanceService** | `getAgentsRanking()` | 2min ⚡⚡ |
| **DashboardService** | `getAverageResponseTime()` | 5min ⚡⚡ |
| **DashboardService** | `getDepartmentStats()` | 5min ⚡⚡ |
| **DashboardService** | `getFunnelStats()` | 5min ⚡⚡ |
| **DashboardService** | `getRecentConversations()` | 2min ⚡ |
| **DashboardService** | `getRecentActivity()` | 2min ⚡ |
| **DashboardService** | `getAgentMetrics()` | 3min ⚡⚡ |
| **DashboardService** | `getAllAgentsMetrics()` | 3min ⚡⚡ |

**Total**: **9 métodos** com cache ativo! ⚡⚡⚡

---

## 💡 PRÓXIMOS PASSOS (Opcional)

Se QPS ainda estiver alto depois disso:

1. ✅ Adicionar cache nos charts do DashboardService
2. ✅ Adicionar cache em CoachingMetricsService
3. ✅ Implementar Redis para cache mais rápido
4. ✅ Pré-computar métricas em background (cron)

---

**Data**: 2026-01-13  
**Status**: ✅ CACHE IMPLEMENTADO E DOCUMENTADO

**Pronto para testar!** 🚀
