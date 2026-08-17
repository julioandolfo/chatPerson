# Análise do Sistema de Conversas + Proposta: "Análise de Conversas por Coorte"

> Objetivo do estudo: viabilizar a funcionalidade de **selecionar conversas que passaram por etapa(s) X e/ou agente(s) Y, num período de N dias, e analisá-las com IA sob um contexto Z**.
> Exemplo alvo: _"analisar todas as conversas que passaram pelo time comercial nos últimos 30 dias e entender em que momento o cliente desiste da compra ou para de responder, e por quê"_.

---

## 1. Como o sistema de conversas funciona hoje

### 1.1 Núcleo de dados

| Tabela | Papel | Campos relevantes |
|---|---|---|
| `conversations` | Entidade central. Guarda **apenas o estado atual** | `contact_id`, `agent_id`, `department_id`, `channel`, `status`, `funnel_id`, `funnel_stage_id`, `integration_account_id`, `priority`, `created_at`, `moved_at`, `assigned_at`, `resolved_at`, `first_response_at`, `first_human_response_at`, `is_spam` |
| `messages` | Todas as mensagens | `conversation_id`, `sender_type`, `sender_id`, `ai_agent_id`, `content`, `message_type`, `created_at`, `read_at` |
| `contacts` | Cliente | `name`, `phone`, `email`, `primary_agent_id` |
| `funnels` / `funnel_stages` | Kanban | `funnel_stages.position` / `stage_order`, `is_system_stage` (Entrada, Fechadas/Resolvidas, Perdidas), `sla_hours` |
| `users` | Agentes **humanos** | — |
| `ai_agents` | Agentes de **IA** conversacionais | — |
| `ai_kanban_agents` | Agentes de IA que agem sobre o kanban (movem/atribuem/mandam follow-up) | — |
| `teams` / `team_members` | Times (ex.: "Comercial") → `team_members.user_id` | — |
| `departments` | Setores (`conversations.department_id`) | — |

Convenções importantes descobertas no código:

- `messages.sender_type` ∈ `contact` \| `agent` \| `system`.
- **Mensagem de IA = `sender_type = 'agent'` com `ai_agent_id NOT NULL`.** Mensagem de humano = `sender_type = 'agent'` **e** `ai_agent_id IS NULL` **e** `sender_id > 0` (esse é exatamente o critério usado nos filtros "respondido"/"sem resposta" em `app/Models/Conversation.php:303-345`).
- `conversations.status` ∈ `open`, `closed`, `resolved`, `pending` (varia por fluxo). `CopilotService` trata `resolved` + `closed` como encerradas.

### 1.2 Histórico (o que é versionado ao longo do tempo)

| Tabela | O que registra | Quem grava | Cobertura real |
|---|---|---|---|
| `conversation_assignments` | Atribuições de agente humano (`agent_id`, `assigned_by`, `assigned_at`, `removed_at`) | `ConversationService::assignToAgent()` (`app/Services/ConversationService.php:1129`), criação da conversa (`:705`), `AgentController.php:249` | **Boa, mas com furo** (ver §2.2) |
| `conversation_participants` | Participantes adicionais (multi-atendimento) | `ConversationParticipant` | OK |
| `activities` | Auditoria genérica (`activity_type`, `entity_type`, `entity_id`, `metadata` JSON) — inclui `stage_moved` com `old_stage_id`/`new_stage_id` | `ActivityService::logStageMoved()` chamado por `FunnelService::moveConversation()` (`app/Services/FunnelService.php:398`) | **Parcial** (ver §2.1) |
| `funnel_stage_history` | Transições de etapa (`from_stage_id`, `to_stage_id`, `changed_by`) | **NINGUÉM** | ❌ **Tabela morta** |
| `ai_conversations` | Sessão de IA na conversa (`ai_agent_id`, `status`, tokens, custo, `escalated_to_user_id`) | `ConversationAIService` | OK |
| `ai_kanban_agent_executions` / `ai_kanban_agent_actions_log` | Execuções dos agentes de kanban | `KanbanAgentService` | OK |
| `conversation_sentiments`, `agent_performance_analysis`, `coaching_*` | Análises de IA já existentes, por conversa | Serviços dedicados | OK |

