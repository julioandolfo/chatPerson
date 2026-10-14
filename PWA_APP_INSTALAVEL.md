# App instalável (PWA) — Conversas no celular

Implementação das Fases 0 a 4 do `PLANO_PWA_CONVERSAS.md`.

## Colocar no ar

```bash
php database/run_migrations.php 160
```

Só isso. As chaves de notificação push (VAPID) são geradas sozinhas no primeiro uso e
guardadas em `settings` (grupo `pwa`). Para fixá-las por variável de ambiente (opcional):

```
VAPID_PUBLIC_KEY=...    # base64url, 65 bytes
VAPID_PRIVATE_KEY=...   # base64url, 32 bytes
VAPID_SUBJECT=mailto:voce@empresa.com.br
```

Requisitos: HTTPS (o Coolify já fornece) e a extensão `openssl` do PHP (já presente). Não há
dependência nova de Composer.

> Não troque as chaves VAPID depois que os agentes ativarem as notificações: as inscrições
> existentes deixam de funcionar e cada aparelho precisa ativar de novo.

## Para o agente

1. Abrir o sistema no celular → menu do usuário → **App e aparelhos** (ou a sugestão
   "Instalar app" que aparece embaixo da tela).
   - **Android/Chrome**: botão **Instalar app**.
   - **iPhone**: Safari → **Compartilhar → Adicionar à Tela de Início**.
2. Entrar marcando **Manter conectado** (no app instalado já vem marcado).
3. Em **App e aparelhos** → **Ativar notificações** → **Enviar teste**.
   - No iPhone só funciona com o app instalado e aberto pelo ícone (iOS 16.4+).
   - "Mostrar o texto da mensagem" pode ser desligado por aparelho (aparece só "Nova mensagem").

## O que mudou

| Área | Comportamento |
|---|---|
| Instalação | `manifest.webmanifest` dinâmico (nome e logo de Configurações), abre em `/conversations` |
| Cache (`public/sw.js`) | Só JS/CSS/ícones/sons. Páginas, dados, anexos e uploads nunca ficam no aparelho |
| Atualização | JS/CSS ganham `?v=<data do arquivo>`; o app avisa "Nova versão disponível — Atualizar" |
| Offline | Página "Sem conexão"; aviso vermelho no topo e envio bloqueado sem perder o texto |
| Login | "Manter conectado" por 30 dias por aparelho (token rotacionado a cada uso, revogável em **App e aparelhos**; trocar a senha desconecta todos; cookie antigo reaproveitado após 60 s = token revogado) |
| Pós-login | Volta para a página que estava sendo aberta (ex.: conversa da notificação) |
| Push | Toda notificação interna vira push (mensagem nova mostra nome do contato e texto). Mensagens da mesma conversa se agrupam; não notifica se a conversa já está aberta na tela; toque abre a conversa |
| Android | Notificação do navegador corrigida (`new Notification` dava erro); "voltar" do aparelho volta do chat para a lista; teclado não cobre a caixa de mensagem e não joga mais para a lista |
| iPhone | Áudio gravado (`audio/mp4`) agora vai como `.m4a` e é convertido para OGG/Opus; teclado não cobre a caixa de mensagem |
| Volta do 2º plano | Reconecta o tempo real e busca mensagens/lista/follow-up que chegaram |

## Arquivos

- `app/Helpers/WebPush.php` — criptografia RFC 8291 (aes128gcm) e JWT VAPID (ES256), só com OpenSSL
- `app/Services/PushService.php` — inscrições, chaves, conteúdo e envio em lote (curl_multi)
- `app/Services/RememberMeService.php` — "Manter conectado"
- `app/Controllers/PwaController.php` — manifest, ícone, endpoints de push e aparelhos
- `public/sw.js`, `public/offline.html`, `public/assets/js/pwa.js`
- `views/partials/pwa-head.php`, `views/partials/pwa-scripts.php`, `views/profile/app-tab.php`
- `database/migrations/160_create_pwa_tables.php` — `user_remember_tokens`, `push_subscriptions`

## Endpoints

- `GET /manifest.webmanifest`, `GET /pwa/icon/{192|512|180}` (públicos)
- `GET /pwa/push/public-key`, `POST /pwa/push/{status|subscribe|unsubscribe|preferences|test}`
- `GET /pwa/devices`, `POST /pwa/devices/{id}/revoke`

## Validação feita

- Criptografia e assinatura conferidas contra a implementação de referência (`http_ece`/`web-push`
  do Node): conteúdo descriptografado idêntico, 500/500 assinaturas válidas.
- Envio real para Google, Mozilla e Apple com inscrições falsas: Google/Mozilla responderam
  "inscrição inexistente" (removida automaticamente) e a Apple `BadWebPushToken` — com assinatura
  propositalmente errada a Apple responde `BadJwtToken`, ou seja, a autenticação é aceita.
- Chromium: app instalável sem erros de manifest, service worker ativo, cache só de estáticos,
  página offline, login com "Manter conectado" sobrevivendo à perda do cookie de sessão, volta à
  página pretendida, push exibido/agrupado, navegação "voltar" no celular.

**Não testado em aparelho físico** (iPhone/Android reais): instalação, recebimento de push com o
app fechado, teclado e gravação de áudio no iPhone. Faça o roteiro de testes de aceite do
`PLANO_PWA_CONVERSAS.md` (seção 3) antes de liberar para a equipe.

## Limitações

- iPhone: push só com app instalado e iOS 16.4+.
- Tempo real continua sendo polling de 3 s no container atual (o `websocket-server.php` não sobe no Dockerfile).
- Mensagens não ficam disponíveis offline (decisão de privacidade); sem fila de envio offline.
