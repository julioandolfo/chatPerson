<?php
/**
 * Aba de Configurações do Assistente IA
 */
use App\Services\AIAssistantFeatureService;
use App\Models\AIAgent;
use App\Models\AIAssistantFeatureAgent;

$features = $aiAssistantFeatures ?? [];
$agents = $aiAgents ?? [];
$funnels = $funnels ?? [];
$allStages = $allStages ?? [];
?>

<div class="mb-10">
    <h3 class="fw-bold mb-5">Funcionalidades do Assistente IA</h3>
    <p class="text-muted mb-7">Configure e gerencie as funcionalidades disponíveis no Assistente IA do chat.</p>
    
    <div class="table-responsive">
        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
            <thead>
                <tr class="fw-bold text-muted">
                    <th class="min-w-150px">Funcionalidade</th>
                    <th class="min-w-120px">Status</th>
                    <th class="min-w-150px">Agente Padrão</th>
                    <th class="min-w-120px">Seleção Automática</th>
                    <th class="min-w-100px text-end">Ações</th>
                </tr>
            </thead>
            <tbody id="aiFeaturesTableBody">
                <?php foreach ($features as $feature): ?>
                    <?php
                    $defaultAgent = null;
                    if (!empty($feature['default_ai_agent_id'])) {
                        $defaultAgent = AIAgent::find($feature['default_ai_agent_id']);
                    }
                    $settings = json_decode($feature['settings'] ?? '{}', true);
                    ?>
                    <tr data-feature-key="<?= htmlspecialchars($feature['feature_key']) ?>">
                        <td>
                            <div class="d-flex align-items-center">
                                <i class="ki-duotone <?= htmlspecialchars($feature['icon'] ?? 'ki-abstract-26') ?> fs-2x text-primary me-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                    <span class="path4"></span>
                                </i>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($feature['name']) ?></div>
                                    <div class="text-muted fs-7"><?= htmlspecialchars($feature['description'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input feature-enabled" type="checkbox" 
                                       data-feature-key="<?= htmlspecialchars($feature['feature_key']) ?>"
                                       <?= $feature['enabled'] ? 'checked' : '' ?> />
                            </div>
                        </td>
                        <td>
                            <select class="form-select form-select-sm form-select-solid feature-default-agent" 
                                    data-feature-key="<?= htmlspecialchars($feature['feature_key']) ?>">
                                <option value="">Nenhum</option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?= $agent['id'] ?>" 
                                            <?= $feature['default_ai_agent_id'] == $agent['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($agent['name']) ?> (<?= htmlspecialchars($agent['agent_type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <div class="form-check form-switch form-check-custom form-check-solid">
                                <input class="form-check-input feature-auto-select" type="checkbox" 
                                       data-feature-key="<?= htmlspecialchars($feature['feature_key']) ?>"
                                       <?= $feature['auto_select_agent'] ? 'checked' : '' ?> />
                            </div>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-light-primary feature-config-btn" 
                                    data-feature-key="<?= htmlspecialchars($feature['feature_key']) ?>"
                                    onclick="showFeatureConfig('<?= htmlspecialchars($feature['feature_key']) ?>')">
                                <i class="ki-duotone ki-setting-3 fs-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                    <span class="path3"></span>
                                </i>
                                Configurar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="separator separator-dashed my-10"></div>

<div class="mb-10">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h3 class="fw-bold m-0">Logs Recentes</h3>
        <button class="btn btn-sm btn-light-primary" onclick="loadAILogs()">
            <i class="ki-duotone ki-arrows-circle fs-5">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Atualizar
        </button>
    </div>
    <div id="aiLogsContainer" class="card">
        <div class="card-body">
            <div class="text-center py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div class="text-muted">Carregando logs...</div>
            </div>
        </div>
    </div>
</div>

<div class="separator separator-dashed my-10"></div>

<div class="mb-10">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h3 class="fw-bold m-0">Estatísticas de Uso</h3>
        <div class="d-flex align-items-center gap-3">
            <select id="ai_stats_period" class="form-select form-select-sm" style="width: auto;" onchange="loadAIStats()">
                <option value="7">Últimos 7 dias</option>
                <option value="30" selected>Últimos 30 dias</option>
                <option value="60">Últimos 60 dias</option>
                <option value="90">Últimos 90 dias</option>
            </select>
            <button class="btn btn-sm btn-light-primary" onclick="loadAIStats()">
                <i class="ki-duotone ki-arrows-circle fs-5">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Atualizar
            </button>
        </div>
    </div>
    <div id="aiStatsContainer" class="card">
        <div class="card-body">
            <div class="text-center py-10">
                <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
                <div class="text-muted">Carregando estatísticas...</div>
            </div>
        </div>
    </div>
</div>

<div class="separator separator-dashed my-10"></div>

<div class="mb-10">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <h3 class="fw-bold m-0">Regras de Seleção de Agentes</h3>
        <button class="btn btn-primary" onclick="showAddRuleModal()">
            <i class="ki-duotone ki-plus fs-2">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            Adicionar Regra
        </button>
    </div>
    <p class="text-muted mb-7">Configure regras para seleção automática de agentes baseadas em contexto (canal, tags, sentimento, etc).</p>
    
    <div id="aiRulesContainer">
        <!-- Regras serão carregadas via AJAX -->
        <div class="text-center py-10">
            <span class="spinner-border spinner-border-sm text-primary mb-3" role="status"></span>
            <div class="text-muted">Carregando regras...</div>
        </div>
    </div>
</div>

<!-- MODAL: Configurar Funcionalidade -->
<div class="modal fade" id="kt_modal_feature_config" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold" id="featureConfigTitle">Configurar Funcionalidade</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body" id="featureConfigBody">
                <!-- Conteúdo será preenchido dinamicamente -->
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Adicionar Regra -->
<div class="modal fade" id="kt_modal_add_rule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Adicionar Regra de Seleção</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <form id="kt_add_rule_form">
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Funcionalidade</label>
                        <select name="feature_key" class="form-select form-select-solid" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($features as $feature): ?>
                                <option value="<?= htmlspecialchars($feature['feature_key']) ?>">
                                    <?= htmlspecialchars($feature['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Agente de IA</label>
                        <select name="ai_agent_id" class="form-select form-select-solid" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?= $agent['id'] ?>">
                                    <?= htmlspecialchars($agent['name']) ?> (<?= htmlspecialchars($agent['agent_type']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="required fw-semibold fs-6 mb-2">Prioridade</label>
                        <input type="number" name="priority" class="form-control form-control-solid" 
                               value="0" min="0" max="100" required />
                        <div class="form-text">Maior número = maior prioridade. Regras com maior prioridade são verificadas primeiro.</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Condições</label>
                        <div class="card card-flush">
                            <div class="card-body">
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Canal</label>
                                        <select name="conditions[channel]" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <option value="whatsapp">WhatsApp</option>
                                            <option value="email">Email</option>
                                            <option value="chat">Chat</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Sentimento</label>
                                        <select name="conditions[sentiment]" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <option value="positive">Positivo</option>
                                            <option value="neutral">Neutro</option>
                                            <option value="negative">Negativo</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Urgência</label>
                                        <select name="conditions[urgency]" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <option value="low">Baixa</option>
                                            <option value="medium">Média</option>
                                            <option value="high">Alta</option>
                                            <option value="critical">Crítica</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Status</label>
                                        <select name="conditions[status]" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <option value="open">Aberta</option>
                                            <option value="pending">Pendente</option>
                                            <option value="resolved">Resolvida</option>
                                            <option value="closed">Fechada</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-5">
                                    <div class="col-md-6">
                                        <label class="form-label">Funil</label>
                                        <select name="conditions[funnel_id]" id="rule_funnel_id" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <?php foreach ($funnels as $funnel): ?>
                                                <option value="<?= $funnel['id'] ?>"><?= htmlspecialchars($funnel['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Etapa/Estágio</label>
                                        <select name="conditions[funnel_stage_id]" id="rule_funnel_stage_id" class="form-select form-select-sm">
                                            <option value="">Qualquer</option>
                                            <?php foreach ($allStages as $stage): ?>
                                                <option value="<?= $stage['id'] ?>" data-funnel-id="<?= $stage['funnel_id'] ?>">
                                                    <?= htmlspecialchars($stage['funnel_name'] . ' - ' . $stage['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Filtra automaticamente quando um funil é selecionado</div>
                                    </div>
                                </div>
                                <div class="fv-row">
                                    <label class="form-label">Tags (separadas por vírgula)</label>
                                    <input type="text" name="conditions[tags]" class="form-control form-control-sm" 
                                           placeholder="vendas, cliente-vip" />
                                    <div class="form-text">Deixe vazio para qualquer tag. Use vírgula para múltiplas tags.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar Regra</span>
                            <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filtrar etapas quando funil é selecionado
    const funnelSelect = document.getElementById('rule_funnel_id');
    const stageSelect = document.getElementById('rule_funnel_stage_id');
    
    if (funnelSelect && stageSelect) {
        funnelSelect.addEventListener('change', function() {
            const selectedFunnelId = this.value;
            const options = stageSelect.querySelectorAll('option');
            
            // Mostrar todas as opções se nenhum funil selecionado
            if (!selectedFunnelId) {
                options.forEach(opt => {
                    opt.style.display = '';
                });
                return;
            }
            
            // Filtrar etapas do funil selecionado
            options.forEach(opt => {
                if (opt.value === '') {
                    // Sempre mostrar opção "Qualquer"
                    opt.style.display = '';
                } else {
                    const funnelId = opt.getAttribute('data-funnel-id');
                    opt.style.display = (funnelId === selectedFunnelId) ? '' : 'none';
                }
            });
            
            // Resetar seleção de etapa se não pertence ao funil selecionado
            const selectedStageFunnelId = stageSelect.options[stageSelect.selectedIndex]?.getAttribute('data-funnel-id');
            if (selectedStageFunnelId && selectedStageFunnelId !== selectedFunnelId) {
                stageSelect.value = '';
            }
        });
        
        // Trigger inicial se já houver um funil selecionado
        if (funnelSelect.value) {
            funnelSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // Carregar regras, estatísticas e logs
    loadAIRules();
    loadAIStats();
    loadAILogs();
    
    // Event listeners para mudanças nas funcionalidades
    document.querySelectorAll('.feature-enabled').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateFeatureSetting(this.dataset.featureKey, 'enabled', this.checked);
        });
    });
    
    document.querySelectorAll('.feature-auto-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateFeatureSetting(this.dataset.featureKey, 'auto_select_agent', this.checked);
        });
    });
    
    document.querySelectorAll('.feature-default-agent').forEach(select => {
        select.addEventListener('change', function() {
            updateFeatureSetting(this.dataset.featureKey, 'default_ai_agent_id', this.value || null);
        });
    });
    
    // Form de adicionar regra
    const addRuleForm = document.getElementById('kt_add_rule_form');
    if (addRuleForm) {
        addRuleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            saveAIRule(new FormData(this));
        });
    }
});

function updateFeatureSetting(featureKey, field, value) {
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/features') ?>/' + featureKey, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            [field]: value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Sucesso silencioso
        } else {
            alert('Erro ao atualizar: ' + (data.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        alert('Erro ao atualizar configuração');
        console.error(error);
    });
}

function loadAIRules() {
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/rules') ?>', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('aiRulesContainer');
        if (data.success && data.rules) {
            renderAIRules(data.rules);
        } else {
            container.innerHTML = '<div class="text-muted text-center py-10">Nenhuma regra configurada</div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar regras:', error);
        document.getElementById('aiRulesContainer').innerHTML = '<div class="alert alert-danger">Erro ao carregar regras</div>';
    });
}