### 1.3 Fluxo de leitura das conversas

`Conversation::getAll()` (`app/Models/Conversation.php:90-644`) já é o "motor de filtros" do sistema e **já suporta multi-select** em: canal, agente, tag, funil, **etapa**, conta de integração, participante, período (`date_from`/`date_to`), busca textual, respondido/sem resposta — além de aplicar as permissões de funil (`AgentFunnelPermission`) e o bypass de admin.

⚠️ Mas todos esses filtros de funil/etapa/agente são sobre o **estado atual** (`c.funnel_stage_id = ?`, `c.agent_id = ?`). Isso responde "está na etapa X", **não** "passou pela etapa X".

### 1.4 Infraestrutura de IA já disponível para reuso

- `OpenAIService::generateText()` — chamada genérica ao LLM (`app/Services/OpenAIService.php:4119`).
- `AgentPerformanceAnalysisService` — **o molde mais próximo do que queremos**: pega conversa → monta transcrição → prompt → `response_format: json_object` → parseia → salva → calcula custo (`app/Services/AgentPerformanceAnalysisService.php:153-710`). Inclui limite de custo diário (`checkDailyCostLimit`) e filtros configuráveis.
- `AICostControlService`, `AIUsageLogger` — controle de gasto.
- `ContactSegmentationService::buildPassedThrough()` (`app/Services/ContactSegmentationService.php:175-265`) — **já existe a semântica "passou por etapa" com ANY OF / ALL OF**, porém apoiada em `funnel_stage_history`, que está vazia. É a interface certa; falta a fonte de dados.
- `CopilotService` — RAG sobre conversas encerradas (resumo + embedding + anonimização).

---

## 2. Lacunas que precisam ser fechadas ANTES da funcionalidade

### 2.1 🔴 CRÍTICO — `funnel_stage_history` nunca é populada

A tabela é criada em `database/migrations/091_create_conversation_details_tables.php:22` e **lida** em dois lugares:

- `app/Services/FunnelService.php:1411` — painel "histórico de etapas" da conversa (hoje **sempre vazio**);
- `app/Services/ContactSegmentationService.php:208,251` — regra "passou pela etapa" da segmentação (hoje **sempre retorna zero contatos**).

Não existe nenhum `INSERT INTO funnel_stage_history` no código. Confirmado por varredura em todo o repositório.

### 2.2 🔴 CRÍTICO — mudanças de etapa e de agente fora do caminho oficial

Existem escritas diretas em `conversations` que **não geram histórico algum**:

| Local | O que faz | Registra histórico? |
|---|---|---|
| `FunnelService::moveConversation()` `:364` | move etapa | ✅ grava `activities.stage_moved` |
| `KanbanAgentService::actionMoveToStage()` `:1936-1941` | move etapa (agente de IA do kanban) | ❌ nada |
| `KanbanAgentService::actionMoveToNextStage()` `:1978-1981` | move etapa | ❌ nada |
| `KanbanAgentService::actionAssignToAgent()` `:2010-2013` | troca `agent_id` direto | ❌ não grava em `conversation_assignments` |
| `ConversationService.php:1310` | `agent_id = null` (desatribuir) | ❌ não fecha `removed_at` |
| `ConversationService::create()` `:687` | etapa inicial | ❌ não gera evento de entrada |
| `ApiMessageController.php:160` | etapa inicial via conta de integração | ❌ idem |

Ou seja: **quanto mais a IA do kanban atua, mais o histórico se perde** — justamente nas conversas que mais interessa analisar.

### 2.3 🟡 `activities` é hoje a única fonte parcial de trajetória de etapas

`activities` tem os índices certos (`idx_entity_activity (entity_type, entity_id, created_at)`) e o `metadata` JSON com `old_stage_id`/`new_stage_id`. Serve para **backfill**, mas não como fonte primária (é tabela genérica de auditoria, cresce muito, e `JSON_EXTRACT` em filtro é caro).

---

## 3. Correções de base (pré-requisito da funcionalidade)

