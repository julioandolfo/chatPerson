<?php
/**
 * View: Disparos de Templates por Vendedor
 * Envio de templates NotificaMe para clientes do vendedor
 */

$layout = 'layouts.metronic.app';
$title = $title ?? 'Disparos do Vendedor';

use App\Helpers\Url;

ob_start();

$agent = $agent ?? [];
$accounts = $accounts ?? [];
$sentToday = $sentToday ?? 0;
$remaining = $remaining ?? 0;
$dailyLimit = $dailyLimit ?? 30;
$history = $history ?? [];
?>

<!-- Cabecalho -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-7 gap-4">
    <div class="d-flex align-items-center gap-4">
        <div class="symbol symbol-50px symbol-circle">
            <?php if (!empty($agent['avatar'])): ?>
                <img src="<?= htmlspecialchars($agent['avatar']) ?>" alt="">
            <?php else: ?>
                <span class="symbol-label bg-light-primary text-primary fs-1 fw-bold">
                    <?= strtoupper(substr($agent['name'] ?? 'V', 0, 1)) ?>
                </span>
            <?php endif; ?>
        </div>
        <div>
            <h1 class="fs-2 fw-bold mb-1">Disparos de Templates</h1>
            <div class="text-muted fs-7"><?= htmlspecialchars($agent['name'] ?? 'Vendedor') ?></div>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="<?= Url::to('/agent-performance/agent', ['id' => $agent['id'] ?? '']) ?>" class="btn btn-sm btn-light-primary">
            <i class="ki-duotone ki-arrow-left fs-4"><span class="path1"></span><span class="path2"></span></i>
            Voltar ao Performance
        </a>
    </div>
</div>

<!-- Limite diario -->
<div class="row g-5 mb-7">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-5 text-center">
                <div class="fs-2x fw-bold text-primary"><?= $remaining ?></div>
                <div class="fw-semibold text-muted">Envios Restantes Hoje</div>
                <div class="progress h-8px mt-3">
                    <div class="progress-bar bg-primary" style="width: <?= $dailyLimit > 0 ? round(($sentToday / $dailyLimit) * 100) : 0 ?>%"></div>
                </div>
                <div class="fs-8 text-muted mt-2"><?= $sentToday ?> / <?= $dailyLimit ?> usados</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-5 text-center">
                <div class="fs-2x fw-bold text-success"><?= $sentToday ?></div>
                <div class="fw-semibold text-muted">Enviados Hoje</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body p-5 text-center">
                <div class="fs-2x fw-bold text-gray-800"><?= $dailyLimit ?></div>
                <div class="fw-semibold text-muted">Limite Diario</div>
            </div>
        </div>
    </div>
</div>

