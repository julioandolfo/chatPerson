# ⚡ Solução Imediata para Queries Pesadas

## 🎯 Resumo Executivo

**Problema Principal**: Query de histórico do contato trava o sistema (3+ segundos, 217k linhas examinadas)

**Onde**: Executa TODA VEZ que o usuário clica em uma conversa no sidebar

**Solução Rápida**: Implementar cache de 5 minutos

---

## 🥇 QUERY #1 - Histórico do Contato (CRÍTICA)

### 📍 Localização Exata
```
Arquivo: app/Controllers/ContactController.php
Método: getHistoryMetrics() (linha 298)
Rota: GET /contacts/{id}/history
Chamada: views/conversations/index.php (linha 9016 - loadContactHistory)
```

### 🔴 Quando Executa
- ✅ A CADA clique em uma conversa diferente
- ✅ A CADA vez que o sidebar é recarregado
- ✅ Usuário navegando rapidamente = múltiplas queries simultâneas

### ⚡ Solução Rápida (5 minutos para implementar)

**1. Criar Helper de Cache** (se não existir)

```php
// app/Helpers/Cache.php
<?php
namespace App\Helpers;

class Cache
{
    private static string $cacheDir = __DIR__ . '/../../storage/cache/queries/';
    
    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        $file = self::$cacheDir . md5($key) . '.cache';
        
        // Criar diretório se não existir
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0777, true);
        }
        
        // Verificar se cache existe e ainda é válido
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            if (time() < $data['expires']) {
                return $data['value'];
            }
            // Cache expirado, deletar
            unlink($file);
        }
        
        // Executar callback e cachear
        $value = $callback();
        
        $data = [
            'value' => $value,
            'expires' => time() + $seconds
        ];
        
        file_put_contents($file, serialize($data), LOCK_EX);
        
        return $value;
    }
    
    public static function forget(string $key): void
    {
        $file = self::$cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function clear(): void
    {
        if (is_dir(self::$cacheDir)) {
            $files = glob(self::$cacheDir . '*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }
}
```

**2. Modificar ContactController::getHistoryMetrics()**

```php
// app/Controllers/ContactController.php (linha ~315)

public function getHistoryMetrics(int $id): void
{
    Permission::abortIfCannot('contacts.view');

    try {
        // Verificar se contato existe
        $contact = \App\Models\Contact::find($id);
        if (!$contact) {
            Response::json([
                'success' => false,
                'message' => 'Contato não encontrado'
            ], 404);
            return;
        }

        // ✅ CACHE DE 5 MINUTOS (300 segundos)
        $cacheKey = "contact_history_{$id}";
        $stats = \App\Helpers\Cache::remember($cacheKey, 300, function() use ($id) {
            return \App\Helpers\Database::fetch("
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
                            SELECT MIN(m3.created_at)
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
        });
        
        // Log para debug (pode remover após confirmar que funciona)
        error_log("Histórico do contato {$id} (cached): " . json_encode($stats));

        // Conversas anteriores (últimas 5 conversas, priorizando fechadas/resolvidas)
        $previous = \App\Helpers\Database::fetchAll("
            SELECT 
                c.id,
                c.status,
                c.created_at,
                c.updated_at,
                (SELECT content FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id) as message_count
            FROM conversations c
            WHERE c.contact_id = ?
            ORDER BY 
                CASE 
                    WHEN c.status IN ('closed', 'resolved') THEN 0
                    WHEN c.status = 'open' THEN 1
                    ELSE 2
                END,
                c.updated_at DESC
            LIMIT 5
        ", [$id]);

        $totalConv = (int)($stats['total_conversations'] ?? 0);
        $avgResponseMinutes = $stats['avg_response_time_minutes'] !== null && $stats['avg_response_time_minutes'] > 0 
            ? round((float)$stats['avg_response_time_minutes'], 1) 
            : null;
        
        // Converter para segundos para compatibilidade com o frontend
        $avgResponseSeconds = $avgResponseMinutes !== null ? (int)($avgResponseMinutes * 60) : null;
        
        Response::json([
            'success' => true,
            'contact_id' => $id,
            'total_conversations' => $totalConv,
            'avg_response_time_seconds' => $avgResponseSeconds,
            'avg_response_time_minutes' => $avgResponseMinutes,
            'avg_response_time_hours' => $avgResponseMinutes !== null ? round($avgResponseMinutes / 60, 2) : null,
            'avg_duration_seconds' => $avgResponseSeconds,
            'avg_duration_minutes' => $avgResponseMinutes,
            'avg_duration_hours' => $avgResponseMinutes !== null ? round($avgResponseMinutes / 60, 2) : null,
            'csat_score' => null,
            'previous_conversations' => $previous ?: []
        ]);
    } catch (\Exception $e) {
        Response::json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
```