**P0.1 — Centralizar a transição de etapa.**
Criar `FunnelService::recordStageTransition($conversationId, $fromStageId, $toStageId, $changedBy, $source)` que faz `INSERT` em `funnel_stage_history` (+ `activities`), e chamá-la em **todos** os pontos da tabela §2.2. Ideal: fazer `KanbanAgentService` e `OpenAIService` passarem a usar `FunnelService::moveConversation(..., bypassPermissions: true)` em vez de `Conversation::update()` direto.

**P0.2 — Enriquecer `funnel_stage_history`.**
```sql
ALTER TABLE funnel_stage_history
  ADD COLUMN funnel_id INT NULL AFTER conversation_id,
  ADD COLUMN changed_by_ai_agent_id INT NULL,
  ADD COLUMN source VARCHAR(30) NULL COMMENT 'manual|automation|kanban_agent|ai_tool|api|system',
  ADD INDEX idx_stage_date (to_stage_id, created_at),
  ADD INDEX idx_conv_date (conversation_id, created_at);
```

**P0.3 — Backfill histórico** (script em `database/scripts/`):
1. `activities` com `activity_type='stage_moved'` → linhas de `funnel_stage_history` (usa `metadata->>'$.old_stage_id'` e `'$.new_stage_id'`);
2. para conversas sem nenhum evento, gerar 1 linha sintética com o estado atual (`to_stage_id = funnel_stage_id`, `created_at = COALESCE(moved_at, created_at)`, `source='backfill'`).

Sem isso, "passou pela etapa X" nos últimos 30 dias só enxerga o presente.

**P0.4 — Fechar `removed_at`** em `conversation_assignments` ao desatribuir/reatribuir, e gravar assignment no `KanbanAgentService`.

---

## 4. Arquitetura proposta da funcionalidade

Nome sugerido: **Análise de Coortes de Conversas** (`/conversation-insights`).

```
                     ┌──────────────────────────────────────────┐
   Filtros (UI)  ──▶ │ ConversationCohortService                │  "quem entra na análise"
                     │  ↳ monta SQL da coorte (passou por…)     │
                     └───────────────┬──────────────────────────┘
                                     │ IDs das conversas
                     ┌───────────────▼──────────────────────────┐
                     │ ConversationBatchAnalysisService         │
                     │  FASE 0  métricas determinísticas (SQL)  │  ← sem custo de IA
                     │  FASE 1  MAP: 1 conversa → 1 JSON (LLM)  │  ← modelo barato
                     │  FASE 2  REDUCE: agrega + sintetiza (LLM)│  ← modelo forte
                     └───────────────┬──────────────────────────┘
                                     │
                     ┌───────────────▼──────────────────────────┐
                     │ conversation_analysis_batches / _items    │
                     └──────────────────────────────────────────┘
```

### 4.1 Camada 1 — Seleção da coorte (`ConversationCohortService`)

Recebe um objeto de filtros e devolve SQL + params (mesmo estilo de `ContactSegmentationService`):

```php
[
  'date_from' => '2026-07-18', 'date_to' => '2026-08-17',
  'date_basis' => 'activity',          // created | activity (última msg) | stage_event
  'passed_stages'    => ['ids' => [12, 15], 'match' => 'any'],   // any | all
  'passed_agents'    => ['ids' => [7, 9],   'match' => 'any'],
  'passed_ai_agents' => ['ids' => [3]],
  'team_ids'         => [2],            // "time comercial" → expande p/ team_members
  'department_ids'   => [],
  'funnel_ids'       => [1],
  'channels'         => ['whatsapp'],
  'tag_ids'          => [],
  'status'           => ['open','closed','resolved'],
  'exclude_spam'     => true,
  'min_messages'     => 4,
  'limit'            => 500,
]
```

**Predicados (cada um vira um `EXISTS` combinável com AND):**

