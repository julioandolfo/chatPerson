# 📋 PLANEJAMENTO: Nó de Atribuição Avançada

## Data: 18/12/2025

---

## 🎯 Objetivo

Criar um nó específico para **atribuição avançada** de conversas em automações, replicando todas as configurações disponíveis no sistema de configurações gerais, permitindo atribuição granular e inteligente dentro de fluxos automatizados.

---

## 📊 Análise do Sistema Atual

### **Onde está a configuração de atribuição:**
- **Localização:** `/settings` → Aba "Conversas Avançadas"
- **Service:** `ConversationSettingsService.php`
- **Método principal:** `autoAssignConversation()`

### **Configurações Disponíveis:**

#### **1. Distribuição e Atribuição**
- ✅ Habilitar atribuição automática
- ✅ Método de distribuição:
  - `round_robin` - Round-Robin (Distribuição igual)
  - `by_load` - Por Carga (Menor carga primeiro)
  - `by_performance` - Por Performance (Melhor performance)
  - `by_specialty` - Por Especialidade
  - `percentage` - Por Porcentagem
- ✅ Considerar status de disponibilidade (online/offline)
- ✅ Considerar limite máximo de conversas
- ✅ Permitir atribuição a agentes de IA

#### **2. Filtros de Contexto**
- Setor/Departamento (`department_id`)
- Funil (`funnel_id`)
- Estágio (`stage_id`)

#### **3. Distribuição por Porcentagem**
- Regras por agente ou setor
- Porcentagens específicas
- Balanceamento automático

---

## 🎨 Proposta de Implementação

### **Nome do Nó:**
`action_assign_advanced` - "Atribuição Avançada"

### **Ícone e Cor:**
- **Ícone:** `ki-user-tick` (usuário com check)
- **Cor:** `#7239ea` (roxo - mesma do "Atribuir Agente" simples)

---

## 📝 Configurações do Nó

### **Formulário de Configuração:**

```html
<!--begin::Tipo de Atribuição-->
<div class="fv-row mb-7">
    <label class="required fw-semibold fs-6 mb-2">Tipo de Atribuição</label>
    <select name="assignment_type" id="kt_assignment_type" class="form-select form-select-solid" required>
        <option value="auto">Automática (Usar método do sistema)</option>
        <option value="specific_agent">Agente Específico</option>
        <option value="department">Setor Específico</option>
        <option value="custom_method">Método Personalizado</option>
    </select>
</div>

<!--begin::Agente Específico (se tipo = specific_agent)-->
<div id="specific_agent_container" style="display: none;">
    <div class="fv-row mb-7">
        <label class="required fw-semibold fs-6 mb-2">Agente</label>
        <select name="agent_id" class="form-select form-select-solid">
            <option value="">Selecione um agente</option>
            <!-- Lista de agentes dinamicamente -->
        </select>
    </div>
    <div class="fv-row mb-7">
        <label class="d-flex align-items-center">
            <input type="checkbox" name="force_assign" class="form-check-input me-2" />
            <span class="fw-semibold fs-6">Forçar atribuição (ignorar limites)</span>
        </label>
        <div class="form-text">Se habilitado, ignora limite máximo e status de disponibilidade</div>
    </div>
</div>

<!--begin::Setor Específico (se tipo = department)-->
<div id="department_container" style="display: none;">
    <div class="fv-row mb-7">
        <label class="required fw-semibold fs-6 mb-2">Setor</label>
        <select name="department_id" class="form-select form-select-solid">
            <option value="">Selecione um setor</option>
            <!-- Lista de setores dinamicamente -->
        </select>
        <div class="form-text">Atribui a um agente disponível do setor selecionado</div>
    </div>
</div>

<!--begin::Método Personalizado (se tipo = custom_method)-->
<div id="custom_method_container" style="display: none;">
    <div class="fv-row mb-7">
        <label class="required fw-semibold fs-6 mb-2">Método de Distribuição</label>
        <select name="distribution_method" class="form-select form-select-solid">
            <option value="round_robin">Round-Robin (Distribuição igual)</option>
            <option value="by_load">Por Carga (Menor carga primeiro)</option>
            <option value="by_performance">Por Performance</option>
            <option value="by_specialty">Por Especialidade</option>
            <option value="percentage">Por Porcentagem</option>
        </select>
    </div>
    
    <div class="fv-row mb-7">
        <label class="fw-semibold fs-6 mb-2">Filtrar por Setor</label>
        <select name="filter_department_id" class="form-select form-select-solid">
            <option value="">Todos os setores</option>
            <!-- Lista de setores -->
        </select>
        <div class="form-text">Limita candidatos a agentes de um setor específico</div>
    </div>
    
    <div class="fv-row mb-7">
        <label class="d-flex align-items-center">
            <input type="checkbox" name="consider_availability" class="form-check-input me-2" checked />
            <span class="fw-semibold fs-6">Considerar status de disponibilidade</span>
        </label>
        <div class="form-text">Apenas agentes online/disponíveis</div>
    </div>
    
    <div class="fv-row mb-7">
        <label class="d-flex align-items-center">
            <input type="checkbox" name="consider_max_conversations" class="form-check-input me-2" checked />
            <span class="fw-semibold fs-6">Considerar limite máximo</span>
        </label>
        <div class="form-text">Respeita limite máximo de conversas do agente</div>
    </div>
    
    <div class="fv-row mb-7">
        <label class="d-flex align-items-center">
            <input type="checkbox" name="allow_ai_agents" class="form-check-input me-2" />
            <span class="fw-semibold fs-6">Permitir agentes de IA</span>
        </label>
        <div class="form-text">Inclui agentes de IA na seleção</div>
    </div>
</div>

<!--begin::Ação se Falhar-->
<div class="fv-row mb-7">
    <label class="fw-semibold fs-6 mb-2">Se não conseguir atribuir</label>
    <select name="fallback_action" class="form-select form-select-solid">
        <option value="leave_unassigned">Deixar sem atribuição</option>
        <option value="try_any_agent">Tentar qualquer agente disponível</option>
        <option value="assign_to_ai">Atribuir a IA</option>
        <option value="move_to_stage">Mover para estágio específico</option>
    </select>
</div>

<div id="fallback_stage_container" style="display: none;">
    <div class="fv-row mb-7">
        <label class="required fw-semibold fs-6 mb-2">Estágio de Fallback</label>
        <select name="fallback_stage_id" class="form-select form-select-solid">
            <option value="">Selecione um estágio</option>
            <!-- Lista de estágios -->
        </select>
    </div>
</div>
```

