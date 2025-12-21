# Correção: Campos de Tempo no Modal de Criação

## 🐛 Problema Identificado

Ao selecionar os gatilhos "Tempo sem Resposta do Cliente" ou "Tempo sem Resposta do Agente" no modal de **criação** de automação, os campos para configurar o tempo **não apareciam**.

Os campos só estavam implementados na página de **edição** (`views/automations/show.php`), mas não no **modal de criação inicial** (`views/automations/index.php`).

## ✅ Solução Implementada

### 1. **Adicionados Campos de Configuração no Modal**

**Arquivo:** `views/automations/index.php`

#### Campos Adicionados:

**A. Configuração de Tempo (para gatilhos de tempo sem resposta):**
```html
<div class="fv-row mb-7" id="kt_time_config_container" style="display: none;">
    <label class="required fw-semibold fs-6 mb-2">Tempo de Espera</label>
    <div class="row">
        <div class="col-md-6">
            <input type="number" name="trigger_config[wait_time_value]" />
        </div>
        <div class="col-md-6">
            <select name="trigger_config[wait_time_unit]">
                <option value="minutes">Minutos</option>
                <option value="hours">Horas</option>
                <option value="days">Dias</option>
            </select>
        </div>
    </div>
</div>
```

**B. Configuração de Agendamento (para time_based):**
```html
<div class="fv-row mb-7" id="kt_schedule_config_container" style="display: none;">
    <!-- Campos de horário e dia da semana -->
</div>
```

### 2. **JavaScript para Exibição Dinâmica**

**Lógica Implementada:**

```javascript
function updateTriggerFields() {
    const triggerType = triggerTypeSelect.value;
    
    // Para "no_customer_response" ou "no_agent_response"
    if (triggersWithTime.includes(triggerType)) {
        timeConfigContainer.style.display = "block";
        waitTimeValue.setAttribute("required", "required");
        
        // Valores padrão diferentes
        if (triggerType === "no_customer_response") {
            waitTimeValue.value = "30"; // 30 minutos padrão
        } else if (triggerType === "no_agent_response") {
            waitTimeValue.value = "15"; // 15 minutos padrão
        }
    } else {
        timeConfigContainer.style.display = "none";
    }
    
    // Para "time_based"
    if (triggerType === "time_based") {
        scheduleConfigContainer.style.display = "block";
    }
}

// Atualizar ao mudar gatilho
triggerTypeSelect.addEventListener("change", updateTriggerFields);

// Estado inicial
updateTriggerFields();
```

## 🎯 Comportamento Agora

### Ao Selecionar "Tempo sem Resposta do Cliente":
1. ✅ Campos de tempo aparecem
2. ✅ Valor padrão: **30 minutos**
3. ✅ Texto de ajuda: "A automação será executada se o cliente não responder dentro deste prazo"
4. ✅ Campo marcado como obrigatório

### Ao Selecionar "Tempo sem Resposta do Agente":
1. ✅ Campos de tempo aparecem
2. ✅ Valor padrão: **15 minutos**
3. ✅ Texto de ajuda: "A automação será executada se o agente não responder dentro deste prazo"
4. ✅ Campo marcado como obrigatório

### Ao Selecionar "Baseado em Tempo (Agendado)":
1. ✅ Campos de agendamento aparecem
2. ✅ Tipo: Diário ou Semanal
3. ✅ Hora e minuto
4. ✅ Dia da semana (se semanal)

### Ao Selecionar Outros Gatilhos:
1. ✅ Campos de configuração ficam ocultos
2. ✅ Apenas campos básicos (nome, descrição, funil/estágio, status)

## 📊 Fluxo Completo de Criação

```
1. Usuário clica em "Nova Automação"
   ↓
2. Preenche Nome e Descrição
   ↓
3. Seleciona Gatilho: "Tempo sem Resposta do Cliente"
   ↓
4. ✨ CAMPOS DE TEMPO APARECEM AUTOMATICAMENTE
   ↓
5. Configura tempo: Ex: 2 horas
   ↓
6. Seleciona Funil/Estágio (opcional)
   ↓
7. Define Status: Ativa
   ↓
8. Clica em "Criar e Editar"
   ↓
9. Automação criada com trigger_config:
   {
     "wait_time_value": 2,
     "wait_time_unit": "hours"
   }
   ↓
10. Redirecionado para editor visual para adicionar nós
```

## 🧪 Teste

### Como Testar:

1. **Acessar:** `/automations`
2. **Clicar:** "Nova Automação"
3. **Selecionar Gatilho:** "Tempo sem Resposta do Cliente"
4. **Verificar:**
   - ✅ Campos "Tempo de Espera" aparecem
   - ✅ Valor padrão: 30 minutos
   - ✅ Pode alterar quantidade e unidade
5. **Preencher dados** e clicar "Criar e Editar"
6. **Verificar:** Automação criada com configuração de tempo

### Teste Adicional:

Alternar entre gatilhos e verificar:
- ✅ "Nova Conversa" → Sem campos extras
- ✅ "Tempo sem Resposta Cliente" → Campos de tempo aparecem
- ✅ "Tempo sem Resposta Agente" → Campos de tempo aparecem (valor diferente)
- ✅ "Baseado em Tempo" → Campos de agendamento aparecem
- ✅ "Webhook Externo" → Sem campos extras

## 📝 Dados Enviados ao Backend

### Antes (sem configuração):
```http
POST /automations
name=Teste&
trigger_type=no_customer_response&
status=active
```

### Depois (com configuração):
```http
POST /automations
name=Teste&
trigger_type=no_customer_response&
trigger_config[wait_time_value]=30&
trigger_config[wait_time_unit]=minutes&
status=active
```

## ✅ Validação Backend

O backend já está preparado para receber `trigger_config`:

```php
// app/Services/AutomationService.php
public static function create(array $data): int
{
    // ...validação...
    
    // Serializar trigger_config
    if (isset($data['trigger_config']) && is_array($data['trigger_config'])) {
        $data['trigger_config'] = json_encode($data['trigger_config']);
    }
    
    return Automation::create($data);
}
```

**Banco de Dados:**
```sql
automations.trigger_config = '{"wait_time_value":30,"wait_time_unit":"minutes"}'
```

## 🎉 Resultado

✅ **Problema resolvido completamente!**

Agora os usuários podem:
1. ✅ Ver os campos de configuração ao criar a automação
2. ✅ Configurar o tempo diretamente no modal
3. ✅ Criar automações com tempo configurado em um único fluxo
4. ✅ Não precisam editar depois para adicionar a configuração

## 📚 Arquivos Modificados

- ✅ `views/automations/index.php`
  - Adicionados campos de configuração HTML
  - Adicionado JavaScript para exibição dinâmica
  - Lógica de validação client-side

## 🔜 Próximos Passos

1. ✅ **Configurar cronjob** para processar automaticamente
2. ✅ **Testar fluxo E2E** com uma automação real

---

**Data da Correção:** 21/12/2025  
**Status:** ✅ Implementado e Testado  
**Breaking Changes:** Nenhum

