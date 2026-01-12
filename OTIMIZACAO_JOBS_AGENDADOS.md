# ⚡ OTIMIZAÇÃO DE JOBS AGENDADOS

**Data**: 2026-01-12  
**Objetivo**: Reduzir carga do sistema causada por jobs pesados

---

## 🔴 PROBLEMAS IDENTIFICADOS

### 1. **Jobs Pesados Executando SEMPRE**

Antes da otimização, **4 jobs pesados** executavam a cada execução do cron (1-2 minutos):

| Job | Problema | Impacto |
|-----|----------|---------|
| **SLAMonitoringJob** | Buscava 500 conversas com JOINs complexos | ⚠️ MUITO ALTO |
| **AIFallbackMonitoringJob** | 50 conversas + chamadas OpenAI | ⚠️ ALTO |
| **AutomationDelayJob** | 100 delays + execuções de automações | ⚠️ MÉDIO |
| **process-ai-buffers.php** | Processamento de buffers + OpenAI | ⚠️ MÉDIO |

### 2. **Queries SQL Complexas**

```sql
-- SLAMonitoringService (ANTES)
SELECT c.*, ct.name, TIMESTAMPDIFF(...) 
FROM conversations c
INNER JOIN contacts ct ON c.contact_id = ct.id
WHERE c.status IN ('open', 'pending')
AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
LIMIT 500  -- ❌ MUITO ALTO
```

### 3. **Lógica de Agendamento Inadequada**

```php
// ❌ ANTES: Só executava no minuto 0
$currentMinute = (int)date('i');
if ($currentMinute % 60 === 0) {
    FollowupJob::run(); // Só roda 1x/hora
}
```

### 4. **Sem Controle de Concorrência**

- Múltiplas execuções simultâneas podiam ocorrer
- Não havia proteção contra execuções paralelas

---

## ✅ SOLUÇÕES APLICADAS

### **1. Sistema de Lock (Concorrência)**

```php
// ✅ Previne múltiplas execuções simultâneas
$lockFile = __DIR__ . '/../storage/cache/jobs.lock';
$lockHandle = fopen($lockFile, 'c+');
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0); // Já em execução
}
```

**Benefício**: Evita sobrecarga quando cron executa enquanto anterior ainda está rodando.

---

### **2. Sistema de Estado (Frequência)**

```php
// ✅ Controle preciso de quando cada job foi executado
$stateFile = __DIR__ . '/../storage/cache/jobs_state.json';
$state = json_decode(file_get_contents($stateFile), true);

// Exemplo: SLA roda a cada 3 minutos
$lastSLA = $state['last_sla'] ?? 0;
if (($now - $lastSLA) >= 180) {
    SLAMonitoringJob::run();
    $state['last_sla'] = $now;
}
```

**Benefício**: Frequência precisa, independente de quando o cron roda.

---

### **3. Priorização de Jobs**

| Prioridade | Jobs | Frequência |
|------------|------|------------|
| 🔴 **CRÍTICO** | AI Buffers, Automation Delays | **A cada execução** (1-2 min) |
| 🟠 **IMPORTANTE** | SLA Monitoring | **A cada 3 minutos** |
| 🟡 **MODERADO** | AI Fallback, Followups | **A cada 10-15 minutos** |
| 🟢 **LEVE** | AI Cost, WooCommerce Sync | **A cada hora** |

---

### **4. Otimização de Queries SQL**

#### **SLAMonitoringService**

```sql
-- ✅ DEPOIS: Otimizado
SELECT c.id, c.status, c.priority, c.agent_id, ... 
FROM conversations c
WHERE c.status IN ('open', 'pending')
AND c.sla_paused_at IS NULL  -- ✅ Filtro direto
AND c.created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)  -- ✅ 48h ao invés de 7 dias
LIMIT 100  -- ✅ Reduzido de 500 para 100
```

**Melhorias**:
- ✅ Sem JOIN desnecessário com `contacts`
- ✅ Filtro de SLA pausado direto na query
- ✅ Janela de tempo reduzida (7 dias → 2 dias)
- ✅ Limite reduzido (500 → 100)

#### **AIFallbackMonitoringService**

```sql
-- ✅ Query simplificada
SELECT c.id, c.conversation_id, c.ai_agent_id, c.status
FROM ai_conversations c
INNER JOIN conversations conv ON conv.id = c.conversation_id
WHERE c.status = 'active'
AND conv.status IN ('open', 'pending')
AND conv.updated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
LIMIT 20  -- ✅ Reduzido de 50 para 20
```

**Melhorias**:
- ✅ Sem subconsultas complexas
- ✅ Usa `updated_at` ao invés de buscar última mensagem
- ✅ Limite reduzido (50 → 20)

---

### **5. Monitoramento de Performance**

```php
// ✅ Tempo de execução de cada job
$startTime = microtime(true);
SLAMonitoringJob::run();
$duration = round(microtime(true) - $startTime, 2);
echo "SLAMonitoringJob concluído em {$duration}s\n";
```

**Benefício**: Identifica gargalos e jobs lentos.

---

## 📊 COMPARAÇÃO ANTES/DEPOIS

### **Execução a cada 2 minutos (1 hora)**

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| **Execuções SLA** | 30x | 20x | ⬇️ 33% |
| **Conversas SLA** | 15.000 | 2.000 | ⬇️ 87% |
| **Execuções AI Fallback** | 30x | 6x | ⬇️ 80% |
| **Conversas AI Fallback** | 1.500 | 120 | ⬇️ 92% |
| **Delays Processados** | 3.000 | 1.500 | ⬇️ 50% |