```sql
-- passou pela ETAPA (após P0.1/P0.3)
EXISTS (SELECT 1 FROM funnel_stage_history h
        WHERE h.conversation_id = c.id
          AND h.to_stage_id IN (:stage_ids)
          AND h.created_at BETWEEN :from AND :to)

-- passou pelo AGENTE HUMANO — dupla fonte (atribuição OU fala real)
EXISTS (SELECT 1 FROM conversation_assignments ca
        WHERE ca.conversation_id = c.id AND ca.agent_id IN (:user_ids)
          AND ca.assigned_at <= :to AND (ca.removed_at IS NULL OR ca.removed_at >= :from))
OR EXISTS (SELECT 1 FROM messages m
        WHERE m.conversation_id = c.id AND m.sender_type = 'agent'
          AND m.ai_agent_id IS NULL AND m.sender_id IN (:user_ids)
          AND m.created_at BETWEEN :from AND :to)

-- passou pelo AGENTE DE IA
EXISTS (SELECT 1 FROM messages m
        WHERE m.conversation_id = c.id AND m.ai_agent_id IN (:ai_ids)
          AND m.created_at BETWEEN :from AND :to)

-- TIME (ex.: comercial) = expande team_members.user_id e reusa o predicado de agente
```

> A "dupla fonte" (atribuição **ou** mensagem) é o que torna a feature confiável mesmo com o histórico de atribuição imperfeito descrito em §2.2.

### 4.2 Camada 2 — Métricas determinísticas (sem IA)

Calculadas em SQL/PHP para **toda** a coorte, antes de qualquer chamada de LLM. Elas já respondem metade da pergunta "em que momento pararam de responder" com custo zero:

- `last_speaker` — quem falou por último (`contact` / `human_agent` / `ai_agent`);
- `silence_days` — dias desde a última mensagem;
- `dropped_after` — se `last_speaker = 'contact'`: **o vendedor não respondeu**; se `last_speaker = 'agent'`: **o cliente sumiu**;
- `stage_at_drop` — etapa vigente na data da última mensagem (via `funnel_stage_history`);
- `messages_total`, `messages_contact`, `messages_human`, `messages_ai`;
- `first_response_seconds`, `avg_agent_response_seconds`, `max_gap_seconds`;
- `stage_path` — trilha completa de etapas com tempo em cada uma;
- `handoffs` — nº de trocas de agente.

### 4.3 Camada 3 — MAP: análise por conversa (LLM barato)

Transcrição montada no formato já usado em `AgentPerformanceAnalysisService::formatMessagesForAnalysis()` (com marcadores `[... N minutos depois ...]`, que é justamente o sinal de abandono). Prompt com o **contexto Z do usuário** + saída JSON forçada:

```json
{
  "outcome": "ganho|perdido|em_andamento|abandonado_cliente|abandonado_vendedor|sem_interesse",
  "drop_off_moment": {
    "stage_name": "Proposta enviada",
    "message_excerpt": "vou ver com meu sócio e te falo",
    "who_stopped": "cliente|vendedor|ia",
    "turn_index": 14
  },
  "primary_reason": "preco|prazo|concorrencia|falta_followup|duvida_nao_respondida|sem_budget|momento_errado|atrito_atendimento|produto_inadequado|sumiu_sem_sinal|outro",
  "reason_explanation": "…",
  "objections": ["preço acima do orçamento"],
  "buying_signals": ["pediu link de pagamento"],
  "agent_mistakes": ["não fez follow-up após 3 dias de silêncio"],
  "recoverable": true,
  "recovery_action": "…",
  "confidence": 0.82,
  "evidence_quotes": ["…"]
}
```

**A taxonomia fechada de `primary_reason` é o que permite agregar.** Texto livre não agrega — só serve como evidência.

### 4.4 Camada 4 — REDUCE: síntese executiva (LLM forte)

Recebe **os números agregados** (contagens por `primary_reason` × etapa × vendedor, medianas de tempo, taxa de last_speaker) + uma amostra estratificada de citações — nunca as transcrições inteiras. Devolve:

- diagnóstico dos 3-5 pontos de vazamento, em ordem de impacto;
- "etapa X concentra 42% das perdas; em 68% delas quem parou foi o vendedor após a proposta";
- recomendações acionáveis por etapa e por vendedor;
- comparativo com o período anterior (mesmo filtro, janela anterior).

### 4.5 Persistência

