/**
 * Alerta de cliente sem resposta na etapa do funil
 *
 * Consulta /stage-alerts periodicamente e, para cada conversa em alerta:
 * - mostra um painel fixo na tela (qualquer página do sistema);
 * - toca o som configurado na etapa (e repete no intervalo configurado);
 * - destaca a conversa na lista de conversas e no Kanban;
 * - com a aba em segundo plano, mostra notificação do navegador e pisca o título.
 *
 * O agente pode abrir a conversa, adiar (15 min / 1 h), dispensar (até alguém responder)
 * ou silenciar o som neste navegador. Várias abas abertas tocam o som uma vez só.
 */
(function (window, document) {
    'use strict';

    const cfg = window.CP_STAGE_ALERTS;
    if (!cfg || !cfg.url || window.StageAlerts) return;

    const STORE_KEY = 'cpStageAlerts:v1';
    const POLL_MS = 60 * 1000;
    const IDLE_POLL_MS = 5 * 60 * 1000; // nenhuma etapa com alerta configurado
    const HIGHLIGHT_MS = 4000;

    let alerts = [];
    let pollTimer = null;
    let inFlight = false;
    let collapsed = false;
    let titleTimer = null;
    let originalTitle = document.title;

    // ---------------------------------------------------------------------------------
    // Estado salvo no navegador (adiamentos, dispensas, som desligado)
    // ---------------------------------------------------------------------------------
    function loadStore() {
        try {
            const data = JSON.parse(window.localStorage.getItem(STORE_KEY) || '{}');
            return {
                snooze: data.snooze || {},
                dismissed: data.dismissed || {},
                played: data.played || {},
                muted: !!data.muted,
                // No celular começa minimizado (só a barra) para não cobrir a tela
                collapsed: data.collapsed === undefined ? isMobile() : !!data.collapsed
            };
        } catch (e) {
            return { snooze: {}, dismissed: {}, played: {}, muted: false, collapsed: isMobile() };
        }
    }

    function saveStore(store) {
        try {
            window.localStorage.setItem(STORE_KEY, JSON.stringify(store));
        } catch (e) { /* navegação privada / bloqueado */ }
    }

    function updateStore(fn) {
        const store = loadStore();
        fn(store);
        saveStore(store);
        return store;
    }

    /** Remove chaves de alertas que já não existem (o cliente foi respondido) */
    function pruneStore(activeKeys) {
        updateStore((store) => {
            const now = Date.now();
            ['dismissed', 'played'].forEach((bucket) => {
                Object.keys(store[bucket]).forEach((key) => {
                    if (!activeKeys.has(key)) delete store[bucket][key];
                });
            });
            Object.keys(store.snooze).forEach((key) => {
                if (!activeKeys.has(key) || store.snooze[key] <= now) delete store.snooze[key];
            });
        });
    }

    function isHidden(alert, store) {
        return !!store.dismissed[alert.alert_key] || (store.snooze[alert.alert_key] || 0) > Date.now();
    }

    // ---------------------------------------------------------------------------------
    // Utilidades
    // ---------------------------------------------------------------------------------
    function isMobile() {
        return window.matchMedia ? window.matchMedia('(max-width: 767.98px)').matches : window.innerWidth < 768;
    }

    /** No celular o painel fica logo abaixo do cabeçalho fixo */
    function positionPanel(panel) {
        if (!isMobile()) {
            panel.style.top = '';
            return;
        }
        const header = document.getElementById('kt_header');
        const bottom = header ? Math.max(0, header.getBoundingClientRect().bottom) : 0;
        panel.style.top = (bottom + 8) + 'px';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function waitingLabel(minutes) {
        minutes = Math.max(0, parseInt(minutes, 10) || 0);
        if (minutes < 60) return minutes + ' min';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h' + (minutes % 60 ? ' ' + (minutes % 60) + 'min' : '');
        const days = Math.floor(hours / 24);
        return days + 'd' + (hours % 24 ? ' ' + (hours % 24) + 'h' : '');
    }

    /** Minutos de espera atualizados localmente entre uma consulta e outra */
    function currentWaiting(alert) {
        const elapsed = Math.floor((Date.now() - alert._receivedAt) / 60000);
        return (alert.waiting_minutes || 0) + Math.max(0, elapsed);
    }

    function isViewing(conversationId) {
        return document.visibilityState === 'visible'
            && String(window.currentConversationId || '') === String(conversationId);
    }

    function isConversationsPage() {
        return typeof window.selectConversation === 'function' && /\/conversations\/?$/.test(window.location.pathname);
    }

    // ---------------------------------------------------------------------------------
    // Som
    // ---------------------------------------------------------------------------------
    function playSound(file) {
        if (!file) return;
        try {
            if (window.SoundManager && typeof window.SoundManager.playFile === 'function') {
                window.SoundManager.playFile(file);
                return;
            }
            const audio = new Audio(cfg.soundsUrl + file);
            audio.volume = 0.8;
            audio.play().catch(() => {});
        } catch (e) { /* sem áudio */ }
    }

    /**
     * Toca no máximo um som por rodada: o do alerta mais antigo que esteja "vencido"
     * (novo ou no momento de repetir). O registro do último toque fica no localStorage,
     * então outras abas abertas não tocam de novo.
     */
    function handleSounds(visible) {
        const store = loadStore();
        const now = Date.now();
        let toPlay = null;
        const fresh = [];

        visible.forEach((alert) => {
            const last = store.played[alert.alert_key] || 0;
            const repeatMs = (alert.repeat_minutes || 0) * 60000;
            const due = !last || (repeatMs > 0 && now - last >= repeatMs - 5000);
            if (!due) return;
            if (!last) fresh.push(alert);
            if (isViewing(alert.conversation_id)) return; // já está com a conversa aberta
            if (!toPlay) toPlay = alert;
        });

        if (!toPlay && !fresh.length) return;

        updateStore((s) => {
            visible.forEach((alert) => {
                const last = s.played[alert.alert_key] || 0;
                const repeatMs = (alert.repeat_minutes || 0) * 60000;
                if (!last || (repeatMs > 0 && now - last >= repeatMs - 5000)) {
                    s.played[alert.alert_key] = now;
                }
            });
        });

        if (toPlay && !store.muted && toPlay.sound) playSound(toPlay.sound);
        if (fresh.length) browserNotify(fresh);
    }

    function browserNotify(fresh) {
        if (document.visibilityState === 'visible') return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        const first = fresh[0];
        const title = fresh.length > 1
            ? fresh.length + ' clientes aguardando resposta'
            : first.contact_name + ' aguardando resposta';
        const body = fresh.length > 1
            ? fresh.map((a) => a.contact_name).slice(0, 4).join(', ')
            : 'Há ' + waitingLabel(first.waiting_minutes) + ' na etapa ' + first.stage_name;
        const options = {
            body: body,
            tag: 'stage-alert',
            renotify: true,
            icon: cfg.iconUrl || undefined,
            data: { url: cfg.conversationsUrl + '?id=' + first.conversation_id, conversation_id: first.conversation_id }
        };

        try {
            if (navigator.serviceWorker && navigator.serviceWorker.controller) {
                navigator.serviceWorker.ready.then((reg) => reg.showNotification(title, options)).catch(() => {});
                return;
            }
            const n = new Notification(title, options);
            n.onclick = () => { window.focus(); openConversation(first.conversation_id); n.close(); };
        } catch (e) { /* sem notificação */ }
    }

    // ---------------------------------------------------------------------------------
    // Painel
    // ---------------------------------------------------------------------------------
    function ensurePanel() {
        let panel = document.getElementById('cpStageAlerts');
        if (panel) return panel;

        panel = document.createElement('div');
        panel.id = 'cpStageAlerts';
        panel.className = 'cp-stage-alerts d-none';
        panel.setAttribute('role', 'alert');
        panel.setAttribute('aria-live', 'polite');
        panel.innerHTML =
            '<div class="cp-sa-header">' +
                '<span class="cp-sa-bell">⏰</span>' +
                '<span class="cp-sa-title"></span>' +
                '<button type="button" class="cp-sa-btn cp-sa-mute" title="Silenciar som"></button>' +
                '<button type="button" class="cp-sa-btn cp-sa-toggle" title="Minimizar">▾</button>' +
            '</div>' +
            '<div class="cp-sa-list"></div>';
        document.body.appendChild(panel);

        panel.querySelector('.cp-sa-header').addEventListener('click', (e) => {
            if (e.target.closest('.cp-sa-mute')) return;
            collapsed = !collapsed;
            updateStore((s) => { s.collapsed = collapsed; });
            render();
        });
        panel.querySelector('.cp-sa-mute').addEventListener('click', (e) => {
            e.stopPropagation();
            const store = updateStore((s) => { s.muted = !s.muted; });
            if (window.showToast) window.showToast('info', store.muted ? 'Som dos alertas desligado neste navegador' : 'Som dos alertas ligado');
            render();
        });
        panel.querySelector('.cp-sa-list').addEventListener('click', onListClick);
        return panel;
    }

    function onListClick(e) {
        const btn = e.target.closest('[data-sa-action]');
        if (!btn) return;
        const item = btn.closest('[data-sa-key]');
        const key = item.getAttribute('data-sa-key');
        const conversationId = item.getAttribute('data-sa-conversation');
        const action = btn.getAttribute('data-sa-action');

        if (action === 'open') {
            if (isMobile()) {
                updateStore((s) => { s.collapsed = true; });
                render();
            }
            openConversation(conversationId);
            return;
        }
        if (action === 'snooze') {
            const minutes = parseInt(btn.getAttribute('data-sa-minutes'), 10) || 15;
            updateStore((s) => { s.snooze[key] = Date.now() + minutes * 60000; });
        } else if (action === 'dismiss') {
            updateStore((s) => { s.dismissed[key] = 1; });
        }
        render();
    }

    function openConversation(conversationId) {
        if (isConversationsPage()) {
            try {
                window.selectConversation(parseInt(conversationId, 10));
                return;
            } catch (e) { /* cai no redirecionamento */ }
        }
        window.location.href = cfg.conversationsUrl + '?id=' + encodeURIComponent(conversationId);
    }

    function render() {
        const panel = ensurePanel();
        const store = loadStore();
        collapsed = store.collapsed;
        const visible = alerts.filter((a) => !isHidden(a, store));

        highlight();
        updateTitle(visible.length);

        if (!visible.length) {
            panel.classList.add('d-none');
            return;
        }

        panel.classList.remove('d-none');
        panel.classList.toggle('cp-sa-collapsed', collapsed);
        positionPanel(panel);
        panel.querySelector('.cp-sa-title').textContent = visible.length === 1
            ? '1 cliente aguardando resposta'
            : visible.length + ' clientes aguardando resposta';
        const muteBtn = panel.querySelector('.cp-sa-mute');
        muteBtn.textContent = store.muted ? '🔕' : '🔔';
        muteBtn.title = store.muted ? 'Ligar o som dos alertas' : 'Silenciar o som dos alertas';
        panel.querySelector('.cp-sa-toggle').textContent = collapsed ? '▴' : '▾';

        panel.querySelector('.cp-sa-list').innerHTML = visible.slice(0, 30).map((a) => {
            const color = /^#[0-9a-f]{3,8}$/i.test(a.stage_color || '') ? a.stage_color : '#f1416c';
            const owner = a.assigned_to_me ? '' : (a.agent_name ? ' · ' + escapeHtml(a.agent_name) : ' · sem agente');
            return '<div class="cp-sa-item" data-sa-key="' + escapeHtml(a.alert_key) + '" data-sa-conversation="' + a.conversation_id + '">' +
                '<div class="cp-sa-main" data-sa-action="open" title="Abrir conversa">' +
                    '<div class="cp-sa-name">' + escapeHtml(a.contact_name) + '</div>' +
                    '<div class="cp-sa-meta">' +
                        '<span class="cp-sa-stage" style="background:' + color + '">' + escapeHtml(a.stage_name) + '</span>' +
                        ' <strong>há ' + waitingLabel(currentWaiting(a)) + '</strong>' + owner +
                    '</div>' +
                    (a.last_message ? '<div class="cp-sa-msg">' + escapeHtml(a.last_message) + '</div>' : '') +
                '</div>' +
                '<div class="cp-sa-actions">' +
                    '<button type="button" class="cp-sa-act cp-sa-open" data-sa-action="open">Abrir</button>' +
                    '<button type="button" class="cp-sa-act" data-sa-action="snooze" data-sa-minutes="15" title="Adiar 15 minutos">15 min</button>' +
                    '<button type="button" class="cp-sa-act" data-sa-action="snooze" data-sa-minutes="60" title="Adiar 1 hora">1 h</button>' +
                    '<button type="button" class="cp-sa-act cp-sa-dismiss" data-sa-action="dismiss" title="Dispensar até alguém responder">✕</button>' +
                '</div>' +
            '</div>';
        }).join('') + (visible.length > 30 ? '<div class="cp-sa-more">+ ' + (visible.length - 30) + ' conversas</div>' : '');
    }

    /** Destaque na lista de conversas e nos cards do Kanban */
    function highlight() {
        const store = loadStore();
        const active = new Map();
        alerts.forEach((a) => {
            if (!store.dismissed[a.alert_key]) active.set(String(a.conversation_id), a);
        });

        document.querySelectorAll('.cp-stage-alert-item').forEach((el) => {
            if (!active.has(el.getAttribute('data-conversation-id'))) {
                el.classList.remove('cp-stage-alert-item');
                el.removeAttribute('data-stage-alert');
            }
        });
        active.forEach((a, id) => {
            // Lista de conversas e cards do Kanban usam .conversation-item
            document.querySelectorAll('.conversation-item[data-conversation-id="' + id + '"]').forEach((el) => {
                el.classList.add('cp-stage-alert-item');
                el.setAttribute('data-stage-alert', 'Sem resposta há ' + waitingLabel(currentWaiting(a)));
                el.title = 'Cliente sem resposta há ' + waitingLabel(currentWaiting(a)) + ' (' + a.stage_name + ')';
            });
        });
    }

    function updateTitle(count) {
        if (titleTimer) {
            clearInterval(titleTimer);
            titleTimer = null;
            document.title = originalTitle;
        }
        if (!count || document.visibilityState === 'visible') return;
        originalTitle = document.title;
        let on = false;
        titleTimer = setInterval(() => {
            on = !on;
            document.title = on ? '⏰ (' + count + ') Cliente aguardando' : originalTitle;
        }, 1500);
    }

    // ---------------------------------------------------------------------------------
    // Consulta
    // ---------------------------------------------------------------------------------
    function schedule(ms) {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, ms);
    }

    function poll() {
        if (inFlight) return;
        if (navigator.onLine === false) {
            schedule(POLL_MS);
            return;
        }
        inFlight = true;
        fetch(cfg.url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then((r) => (r.ok ? r.json() : null))
            .then((data) => {
                if (!data || !data.success) {
                    schedule(POLL_MS);
                    return;
                }
                const receivedAt = Date.now();
                alerts = (data.alerts || []).map((a) => Object.assign(a, { _receivedAt: receivedAt }));
                pruneStore(new Set(alerts.map((a) => a.alert_key)));
                render();
                const store = loadStore();
                handleSounds(alerts.filter((a) => !isHidden(a, store)));
                schedule(data.has_rules ? POLL_MS : IDLE_POLL_MS);
            })
            .catch(() => schedule(POLL_MS))
            .finally(() => { inFlight = false; });
    }

    // Re-aplica o destaque quando a lista é redesenhada e atualiza os tempos
    setInterval(() => { if (alerts.length) highlight(); }, HIGHLIGHT_MS);
    setInterval(() => { if (alerts.length) render(); }, 60000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateTitle(0);
            schedule(500);
        }
    });

    // Outra aba adiou/dispensou/silenciou: reflete aqui
    window.addEventListener('storage', (e) => {
        if (e.key === STORE_KEY) render();
    });

    // Agente respondeu nesta tela: consulta logo para o alerta sumir
    window.addEventListener('realtime:new_message', () => schedule(3000));

    window.StageAlerts = {
        refresh: () => schedule(0),
        getAlerts: () => alerts.slice()
    };

    schedule(3000);
})(window, document);
