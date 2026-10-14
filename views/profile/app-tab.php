<?php
/**
 * Aba "App e aparelhos": instalar o app (PWA), notificações push deste aparelho e
 * aparelhos conectados com "Manter conectado".
 */
?>
<div class="row g-6">
    <!-- Instalar -->
    <div class="col-12 col-lg-6">
        <div class="border border-gray-300 border-dashed rounded p-6 h-100">
            <h4 class="fw-bold mb-2">Instalar o app</h4>
            <p class="text-gray-600 fs-6 mb-5">Abra o sistema direto nas Conversas, em tela cheia, como um aplicativo do celular ou do computador.</p>
            <div id="pwa_install_status" class="fs-6 mb-4"></div>
            <button type="button" class="btn btn-primary btn-sm d-none" data-pwa-install>
                <i class="ki-duotone ki-cloud-download fs-4"><span class="path1"></span><span class="path2"></span></i> Instalar app
            </button>
        </div>
    </div>

    <!-- Notificações -->
    <div class="col-12 col-lg-6">
        <div class="border border-gray-300 border-dashed rounded p-6 h-100">
            <h4 class="fw-bold mb-2">Notificações neste aparelho</h4>
            <p class="text-gray-600 fs-6 mb-5">Receba as novas mensagens mesmo com o app ou o navegador fechado.</p>
            <div id="pwa_push_status" class="fs-6 mb-4">Verificando…</div>
            <div class="form-check form-switch form-check-custom form-check-solid mb-5 d-none" id="pwa_push_preview_row">
                <input class="form-check-input" type="checkbox" id="pwa_push_preview" checked />
                <label class="form-check-label" for="pwa_push_preview">Mostrar o texto da mensagem na notificação</label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary btn-sm d-none" id="pwa_push_enable">Ativar notificações</button>
                <button type="button" class="btn btn-light-primary btn-sm d-none" id="pwa_push_test">Enviar teste</button>
                <button type="button" class="btn btn-light-danger btn-sm d-none" id="pwa_push_disable">Desativar</button>
            </div>
        </div>
    </div>

    <!-- Aparelhos -->
    <div class="col-12">
        <div class="border border-gray-300 border-dashed rounded p-6">
            <h4 class="fw-bold mb-2">Aparelhos conectados</h4>
            <p class="text-gray-600 fs-6 mb-5">Aparelhos em que você entrou com <b>Manter conectado</b> (ficam logados por até 30 dias sem uso). Trocar a senha desconecta todos.</p>
            <div id="pwa_devices" class="fs-6 text-gray-600">Carregando…</div>
        </div>
    </div>
</div>