**3. Opcional: Limpar Cache Quando Houver Nova Mensagem**

```php
// Em algum lugar onde você cria/atualiza mensagens (ex: MessageService ou MessageController)
// Adicionar após salvar nova mensagem:

\App\Helpers\Cache::forget("contact_history_{$contactId}");
```

### ✅ Resultado Esperado

**Antes**:
- ❌ Query executa TODA VEZ: 3+ segundos
- ❌ Usuário navegando = CPU alta
- ❌ Slow log lotado

**Depois**:
- ✅ Primeira requisição: 3 segundos (ainda pesada, mas aceitável)
- ✅ Próximas 5 minutos: < 0.01 segundos (leitura de arquivo)
- ✅ CPU normal
- ✅ Slow log limpo

**Ganho**: 99.7% de redução no tempo médio de resposta

---

## 🥈 QUERY #2 - Ranking de Agentes (SECUNDÁRIA)

### 📍 Localização Exata
```
Arquivo: app/Services/AgentPerformanceService.php
Método: getAgentsRanking() (linha 253)
Chamadas:
  - DashboardController::index() → Dashboard inicial
  - AnalyticsController::getAgentsPerformance() → Página Analytics
```

### 🟡 Quando Executa
- ✅ Load do dashboard
- ✅ Load da página de analytics
- ✅ Filtros de data alterados

### ⚡ Solução Rápida

```php
// app/Services/AgentPerformanceService.php (linha ~253)

public static function getAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 10): array
{
    $dateFrom = $dateFrom ?? date('Y-m-01');
    $dateTo = $dateTo ?? date('Y-m-d H:i:s');

    // ✅ CACHE DE 2 MINUTOS (120 segundos)
    $cacheKey = "agents_ranking_{$dateFrom}_{$dateTo}_{$limit}";
    
    return \App\Helpers\Cache::remember($cacheKey, 120, function() use ($dateFrom, $dateTo, $limit) {
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
        
        // Calcular taxa de resolução e tempo médio de resposta para cada agente
        foreach ($agents as &$agent) {
            $agent['resolution_rate'] = $agent['total_conversations'] > 0
                ? round(($agent['closed_conversations'] / $agent['total_conversations']) * 100, 2)
                : 0;
            $agent['avg_resolution_time'] = $agent['avg_resolution_time'] 
                ? round((float)$agent['avg_resolution_time'], 2) 
                : null;
            
            // Calcular tempo médio de resposta individual (excluindo IA)
            $agent['avg_response_time'] = self::getAverageFirstResponseTime(
                $agent['id'], 
                $dateFrom, 
                $dateTo
            );
        }
        
        return $agents;
    });
}
```

### ✅ Resultado Esperado

**Antes**:
- ❌ Query: 1+ segundo a cada load
- ❌ Filtros de data = nova query pesada

**Depois**:
- ✅ Primeira requisição: 1 segundo
- ✅ Próximas 2 minutos: < 0.01 segundos
- ✅ Dashboard carrega muito mais rápido

**Ganho**: ~90% de redução no load do dashboard

---

## 📊 Impacto Geral das Otimizações

