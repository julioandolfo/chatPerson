# 📱 Análise: App Mobile (iOS/Android) para o Módulo de Conversations

**Data**: 01/07/2026
**Objetivo**: Mapear o que o sistema já oferece e o que precisa ser construído para lançar um app mobile focado **apenas** no módulo de conversas (atendimento).

---

## 1. Resumo executivo

O backend está **bem posicionado** para um app mobile: existe uma API REST v1 com JWT (`/api/v1`), toda a lógica de negócio vive em Services reutilizáveis, e o frontend web já consome tudo via endpoints JSON. Porém, há **4 lacunas críticas** que precisam ser resolvidas antes (ou durante) o desenvolvimento do app:

| # | Lacuna | Impacto | Esforço |
|---|--------|---------|---------|
| 1 | **Sem push notifications** (FCM/APNs inexistentes) | App não notifica com tela bloqueada — inviabiliza atendimento mobile | Alto |
| 2 | **API v1 não aceita upload de mídia** (só texto) | Sem envio de foto/áudio/documento pelo app | Médio |
| 3 | **Endpoint de polling/tempo real é session-based** (`/api/realtime/poll` usa cookie de sessão, não JWT) | App não recebe atualizações em tempo real pela API | Médio |
| 4 | **WebSocket é código morto** (Ratchet não instalado, broadcast é stub) | Não contar com WS; usar polling ou implementar WS de verdade | — (decisão) |

**Conclusão**: dá para construir o app consumindo a API v1 existente para ~70% das funcionalidades (login, listar conversas, mensagens, atribuir, fechar, tags, funil), mas é obrigatório estender a API com: upload de mídia, polling delta com JWT, endpoints de leitura/reação/notas, e uma camada de push (FCM).

---

## 2. O que já existe e funciona

### 2.1 API REST v1 (`/api/index.php` → `api/v1/routes.php`)

Autenticação pronta para mobile:
- `POST /api/v1/auth/login` → retorna `access_token` (JWT HS256, 1h) + `refresh_token` (30 dias)
- `POST /api/v1/auth/refresh` — renovação
- `GET /api/v1/auth/me` — dados + permissões do usuário
- Alternativa: API Token permanente via header `X-API-Key` (com IP allowlist e rate limit por token)
- Rate limiting (100 req/min padrão), CORS configurável, logs de requisição (`api_logs`), envelope JSON padronizado (`ApiResponse`)

Endpoints já disponíveis (arquivo `api/v1/routes.php`):

| Recurso | Endpoints |
|---------|-----------|
| Conversas | `GET/POST /conversations`, `GET/PUT/DELETE /conversations/:id`, `assign`, `close`, `reopen`, `move-stage`, `department`, `tags` (add/remove) |
| Mensagens | `GET /conversations/:id/messages` (paginado), `POST .../messages` (**só texto**), `GET /messages/:id`, `POST /messages/send` (WhatsApp direto), `POST /messages/send-template`, `GET /templates` |
| Participantes | `GET/POST/DELETE /conversations/:id/participants` |
| Contatos | CRUD completo + `GET /contacts/:id/conversations` |
| Agentes | `GET /agents`, `/agents/:id`, `/agents/:id/stats` |
| Setores/Funis/Tags | listagem completa; funis com `stages` e `conversations` |
| Contas WhatsApp | `GET /whatsapp-accounts` |
| Stats | `GET /stats/overview`, `/conversations`, `/agents`, `/departments`, `/funnels`, `/sla` |

> ⚠️ Existe um segundo gateway espelho em `public/api.php` (usado pela integração WordPress). **Ignorar para o app** — usar `/api/v1` que é a implementação OO completa.

### 2.2 Backend de conversas (Services reutilizáveis)

