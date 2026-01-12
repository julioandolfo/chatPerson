# 🚨 Análise Completa: Polling Excessivo Causando CPU Alto

**Data**: 2026-01-12  
**Prioridade**: 🔴 CRÍTICA  
**Problema**: Múltiplos pollings executando queries pesadas constantemente

---

## 🎯 RESUMO EXECUTIVO

Seu sistema tem **7 pollings diferentes** executando simultaneamente, alguns a cada **3 segundos**. Isso significa que para cada usuário conectado, centenas de queries são executadas por minuto, causando:

- **CPU: 60-80% constante**
- **Slow log: 100+ queries/hora**
- **Latência: Dashboard lento**
- **Escalabilidade: Problema se aumenta usuários**

---

## 🔴 POLLING #1: Mensagens (CRÍTICO)

### 📍 Localização
**Arquivo**: `views/conversations/index.php:7090`

```javascript
pollingInterval = setInterval(() => {
    checkForNewMessages(conversationId);
}, 3000); // A CADA 3 SEGUNDOS ⚠️
```

### 🔥 Query Executada
```javascript
// Endpoint: GET /conversations/{id}/messages?last_message_id=X
const url = `/conversations/${conversationId}/messages?last_message_id=${lastMessageId}`;
```

### 💥 Impacto
- **Frequência**: A cada 3 segundos
- **Por Usuário**: 20 queries/minuto, 1.200 queries/hora
- **10 Usuários**: 12.000 queries/hora
- **Query**: Busca novas mensagens com JOIN na tabela `messages`

### ⚡ Problema
1. **Intervalo muito curto** (3 segundos)
2. **Mesmo com WebSocket ativo**, ainda faz polling!
3. **Não há rate limiting**
4. **Não verifica se há inatividade**

### ✅ Solução
```javascript
// 1. AUMENTAR intervalo para 15-30 segundos
pollingInterval = setInterval(() => {
    checkForNewMessages(conversationId);
}, 30000); // 30 segundos

// 2. DESABILITAR polling se WebSocket estiver ativo
if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
    startPolling(currentConversationId);
}

// 3. PARAR polling após 5 minutos de inatividade
let lastActivityTime = Date.now();
if (Date.now() - lastActivityTime > 300000) { // 5 minutos
    stopPolling();
}
```

---

## 🟠 POLLING #2: Badges de Conversas (PESADO)

### 📍 Localização
**Arquivo**: `views/conversations/index.php:16750`

```javascript
setInterval(() => {
    refreshConversationBadges();
}, 10000); // A CADA 10 SEGUNDOS ⚠️
```

### 🔥 Query Executada
```javascript
// Endpoint: GET /conversations (com filtros)
// Retorna TODAS as conversas para atualizar badges
fetch(`?${params.toString()}`)
```

### 💥 Impacto
- **Frequência**: A cada 10 segundos
- **Por Usuário**: 6 queries/minuto, 360 queries/hora
- **Query**: Busca TODAS as conversas com unread_count
- **Problema**: Query pesada que examina múltiplas tabelas

### ⚡ Problema
1. **Busca TODAS as conversas** ao invés de apenas contadores
2. **Query não tem cache**
3. **Executa mesmo se usuário estiver inativo**
4. **WebSocket deveria substituir isso**

### ✅ Solução
```javascript
// 1. AUMENTAR intervalo para 60 segundos
setInterval(() => {
    refreshConversationBadges();
}, 60000); // 1 minuto

// 2. CRIAR endpoint leve só para contadores
// GET /conversations/unread-counts
// Retorna: { conversation_id: unread_count }

// 3. DESABILITAR se WebSocket estiver ativo
if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
    setInterval(refreshConversationBadges, 60000);
}
```

---

## 🟡 POLLING #3: SLA Indicators (MÉDIO)

### 📍 Localização
**Arquivo**: `public/assets/js/custom/sla-indicator.js:82`

```javascript
setInterval(() => {
    this.updateAllIndicators();
}, 10000); // A CADA 10 SEGUNDOS ⚠️
```

### 💥 Impacto
- **Frequência**: A cada 10 segundos
- **Por Usuário**: 6 queries/minuto, 360 queries/hora
- **Query**: Busca SLA de TODAS as conversas visíveis

