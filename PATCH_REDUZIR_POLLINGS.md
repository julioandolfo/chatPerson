# 🔧 PATCH: Reduzir Pollings Excessivos

**Data**: 2026-01-12  
**Objetivo**: Reduzir 86% das queries repetitivas  
**Tempo**: 30 minutos

---

## 📋 CHECKLIST

- [ ] 1. Criar backup dos arquivos
- [ ] 2. Aplicar Patch #1 - Polling de Mensagens
- [ ] 3. Aplicar Patch #2 - Polling de Badges
- [ ] 4. Aplicar Patch #3 - Polling de SLA
- [ ] 5. Aplicar Patch #4 - Polling de Coaching
- [ ] 6. Limpar cache
- [ ] 7. Testar
- [ ] 8. Monitorar CPU

---

## 1️⃣ PATCH #1: Polling de Mensagens

### Backup
```bash
cp views/conversations/index.php views/conversations/index.php.backup
```

### Localizar
**Arquivo**: `views/conversations/index.php`  
**Linha**: ~7090

### ANTES
```javascript
// Verificar novas mensagens a cada 3 segundos
pollingInterval = setInterval(() => {
    checkForNewMessages(conversationId);
}, 3000);
```

### DEPOIS
```javascript
// Verificar novas mensagens a cada 30 segundos (apenas se WebSocket não estiver ativo)
pollingInterval = setInterval(() => {
    // Só fazer polling se WebSocket não estiver ativo
    if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
        checkForNewMessages(conversationId);
    } else {
        console.log('[Polling] WebSocket ativo, pulando polling de mensagens');
    }
}, 30000); // 30 segundos ao invés de 3
```

### Ganho
- **Antes**: 1.200 queries/hora por usuário
- **Depois**: 120 queries/hora por usuário
- **Redução**: 90% ⚡

---

## 2️⃣ PATCH #2: Polling de Badges

### Localizar
**Arquivo**: `views/conversations/index.php`  
**Linha**: ~16750

### ANTES
```javascript
// Sistema de atualização periódica da lista de conversas (para badges de não lidas)
// Atualizar a cada 10 segundos para verificar novas mensagens em todas as conversas
let conversationListUpdateInterval = setInterval(() => {
    refreshConversationBadges();
}, 10000);
```

### DEPOIS
```javascript
// Sistema de atualização periódica da lista de conversas (para badges de não lidas)
// Atualizar apenas se WebSocket não estiver disponível
if (!window.wsClient || window.wsClient.readyState !== WebSocket.OPEN) {
    console.log('[Badges] WebSocket inativo, habilitando polling de badges');
    let conversationListUpdateInterval = setInterval(() => {
        refreshConversationBadges();
    }, 60000); // 1 minuto ao invés de 10 segundos
} else {
    console.log('[Badges] WebSocket ativo, polling de badges desabilitado');
}
```

### Localizar TAMBÉM (modo sem WebSocket)
**Linha**: ~16774

### ANTES
```javascript
// Sistema de atualização periódica da lista de conversas (para badges de não lidas)
let conversationListUpdateInterval = setInterval(() => {
    refreshConversationBadges();
}, 10000); // 10 segundos
```

### DEPOIS
```javascript
// Sistema de atualização periódica da lista de conversas (para badges de não lidas)
let conversationListUpdateInterval = setInterval(() => {
    refreshConversationBadges();
}, 60000); // 1 minuto ao invés de 10 segundos
```

### Ganho
- **Antes**: 360 queries/hora por usuário
- **Depois**: 60 queries/hora por usuário (ou 0 se WebSocket ativo)
- **Redução**: 83-100% ⚡

---

## 3️⃣ PATCH #3: Polling de SLA

### Backup
```bash
cp public/assets/js/custom/sla-indicator.js public/assets/js/custom/sla-indicator.js.backup
```

### Localizar
**Arquivo**: `public/assets/js/custom/sla-indicator.js`  
**Linha**: ~82

### ANTES
```javascript
// Atualizar a cada 10 segundos para resposta mais rápida
setInterval(() => {
    this.updateAllIndicators();
}, 10000);
```

### DEPOIS
```javascript
// Atualizar a cada 60 segundos (SLA é medido em horas, não precisa atualizar tão rápido)
setInterval(() => {
    this.updateAllIndicators();
}, 60000); // 1 minuto ao invés de 10 segundos
```

### Ganho
- **Antes**: 360 queries/hora por usuário
- **Depois**: 60 queries/hora por usuário
- **Redução**: 83% ⚡

---

## 4️⃣ PATCH #4: Polling de Coaching

### Backup
```bash
cp public/assets/js/coaching-inline.js public/assets/js/coaching-inline.js.backup
```

### Localizar
**Arquivo**: `public/assets/js/coaching-inline.js`  
**Linha**: ~62

### ANTES
```javascript
startPolling() {
    // Polling a cada 10 segundos para buscar novos hints
    setInterval(() => {
        if (this.conversationId) {
            console.log('[CoachingInline] Polling - buscando novos hints...');
            this.loadHints();
        }
    }, 10000);
}
```

### DEPOIS
```javascript
startPolling() {
    // Polling a cada 60 segundos para buscar novos hints (coaching não é tempo real crítico)
    setInterval(() => {
        if (this.conversationId) {
            console.log('[CoachingInline] Polling - buscando novos hints...');
            this.loadHints();
        }
    }, 60000); // 1 minuto ao invés de 10 segundos
}
```

