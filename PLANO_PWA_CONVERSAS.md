# Plano — PWA do CRM (começando pelas Conversas)

> **Status:** Fases 0 a 4 implementadas — ver `PWA_APP_INSTALAVEL.md`. Fases 5 e 6 (opcionais) não iniciadas.
> Decisões aplicadas: notificação mostra o texto (com opção de ocultar por aparelho); sem Play Store; nome/logo do app vêm de Configurações.
> Diferença do plano: o Web Push foi feito sem a biblioteca `minishlink/web-push` (criptografia própria validada contra a referência), então não muda o Composer nem o Dockerfile.

Objetivo: o agente instala o CRM no celular (e no desktop) como um app, abre direto nas
Conversas, recebe notificação de mensagem nova com o app fechado e consegue atender
(ler, responder, áudio, anexo, follow-up) com conforto.

---

## 1. Validação do que existe hoje

| Item | Situação | Evidência |
|---|---|---|
| Manifest / service worker / meta tags de app | ❌ Não existe | nenhum `manifest`, `sw.js`, `theme-color` ou `apple-touch-icon` |
| Layout mobile da tela de Conversas | ✅ Existe | `views/conversations/index.php` tem `@media (max-width: 767px)` alternando lista → chat → detalhes (`.mobile-active`, `body.conv-mobile-chat`, botão voltar) |
| HTTPS (obrigatório p/ PWA) | ✅ Coolify | — |
| Endpoints JSON do chat (sessão) | ✅ Prontos | `GET /conversations?format=json`, `GET/POST /conversations/{id}/messages`, `GET /conversations/{id}` (AJAX) |
| API REST v1 (JWT) | ⚠️ Não serve p/ agentes | `GET /conversations` exige `conversations.view.all`; não aplica as regras de visibilidade do painel |
| Sessão de login | ❌ Ruim p/ app | sem "lembrar de mim"; padrão do PHP (`gc_maxlifetime` 24 min, cookie de sessão). No iPhone o app instalado perde cookie de sessão ao ser fechado → login toda hora |
| Notificação do navegador | ❌ Não funciona no celular | `notification-manager.js` usa `new Notification()`: lança erro no Chrome Android (exige `registration.showNotification`) e não existe no iPhone fora de PWA |
| Notificação com app fechado (push) | ❌ Não existe | sem Web Push/VAPID; ponto único de disparo existe: `NotificationService::create` |
| Tempo real | ⚠️ Polling na prática | `realtime-client.js` faz WebSocket ou polling 3s; o container só sobe Apache (`websocket-server.php` não roda no Dockerfile) |
| Peso da tela de Conversas | ⚠️ Pesado | ~4,8 MB de JS/CSS (plugins.bundle.js 2,4 MB, style.bundle.css 1,2 MB) + ~1 MB de HTML/JS inline a cada abertura |
| Versionamento de assets | ❌ Não existe | `Url::asset()` sem `?v=` → service worker com cache serviria versão velha após deploy |
| Gravação de áudio no iPhone | ⚠️ Testar | Safari grava `audio/mp4`, mas o upload força nome `.webm` (`sendAudioMessage`) |

**Decisão de arquitetura:** PWA sobre o próprio sistema (mesma origem, mesma sessão,
mesmas permissões), abrindo em `/conversations`. Não usar a API v1 nem criar app separado
agora — o layout mobile já existe e as regras de visibilidade ficam num lugar só.
Uma tela mobile leve dedicada só entra se as métricas da Fase 2 ficarem ruins (Fase 5).

---

## 2. Fases

### Fase 0 — Pré-requisitos (1 dia)

1. **Versionar assets**: `Url::asset()` passa a anexar `?v=<filemtime>`; sem isso o cache do SW quebra deploys.
2. **Sessão longa + "Manter conectado"**
   - Tabela `user_remember_tokens` (user_id, selector, hash do validator, user_agent, expires_at, last_used_at).
   - Checkbox no login; cookie `remember` `HttpOnly; Secure; SameSite=Lax`, 30 dias, **rotacionado a cada uso**; validator só como hash no banco.
   - `Authentication` middleware: sem sessão + cookie válido → recria a sessão.
   - Logout e troca de senha revogam os tokens; tela de perfil lista/revoga dispositivos.
   - `session.gc_maxlifetime` ≥ 8h no `99-app.ini` do Dockerfile.