### ⚡ Problema
1. **Atualiza indicadores mesmo sem mudanças**
2. **Não precisa ser tão frequente** (SLA é em horas, não segundos)
3. **WebSocket deveria notificar mudanças**

### ✅ Solução
```javascript
// 1. AUMENTAR intervalo para 60 segundos
setInterval(() => {
    this.updateAllIndicators();
}, 60000); // 1 minuto

// 2. ATUALIZAR apenas quando conversa muda
document.addEventListener('realtime:conversation_updated', (e) => {
    if (e.detail && e.detail.conversation_id) {
        this.updateConversation(e.detail.conversation_id, e.detail);
    }
});
```

---

## 🟡 POLLING #4: Coaching Hints (MÉDIO)

### 📍 Localização
**Arquivo**: `public/assets/js/coaching-inline.js:62`

```javascript
setInterval(() => {
    if (this.conversationId) {
        console.log('[CoachingInline] Polling - buscando novos hints...');
        this.loadHints();
    }
}, 10000); // A CADA 10 SEGUNDOS ⚠️
```

### 💥 Impacto
- **Frequência**: A cada 10 segundos
- **Por Usuário**: 6 queries/minuto, 360 queries/hora
- **Query**: Busca hints de coaching para conversa atual
- **Problema**: Query com múltiplos JOINs (coaching_hints + conversations + messages)

### ⚡ Problema
1. **Busca hints mesmo se não houver novos**
2. **Executa para TODOS os usuários**, mesmo quem não usa coaching
3. **Não tem cache**

### ✅ Solução
```javascript
// 1. AUMENTAR intervalo para 30-60 segundos
setInterval(() => {
    if (this.conversationId) {
        this.loadHints();
    }
}, 60000); // 1 minuto

// 2. DESABILITAR se usuário não tem permissão de coaching
if (window.user && window.user.has_coaching_access) {
    this.startPolling();
}

// 3. CACHE de hints por 30 segundos no backend
```

---

## 🟢 POLLING #5: Convites Pendentes (LEVE)

### 📍 Localização
**Arquivo**: `views/conversations/index.php:5767`

```javascript
setInterval(loadPendingInvitesCount, 30000); // A CADA 30 SEGUNDOS
```

### 💥 Impacto
- **Frequência**: A cada 30 segundos
- **Por Usuário**: 2 queries/minuto, 120 queries/hora
- **Query**: Conta convites e solicitações pendentes

### ⚡ Status
- ✅ **Intervalo aceitável** (30 segundos)
- ✅ **Query leve** (apenas COUNT)
- ⚠️ **WebSocket deveria substituir**

### ✅ Solução
```javascript
// JÁ TEM WebSocket listener, então:
// DESABILITAR polling se WebSocket estiver ativo
if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
    setInterval(loadPendingInvitesCount, 30000);
}
```

---

## 🟢 POLLING #6: Activity Tracker (LEVE)

### 📍 Localização
**Arquivo**: `public/assets/js/activity-tracker.js:165`

```javascript
this.heartbeatInterval = setInterval(() => {
    this.sendHeartbeat();
}, 30000); // A CADA 30 SEGUNDOS
```

### 💥 Impacto
- **Frequência**: A cada 30 segundos
- **Por Usuário**: 2 queries/minuto, 120 queries/hora
- **Query**: UPDATE na tabela `users` (last_activity_at)

### ⚡ Status
- ✅ **Intervalo aceitável** (30 segundos)
- ✅ **Query rápida** (UPDATE por ID)
- ✅ **Necessário para status online/offline**

### ✅ Solução
```javascript
// MANTER como está, mas adicionar:
// 1. PARAR heartbeat se tab estiver inativa
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        this.stopHeartbeat();
    } else {
        this.startHeartbeat();
    }
});
```

---

## 🟢 POLLING #7: Tempos Relativos (LEVE)

### 📍 Localização
**Arquivo**: `views/conversations/index.php:16755`

```javascript
setInterval(() => {
    updateConversationTimes();
}, 30000); // A CADA 30 SEGUNDOS
```

### 💥 Impacto
- **Frequência**: A cada 30 segundos
- **Query**: NENHUMA (apenas atualiza UI)
- **Impacto**: Zero no backend

### ⚡ Status
- ✅ **Apenas JavaScript** (sem requisições)
- ✅ **Necessário para "há 5 minutos" ficar atualizado**

---

