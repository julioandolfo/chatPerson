# 📊 DOCUMENTAÇÃO COMPLETA - MELHORIAS DO SISTEMA DE SLA

**Data**: 08 de Janeiro de 2026  
**Versão**: 2.0  
**Status**: ✅ IMPLEMENTADO

---

## 📋 ÍNDICE

1. [Visão Geral](#visão-geral)
2. [Problemas Corrigidos](#problemas-corrigidos)
3. [Novas Funcionalidades](#novas-funcionalidades)
4. [Arquitetura](#arquitetura)
5. [Banco de Dados](#banco-de-dados)
6. [Instalação](#instalação)
7. [Configuração](#configuração)
8. [API e Uso](#api-e-uso)
9. [Testes](#testes)
10. [FAQ](#faq)

---

## 🎯 VISÃO GERAL

Este documento descreve todas as melhorias implementadas no sistema de SLA (Service Level Agreement) do sistema de atendimento multicanal.

### O que foi implementado?

✅ **Working Hours no Backend** - Cálculo de SLA considerando horário de trabalho  
✅ **Horários Personalizados** - Horários diferentes por dia da semana  
✅ **Feriados** - Sistema de feriados fixos e recorrentes  
✅ **SLA por Contexto** - SLA diferente por prioridade/canal/setor/funil  
✅ **SLA Pausado** - Pausar SLA quando conversa está snoozed ou aguardando cliente  
✅ **First Human Response** - Rastreamento separado de resposta humana vs IA  
✅ **Reatribuição Inteligente** - Não reatribui para o mesmo agente  
✅ **Notificações Únicas** - Evita spam de alertas de SLA  
✅ **Ongoing Response Monitoring** - Monitora SLA de respostas durante conversa  
✅ **Priorização Inteligente** - Ordena conversas por urgência de SLA  
✅ **Contador de Reatribuições** - Rastreia quantas vezes conversa foi reatribuída  

---

## 🐛 PROBLEMAS CORRIGIDOS

### 1. ✅ Inconsistência Working Hours Frontend vs Backend

**Problema Original:**
- Frontend calculava SLA considerando horário de trabalho
- Backend calculava SLA 24/7
- Resultava em SLA diferente no frontend e backend

**Solução:**
- Criado `WorkingHoursCalculator` helper
- Todos os cálculos de SLA agora usam working hours
- Consistência total frontend/backend

### 2. ✅ Campo first_response_at Não Atualizado em Tempo Real

**Problema Original:**
- Campo era atualizado no banco mas não refletido no WebSocket
- Indicador visual demorava até 10s para atualizar

**Solução:**
- WebSocket agora envia dados completos da conversa
- Campo `first_human_response_at` adicionado para rastrear separadamente

### 3. ✅ SLA de IA Duplicado

**Problema Original:**
- IA respondia e marcava `first_response_at`
- SLA de resposta humana não era rastreado separadamente

**Solução:**
- Campo `first_human_response_at` para rastrear separadamente
- Estatísticas separadas para IA vs Humanos

### 4. ✅ Reatribuição para o Mesmo Agente

**Problema Original:**
- Sistema podia reatribuir conversa para o mesmo agente que falhou

**Solução:**
- Parâmetro `excludeAgentId` em todos os métodos de atribuição
- Valida disponibilidade antes de reatribuir

### 5. ✅ SLA de Resolução Sem Considerar Pausas

**Problema Original:**
- Cliente demorava dias para responder e SLA era "excedido"

**Solução:**
- Sistema de pausa (`sla_paused_at`, `sla_paused_duration`)
- Desconta tempo pausado do cálculo

### 6. ✅ Monitoramento Limitado a 100 Conversas

**Problema Original:**
- Limite fixo de 100 conversas monitoradas
- Conversas mais críticas podiam ser ignoradas

**Solução:**
- Limite aumentado para 500
- Ordenação por urgência (prioridade + tempo)

### 7. ✅ SLA Ongoing Response Não Monitorado

**Problema Original:**
- Backend só monitorava primeira resposta e resolução
- Respostas atrasadas durante conversa eram ignoradas

**Solução:**
- Monitoramento de `ongoing_response_time` implementado
- Reatribuição automática para respostas atrasadas

### 8. ✅ Spam de Notificações de SLA

**Problema Original:**
- A cada minuto, se SLA estava em 80-100%, criava nova notificação

**Solução:**
- Campo `sla_warning_sent` para marcar quando já alertou
- Reset do flag quando agente responde

### 9. ✅ Working Hours Sem Feriados e Finais de Semana

**Problema Original:**
- Não considerava sábados, domingos e feriados

**Solução:**
- Tabela `working_hours_config` com configuração por dia
- Tabela `holidays` com feriados fixos e recorrentes
- Cálculo inteligente de minutos úteis

### 10. ✅ SLA Global para Todas as Conversas

**Problema Original:**
- Mesmo SLA para urgente, normal e baixa prioridade
- Mesmo SLA para WhatsApp e Email

**Solução:**
- Tabela `sla_rules` com regras personalizadas
- Match por prioridade, canal, setor, funil, estágio
- Prioridade das regras (maior prioridade = mais específica)

---

## 🆕 NOVAS FUNCIONALIDADES

### 1. Helper `WorkingHoursCalculator`

Calcula minutos considerando:
- Horários de trabalho configuráveis por dia da semana
- Feriados fixos e recorrentes
- Finais de semana

```php
use App\Helpers\WorkingHoursCalculator;

$start = new DateTime('2026-01-08 10:00:00'); // Quarta
$end = new DateTime('2026-01-10 15:00:00');   // Sexta

$minutes = WorkingHoursCalculator::calculateMinutes($start, $end);
// Resultado: Minutos apenas durante horário de trabalho
```

### 2. Gestão de Horários de Trabalho

Configurar horários diferentes por dia:

```php
use App\Models\WorkingHoursConfig;

// Segunda a Sexta: 08:00-18:00
// Sábado e Domingo: Não útil

$config = WorkingHoursConfig::getAllDays();
```

### 3. Gestão de Feriados

```php
use App\Models\Holiday;

// Adicionar feriado
Holiday::create([
    'name' => 'Carnaval 2026',
    'date' => '2026-02-16',
    'is_recurring' => false
]);

// Verificar se é feriado
$isHoliday = Holiday::isHoliday('2026-12-25'); // true (Natal)
```

### 4. Regras de SLA Personalizadas

```php
use App\Models\SLARule;

// Criar regra para conversas urgentes
SLARule::create([
    'name' => 'SLA Urgente',
    'priority' => 100,
    'conversation_priority' => 'urgent',
    'first_response_time' => 5,
    'resolution_time' => 30,
    'ongoing_response_time' => 5,
    'enabled' => true
]);

// Obter SLA aplicável para uma conversa
$slaConfig = SLARule::getSLAForConversation($conversation);
// Retorna: ['first_response_time' => 5, 'resolution_time' => 30, ...]
```

### 5. Pausar/Retomar SLA

```php
use App\Services\ConversationSettingsService;

// Pausar SLA (quando snooze, aguardando cliente, etc)
ConversationSettingsService::pauseSLA($conversationId);

// Retomar SLA (quando cliente responde)
ConversationSettingsService::resumeSLA($conversationId);

// Obter tempo decorrido (já descontando pausas)
$minutes = ConversationSettingsService::getElapsedSLAMinutes($conversationId);
```

### 6. Prioridades de Conversa

```php
use App\Models\Conversation;

// Criar conversa com prioridade
Conversation::create([
    'contact_id' => 123,
    'priority' => 'urgent', // urgent, high, normal, low
    'channel' => 'whatsapp',
    // ...
]);
```

### 7. Reatribuição Inteligente

```php
use App\Services\ConversationSettingsService;

// Reatribuir excluindo agente atual
$newAgentId = ConversationSettingsService::autoAssignConversation(
    $conversationId,
    $departmentId,
    $funnelId,
    $stageId,
    $currentAgentId // Excluir este agente
);
```

---

## 🏗️ ARQUITETURA

### Fluxo de Cálculo de SLA

```
┌─────────────────────────────────────────────┐
│ Nova Mensagem do Cliente                    │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│ ConversationService::addMessage()           │
│ - Salva mensagem                            │
│ - Se é primeira resposta do agente:        │
│   • Atualiza first_response_at              │
│   • Se humano: first_human_response_at      │
│   • Reset sla_warning_sent                  │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│ SLAMonitoringJob (Cron a cada 1 minuto)    │
│ - Busca conversas abertas                   │
│ - Ordena por urgência (prioridade + tempo)  │
│ - Processa até 500 conversas               │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│ SLAMonitoringService::processConversationSLA│
│                                             │
│ 1. Verifica se SLA está pausado           │
│ 2. Obtém regra de SLA aplicável           │
│ 3. Calcula tempo decorrido (working hours) │
│ 4. Desconta tempo pausado                  │
│ 5. Verifica SLA de primeira resposta       │
│ 6. Verifica SLA de ongoing response        │
│ 7. Reatribui se necessário (exclude agent) │
│ 8. Envia alerta se 80% (apenas 1x)        │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│ ConversationSettingsService::getSLAForConv  │
│ - Busca SLARule mais específica            │
│ - Match: priority, channel, dept, funnel    │
│ - Retorna SLA personalizado                │
└─────────────────┬───────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────┐
│ WorkingHoursCalculator::calculateMinutes    │
│ - Itera dia por dia                         │
│ - Verifica feriados                         │
│ - Verifica dia útil                         │
│ - Calcula apenas minutos úteis             │
└─────────────────────────────────────────────┘
```

---

## 💾 BANCO DE DADOS

### Novos Campos em `conversations`

```sql
ALTER TABLE conversations ADD COLUMN first_response_at TIMESTAMP NULL;
ALTER TABLE conversations ADD COLUMN first_human_response_at TIMESTAMP NULL;
ALTER TABLE conversations ADD COLUMN sla_paused_at TIMESTAMP NULL;
ALTER TABLE conversations ADD COLUMN sla_paused_duration INT DEFAULT 0;
ALTER TABLE conversations ADD COLUMN sla_warning_sent TINYINT(1) DEFAULT 0;
ALTER TABLE conversations ADD COLUMN reassignment_count INT DEFAULT 0;
ALTER TABLE conversations ADD COLUMN last_reassignment_at TIMESTAMP NULL;
ALTER TABLE conversations ADD COLUMN priority VARCHAR(50) DEFAULT 'normal';
```

### Nova Tabela `working_hours_config`

```sql
CREATE TABLE working_hours_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL,
    is_working_day TINYINT(1) DEFAULT 1,
    start_time TIME DEFAULT '08:00:00',
    end_time TIME DEFAULT '18:00:00',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (day_of_week)
);
```

### Nova Tabela `holidays`

```sql
CREATE TABLE holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    is_recurring TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date)
);
```

### Nova Tabela `sla_rules`

```sql
CREATE TABLE sla_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    priority TINYINT DEFAULT 0,
    conversation_priority VARCHAR(50) NULL,
    channel VARCHAR(50) NULL,
    department_id INT NULL,
    funnel_id INT NULL,
    funnel_stage_id INT NULL,
    first_response_time INT DEFAULT 15,
    resolution_time INT DEFAULT 60,
    ongoing_response_time INT DEFAULT 15,
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (funnel_id) REFERENCES funnels(id) ON DELETE CASCADE,
    FOREIGN KEY (funnel_stage_id) REFERENCES funnel_stages(id) ON DELETE CASCADE
);
```

---

## 📦 INSTALAÇÃO

### Passo 1: Aplicar Migrations

```bash
cd C:\laragon\www\chat
php public/apply-sla-improvements.php
```

Este script irá:
- ✅ Adicionar novos campos em `conversations`
- ✅ Criar tabelas `working_hours_config`, `holidays`, `sla_rules`
- ✅ Popular dados padrão (horários, feriados brasileiros, regras de SLA)
- ✅ Limpar caches
- ✅ Verificar integridade

### Passo 2: Verificar Instalação

O script mostra um relatório:

```
✅ first_response_at
✅ first_human_response_at
✅ sla_paused_at
✅ priority
✅ working_hours_config
✅ holidays
✅ sla_rules
```

---

## ⚙️ CONFIGURAÇÃO

### 1. Configurar Horários de Trabalho

**Via Banco de Dados:**

```sql
-- Alterar horário de Segunda a Sexta
UPDATE working_hours_config 
SET start_time = '09:00:00', end_time = '17:00:00'
WHERE day_of_week BETWEEN 1 AND 5;

-- Tornar Sábado dia útil (meio expediente)
UPDATE working_hours_config 
SET is_working_day = 1, start_time = '09:00:00', end_time = '12:00:00'
WHERE day_of_week = 6;
```

**Via Interface (Futuro):**
Acessar: Configurações → Horários de Trabalho

### 2. Adicionar Feriados

```sql
-- Adicionar feriado específico
INSERT INTO holidays (name, date, is_recurring) 
VALUES ('Black Friday 2026', '2026-11-27', 0);

-- Adicionar feriado recorrente
INSERT INTO holidays (name, date, is_recurring) 
VALUES ('Páscoa', '2026-04-05', 1);
```

### 3. Criar Regras de SLA

```sql
-- SLA para WhatsApp (resposta mais rápida)
INSERT INTO sla_rules (name, priority, channel, first_response_time, resolution_time, enabled)
VALUES ('SLA WhatsApp', 80, 'whatsapp', 5, 30, 1);

-- SLA para setor de vendas (urgente)
INSERT INTO sla_rules (name, priority, department_id, first_response_time, resolution_time, enabled)
VALUES ('SLA Vendas', 90, 1, 10, 45, 1);
```

### 4. Configurar SLA Global

Acessar: **Configurações → Conversas → SLA**

Opções disponíveis:
- ☑️ Habilitar monitoramento de SLA
- ☑️ Monitorar SLA de resolução
- ☑️ Considerar apenas horário de atendimento
- ☑️ Reatribuir automaticamente quando SLA for excedido
- Tempo de primeira resposta (minutos)
- Tempo de resolução (minutos)
- Tempo de resposta em conversa (minutos)
- Horário de início e fim

---

## 🔌 API E USO

### Verificar SLA de uma Conversa

```php
use App\Services\ConversationSettingsService;

// Verificar SLA de primeira resposta
$ok = ConversationSettingsService::checkFirstResponseSLA($conversationId);

// Verificar apenas resposta humana
$ok = ConversationSettingsService::checkFirstResponseSLA($conversationId, $humanOnly = true);

// Verificar SLA de resolução
$ok = ConversationSettingsService::checkResolutionSLA($conversationId);

// Obter tempo decorrido
$minutes = ConversationSettingsService::getElapsedSLAMinutes($conversationId);
```

### Pausar/Retomar SLA

```php
// Pausar SLA (ex: conversa em snooze)
ConversationSettingsService::pauseSLA($conversationId);

// Retomar SLA (ex: cliente respondeu)
ConversationSettingsService::resumeSLA($conversationId);
```

### Obter Estatísticas de SLA

```php
use App\Services\SLAMonitoringService;

// Estatísticas gerais
$stats = SLAMonitoringService::getSLAStats();
/*
[
    'first_response' => ['within_sla' => 45, 'exceeded' => 5],
    'first_response_human' => ['within_sla' => 30, 'exceeded' => 10],
    'first_response_ai' => ['within_sla' => 15, 'exceeded' => 0]
]
*/

// Taxa de cumprimento
$rates = SLAMonitoringService::getSLAComplianceRates('2026-01-01', '2026-01-31');
/*
[
    'general' => ['total' => 100, 'within_sla' => 85, 'rate' => 85.0],
    'human' => ['total' => 60, 'within_sla' => 50, 'rate' => 83.33],
    'ai' => ['total' => 40, 'within_sla' => 40, 'rate' => 100.0]
]
*/
```

### Reatribuição com Exclusão

```php
use App\Services\ConversationSettingsService;

$conversation = Conversation::find($conversationId);
$currentAgentId = $conversation['agent_id'];

// Reatribuir excluindo agente atual
$newAgentId = ConversationSettingsService::autoAssignConversation(
    $conversationId,
    $conversation['department_id'],
    $conversation['funnel_id'],
    $conversation['funnel_stage_id'],
    $currentAgentId // Não atribuir para este agente
);
```

---

## 🧪 TESTES

### Teste 1: Working Hours

```php
// Criar conversa na sexta às 17:00
$conv = Conversation::create([
    'contact_id' => 1,
    'created_at' => '2026-01-09 17:00:00', // Sexta
    'priority' => 'normal'
]);

// Verificar SLA na segunda às 09:00 (horário de trabalho: apenas 1 hora)
$elapsed = ConversationSettingsService::getElapsedSLAMinutes($conv['id']);
// Esperado: ~60 minutos (1 hora útil)
// Sem working hours seria: ~1560 minutos (26 horas corridas)
```

### Teste 2: Feriados

```php
// Criar conversa na véspera de feriado
$conv = Conversation::create([
    'contact_id' => 1,
    'created_at' => '2025-12-24 17:00:00', // Véspera de Natal
]);

// Verificar SLA após feriado
// Natal (25/12) não deve contar como tempo de SLA
```

### Teste 3: SLA Pausado

```php
$conversationId = 123;

// Pausar SLA
ConversationSettingsService::pauseSLA($conversationId);

// Aguardar 1 hora...

// Retomar SLA
ConversationSettingsService::resumeSLA($conversationId);

// Tempo decorrido NÃO deve incluir a 1 hora pausada
$elapsed = ConversationSettingsService::getElapsedSLAMinutes($conversationId);
```

### Teste 4: Regras de SLA Personalizadas

```php
// Criar conversa urgente
$conv = Conversation::create([
    'contact_id' => 1,
    'priority' => 'urgent',
    'channel' => 'whatsapp'
]);

// Obter SLA aplicável
$sla = SLARule::getSLAForConversation($conv);

// Esperado: SLA mais rigoroso (5 min ao invés de 15 min)
assert($sla['first_response_time'] == 5);
```

### Teste 5: Reatribuição Inteligente

```php
$conversationId = 123;
$conversation = Conversation::find($conversationId);
$originalAgent = $conversation['agent_id']; // Ex: 5

// Simular SLA excedido
// ...

// Reatribuir
$newAgent = ConversationSettingsService::autoAssignConversation(
    $conversationId,
    null, null, null,
    $originalAgent // Excluir agente 5
);

// Verificar que não voltou para o mesmo agente
assert($newAgent != $originalAgent);
```

---

## ❓ FAQ

### 1. O working hours é obrigatório?

Não! É opcional. Se desabilitado (`working_hours_enabled = false`), o sistema calcula SLA 24/7.

### 2. Como adiciono um feriado específico da minha cidade?

```sql
INSERT INTO holidays (name, date, is_recurring)
VALUES ('Feriado Municipal', '2026-XX-XX', 0);
```

### 3. Posso ter SLA diferente por canal?

Sim! Crie uma regra de SLA específica:

```sql
INSERT INTO sla_rules (name, priority, channel, first_response_time, enabled)
VALUES ('SLA WhatsApp', 80, 'whatsapp', 5, 1);
```

### 4. O que acontece se houver múltiplas regras aplicáveis?

A regra com **maior prioridade** (priority) é usada. Se houver empate, a mais recente (maior ID).

### 5. Como pausar SLA quando conversa está em snooze?

Implementar na funcionalidade de snooze:

```php
// Ao fazer snooze
ConversationSettingsService::pauseSLA($conversationId);

// Ao despertar do snooze
ConversationSettingsService::resumeSLA($conversationId);
```

### 6. Posso desativar reatribuição automática?

Sim! Nas configurações: **Configurações → Conversas → SLA**

Desmarcar: "Reatribuir automaticamente quando SLA for excedido"

### 7. Como vejo quantas vezes uma conversa foi reatribuída?

```php
$conversation = Conversation::find($conversationId);
echo $conversation['reassignment_count'];
echo $conversation['last_reassignment_at'];
```

### 8. O indicador visual considera as novas regras?

Sim! O frontend (`sla-indicator.js`) usa a mesma lógica do backend através da API `/api/settings/sla`.

### 9. Como diferenciar SLA de IA vs Humano?

Use `checkFirstResponseSLA()` com parâmetro `$humanOnly`:

```php
$humanSLA = ConversationSettingsService::checkFirstResponseSLA($id, true);
$anySLA = ConversationSettingsService::checkFirstResponseSLA($id, false);
```

### 10. Posso ter horários diferentes por dia da semana?

Sim! Configure na tabela `working_hours_config`:

```sql
-- Segunda a Quinta: 09:00-18:00
UPDATE working_hours_config 
SET start_time = '09:00:00', end_time = '18:00:00'
WHERE day_of_week BETWEEN 1 AND 4;

-- Sexta: 09:00-17:00
UPDATE working_hours_config 
SET start_time = '09:00:00', end_time = '17:00:00'
WHERE day_of_week = 5;
```

---

## 📝 CHANGELOG

### v2.0 (2026-01-08)

**🆕 Adicionado:**
- Helper `WorkingHoursCalculator` com suporte a feriados e horários personalizados
- Tabelas `working_hours_config`, `holidays`, `sla_rules`
- Campos em `conversations`: `first_human_response_at`, `sla_paused_at`, `sla_paused_duration`, `sla_warning_sent`, `reassignment_count`, `last_reassignment_at`, `priority`
- SLA por contexto (prioridade, canal, setor, funil)
- Sistema de pausa de SLA
- Monitoramento de ongoing response SLA
- Reatribuição com exclusão de agente
- Notificações únicas (sem spam)
- Ordenação inteligente por urgência

**🔧 Corrigido:**
- Inconsistência working hours frontend vs backend
- Campo first_response_at não atualizado em tempo real
- SLA de IA duplicado
- Reatribuição para o mesmo agente
- SLA de resolução sem considerar pausas
- Limite de 100 conversas
- SLA ongoing response não monitorado
- Spam de notificações
- Working hours sem feriados
- SLA global para todas as conversas

**⚡ Melhorado:**
- Performance do monitoramento (ordena por urgência)
- Limite aumentado para 500 conversas
- Estatísticas separadas IA vs Humanos
- Contador de reatribuições

---

## 👥 SUPORTE

Para dúvidas ou problemas:

1. Verificar logs: `storage/logs/`
2. Verificar cron jobs: `public/run-scheduled-jobs.php`
3. Consultar este documento
4. Verificar integridade do banco: `php public/apply-sla-improvements.php`

---

## 📚 REFERÊNCIAS

- `app/Helpers/WorkingHoursCalculator.php` - Cálculo de horários
- `app/Services/ConversationSettingsService.php` - Lógica de SLA
- `app/Services/SLAMonitoringService.php` - Monitoramento
- `app/Models/SLARule.php` - Regras personalizadas
- `app/Models/WorkingHoursConfig.php` - Configuração de horários
- `app/Models/Holiday.php` - Gestão de feriados

---

**Desenvolvido com ❤️ para melhorar a experiência de atendimento ao cliente**