3. **Ícones**: 192, 512, 512 *maskable* e `apple-touch-icon` 180 px em `public/assets/media/pwa/`.

### Fase 1 — Instalável (1–2 dias)

1. `public/manifest.webmanifest` (gerado com `Url::to` se houver subpasta):
   `name`, `short_name`, `start_url: /conversations?source=pwa`, `scope: /`, `display: standalone`,
   `theme_color`, `background_color`, ícones e atalhos ("Não respondidas", "Minhas conversas").
2. Meta tags no `layouts/metronic/app.php`, `auth/login.php` e páginas de erro:
   `manifest`, `theme-color`, `apple-mobile-web-app-capable`, `apple-touch-icon`, `viewport-fit=cover`.
3. `public/sw.js` (escopo raiz):
   - **Páginas (navegação)**: sempre rede; sem rede → `offline.html`. Nunca cachear HTML autenticado.
   - **`/assets/*`**: stale-while-revalidate com cache versionado.
   - **JSON, POST, uploads, `/storage`, webhooks**: nunca cachear (dados e privacidade).
   - Nova versão → aviso "Atualização disponível — recarregar" (sem `skipWaiting` forçado no meio de uma digitação).
4. Botão "Instalar app": `beforeinstallprompt` no Android/desktop; no iPhone, instrução
   "Compartilhar → Adicionar à Tela de Início".

### Fase 2 — Conversas no celular (2–4 dias)

1. **Modo standalone**: `env(safe-area-inset-*)` (notch/barra), `100dvh`, `overscroll-behavior: contain` (sem pull-to-refresh acidental).
2. **Teclado virtual**: usar `visualViewport` para manter a caixa de mensagem e o aviso de follow-up acima do teclado.
3. **Botão voltar do Android**: abrir conversa faz `history.pushState`; `popstate` volta para a lista em vez de fechar o app.
4. **Volta do segundo plano**: em `visibilitychange → visible`, reconectar tempo real e ressincronizar lista + mensagens da conversa aberta (o celular mata o WebSocket/polling em background).
5. **Áudio no iPhone**: nome do arquivo pela extensão real do `blob.type` (`.m4a`/`.mp4`) e aceitar `audio/mp4` no `AttachmentService` (converter para ogg/opus via ffmpeg como já é feito com webm).
6. **Anexos**: `input accept` com câmera/galeria; links externos abrem fora do app.
7. **Peso**: não carregar no mobile o que não aparece (coaching, copilot, gráficos); medir com Lighthouse mobile em 4G simulado.
   - Meta: abrir a lista em < 3 s após o primeiro acesso (com cache do SW).

### Fase 3 — Notificações push (3–4 dias)

