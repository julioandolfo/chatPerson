/**
 * PWA — app instalável
 *
 * window.CP_PWA (definido pelo layout): { scope, swUrl, loggedIn, routes: {...} }
 *
 * Expõe:
 *   CPApp.install(), CPApp.canInstall(), CPApp.isStandalone, CPApp.isIOS
 *   CPPush.supported(), CPPush.state(), CPPush.enable(showPreview), CPPush.disable(),
 *   CPPush.setShowPreview(bool), CPPush.test()
 *
 * Eventos no document:
 *   'pwa:resume'  — o app voltou do segundo plano (ressincronizar dados)
 *   'pwa:online' / 'pwa:offline'
 *   'pwa:installable' — o botão "Instalar app" pode aparecer
 */
(function () {
    'use strict';

    var cfg = window.CP_PWA || {};
    var routes = cfg.routes || {};

    var isStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isStandalone) {
        document.documentElement.classList.add('pwa-standalone');
        if (document.head) injectStylesSoon();
    }
    if (isIOS) document.documentElement.classList.add('pwa-ios');

    var registrationPromise = null;
    var deferredInstallPrompt = null;
    var userAskedUpdate = false;

    function storageGet(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function storageSet(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* modo privado */ } }

    function toast(message, type) {
        if (typeof window.showToast === 'function') { window.showToast(type === 'error' ? 'error' : 'success', message); return; }
        if (window.Swal) {
            window.Swal.fire({ toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, icon: type === 'error' ? 'error' : 'success', text: message });
            return;
        }
        alert(message);
    }

    function injectStylesSoon() { setTimeout(injectStyles, 0); }

    function injectStyles() {
        if (document.getElementById('pwa-styles')) return;
        var css = ''
            + '.pwa-bar{position:fixed;left:12px;right:12px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:10900;display:flex;align-items:center;gap:10px;'
            + 'padding:10px 12px;border-radius:12px;background:#1e1e2d;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.25);font-size:14px;max-width:560px;margin:0 auto}'
            + '.pwa-bar .pwa-bar-text{flex:1;min-width:0}'
            + '.pwa-bar button{border:0;border-radius:8px;padding:8px 12px;font-weight:600;font-size:13px;cursor:pointer}'
            + '.pwa-bar .pwa-primary{background:#1B84FF;color:#fff}.pwa-bar .pwa-close{background:transparent;color:#a1a5b7;padding:8px}'
            + '.pwa-offline{position:fixed;top:env(safe-area-inset-top);left:0;right:0;z-index:10950;background:#f1416c;color:#fff;text-align:center;'
            + 'font-size:13px;font-weight:600;padding:6px 12px;transform:translateY(-120%);transition:transform .2s}'
            + 'html.pwa-is-offline .pwa-offline{transform:none}'
            // App instalado: sem "puxar para atualizar" acidental no meio da conversa
            + 'html.pwa-standalone,html.pwa-standalone body{overscroll-behavior-y:contain}';
        var style = document.createElement('style');
        style.id = 'pwa-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    function showBar(id, text, actionLabel, onAction, onClose) {
        injectStyles();
        var existing = document.getElementById(id);
        if (existing) existing.remove();
        var bar = document.createElement('div');
        bar.className = 'pwa-bar';
        bar.id = id;
        bar.innerHTML = '<span class="pwa-bar-text"></span><button type="button" class="pwa-primary"></button><button type="button" class="pwa-close" aria-label="Fechar">✕</button>';
        bar.querySelector('.pwa-bar-text').textContent = text;
        bar.querySelector('.pwa-primary').textContent = actionLabel;
        bar.querySelector('.pwa-primary').addEventListener('click', function () { bar.remove(); onAction(); });
        bar.querySelector('.pwa-close').addEventListener('click', function () { bar.remove(); if (onClose) onClose(); });
        (document.body || document.documentElement).appendChild(bar);
    }

    // =========================================================================
    // SERVICE WORKER + ATUALIZAÇÃO
    // =========================================================================

    function registerServiceWorker() {
        if (!('serviceWorker' in navigator) || !cfg.swUrl) return null;

        registrationPromise = navigator.serviceWorker.register(cfg.swUrl, { scope: cfg.scope || '/' }).then(function (reg) {
            if (reg.waiting && navigator.serviceWorker.controller) offerUpdate(reg.waiting);
            reg.addEventListener('updatefound', function () {
                var worker = reg.installing;
                if (!worker) return;
                worker.addEventListener('statechange', function () {
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) offerUpdate(worker);
                });
            });
            // Procura versão nova a cada hora e quando o app volta para a frente
            setInterval(function () { reg.update().catch(function () {}); }, 60 * 60 * 1000);
            return reg;
        }).catch(function (err) {
            console.warn('[PWA] Falha ao registrar service worker:', err);
            return null;
        });

        navigator.serviceWorker.addEventListener('controllerchange', function () {
            if (userAskedUpdate) window.location.reload();
        });

        navigator.serviceWorker.addEventListener('message', function (event) {
            var data = event.data || {};
            if (data.type === 'open-conversation') openConversation(data.conversation_id, data.url);
        });

        return registrationPromise;
    }

    function offerUpdate(worker) {
        showBar('pwa-update-bar', 'Nova versão disponível.', 'Atualizar', function () {
            userAskedUpdate = true;
            worker.postMessage({ type: 'SKIP_WAITING' });
        });
    }

    function getRegistration() {
        return registrationPromise || Promise.resolve(null);
    }

    // Toque na notificação com o app aberto: abre a conversa sem recarregar a página
    function openConversation(conversationId, url) {
        if (conversationId && typeof window.selectConversation === 'function' && document.getElementById('chatMessages')) {
            window.selectConversation(conversationId);
            return;
        }
        if (url) window.location.href = url;
    }

    // =========================================================================
    // INSTALAÇÃO
    // =========================================================================

    var CPApp = {
        isStandalone: isStandalone,
        isIOS: isIOS,
        canInstall: function () {
            return !isStandalone && (!!deferredInstallPrompt || isIOS);
        },
        install: function () {
            if (deferredInstallPrompt) {
                var promptEvent = deferredInstallPrompt;
                deferredInstallPrompt = null;
                promptEvent.prompt();
                return promptEvent.userChoice.then(function (choice) {
                    updateInstallButtons();
                    return choice && choice.outcome === 'accepted';
                });
            }
            showInstallInstructions();
            return Promise.resolve(false);
        }
    };

    function showInstallInstructions() {
        var html = isIOS
            ? '<div style="text-align:left;line-height:1.7">No Safari:<br>1. Toque em <b>Compartilhar</b> (quadrado com seta para cima)<br>2. Escolha <b>Adicionar à Tela de Início</b><br>3. Toque em <b>Adicionar</b><br><br><small>As notificações no iPhone funcionam a partir do iOS 16.4, com o app aberto pela tela de início.</small></div>'
            : '<div style="text-align:left;line-height:1.7">No Chrome/Edge: abra o menu <b>⋮</b> e escolha <b>Instalar app</b> (ou "Adicionar à tela inicial").</div>';
        if (window.Swal) {
            window.Swal.fire({ title: 'Instalar o app', html: html, confirmButtonText: 'Entendi' });
        } else {
            alert(html.replace(/<[^>]+>/g, ' '));
        }
    }

    function updateInstallButtons() {
        var can = CPApp.canInstall();
        document.querySelectorAll('[data-pwa-install]').forEach(function (el) {
            el.classList.toggle('d-none', !can);
            if (!el.dataset.pwaBound) {
                el.dataset.pwaBound = '1';
                el.addEventListener('click', function (e) { e.preventDefault(); CPApp.install(); });
            }
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        updateInstallButtons();
        document.dispatchEvent(new CustomEvent('pwa:installable'));
        maybeShowInstallBar();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        storageSet('pwa_installed', '1');
        updateInstallButtons();
    });

    // Sugestão de instalação no celular, no máximo a cada 14 dias
    function maybeShowInstallBar() {
        if (isStandalone || !cfg.loggedIn || window.innerWidth > 991) return;
        if (storageGet('pwa_installed') === '1') return;
        var dismissed = parseInt(storageGet('pwa_install_dismissed_at') || '0', 10);
        if (Date.now() - dismissed < 14 * 86400000) return;
        if (!CPApp.canInstall()) return;

        showBar('pwa-install-bar', 'Instale o app para atender pelo celular e receber notificações.', 'Instalar', function () {
            CPApp.install();
        }, function () {
            storageSet('pwa_install_dismissed_at', String(Date.now()));
        });
    }

    // =========================================================================
    // NOTIFICAÇÕES PUSH
    // =========================================================================

    function urlBase64ToUint8Array(base64) {
        var padding = '='.repeat((4 - base64.length % 4) % 4);
        var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        var output = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
        return output;
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {})
        }).then(function (r) { return r.json(); });
    }

    var CPPush = {
        supported: function () {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        },

        /** { supported, permission, subscribed, show_preview, needsInstall } */
        state: function () {
            if (!CPPush.supported()) {
                return Promise.resolve({ supported: false, permission: 'unsupported', subscribed: false, needsInstall: isIOS && !isStandalone });
            }
            return getRegistration().then(function (reg) {
                if (!reg) return { supported: false, permission: Notification.permission, subscribed: false };
                return reg.pushManager.getSubscription().then(function (sub) {
                    if (!sub) return { supported: true, permission: Notification.permission, subscribed: false };
                    return postJson(routes.pushStatus, { endpoint: sub.endpoint }).then(function (res) {
                        return {
                            supported: true,
                            permission: Notification.permission,
                            subscribed: !!(res && res.subscribed),
                            show_preview: res && res.subscription ? !!res.subscription.show_preview : true
                        };
                    });
                });
            });
        },

        /** Precisa ser chamado a partir de um toque/clique (exigência do iPhone) */
        enable: function (showPreview) {
            if (!CPPush.supported()) {
                return Promise.reject(new Error(isIOS && !isStandalone
                    ? 'No iPhone, instale o app (Compartilhar → Adicionar à Tela de Início) e abra por ele para ativar as notificações.'
                    : 'Este navegador não suporta notificações push.'));
            }
            return Notification.requestPermission().then(function (permission) {
                if (permission !== 'granted') {
                    throw new Error('Permissão de notificação negada. Libere nas configurações do navegador/aparelho.');
                }
                return Promise.all([getRegistration(), fetch(routes.pushPublicKey, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); })]);
            }).then(function (results) {
                var reg = results[0];
                var keyResponse = results[1];
                if (!reg) throw new Error('Service worker indisponível.');
                if (!keyResponse || !keyResponse.success) throw new Error((keyResponse && keyResponse.message) || 'Chave de push indisponível.');
                var applicationServerKey = urlBase64ToUint8Array(keyResponse.public_key);

                return reg.pushManager.getSubscription().then(function (existing) {
                    // Inscrição feita com outra chave (servidor trocou as chaves): refaz
                    if (existing && existing.options && existing.options.applicationServerKey) {
                        var current = new Uint8Array(existing.options.applicationServerKey);
                        var same = current.length === applicationServerKey.length && current.every(function (b, i) { return b === applicationServerKey[i]; });
                        if (same) return existing;
                        return existing.unsubscribe().then(function () { return null; });
                    }
                    return existing;
                }).then(function (sub) {
                    return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: applicationServerKey });
                });
            }).then(function (subscription) {
                storageSet('pwa_push_synced_at', String(Date.now()));
                storageSet('pwa_push_user', String(cfg.userId));
                return postJson(routes.pushSubscribe, { subscription: subscription.toJSON(), show_preview: showPreview !== false });
            }).then(function (res) {
                if (!res || !res.success) throw new Error((res && res.message) || 'Não foi possível ativar.');
                return res;
            });
        },

        disable: function () {
            return getRegistration().then(function (reg) {
                return reg ? reg.pushManager.getSubscription() : null;
            }).then(function (sub) {
                if (!sub) return { success: true };
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return postJson(routes.pushUnsubscribe, { endpoint: endpoint });
                });
            });
        },

        setShowPreview: function (showPreview) {
            return getRegistration().then(function (reg) {
                return reg ? reg.pushManager.getSubscription() : null;
            }).then(function (sub) {
                if (!sub) throw new Error('Notificações não estão ativas neste aparelho.');
                return postJson(routes.pushPreferences, { endpoint: sub.endpoint, show_preview: !!showPreview });
            });
        },

        test: function () {
            return postJson(routes.pushTest, {});
        }
    };

    // Mantém a inscrição associada ao usuário logado (troca de usuário no mesmo aparelho,
    // banco restaurado...). Silencioso, no máximo 1x por dia.
    function syncExistingSubscription() {
        if (!cfg.loggedIn || !CPPush.supported() || Notification.permission !== 'granted') return;
        var last = parseInt(storageGet('pwa_push_synced_at') || '0', 10);
        var lastUser = storageGet('pwa_push_user');
        if (Date.now() - last < 86400000 && lastUser === String(cfg.userId)) return;

        getRegistration().then(function (reg) {
            return reg ? reg.pushManager.getSubscription() : null;
        }).then(function (sub) {
            if (!sub) return;
            return postJson(routes.pushStatus, { endpoint: sub.endpoint }).then(function (res) {
                var showPreview = res && res.subscription ? !!res.subscription.show_preview : true;
                return postJson(routes.pushSubscribe, { subscription: sub.toJSON(), show_preview: showPreview });
            }).then(function () {
                storageSet('pwa_push_synced_at', String(Date.now()));
                storageSet('pwa_push_user', String(cfg.userId));
            });
        }).catch(function () {});
    }

    // =========================================================================
    // SEGUNDO PLANO, CONEXÃO E SESSÃO
    // =========================================================================

    var hiddenAt = 0;
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            hiddenAt = Date.now();
            return;
        }
        if (navigator.clearAppBadge) navigator.clearAppBadge().catch(function () {});
        getRegistration().then(function (reg) { if (reg) reg.update().catch(function () {}); });
        // Voltou depois de alguns segundos: o celular pode ter derrubado WebSocket/polling
        if (hiddenAt && Date.now() - hiddenAt > 5000) {
            document.dispatchEvent(new CustomEvent('pwa:resume', { detail: { hiddenMs: Date.now() - hiddenAt } }));
        }
        hiddenAt = 0;
    });

    function setOnlineState(online) {
        injectStyles();
        if (!document.getElementById('pwa-offline-indicator')) {
            var el = document.createElement('div');
            el.id = 'pwa-offline-indicator';
            el.className = 'pwa-offline';
            el.textContent = 'Sem conexão — as mensagens não serão enviadas até a internet voltar';
            (document.body || document.documentElement).appendChild(el);
        }
        document.documentElement.classList.toggle('pwa-is-offline', !online);
        document.dispatchEvent(new CustomEvent(online ? 'pwa:online' : 'pwa:offline'));
        if (online) document.dispatchEvent(new CustomEvent('pwa:resume', { detail: { reason: 'online' } }));
    }
    window.addEventListener('online', function () { setOnlineState(true); });
    window.addEventListener('offline', function () { setOnlineState(false); });

    // Sessão expirada em qualquer chamada AJAX: leva ao login (e volta para esta página depois)
    var sessionExpiredShown = false;
    if (cfg.loggedIn && window.fetch) {
        var originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            return originalFetch(input, init).then(function (response) {
                if (response.status === 401 && !sessionExpiredShown) {
                    var url = typeof input === 'string' ? input : (input && input.url) || '';
                    var sameOrigin = url.indexOf('http') !== 0 || url.indexOf(window.location.origin) === 0;
                    if (sameOrigin) {
                        response.clone().json().then(function (data) {
                            if (data && data.redirect) onSessionExpired();
                        }).catch(function () {});
                    }
                }
                return response;
            });
        };
    }

    function onSessionExpired() {
        if (sessionExpiredShown) return;
        sessionExpiredShown = true;
        var go = function () { window.location.reload(); }; // middleware guarda esta página e manda para o login
        if (window.Swal) {
            window.Swal.fire({ icon: 'info', title: 'Sessão expirada', text: 'Entre novamente para continuar.', confirmButtonText: 'Entrar', allowOutsideClick: false }).then(go);
        } else {
            go();
        }
    }

    // =========================================================================

    window.CPApp = CPApp;
    window.CPPush = CPPush;

    registerServiceWorker();

    document.addEventListener('DOMContentLoaded', function () {
        updateInstallButtons();
        if (!navigator.onLine) setOnlineState(false);
        syncExistingSubscription();
        // iPhone não dispara beforeinstallprompt: sugere instalar mesmo assim
        if (isIOS) maybeShowInstallBar();
    });
    if (document.readyState !== 'loading') {
        updateInstallButtons();
        syncExistingSubscription();
    }
})();
