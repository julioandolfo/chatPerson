# 🔧 Correção do Sistema de Disponibilidade

## 📋 Resumo do Problema

O sistema de disponibilidade está alternando entre online/offline muito rapidamente, fora dos períodos configurados. Isso causa instabilidade e logs confusos.

## 🔴 Problemas Identificados

### 1. **CONFLITO DUPLO DE VERIFICAÇÃO**

**Problema**: O sistema está verificando timeouts em **DOIS lugares ao mesmo tempo**:

1. **WebSocket Server** (`websocket-server.php` linha 101):
   - Recebe `ping` (heartbeat) a cada 30 segundos
   - Chama `AvailabilityService::processHeartbeat()`
   - Que por sua vez chama `checkAndUpdateStatus()` (linha 138)
   
2. **Script CRON** (`check-availability.php`):
   - Roda periodicamente (ex: a cada 5 minutos)
   - Faz a **mesma verificação** novamente

**Resultado**: Verificações duplicadas, conflitantes e inconsistentes!

---

### 2. **LÓGICA CONTRADITÓRIA no `processHeartbeat()`**

```php
// AvailabilityService.php - linha 130-139
public static function processHeartbeat(int $userId): void
{
    // Atualizar last_seen_at (heartbeat recebido)
    User::update($userId, [
        'last_seen_at' => date('Y-m-d H:i:s')  // ← ATUALIZA AQUI
    ]);
    
    // Verificar e atualizar status se necessário
    self::checkAndUpdateStatus($userId);  // ← VERIFICA LOGO DEPOIS
}
```

**Problema**: O método `checkAndUpdateStatus()` verifica se passou do timeout baseado em `last_seen_at`, mas o `processHeartbeat()` **acabou de atualizar** esse campo **1 linha antes**!

**Resultado**: A verificação **NUNCA** vai detectar timeout porque o heartbeat sempre reseta o timer.

---

### 3. **CONFUSÃO entre HEARTBEAT e ATIVIDADE REAL**

Existem dois conceitos diferentes que estão sendo misturados:

1. **`last_seen_at`**: Último heartbeat (ping) recebido → Indica que o navegador está aberto
2. **`last_activity_at`**: Última atividade real do usuário (mouse, teclado, click) → Indica que o usuário está interagindo

**Problema**: No método `updateActivity()` (linha 114-115):

```php
$data = [
    'last_activity_at' => date('Y-m-d H:i:s'),
    'last_seen_at' => date('Y-m-d H:i:s')  // ← ATUALIZA OS DOIS
];
```

**Resultado**: Quando o usuário interage, atualiza AMBOS os campos, causando confusão na lógica de timeout.

---

### 4. **CRON PODE CONFLITAR COM WEBSOCKET**

Se o CRON roda enquanto o WebSocket está ativo, ambos podem tentar mudar o status ao mesmo tempo, causando:
- Mudanças rápidas de status
- Race conditions
- Logs inconsistentes

---

## 🎯 Estratégia de Correção

### **Opção 1: APENAS WebSocket (Recomendado)**

**Cenário**: WebSocket sempre ativo
- ✅ Mais preciso e em tempo real
- ✅ Resposta imediata a atividades
- ❌ Se WebSocket cair, não há fallback

**Implementação**:
1. Desabilitar verificação de timeout no `processHeartbeat()`
2. Deixar APENAS o CRON fazer as verificações
3. CRON roda a cada 1-2 minutos como backup

---

### **Opção 2: APENAS CRON (Mais simples)**

**Cenário**: WebSocket opcional ou instável
- ✅ Mais confiável (não depende de conexão persistente)
- ✅ Mais simples de debugar
- ❌ Menos preciso (verifica apenas periodicamente)

**Implementação**:
1. Remover verificação de timeout do WebSocket completamente
2. CRON roda periodicamente (ex: a cada 1 minuto)
3. WebSocket só atualiza timestamps, não verifica timeouts

---

### **Opção 3: HÍBRIDO (Atual, mas corrigido)**

**Cenário**: WebSocket + CRON trabalhando juntos
- ✅ Melhor dos dois mundos
- ❌ Mais complexo
- ❌ Precisa de coordenação cuidadosa

**Implementação**:
1. **WebSocket**: Atualiza timestamps (`last_seen_at`, `last_activity_at`)
2. **CRON**: Faz as verificações de timeout e muda status
3. **Separar responsabilidades claramente**

---

## ✅ Correção Recomendada (Opção 2 - Mais Simples)

### **1. Corrigir `AvailabilityService::processHeartbeat()`**

**REMOVER** a chamada para `checkAndUpdateStatus()`:

```php
public static function processHeartbeat(int $userId): void
{
    // Atualizar last_seen_at (heartbeat recebido)
    User::update($userId, [
        'last_seen_at' => date('Y-m-d H:i:s')
    ]);
    
    // ❌ REMOVIDO: self::checkAndUpdateStatus($userId);
    // ✅ Deixar o CRON fazer a verificação
}
```

**Motivo**: O heartbeat só deve **registrar** que o cliente está vivo, não **verificar** timeouts.

---

### **2. Corrigir `AvailabilityService::updateActivity()`**

**NÃO atualizar** `last_seen_at` quando houver atividade:

```php
public static function updateActivity(int $userId, ?string $activityType = null): void
{
    $settings = self::getSettings();
    
    if (!$settings['activity_tracking_enabled']) {
        return;
    }

    $data = [
        'last_activity_at' => date('Y-m-d H:i:s')
        // ❌ REMOVIDO: 'last_seen_at' => date('Y-m-d H:i:s')
        // ✅ last_seen_at só é atualizado pelo heartbeat
    ];

    // Se estava 'away' e teve atividade, voltar para 'online'
    $user = User::find($userId);
    if ($user && ($user['availability_status'] ?? 'offline') === 'away') {
        self::updateAvailabilityStatus($userId, 'online', 'activity_detected');
    } else {
        User::update($userId, $data);
    }
}
```