## 🚨 POLLING #8: Dashboard Metrics (DESCOBERTO)

### 📍 Localização
**Observação**: Não encontrei polling automático no dashboard, mas:

### 💥 Problema
Ao **carregar dashboard**, executa **múltiplas queries pesadas**:

```php
// DashboardController::index()
$generalStats = DashboardService::getGeneralStats();        // Query pesada
$departmentStats = DashboardService::getDepartmentStats(); // Query pesada
$funnelStats = DashboardService::getFunnelStats();         // Query pesada
$topAgents = DashboardService::getTopAgents();             // Query pesada (já otimizada)
$allAgentsMetrics = DashboardService::getAllAgentsMetrics(); // Query pesada
$teamsMetrics = ...                                        // Query pesada
$conversionRanking = ...                                   // Query pesada
// + 4 outras queries
```

### ⚡ Total
- **12 queries pesadas** a cada load do dashboard
- Se dashboard **auto-refresh** (não encontrei, mas pode ter), isso é crítico

### ✅ Solução
1. **Cache de 5 minutos** em TODAS essas queries
2. **Lazy loading**: carregar métricas sob demanda
3. **Consolidar queries**: buscar tudo em uma query só

---

## 📊 IMPACTO TOTAL

### Por Usuário (1 hora)
| Polling | Queries/hora | Impacto |
|---------|--------------|---------|
| #1 Mensagens | 1.200 | 🔴 CRÍTICO |
| #2 Badges | 360 | 🔴 CRÍTICO |
| #3 SLA | 360 | 🟠 ALTO |
| #4 Coaching | 360 | 🟠 ALTO |
| #5 Convites | 120 | 🟢 BAIXO |
| #6 Heartbeat | 120 | 🟢 BAIXO |
| #7 Tempos | 0 | ✅ ZERO |
| **TOTAL** | **2.520** | **🔴 CRÍTICO** |

### 10 Usuários Simultâneos
- **25.200 queries/hora**
- **420 queries/minuto**
- **7 queries/segundo**

### 50 Usuários Simultâneos (pico)
- **126.000 queries/hora**
- **2.100 queries/minuto**
- **35 queries/segundo** ⚠️ **INVIÁVEL**

---

## ✅ PLANO DE OTIMIZAÇÃO IMEDIATA

### Prioridade 1: CRÍTICO (implementar AGORA)

#### 1.1. Reduzir Polling de Mensagens
**Arquivo**: `views/conversations/index.php` (linha 7090)

```javascript
// ANTES
pollingInterval = setInterval(() => {
    checkForNewMessages(conversationId);
}, 3000); // 3 segundos

// DEPOIS
pollingInterval = setInterval(() => {
    // Só fazer polling se WebSocket não estiver ativo
    if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
        checkForNewMessages(conversationId);
    }
}, 30000); // 30 segundos
```

**Ganho**: 90% de redução (1.200 → 120 queries/hora)

#### 1.2. Reduzir Polling de Badges
**Arquivo**: `views/conversations/index.php` (linha 16750)

```javascript
// ANTES
setInterval(() => {
    refreshConversationBadges();
}, 10000); // 10 segundos

// DEPOIS
// Só fazer polling se WebSocket não estiver ativo
if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
    setInterval(() => {
        refreshConversationBadges();
    }, 60000); // 1 minuto
}
```

**Ganho**: 83% de redução (360 → 60 queries/hora)

### Prioridade 2: ALTO (implementar esta semana)

#### 2.1. Reduzir Polling de SLA
**Arquivo**: `public/assets/js/custom/sla-indicator.js` (linha 82)

```javascript
// ANTES
setInterval(() => {
    this.updateAllIndicators();
}, 10000); // 10 segundos

// DEPOIS
setInterval(() => {
    this.updateAllIndicators();
}, 60000); // 1 minuto
```

**Ganho**: 83% de redução (360 → 60 queries/hora)

#### 2.2. Reduzir Polling de Coaching
**Arquivo**: `public/assets/js/coaching-inline.js` (linha 62)

```javascript
// ANTES
setInterval(() => {
    if (this.conversationId) {
        this.loadHints();
    }
}, 10000); // 10 segundos

// DEPOIS
// Verificar se usuário tem acesso a coaching
if (window.user && window.user.has_coaching_access) {
    setInterval(() => {
        if (this.conversationId) {
            this.loadHints();
        }
    }, 60000); // 1 minuto
}
```