---

## 💻 Implementação Backend

### **Service: `AutomationService::executeAssignAdvanced()`**

```php
private static function executeAssignAdvanced(array $nodeData, int $conversationId, ?int $executionId = null): void
{
    try {
        $assignmentType = $nodeData['assignment_type'] ?? 'auto';
        $agentId = null;
        
        switch ($assignmentType) {
            case 'specific_agent':
                $agentId = (int)($nodeData['agent_id'] ?? 0);
                $forceAssign = (bool)($nodeData['force_assign'] ?? false);
                
                if ($agentId) {
                    \App\Services\ConversationService::assignToAgent($conversationId, $agentId, $forceAssign);
                }
                break;
                
            case 'department':
                $departmentId = (int)($nodeData['department_id'] ?? 0);
                if ($departmentId) {
                    $agentId = \App\Services\ConversationSettingsService::autoAssignConversation(
                        $conversationId,
                        $departmentId,
                        null,
                        null
                    );
                }
                break;
                
            case 'custom_method':
                $method = $nodeData['distribution_method'] ?? 'round_robin';
                $filterDepartmentId = !empty($nodeData['filter_department_id']) ? (int)$nodeData['filter_department_id'] : null;
                $considerAvailability = (bool)($nodeData['consider_availability'] ?? true);
                $considerMaxConversations = (bool)($nodeData['consider_max_conversations'] ?? true);
                $allowAI = (bool)($nodeData['allow_ai_agents'] ?? false);
                
                // Aplicar configurações temporariamente
                $originalSettings = \App\Services\ConversationSettingsService::getSettings();
                $tempSettings = $originalSettings;
                $tempSettings['distribution']['method'] = $method;
                $tempSettings['distribution']['consider_availability'] = $considerAvailability;
                $tempSettings['distribution']['consider_max_conversations'] = $considerMaxConversations;
                $tempSettings['distribution']['assign_to_ai_agent'] = $allowAI;
                
                // Executar atribuição com configurações personalizadas
                $agentId = \App\Services\ConversationSettingsService::autoAssignConversation(
                    $conversationId,
                    $filterDepartmentId,
                    null,
                    null
                );
                break;
                
            case 'auto':
            default:
                // Usa método padrão do sistema
                $agentId = \App\Services\ConversationSettingsService::autoAssignConversation($conversationId);
                break;
        }
        
        // Se não conseguiu atribuir, executar fallback
        if (!$agentId) {
            $fallbackAction = $nodeData['fallback_action'] ?? 'leave_unassigned';
            
            switch ($fallbackAction) {
                case 'try_any_agent':
                    // Tenta qualquer agente disponível sem filtros
                    $agentId = \App\Services\ConversationSettingsService::assignRoundRobin(null, null, null, false);
                    if ($agentId) {
                        \App\Services\ConversationService::assignToAgent($conversationId, $agentId, false);
                    }
                    break;
                    
                case 'assign_to_ai':
                    // Atribuir a um agente de IA
                    $aiAgents = \App\Models\User::where('is_ai_agent', '=', 1);
                    if (!empty($aiAgents)) {
                        \App\Services\ConversationService::assignToAgent($conversationId, $aiAgents[0]['id'], false);
                    }
                    break;
                    
                case 'move_to_stage':
                    $fallbackStageId = (int)($nodeData['fallback_stage_id'] ?? 0);
                    if ($fallbackStageId) {
                        \App\Services\FunnelService::moveConversationToStage($conversationId, $fallbackStageId);
                    }
                    break;
                    
                case 'leave_unassigned':
                default:
                    // Não faz nada, deixa sem atribuição
                    break;
            }
        }
        
        if ($executionId) {
            \App\Models\AutomationExecution::updateStatus(
                $executionId,
                'completed',
                $agentId ? "Atribuído ao agente ID: {$agentId}" : "Não foi possível atribuir"
            );
        }
        
    } catch (\Exception $e) {
        if ($executionId) {
            \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro na atribuição: " . $e->getMessage());
        }
        throw $e;
    }
}
```

