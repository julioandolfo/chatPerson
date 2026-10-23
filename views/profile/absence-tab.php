<?php
/**
 * Aba "Ausência": o agente agenda períodos em que estará fora e a mensagem automática
 * que o cliente recebe ("Estou ausente, retorno às 11:00").
 */
$absenceUserName = $user['name'] ?? (\App\Helpers\Auth::userField('name') ?? '');
$absenceConfig = [
    'url' => \App\Helpers\Url::to('profile/absences'),
    'conversationsUrl' => \App\Helpers\Url::to('conversations'),
    'agentName' => $absenceUserName,
];
?>
<div id="absence_root">
    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-7">
        <i class="ki-duotone ki-time fs-2tx text-primary me-4"><span class="path1"></span><span class="path2"></span></i>
        <div class="fs-6 text-gray-700">
            Durante o período de ausência, quando um cliente escrever numa conversa <strong>atribuída a você</strong>,
            ele recebe a mensagem abaixo depois do tempo de espera escolhido — <strong>uma vez por conversa</strong>, não a cada mensagem.
            Se você (ou outro agente) responder antes do envio, a mensagem não sai. Conversas atendidas por agente de IA são ignoradas.
        </div>
    </div>

    <!-- Ausência ativa agora -->
    <div id="absence_active" class="alert alert-warning d-none align-items-center gap-4 mb-7">
        <span class="fs-2x">🌙</span>
        <div class="flex-grow-1">
            <div class="fw-bold fs-5" id="absence_active_title"></div>
            <div class="fs-7 text-gray-700" id="absence_active_info"></div>
        </div>
        <button type="button" class="btn btn-sm btn-light-warning fw-bold flex-shrink-0" id="absence_active_end">Voltei — encerrar agora</button>
    </div>

    <div class="row g-7">
        <!-- Formulário -->
        <div class="col-12 col-xl-6">
            <div class="border border-gray-300 border-dashed rounded p-6">
                <h4 class="fw-bold mb-5" id="absence_form_title">Agendar ausência</h4>
                <input type="hidden" id="absence_id" value="" />

                <div class="row g-4 mb-2">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold required" for="absence_starts">Ausente de</label>
                        <input type="datetime-local" class="form-control form-control-solid" id="absence_starts" />
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold required" for="absence_ends">Até (retorno)</label>
                        <input type="datetime-local" class="form-control form-control-solid" id="absence_ends" />
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-6" id="absence_presets">
                    <button type="button" class="btn btn-sm btn-light" data-preset="now">Começar agora</button>
                    <button type="button" class="btn btn-sm btn-light" data-preset="1h">Agora por 1 h</button>
                    <button type="button" class="btn btn-sm btn-light" data-preset="4h">Agora por 4 h</button>
                    <button type="button" class="btn btn-sm btn-light" data-preset="tomorrow">Amanhã o dia todo</button>
                </div>

                <label class="form-label fw-semibold required" for="absence_message">Mensagem</label>
                <textarea class="form-control form-control-solid" id="absence_message" rows="4" maxlength="4000"></textarea>
                <div class="d-flex flex-wrap gap-2 mt-2 mb-3" id="absence_vars">
                    <span class="text-muted fs-8 me-1 align-self-center">Inserir:</span>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8" data-var="{{contact.name}}">nome do cliente</button>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8" data-var="{{absence.return}}">retorno (ex.: hoje às 11:00)</button>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8" data-var="{{absence.return_time}}">hora do retorno</button>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8" data-var="{{absence.return_date}}">data do retorno</button>
                    <button type="button" class="btn btn-sm btn-light-primary py-1 px-2 fs-8" data-var="{{agent.name}}">seu nome</button>
                </div>
                <div class="bg-light rounded p-4 mb-6">
                    <div class="text-muted fs-8 fw-semibold mb-1">PRÉVIA (cliente "Maria")</div>
                    <div class="fs-6 text-gray-800" id="absence_preview" style="white-space: pre-wrap"></div>
                </div>

                <div class="row g-4 mb-6">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold" for="absence_delay_value">Enviar depois da mensagem do cliente</label>
                        <div class="d-flex gap-2">
                            <input type="number" min="0" class="form-control form-control-solid w-100px" id="absence_delay_value" value="10" />
                            <select class="form-select form-select-solid" id="absence_delay_unit">
                                <option value="1">segundos</option>
                                <option value="60">minutos</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fw-semibold" for="absence_resend">Se o mesmo cliente escrever de novo</label>
                        <select class="form-select form-select-solid" id="absence_resend">
                            <option value="0">Não enviar de novo nesta ausência</option>
                            <option value="12">Enviar de novo após 12 h</option>
                            <option value="24">Enviar de novo após 24 h</option>
                            <option value="48">Enviar de novo após 48 h</option>
                            <option value="168">Enviar de novo após 7 dias</option>
                        </select>
                    </div>
                </div>

                <div class="form-check form-switch form-check-custom form-check-solid mb-6 d-none" id="absence_enabled_row">
                    <input class="form-check-input" type="checkbox" id="absence_enabled" checked />
                    <label class="form-check-label" for="absence_enabled">Ativa</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="absence_save">
                        <span class="indicator-label">Agendar ausência</span>
                        <span class="indicator-progress">Salvando… <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                    <button type="button" class="btn btn-light d-none" id="absence_cancel_edit">Cancelar edição</button>
                </div>
            </div>
        </div>

        <!-- Lista -->
        <div class="col-12 col-xl-6">
            <div class="border border-gray-300 border-dashed rounded p-6 h-100">
                <h4 class="fw-bold mb-5">Minhas ausências</h4>
                <div id="absence_list" class="d-flex flex-column gap-4">
                    <div class="text-muted">Carregando…</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const cfg = <?= json_encode($absenceConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    const $ = (id) => document.getElementById(id);
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    let defaultMessage = '';
    let absences = [];

    const STATE = {
        active: ['Ativa agora', 'badge-light-warning'],
        scheduled: ['Agendada', 'badge-light-primary'],
        paused: ['Desativada', 'badge-light-secondary'],
        ended: ['Encerrada', 'badge-light']
    };
    const REPLY_STATUS = {
        sent: ['Enviada', 'badge-light-success'],
        pending: ['Aguardando', 'badge-light-primary'],
        sending: ['Enviando', 'badge-light-primary'],
        answered: ['Respondida antes', 'badge-light-info'],
        cancelled: ['Não enviada', 'badge-light'],
        failed: ['Falhou', 'badge-light-danger']
    };

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function pad(n) { return String(n).padStart(2, '0'); }
    function toLocalInput(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }
    function fromLocalInput(value) {
        const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(value || '');
        return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) : null;
    }

    function returnLabel(date) {
        if (!date) return '…';
        const time = pad(date.getHours()) + ':' + pad(date.getMinutes());
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const day = new Date(date); day.setHours(0, 0, 0, 0);
        const diff = Math.round((day - today) / 86400000);
        if (diff === 0) return 'hoje às ' + time;
        if (diff === 1) return 'amanhã às ' + time;
        return 'em ' + pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + ' às ' + time;
    }

    function renderPreview() {
        const end = fromLocalInput($('absence_ends').value);
        const time = end ? pad(end.getHours()) + ':' + pad(end.getMinutes()) : '…';
        const date = end ? pad(end.getDate()) + '/' + pad(end.getMonth() + 1) + '/' + end.getFullYear() : '…';
        const text = ($('absence_message').value || '')
            .split('{{contact.name}}').join('Maria')
            .split('{{agent.name}}').join(cfg.agentName || 'Agente')
            .split('{{absence.return}}').join(returnLabel(end))
            .split('{{absence.return_time}}').join(time)
            .split('{{absence.return_date}}').join(date);
        $('absence_preview').textContent = text || '—';
    }

    function delayLabel(seconds) {
        if (seconds === 0) return 'na hora';
        if (seconds % 60 === 0) return seconds / 60 + ' min';
        return seconds + ' s';
    }

    function resetForm() {
        $('absence_id').value = '';
        $('absence_form_title').textContent = 'Agendar ausência';
        $('absence_save').querySelector('.indicator-label').textContent = 'Agendar ausência';
        $('absence_cancel_edit').classList.add('d-none');
        $('absence_enabled_row').classList.add('d-none');
        $('absence_enabled').checked = true;
        const start = new Date(); start.setSeconds(0, 0);
        const end = new Date(start.getTime() + 60 * 60000);
        $('absence_starts').value = toLocalInput(start);
        $('absence_ends').value = toLocalInput(end);
        $('absence_message').value = defaultMessage;
        $('absence_delay_value').value = 10;
        $('absence_delay_unit').value = '1';
        $('absence_resend').value = '0';
        renderPreview();
    }

    function editAbsence(a) {
        $('absence_id').value = a.id;
        $('absence_form_title').textContent = 'Editar ausência';
        $('absence_save').querySelector('.indicator-label').textContent = 'Salvar alterações';
        $('absence_cancel_edit').classList.remove('d-none');
        $('absence_enabled_row').classList.remove('d-none');
        $('absence_enabled').checked = a.enabled;
        $('absence_starts').value = a.starts_at;
        $('absence_ends').value = a.ends_at;
        $('absence_message').value = a.message;
        const minutes = a.delay_seconds > 0 && a.delay_seconds % 60 === 0;
        $('absence_delay_value').value = minutes ? a.delay_seconds / 60 : a.delay_seconds;
        $('absence_delay_unit').value = minutes ? '60' : '1';
        const resend = String(a.resend_after_hours);
        if (!Array.from($('absence_resend').options).some(o => o.value === resend)) {
            const opt = document.createElement('option');
            opt.value = resend;
            opt.textContent = 'Enviar de novo após ' + resend + ' h';
            $('absence_resend').appendChild(opt);
        }
        $('absence_resend').value = resend;
        renderPreview();
        $('absence_form_title').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function renderList() {
        const list = $('absence_list');
        const active = absences.find(a => a.state === 'active');
        if (active) {
            $('absence_active').classList.remove('d-none');
            $('absence_active').classList.add('d-flex');
            $('absence_active_title').textContent = 'Você está ausente até ' + returnLabel(fromLocalInput(active.ends_at));
            $('absence_active_info').textContent = 'Mensagens automáticas enviadas: ' + (active.sent_count || 0)
                + (active.pending_count ? ' · aguardando envio: ' + active.pending_count : '');
            $('absence_active_end').dataset.id = active.id;
        } else {
            $('absence_active').classList.add('d-none');
            $('absence_active').classList.remove('d-flex');
        }

        if (!absences.length) {
            list.innerHTML = '<div class="text-muted">Nenhuma ausência agendada.</div>';
            return;
        }
        list.innerHTML = absences.map(a => {
            const st = STATE[a.state] || STATE.ended;
            const canEdit = a.state !== 'ended';
            const sent = a.sent_count || 0;
            return '<div class="border rounded p-4" data-absence-id="' + a.id + '">' +
                '<div class="d-flex align-items-center flex-wrap gap-2 mb-2">' +
                    '<span class="fw-bold fs-6">' + esc(a.period_label) + '</span>' +
                    '<span class="badge ' + st[1] + '">' + st[0] + '</span>' +
                    '<span class="text-muted fs-8 ms-auto">envia ' + delayLabel(a.delay_seconds) + ' depois' +
                        (a.resend_after_hours ? ' · repete após ' + a.resend_after_hours + ' h' : ' · 1 vez por conversa') + '</span>' +
                '</div>' +
                '<div class="text-gray-700 fs-7 mb-3" style="white-space: pre-wrap">' + esc(a.message.length > 220 ? a.message.slice(0, 220) + '…' : a.message) + '</div>' +
                '<div class="d-flex flex-wrap gap-2">' +
                    '<button type="button" class="btn btn-sm btn-light" data-action="replies"' + (sent || a.pending_count ? '' : ' disabled') + '>' +
                        'Enviadas: ' + sent + (a.pending_count ? ' (+' + a.pending_count + ')' : '') + '</button>' +
                    (canEdit ? '<button type="button" class="btn btn-sm btn-light-primary" data-action="edit">Editar</button>' : '') +
                    (a.state === 'active' ? '<button type="button" class="btn btn-sm btn-light-warning" data-action="end">Encerrar agora</button>' : '') +
                    '<button type="button" class="btn btn-sm btn-light-danger ms-auto" data-action="delete">Excluir</button>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function load() {
        return fetch(cfg.url, { headers })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    $('absence_list').innerHTML = '<div class="text-danger">' + esc(data.message || 'Erro ao carregar') + '</div>';
                    return;
                }
                const first = !defaultMessage;
                defaultMessage = data.default_message;
                absences = data.absences || [];
                if (first) resetForm();
                renderList();
            })
            .catch(() => { $('absence_list').innerHTML = '<div class="text-danger">Erro ao carregar as ausências.</div>'; });
    }

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
            body: JSON.stringify(body || {})
        }).then(r => r.json().catch(() => ({ success: false, message: 'Resposta inválida do servidor' })));
    }

    function notify(ok, message) {
        if (window.Swal) {
            Swal.fire({ icon: ok ? 'success' : 'warning', text: message, timer: ok ? 1800 : undefined, showConfirmButton: !ok, confirmButtonText: 'OK' });
        } else {
            alert(message);
        }
    }

    function save() {
        const delay = (parseInt($('absence_delay_value').value, 10) || 0) * (parseInt($('absence_delay_unit').value, 10) || 1);
        const payload = {
            starts_at: $('absence_starts').value,
            ends_at: $('absence_ends').value,
            message: $('absence_message').value,
            delay_seconds: delay,
            resend_after_hours: parseInt($('absence_resend').value, 10) || 0,
            enabled: $('absence_enabled').checked
        };
        const id = $('absence_id').value;
        const btn = $('absence_save');
        btn.setAttribute('data-kt-indicator', 'on');
        btn.disabled = true;
        post(cfg.url + (id ? '/' + id : ''), payload)
            .then(data => {
                notify(data.success, data.message || (data.success ? 'Salvo' : 'Erro ao salvar'));
                if (data.success) {
                    resetForm();
                    load();
                }
            })
            .finally(() => { btn.removeAttribute('data-kt-indicator'); btn.disabled = false; });
    }

    function confirmThen(text, confirmText, fn) {
        if (!window.Swal) {
            if (confirm(text)) fn();
            return;
        }
        Swal.fire({ icon: 'question', text, showCancelButton: true, confirmButtonText: confirmText, cancelButtonText: 'Voltar' })
            .then(r => { if (r.isConfirmed) fn(); });
    }

    function endNow(id) {
        confirmThen('Encerrar a ausência agora? Os clientes deixam de receber a mensagem automática.', 'Encerrar', () =>
            post(cfg.url + '/' + id + '/end').then(data => { notify(data.success, data.message); load(); }));
    }

    function showReplies(a) {
        fetch(cfg.url + '/' + a.id + '/replies', { headers })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return notify(false, data.message || 'Erro ao carregar');
                const rows = (data.replies || []).map(r => {
                    const st = REPLY_STATUS[r.status] || [r.status, 'badge-light'];
                    const when = new Date(r.at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                    return '<tr><td><a href="' + cfg.conversationsUrl + '?id=' + r.conversation_id + '">' + esc(r.contact_name) + '</a></td>' +
                        '<td><span class="badge ' + st[1] + '">' + st[0] + '</span>' + (r.reason ? '<div class="text-muted fs-8">' + esc(r.reason) + '</div>' : '') + '</td>' +
                        '<td class="text-muted">' + when + '</td></tr>';
                }).join('');
                const html = rows
                    ? '<table class="table table-row-dashed fs-7 text-start mb-0"><thead><tr class="fw-bold text-muted"><th>Cliente</th><th>Situação</th><th>Quando</th></tr></thead><tbody>' + rows + '</tbody></table>'
                    : '<div class="text-muted">Nenhum envio.</div>';
                if (window.Swal) Swal.fire({ title: 'Mensagens de ausência', html, width: 640, confirmButtonText: 'Fechar' });
            });
    }

    $('absence_list').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        const id = parseInt(btn.closest('[data-absence-id]').dataset.absenceId, 10);
        const a = absences.find(x => x.id === id);
        if (!a) return;
        const action = btn.dataset.action;
        if (action === 'edit') editAbsence(a);
        else if (action === 'end') endNow(id);
        else if (action === 'replies') showReplies(a);
        else if (action === 'delete') {
            confirmThen('Excluir esta ausência?', 'Excluir', () =>
                post(cfg.url + '/' + id + '/delete').then(data => {
                    notify(data.success, data.message);
                    if (String($('absence_id').value) === String(id)) resetForm();
                    load();
                }));
        }
    });

    $('absence_presets').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-preset]');
        if (!btn) return;
        const now = new Date(); now.setSeconds(0, 0);
        const preset = btn.dataset.preset;
        if (preset === 'tomorrow') {
            const start = new Date(now); start.setDate(start.getDate() + 1); start.setHours(0, 0, 0, 0);
            const end = new Date(start); end.setHours(23, 59, 0, 0);
            $('absence_starts').value = toLocalInput(start);
            $('absence_ends').value = toLocalInput(end);
        } else {
            $('absence_starts').value = toLocalInput(now);
            if (preset === '1h' || preset === '4h') {
                $('absence_ends').value = toLocalInput(new Date(now.getTime() + (preset === '1h' ? 1 : 4) * 3600000));
            }
        }
        renderPreview();
    });

    $('absence_vars').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-var]');
        if (!btn) return;
        const ta = $('absence_message');
        const start = ta.selectionStart ?? ta.value.length;
        const end = ta.selectionEnd ?? ta.value.length;
        ta.value = ta.value.slice(0, start) + btn.dataset.var + ta.value.slice(end);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = start + btn.dataset.var.length;
        renderPreview();
    });

    ['absence_message', 'absence_ends'].forEach(id => $(id).addEventListener('input', renderPreview));
    $('absence_save').addEventListener('click', save);
    $('absence_cancel_edit').addEventListener('click', resetForm);
    $('absence_active_end').addEventListener('click', () => endNow($('absence_active_end').dataset.id));

    load();
})();
</script>
