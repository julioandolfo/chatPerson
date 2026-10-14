/**
 * Service worker do app (PWA)
 *
 * Cache: só arquivos estáticos (JS/CSS/plugins/ícones/sons). Páginas, JSON, uploads e
 * anexos SEMPRE vão para a rede — dados de conversas nunca ficam guardados no aparelho.
 * Sem rede, a navegação mostra offline.html.
 *
 * Push: mostra a notificação e, ao tocar, abre a conversa no app já aberto (ou abre o app).
 */

const SW_VERSION = 'v1';
const ASSET_CACHE = 'cp-assets-' + SW_VERSION;
const CORE_CACHE = 'cp-core-' + SW_VERSION;

const SCOPE = self.registration.scope; // termina com "/" (funciona com o sistema em subpasta)
const OFFLINE_URL = new URL('offline.html', SCOPE).href;
const ICON_URL = new URL('assets/media/pwa/icon-192.png', SCOPE).href;
const BADGE_URL = new URL('assets/media/pwa/badge-96.png', SCOPE).href;
const CORE_FILES = [OFFLINE_URL, ICON_URL, BADGE_URL];

// Estáticos que podem ir para o cache (anexos em /assets/media/attachments ficam de fora)
const CACHEABLE_PATH = /\/assets\/(js|css|plugins|sounds|media\/(pwa|logos|icons))\//;

const IS_IOS = /iphone|ipad|ipod/i.test(self.navigator.userAgent || '');

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CORE_CACHE).then((cache) => cache.addAll(CORE_FILES)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keep = [ASSET_CACHE, CORE_CACHE];
        const names = await caches.keys();
        await Promise.all(names.filter((n) => n.startsWith('cp-') && !keep.includes(n)).map((n) => caches.delete(n)));
        await self.clients.claim();
    })());
});

// Atualização só quando o usuário toca em "Atualizar" (não recarrega no meio de uma digitação)
self.addEventListener('message', (event) => {
    const data = event.data || {};
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    } else if (data.type === 'CLEAR_BADGE' && self.navigator.clearAppBadge) {
        self.navigator.clearAppBadge().catch(() => {});
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (CACHEABLE_PATH.test(url.pathname)) {
        event.respondWith(staleWhileRevalidate(event, request));
    }
    // Todo o resto (JSON, uploads, anexos...) segue direto para a rede
});

async function staleWhileRevalidate(event, request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request).then(async (response) => {
        if (response && response.ok && response.type === 'basic') {
            await cache.put(request, response.clone());
            await removeOldVersions(cache, request.url);
        }
        return response;
    }).catch(() => cached);

    if (cached) {
        event.waitUntil(network);
        return cached;
    }
    return network;
}

// "app.js?v=1" ficou velho quando chega "app.js?v=2": remove a versão anterior
async function removeOldVersions(cache, currentUrl) {
    const current = new URL(currentUrl);
    const keys = await cache.keys();
    await Promise.all(keys.map((req) => {
        const u = new URL(req.url);
        return (u.pathname === current.pathname && u.search !== current.search) ? cache.delete(req) : null;
    }));
}

// =============================================================================
// PUSH
// =============================================================================

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Nova notificação', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil((async () => {
        const url = new URL(data.url || 'conversations', SCOPE).href;

        // Conversa aberta e visível na tela: não duplica o aviso.
        // (No iPhone sempre mostra: o Safari cancela a inscrição de quem recebe push sem notificação.)
        if (!IS_IOS && data.conversation_id) {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            const openHere = windows.some((client) => {
                if (client.visibilityState !== 'visible' || !client.focused) return false;
                try {
                    return new URL(client.url).searchParams.get('id') === String(data.conversation_id);
                } catch (e) {
                    return false;
                }
            });
            if (openHere) return;
        }

        if (self.navigator.setAppBadge) {
            self.navigator.setAppBadge().catch(() => {});
        }

        await self.registration.showNotification(data.title || 'Nova mensagem', {
            body: data.body || '',
            icon: ICON_URL,
            badge: BADGE_URL,
            tag: data.tag || undefined,
            renotify: !!data.tag,
            timestamp: data.timestamp || Date.now(),
            data: { url: url, conversation_id: data.conversation_id || null },
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = (event.notification.data && event.notification.data.url) || new URL('conversations', SCOPE).href;
    const conversationId = event.notification.data && event.notification.data.conversation_id;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const inApp = windows.filter((client) => client.url.startsWith(SCOPE));

        // Prefere uma janela já nas Conversas; senão qualquer janela do app
        const client = inApp.find((c) => new URL(c.url).pathname.endsWith('/conversations')) || inApp[0];
        if (client) {
            await client.focus();
            client.postMessage({ type: 'open-conversation', conversation_id: conversationId, url: target });
            return;
        }
        await self.clients.openWindow(target);
    })());
});

// O navegador trocou a inscrição (expirou/rotacionou): reinscreve e avisa o servidor
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        try {
            const options = event.oldSubscription && event.oldSubscription.options;
            if (!options || !options.applicationServerKey) return;
            const subscription = event.newSubscription || await self.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: options.applicationServerKey,
            });
            await fetch(new URL('pwa/push/subscribe', SCOPE).href, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ subscription: subscription.toJSON(), show_preview: true }),
            });
        } catch (e) {
            // Sem sessão no momento: a página reinscreve no próximo acesso (pwa.js)
        }
    })());
});