<!-- Formulario de Disparo -->
<div class="card mb-7">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold text-gray-900">
                <i class="ki-duotone ki-send fs-2 text-success me-2">
                    <span class="path1"></span>
                    <span class="path2"></span>
                </i>
                Novo Disparo
            </span>
            <span class="text-muted mt-1 fw-semibold fs-7">Envie templates WhatsApp para seus clientes</span>
        </h3>
    </div>
    <div class="card-body py-4">
        <!-- Step 1: Conta e Template -->
        <div class="row g-5 mb-5">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Conta WhatsApp</label>
                <select class="form-select" id="vb-account-select">
                    <option value="">Selecione uma conta...</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= (int)$acc['id'] ?>" data-phone="<?= htmlspecialchars($acc['phone_number'] ?? '') ?>">
                            <?= htmlspecialchars($acc['name']) ?>
                            <?php if (!empty($acc['phone_number'])): ?>
                                (<?= htmlspecialchars($acc['phone_number']) ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($accounts)): ?>
                    <div class="text-warning fs-8 mt-2">Nenhuma conta WhatsApp NotificaMe ativa encontrada.</div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Template</label>
                <select class="form-select" id="vb-template-select" disabled>
                    <option value="">Selecione a conta primeiro...</option>
                </select>
                <div id="vb-template-loading" class="d-none">
                    <span class="spinner-border spinner-border-sm text-primary mt-2"></span>
                    <span class="text-muted fs-8 ms-2">Carregando templates...</span>
                </div>
            </div>
        </div>

        <!-- Preview do Template -->
        <div class="row g-5 mb-5" id="vb-template-preview-area" style="display:none;">
            <div class="col-12">
                <div class="bg-light-info rounded p-4">
                    <div class="fw-bold text-gray-800 mb-2">
                        <i class="ki-duotone ki-eye fs-5 me-1"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                        Preview do Template
                    </div>
                    <div id="vb-template-preview" class="text-gray-700 fs-7"></div>
                    <div id="vb-template-params-area" class="mt-3" style="display:none;">
                        <div class="fw-semibold text-gray-700 mb-2">Parametros do template:</div>
                        <div id="vb-template-params-inputs"></div>
                        <div class="fs-8 text-muted mt-2">
                            Variaveis disponiveis: <code>{{nome}}</code>, <code>{{telefone}}</code>, <code>{{email}}</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Selecao de Clientes -->
        <div class="row g-5 mb-5">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="form-label fw-semibold mb-0">Selecionar Clientes</label>
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" class="form-control form-control-sm w-200px" id="vb-client-search" placeholder="Buscar cliente...">
                        <button class="btn btn-sm btn-light-primary" onclick="loadClients()">
                            <i class="ki-duotone ki-magnifier fs-5"><span class="path1"></span><span class="path2"></span></i>
                            Buscar
                        </button>
                        <button class="btn btn-sm btn-light-info" onclick="selectAllClients()" title="Selecionar todos">
                            Selecionar Todos
                        </button>
                        <button class="btn btn-sm btn-light-warning" onclick="deselectAllClients()" title="Desmarcar todos">
                            Limpar
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge badge-light-primary fs-7" id="vb-selected-count">0 selecionados</span>
                    <span class="badge badge-light-warning fs-7" id="vb-remaining-badge"><?= $remaining ?> restantes</span>
                </div>
                <div id="vb-clients-list" class="border rounded p-3" style="max-height:400px; overflow-y:auto;">
                    <div class="text-muted text-center py-5 fs-7">Carregando seus clientes...</div>
                </div>
            </div>
        </div>

        <!-- Botao Enviar -->
        <div class="d-flex justify-content-end">
            <button class="btn btn-success btn-lg" id="vb-send-btn" onclick="sendBroadcast()" disabled>
                <i class="ki-duotone ki-send fs-3 me-2"><span class="path1"></span><span class="path2"></span></i>
                Enviar Disparo
            </button>
        </div>
    </div>
</div>

<!-- Historico de Disparos -->
<?php if (!empty($history)): ?>
<div class="card">
    <div class="card-header border-0 pt-5">
        <h3 class="card-title align-items-start flex-column">
            <span class="card-label fw-bold text-gray-900">
                <i class="ki-duotone ki-time fs-2 text-muted me-2"><span class="path1"></span><span class="path2"></span></i>
                Historico de Disparos
            </span>
        </h3>
    </div>
    <div class="card-body py-3">
        <div class="table-responsive">
            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                <thead>
                    <tr class="fw-bold text-muted fs-7">
                        <th>Data</th>
                        <th>Template</th>
                        <th>Conta</th>
                        <th class="text-center">Contatos</th>
                        <th class="text-center">Enviados</th>
                        <th class="text-center">Falhas</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <?php
                        $statusColors = [
                            'completed' => 'success', 'sending' => 'info',
                            'failed' => 'danger', 'pending' => 'warning', 'cancelled' => 'secondary'
                        ];
                        $statusLabels = [
                            'completed' => 'Concluido', 'sending' => 'Enviando',
                            'failed' => 'Falhou', 'pending' => 'Pendente', 'cancelled' => 'Cancelado'
                        ];
                        $st = $h['status'] ?? 'pending';
                    ?>
                    <tr>
                        <td class="fs-7"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($h['template_name']) ?></td>
                        <td class="fs-7"><?= htmlspecialchars($h['account_name'] ?? '-') ?></td>
                        <td class="text-center"><?= (int)$h['total_contacts'] ?></td>
                        <td class="text-center text-success fw-bold"><?= (int)$h['total_sent'] ?></td>
                        <td class="text-center text-danger fw-bold"><?= (int)$h['total_failed'] ?></td>
                        <td class="text-center">
                            <span class="badge badge-light-<?= $statusColors[$st] ?? 'secondary' ?>">
                                <?= $statusLabels[$st] ?? $st ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-icon btn-light-primary" onclick="viewBroadcastDetails(<?= (int)$h['id'] ?>)" title="Ver detalhes">
                                <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Detalhes do Disparo -->