1. **Backend**
   - `composer require minishlink/web-push`; extensões `gmp`/`bcmath` no Dockerfile.
   - Chaves VAPID em variáveis de ambiente (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`).
   - Tabela `push_subscriptions` (user_id, endpoint único, p256dh, auth, user_agent, created_at, last_used_at).
   - `POST /push/subscribe` e `DELETE /push/subscribe`.
   - `PushService::sendToUser()` chamado em `NotificationService::create` (cobre mensagem nova, atribuição, setor, menções...). Remove inscrições que retornam 404/410. Timeout curto; o webhook do WhatsApp já processa depois de responder (`fastcgi_finish_request`), então não atrasa o Quepasa.
   - Payload: título (nome do contato), corpo, `url` `/conversations?conversation_id=X`, `tag` `conversation-X` (agrupa várias mensagens da mesma conversa).
2. **Service worker**
   - `push`: mostra a notificação; **não mostra** se houver janela visível com aquela conversa aberta.
   - `notificationclick`: foca o app já aberto e abre a conversa (ou abre nova janela).
   - `navigator.setAppBadge(nãoLidas)` onde suportado.
3. **Front**
   - Botão "Ativar notificações neste aparelho" (o iPhone exige gesto do usuário e app instalado, iOS 16.4+).
   - `notification-manager.js`: usar `registration.showNotification` quando houver SW (corrige o Android).
   - Preferência por usuário: mostrar o texto da mensagem ou só "Nova mensagem de Fulano" (privacidade na tela bloqueada).

### Fase 4 — Offline e robustez (1–2 dias)

1. `offline.html` com a marca e "sem conexão".
2. Sem rede: aviso fixo no chat e envio bloqueado (sem fila offline na v1; fila com IndexedDB + Background Sync fica para depois — só Android suporta Background Sync).
3. Sessão expirada no meio do uso: respostas 401 (JSON do middleware) levam ao login dentro do app e voltam para a conversa.

### Fase 5 — Opcional: tela mobile leve (5–8 dias)

Só se a Fase 2 não atingir a meta de desempenho: `/m/conversas` com HTML/JS próprios
(sem Metronic completo), usando os mesmos endpoints JSON por sessão.

### Fase 6 — Opcional: lojas

Play Store via TWA (Bubblewrap) reaproveitando o PWA. App Store não é necessária para o iPhone (instala pelo Safari).

---

## 3. Testes de aceite

Dispositivos: Android (Chrome), iPhone (Safari, iOS 16.4+), desktop (Chrome/Edge).

- [ ] Instala, abre em tela cheia direto em Conversas, com ícone e cor corretos.
- [ ] Fecha o app, abre no dia seguinte: continua logado (Manter conectado).
- [ ] Logout e "revogar dispositivo" desconectam de verdade.
- [ ] App fechado + mensagem do cliente → notificação; toque abre a conversa certa.
- [ ] Com a conversa aberta na tela, não chega notificação duplicada.
- [ ] Várias mensagens da mesma conversa viram uma notificação só.
- [ ] Responder texto, áudio (Android e iPhone), foto da câmera, documento.
- [ ] Teclado não cobre a caixa de mensagem; voltar do Android volta para a lista.
- [ ] Aviso de follow-up por etapa aparece e o "Cancelar" funciona no celular.
- [ ] Volta do segundo plano mostra mensagens que chegaram enquanto estava fechado.
- [ ] Sem internet: tela offline / aviso no chat; ao voltar, recarrega.
- [ ] Deploy novo: aparece "Atualização disponível" e os assets novos são carregados.
- [ ] Agente comum continua vendo só as conversas que vê no desktop.
- [ ] Lighthouse: critérios de PWA instalável ok; lista abre em < 3 s em 4G (segunda visita).

---

## 4. Riscos e limitações

- **iPhone**: push só com app instalado e iOS 16.4+; sem Background Sync; o iOS pode apagar o cache do app após semanas sem uso (o login persiste pelo cookie de 30 dias).
- **Tempo real**: com o container atual o modo efetivo é polling de 3 s (consome bateria com o app aberto). Subir o `websocket-server.php` como serviço no Coolify melhora isso, mas não é pré-requisito.
- **Segurança**: "manter conectado" amplia a janela de uma sessão roubada — mitigado com token rotacionado, hash no banco, revogação por dispositivo e `Secure/HttpOnly`.
- **Privacidade**: conteúdo de mensagem na tela bloqueada — controlado pela preferência da Fase 3.
- **Peso**: a tela de Conversas é grande; o SW resolve o download repetido dos assets, mas não o HTML/JS inline gerado a cada abertura (por isso a Fase 5 existe).

---

## 5. Estimativa

| Fase | Esforço |
|---|---|
| 0 — Pré-requisitos | 1 dia |
| 1 — Instalável | 1–2 dias |
| 2 — Conversas no celular | 2–4 dias |
| 3 — Push | 3–4 dias |
| 4 — Offline/robustez | 1–2 dias |
| **Total (0–4)** | **8–13 dias úteis** |
| 5 — Tela leve (opcional) | 5–8 dias |

Ordem de entrega sugerida: Fases 0 + 1 + 2 juntas (o app já fica utilizável no celular),
depois Fase 3 (push), depois Fase 4.

## 6. Decisões pendentes

1. Notificação mostra o texto da mensagem ou só "Nova mensagem de Fulano"? (padrão sugerido: texto, com opção de ocultar)
2. Publicar na Play Store (TWA) ou só instalar pelo navegador?
3. Nome e cor do app na tela inicial (padrão: nome do sistema e cor primária atual).
