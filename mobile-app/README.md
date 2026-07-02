# Chat Privus — App Mobile (Expo)

Aplicativo de atendimento (para **agentes**) do sistema **chatPerson** — módulo de conversas
multicanal (WhatsApp, Instagram, e-mail e chat). Construído com **Expo SDK 52**,
**React Native 0.76**, **TypeScript** e **expo-router**.

## Requisitos

- Node.js 18+ (recomendado 20 LTS)
- npm 9+
- Conta Expo (para builds via EAS)
- Backend do chatPerson com os endpoints da **Fase 0** habilitados (ver abaixo)

## Setup

```bash
cd mobile-app
npm install
npx expo start
```

Abra no dispositivo com o app **Expo Go** (escaneando o QR code) ou em um
emulador (`a` para Android, `i` para iOS no terminal do Metro).

> Observação: recursos de **push notification** exigem um _development build_
> (não funcionam no Expo Go a partir do SDK 53; no SDK 52 funcionam de forma
> limitada). Para testar push de verdade, use o development build abaixo.

## Variáveis de ambiente

A URL do backend é lida de `EXPO_PUBLIC_API_URL` (fallback:
`https://chat.personizi.com.br`). A API é consumida em `${BASE}/api/v1`.

```bash
# apontando para outro ambiente
EXPO_PUBLIC_API_URL=https://staging.meudominio.com npx expo start
```

Nos builds EAS, a variável é definida por profile em `eas.json` (campo `env`).

## Development build (EAS)

1. Instale o CLI e faça login:

```bash
npm install -g eas-cli
eas login
```

2. Vincule o projeto (isso preenche o `extra.eas.projectId` do `app.json` —
   **obrigatório** para push notifications):

```bash
eas init
```

3. Gere o build de desenvolvimento:

```bash
eas build --profile development --platform android   # ou ios
```

4. Instale o artefato no dispositivo e rode `npx expo start --dev-client`.

## Publicação

```bash
# build de produção
eas build --profile production --platform all

# envio para as lojas (App Store / Play Store)
eas submit --platform ios
eas submit --platform android
```

Profiles disponíveis em `eas.json`: `development` (dev client, distribuição
interna), `preview` (APK/IPA interno para QA) e `production` (auto-increment de
versão).

## Estrutura de pastas

```
mobile-app/
├── app/                       # rotas (expo-router)
│   ├── _layout.tsx            # providers (React Query), auth gate, notif handler
│   ├── login.tsx
│   ├── (tabs)/
│   │   ├── _layout.tsx        # bottom tabs: Conversas, Notificações, Contatos, Perfil
│   │   ├── index.tsx          # lista de conversas (filtros, busca, scroll infinito)
│   │   ├── notifications.tsx
│   │   ├── contacts.tsx
│   │   └── profile.tsx
│   └── conversations/[id].tsx # tela de chat
├── src/
│   ├── api/                   # axios client (interceptors) + módulos por domínio
│   ├── components/            # ConversationTile, MessageBubble, Composer, sheets…
│   ├── hooks/                 # useConversations, useMessages, useRealtime, push, áudio
│   ├── stores/                # zustand: auth, ui (filtros), settings (tema/sons)
│   ├── theme/                 # design system (cores light/dark, tipografia, useTheme)
│   ├── types/                 # tipos do contrato da API
│   ├── utils/                 # formatação de datas, arquivos, telefone
│   └── config.ts              # URL da API e constantes
├── app.json
├── eas.json
└── tsconfig.json              # strict, paths @/* → src/*
```

## Como o backend deve estar configurado (Fase 0)

Todas as respostas usam o envelope `{ "success": true, "data": ... }` ou
`{ "success": false, "error": { "message", "code" } }`. Endpoints consumidos
(prefixo `/api/v1`):

| Domínio | Endpoints |
| --- | --- |
| Auth | `POST /auth/login`, `POST /auth/refresh`, `GET /auth/me` |
| Conversas | `GET /conversations` (page, per_page, status, filter=mine\|unassigned\|all, search, funnel_id, department_id), `GET /conversations/:id`, `POST :id/assign`, `POST :id/close`, `POST :id/reopen`, `POST :id/move-stage`, `PUT :id/department`, `POST/DELETE :id/tags`, `POST :id/mark-read`, `POST :id/mark-unread` |
| Mensagens | `GET /conversations/:id/messages` (limit, before_id, after_id), `POST /conversations/:id/messages` (multipart: content, attachments[], quoted_message_id, is_note='1') |
| Notas | `GET/POST /conversations/:id/notes` |
| Realtime | `POST /realtime/poll` (subscribed_conversations, last_update_time, activity_type) — o app faz polling a cada 5s |
| Notificações | `GET /notifications`, `GET /notifications/unread`, `POST /notifications/:id/read`, `POST /notifications/read-all` |
| Push | `POST /devices` (token, platform, device_name, app_version), `DELETE /devices/:token` |
| Mídia | `GET /attachments/sign?path=...` — o app resolve paths relativos de anexos para URLs assinadas (cache de 10 min) |
| Cloud (WhatsApp oficial) | `GET /conversations/:id/cloud-window`, `POST /conversations/:id/send-cloud-template`, `GET /templates?from=` |
| Auxiliares | `GET /agents`, `/departments`, `/funnels`, `/funnels/:id/stages`, `/tags`, `/whatsapp-accounts`, `/contacts?search=`, `POST /conversations/check-existing`, `POST /conversations/new` |

### Push notifications

O app registra o **Expo Push Token** via `POST /devices`. O backend deve enviar
push através da [Expo Push API](https://docs.expo.dev/push-notifications/sending-notifications/)
incluindo `data.conversation_id` para que o tap na notificação abra a conversa.

## Funcionalidades

- Login com refresh automático de token (single-flight) e sessão persistida em
  `expo-secure-store`.
- Lista de conversas: abas Minhas/Não atribuídas/Todas, busca com debounce,
  filtros (status, canal, setor, funil), barra de SLA, badge de não lidas,
  fixadas primeiro, pull-to-refresh e scroll infinito.
- Chat: bolhas por remetente (contato/agente/IA/nota interna/sistema),
  citação (long-press → responder), envio otimista com reenvio em caso de erro,
  status ✓/✓✓, imagens com visualização em tela cheia, player de áudio,
  documentos, gravação de áudio segurando o microfone (m4a), notas internas.
- Realtime por polling (5s) com merge nos caches do React Query; pausa em background.
- Janela de 24h do WhatsApp Cloud: banner bloqueando o composer + envio de template.
- Notificações in-app + push; contatos com criação de nova conversa; perfil com
  tema claro/escuro/sistema e sons.

## Pontos de atenção

- **`extra.eas.projectId`** em `app.json` está com placeholder
  (`REPLACE_WITH_EAS_PROJECT_ID`). Rode `eas init` para preenchê-lo.
- Bundle IDs: `com.privus.chat` (iOS e Android).
- O app não referencia assets binários (ícone/splash padrão do Expo). Adicione
  `icon`/`splash` em `app.json` antes de publicar nas lojas.