<div class="modal fade" id="modal_broadcast_details" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bold">Detalhes do Disparo</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary" data-bs-dismiss="modal">
                    <i class="ki-duotone ki-cross fs-1"><span class="path1"></span><span class="path2"></span></i>
                </button>
            </div>
            <div class="modal-body" id="modal_broadcast_details_body">
                <div class="text-center py-10"><span class="spinner-border text-primary"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
const VB = {
    agentId: <?= (int)($agent['id'] ?? 0) ?>,
    remaining: <?= $remaining ?>,
    dailyLimit: <?= $dailyLimit ?>,
    selectedContacts: new Map(),
    templates: [],
    currentTemplate: null,

    urls: {
        templates: <?= json_encode(Url::to('/api/vendor-broadcast/templates')) ?>,
        clients: <?= json_encode(Url::to('/api/vendor-broadcast/clients')) ?>,
        send: <?= json_encode(Url::to('/api/vendor-broadcast/send')) ?>,
        details: <?= json_encode(Url::to('/api/vendor-broadcast/details')) ?>,
    }
};

// === Carregar templates ao selecionar conta ===
document.getElementById('vb-account-select').addEventListener('change', function() {
    const accountId = this.value;
    const templateSelect = document.getElementById('vb-template-select');
    const loading = document.getElementById('vb-template-loading');

    templateSelect.disabled = true;
    templateSelect.innerHTML = '<option value="">Carregando...</option>';
    document.getElementById('vb-template-preview-area').style.display = 'none';

    if (!accountId) {
        templateSelect.innerHTML = '<option value="">Selecione a conta primeiro...</option>';
        return;
    }

    loading.classList.remove('d-none');
    const u = new URL(VB.urls.templates, location.origin);
    u.searchParams.set('account_id', accountId);

    fetch(u.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(j => {
            loading.classList.add('d-none');
            if (!j.success) throw new Error(j.message || 'Erro');
            VB.templates = j.data || [];
            templateSelect.innerHTML = '<option value="">Selecione um template...</option>';
            VB.templates.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.name;
                opt.dataset.language = t.language || 'pt_BR';
                opt.textContent = t.name + (t.category ? ' (' + t.category + ')' : '');
                templateSelect.appendChild(opt);
            });
            templateSelect.disabled = false;
        })
        .catch(e => {
            loading.classList.add('d-none');
            templateSelect.innerHTML = '<option value="">Erro ao carregar templates</option>';
            console.error(e);
        });
});

