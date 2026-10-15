# Alerta de cliente sem resposta por etapa do funil

Avisa o agente, **na tela e com som**, quando uma conversa de uma etapa fica com o cliente
esperando resposta por mais tempo do que o configurado.

## Instalação

A tabela é criada **automaticamente no deploy** (`database/auto_migrate.php`). Fora do Docker:

```bash
php database/run_migrations.php 162
```

Não depende do cron: quem verifica é o navegador do agente (a cada 1 minuto).

## Configurar

Funis → Kanban → editar etapa → aba **Alerta**:

| Campo | O que faz |
|---|---|
| Ativar alerta nesta etapa | Liga/desliga |
| Alertar após o cliente esperar | X minutos / horas / dias (até 30 dias) |
| Som | Um dos sons do sistema, ou "Sem som" (só visual). Botão **▶ Ouvir** para testar |
| Repetir o som | Não repetir, ou a cada 1, 2, 5, 10, 15, 30, 60 min enquanto o cliente esperar |
| Alertar também conversas sem agente | Vão para todos os agentes com acesso à conversa |
| Contar só o horário de atendimento | Usa Configurações → horário de trabalho/feriados: fora do expediente o tempo não corre e não há alerta |
| Alertar também (supervisores) | Usuários que recebem o alerta de **todas** as conversas da etapa |

## Regras

- **"Sem resposta"**: a última mensagem da conversa é do cliente. Notas internas e mensagens de
  sistema não contam como resposta; mensagens da IA e de automações contam.
- O tempo conta da **primeira** mensagem do cliente ainda sem resposta. Se a conversa entrou na
  etapa depois disso, conta da entrada na etapa.
- Só conversas abertas/pendentes (não encerradas, não spam, não mescladas).
- Quem recebe: o agente atribuído; as sem agente, se marcado; e os supervisores escolhidos.
- O alerta some sozinho quando alguém responde, a conversa é encerrada ou muda de etapa.

## Na tela do agente

- Painel vermelho fixo em **qualquer página** do sistema (no celular, logo abaixo do cabeçalho,
  minimizado): contato, etapa, há quanto tempo espera e a última mensagem.
- Por conversa: **Abrir**, adiar **15 min** / **1 h**, **✕** dispensar (até a próxima resposta).
- 🔔/🔕 silencia o som neste navegador. Clicar no título minimiza/expande.
- A conversa fica destacada (borda vermelha pulsando) na lista de conversas e no Kanban.
- Com a aba em segundo plano: notificação do navegador (se permitida) e o título da aba pisca.
- Várias abas abertas tocam o som uma vez só. Não toca para a conversa que já está aberta na tela.
- O volume segue Configurações → Sons.

## Arquivos

- `database/migrations/162_create_funnel_stage_alerts.php` — tabela `funnel_stage_alerts`
- `app/Services/StageAlertService.php` — configuração e cálculo dos alertas
- `app/Controllers/StageAlertController.php` — endpoints
- `views/funnels/kanban.php` + `public/assets/js/kanban.js` — aba Alerta da etapa
- `views/partials/stage-alerts.php` + `public/assets/js/stage-alerts.js` — painel, som e destaque
  (incluído em `views/layouts/metronic/app.php`)

Endpoints:

- `GET/POST /funnel-stages/{stageId}/alert`
- `GET /stage-alerts` — alertas do usuário logado
