<?php
use App\Helpers\Url;
$buttons = $buttons ?? [];
$stepsByButton = $stepsByButton ?? [];
$pageTitle = 'Botões de Ações';
ob_start();
?>

<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold mb-1">Botões de Ações</h1>
            <p class="text-muted mb-0">Crie atalhos para executar ações rápidas nas conversas.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#actionButtonModal" onclick="openActionButtonModal()">
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
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#actionButtonModal" onclick="openActionButtonModal()">Criar botão</button>
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
                                        <button class="btn btn-sm btn-light" onclick='openActionButtonModal(<?= json_encode($btn) ?>, <?= json_encode($stepsByButton[$btn["id"]] ?? []) ?>)'>
                                            Editar
                                        </button>
                                        <button class="btn btn-sm btn-light-danger" onclick="deleteActionButton(<?= (int)$btn['id'] ?>)">
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
                    <label class="form-label">Cor (hex)</label>
                    <input type="text" class="form-control" name="color" id="ab_color" value="#009ef7">
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
                        <input type="text" class="form-control" name="icon" id="ab_icon" value="ki-bolt" oninput="syncIconPreview(this.value)">
                        <button class="btn btn-light-primary" type="button" onclick="toggleIconSelect()">Escolher</button>
                    </div>
                    <div class="mt-2" id="icon-select-wrapper" style="display:none;">
                        <select class="form-select" id="ab_icon_select" size="6" onchange="selectIcon(this.value)">
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
                <div class="d-flex align-items-center justify-content-between">
                    <label class="form-label mb-0">Etapas</label>
                    <button type="button" class="btn btn-sm btn-light-primary" onclick="addStepRow()">Adicionar Etapa</button>
                </div>
                <div id="stepsContainer" class="mt-3"></div>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="submitActionButton()">Salvar</button>
      </div>
    </div>
  </div>
</div>

