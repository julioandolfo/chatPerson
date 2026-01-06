<?php
$buttons = $buttons ?? [];
$stepsByButton = $stepsByButton ?? [];
?>
<?php include __DIR__ . '/../../layouts/metronic/header.php'; ?>

<div class="container-xxl">
    <div class="d-flex align-items-center justify-content-between mb-5">
        <div>
            <h1 class="fw-bold">Botões de Ações</h1>
            <div class="text-muted">Crie atalhos para executar ações rápidas nas conversas.</div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#actionButtonModal" onclick="openActionButtonModal()">
            <i class="ki-duotone ki-plus fs-2"></i>
            Novo Botão
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($buttons)): ?>
                <div class="text-muted">Nenhum botão configurado ainda.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Cor/Icone</th>
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
        <form id="actionButtonForm" method="POST" action="<?= \App\Helpers\Url::to('/settings/action-buttons') ?>">
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
                    <label class="form-label">Ícone (classe Metronic)</label>
                    <input type="text" class="form-control" name="icon" id="ab_icon" value="ki-bolt">
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

function openActionButtonModal(button = null, steps = []) {
    const form = document.getElementById('actionButtonForm');
    form.action = '<?= \App\Helpers\Url::to('/settings/action-buttons') ?>';
    form.reset();
    stepCount = 0;
    document.getElementById('stepsContainer').innerHTML = '';
    document.getElementById('actionButtonModalLabel').textContent = button ? 'Editar Botão' : 'Novo Botão';
    document.getElementById('ab_id').value = button ? button.id : '';
    document.getElementById('ab_name').value = button ? button.name : '';
    document.getElementById('ab_description').value = button ? (button.description || '') : '';
    document.getElementById('ab_color').value = button ? (button.color || '#009ef7') : '#009ef7';
    document.getElementById('ab_icon').value = button ? (button.icon || 'ki-bolt') : 'ki-bolt';
    document.getElementById('ab_sort_order').value = button ? (button.sort_order || 0) : 0;
    document.getElementById('ab_is_active').value = button ? button.is_active : 1;

    if (steps && steps.length) {
        steps.forEach(addStepRowFromData);
    } else {
        addStepRow();
    }
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
                    <label class="form-label">Payload (JSON)</label>
                    <textarea class="form-control" rows="2" name="steps[${idx}][payload]" id="payload_${idx}">${JSON.stringify(parsed || {}, null, 0)}</textarea>
                    <div class="text-muted fs-8" id="hint_${idx}"></div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    updatePayloadPlaceholders(idx);
}

function removeStepRow(idx) {
    const el = document.querySelector(`[data-step-index="${idx}"]`);
    if (el) el.remove();
}

function updatePayloadPlaceholders(idx) {
    const select = document.querySelector(`[name="steps[${idx}][type]"]`);
    const hint = document.getElementById(`hint_${idx}`);
    if (!select || !hint) return;
    const value = select.value;
    const hints = {
        set_funnel_stage: 'Ex: {"stage_id": 12}',
        assign_agent: 'Ex: {"agent_id": 5}',
        add_participant: 'Ex: {"participant_id": 7}',
        close_conversation: 'Sem payload',
        add_tag: 'Ex: {"tag_id": 3}',
        remove_tag: 'Ex: {"tag_id": 3}'
    };
    hint.textContent = hints[value] || '';
}

function submitActionButton() {
    const id = document.getElementById('ab_id').value;
    const form = document.getElementById('actionButtonForm');
    form.action = id ? '<?= \App\Helpers\Url::to('/settings/action-buttons') ?>/' + id : '<?= \App\Helpers\Url::to('/settings/action-buttons') ?>';
    form.method = 'POST';
    form.submit();
}

function deleteActionButton(id) {
    if (!confirm('Excluir este botão?')) return;
    fetch('<?= \App\Helpers\Url::to('/settings/action-buttons') ?>/' + id, {
        method: 'DELETE',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(r => r.json()).then(() => location.reload());
}
</script>

<?php include __DIR__ . '/../../layouts/metronic/footer.php'; ?>
