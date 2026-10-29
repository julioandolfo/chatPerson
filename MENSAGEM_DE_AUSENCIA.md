# Mensagem de ausência do agente

O agente agenda quando vai estar fora e a mensagem automática que o cliente recebe.
Exemplo: ausente dia 25 das 07:00 às 11:00 → o cliente que escrever recebe, 10 segundos
depois, "Olá, Maria! No momento estou ausente e retorno hoje às 11:00…" — **uma vez só**.

## Instalação

As tabelas são criadas **automaticamente no deploy** (`database/auto_migrate.php`). Fora do Docker:

```bash
php database/run_migrations.php 163
php database/run_migrations.php 164   # ausências rápidas
```

## Ausências rápidas (almoço, pausa)

Para sair na hora, sem agendar: botão **☕** no topo da tela → **Almoço** / **Pausa** / ...
A ausência começa agora e termina depois da duração do modelo. Enquanto durar:

- o topo mostra **🍽️ Almoço até 13:05** (em qualquer página e no app do celular);
- clientes que escreverem recebem a mensagem automática do modelo (mesmas regras abaixo);
- o status fica **Ausente** (se o modelo estiver assim) e não volta para Online sozinho quando
  o agente mexe no sistema — conforme Configurações → Disponibilidade, quem está Ausente não
  recebe novas conversas;
- **Voltei** (no topo ou na aba Ausência) encerra na hora e volta o status para Online.
  Se ninguém clicar, a ausência termina no horário e o status volta para Online com a próxima
  atividade do agente.

Modelos em Perfil → **Ausência** → **Ausências rápidas**: ícone, nome, duração (1 min a 12 h),
mensagem, tempo para responder o cliente e se muda o status para Ausente.

- Já vêm criados **🍽️ Almoço (1 h)** e **☕ Pausa (15 min)** para toda a equipe (edite à vontade).
- Administradores (ou quem tem `settings.manage`) criam modelos **para todos os agentes**;
  cada agente pode criar os seus.
- Não dá para iniciar uma ausência rápida estando já ausente; se houver ausência agendada antes
  do fim, a rápida termina quando a agendada começar.

## Onde configurar

Menu do usuário (canto superior direito) → **Mensagem de ausência** (ou Perfil → aba **Ausência**).
Quando há uma ausência valendo, o item do menu mostra o selo **Ativa**.

| Campo | O que faz |
|---|---|
| Ausente de / Até (retorno) | Período (data e hora). Atalhos: começar agora, agora por 1 h / 4 h, amanhã o dia todo |
| Mensagem | Texto enviado ao cliente, com prévia ao vivo |
| Enviar depois da mensagem do cliente | 0 s a 1 h (padrão 10 s) |
| Se o mesmo cliente escrever de novo | Não enviar de novo nesta ausência (padrão) ou reenviar após 12 h / 24 h / 48 h / 7 dias — útil em férias |
| Ativa | Pausar sem excluir (ao editar) |

Variáveis: `{{contact.name}}`, `{{agent.name}}`, `{{absence.return}}` ("hoje às 11:00",
"amanhã às 11:00" ou "em 25/10 às 11:00"), `{{absence.return_time}}` ("11:00"),
`{{absence.return_date}}` ("25/10/2026").

Na lista: situação (Agendada / Ativa agora / Encerrada), **Enviadas** (para quem foi e por que
alguma não saiu), **Editar**, **Encerrar agora** (voltou antes) e **Excluir**. Não é permitido
cadastrar duas ausências no mesmo horário.

## Regras

- Vale para conversas **atribuídas ao agente**, em todos os canais (WhatsApp, WhatsApp Cloud,
  Notificame, Instagram…).
- **Uma vez por conversa** no período: as próximas mensagens do cliente não disparam de novo
  (a não ser que o reenvio após X horas esteja configurado).
- No momento do envio confere tudo de novo e **não envia** se:
  - alguém respondeu a conversa durante a espera (e aí não envia mais nessa conversa no período);
  - a conversa foi transferida para outro agente, encerrada ou um agente de IA está atendendo;
  - a ausência terminou, foi encerrada, desativada ou excluída.
- Conversas com agente de IA ativo e conversas sem agente são ignoradas.
- A mensagem sai como mensagem do sistema: não reatribui a conversa, não desliga a IA, não liga
  o follow-up por etapa e **não conta como primeira resposta** (SLA intacto).
- **Não marca a conversa como lida**: as mensagens do cliente continuam como não lidas (contador
  da lista) até o agente abrir a conversa. Nenhum "visto" é enviado ao cliente.
- **Não conta como resposta**: a conversa continua com o selo **Aguardando** na lista (também em
  tempo real), no filtro **Não respondidas** (e fora de **Respondidas**) e no alerta de cliente
  sem resposta por etapa, até um agente responder de verdade.

## Como o envio acontece

1. A mensagem do cliente chega → grava um envio pendente (`agent_absence_replies`) e dispara um
   processo PHP em segundo plano (`scripts/absence-reply-worker.php`) que espera o tempo
   configurado e envia. O recebimento não fica mais lento.
2. Se o servidor não permitir `exec`, se a espera for maior que 10 minutos ou se o envio falhar
   (nova tentativa em 2 min, até 3 vezes), quem envia é o cron (`AgentAbsenceJob` em
   `public/run-scheduled-jobs.php`) — nesse caso a precisão é a do intervalo do cron.

## Arquivos

- `database/migrations/163_create_agent_absences.php` — `agent_absences`, `agent_absence_replies`
- `database/migrations/164_create_agent_absence_presets.php` — `agent_absence_presets` (ausências rápidas)
- `views/partials/quick-absence.php` — botão ☕ / "Voltei" no topo
- `app/Services/AgentAbsenceService.php` — regras, agendamento e envio
- `app/Controllers/AgentAbsenceController.php` — endpoints
- `scripts/absence-reply-worker.php` — envio em segundo plano
- `app/Jobs/AgentAbsenceJob.php` — envio pelo cron (reserva)
- `views/profile/absence-tab.php` — aba Ausência; item no menu em `views/layouts/metronic/header.php`
- Gancho no recebimento: `ConversationService`, `WhatsAppCloudService`, `NotificameService`,
  `InstagramGraphService` (junto da notificação de mensagem nova)

Endpoints (sempre do usuário logado):

- `GET/POST /profile/absences`
- `POST /profile/absences/{id}` (editar), `/profile/absences/{id}/end`, `/profile/absences/{id}/delete`
- `GET /profile/absences/{id}/replies`
- `GET /profile/absences/quick` (estado do botão do topo)
- `POST /profile/absence-presets`, `/profile/absence-presets/{id}`, `/{id}/delete`, `/{id}/start`