### Antes (Sem Cache)
```
Load Dashboard: ~1.5 segundos
Navegação entre conversas: 3 segundos por clique
10 conversas = 30 segundos de espera
CPU: 60-80% constante
Slow log: 100+ queries/hora
```

### Depois (Com Cache)
```
Load Dashboard: ~0.5 segundos (primeira vez), ~0.1s (subsequentes)
Navegação entre conversas: 0.01 segundos (na maioria das vezes)
10 conversas = 0.1 segundos de espera
CPU: 20-30% normal
Slow log: 5-10 queries/hora (apenas cache misses)
```

### ⚡ Ganho Total
- **95%** de redução no tempo de resposta médio
- **70%** de redução no uso de CPU
- **90%** de redução em queries no slow log

---

## 🛠️ Implementação (Passo a Passo)

### 1️⃣ Criar o Helper de Cache (5 min)
```bash
# Criar arquivo
touch app/Helpers/Cache.php

# Copiar código acima para o arquivo
# Criar diretório de cache
mkdir -p storage/cache/queries
chmod 777 storage/cache/queries
```

### 2️⃣ Modificar ContactController (3 min)
- Abrir `app/Controllers/ContactController.php`
- Envolver a query do `$stats` com `Cache::remember()` (ver código acima)

### 3️⃣ Modificar AgentPerformanceService (2 min)
- Abrir `app/Services/AgentPerformanceService.php`
- Envolver todo o método `getAgentsRanking()` com `Cache::remember()` (ver código acima)

### 4️⃣ Testar (5 min)
```bash
# Limpar cache se necessário
rm -rf storage/cache/queries/*

# Acessar dashboard
# 1ª vez: deve demorar ~1 segundo
# 2ª vez: deve ser instantâneo (< 0.1s)

# Navegar entre conversas
# 1ª vez cada contato: ~3 segundos
# Próximas vezes: instantâneo
```

### 5️⃣ Monitorar (contínuo)
```bash
# Ver se slow log diminuiu
tail -f /var/log/mysql/slow.log

# Ver uso de CPU
top -p $(pgrep -f php)
```

---

## 🔄 Manutenção

### Limpar Cache Manualmente (se necessário)
```bash
rm -rf storage/cache/queries/*
```

### Limpar Cache Programaticamente
```php
// Limpar tudo
\App\Helpers\Cache::clear();

// Limpar cache específico de um contato
\App\Helpers\Cache::forget("contact_history_{$contactId}");

// Limpar cache do ranking
\App\Helpers\Cache::forget("agents_ranking_*"); // você precisaria implementar um clearPattern()
```

### Ajustar Tempo de Cache (se necessário)
```php
// Histórico do contato
// Atual: 300 segundos (5 minutos)
// Pode aumentar para 600 (10 min) ou 900 (15 min) se quiser mais performance

// Ranking de agentes
// Atual: 120 segundos (2 minutos)
// Pode aumentar para 300 (5 min) se dashboard não precisar ser tão real-time
```

---

## ⚠️ Considerações Importantes

### ✅ Vantagens
1. Implementação rápida (15 minutos)
2. Ganho imediato de performance
3. Sem mudanças no banco de dados
4. Sem impacto no frontend
5. Fácil de reverter se necessário

### ⚠️ Limitações
1. Cache em arquivo (não é cluster-friendly)
2. Dados podem ficar "defasados" por alguns minutos
3. Não resolve o problema da query em si (apenas mascara)

### 🔮 Próximos Passos (Longo Prazo)
1. Adicionar índices compostos nas tabelas
2. Criar tabela materializada para histórico
3. Usar Redis/Memcached em vez de cache em arquivo
4. Otimizar a query em si (window functions, CTEs)
5. Mover cálculo para background job

---

**Data**: 2026-01-12  
**Versão**: 1.0  
**Prioridade**: 🔴 CRÍTICA  
**Tempo de Implementação**: ~15 minutos  
**Ganho Esperado**: 95% de melhoria

