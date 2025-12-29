<?php
$layout = 'layouts.metronic.app';
$title = 'Editar Agente Kanban: ' . htmlspecialchars($agent['name'] ?? '');

ob_start();
?>
<!--begin::Card-->
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <h3 class="fw-bold m-0">Editar Agente Kanban</h3>
        </div>
    </div>
    <div class="card-body pt-0">
        <form id="kt_form_kanban_agent">
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label required">Nome</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($agent['name']) ?>" required>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($agent['description'] ?? '') ?></textarea>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label required">Tipo</label>
                    <select name="agent_type" class="form-select" required>
                        <option value="kanban_followup" <?= ($agent['agent_type'] ?? '') === 'kanban_followup' ? 'selected' : '' ?>>Followup</option>
                        <option value="kanban_analyzer" <?= ($agent['agent_type'] ?? '') === 'kanban_analyzer' ? 'selected' : '' ?>>Analisador</option>
                        <option value="kanban_manager" <?= ($agent['agent_type'] ?? '') === 'kanban_manager' ? 'selected' : '' ?>>Gerenciador</option>
                        <option value="kanban_custom" <?= ($agent['agent_type'] ?? '') === 'kanban_custom' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Modelo</label>
                    <select name="model" class="form-select" required>
                        <option value="gpt-4" <?= ($agent['model'] ?? 'gpt-4') === 'gpt-4' ? 'selected' : '' ?>>GPT-4</option>
                        <option value="gpt-3.5-turbo" <?= ($agent['model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                    </select>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-12">
                    <label class="form-label required">Prompt</label>
                    <textarea name="prompt" class="form-control" rows="5" required><?= htmlspecialchars($agent['prompt']) ?></textarea>
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label required">Tipo de Execução</label>
                    <select name="execution_type" class="form-select" required id="execution_type">
                        <option value="interval" <?= ($agent['execution_type'] ?? '') === 'interval' ? 'selected' : '' ?>>Por Intervalo</option>
                        <option value="schedule" <?= ($agent['execution_type'] ?? '') === 'schedule' ? 'selected' : '' ?>>Agendado</option>
                        <option value="manual" <?= ($agent['execution_type'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual</option>
                    </select>
                </div>
                <div class="col-md-6" id="interval_hours_container" style="display: <?= ($agent['execution_type'] ?? '') === 'interval' ? 'block' : 'none' ?>;">
                    <label class="form-label">Intervalo (horas)</label>
                    <input type="number" name="execution_interval_hours" class="form-control" min="1" value="<?= $agent['execution_interval_hours'] ?? '' ?>" placeholder="Ex: 48 (a cada 2 dias)">
                </div>
            </div>
            
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Máximo de Conversas por Execução</label>
                    <input type="number" name="max_conversations_per_execution" class="form-control" value="<?= $agent['max_conversations_per_execution'] ?? 50 ?>" min="1" max="1000">
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-8">
                        <input class="form-check-input" type="checkbox" name="enabled" id="enabled" <?= ($agent['enabled'] ?? true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="enabled">Ativo</label>
                    </div>
                </div>
            </div>
            
            <div class="separator separator-dashed my-10"></div>
            
            <h4 class="fw-bold mb-5">Funis e Etapas Alvo</h4>
            <div class="row mb-5">
                <div class="col-md-6">
                    <label class="form-label">Funis (deixe vazio para todos)</label>
                    <select name="target_funnel_ids[]" class="form-select" id="target_funnels" multiple size="5">
                        <?php 
                        $selectedFunnelIds = $agent['target_funnel_ids'] ?? [];
                        foreach ($funnels as $funnel): 
                        ?>
                            <option value="<?= $funnel['id'] ?>" <?= in_array($funnel['id'], $selectedFunnelIds) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($funnel['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Segure Ctrl/Cmd para selecionar múltiplos funis</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Etapas (deixe vazio para todas)</label>
                    <select name="target_stage_ids[]" class="form-select" id="target_stages" multiple size="5">
                        <option value="">Carregando etapas...</option>
                    </select>
                    <div class="form-text">Segure Ctrl/Cmd para selecionar múltiplas etapas</div>
                </div>
            </div>
            
            <div class="separator separator-dashed my-10"></div>
            
            <!-- Abas para Condições e Ações -->
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-bold" role="tablist">
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary ms-0 me-10 py-5 active" data-bs-toggle="tab" href="#kt_tab_conditions">
                        Condições
                    </a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary me-10 py-5" data-bs-toggle="tab" href="#kt_tab_actions">
                        Ações
                    </a>
                </li>
                <li class="nav-item mt-2">
                    <a class="nav-link text-active-primary me-10 py-5" data-bs-toggle="tab" href="#kt_tab_schedule">
                        Agendamento
                    </a>
                </li>
            </ul>
            
            <div class="tab-content" id="kt_tab_content">
                <!-- Aba Condições -->
                <div class="tab-pane fade show active" id="kt_tab_conditions" role="tabpanel">
                    <div class="card mt-5">
                        <div class="card-header">
                            <h3 class="card-title">Configurar Condições</h3>
                            <div class="card-toolbar">
                                <select id="conditions_operator" class="form-select form-select-sm w-150px">
                                    <option value="AND" <?= ($agent['conditions']['operator'] ?? 'AND') === 'AND' ? 'selected' : '' ?>>E (AND)</option>
                                    <option value="OR" <?= ($agent['conditions']['operator'] ?? '') === 'OR' ? 'selected' : '' ?>>OU (OR)</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="conditions_list">
                                <!-- Condições serão adicionadas aqui -->
                            </div>
                            <button type="button" class="btn btn-light-primary" onclick="addCondition()">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Condição
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Aba Ações -->
                <div class="tab-pane fade" id="kt_tab_actions" role="tabpanel">
                    <div class="card mt-5">
                        <div class="card-header">
                            <h3 class="card-title">Configurar Ações</h3>
                        </div>
                        <div class="card-body">
                            <div id="actions_list">
                                <!-- Ações serão adicionadas aqui -->
                            </div>
                            <button type="button" class="btn btn-light-primary" onclick="addAction()">
                                <i class="ki-duotone ki-plus fs-2"></i>
                                Adicionar Ação
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Aba Agendamento -->
                <div class="tab-pane fade" id="kt_tab_schedule" role="tabpanel">
                    <div class="card mt-5">
                        <div class="card-header">
                            <h3 class="card-title">Configurar Agendamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="row mb-5">
                                <div class="col-md-12">
                                    <label class="form-label">Dias da Semana</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                        $selectedDays = $agent['execution_schedule']['days'] ?? [];
                                        for ($i = 0; $i < 7; $i++):
                                        ?>
                                            <div class="form-check form-check-custom form-check-solid">
                                                <input class="form-check-input" type="checkbox" value="<?= $i ?>" id="schedule_day_<?= $i ?>" <?= in_array($i, $selectedDays) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="schedule_day_<?= $i ?>">
                                                    <?= $days[$i] ?>
                                                </label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-5">
                                <div class="col-md-6">
                                    <label class="form-label">Horário</label>
                                    <input type="time" id="schedule_time" class="form-control" value="<?= $agent['execution_schedule']['time'] ?? '09:00' ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="separator separator-dashed my-10"></div>
            
            <div class="d-flex justify-content-end">
                <a href="<?= \App\Helpers\Url::to('/kanban-agents/' . $agent['id']) ?>" class="btn btn-light me-3">Cancelar</a>
                <button type="button" class="btn btn-info me-3" onclick="testConditions()">Testar Condições</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
<!--end::Card-->

<script>
const allStages = <?= json_encode($allStages, JSON_UNESCAPED_UNICODE) ?>;
const selectedStageIds = <?= json_encode($agent['target_stage_ids'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

document.getElementById('execution_type').addEventListener('change', function() {
    const intervalContainer = document.getElementById('interval_hours_container');
    if (this.value === 'interval') {
        intervalContainer.style.display = 'block';
    } else {
        intervalContainer.style.display = 'none';
    }
});

// Atualizar etapas quando funis são selecionados
function updateStages() {
    const selectedFunnels = Array.from(document.getElementById('target_funnels').selectedOptions).map(opt => parseInt(opt.value));
    const stagesSelect = document.getElementById('target_stages');
    
    stagesSelect.innerHTML = '';
    
    if (selectedFunnels.length === 0) {
        // Se nenhum funil selecionado, mostrar todas as etapas
        Object.keys(allStages).forEach(funnelId => {
            allStages[funnelId].forEach(stage => {
                const option = document.createElement('option');
                option.value = stage.id;
                option.textContent = stage.name + ' (Funil: ' + stage.funnel_id + ')';
                if (selectedStageIds.includes(stage.id)) {
                    option.selected = true;
                }
                stagesSelect.appendChild(option);
            });
        });
    } else {
        // Mostrar apenas etapas dos funis selecionados
        selectedFunnels.forEach(funnelId => {
            if (allStages[funnelId]) {
                allStages[funnelId].forEach(stage => {
                    const option = document.createElement('option');
                    option.value = stage.id;
                    option.textContent = stage.name;
                    if (selectedStageIds.includes(stage.id)) {
                        option.selected = true;
                    }
                    stagesSelect.appendChild(option);
                });
            }
        });
    }
}

document.getElementById('target_funnels').addEventListener('change', updateStages);

// Carregar etapas inicialmente
updateStages();

// Variáveis globais para condições e ações
let conditions = <?= json_encode($agent['conditions']['conditions'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
let actions = <?= json_encode($agent['actions'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
let systemData = null;

// Tipos de condições disponíveis (será atualizado com dados do sistema)
let conditionTypes = {};
let actionTypes = {};

// Carregar dados do sistema
async function loadSystemData() {
    try {
        const response = await fetch('/kanban-agents/system-data');
        const result = await response.json();
        
        if (result.success && result.data) {
            systemData = result.data;
            
            // Definir tipos de condições com dados reais
            conditionTypes = {
                'conversation_status': { 
                    label: 'Status da Conversa', 
                    operators: ['equals', 'not_equals'], 
                    valueType: 'select', 
                    options: systemData.conversation_statuses || ['open', 'closed', 'resolved', 'pending', 'spam']
                },
                'conversation_priority': { 
                    label: 'Prioridade', 
                    operators: ['equals', 'not_equals'], 
                    valueType: 'select', 
                    options: systemData.priorities || ['low', 'normal', 'medium', 'high', 'urgent']
                },
                'conversation_channel': {
                    label: 'Canal',
                    operators: ['equals', 'not_equals'],
                    valueType: 'select',
                    options: Object.keys(systemData.channels || {}),
                    optionLabels: systemData.channels || {}
                },
                'conversation_funnel': {
                    label: 'Funil',
                    operators: ['equals', 'not_equals'],
                    valueType: 'select',
                    options: (systemData.funnels || []).map(f => f.id.toString()),
                    optionLabels: (systemData.funnels || []).reduce((acc, f) => { acc[f.id] = f.name; return acc; }, {})
                },
                'conversation_stage': {
                    label: 'Etapa',
                    operators: ['equals', 'not_equals'],
                    valueType: 'select',
                    options: getAllStagesIds(),
                    optionLabels: getAllStagesLabels()
                },
                'conversation_assigned': {
                    label: 'Atribuída a Agente',
                    operators: ['equals', 'not_equals', 'is_empty'],
                    valueType: 'select',
                    options: (systemData.agents || []).map(a => a.id.toString()),
                    optionLabels: (systemData.agents || []).reduce((acc, a) => { acc[a.id] = a.name; return acc; }, {})
                },
                'conversation_department': {
                    label: 'Setor',
                    operators: ['equals', 'not_equals'],
                    valueType: 'select',
                    options: (systemData.departments || []).map(d => d.id.toString()),
                    optionLabels: (systemData.departments || []).reduce((acc, d) => { acc[d.id] = d.name; return acc; }, {})
                },
                'has_tag': {
                    label: 'Tem Tag',
                    operators: ['equals', 'not_equals'],
                    valueType: 'select',
                    options: (systemData.tags || []).map(t => t.id.toString()),
                    optionLabels: (systemData.tags || []).reduce((acc, t) => { acc[t.id] = t.name; return acc; }, {})
                },
                'last_message_hours': { 
                    label: 'Horas desde Última Mensagem', 
                    operators: ['greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'], 
                    valueType: 'number' 
                },
                'last_message_from': { 
                    label: 'Última Mensagem de', 
                    operators: ['equals', 'not_equals'], 
                    valueType: 'select', 
                    options: ['contact', 'agent', 'system'] 
                },
                'stage_duration_hours': { 
                    label: 'Tempo no Estágio (horas)', 
                    operators: ['greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'], 
                    valueType: 'number' 
                },
                'conversation_age_hours': {
                    label: 'Idade da Conversa (horas)',
                    operators: ['greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'],
                    valueType: 'number'
                },
                'message_count': {
                    label: 'Total de Mensagens',
                    operators: ['greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'],
                    valueType: 'number'
                },
                'ai_analysis_score': { 
                    label: 'Score de Análise IA', 
                    operators: ['greater_than', 'less_than', 'greater_or_equal', 'less_or_equal'], 
                    valueType: 'number', 
                    min: 0, 
                    max: 100 
                },
                'ai_sentiment': { 
                    label: 'Sentimento IA', 
                    operators: ['equals', 'not_equals'], 
                    valueType: 'select', 
                    options: ['positive', 'neutral', 'negative'] 
                },
                'ai_urgency': { 
                    label: 'Urgência IA', 
                    operators: ['equals', 'not_equals'], 
                    valueType: 'select', 
                    options: ['low', 'medium', 'high'] 
                }
            };
            
            // Definir tipos de ações com dados reais
            actionTypes = {
                'send_followup_message': { 
                    label: 'Enviar Mensagem de Followup', 
                    icon: 'ki-message-text-2' 
                },
                'move_to_stage': { 
                    label: 'Mover para Etapa', 
                    icon: 'ki-arrow-right',
                    requiresConfig: true
                },
                'move_to_next_stage': { 
                    label: 'Mover para Próxima Etapa', 
                    icon: 'ki-arrow-right' 
                },
                'assign_to_agent': { 
                    label: 'Atribuir a Agente', 
                    icon: 'ki-user',
                    requiresConfig: true
                },
                'assign_to_department': {
                    label: 'Atribuir a Setor',
                    icon: 'ki-briefcase',
                    requiresConfig: true
                },
                'add_tag': { 
                    label: 'Adicionar Tag', 
                    icon: 'ki-tag',
                    requiresConfig: true
                },
                'remove_tag': {
                    label: 'Remover Tag',
                    icon: 'ki-cross',
                    requiresConfig: true
                },
                'change_priority': {
                    label: 'Alterar Prioridade',
                    icon: 'ki-star',
                    requiresConfig: true
                },
                'change_status': {
                    label: 'Alterar Status',
                    icon: 'ki-check',
                    requiresConfig: true
                },
                'create_summary': { 
                    label: 'Criar Resumo', 
                    icon: 'ki-document' 
                },
                'create_note': { 
                    label: 'Criar Nota', 
                    icon: 'ki-note-edit' 
                }
            };
            
            // Recarregar condições e ações se já existirem
            if (conditions.length > 0) {
                loadConditions();
            }
            if (actions.length > 0) {
                loadActions();
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dados do sistema:', error);
        // Usar valores padrão em caso de erro
        conditionTypes = {
            'conversation_status': { label: 'Status da Conversa', operators: ['equals', 'not_equals'], valueType: 'select', options: ['open', 'closed', 'resolved'] }
        };
        actionTypes = {
            'send_followup_message': { label: 'Enviar Mensagem de Followup', icon: 'ki-message-text-2' }
        };
    }
}

// Funções auxiliares para obter todas as etapas
function getAllStagesIds() {
    if (!systemData || !systemData.stages) return [];
    const ids = [];
    Object.values(systemData.stages).forEach(stages => {
        stages.forEach(stage => {
            ids.push(stage.id.toString());
        });
    });
    return ids;
}

function getAllStagesLabels() {
    if (!systemData || !systemData.stages || !systemData.funnels) return {};
    const labels = {};
    Object.entries(systemData.stages).forEach(([funnelId, stages]) => {
        const funnel = systemData.funnels.find(f => f.id == funnelId);
        const funnelName = funnel ? funnel.name : `Funil ${funnelId}`;
        stages.forEach(stage => {
            labels[stage.id] = `${stage.name} (${funnelName})`;
        });
    });
    return labels;
}
    'move_to_next_stage': { label: 'Mover para Próxima Etapa', icon: 'ki-arrow-right' },
    'assign_to_agent': { label: 'Atribuir a Agente', icon: 'ki-user' },
    'add_tag': { label: 'Adicionar Tag', icon: 'ki-tag' },
    'create_summary': { label: 'Criar Resumo', icon: 'ki-document' },
    'create_note': { label: 'Criar Nota', icon: 'ki-note-edit' }
};

// Carregar condições existentes
function loadConditions() {
    const container = document.getElementById('conditions_list');
    container.innerHTML = '';
    
    if (conditions.length === 0) {
        container.innerHTML = '<div class="text-muted text-center py-5">Nenhuma condição configurada. Todas as conversas serão analisadas.</div>';
        return;
    }
    
    conditions.forEach((condition, index) => {
        addConditionUI(condition, index);
    });
}

// Adicionar UI de condição
function addConditionUI(condition = null, index = null) {
    const container = document.getElementById('conditions_list');
    const conditionIndex = index !== null ? index : conditions.length;
    
    const conditionData = condition || { type: '', operator: '', value: '' };
    const typeConfig = conditionTypes[conditionData.type] || {};
    
    const conditionHTML = `
        <div class="card mb-3 condition-item" data-index="${conditionIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select condition-type" onchange="updateConditionType(this, ${conditionIndex})">
                            <option value="">Selecione...</option>
                            ${Object.keys(conditionTypes).map(key => 
                                `<option value="${key}" ${conditionData.type === key ? 'selected' : ''}>${conditionTypes[key].label}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Operador</label>
                        <select class="form-select condition-operator" ${!conditionData.type ? 'disabled' : ''}>
                            ${typeConfig.operators ? typeConfig.operators.map(op => 
                                `<option value="${op}" ${conditionData.operator === op ? 'selected' : ''}>${getOperatorLabel(op)}</option>`
                            ).join('') : '<option value="">Selecione tipo primeiro</option>'}
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        ${getConditionValueInput(conditionData, typeConfig)}
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-sm btn-light-danger w-100" onclick="removeCondition(${conditionIndex})">
                            <i class="ki-duotone ki-trash fs-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    if (index !== null) {
        const existingItem = container.querySelector(`[data-index="${index}"]`);
        if (existingItem) {
            existingItem.outerHTML = conditionHTML;
        } else {
            container.insertAdjacentHTML('beforeend', conditionHTML);
        }
    } else {
        container.insertAdjacentHTML('beforeend', conditionHTML);
    }
}

// Obter input de valor baseado no tipo
function getConditionValueInput(conditionData, typeConfig) {
    if (typeConfig.valueType === 'select') {
        const options = typeConfig.options || [];
        const optionLabels = typeConfig.optionLabels || {};
        
        // Se tem optionLabels, usar eles, senão usar o próprio valor como label
        const optionsHTML = options.map(opt => {
            const label = optionLabels[opt] || opt;
            return `<option value="${opt}" ${conditionData.value === opt ? 'selected' : ''}>${label}</option>`;
        }).join('');
        
        return `
            <select class="form-select condition-value">
                <option value="">Selecione...</option>
                ${optionsHTML}
            </select>
        `;
    } else if (typeConfig.valueType === 'number') {
        return `
            <input type="number" class="form-control condition-value" 
                   value="${conditionData.value || ''}" 
                   ${typeConfig.min !== undefined ? `min="${typeConfig.min}"` : ''}
                   ${typeConfig.max !== undefined ? `max="${typeConfig.max}"` : ''}
                   placeholder="Digite o valor...">
        `;
    }
    return `<input type="text" class="form-control condition-value" value="${conditionData.value || ''}" placeholder="Digite o valor...">`;
}

// Atualizar tipo de condição
function updateConditionType(select, index) {
    const type = select.value;
    const typeConfig = conditionTypes[type] || {};
    const conditionItem = select.closest('.condition-item');
    const operatorSelect = conditionItem.querySelector('.condition-operator');
    const valueContainer = conditionItem.querySelector('.col-md-4:last-of-type');
    
    // Atualizar operadores
    operatorSelect.innerHTML = typeConfig.operators ? 
        typeConfig.operators.map(op => `<option value="${op}">${getOperatorLabel(op)}</option>`).join('') :
        '<option value="">Selecione tipo primeiro</option>';
    operatorSelect.disabled = !type;
    
    // Atualizar valor
    const conditionData = conditions[index] || {};
    conditionData.type = type;
    conditionData.operator = typeConfig.operators ? typeConfig.operators[0] : '';
    conditionData.value = '';
    valueContainer.innerHTML = `<label class="form-label">Valor</label>${getConditionValueInput(conditionData, typeConfig)}`;
    
    // Atualizar array
    if (!conditions[index]) {
        conditions[index] = conditionData;
    } else {
        conditions[index].type = type;
        conditions[index].operator = typeConfig.operators ? typeConfig.operators[0] : '';
        conditions[index].value = '';
    }
}

// Obter label do operador
function getOperatorLabel(operator) {
    const labels = {
        'equals': 'Igual a',
        'not_equals': 'Diferente de',
        'greater_than': 'Maior que',
        'less_than': 'Menor que',
        'greater_or_equal': 'Maior ou igual',
        'less_or_equal': 'Menor ou igual',
        'includes': 'Contém',
        'not_includes': 'Não contém'
    };
    return labels[operator] || operator;
}

// Adicionar condição
function addCondition() {
    conditions.push({ type: '', operator: '', value: '' });
    addConditionUI(null, conditions.length - 1);
}

// Remover condição
function removeCondition(index) {
    conditions.splice(index, 1);
    loadConditions();
}

// Carregar ações existentes
function loadActions() {
    const container = document.getElementById('actions_list');
    container.innerHTML = '';
    
    if (actions.length === 0) {
        container.innerHTML = '<div class="text-muted text-center py-5">Nenhuma ação configurada.</div>';
        return;
    }
    
    actions.forEach((action, index) => {
        addActionUI(action, index);
    });
}

// Adicionar UI de ação
function addActionUI(action = null, index = null) {
    const container = document.getElementById('actions_list');
    const actionIndex = index !== null ? index : actions.length;
    
    const actionData = action || { type: '', enabled: true, config: {} };
    const typeConfig = actionTypes[actionData.type] || { label: 'Desconhecido', icon: 'ki-gear' };
    
    const actionHTML = `
        <div class="card mb-3 action-item" data-index="${actionIndex}">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input action-enabled" type="checkbox" ${actionData.enabled !== false ? 'checked' : ''}>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de Ação</label>
                        <select class="form-select action-type" onchange="updateActionType(this, ${actionIndex})">
                            <option value="">Selecione...</option>
                            ${Object.keys(actionTypes).map(key => 
                                `<option value="${key}" ${actionData.type === key ? 'selected' : ''}>${actionTypes[key].label}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-md-6" id="action_config_${actionIndex}">
                        ${getActionConfigHTML(actionData, actionIndex)}
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-sm btn-light-danger w-100" onclick="removeAction(${actionIndex})">
                            <i class="ki-duotone ki-trash fs-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    if (index !== null) {
        const existingItem = container.querySelector(`[data-index="${index}"]`);
        if (existingItem) {
            existingItem.outerHTML = actionHTML;
        } else {
            container.insertAdjacentHTML('beforeend', actionHTML);
        }
    } else {
        container.insertAdjacentHTML('beforeend', actionHTML);
    }
}

// Obter HTML de configuração da ação
function getActionConfigHTML(actionData, index) {
    const type = actionData.type;
    const config = actionData.config || {};
    
    if (!systemData) {
        return '<div class="text-muted">Carregando dados do sistema...</div>';
    }
    
    switch(type) {
        case 'send_followup_message':
            return `
                <label class="form-label">Configuração</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="use_ai_${index}" ${config.use_ai_generated ? 'checked' : ''} onchange="toggleTemplate(${index}, this.checked)">
                    <label class="form-check-label" for="use_ai_${index}">Gerar mensagem com IA</label>
                </div>
                <textarea class="form-control action-config-template" rows="2" placeholder="Template da mensagem..." ${config.use_ai_generated ? 'disabled' : ''}>${config.template || ''}</textarea>
            `;
        case 'move_to_stage':
            const stagesOptions = getAllStagesLabels();
            const stagesSelect = Object.entries(stagesOptions).map(([id, label]) => 
                `<option value="${id}" ${config.stage_id == id ? 'selected' : ''}>${label}</option>`
            ).join('');
            return `
                <label class="form-label">Etapa</label>
                <select class="form-select action-config-stage_id">
                    <option value="">Selecione uma etapa...</option>
                    ${stagesSelect}
                </select>
            `;
        case 'assign_to_agent':
            const agentsOptions = (systemData.agents || []).map(a => 
                `<option value="${a.id}" ${config.agent_id == a.id ? 'selected' : ''}>${a.name}</option>`
            ).join('');
            return `
                <label class="form-label">Agente</label>
                <select class="form-select action-config-agent_id">
                    <option value="">Selecione um agente...</option>
                    ${agentsOptions}
                </select>
                <div class="form-text mt-1">Ou deixe vazio para usar método de distribuição automática</div>
            `;
        case 'assign_to_department':
            const departmentsOptions = (systemData.departments || []).map(d => 
                `<option value="${d.id}" ${config.department_id == d.id ? 'selected' : ''}>${d.name}</option>`
            ).join('');
            return `
                <label class="form-label">Setor</label>
                <select class="form-select action-config-department_id">
                    <option value="">Selecione um setor...</option>
                    ${departmentsOptions}
                </select>
            `;
        case 'add_tag':
        case 'remove_tag':
            const tagsOptions = (systemData.tags || []).map(t => 
                `<option value="${t.id}" ${(Array.isArray(config.tag_ids) && config.tag_ids.includes(t.id.toString())) ? 'selected' : ''}>${t.name}</option>`
            ).join('');
            return `
                <label class="form-label">Tags</label>
                <select class="form-select action-config-tag_ids" multiple size="5">
                    ${tagsOptions}
                </select>
                <div class="form-text mt-1">Segure Ctrl/Cmd para selecionar múltiplas tags</div>
            `;
        case 'change_priority':
            const prioritiesOptions = (systemData.priorities || []).map(p => 
                `<option value="${p}" ${config.priority === p ? 'selected' : ''}>${p}</option>`
            ).join('');
            return `
                <label class="form-label">Prioridade</label>
                <select class="form-select action-config-priority">
                    <option value="">Selecione uma prioridade...</option>
                    ${prioritiesOptions}
                </select>
            `;
        case 'change_status':
            const statusesOptions = (systemData.conversation_statuses || []).map(s => 
                `<option value="${s}" ${config.status === s ? 'selected' : ''}>${s}</option>`
            ).join('');
            return `
                <label class="form-label">Status</label>
                <select class="form-select action-config-status">
                    <option value="">Selecione um status...</option>
                    ${statusesOptions}
                </select>
            `;
        case 'create_note':
            return `
                <label class="form-label">Conteúdo da Nota</label>
                <textarea class="form-control action-config-note" rows="2" placeholder="Conteúdo da nota...">${config.note || ''}</textarea>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="is_internal_${index}" ${config.is_internal !== false ? 'checked' : ''}>
                    <label class="form-check-label" for="is_internal_${index}">Nota interna</label>
                </div>
            `;
        default:
            return '<div class="text-muted">Selecione um tipo de ação</div>';
    }
}

// Atualizar tipo de ação
function updateActionType(select, index) {
    const type = select.value;
    const actionItem = select.closest('.action-item');
    const configContainer = actionItem.querySelector(`#action_config_${index}`);
    
    if (!actions[index]) {
        actions[index] = { type: '', enabled: true, config: {} };
    }
    
    actions[index].type = type;
    actions[index].config = {};
    
    configContainer.innerHTML = getActionConfigHTML(actions[index], index);
}

// Toggle template quando usar IA
function toggleTemplate(index, useAI) {
    const actionItem = document.querySelector(`.action-item[data-index="${index}"]`);
    if (actionItem) {
        const textarea = actionItem.querySelector('.action-config-template');
        if (textarea) {
            textarea.disabled = useAI;
            if (useAI) {
                textarea.value = '';
            }
        }
    }
}

// Adicionar ação
function addAction() {
    actions.push({ type: '', enabled: true, config: {} });
    addActionUI(null, actions.length - 1);
}

// Remover ação
function removeAction(index) {
    actions.splice(index, 1);
    loadActions();
}

// Testar condições
function testConditions() {
    Swal.fire({
        title: 'Testar Condições',
        text: 'Selecione uma conversa para testar as condições configuradas',
        input: 'number',
        inputLabel: 'ID da Conversa',
        inputPlaceholder: 'Digite o ID da conversa...',
        showCancelButton: true,
        confirmButtonText: 'Testar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const conversationId = parseInt(result.value);
            if (!conversationId) {
                Swal.fire('Erro!', 'ID da conversa inválido', 'error');
                return;
            }
            
            // Coletar condições do formulário
            collectConditions();
            
            fetch(`/kanban-agents/<?= $agent['id'] ?>/test-conditions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    conversation_id: conversationId,
                    conditions: {
                        operator: document.getElementById('conditions_operator').value,
                        conditions: conditions
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = `Condições ${data.result ? 'ATENDIDAS' : 'NÃO ATENDIDAS'}\n\n`;
                    if (data.details) {
                        message += 'Detalhes:\n';
                        data.details.forEach((detail, idx) => {
                            message += `${idx + 1}. ${detail.condition.type}: ${detail.result ? '✓' : '✗'}\n`;
                        });
                    }
                    Swal.fire({
                        title: data.result ? 'Sucesso!' : 'Atenção',
                        text: message,
                        icon: data.result ? 'success' : 'warning',
                        width: 600
                    });
                } else {
                    Swal.fire('Erro!', data.message || 'Erro ao testar condições', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Erro!', 'Erro ao testar condições', 'error');
            });
        }
    });
}

// Coletar condições do formulário
function collectConditions() {
    conditions = [];
    document.querySelectorAll('.condition-item').forEach((item, index) => {
        const type = item.querySelector('.condition-type').value;
        const operator = item.querySelector('.condition-operator').value;
        const valueInput = item.querySelector('.condition-value');
        const value = valueInput ? valueInput.value : '';
        
        if (type && operator && value !== '') {
            conditions.push({ type, operator, value });
        }
    });
}

// Coletar ações do formulário
function collectActions() {
    actions = [];
    document.querySelectorAll('.action-item').forEach((item, index) => {
        const enabled = item.querySelector('.action-enabled').checked;
        const type = item.querySelector('.action-type').value;
        
        if (!type) return;
        
        const config = {};
        const configInputs = item.querySelectorAll('[class*="action-config-"]');
        
        configInputs.forEach(input => {
            const className = input.className;
            if (className.includes('action-config-template')) {
                config.template = input.value;
            } else if (className.includes('action-config-use_ai')) {
                config.use_ai_generated = input.checked;
            } else if (className.includes('action-config-stage_id')) {
                config.stage_id = parseInt(input.value) || null;
            } else if (className.includes('action-config-agent_id')) {
                config.agent_id = parseInt(input.value) || null;
            } else if (className.includes('action-config-department_id')) {
                config.department_id = parseInt(input.value) || null;
            } else if (className.includes('action-config-tag_ids')) {
                config.tag_ids = Array.from(input.selectedOptions).map(opt => opt.value);
            } else if (className.includes('action-config-priority')) {
                config.priority = input.value;
            } else if (className.includes('action-config-status')) {
                config.status = input.value;
            } else if (className.includes('action-config-method')) {
                config.method = input.value;
            } else if (className.includes('action-config-note')) {
                config.note = input.value;
            } else if (className.includes('action-config-is_internal')) {
                config.is_internal = input.checked;
            }
        });
        
        // Configurações específicas por tipo
        if (type === 'send_followup_message') {
            const useAI = item.querySelector(`#use_ai_${index}`);
            if (useAI) {
                config.use_ai_generated = useAI.checked;
            }
        }
        if (type === 'create_note') {
            const isInternal = item.querySelector(`#is_internal_${index}`);
            if (isInternal) {
                config.is_internal = isInternal.checked;
            }
        }
        
        actions.push({ type, enabled, config });
    });
}

// Inicializar ao carregar página
document.addEventListener('DOMContentLoaded', async function() {
    await loadSystemData();
    loadConditions();
    loadActions();
});

// Atualizar condições quando operador mudar
document.getElementById('conditions_operator')?.addEventListener('change', function() {
    // Operador já está sendo coletado no collectConditions
});

document.getElementById('kt_form_kanban_agent').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Coletar condições e ações
    collectConditions();
    collectActions();
    
    const formData = new FormData(this);
    const data = {};
    
    // Processar campos normais
    for (let [key, value] of formData.entries()) {
        if (key.endsWith('[]')) {
            const realKey = key.replace('[]', '');
            if (!data[realKey]) {
                data[realKey] = [];
            }
            data[realKey].push(value);
        } else {
            data[key] = value;
        }
    }
    
    // Converter arrays vazios para null
    if (data.target_funnel_ids && data.target_funnel_ids.length === 0) {
        data.target_funnel_ids = null;
    }
    if (data.target_stage_ids && data.target_stage_ids.length === 0) {
        data.target_stage_ids = null;
    }
    
    // Processar agendamento
    if (data.execution_type === 'schedule') {
        const selectedDays = [];
        for (let i = 0; i < 7; i++) {
            const checkbox = document.getElementById(`schedule_day_${i}`);
            if (checkbox && checkbox.checked) {
                selectedDays.push(i);
            }
        }
        const time = document.getElementById('schedule_time').value;
        data.execution_schedule = { days: selectedDays, time: time };
    }
    
    data.enabled = document.getElementById('enabled').checked;
    data.conditions = JSON.stringify({
        operator: document.getElementById('conditions_operator').value,
        conditions: conditions
    });
    data.actions = JSON.stringify(actions);
    
    fetch('/kanban-agents/<?= $agent['id'] ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Sucesso!', data.message, 'success').then(() => {
                window.location.href = '/kanban-agents/<?= $agent['id'] ?>';
            });
        } else {
            Swal.fire('Erro!', data.message || 'Erro ao atualizar agente', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Erro!', 'Erro ao atualizar agente', 'error');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>

