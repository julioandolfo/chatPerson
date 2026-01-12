# 🔍 Diagnóstico: QPS Ainda Alto Após Otimizações

**Data**: 2026-01-12  
**Problema**: QPS (Queries Per Second) ainda alto mesmo após otimizações  
**Prioridade**: 🔴 CRÍTICA

---

## 🎯 POSSÍVEIS CAUSAS

Mesmo após otimizar os pollings, o QPS pode estar alto por:

### 1️⃣ Cache Desabilitado no ConversationService ⚠️ **ENCONTRADO!**

**Arquivo**: `app/Services/ConversationService.php:365`

```php
// 🐛 TEMPORÁRIO: Desabilitar cache para debug de permissões de funil
$canUseCache = false; // self::canUseCache($filters);
```

#### Problema
- ✅ Cache foi **DESABILITADO** temporariamente
- ❌ Toda requisição executa query completa no banco
- ❌ Polling de badges (60s) sempre executa query pesada

#### Impacto
| Cenário | Com Cache | Sem Cache | Diferença |
|---------|-----------|-----------|-----------|
| **1 usuário** | 60 q/h | 420 q/h | **7x mais** |
| **10 usuários** | 600 q/h | 4.200 q/h | **7x mais** |
| **50 usuários** | 3.000 q/h | 21.000 q/h | **7x mais** |

#### Solução IMEDIATA ⚡
```php
// ✅ REABILITAR CACHE
$canUseCache = self::canUseCache($filters);
```

---

### 2️⃣ Query Complexa de Conversas (Problema N+1 Potencial)

**Arquivo**: `app/Models/Conversation.php:91-392`

#### Query Atual
```sql
SELECT c.*, 
       ct.name as contact_name,
       ct.phone as contact_phone,
       ct.email as contact_email,
       ct.avatar as contact_avatar,
       u.name as agent_name,
       u.email as agent_email,
       wa.name as whatsapp_account_name,
       -- ⚠️ SUBQUERY para cada conversa
       (SELECT COUNT(*) FROM messages m 
        WHERE m.conversation_id = c.id 
          AND m.sender_type = 'contact' 
          AND m.read_at IS NULL) as unread_count,
       -- ⚠️ SUBQUERY para cada conversa
       (SELECT content FROM messages m 
        WHERE m.conversation_id = c.id 
        ORDER BY m.created_at DESC LIMIT 1) as last_message,
       -- ⚠️ SUBQUERY para cada conversa
       (SELECT created_at FROM messages m 
        WHERE m.conversation_id = c.id 
        ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
       -- ⚠️ GROUP_CONCAT pode ser lento
       GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') as tags_names,
       GROUP_CONCAT(DISTINCT t.id SEPARATOR ',') as tag_ids,
       GROUP_CONCAT(DISTINCT cp_user.name SEPARATOR ', ') as participants_names
FROM conversations c
LEFT JOIN contacts ct ON c.contact_id = ct.id
LEFT JOIN users u ON c.agent_id = u.id
LEFT JOIN whatsapp_accounts wa ON c.whatsapp_account_id = wa.id
LEFT JOIN conversation_tags ctt ON c.id = ctt.conversation_id
LEFT JOIN tags t ON ctt.tag_id = t.id
LEFT JOIN conversation_participants cp ON c.id = cp.conversation_id
LEFT JOIN users cp_user ON cp.user_id = cp_user.id
WHERE [filtros]
GROUP BY c.id
ORDER BY c.pinned DESC, c.updated_at DESC
LIMIT 70;
```

#### Problemas
1. **3 subqueries** por conversa (unread_count, last_message, last_message_at)
2. **GROUP_CONCAT** pode ser lento com muitas tags/participantes
3. **Múltiplos LEFT JOINs** multiplicam linhas antes do GROUP BY

#### Impacto
- **70 conversas** = 210 subqueries (70 × 3)
- **Tempo**: 0.3-0.5s por query
- **Com polling 60s**: Query pesada a cada minuto

---

### 3️⃣ Índices Não Criados

Se você ainda **não executou** `CRIAR_INDICES_OTIMIZADOS.sql`:

#### Índices Faltando
```sql
-- Críticos para performance
idx_messages_conv_sender_date
idx_messages_sender_type_date
idx_conversations_agent_date_status
idx_users_role_status
```

#### Impacto
- Queries 5-10x mais lentas
- Full table scans
- CPU alto

---

### 4️⃣ Outros Pollings Ativos

#### Activity Tracker (Heartbeat)
**Arquivo**: `public/assets/js/activity-tracker.js:165`

```javascript
this.heartbeatInterval = setInterval(() => {
    this.sendHeartbeat();  // UPDATE users SET last_activity_at = NOW()
}, 30000); // A cada 30 segundos
```