**Motivo**: Separar claramente **heartbeat** (navegador aberto) de **atividade** (usuário interagindo).

---

### **3. Ajustar intervalo do CRON**

**Recomendação**: Rodar a cada **1-2 minutos** (não 5 minutos)

**Motivo**: Se o timeout de offline é 5 minutos e o cron roda a cada 5 minutos, pode demorar até 10 minutos para detectar!

**Windows Task Scheduler**:
```
Trigger: Repetir a cada 1 minuto
Action: php C:\laragon\www\chat\public\check-availability.php
```

**Linux Cron**:
```cron
* * * * * php /var/www/html/public/check-availability.php >> /var/log/availability-cron.log 2>&1
```

---

### **4. Adicionar logs detalhados no CRON**

O script `check-availability.php` já tem logs detalhados, mas podemos melhorar:

```php
// Adicionar timestamp em cada linha
echo "[" . date('H:i:s') . "] Verificando: {$agentName} (Status: {$currentStatus})\n";
```

---

## 🧪 Como Testar

### **1. Rodar o script de debug**

```bash
# Listar todos os agentes
php public/debug-availability.php

# Debug de um agente específico
php public/debug-availability.php 1

# Ou via HTTP
http://localhost/debug-availability.php?user_id=1
```

**O que observar**:
- ✅ Diferença entre `last_seen_at` e `last_activity_at`
- ✅ Tempo desde último heartbeat/atividade
- ❌ Mudanças rápidas (< 2 minutos)
- ❌ Status inconsistente com timeouts

---

### **2. Monitorar logs do CRON**

```bash
# Rodar manualmente e observar output
php public/check-availability.php

# Verificar se está detectando timeouts corretamente
```

**O que deve acontecer**:
- Se agente está online e não envia heartbeat por 5+ minutos → marcar como OFFLINE
- Se agente está online e não tem atividade por 15+ minutos → marcar como AWAY
- Se agente estava away e teve atividade → voltar para ONLINE

---

### **3. Verificar histórico no banco**

```sql
-- Ver histórico recente de um agente
SELECT status, started_at, ended_at, duration_seconds, metadata
FROM user_availability_history
WHERE user_id = 1
ORDER BY started_at DESC
LIMIT 20;

-- Verificar mudanças muito rápidas (< 2 min)
SELECT COUNT(*) as quick_changes
FROM user_availability_history
WHERE user_id = 1
AND duration_seconds > 0
AND duration_seconds < 120
AND started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

---

## 📊 Comportamento Esperado

### **Cenário 1: Usuário ativo**
1. Login → Status: `online`
2. A cada 30s → Heartbeat (atualiza `last_seen_at`)
3. Clique/digitação → Atividade (atualiza `last_activity_at`)
4. Status permanece: `online`

### **Cenário 2: Usuário inativo (navegador aberto, mas sem interagir)**
1. Status: `online`
2. Heartbeat continua (a cada 30s) → `last_seen_at` atualiza
3. Sem atividade por 15 minutos → `last_activity_at` fica antigo
4. **CRON detecta** → Status muda para: `away`

### **Cenário 3: Usuário fecha navegador**
1. Status: `online` ou `away`
2. Heartbeat **para** de chegar
3. Após 5 minutos sem heartbeat
4. **CRON detecta** → Status muda para: `offline`

### **Cenário 4: Usuário volta a interagir (estava away)**
1. Status: `away`
2. Clique/digitação → `updateActivity()` detecta
3. **Imediatamente** → Status volta para: `online`

---

## 🚀 Implementação das Correções

### **Passo 1**: Aplicar correções no código
- Editar `app/Services/AvailabilityService.php`
- Aplicar as mudanças nos métodos `processHeartbeat()` e `updateActivity()`

### **Passo 2**: Testar localmente
- Rodar debug: `php public/debug-availability.php`
- Verificar se detecta problemas atuais

### **Passo 3**: Ajustar intervalo do CRON
- Windows Task Scheduler: 1 minuto
- Ou rodar manualmente para testes

### **Passo 4**: Monitorar por 30-60 minutos
- Verificar logs do CRON
- Verificar histórico no banco
- Confirmar que não há mais mudanças rápidas

---

## 📝 Checklist de Verificação

- [ ] Aplicadas correções em `AvailabilityService.php`
- [ ] Script de debug criado e testado
- [ ] CRON ajustado para rodar a cada 1-2 minutos
- [ ] Testado cenário: usuário ativo
- [ ] Testado cenário: usuário inativo (away)
- [ ] Testado cenário: usuário fecha navegador (offline)
- [ ] Verificado histórico no banco (sem mudanças rápidas)
- [ ] Logs do CRON estão claros e informativos

---

## 🎯 Resultado Esperado

Após as correções:
- ✅ Status muda apenas quando realmente necessário
- ✅ Sem alternâncias rápidas online/offline
- ✅ Histórico mostra mudanças consistentes (> 2 minutos de duração)
- ✅ Logs claros e informativos
- ✅ Sistema confiável e previsível

---

## 🆘 Se Problemas Persistirem

1. **Verificar timezone**: PHP e MySQL devem usar o mesmo timezone
2. **Verificar WebSocket**: Confirmar se está rodando e recebendo heartbeats
3. **Verificar múltiplas abas**: Usuário com várias abas abertas pode causar comportamento estranho
4. **Verificar logs do navegador**: Console do browser pode mostrar erros no envio de heartbeats

---

**Criado em**: 2025-01-05  
**Autor**: AI Assistant  
**Versão**: 1.0
