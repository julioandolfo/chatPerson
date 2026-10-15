# Follow-up automático por etapa do funil ("vamos continuar?")

Cada etapa do funil pode ter uma sequência de mensagens enviadas automaticamente quando
**a última mensagem da conversa é nossa** e o cliente não responde.

Exemplo (etapa "Proposta"):

| Após (da sua última mensagem) | Mensagem |
|---|---|
| 2 horas | Vamos continuar, {{contact.name}}? |
| 6 horas | Ainda está por aí? |
| 1 dia   | Vou deixar a proposta reservada até amanhã 😉 |

## Instalação

As tabelas são criadas **automaticamente no deploy** (`docker/entrypoint.sh` →
`database/auto_migrate.php`, registro em `schema_migrations`). Fora do Docker, rode:

```bash
php database/run_migrations.php 159
php database/run_migrations.php 161   # limites de envio (anti-spam)
```

O envio é feito pelo cron principal (`public/run-scheduled-jobs.php`, job `StageFollowupJob`),
que já precisa estar agendado. A precisão do envio é a do intervalo do cron (1 a 5 min).

## Configurar

Funis → Kanban → editar etapa → aba **Follow-up**:

- **Ativar follow-up automático nesta etapa**
- **Enviar apenas em horário de atendimento**: se cair fora do expediente (Configurações de
  horário de trabalho/feriados), adia para a próxima abertura.
- **Ativar somente após mensagens de agentes humanos**: se desmarcado, mensagens de IA e
  automações também ligam a sequência.
- Quantas mensagens quiser (até 20). Os tempos são contados **a partir da sua última
  mensagem** e precisam ser crescentes.
- Variáveis: `{{contact.name}}`, `{{contact.phone}}`, `{{contact.email}}`, `{{agent.name}}`,
  `{{date}}`, `{{time}}`, `{{datetime}}`.

## Limites de envio (anti-spam)

Na mesma aba **Follow-up**, seção **Limites de envio** — valem para **todas as etapas** e são
contados **por número de WhatsApp/conta** de envio:

| Campo | Padrão | O que faz |
|---|---|---|
| Intervalo entre mensagens | 15 a 45 s (aleatório) | Espera entre dois follow-ups do mesmo número |
| Máximo por hora | 60 | Por número, janela móvel de 60 min |
| Máximo por dia | 300 | Por número, janela móvel de 24 h |

- O que passar do limite **não se perde**: continua na fila e sai nas próximas execuções do cron,
  na ordem em que venceu.
- Números diferentes têm cada um a sua vez (um número cheio não segura os outros).
- Duas execuções do cron ao mesmo tempo dividem a vez do número (não dobram a velocidade).
- Cada execução do cron envia por no máximo ~20 s. Com o cron a cada 1 minuto e intervalo de
  15–45 s, sai cerca de 1 mensagem por minuto por número; com o cron a cada 5 minutos, bem menos.
  Para filas grandes, agende `public/run-scheduled-jobs.php` a cada 1 minuto.

## Como funciona

- **Liga sozinho**: toda mensagem nossa enviada pelo sistema (painel, IA, automações, mensagens
  agendadas, API) arma a sequência do zero a partir dela. Notas internas não contam.
- **Para sozinho** quando: o cliente responde, a conversa é encerrada, a etapa é desativada ou
  todas as mensagens foram enviadas.
- **Mudou de etapa**: passa a usar a sequência da nova etapa, contando a partir da mudança.
  Se a nova etapa não tiver follow-up, para.
- **No chat**: acima da caixa de mensagem aparece "Follow-up automático ativo — mensagem X/Y em
  1h 58m", com **Ver sequência** e **Cancelar follow-up**. O cancelamento vale só para aquela
  conversa e até a próxima mensagem enviada (que liga a sequência de novo). Depois de cancelar
  dá para **Reativar**.
- As mensagens do follow-up saem como mensagem do sistema (não reatribuem a conversa nem
  desligam a IA) e **não** reiniciam a própria sequência.
- Falha de envio: tenta de novo em 5 min; após 3 falhas seguidas a sequência para (motivo
  registrado em `conversation_followups.stop_reason`).
- Mensagens enviadas direto pelo celular (fora do sistema) não ligam a sequência.

## Tabelas

- `funnel_stage_followups` — configuração por etapa
- `conversation_followups` — estado por conversa (`active`, `cancelled`, `replied`, `completed`, `stopped`)
- `conversation_followup_messages` — histórico de cada envio/falha

## Arquivos

- `app/Services/StageFollowupService.php` — regra de negócio
- `app/Jobs/StageFollowupJob.php` — envio dos passos vencidos (cron)
- `app/Controllers/StageFollowupController.php` — endpoints
- `app/Services/ConversationService.php` — gancho em `sendMessage` que arma a sequência
- `views/funnels/kanban.php` + `public/assets/js/kanban.js` — aba Follow-up da etapa
- `views/conversations/index.php` — aviso no chat

Endpoints:

- `GET/POST /funnel-stages/{stageId}/followup`
- `GET /conversations/{id}/stage-followup`
- `POST /conversations/{id}/stage-followup/cancel`
- `POST /conversations/{id}/stage-followup/reactivate`