<script>
let stepCount = 0;
const stepTypes = [
    { value: 'set_funnel_stage', label: 'Mover para etapa' },
    { value: 'assign_agent', label: 'Atribuir agente' },
    { value: 'add_participant', label: 'Adicionar participante' },
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

function openActionButtonModal(button = null, steps = []) {
    const form = document.getElementById('actionButtonForm');
    form.action = '<?= Url::to('/settings/action-buttons') ?>';
    form.reset();
    stepCount = 0;
    document.getElementById('stepsContainer').innerHTML = '';
    document.getElementById('actionButtonModalLabel').textContent = button ? 'Editar Botão' : 'Novo Botão';
    document.getElementById('ab_id').value = button ? button.id : '';
    document.getElementById('ab_name').value = button ? button.name : '';
    document.getElementById('ab_description').value = button ? (button.description || '') : '';
    document.getElementById('ab_color').value = button ? (button.color || '#009ef7') : '#009ef7';
    const iconVal = button ? (button.icon || 'ki-bolt') : 'ki-bolt';
    document.getElementById('ab_icon').value = iconVal;
    syncIconPreview(iconVal);
    document.getElementById('ab_sort_order').value = button ? (button.sort_order || 0) : 0;
    document.getElementById('ab_is_active').value = button ? button.is_active : 1;

    if (steps && steps.length) {
        steps.forEach(addStepRowFromData);
    } else {
        addStepRow();
    }

    populateIconSelect(iconVal);
    document.getElementById('icon-select-wrapper').style.display = 'none';
}

function addStepRowFromData(step) {
    addStepRow(step.type, step.payload);
}

function addStepRow(type = '', payload = '{}') {
    const container = document.getElementById('stepsContainer');
    const idx = stepCount++;
    let parsed = payload;
    if (typeof payload === 'string') {
        try { parsed = JSON.parse(payload); } catch(e) { parsed = {}; }
    }
    const html = `
        <div class="border rounded p-3 mb-2" data-step-index="${idx}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Etapa</strong>
                <button type="button" class="btn btn-sm btn-icon btn-light-danger" onclick="removeStepRow(${idx})">
                    <i class="ki-duotone ki-cross fs-6"></i>
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="steps[${idx}][type]" onchange="updatePayloadPlaceholders(${idx})">
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
    updatePayloadPlaceholders(idx, parsed || {});
}

function removeStepRow(idx) {
    const el = document.querySelector(`[data-step-index="${idx}"]`);
    if (el) el.remove();
}

function updatePayloadPlaceholders(idx, payload = {}) {
    const select = document.querySelector(`[name="steps[${idx}][type]"]`);
    const hint = document.getElementById(`hint_${idx}`);
    if (!select || !hint) return;
    const value = select.value;
    const hints = {
        set_funnel_stage: 'Selecione funil e etapa',
        assign_agent: 'Selecione o agente',
        add_participant: 'Selecione o participante',
        close_conversation: 'Sem configuração',
        add_tag: 'Selecione a tag',
        remove_tag: 'Selecione a tag'
    };
    hint.textContent = hints[value] || '';
    renderPayloadFields(idx, value, payload);
}

function renderPayloadFields(idx, type, payload = {}) {
    const wrap = document.getElementById(`payload_fields_${idx}`);
    const hidden = document.getElementById(`payload_${idx}`);
    if (!wrap || !hidden) return;
    const p = payload || {};
    const controls = {
        set_funnel_stage: () => `
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label">Funil</label>
                    <select class="form-select" data-field="funnel_id" onchange="onPayloadFieldChange(${idx}); onFunnelChange(${idx});">
                        <option value="">Selecione</option>
                        ${cacheFunnels.map(f => `<option value="${f.id}" ${p.funnel_id==f.id?'selected':''}>${escapeHtml(f.name || 'Funil')}</option>`).join('')}
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Etapa</label>
                    <select class="form-select" data-field="stage_id" onchange="onPayloadFieldChange(${idx})">
                        <option value="">Selecione</option>
                        ${getStagesOptions(p.funnel_id, p.stage_id)}
                    </select>
                </div>
            </div>
        `,
        assign_agent: () => `
            <label class="form-label">Agente</label>
            <select class="form-select" data-field="agent_id" onchange="onPayloadFieldChange(${idx})">
                <option value="">Selecione</option>
                ${cacheAgents.map(a => `<option value="${a.id}" ${p.agent_id==a.id?'selected':''}>${escapeHtml(a.name || a.email || 'Agente')}</option>`).join('')}
            </select>
        `,
        add_participant: () => `
            <label class="form-label">Participante</label>
            <select class="form-select" data-field="participant_id" onchange="onPayloadFieldChange(${idx})">
                <option value="">Selecione</option>
                ${cacheAgents.map(a => `<option value="${a.id}" ${p.participant_id==a.id?'selected':''}>${escapeHtml(a.name || a.email || 'Usuário')}</option>`).join('')}
            </select>
        `,
        close_conversation: () => `<div class="text-muted fs-8">Sem configuração necessária.</div>`,
        add_tag: () => `
            <label class="form-label">Tag</label>
            <select class="form-select" data-field="tag_id" onchange="onPayloadFieldChange(${idx})">
                <option value="">Selecione</option>
                ${cacheTags.map(t => `<option value="${t.id}" ${p.tag_id==t.id?'selected':''}>${escapeHtml(t.name || 'Tag')}</option>`).join('')}
            </select>
        `,
        remove_tag: () => `
            <label class="form-label">Tag</label>
            <select class="form-select" data-field="tag_id" onchange="onPayloadFieldChange(${idx})">
                <option value="">Selecione</option>
                ${cacheTags.map(t => `<option value="${t.id}" ${p.tag_id==t.id?'selected':''}>${escapeHtml(t.name || 'Tag')}</option>`).join('')}
            </select>
        `
    };
    wrap.innerHTML = (controls[type] || (() => '<div class="text-muted fs-8">Selecione o tipo.</div>'))();
    onPayloadFieldChange(idx);
}

function onFunnelChange(idx) {
    const wrap = document.getElementById(`payload_fields_${idx}`);
    if (!wrap) return;
    const funnelSelect = wrap.querySelector('[data-field="funnel_id"]');
    if (!funnelSelect) return;
    const funnelId = funnelSelect.value;
    if (!funnelId) return;
    fetchStagesForFunnel(funnelId).then(() => {
        const stageSelect = wrap.querySelector('[data-field="stage_id"]');
        if (stageSelect) {
            stageSelect.innerHTML = '<option value="">Selecione</option>' + getStagesOptions(funnelId, stageSelect.value || '');
        }
    });
}

function onPayloadFieldChange(idx) {
    const wrap = document.getElementById(`payload_fields_${idx}`);
    const hidden = document.getElementById(`payload_${idx}`);
    if (!wrap || !hidden) return;
    const selects = wrap.querySelectorAll('[data-field]');
    const obj = {};
    selects.forEach(sel => {
        const key = sel.getAttribute('data-field');
        const val = sel.value;
        if (val !== '') obj[key] = isNaN(Number(val)) ? val : Number(val);
    });
    hidden.value = JSON.stringify(obj);
}

function getStagesOptions(funnelId, selectedId) {
    if (!funnelId || !cacheStagesByFunnel[funnelId]) return '';
    return cacheStagesByFunnel[funnelId].map(s => `<option value="${s.id}" ${selectedId==s.id?'selected':''}>${escapeHtml(s.name || 'Etapa')}</option>`).join('');
}

function submitActionButton() {
    const id = document.getElementById('ab_id').value;
    const form = document.getElementById('actionButtonForm');
    form.action = id ? '<?= Url::to('/settings/action-buttons') ?>/' + id : '<?= Url::to('/settings/action-buttons') ?>';
    form.method = 'POST';
    form.submit();
}

function deleteActionButton(id) {
    if (!confirm('Excluir este botão?')) return;
    fetch('<?= Url::to('/settings/action-buttons') ?>/' + id, {
        method: 'DELETE',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(() => location.reload());
}

function populateIconSelect(selected) {
    const sel = document.getElementById('ab_icon_select');
    if (!sel) return;
    sel.innerHTML = iconOptions.map(ic => `<option value="${ic}" ${ic === selected ? 'selected' : ''}>${ic}</option>`).join('');
}

function selectIcon(val) {
    const input = document.getElementById('ab_icon');
    if (input) {
        input.value = val;
        syncIconPreview(val);
    }
}

function syncIconPreview(val) {
    const preview = document.getElementById('ab_icon_preview');
    if (!preview) return;
    preview.innerHTML = `<i class="ki-duotone ${val} fs-3"><span class="path1"></span><span class="path2"></span></i>`;
}

function toggleIconSelect() {
    const wrap = document.getElementById('icon-select-wrapper');
    if (!wrap) return;
    wrap.style.display = wrap.style.display === 'none' ? 'block' : 'none';
}

// Inicializar select ao carregar
document.addEventListener('DOMContentLoaded', () => {
    const currentIcon = document.getElementById('ab_icon')?.value || 'ki-bolt';
    populateIconSelect(currentIcon);
    syncIconPreview(currentIcon);
    preloadActionData();
});

// Caches para selects dinâmicos
let cacheFunnels = [];
let cacheStagesByFunnel = {};
let cacheAgents = [];
let cacheTags = [];

function preloadActionData() {
    fetchFunis();
    fetchAgentes();
    fetchTags();
}

function fetchFunis() {
    if (cacheFunnels.length) return;
    fetch('<?= Url::to('/funnels?format=json') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            cacheFunnels = data.funnels || data || [];
            if (data.stagesByFunnel) {
                cacheStagesByFunnel = data.stagesByFunnel;
            }
        })
        .catch(() => {});
}

function fetchStagesForFunnel(funnelId) {
    if (!funnelId || cacheStagesByFunnel[funnelId]) return Promise.resolve();
    return fetch('<?= Url::to('/funnels') ?>/' + funnelId + '/stages?format=json', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { cacheStagesByFunnel[funnelId] = data.stages || data || []; })
        .catch(() => { cacheStagesByFunnel[funnelId] = []; });
}

function fetchAgentes() {
    if (cacheAgents.length) return;
    fetch('<?= Url::to('/agents?format=json') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { cacheAgents = data.agents || data || []; })
        .catch(() => {});
}

function fetchTags() {
    if (cacheTags.length) return;
    fetch('<?= Url::to('/tags?format=json') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { cacheTags = data.tags || data || []; })
        .catch(() => {});
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../layouts/metronic/app.php';