// === Preview do template ===
document.getElementById('vb-template-select').addEventListener('change', function() {
    const name = this.value;
    const previewArea = document.getElementById('vb-template-preview-area');
    const previewEl = document.getElementById('vb-template-preview');
    const paramsArea = document.getElementById('vb-template-params-area');
    const paramsInputs = document.getElementById('vb-template-params-inputs');

    if (!name) {
        previewArea.style.display = 'none';
        VB.currentTemplate = null;
        updateSendButton();
        return;
    }

    const tpl = VB.templates.find(t => t.name === name);
    VB.currentTemplate = tpl;

    if (!tpl) {
        previewArea.style.display = 'none';
        return;
    }

    // Renderizar preview
    let html = '';
    const components = tpl.components || [];
    components.forEach(comp => {
        if (comp.type === 'HEADER') {
            if (comp.format === 'TEXT' && comp.text) {
                html += '<div class="fw-bold mb-1">' + escapeHtml(comp.text) + '</div>';
            } else if (comp.format === 'IMAGE') {
                html += '<div class="text-muted fs-8 mb-1">[Imagem do header]</div>';
            }
        } else if (comp.type === 'BODY') {
            html += '<div class="mb-1">' + escapeHtml(comp.text || '') + '</div>';
        } else if (comp.type === 'FOOTER') {
            html += '<div class="text-muted fs-8 mt-1">' + escapeHtml(comp.text || '') + '</div>';
        } else if (comp.type === 'BUTTONS') {
            (comp.buttons || []).forEach(btn => {
                html += '<span class="badge badge-light-primary me-1 mt-1">' + escapeHtml(btn.text || btn.url || '') + '</span>';
            });
        }
    });
    previewEl.innerHTML = html || '<em class="text-muted">Sem preview disponivel</em>';
    previewArea.style.display = '';

    // Detectar parametros {{1}}, {{2}}, etc no BODY
    const bodyComp = components.find(c => c.type === 'BODY');
    const bodyText = bodyComp ? (bodyComp.text || '') : '';
    const paramMatches = bodyText.match(/\{\{\d+\}\}/g) || [];
    const paramCount = paramMatches.length;

    if (paramCount > 0) {
        paramsInputs.innerHTML = '';
        for (let i = 1; i <= paramCount; i++) {
            const div = document.createElement('div');
            div.className = 'mb-2';
            div.innerHTML = '<label class="form-label fs-8">Parametro {{' + i + '}}</label>' +
                '<input type="text" class="form-control form-control-sm vb-param-input" data-param="' + i + '" ' +
                'placeholder="Ex: {{nome}} ou texto fixo">';
            paramsInputs.appendChild(div);
        }
        paramsArea.style.display = '';
    } else {
        paramsArea.style.display = 'none';
    }

    updateSendButton();
});