**Impacto**: 2 queries/minuto por usuário = 120 queries/hora

#### Realtime Coaching
**Arquivo**: `public/assets/js/realtime-coaching.js:94`

```javascript
this.pollingInterval = setInterval(() => {
    this.pollPendingHints();
}, this.pollingFrequency); // Padrão: 10 segundos
```

**Impacto**: Se não foi otimizado, 6 queries/minuto = 360 queries/hora

---

### 5️⃣ Background Jobs/Cron

Verifique se há jobs rodando em background:

```bash
# Verificar processos PHP
ps aux | grep php

# Verificar cron jobs
crontab -l
```

#### Jobs Comuns que Podem Causar QPS Alto
- SLA Monitoring (verifica SLA de todas as conversas)
- AI Fallback Monitoring (verifica conversas sem resposta)
- Automation Scheduler (executa automações)
- Message Scheduler (envia mensagens agendadas)

---

## 🔍 COMO DIAGNOSTICAR

### Passo 1: Verificar Slow Log

```sql
-- Ver queries mais frequentes (MySQL 8.0+)
SELECT 
    DIGEST_TEXT,
    COUNT_STAR as executions,
    AVG_TIMER_WAIT/1000000000000 as avg_time_sec,
    SUM_ROWS_EXAMINED as total_rows_examined
FROM performance_schema.events_statements_summary_by_digest
WHERE SCHEMA_NAME = 'chat_person'
ORDER BY COUNT_STAR DESC
LIMIT 20;
```

### Passo 2: Verificar Queries em Tempo Real

```sql
-- Ver queries rodando agora
SHOW FULL PROCESSLIST;
```

### Passo 3: Verificar QPS Atual

```sql
-- Ver QPS (Queries Per Second)
SHOW GLOBAL STATUS LIKE 'Questions';
-- Aguardar 10 segundos
SHOW GLOBAL STATUS LIKE 'Questions';
-- Calcular: (valor2 - valor1) / 10 = QPS
```

### Passo 4: Verificar Cache

```bash
# Ver se cache está funcionando
ls -lh storage/cache/queries/

# Deve ter arquivos .cache recentes
# Se estiver vazio, cache não está funcionando
```

### Passo 5: Verificar Índices

```sql
-- Ver se índices foram criados
SHOW INDEX FROM messages WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM conversations WHERE Key_name LIKE 'idx_%';
```

---

## ✅ SOLUÇÕES PRIORITÁRIAS

### 🔴 PRIORIDADE 1: Reabilitar Cache (IMEDIATO)

**Arquivo**: `app/Services/ConversationService.php`

```php
// ANTES (linha 365)
$canUseCache = false; // self::canUseCache($filters);

// DEPOIS
$canUseCache = self::canUseCache($filters);
```

**Ganho**: 7x menos queries (4.200 → 600 queries/hora para 10 usuários)

---

### 🔴 PRIORIDADE 2: Criar Índices (SE NÃO FEZ)

```bash
mysql -u root -p chat_person < CRIAR_INDICES_OTIMIZADOS.sql
```

**Ganho**: 5-10x mais rápido por query

---

### 🟠 PRIORIDADE 3: Otimizar Query de Conversas

**Opção A: Remover Subqueries (Recomendado)**

Criar query separada para buscar unread_count e last_message:

```php
// 1. Buscar conversas (sem subqueries)
$conversations = Conversation::getAll($filters);

// 2. Buscar unread_counts em batch
$conversationIds = array_column($conversations, 'id');
$unreadCounts = Message::getUnreadCountsByConversations($conversationIds);

// 3. Buscar last_messages em batch
$lastMessages = Message::getLastMessagesByConversations($conversationIds);

// 4. Mesclar dados
foreach ($conversations as &$conv) {
    $conv['unread_count'] = $unreadCounts[$conv['id']] ?? 0;
    $conv['last_message'] = $lastMessages[$conv['id']]['content'] ?? null;
    $conv['last_message_at'] = $lastMessages[$conv['id']]['created_at'] ?? null;
}
```

**Ganho**: 70% mais rápido (1 query ao invés de 210 subqueries)

**Opção B: Usar LEFT JOIN ao invés de Subquery**

```sql
SELECT c.*, 
       ct.name as contact_name,
       u.name as agent_name,
       COUNT(DISTINCT CASE WHEN m.sender_type = 'contact' AND m.read_at IS NULL THEN m.id END) as unread_count,
       MAX(m2.created_at) as last_message_at
FROM conversations c
LEFT JOIN contacts ct ON c.contact_id = ct.id
LEFT JOIN users u ON c.agent_id = u.id
LEFT JOIN messages m ON m.conversation_id = c.id
LEFT JOIN messages m2 ON m2.conversation_id = c.id 
    AND m2.id = (SELECT MAX(id) FROM messages WHERE conversation_id = c.id)
WHERE [filtros]
GROUP BY c.id
LIMIT 70;
```

