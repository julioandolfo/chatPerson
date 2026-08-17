# Setup — Análise de Conversas por Coorte

Funcionalidade que seleciona conversas que **passaram** por determinadas etapas do funil
e/ou por determinados agentes/times, dentro de um período, e as analisa com IA sob um
contexto definido pelo gestor.

> Caso de uso alvo: _"analisar todas as conversas que passaram pelo time comercial nos
> últimos 30 dias e entender em qual momento estão desistindo da compra ou deixando de
> responder, e por quê"_.

Diagnóstico técnico que originou esta implementação: `ANALISE_ANALISE_CONVERSAS_POR_COORTE.md`.

---

## 1. Instalação

Execute na ordem:

```bash
# 1. Tabelas da análise (lotes + itens)
php database/run_migrations.php 157

# 2. Histórico de etapas: garante a tabela e adiciona colunas/índices
php database/run_migrations.php 158

# 3. Backfill do histórico de etapas (veja o aviso abaixo)
php database/scripts/backfill_funnel_stage_history.php --dry-run   # simula
php database/scripts/backfill_funnel_stage_history.php             # aplica

# 4. Permissões
php database/seeds/add_conversation_insights_permissions.php
```

### 2. Agendar o processamento

A análise roda em segundo plano. Agende **a cada minuto**:

```
# Linux
* * * * * php /var/www/html/public/scripts/process-conversation-analysis.php >> /var/log/conversation-analysis.log 2>&1

# Docker
* * * * * docker exec CONTAINER php /var/www/html/public/scripts/process-conversation-analysis.php
```

```
:: Windows (Agendador de Tarefas)
C:\laragon\bin\php\php-8.x\php.exe C:\laragon\www\chat\public\scripts\process-conversation-analysis.php
```

Cada execução analisa um pedaço do lote (padrão: 10 conversas) e é **retomável** —
travar no meio não perde o trabalho já pago à OpenAI.

### 3. Configuração (opcional)

Chave `conversation_insights_settings` na tabela `settings` (JSON):

```json
{
  "model_map": "gpt-4o-mini",
  "model_reduce": "gpt-4o",
  "cost_limit_per_batch": 25.00,
  "items_per_run": 10
}
```

A chave da OpenAI é a mesma do resto do sistema (`settings.openai_api_key`).

---

## ⚠️ Sobre o backfill do histórico

`funnel_stage_history` existia no schema mas **nunca era populada** — nenhum ponto do
código gravava nela. Ela era apenas lida (pelo painel de histórico da conversa e pela
segmentação de contatos), e por isso ambos apareciam vazios.

Esta entrega corrige a escrita em todos os pontos que movem etapa. O backfill
reconstrói o passado em três passadas:

| Passada | Fonte | Cobertura |
|---|---|---|
| 1 | `activities` (`activity_type = 'stage_moved'`) | movimentações feitas via `FunnelService::moveConversation` (kanban manual, automações) |
| 2 | estado atual das conversas sem histórico | 1 evento sintético por conversa |
| 3 | conversas cuja etapa atual diverge do último evento | reconcilia movimentações perdidas |

**O que não é recuperável:** movimentações feitas pelo `KanbanAgentService` (agentes de
IA do kanban) **antes** desta correção não existem em nenhum lugar do banco. Daí para a
frente o histórico fica íntegro.

A tela avisa o usuário quando o período pedido é mais antigo que o primeiro registro
do histórico.

---

## Como funciona

```
Filtros (UI)
    │
    ▼
ConversationCohortService ──► "quais conversas entram"
    │                          EXISTS em funnel_stage_history (etapas)
    │                          EXISTS em conversation_assignments OU messages (agentes)
    ▼
ConversationBatchAnalysisService
    │
    ├─ FASE 0  métricas determinísticas (SQL/PHP) ....... custo ZERO
    │          quem parou de responder, etapa onde travou, tempos
    │
    ├─ FASE 1  MAP: 1 chamada de LLM por conversa ....... modelo barato
    │          JSON com taxonomia FECHADA de motivo + citações
    │
    └─ FASE 2  REDUCE: 1 chamada sobre os agregados ..... modelo forte
               diagnóstico executivo
```

### Por que a taxonomia é fechada

`primary_reason` só aceita valores de uma lista fixa (`preco`, `falta_followup`,
`duvida_nao_respondida`, `concorrencia`, …). Motivo em texto livre não agrega — não dá
para dizer "24% das perdas foram por falta de follow-up" se cada conversa devolve uma
frase diferente. Valor fora da lista é normalizado para `outro` em vez de poluir os
números.

### Por que "quem parou de responder" não vem da IA

Esse número sai do SQL: se a última mensagem é do contato, o **vendedor** não respondeu;
se é do agente, o **cliente** sumiu. É um fato auditável, não uma inferência do modelo.
A IA entra só para responder o **porquê**.

Conversas com silêncio menor que 2 dias são marcadas como `ninguem` — ainda não houve
abandono.

---

## Uso

1. Menu **Performance → 🔎 Análise de Conversas**
2. **Nova análise**
3. Selecione etapas / times / agentes e o período
4. A prévia mostra **quantas conversas** entram e o **custo estimado** antes de rodar
5. Escreva o que quer entender (há presets prontos)
6. **Iniciar análise** — a página acompanha o progresso sozinha

Na tela de resultado, clicar em qualquer motivo, etapa ou vendedor filtra a lista de
conversas correspondente, com link direto para o chat.

### Semântica dos filtros

| Filtro | Significado |
|---|---|
| **Passou pela etapa** | Trajetória, não estado atual. A conversa entra mesmo que já tenha saído da etapa. |
| ↳ *dentro do período* | Marcado: a movimentação em si ocorreu no período. Desmarcado: passou em qualquer momento. |
| **Passou pelo agente** | Por padrão: esteve **atribuída** a ele **ou** ele **respondeu** na conversa. |
| ↳ *só atribuição* | Ignora quem atendeu sem estar atribuído. |
| ↳ *só quem respondeu* | Ignora quem recebeu a conversa e nunca respondeu. |
| **Time** | Expandido para os membros em `team_members`. |
| **Período** | Por padrão considera conversas com mensagens no intervalo. |

A dupla fonte no filtro de agente é proposital: a atribuição sozinha perde quem atendeu
sem estar atribuído, e a mensagem sozinha perde justamente o caso que interessa — o
vendedor que recebeu a conversa e nunca respondeu.

---

## Controle de custo

- **Prévia obrigatória** antes de rodar, com custo estimado
- **Teto por lote** (padrão US$ 25) — a análise para ao atingir
- **Truncamento**: conversas longas enviam as 15 primeiras + 40 últimas mensagens
  (o abandono está no fim)
- **Modelo barato no MAP**, forte só no REDUCE
- **Idempotência**: `UNIQUE (batch_id, conversation_id)` — reprocessar não recobra

## Privacidade

As transcrições passam por `ManualGeneratorService::anonymize()` antes de irem para a
OpenAI — mesma anonimização já usada pelo Copiloto (mascara nome do contato, e-mail,
CPF, CNPJ e telefone).

## Permissões

| Slug | O que libera |
|---|---|
| `conversation_insights.view` | Ver análises e fazer prévia |
| `conversation_insights.run` | Criar e cancelar análises (consome créditos de IA) |

O escopo de permissão de funil (`AgentFunnelPermission`) é reaplicado na montagem da
coorte: um usuário não vê, via relatório, conversa que não veria na lista.
