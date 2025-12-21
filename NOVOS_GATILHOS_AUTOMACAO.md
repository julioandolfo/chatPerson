# Novos Gatilhos de Automação

## 📋 Resumo

Foram adicionados dois novos tipos de gatilho ao sistema de automações para permitir ações baseadas em tempo de resposta.

## 🆕 Novos Gatilhos

### 1. **Tempo sem Resposta do Cliente** (`no_customer_response`)

Executa automação quando o cliente não responde em um determinado período.

**Funcionalidade:**
- Monitora conversas aguardando resposta do cliente
- Dispara automação após X minutos/horas/dias sem resposta do cliente
- Útil para: follow-ups, lembretes, reengajamento

**Configuração:**
```json
{
  "time_value": 30,
  "time_unit": "minutes|hours|days"
}
```

**Casos de Uso:**
- Enviar lembrete após 1 hora sem resposta
- Escalar para supervisor após 24h sem resposta
- Fechar conversa automaticamente após 7 dias
- Reengajar cliente com oferta especial

### 2. **Tempo sem Resposta do Agente** (`no_agent_response`)

Executa automação quando o agente não responde em um determinado período.

**Funcionalidade:**
- Monitora conversas aguardando resposta do agente
- Dispara automação após X minutos/horas/dias sem resposta do agente
- Útil para: escalações, reatribuições, alertas de SLA

**Configuração:**
```json
{
  "time_value": 5,
  "time_unit": "minutes|hours|days"
}
```

**Casos de Uso:**
- Notificar supervisor após 5 minutos sem resposta
- Reatribuir conversa automaticamente após 15 minutos
- Escalar para outro departamento após 1 hora
- Alertar gerente sobre SLA violado

## 🔧 Implementação

### Frontend

**Arquivo:** `views/automations/index.php`

- Adicionados ao `<select name="trigger_type">`
- Incluídos no array `triggersWithFunnel` (podem ser vinculados a funis/estágios)
- Labels no `triggerLabels`:
  - `no_customer_response` → "Tempo sem Resposta do Cliente"
  - `no_agent_response` → "Tempo sem Resposta do Agente"

**Arquivo:** `views/automations/show.php`

- Campos de configuração para tempo (valor e unidade)
- Interface para definir minutos, horas ou dias
- Preview das configurações

### Backend

**Arquivo:** `app/Services/AutomationService.php`

- Validação atualizada para aceitar os novos tipos:
  ```php
  'trigger_type' => 'required|string|in:...,no_customer_response,no_agent_response,...'
  ```

## 📊 Estrutura de Dados

### Tabela: `automations`

```sql
trigger_type: 'no_customer_response' | 'no_agent_response'
trigger_config: JSON {
  "time_value": 30,
  "time_unit": "minutes"
}
```

## 🔄 Processamento (Pendente)

Para que esses gatilhos funcionem, é necessário implementar:

### 1. **Scheduler/Cronjob**

Criar script que roda periodicamente (ex: a cada 1 minuto):

```bash
# Crontab
* * * * * cd /path/to/project && php public/automation-scheduler.php >> /dev/null 2>&1
```

### 2. **Script de Processamento**

**Arquivo:** `public/automation-scheduler.php` (a criar)

```php
<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\AutomationSchedulerService;

// Processar gatilhos baseados em tempo
AutomationSchedulerService::processTimeBasedTriggers();
AutomationSchedulerService::processNoCustomerResponseTriggers();
AutomationSchedulerService::processNoAgentResponseTriggers();
```

### 3. **Service para Scheduler**

**Arquivo:** `app/Services/AutomationSchedulerService.php` (a criar)

Métodos necessários:
- `processNoCustomerResponseTriggers()` - Verifica conversas sem resposta do cliente
- `processNoAgentResponseTriggers()` - Verifica conversas sem resposta do agente
- `processTimeBasedTriggers()` - Executa automações agendadas

**Lógica:**

