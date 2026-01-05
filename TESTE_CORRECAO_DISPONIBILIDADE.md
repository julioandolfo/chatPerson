# 🧪 Guia de Teste - Correção do Sistema de Disponibilidade

## 📝 O que foi corrigido?

### ✅ Correções Aplicadas

1. **`AvailabilityService::processHeartbeat()`**
   - ❌ **Antes**: Atualizava `last_seen_at` E verificava timeouts (conflito!)
   - ✅ **Agora**: Apenas atualiza `last_seen_at` (registra heartbeat)
   - 🎯 **Motivo**: Deixar apenas o CRON fazer as verificações

2. **`AvailabilityService::updateActivity()`**
   - ❌ **Antes**: Atualizava `last_activity_at` E `last_seen_at` (confusão!)
   - ✅ **Agora**: Apenas atualiza `last_activity_at` (registra atividade real)
   - 🎯 **Motivo**: Separar heartbeat (navegador vivo) de atividade (usuário interagindo)

3. **Separação de Responsabilidades**
   - 🔵 **WebSocket/Frontend**: Registra heartbeats e atividades
   - 🔴 **CRON**: Verifica timeouts e muda status
   - 🎯 **Motivo**: Evitar conflitos e mudanças rápidas

---

## 🚀 Como Testar

### **Passo 1: Rodar o Debug**

```bash
# Via CLI (recomendado)
php public/debug-availability.php

# Vai listar todos os agentes ativos
# Escolha um ID para analisar

# Analisar agente específico
php public/debug-availability.php 1

# Ou via navegador
http://localhost/debug-availability.php?user_id=1
```

**O que observar**:
- ✅ Status atual do agente
- ✅ Tempo desde último heartbeat (`last_seen_at`)
- ✅ Tempo desde última atividade (`last_activity_at`)
- ✅ Histórico recente (últimas 20 mudanças)
- ❌ Mudanças muito rápidas (< 2 minutos) → **PROBLEMA!**
- ❌ Status inconsistente → **PROBLEMA!**

---

### **Passo 2: Verificar Estado Atual no Banco**

```sql
-- Ver estado atual de todos os agentes
SELECT 
    id,
    name,
    availability_status,
    last_seen_at,
    last_activity_at,
    TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) as minutes_since_heartbeat,
    TIMESTAMPDIFF(MINUTE, last_activity_at, NOW()) as minutes_since_activity
FROM users
WHERE role IN ('agent', 'admin', 'supervisor')
AND status = 'active'
ORDER BY availability_status DESC, name ASC;
```

**O que observar**:
- Agentes `online` devem ter `minutes_since_heartbeat` < 5 minutos
- Agentes `away` devem ter `minutes_since_activity` >= 15 minutos
- Agentes `offline` devem ter `minutes_since_heartbeat` >= 5 minutos

---

### **Passo 3: Rodar o CRON Manualmente**

```bash
# Rodar o script de verificação
php public/check-availability.php
```

**Exemplo de output esperado**:

```
=== Verificação de Disponibilidade dos Agentes ===
Data/Hora: 2025-01-05 14:30:00

Configurações:
- Timeout para Away: 15 minutos
- Timeout para Offline: 5 minutos

Agentes a verificar: 3

Verificando: João Silva (Status: online)
  - Último visto: 2025-01-05 14:29:45 (0.25 minutos atrás)
  - Última atividade: 2025-01-05 14:25:00 (5 minutos atrás)
  ✓ Status OK

Verificando: Maria Santos (Status: online)
  - Último visto: 2025-01-05 14:20:00 (10 minutos atrás)
  ⚠️  AÇÃO: Marcar como OFFLINE (sem heartbeat há 10 minutos)

Verificando: Pedro Oliveira (Status: online)
  - Último visto: 2025-01-05 14:29:50 (0.17 minutos atrás)
  - Última atividade: 2025-01-05 14:10:00 (20 minutos atrás)
  ⚠️  AÇÃO: Marcar como AWAY (sem atividade há 20 minutos)

=== Resumo ===
Total verificado: 3
Total atualizado: 2
Concluído em: 2025-01-05 14:30:05
```

---

### **Passo 4: Testar Cenários Reais**

#### **Cenário A: Usuário Ativo**

1. Faça login como agente
2. Mantenha-se ativo (clicando, digitando, etc)
3. Aguarde 5 minutos
4. Rode o debug: `php public/debug-availability.php [seu_user_id]`

**Resultado esperado**:
- ✅ Status deve permanecer: `online`
- ✅ `last_seen_at` recente (< 1 min)
- ✅ `last_activity_at` recente (< 5 min)

---

#### **Cenário B: Usuário Inativo (Navegador Aberto)**

1. Faça login como agente
2. **NÃO** interaja (deixe navegador aberto, mas não clique/digite)
3. Aguarde 15+ minutos
4. Rode o CRON: `php public/check-availability.php`

**Resultado esperado**:
- ✅ Status deve mudar para: `away`
- ✅ `last_seen_at` recente (heartbeat continua)
- ✅ `last_activity_at` antigo (> 15 min)
- ✅ Histórico mostra: `online` → `away` (duração: ~15+ min)

---

#### **Cenário C: Usuário Fecha Navegador**

1. Faça login como agente
2. Feche o navegador/aba
3. Aguarde 5+ minutos
4. Rode o CRON: `php public/check-availability.php`

**Resultado esperado**:
- ✅ Status deve mudar para: `offline`
- ✅ `last_seen_at` antigo (> 5 min)
- ✅ Histórico mostra: `online` → `offline` (duração: ~5+ min)

---

