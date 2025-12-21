# Resumo da Implementação dos Novos Gatilhos

## ✅ O Que Foi Implementado

### 1. **Frontend - Interface de Criação/Edição** ✅

**Arquivo:** `views/automations/index.php`
- ✅ Adicionados novos tipos de gatilho no `<select name="trigger_type">`:
  - `no_customer_response` - "Tempo sem Resposta do Cliente"
  - `no_agent_response` - "Tempo sem Resposta do Agente"
- ✅ Atualizado array `triggerLabels` com labels corretos
- ✅ Incluídos no array `triggersWithFunnel` (podem ser vinculados a funis/estágios)
- ✅ JavaScript para mostrar/ocultar container de funil/estágio conforme gatilho

**Arquivo:** `views/automations/show.php`
- ✅ Formulário completo para `no_customer_response`:
  - Campo de tempo (quantidade + unidade: minutos/horas/dias)
  - Checkbox "Apenas conversas abertas"
  - Alert explicativo de como funciona
- ✅ Formulário completo para `no_agent_response`:
  - Campo de tempo (quantidade + unidade: minutos/horas/dias)
  - Checkbox "Apenas conversas atribuídas"
  - Checkbox "Apenas conversas abertas"
  - Alert explicativo de como funciona

### 2. **Backend - Validação** ✅

**Arquivo:** `app/Services/AutomationService.php`
- ✅ Validação atualizada no método `create()`:
  ```php
  'trigger_type' => 'required|string|in:...,no_customer_response,no_agent_response,...'
  ```
- ✅ Aceita os novos tipos de gatilho
- ✅ Processa `trigger_config` como JSON

### 3. **Documentação** ✅

**Arquivo:** `NOVOS_GATILHOS_AUTOMACAO.md`
- ✅ Documentação completa dos novos gatilhos
- ✅ Casos de uso detalhados
- ✅ Estrutura de dados explicada
- ✅ Próximos passos definidos

## ⏳ O Que Ainda Precisa Ser Implementado

### 1. **Backend - Processamento dos Gatilhos** ⏳

Os gatilhos estão **criados e configurados** no banco de dados, mas **não são processados automaticamente ainda**.

**Necessário:**

#### A. Criar `AutomationSchedulerService.php`

**Arquivo:** `app/Services/AutomationSchedulerService.php`

Implementar métodos:

```php
<?php
namespace App\Services;

use App\Models\Automation;
use App\Models\Conversation;
use App\Helpers\Database;
use App\Helpers\Logger;

class AutomationSchedulerService
{
    /**
     * Processar gatilhos de tempo sem resposta do cliente
     */
    public static function processNoCustomerResponseTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'no_customer_response' ===");
        
        // Buscar automações ativas
        $automations = Automation::where([
            'trigger_type' => 'no_customer_response',
            'status' => 'active',
            'is_active' => true
        ]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $timeValue = $config['wait_time_value'] ?? 30;
                $timeUnit = $config['wait_time_unit'] ?? 'minutes';
                $onlyOpen = $config['only_open_conversations'] ?? true;
                
                // Converter tempo para minutos
                $minutes = self::convertToMinutes($timeValue, $timeUnit);
                
                Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Aguardando {$timeValue} {$timeUnit} ({$minutes} min)");
                
                // Buscar conversas que atendem os critérios
                $sql = "
                    SELECT c.* 
                    FROM conversations c
                    WHERE c.id IN (
                        SELECT m.conversation_id
                        FROM messages m
                        WHERE m.id = (
                            SELECT MAX(id) 
                            FROM messages 
                            WHERE conversation_id = c.id
                        )
                        AND m.sender_type IN ('agent', 'ai_agent')
                        AND TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) >= ?
                    )
                ";
                
                $params = [$minutes];
                
                // Filtrar por status
                if ($onlyOpen) {
                    $sql .= " AND c.status IN ('open', 'pending')";
                }
                
                // Filtrar por funil/estágio
                if (!empty($automation['funnel_id'])) {
                    $sql .= " AND c.funnel_id = ?";
                    $params[] = $automation['funnel_id'];
                }
                if (!empty($automation['stage_id'])) {
                    $sql .= " AND c.funnel_stage_id = ?";
                    $params[] = $automation['stage_id'];
                }
                
                $sql .= " ORDER BY c.id ASC";
                
                $conversations = Database::query($sql, $params);
                
                Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                
                // Executar automação para cada conversa
                foreach ($conversations as $conversation) {
                    Logger::automation("  → Executando para conversa #{$conversation['id']}");
                    AutomationService::executeForConversation($automation['id'], $conversation['id']);
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'no_customer_response' ===\n");
    }
    
    /**
     * Processar gatilhos de tempo sem resposta do agente
     */
    public static function processNoAgentResponseTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'no_agent_response' ===");
        
        // Buscar automações ativas
        $automations = Automation::where([
            'trigger_type' => 'no_agent_response',
            'status' => 'active',
            'is_active' => true
        ]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $timeValue = $config['wait_time_value'] ?? 15;
                $timeUnit = $config['wait_time_unit'] ?? 'minutes';
                $onlyOpen = $config['only_open_conversations'] ?? true;
                $onlyAssigned = $config['only_assigned'] ?? true;
                
                // Converter tempo para minutos
                $minutes = self::convertToMinutes($timeValue, $timeUnit);
                
                Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Aguardando {$timeValue} {$timeUnit} ({$minutes} min)");
                
                // Buscar conversas que atendem os critérios
                $sql = "
                    SELECT c.* 
                    FROM conversations c
                    WHERE c.id IN (
                        SELECT m.conversation_id
                        FROM messages m
                        WHERE m.id = (
                            SELECT MAX(id) 
                            FROM messages 
                            WHERE conversation_id = c.id
                        )
                        AND m.sender_type = 'contact'
                        AND TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) >= ?
                    )
                ";
                
                $params = [$minutes];
                
                // Filtrar por conversas atribuídas
                if ($onlyAssigned) {
                    $sql .= " AND c.agent_id IS NOT NULL";
                }
                
                // Filtrar por status
                if ($onlyOpen) {
                    $sql .= " AND c.status IN ('open', 'pending')";
                }
                
                // Filtrar por funil/estágio
                if (!empty($automation['funnel_id'])) {
                    $sql .= " AND c.funnel_id = ?";
                    $params[] = $automation['funnel_id'];
                }
                if (!empty($automation['stage_id'])) {
                    $sql .= " AND c.funnel_stage_id = ?";
                    $params[] = $automation['stage_id'];
                }
                
                $sql .= " ORDER BY c.id ASC";
                
                $conversations = Database::query($sql, $params);
                
                Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                
                // Executar automação para cada conversa
                foreach ($conversations as $conversation) {
                    Logger::automation("  → Executando para conversa #{$conversation['id']}");
                    AutomationService::executeForConversation($automation['id'], $conversation['id']);
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'no_agent_response' ===\n");
    }
    
    /**
     * Processar gatilhos baseados em tempo (agendados)
     */
    public static function processTimeBasedTriggers(): void
    {
        Logger::automation("=== Processando gatilhos 'time_based' ===");
        
        // Buscar automações ativas
        $automations = Automation::where([
            'trigger_type' => 'time_based',
            'status' => 'active',
            'is_active' => true
        ]);
        
        Logger::automation("Encontradas " . count($automations) . " automações ativas.");
        
        $now = new \DateTime();
        $currentHour = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');
        $currentDay = (int)$now->format('N'); // 1=Segunda, 7=Domingo
        
        foreach ($automations as $automation) {
            try {
                $config = json_decode($automation['trigger_config'], true) ?? [];
                $scheduleType = $config['schedule_type'] ?? 'daily';
                $scheduleHour = isset($config['schedule_hour']) ? (int)$config['schedule_hour'] : 9;
                $scheduleMinute = isset($config['schedule_minute']) ? (int)$config['schedule_minute'] : 0;
                $scheduleDayOfWeek = isset($config['schedule_day_of_week']) ? (int)$config['schedule_day_of_week'] : 1;
                
                $shouldExecute = false;
                
                // Verificar se deve executar baseado no tipo de agendamento
                if ($scheduleType === 'daily') {
                    // Executar diariamente no horário especificado
                    $shouldExecute = ($currentHour === $scheduleHour && $currentMinute === $scheduleMinute);
                } elseif ($scheduleType === 'weekly') {
                    // Executar semanalmente no dia e horário especificados
                    $shouldExecute = (
                        $currentDay === $scheduleDayOfWeek &&
                        $currentHour === $scheduleHour &&
                        $currentMinute === $scheduleMinute
                    );
                }
                
                if ($shouldExecute) {
                    Logger::automation("Automação #{$automation['id']}: {$automation['name']} - Executando agendamento {$scheduleType}");
                    
                    // Executar para todas as conversas que atendem os critérios
                    $sql = "SELECT c.* FROM conversations c WHERE c.status IN ('open', 'pending')";
                    $params = [];
                    
                    // Filtrar por funil/estágio
                    if (!empty($automation['funnel_id'])) {
                        $sql .= " AND c.funnel_id = ?";
                        $params[] = $automation['funnel_id'];
                    }
                    if (!empty($automation['stage_id'])) {
                        $sql .= " AND c.funnel_stage_id = ?";
                        $params[] = $automation['stage_id'];
                    }
                    
                    $conversations = Database::query($sql, $params);
                    
                    Logger::automation("  → Encontradas " . count($conversations) . " conversas elegíveis.");
                    
                    foreach ($conversations as $conversation) {
                        Logger::automation("  → Executando para conversa #{$conversation['id']}");
                        AutomationService::executeForConversation($automation['id'], $conversation['id']);
                    }
                }
                
            } catch (\Exception $e) {
                Logger::automation("ERRO ao processar automação #{$automation['id']}: " . $e->getMessage());
            }
        }
        
        Logger::automation("=== Fim do processamento 'time_based' ===\n");
    }
    
    /**
     * Converter tempo para minutos
     */
    private static function convertToMinutes(int $value, string $unit): int
    {
        switch ($unit) {
            case 'hours':
                return $value * 60;
            case 'days':
                return $value * 1440; // 24 * 60
            case 'minutes':
            default:
                return $value;
        }
    }
}
```