---

## 🎯 Casos de Uso

### **1. Atribuição Simples a Agente Específico**
```
Tipo: Agente Específico
Agente: João Silva
Forçar: ☑ Sim
```
**Resultado:** Conversa atribuída diretamente a João, ignorando limites.

---

### **2. Distribuição Inteligente por Setor**
```
Tipo: Setor Específico
Setor: Comercial
```
**Resultado:** Conversa atribuída ao próximo agente disponível do setor Comercial usando método padrão do sistema.

---

### **3. Distribuição por Carga Personalizada**
```
Tipo: Método Personalizado
Método: Por Carga (menor primeiro)
Filtrar por: Suporte Técnico
Disponibilidade: ☑ Considerar
Limites: ☑ Considerar
IA: ☐ Não permitir
```
**Resultado:** Conversa atribuída ao agente de Suporte Técnico com menor carga atual.

---

### **4. Com Fallback Inteligente**
```
Tipo: Método Personalizado
Método: Por Performance
Se falhar: Atribuir a IA
```
**Resultado:** Tenta atribuir ao melhor agente; se nenhum disponível, atribui a IA.

---

## 📊 Diferencial vs Nó "Atribuir Agente" Simples

| Aspecto | Atribuir Agente (Simples) | Atribuição Avançada (Novo) |
|---------|---------------------------|----------------------------|
| **Seleção** | Apenas agente específico | Agente, setor, método, automático |
| **Métodos** | ❌ Não | ✅ 5 métodos diferentes |
| **Filtros** | ❌ Não | ✅ Setor, disponibilidade, limites |
| **IA** | ❌ Não | ✅ Pode incluir agentes de IA |
| **Fallback** | ❌ Não | ✅ 4 opções de fallback |
| **Forçar** | ❌ Não | ✅ Pode forçar e ignorar limites |
| **Contexto** | ❌ Não | ✅ Considera funil/estágio/setor |

---

## 🚀 Benefícios

1. **Flexibilidade Total:** Cobre 100% dos cenários de atribuição
2. **Reutilização:** Aproveita toda lógica já implementada em `ConversationSettingsService`
3. **Fallback Inteligente:** Garante que conversa seja tratada mesmo se atribuição falhar
4. **Contexto Aware:** Considera setor, funil, estágio da conversa
5. **Performance:** Pode priorizar agentes por carga ou performance
6. **IA Ready:** Integração com agentes de IA quando necessário

---

## 📝 Próximos Passos

1. ✅ **Aprovar planejamento** (este documento)
2. ⏳ Corrigir botão de editar do Chatbot (z-index)
3. ⏳ Criar tipo de nó `action_assign_advanced` em `AutomationNode::getNodeTypes()`
4. ⏳ Implementar formulário de configuração no modal
5. ⏳ Implementar `executeAssignAdvanced()` no `AutomationService`
6. ⏳ Adicionar opção no painel lateral de tipos de nó
7. ⏳ Testar todos os cenários
8. ⏳ Documentar

---

## ❓ Dúvidas / Decisões Pendentes

1. **Nome do nó:**
   - "Atribuição Avançada" ✅
   - "Atribuição Inteligente"
   - "Distribuir Conversa"

2. **Deve substituir o nó "Atribuir Agente" simples?**
   - ❌ Não, manter os dois (simples para casos básicos, avançado para complexos)
   - ✅ Sim, unificar em um só

3. **Distribuição por porcentagem:**
   - ✅ Incluir no nó (mais complexo)
   - ❌ Deixar apenas para configurações globais (mais simples)

---

**Status:** 📋 **AGUARDANDO APROVAÇÃO**  
**Prioridade:** 🔥 **ALTA**  
**Estimativa:** 3-4 horas de implementação

