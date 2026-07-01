# 📱 PLANO COMPLETO — App Mobile (iOS/Android) do Módulo de Conversations

**Data**: 01/07/2026
**Documento-base**: `ANALISE_APP_MOBILE_CONVERSATIONS.md` (diagnóstico do sistema atual)
**Escopo**: App de atendimento para **agentes** (não é app para o cliente final), cobrindo apenas o módulo de conversas.

---

## ÍNDICE

1. [Visão geral e princípios](#1-visão-geral-e-princípios)
2. [FASE 0 — Atualização da API (backend)](#2-fase-0--atualização-da-api-backend)
3. [Stack tecnológico do app](#3-stack-tecnológico-do-app)
4. [Arquitetura do app](#4-arquitetura-do-app)
5. [Tema e Design System](#5-tema-e-design-system)
6. [Layout e navegação — telas](#6-layout-e-navegação--telas)
7. [Funcionalidades por fase](#7-funcionalidades-por-fase)
8. [Push notifications — especificação](#8-push-notifications--especificação)
9. [Segurança](#9-segurança)
10. [Cronograma e estimativas](#10-cronograma-e-estimativas)
11. [Riscos e mitigações](#11-riscos-e-mitigações)
12. [Critérios de aceite do MVP](#12-critérios-de-aceite-do-mvp)

---

## 1. Visão geral e princípios

### Objetivo
Permitir que agentes atendam conversas (WhatsApp, Instagram, e-mail etc.) pelo celular com paridade das ações essenciais do web: responder com texto/mídia/áudio, atribuir, fechar, mover no funil, notas internas — e serem **notificados via push** com o app fechado.

### Princípios
1. **Zero duplicação de lógica** — o app consome a API v1; toda extensão da API reutiliza os Services existentes (`ConversationService`, `AttachmentService`, `IntegrationService`).
2. **API-first** — nenhuma tela do app é iniciada antes do endpoint correspondente estar pronto e testado (a Fase 0 destrava tudo).
3. **Backend intocado no core** — seguindo o padrão da API v1: só arquivos novos em `/api/`, sem alterar o fluxo web (exceção: disparos de push nos hooks `WebSocket::notify*`, que hoje são no-op).
4. **Identidade visual consistente** — o app herda o design do web (azul primário, layout estilo Chatwoot, notas amarelas, dark mode), adaptado a padrões mobile nativos.
5. **Foreground = polling, background = push** — mesmo modelo de tempo real do web (polling delta), com FCM cobrindo o app fechado.

### O que fica FORA do app (decidido)
- Kanban/board de funis (só "mover etapa" via menu), dashboards/analytics, configurações administrativas, automações, campanhas, gerador de mockups, softphone completo (fase 3 avalia), gestão de agentes IA (apenas "assumir conversa da IA" entra).

---

## 2. FASE 0 — Atualização da API (backend)

Pré-requisito de tudo. Trabalho concentrado em `/api/v1/` + 1 service novo + 1 migration. Estimativa: **2–3 semanas**.

### 2.0 Auditoria inicial (dia 1)
- [ ] Confirmar em produção: migrations `091_create_api_tokens_table` e `092_create_api_logs_table` executadas
- [ ] Confirmar roteamento `/api/v1/*` → `api/index.php` (`.htaccess`)
- [ ] Testar fluxo completo: `auth/login` → `conversations` → `messages` com JWT real
- [ ] Corrigir CORS inválido (`Allow-Origin: *` + `Allow-Credentials: true` no `CorsMiddleware`)

### 2.1 Novos endpoints — Tempo real (JWT)

**`POST /api/v1/realtime/poll`** — portar `RealtimeController::poll` para a API v1 (a lógica de deltas já existe em `Message::getNewMessagesSince` / `getStatusUpdatesSince`).

```jsonc
// Request
{
  "subscribed_conversations": [123, 456],   // conversas abertas no app
  "last_update_time": 1751371200000,        // ms epoch do último poll
  "activity_type": "active"                 // heartbeat de disponibilidade
}
// Response (envelope padrão)
{
  "success": true,
  "data": {
    "timestamp": 1751371205000,
    "new_messages": [ { "id", "conversation_id", "sender_type", "content", "message_type", "attachments", "created_at", ... } ],
    "conversation_updates": [ { "id", "status", "agent_id", "unread_count", "last_message_preview", ... } ],
    "new_conversations": [ ... ],
    "message_status_updates": [ { "message_id", "status", "delivered_at", "read_at" } ]
  }
}
```

**`GET /api/v1/conversations/:id/messages?after_id=&before_id=&limit=`** — estender o endpoint atual com cursores (a lógica `beforeId`/`afterId` já existe em `Message::getMessagesWithSenderDetails`). `after_id` para polling da conversa aberta; `before_id` para scroll infinito do histórico.

### 2.2 Novos endpoints — Mídia

**`POST /api/v1/conversations/:id/messages` (estender)** — aceitar `multipart/form-data` com `attachments[]` + `content` opcional, roteando para o mesmo caminho de `ConversationController::sendMessage` (upload via `AttachmentService::upload`, envio em background, conversão de áudio WebM/AAC→OGG, base64 para iOS já resolvidos).

Campos: `content`, `attachments[]`, `quoted_message_id`, `is_note` (bool → nota interna), `message_type`.

**`GET /api/v1/attachments/{path}`** — servir mídia com JWT no header **ou** URL assinada:
- `GET /api/v1/attachments/sign?path=...` → `{ "url": "https://.../attachments/xyz?sig=...&exp=..." }` (HMAC com `JWT_SECRET`, expiração 1h). URLs assinadas são necessárias para o player de vídeo/áudio nativo e cache de imagens (que não mandam header).

### 2.3 Novos endpoints — Paridade com o web

Todos já têm lógica pronta no `ConversationController`/services — é rotear e adaptar o envelope:

| Endpoint novo (API v1) | Reutiliza | Prioridade |
|---|---|---|
| `POST /conversations/:id/mark-read` / `mark-unread` | `ConversationController::markRead/markUnread` | P0 |
| `GET/POST /conversations/:id/notes`, `PUT/DELETE /notes/:noteId` | `ConversationNoteService` | P0 |
| `GET /notifications`, `GET /notifications/unread`, `POST /notifications/:id/read`, `POST /notifications/read-all` | `NotificationController` | P0 |
| `GET /conversations/:id/cloud-window`, `POST /conversations/:id/send-cloud-template` | `WhatsAppCloudService` | P0 (se WhatsApp oficial em uso) |
| `POST /conversations/check-existing`, `POST /conversations/new` | `ConversationController::newConversation` | P0 |
| `POST /messages/:id/react` | `reactToMessage` | P1 |
| `GET /conversations/:id/search-messages?q=` | `searchMessages` | P1 |
| `POST /conversations/:id/forward`, `GET /conversations/for-forwarding` | `forwardMessage` | P1 |
| `GET /message-templates/available`, `/personal`, `POST /message-templates/:id/process` | `MessageTemplate*` | P1 |
| `POST /conversations/:id/mention`, `GET /conversations/invites`, `POST /invites/:id/accept|decline` | `ConversationMentionService` | P1 |
| `POST /conversations/:id/schedule-message`, `GET/DELETE .../scheduled-messages` | `ScheduledMessageService` | P2 |
| `POST /conversations/:id/reminders`, `GET .../reminders` | `ReminderService` | P2 |
| `POST /conversations/:id/pin|unpin|spam` | controller | P2 |
| `GET /conversations/:id/timeline|sentiment|performance` | controller | P2 |
| `GET /conversations/:id/participants` (+ add/remove — já existe), `request-participation`, `approve/reject` | `ConversationController` | P1 |
| `GET /conversation-tabs` (abas customizadas do usuário) | `UserConversationTab` | P2 |

### 2.4 Push — infraestrutura nova

**Migration `1XX_create_device_tokens_table.php`**:
```
device_tokens: id, user_id (FK users), token (VARCHAR 255, UNIQUE),
platform ENUM('ios','android'), device_name, app_version,
created_at, last_used_at, revoked_at NULL
```

**Endpoints**:
- `POST /api/v1/devices` — `{ token, platform, device_name, app_version }` (upsert por token)
- `DELETE /api/v1/devices/:token` — no logout

**Service `app/Services/PushNotificationService.php`** (FCM HTTP v1, service account JSON em `config/` ou env):
- `sendToUser($userId, $notification, $data)` — busca tokens ativos, envia, remove tokens inválidos (erro `UNREGISTERED`)
- Tipos/payloads na seção 8

**Pontos de disparo** (substituir/complementar os hooks no-op existentes):
| Evento | Onde plugar |
|---|---|
| Mensagem nova do cliente | `WhatsAppService::processWebhook` (hook `WebSocket::notifyNewMessage`, `WhatsAppService.php:4659`) + equivalentes em `WhatsAppCloudService`, `EvolutionService`, `InstagramGraphService`, `NotificameService` — ponto único preferido: dentro de `ConversationService::sendMessage` quando `sender_type='contact'` |
| Conversa atribuída a mim | `ConversationService::assignToAgent` |
| Menção/convite recebido | `ConversationMentionService::createMention` |
| Nova conversa não atribuída (para o setor) | `ConversationService::create` / `AutomationService` |
| Lembrete vencido | cron de `ConversationReminder::getPendingToNotify` |
| SLA warning/breach | onde hoje seta `sla_warning_sent` |

**Regra anti-spam**: não enviar push se o usuário tem poll ativo há <30s (presença no `realtime/poll` já registra heartbeat) — evita notificar quem está com o app aberto na conversa.

### 2.5 Ajustes transversais
- [ ] **Rate limit**: criar perfil para o app — poll a 5s = 12 req/min só de realtime; subir default do token do app para 300 req/min ou isentar `/realtime/poll` do contador
- [ ] **Refresh token**: adicionar blacklist/rotação no logout (tabela ou reuso de `api_tokens`)
- [ ] **Versionamento**: manter tudo em `/api/v1`; mudanças breaking → `/api/v2`
- [ ] **Documentação OpenAPI** (`api/openapi.yaml`) — contrato para o time mobile

---

## 3. Stack tecnológico do app

### Recomendação: **Flutter** ✅

| Critério | Flutter | React Native |
|---|---|---|
| 1 codebase iOS+Android | ✅ | ✅ |
| Performance de lista longa (chat) | ✅ excelente (`ListView` + slivers) | ⚠️ requer FlashList/tuning |
| Gravação de áudio + player | ✅ `record` + `just_audio` | ✅ libs equivalentes |
| Push (FCM) | ✅ `firebase_messaging` | ✅ |
| Dark mode/temas | ✅ `ThemeData` nativo | ✅ |
| Curva p/ time PHP (sem time JS forte) | ✅ Dart é simples | ⚠️ ecossistema JS/native modules |
| Consistência visual iOS=Android | ✅ pixel-perfect | ⚠️ componentes nativos divergem |

**Pacotes principais**: `dio` (HTTP + interceptor JWT/refresh), `riverpod` (estado), `drift` ou `hive` (cache local), `firebase_messaging` + `flutter_local_notifications`, `record` (áudio), `just_audio`, `cached_network_image`, `image_picker`/`file_picker`, `flutter_secure_storage` (tokens), `go_router` (navegação/deep links).

> Se o time preferir web-skills: React Native + Expo é aceitável; o plano de telas/API não muda.

---

## 4. Arquitetura do app

```
┌─────────────────────────────────────────────┐
│ UI (telas — seção 6)                        │
├─────────────────────────────────────────────┤
│ Estado (Riverpod)                           │
│  • AuthState (JWT + refresh automático)     │
│  • ConversationListState (abas/filtros)     │
│  • ChatState (mensagens da conversa aberta) │
│  • RealtimeState (poll loop + merge deltas) │
├─────────────────────────────────────────────┤
│ Repositórios                                │
│  • ApiClient (dio): envelope {success,data} │
│  • Cache local (drift): conversas, msgs,    │
│    contatos, fila de envio offline          │
├─────────────────────────────────────────────┤
│ Plataforma: FCM, áudio, câmera/galeria,     │
│ secure storage, deep links                  │
└─────────────────────────────────────────────┘
```

### Fluxos-chave
- **Login**: `POST /auth/login` → guarda tokens no secure storage → registra device token FCM (`POST /devices`) → carrega `GET /auth/me` (permissões controlam UI).
- **Tempo real (foreground)**: loop `POST /realtime/poll` a cada **5s** (config vinda de `GET /api/realtime/config` se exposto na v1); merge dos deltas no estado; badge/som local.
- **Conversa aberta**: além do poll global, `GET /conversations/:id/messages?after_id=` a cada 5s (ou confiar só no poll global com a conversa em `subscribed_conversations` — decidir no piloto).
- **Background**: FCM data message → notificação local → tap → deep link `app://conversations/{id}`.
- **Envio otimista**: mensagem entra na UI com status "enviando"; POST em seguida; em falha → estado "erro + reenviar" (o backend já responde rápido e envia ao provider em background).
- **Fila offline**: mensagens digitadas sem rede ficam em fila local (drift) e são enviadas ao reconectar — v1.1, não MVP.
- **Cache**: lista de conversas e últimas 50 mensagens por conversa persistidas localmente → abertura instantânea + leitura offline.

---

## 5. Tema e Design System

Herda a identidade do web (Metronic/estilo Chatwoot, azul primário, notas amarelas) com componentes mobile nativos. Suporte a **light + dark** desde o MVP (o web já tem dark mode).

### 5.1 Paleta

| Token | Light | Dark | Uso |
|---|---|---|---|
| `primary` | `#3B82F6` | `#4F9CF9` | Botões, bolha do agente, links, badge não lidas |
| `primaryDark` | `#2563EB` | `#3B82F6` | Pressed/CTA |
| `background` | `#F5F8FA` | `#0F1014` | Fundo geral |
| `surface` | `#FFFFFF` | `#1E1E2D` | Cards, lista, composer |
| `surfaceAlt` | `#F1F5F9` | `#2B2B40` | Bolha do cliente, chips |
| `textPrimary` | `#181C32` | `#F5F8FA` | Títulos |
| `textSecondary` | `#7E8299` | `#9899AC` | Preview, timestamps |
| `success` | `#50CD89` | `#50CD89` | Status "Aberta", ✓✓ lido, online |
| `warning` | `#FFC700` | `#FFC700` | SLA warning, tag Urgente, **notas internas** (fundo `#FFF8DD` light / `#3A3421` dark) |
| `danger` | `#F1416C` | `#F1416C` | SLA estourado, erro de envio, tag VIP |
| `info` | `#7239EA` | `#7239EA` | Menções, IA/coaching |
| Canais | WhatsApp `#25D366` · Instagram `#E4405F` · Email `#3B82F6` · Chat `#7239EA` | | Ícone/borda do avatar |

### 5.2 Tipografia
- **Fonte**: Inter (mesma família visual do web/Metronic)
- Escala: `title` 20/semibold · `subtitle` 16/semibold · `body` 15/regular (mensagens) · `caption` 12/regular (timestamps, previews) · `badge` 11/bold
- Tamanho mínimo tocável: 44×44pt

### 5.3 Componentes do design system
- **ConversationTile**: avatar com badge do canal → nome + tempo (canto direito) → preview 1 linha → linha de chips (tags coloridas + badge não lidas azul + pino). Borda esquerda 3px colorida = estado SLA (verde ok / amarelo warning / vermelho estourado) — replica o `applySlaVisualState` do web.
- **MessageBubble**: cliente à esquerda (surfaceAlt), agente à direita (primary, texto branco), IA à direita com borda roxa + ícone 🤖, **nota interna** largura total fundo amarelo + ícone 🔒, mensagem de sistema centralizada em pill cinza. Rodapé: hora + status (🕓 enviando, ✓ enviado, ✓✓ entregue, ✓✓ azul lido, ⚠️ erro c/ tap para reenviar). Suporte a reply/quote (barra lateral com preview) e reações (chips sob a bolha).
- **Composer**: campo multilinha + botões anexo/câmera/áudio/emoji + toggle **Nota** (muda o fundo do campo para amarelo — affordance idêntica ao web) + botão enviar. Segurar microfone = gravar (UI estilo WhatsApp: timer + arrastar p/ cancelar + lock).
- **StatusBadge**: Aberta (verde), Pendente (amarelo), Resolvida (cinza), IA ativa (roxo).
- **FilterSheet**: bottom sheet com status, canal, setor, funil/etapa, tags, conta WhatsApp, período.
- **AttachmentPreview**: grid de pendentes com progresso e remover.

### 5.4 Interações
- Pull-to-refresh na lista; swipe nas conversas: → marcar lida/não lida, ← resolver (com undo via snackbar)
- Long-press em mensagem: responder, reagir, copiar, encaminhar
- Haptics em enviar/gravar/erro; skeleton loaders; som de nova mensagem (respeitando modo silencioso)

---

## 6. Layout e navegação — telas

### Estrutura de navegação

```
Login
 └─ Shell (bottom navigation — 4 abas)
     ├─ 💬 Conversas (home)
     │    └─ Chat ──┬─ Detalhes da conversa (sheet/tela)
     │              ├─ Perfil do contato
     │              └─ Busca em mensagens
     ├─ 🔔 Notificações (+ convites/menções)
     ├─ 📇 Contatos (lista + detalhe, read-mostly)
     └─ ⚙️ Perfil/Config (disponibilidade, som, tema, sair)
```

### 6.1 Login
Logo + email/senha + "manter conectado". Erros inline. (Biometria para reabrir sessão: fase 2.)

### 6.2 Lista de conversas (home)
```
┌──────────────────────────────┐
│ Conversas          🔍  ⚙️(filtro)│
│ [Minhas] [Não atrib.] [Todas]│  ← abas (chips roláveis; abas custom fase 2)
├──────────────────────────────┤
│ ▎🟢 Maria Silva        5min  │  ▎= barra SLA
│ ▎   Olá, preciso de ajuda…   │
│ ▎   [WA] [VIP] [Urgente] (2) │
│ ▎🔴 Carlos Oliveira    30min │
│ ▎   Gostaria de saber sobre… │
│ ▎   [Email] [Novo]           │
│  …scroll infinito…           │
├──────────────────────────────┤
│ [💬] [🔔•3] [📇] [⚙️]         │
└──────────────────────────────┘
              (＋) FAB nova conversa
```
- Ordenação: mais recente primeiro, fixadas no topo; contador por aba; busca por nome/telefone/conteúdo.

### 6.3 Chat
```
┌──────────────────────────────┐
│ ← MS Maria Silva      📞  ⋮  │  ← header: tap = detalhes; ⋮ = ações
│    WhatsApp · Aberta · João  │
├──────────────────────────────┤
│      [pill: Conversa iniciada]│
│ ⬜ Olá, preciso de ajuda…     │
│                    13:23     │
│         Olá Maria! Claro… 🟦 │
│                 13:25 ✓✓     │
│ 🟨 🔒 Nota · João: Cliente   │
│    VIP - dar prioridade      │
│ ⬜ Obrigada! Estou aguardando │
├──────────────────────────────┤
│ [banner: janela 24h expira em 3h — Cloud]│
│ 📎 📷 [ Digite sua mensagem ] 🎤 ➤ │
│ ○ Nota interna               │
└──────────────────────────────┘
```
- Menu ⋮: atribuir/transferir, mover etapa, trocar setor, tags, resolver/reabrir, marcar não lida, fixar, agendar mensagem (F2), lembrete (F2), assumir da IA (se IA ativa).
- Banner de conversa **não atribuída**: "Atribuir a mim" (CTA primário) — espelha o fluxo do web de auto-atribuição no primeiro envio.
- Banner de **janela 24h fechada** (Cloud): composer bloqueado + botão "Enviar template".

### 6.4 Detalhes da conversa (bottom sheet expansível)
Contato (avatar, telefone, e-mail, tags do contato) · Atendimento (agente, setor, funil/etapa, prioridade, SLA com tempos) · Participantes · Notas (lista + criar) · Ações: resolver, transferir, spam. Link "ver perfil completo do contato".

### 6.5 Notificações
Lista agrupada (hoje/ontem/…): mensagem nova, atribuição, menção/convite (com **Aceitar/Recusar** inline), SLA, lembrete. Tap → deep link para a conversa. "Marcar todas como lidas".

### 6.6 Contatos
Busca + lista alfabética → detalhe: dados, histórico de conversas (`GET /contacts/:id/conversations`), botão "Nova conversa". Edição básica (nome/email): fase 2.

### 6.7 Perfil/Configurações
Disponibilidade (online/ausente — alimenta o heartbeat), notificações (sons por tipo, quiet hours), tema (claro/escuro/sistema), conta (nome, avatar), versão, sair (revoga device token).

---

## 7. Funcionalidades por fase

### MVP — Fase 1 (app v1.0)
| Área | Funcionalidades |
|---|---|
| Auth | Login JWT, refresh automático, logout, permissões do `auth/me` refletidas na UI |
| Lista | Abas Minhas/Não atribuídas/Todas, filtros (status/canal/setor/funil/tags), busca, scroll infinito, badges não lidas, indicador SLA, swipe actions, pull-to-refresh |
| Chat | Histórico com cursor, texto, **foto/câmera/documento**, **áudio gravado**, reply/quote, status ✓✓, imagens com lightbox, player de áudio/vídeo, nota interna (toggle), lazy-load de mídia |
| Ações | Atribuir a mim/outro, resolver/reabrir, mover etapa, trocar setor, tags, marcar lida/não lida |
| Nova conversa | Escolher contato/número + conta de envio, checagem de existente, template obrigatório (Cloud) |
| Cloud | Banner janela 24h + envio de template aprovado |
| Push | FCM: mensagem nova, atribuição, menção; deep link; badge do ícone |
| Config | Disponibilidade, sons, tema claro/escuro |

### Fase 2 (v1.1–v1.2)
Respostas rápidas/templates (+atalho "/" e variáveis) · reações · encaminhar · busca em mensagens · menções/convites/solicitações completos · participantes (add/remover/sair) · agendamento + lembretes · abas customizadas do usuário · perfil do contato editável · fila offline de envio · biometria · fixar/spam · timeline/sentimento.

### Fase 3 (avaliar por uso)
Assistente IA (gerar/melhorar resposta) e coaching · copilot · métricas do agente no app · chamadas API4Com (ou handoff para o discador) · merge de conversas/troca de conta · WebSocket real substituindo polling.

---

## 8. Push notifications — especificação

### Tipos e payloads (FCM data messages + notification)

| Tipo | `data.type` | Título/corpo | Deep link |
|---|---|---|---|
| Mensagem nova | `new_message` | Nome do contato / preview (ou "📷 Foto", "🎤 Áudio") | `app://conversations/{id}` |
| Conversa atribuída | `assigned` | "Nova conversa atribuída" / contato + canal | idem |
| Menção/convite | `mention` | "@João te mencionou" / nota do convite | idem (abre sheet de convite) |
| Não atribuída (setor) | `unassigned` | "Nova conversa no setor X" | lista filtrada |
| SLA | `sla_warning` / `sla_breached` | "SLA em risco" / contato + tempo | conversa |
| Lembrete | `reminder` | Nota do lembrete | conversa |

### Regras
1. **Supressão por presença**: sem push para quem fez poll há <30s (está com o app aberto).
2. **Agrupamento**: por conversa (`collapse_key`/`thread-id`) — várias mensagens da mesma conversa = 1 notificação atualizada.
3. **Respeitar disponibilidade**: agente "ausente" recebe só atribuições diretas e menções (configurável).
4. **iOS**: usar APNs via FCM com `content-available` para atualizar badge; sons custom = os mesmos do web (`new-message`, `mention` etc. convertidos para `.caf`).
5. **Higiene de tokens**: resposta `UNREGISTERED` → revogar token na tabela.

---

## 9. Segurança

- Tokens em secure storage (Keychain/Keystore); nunca em SharedPreferences
- Refresh token com rotação + blacklist no logout (item Fase 0)
- Certificate pinning no `dio` (fase 2)
- Mídia sempre por URL assinada com expiração (nunca path público)
- Permissões server-side: a UI esconde ações sem permissão, mas a API v1 já valida via `Permission::userHasPermission` — manter
- Rate limit por token de app; logs em `api_logs` já cobrem auditoria
- LGPD: dados de conversa cacheados no dispositivo são apagados no logout

---

## 10. Cronograma e estimativas

Premissa: 1 dev backend (PHP) + 1–2 devs Flutter. Sprints de 2 semanas.

| Sprint | Entrega |
|---|---|
| **S1** (Fase 0a) | Auditoria API v1 em produção, CORS, `realtime/poll` JWT, cursores em messages, mark-read, notes, notifications |
| **S2** (Fase 0b) | Upload de mídia multipart, URLs assinadas de anexos, device_tokens + `PushNotificationService` + disparos, cloud-window/template, OpenAPI |
| **S3** (App) | Fundação Flutter: design system, auth + refresh, lista de conversas (abas/filtros/busca), cache local |
| **S4** | Chat: histórico, envio texto, polling foreground, status ✓✓, reply, notas internas |
| **S5** | Mídia: foto/câmera/documento, gravação de áudio, players, lightbox; ações de conversa (atribuir/resolver/etapa/setor/tags) |
| **S6** | Push end-to-end (FCM + deep links + badges), nova conversa, banner 24h/templates Cloud, tela de notificações, config |
| **S7** | Hardening: dark mode final, offline básico de leitura, testes em aparelhos, beta interno (TestFlight/Play Internal) |
| **S8** | Correções do beta + publicação nas lojas (**MVP ~16 semanas**) |
| S9–S12 | Fase 2 em releases quinzenais |

**Custos de terceiros**: Apple Developer US$99/ano · Google Play US$25 único · Firebase/FCM gratuito · (Flutter e libs: open source).

---

## 11. Riscos e mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| API v1 nunca usada em produção (criada 01/2025, sem consumidor conhecido) | Bugs latentes atrasam o app | Sprint 1 começa com suite de testes de contrato (Postman/PHPUnit) contra staging |
| Polling 5s × muitos agentes sobrecarrega MySQL (histórico de QPS alto já documentado) | Performance geral do sistema | Poll v1 reusa as queries otimizadas do web; medir QPS no piloto; WebSocket real na fase 3 se necessário |
| Dois formatos de envelope (API v1 vs AJAX web) se misturando | Bugs de parsing | App consome **exclusivamente** `/api/v1`; toda lacuna vira endpoint novo na v1 (nunca chamar rota web) |
| Push duplicado/notificação de conversa que o agente está vendo | UX ruim | Regra de supressão por presença + collapse_key |
| Áudio: codecs divergentes entre iOS (AAC) e pipeline OGG do backend | Áudio não chega no WhatsApp | `AttachmentService` já converte via ffmpeg; validar ffmpeg no servidor de produção no S2 |
| Janela 24h Cloud bloqueando envio sem feedback claro | Agente "não consegue responder" | Banner explícito + fluxo de template no MVP |
| Escopo crescer para dentro do app (IA, kanban…) | Atraso do MVP | Corte de escopo da seção 1 é contrato; extras só na fase 3 |

---

## 12. Critérios de aceite do MVP

1. Agente faz login e vê suas conversas em <2s (cache local + API)
2. Mensagem recebida no WhatsApp aparece no app em ≤5s com app aberto, e como push em ≤10s com app fechado (tap abre a conversa certa)
3. Envio de texto, foto, documento e **áudio gravado** chega ao cliente no WhatsApp (incluindo iPhone do cliente — pipeline base64/OGG)
4. Atribuir, resolver, reabrir, mover etapa, trocar setor e tags refletem no web imediatamente
5. Nota interna criada no app aparece amarela no web (e vice-versa) e nunca é enviada ao cliente
6. Conversa Cloud fora da janela 24h bloqueia composer e permite enviar template aprovado
7. Dark mode íntegro em todas as telas; nenhuma ação disponível sem a permissão correspondente
8. App aprovado nas revisões da App Store e Play Store

---

## Anexo A — Resumo do que muda no backend (arquivos)

```
api/v1/routes.php                        ← ~25 rotas novas
api/v1/Controllers/RealtimeController.php   (novo)
api/v1/Controllers/NotesController.php      (novo)
api/v1/Controllers/NotificationsController.php (novo)
api/v1/Controllers/DevicesController.php    (novo)
api/v1/Controllers/AttachmentsController.php (novo — servir/assinar)
api/v1/Controllers/MessagesController.php   ← multipart + cursores + react/forward/search
api/v1/Controllers/ConversationsController.php ← mark-read, pin, cloud-window, mention, schedule…
api/middleware/CorsMiddleware.php        ← fix credentials/wildcard
app/Services/PushNotificationService.php (novo — FCM)
app/Services/WebSocketService.php        ← broadcast passa a chamar push (mantém no-op WS)
database/migrations/1XX_create_device_tokens_table.php (novo)
config/firebase.php + service account    (novo)
api/openapi.yaml                         (novo — contrato)
```

Zero alterações em controllers/views do web — mesma filosofia da criação da API v1.