```sql
CREATE TABLE conversation_analysis_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NULL,
  context_question TEXT NOT NULL,          -- o "contexto Y" pedido pelo usuário
  filters JSON NOT NULL,
  date_from DATE NULL, date_to DATE NULL,
  status VARCHAR(20) DEFAULT 'pending',    -- pending|running|completed|failed|cancelled
  total_conversations INT DEFAULT 0,
  analyzed_conversations INT DEFAULT 0,
  failed_conversations INT DEFAULT 0,
  metrics JSON NULL,                       -- agregados determinísticos
  summary JSON NULL,                       -- saída do REDUCE
  model_map VARCHAR(50), model_reduce VARCHAR(50),
  tokens_used INT DEFAULT 0, cost DECIMAL(10,4) DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  INDEX idx_status_created (status, created_at)
);

CREATE TABLE conversation_analysis_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batch_id INT NOT NULL,
  conversation_id INT NOT NULL,
  metrics JSON NULL,                       -- fase 0
  analysis JSON NULL,                      -- fase 1
  outcome VARCHAR(40) NULL,                -- desnormalizado p/ agregação rápida
  primary_reason VARCHAR(40) NULL,
  drop_off_stage_id INT NULL,
  who_stopped VARCHAR(20) NULL,
  agent_id INT NULL,
  confidence DECIMAL(3,2) NULL,
  tokens_used INT DEFAULT 0, cost DECIMAL(10,6) DEFAULT 0,
  error TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_batch_conv (batch_id, conversation_id),
  INDEX idx_batch_reason (batch_id, primary_reason),
  INDEX idx_batch_stage (batch_id, drop_off_stage_id),
  FOREIGN KEY (batch_id) REFERENCES conversation_analysis_batches(id) ON DELETE CASCADE,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
);
```

### 4.6 Execução assíncrona

500 conversas × ~2s por chamada = análise longa demais para request HTTP. Padrão do projeto (`cron/`, `app/Jobs/`, `AutomationSchedulerService`):

1. `POST /conversation-insights` cria o batch com `status='pending'` e devolve o ID na hora;
2. `cron/process-conversation-analysis.php` (a cada minuto) processa em lotes de N conversas, com retomada — cada item já gravado é pulado (idempotente pela UNIQUE KEY);
3. a tela faz polling de `GET /conversation-insights/{id}/status` (mesmo padrão do polling já usado em conversas);
4. ao terminar, roda o REDUCE e notifica via `NotificationService`.

### 4.7 Controle de custo (obrigatório)

- Estimativa **antes** de rodar: nº de conversas × tokens médios → custo previsto, exibido para confirmação.
- Teto por batch e reuso do `checkDailyCostLimit()` de `AgentPerformanceAnalysisService`.
- Truncamento inteligente: em conversas muito longas, enviar primeiras 15 + últimas 40 mensagens (o abandono está no fim).
- Cache: `conversation_analysis_items` chaveado por conversa + hash do prompt/contexto → re-rodar o mesmo batch não recobra.
- Modelo do MAP configurável (default barato), do REDUCE separado (default forte).

### 4.8 UI, rotas e permissões

```php
Router::get ('/conversation-insights',                [ConversationInsightController::class,'index'],   ['Authentication','Permission:conversation_insights.view']);
Router::post('/conversation-insights',                [ConversationInsightController::class,'create'],  ['Authentication','Permission:conversation_insights.run']);
Router::post('/conversation-insights/preview',        [ConversationInsightController::class,'preview'], ['Authentication','Permission:conversation_insights.view']); // conta a coorte + estima custo
Router::get ('/conversation-insights/{id}',           [ConversationInsightController::class,'show'],    ['Authentication','Permission:conversation_insights.view']);
Router::get ('/conversation-insights/{id}/status',    [ConversationInsightController::class,'status'],  ['Authentication']);
Router::get ('/conversation-insights/{id}/export',    [ConversationInsightController::class,'export'],  ['Authentication','Permission:conversation_insights.view']);
Router::post('/conversation-insights/{id}/cancel',    [ConversationInsightController::class,'cancel'],  ['Authentication','Permission:conversation_insights.run']);
```

Views (Metronic, padrão de `views/agent-performance/`):
- **index** — lista de análises + botão "Nova análise";
- **form** — multi-select de etapas / agentes / agentes de IA / times / funis / tags / canais, período (atalho "últimos 30 dias"), textarea do contexto, e o **preview**: "1.284 conversas encontradas · custo estimado US$ 3,40";
- **show** — funil de vazamento (onde param), donut de motivos, tabela motivo × etapa, ranking de vendedores por taxa de abandono, lista drilldown das conversas com link direto para o chat.