Toda a lógica está em `app/Services/` e é reutilizada pela API v1 sem duplicação:
- `ConversationService::sendMessage` — grava mensagem, resolve quoted/anexos, dispara automações, envia ao provider em **background** (resposta HTTP imediata)
- `IntegrationService` — dispatcher unificado por `integration_account_id` (Meta Cloud, CoEx, Evolution, Baileys/Quepasa, Instagram, Notificame)
- `AttachmentService` — upload/validação/conversão de mídia (inclusive WebM→OGG via ffmpeg para áudio)
- Atribuição, permissões por funil, SLA, notas, menções/convites, lembretes, agendamento, tags, merge de conversas — tudo em services

### 2.3 Schema de dados sólido

- `conversations`: status, priority, channel, agente, setor, funil/etapa, SLA (first_response_at, pausas, reassignments), pinned, spam, metadata JSON, merge de contas
- `messages`: sender_type (`agent`/`contact`/`ai_agent`), message_type, attachments JSON, status/delivered_at/read_at, `external_id` (dedup WhatsApp), quoted message, reactions JSON
- Suporte a paginação por cursor (`beforeId`/`afterId` em `Message::getMessagesWithSenderDetails`) — ideal para scroll infinito no app

---

## 3. Lacunas críticas para o app

### 3.1 Push notifications — NÃO EXISTE (bloqueador)

- Nenhum vestígio de FCM, APNs, OneSignal ou web push no código
- Notificações atuais = toasts + sons no navegador, acionados por **polling** com a aba aberta
- **Sem push, o app mobile não funciona como ferramenta de atendimento** (agente precisa saber de mensagem nova com o app fechado)

**Onde injetar**: já existem hooks no-op prontos para reaproveitar — as chamadas `WebSocket::notifyNewMessage()` etc. (ex: `app/Services/WhatsAppService.php:4659`, e nos pontos de atribuição/menção do `ConversationService`). Basta trocar/complementar o stub por envio FCM.

**O que construir**:
1. Migration: tabela `device_tokens` (user_id, token, platform, created_at, last_used_at)
2. Endpoints: `POST /api/v1/devices` (registrar token), `DELETE /api/v1/devices/:token`
3. Service `PushNotificationService` (FCM HTTP v1 — cobre Android e iOS via APNs do Firebase)
4. Disparos: mensagem nova recebida, conversa atribuída a mim, menção/convite, SLA warning, lembrete

### 3.2 Upload de mídia na API v1 — NÃO EXISTE

- `POST /api/v1/conversations/:id/messages` aceita **apenas texto** (`body`)
- Upload multipart só existe na rota web `POST /conversations/{id}/messages` (session-based, campo `attachments[]`, processado por `AttachmentService::upload`)

**O que construir**: aceitar `multipart/form-data` no endpoint da API v1 (ou endpoint dedicado `POST /api/v1/conversations/:id/attachments`), reutilizando `AttachmentService` — o serviço já valida MIME/tamanho e converte áudio.

Limites atuais (frontend/backend): imagens e áudios 16 MB, vídeos 64–200 MB (comprimidos no servidor), documentos 100 MB.

**Áudio no iOS**: já resolvido no backend — áudio é convertido WebM→OGG (ffmpeg) e enviado ao WhatsApp como **base64** (iOS rejeita áudio por URL). No app nativo, gravar em AAC/OGG e enviar como anexo normal já cai nesse pipeline.

### 3.3 Tempo real — polling session-based, WebSocket morto

Estado real (documentação diz "WebSocket 100%", mas não é verdade):
- `cboden/ratchet` **não está instalado** no vendor; `WebSocketService::broadcast()` é stub (TODO comentado em `app/Services/WebSocketService.php:46`)
- O frontend web usa **polling**: `POST /api/realtime/poll` a cada 3s (deltas: new_messages, conversation_updates, new_conversations, message_status_updates, agent_status) + `GET /conversations/{id}/messages?last_message_id=` a cada 10s na conversa aberta
- `POST /api/realtime/poll` exige **cookie de sessão PHP** — o app com JWT não consegue usar