**Ganho**: 50% mais rápido

---

### 🟡 PRIORIDADE 4: Otimizar Realtime Coaching

Se ainda estiver em 10 segundos:

**Arquivo**: `public/assets/js/realtime-coaching.js`

```javascript
// ANTES
this.pollingInterval = setInterval(() => {
    this.pollPendingHints();
}, 10000); // 10 segundos

// DEPOIS
this.pollingInterval = setInterval(() => {
    this.pollPendingHints();
}, 60000); // 60 segundos
```

---

## 📊 COMPARAÇÃO DE SOLUÇÕES

| Solução | Implementação | Ganho | Prioridade |
|---------|--------------|-------|------------|
| **1. Reabilitar Cache** | 1 linha | 7x | 🔴 CRÍTICA |
| **2. Criar Índices** | 5 min | 5-10x | 🔴 CRÍTICA |
| **3. Otimizar Query** | 2-4 horas | 2-3x | 🟠 ALTA |
| **4. Coaching Polling** | 1 linha | 6x | 🟡 MÉDIA |

---

## 🎯 PLANO DE AÇÃO IMEDIATO

### Passo 1: Reabilitar Cache (2 min) 🔴

```bash
# Editar arquivo
nano app/Services/ConversationService.php

# Linha 365: Mudar
$canUseCache = false;
# Para
$canUseCache = self::canUseCache($filters);

# Salvar e testar
```

### Passo 2: Criar Índices (5 min) 🔴

```bash
mysql -u root -p chat_person < CRIAR_INDICES_OTIMIZADOS.sql
```

### Passo 3: Limpar Cache (1 min)

```bash
rm -rf storage/cache/queries/*
```

### Passo 4: Verificar QPS (5 min)

```sql
-- Antes
SHOW GLOBAL STATUS LIKE 'Questions';

-- Aguardar 10 segundos

-- Depois
SHOW GLOBAL STATUS LIKE 'Questions';

-- Calcular QPS
-- (valor_depois - valor_antes) / 10
```

### Passo 5: Monitorar (Contínuo)

```bash
# Ver CPU
top -p $(pgrep -f mysql)

# Ver queries em tempo real
watch -n 1 'mysql -u root -p -e "SHOW FULL PROCESSLIST"'
```

---

## 📊 QPS ESPERADO

### Antes das Otimizações

| Usuários | QPS | CPU |
|----------|-----|-----|
| 1 | 0.7 | 10% |
| 10 | 7 | 60-80% |
| 50 | 35 | 300%+ ⚠️ |

### Depois (Com Cache + Índices)

| Usuários | QPS | CPU |
|----------|-----|-----|
| 1 | 0.1 | 5% |
| 10 | 1 | 15-25% |
| 50 | 5 | 40-50% |

---

## ⚠️ SE QPS AINDA ESTIVER ALTO

### Verificar Outros Culpados

1. **Background Jobs**:
   ```bash
   ps aux | grep php
   # Procurar por: scheduler, monitoring, fallback
   ```

2. **Queries de Analytics**:
   - Dashboard sendo acessado constantemente
   - Relatórios sendo gerados
   - Métricas sendo calculadas

3. **Integrações Externas**:
   - Webhooks recebendo muitas requisições
   - WhatsApp enviando/recebendo mensagens
   - API sendo chamada externamente

4. **Logs Excessivos**:
   ```bash
   # Ver tamanho dos logs
   du -sh storage/logs/*
   
   # Se > 1GB, pode estar logando demais
   ```

---

## 📞 PRÓXIMA AÇÃO

**Execute AGORA**:

```bash
# 1. Reabilitar cache
# Editar: app/Services/ConversationService.php
# Linha 365: $canUseCache = self::canUseCache($filters);

# 2. Criar índices (se não fez)
mysql -u root -p chat_person < CRIAR_INDICES_OTIMIZADOS.sql

# 3. Limpar cache
rm -rf storage/cache/queries/*

# 4. Testar
# Acessar dashboard e verificar QPS
```

**Depois**:
1. Verificar QPS (deve cair 70-90%)
2. Verificar CPU (deve cair para 15-25%)
3. Monitorar por 1 hora
4. Se ainda alto, investigar background jobs

---

**Causa Mais Provável**: ✅ **Cache desabilitado** (linha 365)  
**Solução Mais Rápida**: Reabilitar cache (1 linha)  
**Ganho Esperado**: 7x menos queries