**Ganho**: 83% de redução (360 → 60 queries/hora)

### Prioridade 3: MÉDIO (implementar próxima semana)

#### 3.1. Criar Endpoint Leve para Badges
**Novo Arquivo**: `app/Controllers/ConversationController.php`

```php
/**
 * Obter apenas contadores de não lidas (leve)
 * GET /conversations/unread-counts
 */
public function getUnreadCounts(): void
{
    $userId = \App\Helpers\Auth::id();
    
    // Query leve: apenas contadores
    $sql = "SELECT 
                conversation_id,
                COUNT(*) as unread_count
            FROM messages
            WHERE conversation_id IN (
                SELECT id FROM conversations 
                WHERE agent_id = ? OR id IN (
                    SELECT conversation_id FROM conversation_mentions 
                    WHERE user_id = ? AND status = 'accepted'
                )
            )
            AND read_at IS NULL
            AND sender_type != 'agent'
            GROUP BY conversation_id";
    
    $counts = \App\Helpers\Database::fetchAll($sql, [$userId, $userId]);
    
    Response::json([
        'success' => true,
        'counts' => $counts
    ]);
}
```

#### 3.2. Adicionar Cache em Todas as Queries do Dashboard

Ver arquivo `CRIAR_CACHE_DASHBOARD.md` (criado a seguir)

---

## 📊 GANHO ESPERADO

### Após Implementar Prioridade 1 + 2

| Métrica | Antes | Depois | Ganho |
|---------|-------|--------|-------|
| Queries/hora (1 usuário) | 2.520 | 360 | **86%** ⚡ |
| Queries/minuto (10 usuários) | 420 | 60 | **86%** ⚡ |
| Queries/segundo (50 usuários) | 35 | 5 | **86%** ⚡ |
| CPU | 60-80% | 15-25% | **70%** 🎯 |
| Slow log | 100+ q/h | 10-15 q/h | **90%** 📉 |

---

## 🔧 IMPLEMENTAÇÃO PASSO A PASSO

### Passo 1: Criar Backup
```bash
cp views/conversations/index.php views/conversations/index.php.backup
cp public/assets/js/custom/sla-indicator.js public/assets/js/custom/sla-indicator.js.backup
cp public/assets/js/coaching-inline.js public/assets/js/coaching-inline.js.backup
```

### Passo 2: Aplicar Patches

Ver arquivos criados:
- `PATCH_POLLING_MENSAGENS.js`
- `PATCH_POLLING_BADGES.js`
- `PATCH_POLLING_SLA.js`
- `PATCH_POLLING_COACHING.js`

### Passo 3: Testar
```bash
# 1. Limpar cache
rm -rf storage/cache/queries/*

# 2. Abrir dashboard
# 3. Abrir console do navegador (F12)
# 4. Verificar se pollings foram reduzidos
# 5. Monitorar CPU do MySQL
```

### Passo 4: Monitorar
```bash
# Ver queries executadas
tail -f /var/log/mysql/slow.log

# Ver CPU
top -p $(pgrep -f mysql)
```

---

## ⚠️ IMPORTANTE

### Não Quebre o WebSocket!
- WebSocket **JÁ ESTÁ FUNCIONANDO**
- Pollings devem ser **fallback apenas**
- Sempre verificar se WebSocket está ativo antes de fazer polling

### Teste em Homologação Primeiro
- Essas mudanças afetam UX
- Usuários podem perceber "delay" em updates
- Teste com 2-3 usuários antes de deploy em produção

### Monitore Após Deploy
- CPU deve cair para 15-25%
- Slow log deve ter 90% menos queries
- Usuários não devem reclamar de "sistema lento"

---

## 📝 PRÓXIMOS PASSOS

1. ✅ Criar índices (já feito)
2. ✅ Adicionar cache em queries pesadas (já feito em 2 queries)
3. ⏳ **Reduzir polling** (este documento)
4. ⏳ Cache em TODAS as queries do dashboard
5. ⏳ Endpoint leve para badges
6. ⏳ Lazy loading no dashboard

---

**Autor**: Análise baseada no código + slow.log  
**Status**: 🔴 CRÍTICO - Implementar AGORA  
**Tempo Estimado**: 2-3 horas  
**Ganho Esperado**: 86% de redução em queries
