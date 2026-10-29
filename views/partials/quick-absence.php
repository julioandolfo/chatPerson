<?php
/**
 * Botão de ausência rápida no topo ("Sair para almoço" / "Voltei").
 * Os modelos (Almoço 1 h, Pausa 15 min...) ficam em Perfil → Ausência.
 */
$qaState = null;
try {
    if (\App\Helpers\Auth::check()) {
        $qaCanManage = \App\Helpers\Permission::isAdmin() || \App\Helpers\Permission::can('settings.manage');
        $qaState = \App\Services\AgentAbsenceService::quickState((int)\App\Helpers\Auth::id(), $qaCanManage);
    }
} catch (\Throwable $e) {
    $qaState = null; // tabelas ainda não criadas
}
if ($qaState === null) {
    return;
}
$qaConfig = [
    'state' => $qaState,
    'quickUrl' => \App\Helpers\Url::to('profile/absences/quick'),
    'presetsUrl' => \App\Helpers\Url::to('profile/absence-presets'),
    'absencesUrl' => \App\Helpers\Url::to('profile/absences'),
    'settingsUrl' => \App\Helpers\Url::to('profile?tab=absence'),
];
?>
<style>
#qaWidget .qa-pill { display: none; align-items: center; gap: 6px; height: 40px; padding: 0 12px; border-radius: 8px; font-weight: 600; white-space: nowrap;
    background: #fff8dd; color: #b88a00; border: 1px solid #f6c000; animation: qaPulse 2.4s ease-in-out infinite; }