#### **Cenário D: Usuário Volta a Interagir (Estava Away)**

1. Deixe o status ficar `away` (aguarde 15 min sem interagir)
2. Clique ou digite algo no sistema
3. **Imediatamente** rode o debug

**Resultado esperado**:
- ✅ Status deve voltar para: `online` IMEDIATAMENTE
- ✅ `last_activity_at` atualizado
- ✅ Histórico mostra: `away` → `online` (reason: activity_detected)

---

### **Passo 5: Verificar Histórico no Banco**

```sql
-- Ver histórico recente (últimas 24 horas)
SELECT 
    u.name,
    h.status,
    h.started_at,
    h.ended_at,
    h.duration_seconds,
    TIME_FORMAT(SEC_TO_TIME(h.duration_seconds), '%H:%i:%s') as duration_formatted,
    JSON_EXTRACT(h.metadata, '$.reason') as reason
FROM user_availability_history h
JOIN users u ON u.id = h.user_id
WHERE h.started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY h.started_at DESC
LIMIT 50;

-- Verificar mudanças muito rápidas (< 2 minutos)
SELECT 
    u.name,
    h.status,
    h.started_at,
    h.ended_at,
    h.duration_seconds,
    JSON_EXTRACT(h.metadata, '$.reason') as reason
FROM user_availability_history h
JOIN users u ON u.id = h.user_id
WHERE h.duration_seconds > 0
AND h.duration_seconds < 120  -- menos de 2 minutos
AND h.started_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY h.started_at DESC;
```

**O que observar**:
- ❌ **Se encontrar muitas mudanças < 2 min**: Ainda há problema!
- ✅ **Se mudanças são >= 5 min (offline) ou >= 15 min (away)**: Sistema OK!

---

### **Passo 6: Configurar CRON para Rodar Periodicamente**

#### **Windows (Task Scheduler)**

1. Abrir "Agendador de Tarefas"
2. Criar Tarefa Básica:
   - **Nome**: Verificar Disponibilidade Agentes
   - **Gatilho**: Repetir a cada **1 minuto**
   - **Ação**: Iniciar programa
     - **Programa**: `php`
     - **Argumentos**: `C:\laragon\www\chat\public\check-availability.php`
     - **Iniciar em**: `C:\laragon\www\chat`

#### **Linux (Crontab)**

```bash
# Editar crontab
crontab -e

# Adicionar linha (rodar a cada 1 minuto)
* * * * * php /var/www/html/public/check-availability.php >> /var/log/availability-cron.log 2>&1
```

---

## ✅ Critérios de Sucesso

Após as correções, o sistema deve:

1. ✅ **Mudanças de status respeitam os timeouts configurados**:
   - Away: após 15 minutos de inatividade
   - Offline: após 5 minutos sem heartbeat

2. ✅ **Sem mudanças rápidas** (< 2 minutos entre mudanças)

3. ✅ **Histórico consistente**:
   - Durações >= 5 minutos (offline)
   - Durações >= 15 minutos (away)
   - Mudanças lógicas (online → away → offline)

4. ✅ **Logs claros e informativos**:
   - CRON mostra o que está fazendo
   - Razões das mudanças são registradas

5. ✅ **Responsividade em atividade**:
   - Se usuário estava `away` e interage → volta para `online` IMEDIATAMENTE

---

## 🔍 Problemas Conhecidos e Soluções

### **Problema 1: Timezone diferente entre PHP e MySQL**

**Sintoma**: Cálculos de tempo estão errados

**Solução**:
```php
// Adicionar no início dos scripts
date_default_timezone_set('America/Sao_Paulo');
```

```sql
-- No MySQL
SET GLOBAL time_zone = '-03:00';
```

---

### **Problema 2: WebSocket não está rodando**

**Sintoma**: `last_seen_at` não atualiza

**Verificar**:
```bash
# Verificar se processo está rodando (Linux)
ps aux | grep websocket-server

# Verificar se porta 8080 está aberta
netstat -an | grep 8080

# Iniciar WebSocket
php public/websocket-server.php
```

---

### **Problema 3: Múltiplas abas abertas**

**Sintoma**: Comportamento errático, heartbeats duplicados

**Solução**: Detectar múltiplas abas no frontend (futuro)

---

### **Problema 4: CRON não está rodando**

**Sintoma**: Status nunca muda para offline/away

**Verificar**:
```bash
# Rodar manualmente
php public/check-availability.php

# Ver se há erros
```

---

## 📊 Monitoramento Contínuo

### **Dashboard SQL**

```sql
-- Resumo de status atual
SELECT 
    availability_status,
    COUNT(*) as total,
    GROUP_CONCAT(name SEPARATOR ', ') as agents
FROM users
WHERE role IN ('agent', 'admin', 'supervisor')
AND status = 'active'
GROUP BY availability_status;

-- Mudanças nas últimas 24h
SELECT 
    DATE_FORMAT(started_at, '%Y-%m-%d %H:00') as hour,
    status,
    COUNT(*) as total_changes
FROM user_availability_history
WHERE started_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY DATE_FORMAT(started_at, '%Y-%m-%d %H:00'), status
ORDER BY hour DESC, status;
```

---

## 🆘 Se Ainda Houver Problemas

1. **Rodar debug completo**: `php public/debug-availability.php [user_id]`
2. **Ver histórico recente**: Consultar SQLs acima
3. **Verificar logs do WebSocket**: Console onde está rodando
4. **Verificar logs do navegador**: F12 → Console
5. **Criar issue detalhada** com outputs do debug

---

**Boa sorte nos testes! 🚀**