<script>
(function () {
    // pwa.js é carregado no fim da página: ler a configuração só quando for usar
    function routes() { return (window.CP_PWA && window.CP_PWA.routes) || {}; }
    var revokeBase = <?= json_encode(\App\Helpers\Url::to('pwa/devices')) ?>;

    function esc(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }
    function fmt(dt) {
        if (!dt) return '—';
        var d = new Date(String(dt).replace(' ', 'T'));
        return isNaN(d) ? dt : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }
    function notify(ok, message) {
        if (typeof window.showToast === 'function') { window.showToast(ok ? 'success' : 'error', message); return; }
        if (window.Swal) { window.Swal.fire({ icon: ok ? 'success' : 'error', text: message, timer: 3000, showConfirmButton: false, toast: true, position: 'top-end' }); return; }
        alert(message);
    }
    function show(id, visible) {
        var el = document.getElementById(id);
        if (el) el.classList.toggle('d-none', !visible);
    }

    function renderInstall() {
        var el = document.getElementById('pwa_install_status');
        if (!el || !window.CPApp) return;
        if (window.CPApp.isStandalone) {
            el.innerHTML = '<span class="badge badge-light-success">Você está usando o app instalado</span>';
        } else if (window.CPApp.canInstall()) {
            el.innerHTML = window.CPApp.isIOS
                ? 'No iPhone/iPad: <b>Compartilhar → Adicionar à Tela de Início</b> (o botão abaixo mostra o passo a passo).'
                : 'Pronto para instalar neste aparelho.';
        } else {
            el.innerHTML = 'Se o botão não aparecer, use o menu do navegador → <b>Instalar app</b> / <b>Adicionar à tela inicial</b>. Ele pode já estar instalado.';
        }
    }

    function renderPush() {
        var status = document.getElementById('pwa_push_status');
        if (!window.CPPush) { status.textContent = 'Indisponível.'; return; }
        window.CPPush.state().then(function (st) {
            show('pwa_push_enable', false); show('pwa_push_disable', false); show('pwa_push_test', false); show('pwa_push_preview_row', false);
            if (!st.supported) {
                status.innerHTML = st.needsInstall
                    ? 'No iPhone as notificações só funcionam com o <b>app instalado</b> (iOS 16.4 ou superior). Instale e abra pelo ícone na tela de início.'
                    : 'Este navegador não suporta notificações push.';
                return;
            }
            if (st.permission === 'denied') {
                status.innerHTML = '<span class="text-danger">Notificações bloqueadas.</span> Libere nas configurações do navegador/aparelho para este site.';
                return;
            }
            if (st.subscribed) {
                status.innerHTML = '<span class="badge badge-light-success">Ativadas neste aparelho</span>';
                document.getElementById('pwa_push_preview').checked = st.show_preview !== false;
                show('pwa_push_disable', true); show('pwa_push_test', true); show('pwa_push_preview_row', true);
            } else {
                status.innerHTML = '<span class="badge badge-light-warning">Desativadas neste aparelho</span>';
                show('pwa_push_enable', true); show('pwa_push_preview_row', true);
            }
        }).catch(function () { status.textContent = 'Não foi possível verificar.'; });
    }

    function busy(id, on) {
        var b = document.getElementById(id);
        if (b) b.disabled = on;
    }

    document.getElementById('pwa_push_enable').addEventListener('click', function () {
        busy('pwa_push_enable', true);
        window.CPPush.enable(document.getElementById('pwa_push_preview').checked)
            .then(function (res) { notify(true, res.message || 'Notificações ativadas'); })
            .catch(function (err) { notify(false, err.message || 'Não foi possível ativar'); })
            .finally(function () { busy('pwa_push_enable', false); renderPush(); });
    });
    document.getElementById('pwa_push_disable').addEventListener('click', function () {
        busy('pwa_push_disable', true);
        window.CPPush.disable()
            .then(function () { notify(true, 'Notificações desativadas neste aparelho'); })
            .catch(function (err) { notify(false, err.message || 'Erro ao desativar'); })
            .finally(function () { busy('pwa_push_disable', false); renderPush(); });
    });
    document.getElementById('pwa_push_test').addEventListener('click', function () {
        busy('pwa_push_test', true);
        window.CPPush.test()
            .then(function (res) { notify(!!res.success, res.message || ''); })
            .catch(function () { notify(false, 'Erro ao enviar o teste'); })
            .finally(function () { busy('pwa_push_test', false); });
    });
    document.getElementById('pwa_push_preview').addEventListener('change', function () {
        var value = this.checked;
        window.CPPush.state().then(function (st) {
            if (!st.subscribed) return; // ainda não ativado: vale na ativação
            return window.CPPush.setShowPreview(value).then(function (res) { notify(!!res.success, res.message || ''); });
        }).catch(function (err) { notify(false, err.message || 'Erro ao salvar'); });
    });

    function loadDevices() {
        var box = document.getElementById('pwa_devices');
        fetch(routes().devices, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var devices = (data && data.devices) || [];
                if (!devices.length) {
                    box.innerHTML = 'Nenhum aparelho com "Manter conectado".';
                    return;
                }
                box.innerHTML = '<div class="table-responsive"><table class="table table-row-dashed align-middle fs-6 mb-0"><thead><tr class="text-muted fw-semibold">'
                    + '<th>Aparelho</th><th>Último uso</th><th class="d-none d-md-table-cell">Desde</th><th></th></tr></thead><tbody>'
                    + devices.map(function (d) {
                        return '<tr><td>' + esc(d.device) + (d.is_current ? ' <span class="badge badge-light-primary ms-2">este aparelho</span>' : '')
                            + '<div class="text-muted fs-7">' + esc(d.ip_address || '') + '</div></td>'
                            + '<td>' + esc(fmt(d.last_used_at)) + '</td><td class="d-none d-md-table-cell">' + esc(fmt(d.created_at)) + '</td>'
                            + '<td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-revoke="' + d.id + '" title="Desconectar"><span class="d-none d-sm-inline">Desconectar</span><span class="d-sm-none">Sair</span></button></td></tr>';
                    }).join('')
                    + '</tbody></table></div>';
                box.querySelectorAll('[data-revoke]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        btn.disabled = true;
                        fetch(revokeBase + '/' + btn.getAttribute('data-revoke') + '/revoke', {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' }, body: '{}'
                        }).then(function (r) { return r.json(); }).then(function (res) {
                            notify(!!res.success, res.message || '');
                            loadDevices();
                        });
                    });
                });
            })
            .catch(function () { box.textContent = 'Não foi possível carregar os aparelhos.'; });
    }

    function init() {
        renderInstall();
        renderPush();
        loadDevices();
        document.addEventListener('pwa:installable', renderInstall);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