[data-bs-theme="dark"] #qaWidget .qa-pill { background: #392f10; color: #f6c000; border-color: #8a6d00; }
#qaWidget.qa-away .qa-pill { display: inline-flex; }
#qaWidget.qa-away .qa-idle { display: none; }
#qaWidget .qa-menu { min-width: 260px; }
#qaWidget .qa-preset { display: flex; align-items: center; gap: 10px; width: 100%; border: 0; background: none; text-align: left; padding: 8px 12px; border-radius: 6px; color: inherit; }
#qaWidget .qa-preset:hover { background: var(--bs-gray-100, #f9f9f9); }
#qaWidget .qa-preset .qa-emoji { font-size: 20px; width: 26px; text-align: center; }
#qaWidget .qa-preset .qa-dur { margin-left: auto; color: var(--bs-text-muted, #a1a5b7); font-size: 12px; }
@keyframes qaPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(246, 192, 0, .0); } 50% { box-shadow: 0 0 0 4px rgba(246, 192, 0, .25); } }
@media (max-width: 575.98px) { #qaWidget .qa-pill-label { display: none; } #qaWidget .qa-pill { padding: 0 10px; } }
@media (prefers-reduced-motion: reduce) { #qaWidget .qa-pill { animation: none; } }
</style>
<div class="d-flex align-items-center ms-3 ms-lg-4 dropdown" id="qaWidget">
    <button type="button" class="btn btn-icon btn-color-gray-700 btn-active-color-primary btn-outline w-40px h-40px qa-idle"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Ausência rápida (almoço, pausa...)">
        <span class="fs-3">☕</span>
    </button>
    <button type="button" class="btn qa-pill" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Você está ausente — clique para voltar">
        <span class="qa-pill-icon fs-4"></span>
        <span class="qa-pill-label"></span>
        <span class="qa-pill-time"></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end qa-menu p-3 shadow"></div>
</div>
<script>
(function () {
    const cfg = <?= json_encode($qaConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const root = document.getElementById('qaWidget');
    if (!root) return;
    const menu = root.querySelector('.qa-menu');
    const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    let state = cfg.state;
    let busy = false;

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function toast(ok, text) {
        if (typeof window.showToast === 'function') {
            window.showToast(ok ? 'success' : 'error', text);
        } else if (window.Swal) {
            Swal.fire({ icon: ok ? 'success' : 'warning', text, timer: ok ? 2200 : undefined, showConfirmButton: !ok, toast: ok, position: ok ? 'top-end' : 'center' });
        } else {
            alert(text);
        }
    }

    function render() {
        const a = state.active;
        root.classList.toggle('qa-away', !!a);
        if (a) {
            root.querySelector('.qa-pill-icon').textContent = a.icon || '🌙';
            root.querySelector('.qa-pill-label').textContent = a.label || 'Ausente';
            root.querySelector('.qa-pill-time').textContent = 'até ' + a.ends_time;
            menu.innerHTML =
                '<div class="px-2 pb-3">' +
                    '<div class="fw-bold fs-6">' + esc((a.icon || '🌙') + ' ' + (a.label || 'Ausente')) + ' até ' + esc(a.ends_time) + '</div>' +
                    '<div class="text-muted fs-7">Clientes que escreverem recebem a mensagem automática' +
                        (a.sent_count ? ' (' + a.sent_count + ' enviada' + (a.sent_count > 1 ? 's' : '') + ')' : '') + '.</div>' +
                '</div>' +
                '<button type="button" class="btn btn-success w-100 fw-bold" data-qa-end="' + a.id + '">👋 Voltei</button>' +
                '<div class="text-center mt-3"><a class="fs-7" href="' + esc(cfg.settingsUrl) + '">Configurar ausências</a></div>';
        } else {
            const presets = state.presets || [];
            menu.innerHTML =
                '<div class="text-muted fs-8 fw-bold text-uppercase px-3 pb-2">Sair agora</div>' +
                (presets.length
                    ? presets.map(p =>
                        '<button type="button" class="qa-preset" data-qa-start="' + p.id + '">' +
                            '<span class="qa-emoji">' + esc(p.icon) + '</span>' +
                            '<span class="fw-semibold">' + esc(p.name) + '</span>' +
                            '<span class="qa-dur">' + esc(p.duration_label) + '</span>' +
                        '</button>').join('')
                    : '<div class="px-3 py-2 text-muted fs-7">Nenhuma ausência rápida criada.</div>') +
                '<div class="separator my-2"></div>' +
                '<a class="d-block px-3 py-1 fs-7" href="' + esc(cfg.settingsUrl) + '">Criar ou agendar ausências</a>';
        }
    }

    function hideMenu() {
        root.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(btn => {
            try { bootstrap.Dropdown.getOrCreateInstance(btn).hide(); } catch (e) { /* sem bootstrap */ }
        });
    }

    function post(url) {
        return fetch(url, { method: 'POST', headers: Object.assign({ 'Content-Type': 'application/json' }, headers), body: '{}' })
            .then(r => r.json().catch(() => ({ success: false, message: 'Resposta inválida do servidor' })));
    }

    function refresh() {
        return fetch(cfg.quickUrl, { headers })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    state = { active: data.active, presets: data.presets };
                    render();
                }
            })
            .catch(() => {});
    }

    menu.addEventListener('click', (e) => {
        const start = e.target.closest('[data-qa-start]');
        const end = e.target.closest('[data-qa-end]');
        if ((!start && !end) || busy) return;
        busy = true;
        const req = start
            ? post(cfg.presetsUrl + '/' + start.dataset.qaStart + '/start')
            : post(cfg.absencesUrl + '/' + end.dataset.qaEnd + '/end');
        req.then(data => {
            toast(!!data.success, data.success
                ? (start ? data.message + '. Clique em "Voltei" quando retornar.' : 'Bem-vindo de volta! Ausência encerrada.')
                : (data.message || 'Não foi possível concluir.'));
            hideMenu();
            return refresh().then(() => {
                window.dispatchEvent(new CustomEvent('quick-absence:changed', { detail: state }));
            });
        }).finally(() => { busy = false; });
    });

    render();
    // A ausência acaba sozinha no horário: atualiza o botão
    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refresh(); });
    window.QuickAbsence = { refresh };
})();
</script>