### **Estimativa de Redução de Carga**

- **Queries SQL**: ⬇️ ~85% (menos conversas + queries simplificadas)
- **Processamento**: ⬇️ ~70% (menos execuções + limites menores)
- **Chamadas OpenAI**: ⬇️ ~80% (AI Fallback menos frequente)

---

## 🔧 CONFIGURAÇÃO DO CRON

### **Opção 1: Cron a cada 2 minutos** (Recomendado)

```bash
*/2 * * * * cd /laragon/www/chat && php public/run-scheduled-jobs.php >> storage/logs/cron.log 2>&1
```

✅ **Vantagens**:
- Buffers de IA e delays processados rapidamente (2 min)
- Carga reduzida (sistema de estado controla frequência)
- Logs completos

### **Opção 2: Cron a cada 5 minutos** (Mais leve)

```bash
*/5 * * * * cd /laragon/www/chat && php public/run-scheduled-jobs.php >> storage/logs/cron.log 2>&1
```

✅ **Vantagens**:
- Ainda mais leve para o sistema
- Buffers de IA podem demorar até 5 min para processar

⚠️ **Desvantagem**:
- Delays de automações podem atrasar até 5 minutos

---

## 🚀 FORÇAR EXECUÇÃO MANUAL

### **Via Linha de Comando**

```bash
# Executar todos os jobs
php public/run-scheduled-jobs.php

# Forçar job específico
php public/run-scheduled-jobs.php?force_sla=1
php public/run-scheduled-jobs.php?force_fallback=1
php public/run-scheduled-jobs.php?force_followup=1
php public/run-scheduled-jobs.php?force_cost_check=1
php public/run-scheduled-jobs.php?force_wc_sync=1
```

### **Via URL** (se necessário)

```
https://seusite.com.br/run-scheduled-jobs.php?force_sla=1
```

---

## 📈 MONITORAMENTO

### **1. Verificar Logs**

```bash
tail -f storage/logs/cron.log
```

**Exemplo de saída**:

```
[2026-01-12 10:00:00] Processando buffers de IA...
[2026-01-12 10:00:01] Buffers de IA processados em 0.82s
[2026-01-12 10:00:01] Executando AutomationDelayJob...
[2026-01-12 10:00:02] AutomationDelayJob concluído em 0.45s
[2026-01-12 10:00:02] Executando SLAMonitoringJob...
[2026-01-12 10:00:05] SLAMonitoringJob concluído em 2.73s
```

### **2. Verificar Estado**

```bash
cat storage/cache/jobs_state.json
```

**Exemplo**:

```json
{
    "last_sla": 1736689200,
    "last_fallback": 1736689200,
    "last_followup": 1736689200,
    "last_cost": 1736686000,
    "last_wc": 1736686000
}
```

### **3. Identificar Jobs Lentos**

Se um job demorar mais que o esperado:

| Job | Tempo Esperado | Ação se Exceder |
|-----|----------------|-----------------|
| **AI Buffers** | < 2s | Verificar chamadas OpenAI |
| **Automation Delays** | < 5s | Verificar automações pesadas |
| **SLA Monitoring** | < 10s | Reduzir limite ou otimizar queries |
| **AI Fallback** | < 15s | Desabilitar detecção via IA |

---

## 🛠️ AJUSTES FINOS

### **Se sistema ainda estiver pesado**

1. **Reduzir limites ainda mais**:

```php
// app/Services/SLAMonitoringService.php
LIMIT 50  // ao invés de 100

// app/Services/AIFallbackMonitoringService.php
LIMIT 10  // ao invés de 20

// app/Services/AutomationDelayService.php
public static function processPendingDelays(int $limit = 25)
```

2. **Aumentar frequências**:

```php
// SLA a cada 5 minutos ao invés de 3
if (($now - $lastSLA) >= 300) {

// AI Fallback a cada 15 minutos ao invés de 10
if (($now - $lastFallback) >= 900) {
```

3. **Desabilitar jobs não essenciais**:

```php
// Desabilitar AI Fallback temporariamente
// Comentar bloco no run-scheduled-jobs.php
```

---

## 📋 ÍNDICES RECOMENDADOS

Para melhorar ainda mais a performance, adicione estes índices:

```sql
-- Conversas: SLA Monitoring
ALTER TABLE conversations 
ADD INDEX idx_sla_monitoring (status, sla_paused_at, created_at, priority);

-- AI Conversations: Fallback
ALTER TABLE ai_conversations 
ADD INDEX idx_fallback (status, conversation_id);

-- Automation Delays
ALTER TABLE automation_delays 
ADD INDEX idx_pending (status, execute_at);
```

---

## ✅ CHECKLIST DE VERIFICAÇÃO

- [ ] Cron configurado (cada 2-5 minutos)
- [ ] Diretório `storage/cache/` existe e tem permissão de escrita
- [ ] Índices de banco de dados aplicados
- [ ] Logs funcionando (`storage/logs/cron.log`)
- [ ] Monitorar execuções por 1-2 horas
- [ ] Verificar tempo de execução de cada job
- [ ] Ajustar frequências conforme necessário

---

## 📞 SUPORTE

Se após as otimizações o sistema ainda estiver pesado:

1. Verificar logs de erro: `storage/logs/error.log`
2. Verificar tempo de resposta da API OpenAI
3. Verificar carga do banco de dados (queries lentas)
4. Considerar processar jobs em servidor separado (queue worker)

---

**Última atualização**: 2026-01-12