#### B. Criar Script do Cronjob

**Arquivo:** `public/automation-scheduler.php`

```php
<?php
/**
 * Scheduler de Automações
 * 
 * Este script deve ser executado periodicamente via cronjob
 * Recomendado: a cada 1 minuto
 * 
 * Crontab:
 * * * * * * cd /path/to/project && php public/automation-scheduler.php >> storage/logs/scheduler.log 2>&1
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\AutomationSchedulerService;
use App\Helpers\Logger;

echo "[" . date('Y-m-d H:i:s') . "] === AUTOMATION SCHEDULER INICIADO ===\n";
Logger::automation("=== AUTOMATION SCHEDULER INICIADO ===");

try {
    // Processar gatilhos baseados em tempo
    AutomationSchedulerService::processTimeBasedTriggers();
    
    // Processar gatilhos de tempo sem resposta do cliente
    AutomationSchedulerService::processNoCustomerResponseTriggers();
    
    // Processar gatilhos de tempo sem resposta do agente
    AutomationSchedulerService::processNoAgentResponseTriggers();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✅ Scheduler executado com sucesso!\n";
    Logger::automation("✅ Scheduler executado com sucesso!");
    
} catch (\Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ❌ ERRO: " . $e->getMessage() . "\n";
    Logger::automation("❌ ERRO no scheduler: " . $e->getMessage());
    Logger::automation("Stack trace: " . $e->getTraceAsString());
}

echo "[" . date('Y-m-d H:i:s') . "] === AUTOMATION SCHEDULER FINALIZADO ===\n\n";
Logger::automation("=== AUTOMATION SCHEDULER FINALIZADO ===\n");
```

#### C. Configurar Cronjob no Servidor

**Para Linux:**

```bash
# Editar crontab
crontab -e

# Adicionar linha (executar a cada 1 minuto):
* * * * * cd /caminho/do/projeto && php public/automation-scheduler.php >> storage/logs/scheduler.log 2>&1
```

**Para Windows (Task Scheduler):**

1. Abrir "Agendador de Tarefas"
2. Criar Nova Tarefa
3. Nome: "Chat Automation Scheduler"
4. Gatilho: Repetir a cada 1 minuto
5. Ação: Iniciar programa
   - Programa: `C:\laragon\bin\php\php-8.1.10-Win32-vs16-x64\php.exe`
   - Argumentos: `public/automation-scheduler.php`
   - Iniciar em: `C:\laragon\www\chat`

### 2. **Testes** ⏳

Após implementar o scheduler, testar:

1. ✅ Criar automação com gatilho "Tempo sem Resposta do Cliente"
2. ✅ Configurar 1 minuto de espera
3. ✅ Criar conversa e enviar mensagem como agente
4. ✅ Aguardar 1 minuto
5. ✅ Verificar se automação foi executada
6. ✅ Verificar logs em `storage/logs/`

Repetir para "Tempo sem Resposta do Agente".

## 📊 Status Atual

| Item | Status |
|------|--------|
| Interface de Criação | ✅ Completo |
| Interface de Edição | ✅ Completo |
| Validação Backend | ✅ Completo |
| Salvamento no Banco | ✅ Completo |
| Documentação | ✅ Completo |
| Service de Processamento | ⏳ Pendente |
| Script do Cronjob | ⏳ Pendente |
| Configuração do Cronjob | ⏳ Pendente |
| Testes | ⏳ Pendente |

## 🎯 Como Usar Agora

Você **já pode**:
1. ✅ Criar automações com os novos gatilhos
2. ✅ Configurar tempo de espera (minutos/horas/dias)
3. ✅ Vincular a funis/estágios específicos
4. ✅ Adicionar nós de ação (enviar mensagem, atribuir agente, etc)
5. ✅ Salvar e visualizar a automação

Você **ainda não pode**:
- ❌ Executar automaticamente (precisa do cronjob)
- ❌ Testar a detecção de tempo sem resposta

## 🔜 Próximo Passo

Para finalizar a implementação, executar:

```bash
# 1. Criar o Service
# (copiar código acima para app/Services/AutomationSchedulerService.php)

# 2. Criar o script do cronjob
# (copiar código acima para public/automation-scheduler.php)

# 3. Testar manualmente
php public/automation-scheduler.php

# 4. Configurar cronjob (após teste bem-sucedido)
```

## 📚 Arquivos Criados/Modificados

### Criados
- ✅ `NOVOS_GATILHOS_AUTOMACAO.md` - Documentação completa
- ✅ `RESUMO_IMPLEMENTACAO_GATILHOS.md` - Este arquivo

### Modificados
- ✅ `views/automations/index.php` - Lista de automações
- ✅ `views/automations/show.php` - Editor de automação (interface completa)
- ✅ `app/Services/AutomationService.php` - Validação dos novos tipos

### A Criar
- ⏳ `app/Services/AutomationSchedulerService.php` - Lógica de processamento
- ⏳ `public/automation-scheduler.php` - Script do cronjob