**Opções para o app** (recomendação: A agora, B depois):
- **A (rápida)**: criar `POST /api/v1/realtime/poll` com JWT, reaproveitando a lógica do `RealtimeController::poll` (que já usa `Message::getNewMessagesSince` e `Message::getStatusUpdatesSince`). Polling de 3–5s com app em foreground + push para background é padrão de mercado e suficiente.
- **B (ideal, depois)**: implementar WebSocket de verdade (soketi/Centrifugo/Node no `whatsapp-service` que já é Node) — mas não é pré-requisito do MVP.

### 3.4 Paridade de endpoints — o que existe no AJAX web mas falta na API v1

Funcionalidades que o app provavelmente precisa e teriam que ser expostas na API v1 (a lógica já existe em `ConversationController` / services, é só rotear):

| Funcionalidade | Rota web existente | Prioridade p/ app |
|---|---|---|
| Marcar lida/não lida | `POST /conversations/{id}/mark-read` / `mark-unread` | **Alta** |
| Reações a mensagens | `POST /conversations/messages/{id}/react` | Média |
| Notas internas | `GET/POST /conversations/{id}/notes` | **Alta** |
| Busca em mensagens | `GET /conversations/{id}/search-messages` | Média |
| Notificações in-app | `GET /notifications`, `/notifications/unread`, `mark-read` | **Alta** |
| Encaminhar mensagem | `POST /conversations/{id}/forward` | Média |
| Fixar/spam | `POST /conversations/{id}/pin|unpin|spam` | Baixa |
| Menções/convites | `/conversations/{id}/mention`, `/conversations/invites/*` | Média |
| Agendamento/lembretes | `/conversations/{id}/schedule-message`, `/reminders` | Baixa |
| Janela 24h Cloud + templates | `/conversations/{id}/cloud-window`, `/send-cloud-template` | **Alta** (se usar WhatsApp oficial) |
| Respostas rápidas/templates | `/message-templates/available`, `/personal`, `/variables` | Média |
| Servir mídia | `GET /attachments/{path}` (hoje session-based) | **Alta** — precisa aceitar JWT ou URL assinada |

> ⚠️ Atenção ao item "servir mídia": os anexos são servidos por `AttachmentController` sob sessão. O app precisa de acesso via JWT (header) ou URLs assinadas com expiração — sem isso, imagens/áudios não carregam no app.

---

## 4. Funcionalidades do web a replicar no app (escopo sugerido)

Inventário do frontend web (`views/conversations/index.php`, ~25k linhas de JS) priorizado para mobile:

### MVP (fase 1)
- Login (JWT) + refresh automático
- Lista de conversas: abas (minhas/não atribuídas/todas), filtros (status, canal, setor, funil, tags), busca, scroll infinito, badges de não lidas, indicador SLA
- Chat: histórico paginado, envio de texto, **mídia** (foto/câmera/documento), **áudio** (gravação), reply/quote, status de entrega/leitura (✓✓), lightbox de imagem
- Ações de conversa: atribuir a mim/outro agente, fechar/reabrir, mover etapa do funil, trocar setor, tags
- Notas internas (toggle no composer)
- Push notifications + badge count
- Nova conversa (com checagem de existente e template obrigatório p/ Cloud)

### Fase 2
- Respostas rápidas/templates (incluindo atalho "/") e variáveis
- Templates WhatsApp Cloud + banner de janela 24h
- Participantes múltiplos, menções, convites, solicitações de participação
- Reações, encaminhar mensagem, busca em mensagens
- Agendamento de mensagens e lembretes
- Detalhes do contato (histórico, editar, agentes do contato)

### Fase 3 (avaliar se faz sentido no mobile)
- Assistente IA (gerar/melhorar resposta), coaching em tempo real, copilot
- Agentes IA na conversa (escalar de IA p/ humano — o botão "assumir" é útil já na fase 1)
- Kanban/board de funil, merge de conversas, troca de conta, mockups, softphone API4Com

---

## 5. Arquitetura recomendada para o app