⚠️ **Permissões:** o `show` deve reaplicar `AgentFunnelPermission` / `PermissionService::canViewConversation()` no drilldown — um supervisor não pode ver, via relatório, conversa que não veria na lista.

---

## 5. O caso concreto: "time comercial, últimos 30 dias"

```php
$cohort = ConversationCohortService::build([
    'team_ids'   => [$comercialTeamId],       // expande em team_members.user_id
    'date_from'  => date('Y-m-d', strtotime('-30 days')),
    'date_to'    => date('Y-m-d'),
    'date_basis' => 'activity',
    'min_messages' => 4,
    'exclude_spam' => true,
]);

$batch = ConversationBatchAnalysisService::createBatch(
    $cohort,
    'Identifique em que momento o cliente desistiu da compra ou parou de responder e por quê. '
  . 'Aponte a última objeção não tratada e se houve falha de follow-up do vendedor.'
);
```

Saída esperada na tela:

- **Onde vazam:** 42% param na etapa "Proposta enviada", 23% em "Negociação".
- **Quem para primeiro:** em 61% dos casos o **cliente** silencia; em 29% foi o **vendedor** que não respondeu a última mensagem do cliente (número vindo da fase 0, custo zero, e portanto auditável).
- **Por quê:** preço (31%), falta de follow-up (24%), dúvida não respondida (18%), concorrente (11%).
- **Por vendedor:** ranking de taxa de "abandono por falta de follow-up".
- **Drilldown:** clicar em qualquer fatia lista as conversas com as citações que sustentam a classificação.

---

## 6. Riscos e cuidados

| Risco | Mitigação |
|---|---|
| Histórico de etapas inexistente (§2.1) | P0.1 + P0.3 antes de tudo; enquanto isso, a UI deve avisar que "passou pela etapa" só cobre a partir de _data do backfill_ |
| Custo de IA descontrolado | Preview obrigatório, teto por batch, cache, truncamento, modelo barato no MAP |
| Query da coorte pesada | Índices de P0.2; `EXISTS` em vez de `JOIN`+`GROUP BY`; `LIMIT` duro; o repositório já tem histórico de queries pesadas em conversas (`ANALISE_QUERIES_PESADAS_COMPLETA.md`) — vale rodar `EXPLAIN` antes de liberar |
| Alucinação da IA | Taxonomia fechada + `evidence_quotes` obrigatórias + `confidence`; métricas de "quem parou" vêm de SQL, não do LLM |
| Vazamento de dados do cliente | Reusar a anonimização já existente (`ManualGeneratorService::anonymize`, usada pelo `CopilotService`) antes de enviar transcrições à OpenAI |
| Permissão | Reaplicar filtros de funil/conversa no drilldown e no export |

---

## 7. Ordem de implementação sugerida

| # | Entrega | Depende de |
|---|---|---|
| 1 | P0.1 `recordStageTransition` + chamada em todos os pontos de escrita de etapa | — |
| 2 | P0.2 migration de índices/colunas + P0.3 backfill de `activities` | 1 |
| 3 | P0.4 correção de `conversation_assignments` (kanban + `removed_at`) | — |
| 4 | `ConversationCohortService` + endpoint `preview` (conta e estima, sem IA) | 2, 3 |
| 5 | Métricas determinísticas (fase 0) + tela já útil **sem custo de IA** | 4 |
| 6 | MAP + tabelas de batch/items + cron assíncrono | 5 |
| 7 | REDUCE + dashboard de síntese + export | 6 |

O passo 5 já entrega valor real (mostra onde e quando as conversas morrem) **sem gastar um centavo de IA**; a IA entra a partir do 6 para responder o "por quê".

---

_Arquivos-chave citados:_ `app/Models/Conversation.php`, `app/Services/FunnelService.php`, `app/Services/KanbanAgentService.php`, `app/Services/ContactSegmentationService.php`, `app/Services/AgentPerformanceAnalysisService.php`, `app/Models/ConversationAssignment.php`, `database/migrations/091_create_conversation_details_tables.php`, `database/migrations/101_create_conversation_assignments_history.php`.