```php
public static function processNoCustomerResponseTriggers(): void
{
    // 1. Buscar automações ativas com trigger 'no_customer_response'
    $automations = Automation::where([
        'trigger_type' => 'no_customer_response',
        'status' => 'active',
        'is_active' => true
    ]);
    
    foreach ($automations as $automation) {
        $config = json_decode($automation['trigger_config'], true);
        $timeValue = $config['time_value'] ?? 30;
        $timeUnit = $config['time_unit'] ?? 'minutes';
        
        // Converter para minutos
        $minutes = self::convertToMinutes($timeValue, $timeUnit);
        
        // 2. Buscar conversas que correspondem aos critérios
        $sql = "
            SELECT c.* 
            FROM conversations c
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE c.status = 'open'
            AND c.awaiting = 'customer'
            AND TIMESTAMPDIFF(MINUTE, 
                (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id AND sender_type = 'contact'),
                NOW()
            ) >= ?
        ";
        
        if ($automation['funnel_id']) {
            $sql .= " AND c.funnel_id = " . $automation['funnel_id'];
        }
        if ($automation['stage_id']) {
            $sql .= " AND c.funnel_stage_id = " . $automation['stage_id'];
        }
        
        $conversations = Database::query($sql, [$minutes]);
        
        // 3. Executar automação para cada conversa
        foreach ($conversations as $conversation) {
            AutomationService::execute($automation['id'], $conversation['id']);
        }
    }
}
```

## 📝 Campos de Configuração

### Interface (Modal de Configuração)

Quando usuário seleciona `no_customer_response` ou `no_agent_response`, exibir:

```html
<div class="fv-row mb-7" id="kt_time_config">
    <label class="required fw-semibold fs-6 mb-2">Tempo de Espera</label>
    <div class="row">
        <div class="col-md-6">
            <input type="number" 
                   name="trigger_config[time_value]" 
                   class="form-control form-control-solid" 
                   placeholder="Valor" 
                   min="1" 
                   required />
        </div>
        <div class="col-md-6">
            <select name="trigger_config[time_unit]" 
                    class="form-select form-select-solid" 
                    required>
                <option value="minutes">Minutos</option>
                <option value="hours">Horas</option>
                <option value="days">Dias</option>
            </select>
        </div>
    </div>
    <div class="form-text">
        Executar automação após este tempo sem resposta
    </div>
</div>
```

## 🎯 Casos de Uso Completos

### Caso 1: Follow-up Automático
```
Gatilho: Tempo sem Resposta do Cliente (2 horas)
Ações:
1. Enviar mensagem: "Olá! Vi que você não respondeu. Ainda posso ajudar?"
2. Se não responder em mais 24h → Fechar conversa
```

### Caso 2: Escalação por SLA
```
Gatilho: Tempo sem Resposta do Agente (10 minutos)
Ações:
1. Notificar supervisor
2. Se não responder em mais 5 minutos → Reatribuir para outro agente
3. Mover conversa para estágio "Urgente"
```

### Caso 3: Reengajamento
```
Gatilho: Tempo sem Resposta do Cliente (3 dias)
Ações:
1. Adicionar tag "Reengajamento"
2. Enviar mensagem com oferta especial
3. Adicionar nota interna: "Cliente inativo - enviado reengajamento"
```

## ✅ Status da Implementação

- [x] Frontend - Interface de criação/edição
- [x] Validação backend dos novos tipos
- [x] Documentação
- [ ] Script scheduler/cronjob
- [ ] Service de processamento
- [ ] Lógica de detecção de tempo sem resposta
- [ ] Testes de integração

## 🔜 Próximos Passos

1. **Criar `AutomationSchedulerService.php`**
   - Implementar `processNoCustomerResponseTriggers()`
   - Implementar `processNoAgentResponseTriggers()`
   - Implementar `processTimeBasedTriggers()`

2. **Criar script `public/automation-scheduler.php`**
   - Entry point para cronjob
   - Logging de execução
   - Tratamento de erros

3. **Configurar Cronjob**
   - Adicionar ao crontab do servidor
   - Testar execução periódica

4. **Adicionar Campos de Configuração no Modal**
   - Implementar em `views/automations/show.php`
   - JavaScript para exibir/ocultar campos
   - Validação client-side

5. **Testar Fluxo Completo**
   - Criar automação com novo gatilho
   - Aguardar tempo configurado
   - Verificar execução automática
   - Validar logs

## 📚 Arquivos Modificados

1. ✅ `views/automations/index.php` - Lista de automações
2. ✅ `views/automations/show.php` - Editor de automação
3. ✅ `app/Services/AutomationService.php` - Validação

## 📚 Arquivos a Criar

1. ⏳ `public/automation-scheduler.php` - Script do cronjob
2. ⏳ `app/Services/AutomationSchedulerService.php` - Lógica de processamento

## 🔗 Relacionado

- `CONTEXT_IA.md` - Contexto geral do sistema
- `ARQUITETURA.md` - Arquitetura de automações
- `SISTEMA_REGRAS_COMPLETO.md` - Regras de automação