// === Carregar clientes ===
function loadClients() {
    const container = document.getElementById('vb-clients-list');
    const search = document.getElementById('vb-client-search').value.trim();
    container.innerHTML = '<div class="text-center py-5"><span class="spinner-border spinner-border-sm text-primary"></span> Carregando...</div>';

    const u = new URL(VB.urls.clients, location.origin);
    u.searchParams.set('agent_id', VB.agentId);
    if (search) u.searchParams.set('search', search);

    fetch(u.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(j => {
            if (!j.success) throw new Error(j.message || 'Erro');
            const clients = j.data || [];
            if (clients.length === 0) {
                container.innerHTML = '<div class="text-muted text-center py-5 fs-7">Nenhum cliente encontrado.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-sm table-row-dashed gs-0 gy-2">';
            html += '<thead><tr class="text-muted fs-8 fw-bold"><th width="40"></th><th>Cliente</th><th>Telefone</th><th class="text-center">Pedidos</th><th class="text-end">Total Gasto</th></tr></thead><tbody>';
            clients.forEach(c => {
                const checked = VB.selectedContacts.has(c.contact_id) ? 'checked' : '';
                const fmtCur = v => 'R$ ' + Number(v || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                html += '<tr>';
                html += '<td><input type="checkbox" class="form-check-input vb-client-cb" ' + checked + ' ' +
                    'data-id="' + c.contact_id + '" ' +
                    'data-phone="' + escapeHtml(c.contact_phone) + '" ' +
                    'data-name="' + escapeHtml(c.full_name) + '" ' +
                    'data-email="' + escapeHtml(c.contact_email || '') + '" ' +
                    'onchange="toggleClient(this)"></td>';
                html += '<td class="fw-semibold">' + escapeHtml(c.full_name || 'Sem nome') + '</td>';
                html += '<td class="fs-7">' + escapeHtml(c.contact_phone) + '</td>';
                html += '<td class="text-center">' + (c.order_count || 0) + '</td>';
                html += '<td class="text-end text-success">' + fmtCur(c.total_spent) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(e => {
            container.innerHTML = '<div class="alert alert-warning">' + escapeHtml(e.message || 'Erro ao carregar') + '</div>';
        });
}

// Buscar ao pressionar Enter
document.getElementById('vb-client-search').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); loadClients(); }
});

// === Selecao de clientes ===
function toggleClient(cb) {
    const id = parseInt(cb.dataset.id);
    if (cb.checked) {
        if (VB.selectedContacts.size >= VB.remaining) {
            cb.checked = false;
            Swal.fire({ icon: 'warning', title: 'Limite atingido', text: 'Voce pode selecionar no maximo ' + VB.remaining + ' contatos hoje.', timer: 3000 });
            return;
        }
        VB.selectedContacts.set(id, {
            contact_id: id,
            phone: cb.dataset.phone,
            name: cb.dataset.name,
            email: cb.dataset.email || '',
        });
    } else {
        VB.selectedContacts.delete(id);
    }
    updateSelectedCount();
    updateSendButton();
}

function selectAllClients() {
    const checkboxes = document.querySelectorAll('.vb-client-cb:not(:checked)');
    checkboxes.forEach(cb => {
        if (VB.selectedContacts.size < VB.remaining) {
            cb.checked = true;
            toggleClient(cb);
        }
    });
}

function deselectAllClients() {
    document.querySelectorAll('.vb-client-cb:checked').forEach(cb => {
        cb.checked = false;
        VB.selectedContacts.delete(parseInt(cb.dataset.id));
    });
    updateSelectedCount();
    updateSendButton();
}

function updateSelectedCount() {
    const count = VB.selectedContacts.size;
    document.getElementById('vb-selected-count').textContent = count + ' selecionado' + (count !== 1 ? 's' : '');
}

function updateSendButton() {
    const btn = document.getElementById('vb-send-btn');
    const hasAccount = document.getElementById('vb-account-select').value;
    const hasTemplate = document.getElementById('vb-template-select').value;
    const hasContacts = VB.selectedContacts.size > 0;
    btn.disabled = !(hasAccount && hasTemplate && hasContacts);
}

// === Enviar disparo ===
function sendBroadcast() {
    const accountId = parseInt(document.getElementById('vb-account-select').value);
    const templateName = document.getElementById('vb-template-select').value;
    const templateOpt = document.getElementById('vb-template-select').selectedOptions[0];
    const templateLanguage = templateOpt ? (templateOpt.dataset.language || 'pt_BR') : 'pt_BR';
    const contacts = Array.from(VB.selectedContacts.values());

    // Coletar parametros
    const paramInputs = document.querySelectorAll('.vb-param-input');
    const templateParams = [];
    paramInputs.forEach(input => {
        templateParams.push(input.value || '');
    });

    if (!accountId || !templateName || contacts.length === 0) return;

    Swal.fire({
        title: 'Confirmar Disparo',
        html: '<p>Enviar template <strong>' + escapeHtml(templateName) + '</strong> para <strong>' + contacts.length + '</strong> contato' + (contacts.length > 1 ? 's' : '') + '?</p>' +
              '<p class="text-muted fs-8">Esta acao nao pode ser desfeita.</p>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#50cd89',
    }).then(result => {
        if (!result.isConfirmed) return;

        const btn = document.getElementById('vb-send-btn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Enviando...';

        fetch(VB.urls.send, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                account_id: accountId,
                template_name: templateName,
                template_language: templateLanguage,
                contacts: contacts,
                template_params: templateParams,
            })
        })
        .then(r => r.json())
        .then(j => {
            btn.innerHTML = '<i class="ki-duotone ki-send fs-3 me-2"><span class="path1"></span><span class="path2"></span></i> Enviar Disparo';
            if (j.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Disparo enviado!',
                    html: '<p>' + escapeHtml(j.message || '') + '</p>' +
                          (j.errors && j.errors.length > 0 ? '<div class="text-start fs-8 text-danger mt-2">' + j.errors.map(e => escapeHtml(e)).join('<br>') + '</div>' : ''),
                    confirmButtonText: 'OK'
                }).then(() => { location.reload(); });
            } else {
                Swal.fire({ icon: 'error', title: 'Erro', text: j.message || 'Erro ao enviar disparo' });
                btn.disabled = false;
            }
        })
        .catch(e => {
            btn.innerHTML = '<i class="ki-duotone ki-send fs-3 me-2"><span class="path1"></span><span class="path2"></span></i> Enviar Disparo';
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Erro', text: e.message || 'Erro de rede' });
        });
    });
}