```
┌─────────────────────────────┐
│  App (iOS + Android)        │
│  Sugestão: Flutter ou       │
│  React Native (1 codebase)  │
└──────────┬──────────────────┘
           │ HTTPS (JWT Bearer)
           ▼
┌─────────────────────────────┐      ┌──────────────┐
│  /api/v1 (existente +       │◄─────┤ FCM (novo)   │
│  extensões novas)           │      │ push iOS+And │
│                             │      └──────▲───────┘
│  + POST /realtime/poll JWT  │             │
│  + upload de mídia          │   dispara em│
│  + mark-read, notes, etc    │   WhatsAppService::processWebhook
│  + POST /devices (FCM)      │   ConversationService::assignToAgent
└──────────┬──────────────────┘   (hooks WebSocket::notify* já existem)
           ▼
   Services existentes (zero duplicação)
```

**Estratégia de atualização no app**:
- Foreground: polling delta 3–5s (`/api/v1/realtime/poll` novo) — mesmo modelo do web
- Background/app fechado: push FCM (data message com `conversation_id` → deep link)
- Ao abrir conversa: `GET /conversations/:id/messages` com cursor `last_message_id`

**Formato de resposta**: usar sempre o envelope da API v1 (`{success, data|error}` com paginação `{items, pagination}`) — não misturar com o formato das rotas AJAX web, que é diferente.

---

## 6. Checklist de backend antes de começar o app

1. [ ] **Auditar/testar a API v1 em produção** — foi criada em 05/01/2025; validar se as migrations 091/092 (api_tokens, api_logs) rodaram e se `/api/.htaccess` roteia corretamente
2. [ ] `POST /api/v1/realtime/poll` com JWT (portar lógica do `RealtimeController::poll`)
3. [ ] Upload de mídia na API v1 (multipart → `AttachmentService`)
4. [ ] Servir anexos com JWT ou URL assinada (`AttachmentController`)
5. [ ] Endpoints faltantes: mark-read/unread, notes, notifications, reactions, forward, cloud-window/templates
6. [ ] Tabela `device_tokens` + `POST /api/v1/devices` + `PushNotificationService` (FCM)
7. [ ] Disparos de push nos hooks existentes (`WebSocket::notifyNewMessage` em `WhatsAppService.php:4659`, atribuição, menção, SLA)
8. [ ] Revisar CORS: `Access-Control-Allow-Origin: *` com `Allow-Credentials: true` no `CorsMiddleware` é inválido/inseguro (para app nativo CORS não importa, mas corrigir de todo modo)
9. [ ] Segurança JWT: implementação é própria (HS256 manual em `api/helpers/JWTHelper.php`) — revisar (algoritmo fixo ok, mas considerar blacklist de refresh tokens no logout)
10. [ ] Rate limit: avaliar se 100 req/min comporta polling de 3–5s + uso normal (poll a 3s = 20 req/min só de realtime)

---

## 7. Referências no código

| Área | Arquivos-chave |
|---|---|
| API v1 | `api/index.php`, `api/v1/routes.php`, `api/v1/Controllers/*`, `api/middleware/ApiAuthMiddleware.php`, `api/helpers/JWTHelper.php`, `api/helpers/ApiResponse.php` |
| Conversas (lógica) | `app/Controllers/ConversationController.php` (4.9k linhas), `app/Services/ConversationService.php`, `app/Models/Conversation.php`, `app/Models/Message.php` |
| Mídia | `app/Services/AttachmentService.php`, `app/Controllers/AttachmentController.php`, `CONFIGURACAO_UPLOAD_ARQUIVOS.md`, `CORRECAO_AUDIO_IOS_BASE64.md` |
| Tempo real | `app/Controllers/RealtimeController.php`, `public/assets/js/realtime-client.js`, `app/Services/WebSocketService.php` (stub) |
| Inbound (webhooks) | `app/Controllers/WebhookController.php`, `app/Controllers/MetaWebhookController.php`, `app/Services/WhatsAppService.php::processWebhook`, `whatsapp-service/` (Node/Baileys) |
| Frontend web (referência de features) | `views/conversations/index.php`, `views/conversations/sidebar-conversation.php`, `public/assets/js/notification-manager.js` |