function renderAIRules(rules) {
    const container = document.getElementById('aiRulesContainer');
    if (rules.length === 0) {
        container.innerHTML = '<div class="text-muted text-center py-10">Nenhuma regra configurada</div>';
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3"><thead><tr class="fw-bold text-muted"><th>Funcionalidade</th><th>Agente</th><th>Prioridade</th><th>Condições</th><th class="text-end">Ações</th></tr></thead><tbody>';
    
    rules.forEach(rule => {
        const conditions = JSON.parse(rule.conditions || '{}');
        let conditionsText = [];
        if (conditions.channel) conditionsText.push(`Canal: ${conditions.channel}`);
        if (conditions.sentiment) conditionsText.push(`Sentimento: ${conditions.sentiment}`);
        if (conditions.urgency) conditionsText.push(`Urgência: ${conditions.urgency}`);
        if (conditions.status) conditionsText.push(`Status: ${conditions.status}`);
        if (conditions.funnel_id) {
            // Tentar obter nome do funil do select ou usar ID
            const funnelOption = document.querySelector(`#rule_funnel_id option[value="${conditions.funnel_id}"]`);
            const funnelName = funnelOption ? funnelOption.textContent : `Funil ${conditions.funnel_id}`;
            conditionsText.push(`Funil: ${funnelName}`);
        }
        if (conditions.funnel_stage_id) {
            // Tentar obter nome da etapa do select ou usar ID
            const stageOption = document.querySelector(`#rule_funnel_stage_id option[value="${conditions.funnel_stage_id}"]`);
            const stageName = stageOption ? stageOption.textContent : `Etapa ${conditions.funnel_stage_id}`;
            conditionsText.push(`Etapa: ${stageName}`);
        }
        if (conditions.tags && conditions.tags.length > 0) conditionsText.push(`Tags: ${conditions.tags.join(', ')}`);
        
        html += `
            <tr>
                <td>${escapeHtml(rule.feature_name || rule.feature_key)}</td>
                <td>${escapeHtml(rule.agent_name || 'N/A')}</td>
                <td><span class="badge badge-light-primary">${rule.priority || 0}</span></td>
                <td>${conditionsText.length > 0 ? conditionsText.join('<br>') : '<span class="text-muted">Sem condições</span>'}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-light-danger" onclick="deleteAIRule(${rule.id})">
                        <i class="ki-duotone ki-trash fs-5">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function showAddRuleModal() {
    const modal = new bootstrap.Modal(document.getElementById('kt_modal_add_rule'));
    modal.show();
}

function saveAIRule(formData) {
    const data = {};
    formData.forEach((value, key) => {
        if (key === 'conditions[tags]') {
            if (!data.conditions) data.conditions = {};
            data.conditions.tags = value.split(',').map(t => t.trim()).filter(t => t);
        } else if (key.startsWith('conditions[')) {
            if (!data.conditions) data.conditions = {};
            const conditionKey = key.match(/conditions\[(.*?)\]/)[1];
            if (value) data.conditions[conditionKey] = value;
        } else {
            data[key] = value;
        }
    });
    
    // Remover condições vazias
    if (data.conditions) {
        Object.keys(data.conditions).forEach(key => {
            if (!data.conditions[key] || (Array.isArray(data.conditions[key]) && data.conditions[key].length === 0)) {
                delete data.conditions[key];
            }
        });
        if (Object.keys(data.conditions).length === 0) {
            delete data.conditions;
        }
    }
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/rules') ?>', {
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
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_add_rule')).hide();
            document.getElementById('kt_add_rule_form').reset();
            loadAIRules();
        } else {
            alert('Erro: ' + (data.message || 'Erro ao salvar regra'));
        }
    })
    .catch(error => {
        alert('Erro ao salvar regra');
        console.error(error);
    });
}

function deleteAIRule(ruleId) {
    if (!confirm('Tem certeza que deseja excluir esta regra?')) return;
    
    fetch(`<?= \App\Helpers\Url::to('/ai-assistant/rules') ?>/${ruleId}`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadAIRules();
        } else {
            alert('Erro: ' + (data.message || 'Erro ao excluir regra'));
        }
    })
    .catch(error => {
        alert('Erro ao excluir regra');
        console.error(error);
    });
}

function showFeatureConfig(featureKey) {
    const allFeatures = <?= json_encode($features) ?>;
    const feature = allFeatures.find(f => f.feature_key === featureKey);
    if (!feature) {
        alert('Funcionalidade não encontrada');
        return;
    }
    
    const settings = JSON.parse(feature.settings || '{}');
    const modal = document.getElementById('kt_modal_feature_config');
    const title = document.getElementById('featureConfigTitle');
    const body = document.getElementById('featureConfigBody');
    
    title.textContent = `Configurar: ${feature.name}`;
    
    // Gerar HTML baseado no tipo de funcionalidade
    let configHtml = '';
    
    switch(featureKey) {
        case 'generate_response':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Quantidade de Sugestões</label>
                        <input type="number" name="generate_count" class="form-control form-control-solid" 
                               value="${settings.generate_count || 3}" min="1" max="5" />
                        <div class="form-text">Número de sugestões de resposta a gerar (1-5)</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tom Padrão</label>
                        <select name="default_tone" class="form-select form-select-solid">
                            <option value="professional" ${(settings.default_tone || 'professional') === 'professional' ? 'selected' : ''}>Profissional</option>
                            <option value="friendly" ${settings.default_tone === 'friendly' ? 'selected' : ''}>Amigável</option>
                            <option value="formal" ${settings.default_tone === 'formal' ? 'selected' : ''}>Formal</option>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções de Tom Disponíveis</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="tone_options[]" value="professional" class="form-check-input me-2" 
                                       ${(settings.tone_options || ['professional', 'friendly', 'formal']).includes('professional') ? 'checked' : ''} />
                                Profissional
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="tone_options[]" value="friendly" class="form-check-input me-2" 
                                       ${(settings.tone_options || ['professional', 'friendly', 'formal']).includes('friendly') ? 'checked' : ''} />
                                Amigável
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="tone_options[]" value="formal" class="form-check-input me-2" 
                                       ${(settings.tone_options || ['professional', 'friendly', 'formal']).includes('formal') ? 'checked' : ''} />
                                Formal
                            </label>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Configurações de Contexto</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_context" class="form-check-input me-2" 
                                       ${settings.include_context !== false ? 'checked' : ''} />
                                Incluir histórico completo da conversa
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_contact_info" class="form-check-input me-2" 
                                       ${settings.include_contact_info !== false ? 'checked' : ''} />
                                Incluir informações do contato
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_tags" class="form-check-input me-2" 
                                       ${settings.include_tags !== false ? 'checked' : ''} />
                                Incluir tags da conversa
                            </label>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Máximo de Mensagens no Contexto</label>
                        <input type="number" name="max_context_messages" class="form-control form-control-solid" 
                               value="${settings.max_context_messages || 20}" min="5" max="50" />
                        <div class="form-text">Número máximo de mensagens anteriores a incluir no contexto</div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Salvar Configurações</span>
                            <span class="indicator-progress">Aguarde...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            `;
            break;
            
        case 'summarize':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tamanho do Resumo</label>
                        <select name="summary_length" class="form-select form-select-solid">
                            <option value="short" ${settings.summary_length === 'short' ? 'selected' : ''}>Curto</option>
                            <option value="medium" ${(settings.summary_length || 'medium') === 'medium' ? 'selected' : ''}>Médio</option>
                            <option value="long" ${settings.summary_length === 'long' ? 'selected' : ''}>Longo</option>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Conteúdo do Resumo</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_key_points" class="form-check-input me-2" 
                                       ${settings.include_key_points !== false ? 'checked' : ''} />
                                Incluir pontos principais
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_action_items" class="form-check-input me-2" 
                                       ${settings.include_action_items !== false ? 'checked' : ''} />
                                Incluir itens de ação
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_sentiment" class="form-check-input me-2" 
                                       ${settings.include_sentiment !== false ? 'checked' : ''} />
                                Incluir análise de sentimento
                            </label>
                        </div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tamanho Máximo (caracteres)</label>
                        <input type="number" name="max_length" class="form-control form-control-solid" 
                               value="${settings.max_length || 500}" min="100" max="2000" />
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'suggest_tags':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Máximo de Tags</label>
                        <input type="number" name="max_tags" class="form-control form-control-solid" 
                               value="${settings.max_tags || 5}" min="1" max="10" />
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Limiar de Confiança</label>
                        <input type="number" name="confidence_threshold" class="form-control form-control-solid" 
                               value="${settings.confidence_threshold || 0.7}" min="0" max="1" step="0.1" />
                        <div class="form-text">Valor entre 0 e 1. Tags com confiança menor que este valor não serão sugeridas.</div>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="use_existing_tags" class="form-check-input me-2" 
                                       ${settings.use_existing_tags !== false ? 'checked' : ''} />
                                Sugerir apenas tags existentes
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="create_new_tags" class="form-check-input me-2" 
                                       ${settings.create_new_tags === true ? 'checked' : ''} />
                                Permitir criar novas tags
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'analyze_sentiment':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções de Análise</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="detect_emotions" class="form-check-input me-2" 
                                       ${settings.detect_emotions !== false ? 'checked' : ''} />
                                Detectar emoções específicas
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="track_changes" class="form-check-input me-2" 
                                       ${settings.track_changes !== false ? 'checked' : ''} />
                                Rastrear mudanças de sentimento
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="alert_negative" class="form-check-input me-2" 
                                       ${settings.alert_negative === true ? 'checked' : ''} />
                                Alertar quando sentimento for negativo
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="alert_positive" class="form-check-input me-2" 
                                       ${settings.alert_positive === true ? 'checked' : ''} />
                                Alertar quando sentimento for positivo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'translate':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Idioma Padrão de Destino</label>
                        <select name="target_language" class="form-select form-select-solid">
                            <option value="pt-BR" ${(settings.target_language || 'pt-BR') === 'pt-BR' ? 'selected' : ''}>Português (Brasil)</option>
                            <option value="en-US" ${settings.target_language === 'en-US' ? 'selected' : ''}>Inglês (EUA)</option>
                            <option value="es-ES" ${settings.target_language === 'es-ES' ? 'selected' : ''}>Espanhol</option>
                            <option value="fr-FR" ${settings.target_language === 'fr-FR' ? 'selected' : ''}>Francês</option>
                        </select>
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="auto_detect_language" class="form-check-input me-2" 
                                       ${settings.auto_detect_language !== false ? 'checked' : ''} />
                                Detectar idioma automaticamente
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="preserve_context" class="form-check-input me-2" 
                                       ${settings.preserve_context !== false ? 'checked' : ''} />
                                Preservar contexto na tradução
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="translate_attachments" class="form-check-input me-2" 
                                       ${settings.translate_attachments === true ? 'checked' : ''} />
                                Traduzir anexos (quando possível)
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'improve_grammar':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Opções de Melhoria</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="fix_spelling" class="form-check-input me-2" 
                                       ${settings.fix_spelling !== false ? 'checked' : ''} />
                                Corrigir ortografia
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="improve_clarity" class="form-check-input me-2" 
                                       ${settings.improve_clarity !== false ? 'checked' : ''} />
                                Melhorar clareza
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="suggest_synonyms" class="form-check-input me-2" 
                                       ${settings.suggest_synonyms === true ? 'checked' : ''} />
                                Sugerir sinônimos
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="maintain_tone" class="form-check-input me-2" 
                                       ${settings.maintain_tone !== false ? 'checked' : ''} />
                                Manter tom original
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'suggest_next_steps':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Máximo de Sugestões</label>
                        <input type="number" name="max_suggestions" class="form-control form-control-solid" 
                               value="${settings.max_suggestions || 5}" min="1" max="10" />
                    </div>
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Fontes de Sugestões</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_automations" class="form-check-input me-2" 
                                       ${settings.include_automations !== false ? 'checked' : ''} />
                                Incluir automações disponíveis
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="include_templates" class="form-check-input me-2" 
                                       ${settings.include_templates !== false ? 'checked' : ''} />
                                Incluir templates relevantes
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="prioritize_by_urgency" class="form-check-input me-2" 
                                       ${settings.prioritize_by_urgency !== false ? 'checked' : ''} />
                                Priorizar por urgência
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        case 'extract_info':
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    
                    <div class="fv-row mb-7">
                        <label class="fw-semibold fs-6 mb-2">Tipos de Informação a Extrair</label>
                        <div class="d-flex flex-column gap-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="extract_contact_info" class="form-check-input me-2" 
                                       ${settings.extract_contact_info !== false ? 'checked' : ''} />
                                Informações de contato (nome, email, telefone)
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="extract_dates" class="form-check-input me-2" 
                                       ${settings.extract_dates !== false ? 'checked' : ''} />
                                Datas mencionadas
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="extract_numbers" class="form-check-input me-2" 
                                       ${settings.extract_numbers !== false ? 'checked' : ''} />
                                Números importantes (valores, quantidades, IDs)
                            </label>
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="extract_keywords" class="form-check-input me-2" 
                                       ${settings.extract_keywords !== false ? 'checked' : ''} />
                                Palavras-chave e tópicos principais
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar Configurações</button>
                    </div>
                </form>
            `;
            break;
            
        default:
            configHtml = `
                <form id="kt_feature_config_form">
                    <input type="hidden" name="feature_key" value="${featureKey}">
                    <div class="alert alert-info">
                        <i class="ki-duotone ki-information-5 fs-2 me-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        Configurações específicas para esta funcionalidade serão adicionadas em breve.
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </form>
            `;
    }
    
    body.innerHTML = configHtml;
    
    // Adicionar event listener ao form
    const form = document.getElementById('kt_feature_config_form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveFeatureConfig(new FormData(this));
        });
    }
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function saveFeatureConfig(formData) {
    const data = {};
    const featureKey = formData.get('feature_key');
    
    formData.forEach((value, key) => {
        if (key === 'feature_key') return;
        
        if (key === 'tone_options[]') {
            if (!data.tone_options) data.tone_options = [];
            data.tone_options.push(value);
        } else if (key.endsWith('[]')) {
            // Array de checkboxes
            const realKey = key.replace('[]', '');
            if (!data[realKey]) data[realKey] = [];
            data[realKey].push(value);
        } else {
            // Converter strings booleanas para boolean
            if (value === 'on' || value === 'true') {
                data[key] = true;
            } else if (value === 'false') {
                data[key] = false;
            } else if (!isNaN(value) && value !== '') {
                // Número
                data[key] = parseFloat(value);
            } else {
                data[key] = value;
            }
        }
    });
    
    // Processar checkboxes que não foram marcados
    const form = document.getElementById('kt_feature_config_form');
    const checkboxes = form.querySelectorAll('input[type="checkbox"]:not([name*="[]"])');
    checkboxes.forEach(checkbox => {
        if (!checkbox.checked && !data.hasOwnProperty(checkbox.name)) {
            data[checkbox.name] = false;
        }
    });
    
    fetch(`<?= \App\Helpers\Url::to('/ai-assistant/features') ?>/${featureKey}/settings`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            settings: data
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('kt_modal_feature_config')).hide();
            alert('Configurações salvas com sucesso!');
            // Recarregar página para atualizar configurações
            location.reload();
        } else {
            alert('Erro: ' + (result.message || 'Erro ao salvar configurações'));
        }
    })
    .catch(error => {
        alert('Erro ao salvar configurações');
        console.error(error);
    });
}

function loadAILogs() {
    const container = document.getElementById('aiLogsContainer');
    if (!container) return;
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/logs') ?>?limit=20', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.logs) {
            const logs = data.logs;
            
            if (logs.length === 0) {
                container.innerHTML = '<div class="card-body"><div class="text-center text-muted py-10">Nenhum log encontrado</div></div>';
                return;
            }
            
            let html = `
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th class="min-w-150px">Data/Hora</th>
                                    <th class="min-w-120px">Usuário</th>
                                    <th class="min-w-150px">Funcionalidade</th>
                                    <th class="min-w-120px">Agente</th>
                                    <th class="text-end">Tokens</th>
                                    <th class="text-end">Custo</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            logs.forEach(log => {
                const date = new Date(log.created_at);
                const formattedDate = date.toLocaleString('pt-BR', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const successBadge = log.success 
                    ? '<span class="badge badge-success">Sucesso</span>'
                    : '<span class="badge badge-danger">Erro</span>';
                
                html += `
                    <tr>
                        <td>${escapeHtml(formattedDate)}</td>
                        <td>${escapeHtml(log.user_name || 'N/A')}</td>
                        <td>
                            <span class="badge badge-light-primary">${escapeHtml(log.feature_key || 'N/A')}</span>
                        </td>
                        <td>${escapeHtml(log.agent_name || 'N/A')}</td>
                        <td class="text-end">${(log.tokens_used || 0).toLocaleString('pt-BR')}</td>
                        <td class="text-end">R$ ${(log.cost || 0).toFixed(2).replace('.', ',')}</td>
                        <td class="text-center">${successBadge}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="card-body"><div class="alert alert-danger">Erro ao carregar logs</div></div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar logs:', error);
        container.innerHTML = '<div class="card-body"><div class="alert alert-danger">Erro ao carregar logs</div></div>';
    });
}

function loadAIStats() {
    const container = document.getElementById('aiStatsContainer');
    if (!container) return;
    
    const days = document.getElementById('ai_stats_period')?.value || 30;
    
    fetch('<?= \App\Helpers\Url::to('/ai-assistant/stats') ?>?days=' + days + '&include_charts=true', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.stats) {
            const stats = data.stats;
            const totals = stats.totals || {};
            
            let html = `
                <div class="card-body">
                    <h4 class="fw-bold mb-5">Resumo Geral (últimos ${totals.days || 30} dias)</h4>
                    <div class="row g-3 mb-7">
                        <div class="col-md-3">
                            <div class="card card-flush">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-chart-simple fs-2x text-primary me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        <div>
                                            <div class="fs-7 text-muted">Total de Usos</div>
                                            <div class="fs-2 fw-bold">${totals.total_uses || 0}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-flush">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-abstract-26 fs-2x text-success me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                        </i>
                                        <div>
                                            <div class="fs-7 text-muted">Total de Tokens</div>
                                            <div class="fs-2 fw-bold">${(totals.total_tokens || 0).toLocaleString('pt-BR')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-flush">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-dollar fs-2x text-warning me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fs-7 text-muted">Custo Total</div>
                                            <div class="fs-2 fw-bold">R$ ${(totals.total_cost || 0).toFixed(2).replace('.', ',')}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-flush">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <i class="ki-duotone ki-check-circle fs-2x text-info me-3">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        <div>
                                            <div class="fs-7 text-muted">Taxa de Sucesso</div>
                                            <div class="fs-2 fw-bold">${(totals.success_rate || 0).toFixed(1)}%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${stats.usage_over_time && stats.usage_over_time.length > 0 ? `
                    <div class="row mb-7">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="fw-bold mb-0">Uso ao Longo do Tempo</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="ai_chart_usage_over_time" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="row mb-7">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="fw-bold mb-0">Distribuição por Funcionalidade</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="ai_chart_by_feature" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                        ${stats.cost_by_model && stats.cost_by_model.length > 0 ? `
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="fw-bold mb-0">Custos por Modelo</h4>
                                </div>
                                <div class="card-body">
                                    <canvas id="ai_chart_cost_by_model" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                    
                    <h4 class="fw-bold mb-4">Por Funcionalidade</h4>
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                            <thead>
                                <tr class="fw-bold text-muted">
                                    <th>Funcionalidade</th>
                                    <th class="text-end">Usos</th>
                                    <th class="text-end">Tokens</th>
                                    <th class="text-end">Custo</th>
                                    <th class="text-end">Taxa Sucesso</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            if (stats.by_feature && stats.by_feature.length > 0) {
                stats.by_feature.forEach(feature => {
                    const successRate = feature.total_uses > 0 
                        ? ((feature.successful_uses / feature.total_uses) * 100).toFixed(1)
                        : '0.0';
                    
                    html += `
                        <tr>
                            <td>${escapeHtml(feature.feature_key)}</td>
                            <td class="text-end">${feature.total_uses || 0}</td>
                            <td class="text-end">${(feature.total_tokens || 0).toLocaleString('pt-BR')}</td>
                            <td class="text-end">R$ ${(feature.total_cost || 0).toFixed(2).replace('.', ',')}</td>
                            <td class="text-end">
                                <span class="badge badge-${parseFloat(successRate) >= 90 ? 'success' : parseFloat(successRate) >= 70 ? 'warning' : 'danger'}">
                                    ${successRate}%
                                </span>
                            </td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="5" class="text-center text-muted py-5">Nenhum dado disponível</td></tr>';
            }
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            
            // Renderizar gráficos se Chart.js estiver disponível
            if (typeof Chart !== 'undefined') {
                renderAICharts(stats);
            } else {
                // Carregar Chart.js se não estiver disponível
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
                script.onload = () => renderAICharts(stats);
                document.head.appendChild(script);
            }
        } else {
            container.innerHTML = '<div class="card-body"><div class="alert alert-danger">Erro ao carregar estatísticas</div></div>';
        }
    })
    .catch(error => {
        console.error('Erro ao carregar estatísticas:', error);
        container.innerHTML = '<div class="card-body"><div class="alert alert-danger">Erro ao carregar estatísticas</div></div>';
    });
}

// Variáveis globais para armazenar instâncias dos gráficos
let aiChartUsageOverTime = null;
let aiChartByFeature = null;
let aiChartCostByModel = null;

function renderAICharts(stats) {
    // Destruir gráficos existentes antes de criar novos
    if (aiChartUsageOverTime) {
        aiChartUsageOverTime.destroy();
        aiChartUsageOverTime = null;
    }
    if (aiChartByFeature) {
        aiChartByFeature.destroy();
        aiChartByFeature = null;
    }
    if (aiChartCostByModel) {
        aiChartCostByModel.destroy();
        aiChartCostByModel = null;
    }
    
    // Gráfico de uso ao longo do tempo
    if (stats.usage_over_time && stats.usage_over_time.length > 0) {
        const ctx1 = document.getElementById('ai_chart_usage_over_time');
        if (ctx1) {
            const labels = stats.usage_over_time.map(item => {
                // Formatar período baseado no formato retornado
                const period = item.period;
                if (period.includes(' ')) {
                    // Formato com hora: '2025-01-27 14:00:00'
                    const [date, time] = period.split(' ');
                    const [hour] = time.split(':');
                    return date.split('-').reverse().slice(0, 2).join('/') + ' ' + hour + 'h';
                } else if (period.match(/^\d{4}-\d{2}$/)) {
                    // Formato mensal: '2025-01'
                    const [year, month] = period.split('-');
                    const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                    return monthNames[parseInt(month) - 1] + '/' + year;
                } else {
                    // Formato diário: '2025-01-27'
                    return period.split('-').reverse().slice(0, 2).join('/');
                }
            });
            const usesData = stats.usage_over_time.map(item => parseInt(item.uses || 0));
            const costData = stats.usage_over_time.map(item => parseFloat(item.cost || 0));
            
            aiChartUsageOverTime = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Usos',
                            data: usesData,
                            borderColor: '#009ef7',
                            backgroundColor: 'rgba(0, 158, 247, 0.1)',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Custo (R$)',
                            data: costData,
                            borderColor: '#ffc700',
                            backgroundColor: 'rgba(255, 199, 0, 0.1)',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Gráfico de distribuição por funcionalidade
    if (stats.by_feature && stats.by_feature.length > 0) {
        const ctx2 = document.getElementById('ai_chart_by_feature');
        if (ctx2) {
            const labels = stats.by_feature.map(item => item.feature_key);
            const usesData = stats.by_feature.map(item => parseInt(item.total_uses || 0));
            
            aiChartByFeature = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: usesData,
                        backgroundColor: [
                            '#009ef7', '#50cd89', '#ffc700', '#7239ea', 
                            '#f1416c', '#181c32', '#a1a5b7', '#e4e6ef'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'right'
                        }
                    }
                }
            });
        }
    }
    
    // Gráfico de custos por modelo
    if (stats.cost_by_model && stats.cost_by_model.length > 0) {
        const ctx3 = document.getElementById('ai_chart_cost_by_model');
        if (ctx3) {
            const labels = stats.cost_by_model.map(item => item.model || 'N/A');
            const costData = stats.cost_by_model.map(item => parseFloat(item.total_cost || 0));
            
            aiChartCostByModel = new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Custo Total (R$)',
                        data: costData,
                        backgroundColor: '#ffc700'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