### Ganho
- **Antes**: 360 queries/hora por usuário
- **Depois**: 60 queries/hora por usuário
- **Redução**: 83% ⚡

---

## 5️⃣ LIMPEZA DE CACHE

```bash
cd c:\laragon\www\chat
rm -rf storage/cache/queries/*
```

Ou via PHP:
```bash
php -r "require 'config/database.php'; \App\Helpers\Cache::clear();"
```

---

## 6️⃣ TESTE

### 6.1. Abrir Console do Navegador (F12)

### 6.2. Acessar Sistema
```
http://localhost/chat/conversations
```

### 6.3. Verificar Console
Você deve ver mensagens como:
```
[Badges] WebSocket ativo, polling de badges desabilitado
[Polling] WebSocket ativo, pulando polling de mensagens
[CoachingInline] Polling - buscando novos hints... (a cada 60s)
```

### 6.4. Verificar Network (F12 → Network)
- **Antes**: Requisições a cada 3-10 segundos
- **Depois**: Requisições a cada 30-60 segundos
- **Se WebSocket OK**: Quase nenhuma requisição de polling

---

## 7️⃣ MONITORAMENTO

### 7.1. CPU do MySQL
```bash
# Windows: Task Manager
# Ver uso de CPU do mysqld.exe
# Antes: 60-80%
# Depois: 15-25%
```

### 7.2. Slow Log
```bash
# Ver últimas 50 queries
tail -n 50 /var/log/mysql/slow.log

# Deve ter MUITO menos queries
# Antes: 100+ queries/hora
# Depois: 10-15 queries/hora
```

### 7.3. Network Inspector
```
F12 → Network → Filter: XHR

# Contar requisições em 1 minuto
# Antes: 10-20 requisições/minuto
# Depois: 2-4 requisições/minuto
```

---

## 📊 RESULTADO ESPERADO

### Por Usuário
| Métrica | Antes | Depois | Ganho |
|---------|-------|--------|-------|
| Queries/hora | 2.520 | 360 | 86% ⚡ |
| Polling mais frequente | 3s | 30s | 90% ⚡ |
| Requisições/minuto | 42 | 6 | 86% ⚡ |

### 10 Usuários
| Métrica | Antes | Depois | Ganho |
|---------|-------|--------|-------|
| Queries/hora | 25.200 | 3.600 | 86% ⚡ |
| Queries/minuto | 420 | 60 | 86% ⚡ |
| CPU MySQL | 60-80% | 15-25% | 70% 🎯 |

### 50 Usuários (Pico)
| Métrica | Antes | Depois | Ganho |
|---------|-------|--------|-------|
| Queries/hora | 126.000 | 18.000 | 86% ⚡ |
| Queries/segundo | 35/s ⚠️ | 5/s ✅ | 86% ⚡ |

---

## ⚠️ AVISOS IMPORTANTES

### 1. WebSocket DEVE Estar Funcionando
- Se WebSocket estiver quebrado, usuários vão perceber delay
- Teste em homologação primeiro
- Verifique logs de WebSocket: `public/websocket-server.log`

### 2. UX Pode Mudar Levemente
- Badges podem demorar até 60 segundos para atualizar
- Mensagens podem demorar até 30 segundos (modo fallback)
- **MAS**: Se WebSocket estiver ativo, é instantâneo

### 3. Rollback
Se houver problemas:
```bash
# Reverter mudanças
cp views/conversations/index.php.backup views/conversations/index.php
cp public/assets/js/custom/sla-indicator.js.backup public/assets/js/custom/sla-indicator.js
cp public/assets/js/coaching-inline.js.backup public/assets/js/coaching-inline.js

# Limpar cache do navegador (Ctrl+Shift+R)
```

---

## 🎯 PRÓXIMOS PASSOS APÓS ESTE PATCH

1. ✅ Criar índices (já feito)
2. ✅ Adicionar cache em queries pesadas (já feito em 2 queries)
3. ✅ **Reduzir polling** (este documento) ← **VOCÊ ESTÁ AQUI**
4. ⏳ Adicionar cache em TODAS as queries do dashboard
5. ⏳ Criar endpoint leve `/conversations/unread-counts`
6. ⏳ Implementar lazy loading no dashboard
7. ⏳ Migrar cache de arquivo para Redis (opcional)

---

## 📞 VERIFICAÇÃO FINAL

### Checklist Pós-Implementação

- [ ] CPU do MySQL caiu para 15-25%?
- [ ] Slow log tem 90% menos queries?
- [ ] WebSocket está funcionando? (verificar console)
- [ ] Badges ainda atualizam? (pode demorar até 60s)
- [ ] Mensagens chegam? (instantâneo se WebSocket OK)
- [ ] Sistema está mais rápido?
- [ ] Usuários não reclamaram?

### Se Tudo OK ✅
Parabéns! Você reduziu 86% das queries e melhorou drasticamente a performance.

### Se Houver Problemas ⚠️
1. Verificar se WebSocket está rodando: `ps aux | grep websocket`
2. Verificar logs de WebSocket: `tail -f public/websocket-server.log`
3. Reverter mudanças e investigar

---

**Tempo de Implementação**: 30 minutos  
**Ganho Esperado**: 86% de redução em queries  
**Prioridade**: 🔴 CRÍTICA