// === Detalhes do disparo ===
function viewBroadcastDetails(id) {
    const body = document.getElementById('modal_broadcast_details_body');
    body.innerHTML = '<div class="text-center py-10"><span class="spinner-border text-primary"></span></div>';
    const modal = new bootstrap.Modal(document.getElementById('modal_broadcast_details'));
    modal.show();

    const u = new URL(VB.urls.details, location.origin);
    u.searchParams.set('id', id);

    fetch(u.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(j => {
            if (!j.success) throw new Error(j.message);
            const d = j.data;
            const msgs = d.messages || [];
            const statusColors = { sent: 'success', delivered: 'info', read: 'primary', failed: 'danger', pending: 'warning', skipped: 'secondary' };
            const statusLabels = { sent: 'Enviado', delivered: 'Entregue', read: 'Lido', failed: 'Falhou', pending: 'Pendente', skipped: 'Pulado' };

            let html = '<div class="mb-4">';
            html += '<div class="row g-3">';
            html += '<div class="col-4"><div class="border rounded p-3 text-center"><div class="fw-bold text-gray-800">' + (d.total_contacts || 0) + '</div><div class="text-muted fs-8">Contatos</div></div></div>';
            html += '<div class="col-4"><div class="border rounded p-3 text-center"><div class="fw-bold text-success">' + (d.total_sent || 0) + '</div><div class="text-muted fs-8">Enviados</div></div></div>';
            html += '<div class="col-4"><div class="border rounded p-3 text-center"><div class="fw-bold text-danger">' + (d.total_failed || 0) + '</div><div class="text-muted fs-8">Falharam</div></div></div>';
            html += '</div></div>';

            if (msgs.length > 0) {
                html += '<div class="table-responsive"><table class="table table-sm table-row-dashed gs-0 gy-2">';
                html += '<thead><tr class="text-muted fs-8 fw-bold"><th>Contato</th><th>Telefone</th><th class="text-center">Status</th><th>Erro</th></tr></thead><tbody>';
                msgs.forEach(m => {
                    const st = m.status || 'pending';
                    html += '<tr>';
                    html += '<td class="fs-7">' + escapeHtml(m.contact_name || '-') + '</td>';
                    html += '<td class="fs-7">' + escapeHtml(m.contact_phone || '-') + '</td>';
                    html += '<td class="text-center"><span class="badge badge-light-' + (statusColors[st]||'secondary') + '">' + (statusLabels[st]||st) + '</span></td>';
                    html += '<td class="fs-8 text-danger">' + escapeHtml(m.error_message || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }

            body.innerHTML = html;
        })
        .catch(e => {
            body.innerHTML = '<div class="alert alert-danger">' + escapeHtml(e.message) + '</div>';
        });
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}

// Carregar clientes ao abrir a pagina
document.addEventListener('DOMContentLoaded', function() {
    loadClients();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/metronic/app.php';
?>
