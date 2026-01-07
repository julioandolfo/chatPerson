<?php
use App\Helpers\Url;
$buttons = $buttons ?? [];
$stepsByButton = $stepsByButton ?? [];
$pageTitle = 'Botões de Ações';
ob_start();
?>

<!-- SCRIPT PRIMEIRO - Antes do HTML para garantir que funções existam quando o HTML for renderizado -->
<script>
// ===== VARIÁVEIS E FUNÇÕES GLOBAIS (DEFINIDAS IMEDIATAMENTE) =====
(function() {
    let stepCount = 0;
    const stepTypes = [
        { value: 'set_funnel_stage', label: 'Mover para etapa' },
        { value: 'assign_agent', label: 'Atribuir agente' },
        { value: 'add_participant', label: 'Adicionar participante' },
        { value: 'leave_conversation', label: 'Sair da conversa (participante atual)' },
        { value: 'close_conversation', label: 'Encerrar conversa' },
        { value: 'add_tag', label: 'Adicionar tag' },
        { value: 'remove_tag', label: 'Remover tag' }
    ];

    const iconOptions = [
        'ki-bolt','ki-check','ki-check-circle','ki-close-circle','ki-cross','ki-user','ki-user-tick','ki-user-add',
        'ki-call','ki-call-calling','ki-whatsapp','ki-send','ki-send-right','ki-double-check','ki-shield-check',
        'ki-star','ki-like','ki-dislike','ki-filter','ki-filter-edit','ki-setting','ki-gear','ki-rocket','ki-flash',
        'ki-timer','ki-calendar','ki-time','ki-refresh','ki-loop','ki-repeat','ki-upload','ki-download','ki-cloud',
        'ki-tag','ki-bookmark','ki-chat','ki-message','ki-bell','ki-graph','ki-chart-simple','ki-chart-line',
        'ki-chart-pie','ki-folder','ki-file','ki-document','ki-link','ki-lock','ki-unlock','ki-shield-cross',
        'ki-magnifier','ki-search-list','ki-emoji-happy','ki-emoji-sad','ki-emoji-neutral','ki-smile','ki-happy',
        'ki-menu','ki-dots-circle','ki-dots-square','ki-dots-vertical','ki-dots-horizontal','ki-exit-right-corner'
    ];

    // Caches para selects dinâmicos
    let cacheFunnels = [];
    let cacheStagesByFunnel = {};
    let cacheAgents = [];
    let cacheTags = [];

    // Expor caches globalmente
    window.cacheFunnels = cacheFunnels;
    window.cacheStagesByFunnel = cacheStagesByFunnel;
    window.cacheAgents = cacheAgents;
    window.cacheTags = cacheTags;

    // Cores
    window.syncColorInput = function(colorValue) {
        const input = document.getElementById('ab_color');
        if (input) input.value = colorValue;
    };

    window.syncColorPicker = function(hexValue) {
        const picker = document.getElementById('ab_color_picker');
        if (picker) picker.value = hexValue;
    };

    // Ícones
    window.syncIconPreview = function(val) {
        const preview = document.getElementById('ab_icon_preview');
        if (preview) preview.innerHTML = `<i class="ki-duotone ${val} fs-3"><span class="path1"></span><span class="path2"></span></i>`;
    };

    window.toggleIconSelect = function() {
        const wrap = document.getElementById('icon-select-wrapper');
        if (wrap) wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
    };

    window.selectIcon = function(val) {
        const input = document.getElementById('ab_icon');
        if (input) {
            input.value = val;
            window.syncIconPreview(val);
        }
    };

    window.populateIconSelect = function(selected) {
        const sel = document.getElementById('ab_icon_select');
        if (!sel) return;
        sel.innerHTML = iconOptions.map(ic => `<option value="${ic}" ${ic === selected ? 'selected' : ''}>${ic}</option>`).join('');
    };

    // Escape HTML
    window.escapeHtml = function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    // Visibilidade (agentes)
    window.populateVisibilitySelect = function(selected = []) {
        const sel = document.getElementById('ab_visibility_agents');
        if (!sel) return;
        const selectedSet = new Set((selected || []).map(v => String(v)));
        if (!window.cacheAgents || !window.cacheAgents.length) {
            sel.innerHTML = '<option value=\"\">Carregando agentes...</option>';
            return;
        }
        sel.innerHTML = window.cacheAgents.map(a => {
            const id = String(a.id);
            const isSel = selectedSet.has(id);
            return `<option value=\"${id}\" ${isSel ? 'selected' : ''}>${window.escapeHtml(a.name || a.email || 'Agente')}</option>`;
        }).join('');
    };

    // Steps (etapas)
    window.addStepRow = function(type = '', payload = '{}') {
        const container = document.getElementById('stepsContainer');
        if (!container) return;
        const idx = stepCount++;
        let parsed = payload;
        if (typeof payload === 'string') {
            try { parsed = JSON.parse(payload); } catch(e) { parsed = {}; }
        }
        const html = `
            <div class="border rounded p-3 mb-2" data-step-index="${idx}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Etapa</strong>
                    <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="window.removeStepRow(${idx})">
                        <i class="ki-duotone ki-cross fs-6"></i>
                    </button>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="steps[${idx}][type]" onchange="window.updatePayloadPlaceholders(${idx})">
                            ${stepTypes.map(s => `<option value="${s.value}" ${type===s.value?'selected':''}>${s.label}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Configuração da etapa</label>
                        <div id="payload_fields_${idx}" class="d-flex flex-column gap-2"></div>
                        <input type="hidden" name="steps[${idx}][payload]" id="payload_${idx}" value='${JSON.stringify(parsed || {})}'>
                        <div class="text-muted fs-8" id="hint_${idx}"></div>
                    </div>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        window.updatePayloadPlaceholders(idx, parsed || {});
    };

    window.addStepRowFromData = function(step) {
        window.addStepRow(step.type, step.payload);
    };

    window.removeStepRow = function(idx) {
        const el = document.querySelector(`[data-step-index="${idx}"]`);
        if (el) el.remove();
    };

    window.updatePayloadPlaceholders = function(idx, payload = {}) {
        const select = document.querySelector(`[name="steps[${idx}][type]"]`);
        const container = document.getElementById(`payload_fields_${idx}`);
        const hidden = document.getElementById(`payload_${idx}`);
        const hint = document.getElementById(`hint_${idx}`);
        if (!select || !container || !hidden) return;
        
        const type = select.value;
        const p = payload || {};
        container.innerHTML = '';
        
        if (type === 'set_funnel_stage') {
            container.innerHTML = `
                <div class="mb-2">
                    <label class="form-label fs-8">Funil</label>
                    <select class="form-select form-select-sm" data-field="funnel_id" onchange="window.onPayloadFieldChange(${idx}); window.onFunnelChange(${idx});">
                        <option value="">Selecione</option>
                        ${window.cacheFunnels.map(f => `<option value="${f.id}" ${p.funnel_id==f.id?'selected':''}>${window.escapeHtml(f.name || 'Funil')}</option>`).join('')}
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label fs-8">Etapa</label>
                    <select class="form-select form-select-sm" data-field="stage_id" onchange="window.onPayloadFieldChange(${idx});">
                        <option value="">Selecione o funil primeiro</option>
                    </select>
                </div>
            `;
            if (p.funnel_id) {
                window.fetchStagesForFunnel(p.funnel_id).then(() => {
                    const stageSelect = container.querySelector('[data-field="stage_id"]');
                    if (stageSelect) stageSelect.innerHTML = `<option value="">Selecione</option>` + window.getStagesOptions(p.funnel_id, p.stage_id);
                });
            }
            if (hint) hint.textContent = 'Selecione funil e etapa';
        } else if (type === 'assign_agent') {
            container.innerHTML = `
                <label class="form-label fs-8">Agente</label>
                <select class="form-select form-select-sm" data-field="agent_id" onchange="window.onPayloadFieldChange(${idx});">
                    <option value="">Selecione</option>
                    ${window.cacheAgents.map(a => `<option value="${a.id}" ${p.agent_id==a.id?'selected':''}>${window.escapeHtml(a.name || a.email)}</option>`).join('')}
                </select>
            `;
            if (hint) hint.textContent = 'Selecione o agente';
        } else if (type === 'add_participant') {
            container.innerHTML = `
                <label class="form-label fs-8">Participante</label>
                <select class="form-select form-select-sm" data-field="participant_id" onchange="window.onPayloadFieldChange(${idx});">
                    <option value="">Selecione</option>
                    ${window.cacheAgents.map(a => `<option value="${a.id}" ${p.participant_id==a.id?'selected':''}>${window.escapeHtml(a.name || a.email)}</option>`).join('')}
                </select>
            `;
            if (hint) hint.textContent = 'Selecione o participante';
        } else if (type === 'leave_conversation') {
            container.innerHTML = `
                <div class="text-muted fs-8">Sem configuração: usará o usuário que clicar para sair da conversa.</div>
            `;
            if (hint) hint.textContent = 'Sem configuração necessária';
        } else if (type === 'add_tag' || type === 'remove_tag') {
            container.innerHTML = `
                <label class="form-label fs-8">Tag</label>
                <select class="form-select form-select-sm" data-field="tag_id" onchange="window.onPayloadFieldChange(${idx});">
                    <option value="">Selecione</option>
                    ${window.cacheTags.map(t => `<option value="${t.id}" ${p.tag_id==t.id?'selected':''}>${window.escapeHtml(t.name)}</option>`).join('')}
                </select>
            `;
            if (hint) hint.textContent = 'Selecione a tag';
        } else if (type === 'close_conversation') {
            container.innerHTML = '<div class="text-muted fs-8">Sem configuração necessária.</div>';
            if (hint) hint.textContent = '';
        }
        
        window.onPayloadFieldChange(idx);
    };

    window.onFunnelChange = function(idx) {
        const container = document.getElementById(`payload_fields_${idx}`);
        if (!container) return;
        const funnelSelect = container.querySelector('[data-field="funnel_id"]');
        const stageSelect = container.querySelector('[data-field="stage_id"]');
        if (!funnelSelect || !stageSelect) return;
        const funnelId = funnelSelect.value;
        if (!funnelId) {
            stageSelect.innerHTML = '<option value="">Selecione o funil primeiro</option>';
            return;
        }
        window.fetchStagesForFunnel(funnelId).then(() => {
            stageSelect.innerHTML = `<option value="">Selecione</option>` + window.getStagesOptions(funnelId, '');
        });
    };

    window.onPayloadFieldChange = function(idx) {
        const container = document.getElementById(`payload_fields_${idx}`);
        const hidden = document.getElementById(`payload_${idx}`);
        if (!container || !hidden) return;
        const selects = container.querySelectorAll('select[data-field]');
        const obj = {};
        selects.forEach(sel => {
            const key = sel.getAttribute('data-field');
            const val = sel.value;
            if (val !== '') obj[key] = isNaN(Number(val)) ? val : Number(val);
        });
        hidden.value = JSON.stringify(obj);
    };

    window.getStagesOptions = function(funnelId, selectedId) {
        if (!funnelId || !window.cacheStagesByFunnel[funnelId]) return '';
        return window.cacheStagesByFunnel[funnelId].map(s => `<option value="${s.id}" ${selectedId==s.id?'selected':''}>${window.escapeHtml(s.name || 'Etapa')}</option>`).join('');
    };

    // Ações principais
    window.submitActionButton = function() {
        const id = document.getElementById('ab_id').value;
        const form = document.getElementById('actionButtonForm');
        form.action = id ? '<?= Url::to('/settings/action-buttons') ?>/' + id : '<?= Url::to('/settings/action-buttons') ?>';
        form.method = 'POST';
        form.submit();
    };

    window.deleteActionButton = function(id) {
        if (!confirm('Excluir este botão?')) return;
        fetch('<?= Url::to('/settings/action-buttons') ?>/' + id, {
            method: 'DELETE',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(r => r.json()).then(() => location.reload());
    };

    window.fetchStagesForFunnel = function(funnelId) {
        if (!funnelId || window.cacheStagesByFunnel[funnelId]) return Promise.resolve();
        return fetch('<?= Url::to('/funnels') ?>/' + funnelId + '/stages?format=json', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => { window.cacheStagesByFunnel[funnelId] = data.stages || data || []; })
            .catch(() => { window.cacheStagesByFunnel[funnelId] = []; });
    };

    window.openActionButtonModal = function(button, steps) {
        button = button || null;
        steps = steps || [];
        const form = document.getElementById('actionButtonForm');
        if (!form) return;
        form.action = '<?= Url::to('/settings/action-buttons') ?>';
        form.reset();
        stepCount = 0;
        const stepsContainer = document.getElementById('stepsContainer');
        if (stepsContainer) stepsContainer.innerHTML = '';
        const labelEl = document.getElementById('actionButtonModalLabel');
        if (labelEl) labelEl.textContent = button ? 'Editar Botão' : 'Novo Botão';
        document.getElementById('ab_id').value = button ? button.id : '';
        document.getElementById('ab_name').value = button ? button.name : '';
        document.getElementById('ab_description').value = button ? (button.description || '') : '';
        const visibilityAgents = button && button.visibility && Array.isArray(button.visibility.agents)
            ? button.visibility.agents
            : [];
        window.populateVisibilitySelect(visibilityAgents);
        const colorVal = button ? (button.color || '#009ef7') : '#009ef7';
        document.getElementById('ab_color').value = colorVal;
        window.syncColorPicker(colorVal);
        const iconVal = button ? (button.icon || 'ki-bolt') : 'ki-bolt';
        document.getElementById('ab_icon').value = iconVal;
        window.syncIconPreview(iconVal);
        document.getElementById('ab_sort_order').value = button ? (button.sort_order || 0) : 0;
        document.getElementById('ab_is_active').value = button ? button.is_active : 1;

        if (steps && steps.length) {
            steps.forEach(s => window.addStepRow(s.type, s.payload));
        } else {
            window.addStepRow();
        }

        window.populateIconSelect(iconVal);
        const iconWrapper = document.getElementById('icon-select-wrapper');
        if (iconWrapper) iconWrapper.style.display = 'none';
    };

    // Preload de dados para os selects dinâmicos
    window.preloadActionData = function() {
        // Funis
        fetch('<?= Url::to('/funnels?format=json') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => {
                window.cacheFunnels = data.funnels || data || [];
                if (data.stagesByFunnel) {
                    window.cacheStagesByFunnel = data.stagesByFunnel;
                }
                console.log('Funis carregados:', window.cacheFunnels.length);
            })
            .catch(err => { console.error('Erro ao carregar funis:', err); });

        // Agentes
        fetch('<?= Url::to('/agents?format=json') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => { 
                window.cacheAgents = data.agents || data || []; 
                console.log('Agentes carregados:', window.cacheAgents.length);
                window.populateVisibilitySelect(window.visibilitySelected || []);
            })
            .catch(err => { console.error('Erro ao carregar agentes:', err); });

        // Tags
        fetch('<?= Url::to('/tags/all') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(data => { 
                window.cacheTags = data.tags || data || []; 
                console.log('Tags carregadas:', window.cacheTags.length);
            })
            .catch(err => { console.error('Erro ao carregar tags:', err); });
    };

    // Log de debug
    console.log('✅ Funções globais registradas (action-buttons)');

    // Inicializar ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        const currentIcon = document.getElementById('ab_icon')?.value || 'ki-bolt';
        window.populateIconSelect(currentIcon);
        window.syncIconPreview(currentIcon);
        const currentColor = document.getElementById('ab_color')?.value || '#009ef7';
        window.syncColorPicker(currentColor);
        window.preloadActionData();
    });
})();
</script>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">Botões de Ações</h1>
            <p class="text-muted mb-0">Crie atalhos para executar ações rápidas nas conversas.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#actionButtonModal" onclick="window.openActionButtonModal()">
            <i class="ki-duotone ki-plus fs-2"></i>
            Novo Botão
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($buttons)): ?>
                <div class="text-center text-muted py-10">
                    <i class="ki-duotone ki-bolt fs-2x text-muted mb-3"><span class="path1"></span><span class="path2"></span></i>
                    <div class="fw-semibold mb-1">Nenhum botão configurado ainda.</div>
                    <div class="text-muted fs-7 mb-3">Crie seu primeiro botão de ação para acelerar os fluxos.</div>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#actionButtonModal" onclick="window.openActionButtonModal()">Criar botão</button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Cor/Ícone</th>
                                <th>Ordem</th>
                                <th>Ativo</th>
                                <th>Etapas</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buttons as $btn): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($btn['name']) ?></div>
                                        <?php if (!empty($btn['description'])): ?>
                                            <div class="text-muted fs-8"><?= htmlspecialchars($btn['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?= htmlspecialchars($btn['color']) ?>20; color: <?= htmlspecialchars($btn['color']) ?>;">
                                            <i class="ki-duotone <?= htmlspecialchars($btn['icon']) ?> fs-6"></i>
                                            <?= htmlspecialchars($btn['color']) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$btn['sort_order'] ?></td>
                                    <td><?= (int)$btn['is_active'] === 1 ? 'Sim' : 'Não' ?></td>
                                    <td>
                                        <?php $steps = $stepsByButton[$btn['id']] ?? []; ?>
                                        <?php if (empty($steps)): ?>
                                            <span class="text-muted fs-8">Nenhuma etapa</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-1">
                                                <?php foreach ($steps as $step): ?>
                                                    <span class="badge badge-light-primary fs-8">
                                                        <?= htmlspecialchars($step['type']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-light" onclick='window.openActionButtonModal(<?= json_encode($btn) ?>, <?= json_encode($stepsByButton[$btn["id"]] ?? []) ?>)'>
                                            Editar
                                        </button>
                                        <button class="btn btn-sm btn-light-danger" onclick="window.deleteActionButton(<?= (int)$btn['id'] ?>)">
                                            Excluir
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="actionButtonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="actionButtonModalLabel">Novo Botão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="actionButtonForm" method="POST" action="<?= Url::to('/settings/action-buttons') ?>">
            <input type="hidden" name="id" id="ab_id">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nome</label>
                    <input type="text" class="form-control" name="name" id="ab_name" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label d-flex align-items-center justify-content-between">
                        <span>Cor</span>
                        <small class="text-muted">Selecione ou ajuste no picker</small>
                    </label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color" id="ab_color_picker" value="#009ef7" title="Escolher cor" onchange="window.syncColorInput(this.value)">
                        <input type="text" class="form-control" name="color" id="ab_color" value="#009ef7" oninput="window.syncColorPicker(this.value)">
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label d-flex align-items-center justify-content-between">
                        <span>Ícone (classe Metronic)</span>
                        <small class="text-muted">Selecione na lista ou edite manualmente</small>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light" id="ab_icon_preview">
                            <i class="ki-duotone ki-bolt fs-3"><span class="path1"></span><span class="path2"></span></i>
                        </span>
                        <input type="text" class="form-control" name="icon" id="ab_icon" value="ki-bolt" oninput="window.syncIconPreview(this.value)">
                        <button class="btn btn-light-primary" type="button" onclick="window.toggleIconSelect()">Escolher</button>
                    </div>
                    <div class="mt-2" id="icon-select-wrapper" style="display:none;">
                        <select class="form-select" id="ab_icon_select" size="6" onchange="window.selectIcon(this.value)">
                            <!-- opções inseridas via JS -->
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ordem</label>
                    <input type="number" class="form-control" name="sort_order" id="ab_sort_order" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Ativo</label>
                    <select class="form-select" name="is_active" id="ab_is_active">
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Descrição</label>
                <textarea class="form-control" name="description" id="ab_description"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label d-flex align-items-center justify-content-between">
                    <span>Visibilidade (agentes)</span>
                    <small class="text-muted">Deixe vazio para todos</small>
                </label>
                <select class="form-select" name="visibility[agents][]" id="ab_visibility_agents" multiple>
                    <!-- opções preenchidas via JS -->
                </select>
            </div>

            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between">
                    <label class="form-label mb-0">Etapas</label>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="window.addStepRow()">Adicionar Etapa</button>
                </div>
                <div id="stepsContainer" class="mt-3"></div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="window.submitActionButton()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
