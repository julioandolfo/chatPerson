<?php
/**
 * ConversationBatchAnalysisService
 *
 * Analisa em lote as conversas de uma coorte (ver ConversationCohortService)
 * para responder perguntas como "em que momento o cliente desistiu da compra
 * ou parou de responder, e por quê?".
 *
 * Pipeline em 3 fases:
 *
 *   FASE 0 — métricas determinísticas (SQL/PHP, custo ZERO)
 *            quem falou por último, quem parou de responder, em que etapa a
 *            conversa travou, tempos de resposta. Esses números NÃO vêm da IA,
 *            portanto são auditáveis.
 *
 *   FASE 1 — MAP: uma chamada de LLM por conversa, devolvendo JSON com
 *            taxonomia FECHADA de motivo (o que permite agregar) e citações
 *            de evidência (o que permite auditar).
 *
 *   FASE 2 — REDUCE: uma única chamada de LLM sobre os AGREGADOS + amostra de
 *            citações, produzindo o diagnóstico executivo.
 *
 * O processamento é assíncrono (cron/process-conversation-analysis.php) e
 * retomável: cada item já analisado é pulado.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\ConversationAnalysisBatch;
use App\Models\ConversationAnalysisItem;
use App\Models\FunnelStageHistory;
use App\Models\Setting;

class ConversationBatchAnalysisService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    /** Modelo barato para a análise por conversa (fase 1) */
    public const DEFAULT_MODEL_MAP = 'gpt-4o-mini';

    /** Modelo forte para a síntese executiva (fase 2) */
    public const DEFAULT_MODEL_REDUCE = 'gpt-4o';

    /** Mensagens do início e do fim mantidas ao truncar conversas longas */
    private const HEAD_MESSAGES = 15;
    private const TAIL_MESSAGES = 40;

    /**
     * Desfechos possíveis (taxonomia fechada)
     */
    public const OUTCOMES = [
        'ganho' => 'Compra fechada',
        'perdido' => 'Perdido explicitamente',
        'em_andamento' => 'Negociação ainda viva',
        'abandonado_cliente' => 'Cliente parou de responder',
        'abandonado_vendedor' => 'Vendedor parou de responder',
        'sem_interesse' => 'Nunca houve intenção de compra',
        'indeterminado' => 'Não foi possível determinar',
    ];

    /**
     * Motivos possíveis (taxonomia fechada — é o que torna a agregação possível)
     */
    public const REASONS = [
        'preco' => 'Preço acima do esperado',
        'prazo' => 'Prazo de entrega/execução',
        'concorrencia' => 'Escolheu um concorrente',
        'falta_followup' => 'Vendedor não fez follow-up',
        'duvida_nao_respondida' => 'Dúvida do cliente ficou sem resposta',
        'demora_resposta' => 'Demora na resposta do vendedor',
        'sem_budget' => 'Cliente sem orçamento no momento',
        'momento_errado' => 'Timing ruim (vai decidir depois)',
        'atrito_atendimento' => 'Atrito ou má experiência no atendimento',
        'produto_inadequado' => 'Produto/serviço não atende à necessidade',
        'falta_autonomia' => 'Depende de terceiro para decidir',
        'sumiu_sem_sinal' => 'Sumiu sem dar qualquer motivo',
        'compra_concluida' => 'Não se aplica — a compra aconteceu',
        'outro' => 'Outro motivo',
    ];

    // ======================================================================
    // CRIAÇÃO DO LOTE
    // ======================================================================

    /**
     * Criar um lote de análise a partir dos filtros da coorte.
     *
     * @return int ID do lote criado
     * @throws \Exception se a coorte estiver vazia
     */
    public static function createBatch(
        array $filters,
        string $contextQuestion,
        ?string $name = null,
        ?int $userId = null,
        ?float $costLimit = null
    ): int {
        $filters = ConversationCohortService::normalizeFilters($filters);
        $conversationIds = ConversationCohortService::getConversationIds($filters);

        if (empty($conversationIds)) {
            throw new \Exception('Nenhuma conversa encontrada com esses filtros.');
        }

        $settings = self::getSettings();

        $batchId = ConversationAnalysisBatch::create([
            'name' => $name ?: ('Análise de ' . date('d/m/Y H:i')),
            'context_question' => trim($contextQuestion),
            'filters' => json_encode($filters, JSON_UNESCAPED_UNICODE),
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'status' => ConversationAnalysisBatch::STATUS_PENDING,
            'total_conversations' => count($conversationIds),
            'model_map' => $settings['model_map'],
            'model_reduce' => $settings['model_reduce'],
            'cost_limit' => $costLimit ?? $settings['cost_limit_per_batch'],
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        ConversationAnalysisItem::enqueue($batchId, $conversationIds);

        Logger::info("ConversationBatchAnalysisService::createBatch - Lote {$batchId} criado com " . count($conversationIds) . " conversas");

        return $batchId;
    }

    // ======================================================================
    // PROCESSAMENTO (chamado pelo cron)
    // ======================================================================

    /**
     * Processar um pedaço do lote.
     *
     * @param int $maxItems quantas conversas analisar nesta rodada
     * @return array resumo do que foi feito
     */
    public static function processBatch(int $batchId, int $maxItems = 10): array
    {
        $batch = ConversationAnalysisBatch::find($batchId);
        if (!$batch) {
            throw new \Exception("Lote {$batchId} não encontrado");
        }

        $batch = ConversationAnalysisBatch::decode($batch);

        if (in_array($batch['status'], [ConversationAnalysisBatch::STATUS_COMPLETED, ConversationAnalysisBatch::STATUS_CANCELLED], true)) {
            return ['status' => $batch['status'], 'processed' => 0];
        }

        if ($batch['status'] === ConversationAnalysisBatch::STATUS_PENDING) {
            ConversationAnalysisBatch::update($batchId, [
                'status' => ConversationAnalysisBatch::STATUS_RUNNING,
                'started_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $apiKey = self::getApiKey();
        if (!$apiKey) {
            self::failBatch($batchId, 'Chave da OpenAI não configurada (Setting: openai_api_key).');
            return ['status' => 'failed', 'processed' => 0];
        }

        // Teto de custo do lote
        $costLimit = (float)($batch['cost_limit'] ?? 0);
        if ($costLimit > 0 && (float)$batch['cost'] >= $costLimit) {
            Logger::warning("ConversationBatchAnalysisService - Lote {$batchId} atingiu o teto de custo (US$ {$costLimit})");
            return self::finalizeBatch($batchId, 'Teto de custo do lote atingido.');
        }

        // Itens travados em 'processing' (worker morreu) voltam para a fila
        ConversationAnalysisItem::releaseStale($batchId);

        $items = ConversationAnalysisItem::getPending($batchId, $maxItems);

        if (empty($items)) {
            // Pode haver itens reservados por outro worker — só fecha quando
            // ninguém mais está trabalhando no lote
            if (ConversationAnalysisItem::countUnfinished($batchId) > 0) {
                return ['status' => 'running', 'processed' => 0, 'remaining' => ConversationAnalysisItem::countUnfinished($batchId)];
            }

            return self::finalizeBatch($batchId);
        }

        $processed = 0;

        foreach ($items as $item) {
            // Reserva atômica: se outro worker pegou este item antes, pula.
            // Sem isso, dois crons simultâneos pagariam duas vezes pela mesma conversa.
            if (!ConversationAnalysisItem::claim((int)$item['id'])) {
                continue;
            }

            try {
                self::processItem($item, $batch, $apiKey);
            } catch (\Exception $e) {
                Logger::error("ConversationBatchAnalysisService - Erro na conversa {$item['conversation_id']}: " . $e->getMessage());

                ConversationAnalysisItem::update((int)$item['id'], [
                    'status' => ConversationAnalysisItem::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 1000),
                    'analyzed_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $processed++;
        }

        ConversationAnalysisBatch::refreshTotals($batchId);

        $remaining = ConversationAnalysisItem::countUnfinished($batchId);

        if ($remaining === 0) {
            return self::finalizeBatch($batchId);
        }

        return ['status' => 'running', 'processed' => $processed, 'remaining' => $remaining];
    }

    /**
     * Analisar uma conversa (fase 0 + fase 1)
     */
    private static function processItem(array $item, array $batch, string $apiKey): void
    {
        $conversationId = (int)$item['conversation_id'];

        $conversation = Database::fetch(
            "SELECT c.*, ct.name AS contact_name, u.name AS agent_name, fs.name AS current_stage_name
             FROM conversations c
             LEFT JOIN contacts ct ON ct.id = c.contact_id
             LEFT JOIN users u ON u.id = c.agent_id
             LEFT JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
             WHERE c.id = ?",
            [$conversationId]
        );

        if (!$conversation) {
            ConversationAnalysisItem::update((int)$item['id'], [
                'status' => ConversationAnalysisItem::STATUS_SKIPPED,
                'error' => 'Conversa não encontrada',
                'analyzed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        $messages = self::getMessages($conversationId);

        // FASE 0 — determinística
        $metrics = self::computeMetrics($conversationId, $messages);

        if (count($messages) < 2) {
            ConversationAnalysisItem::update((int)$item['id'], [
                'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
                'status' => ConversationAnalysisItem::STATUS_SKIPPED,
                'error' => 'Conversa curta demais para análise',
                'who_stopped' => $metrics['who_stopped'],
                'agent_id' => $conversation['agent_id'] ?: null,
                'analyzed_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        // FASE 1 — MAP via LLM
        $model = $batch['model_map'] ?: self::DEFAULT_MODEL_MAP;
        $prompt = self::buildConversationPrompt($conversation, $messages, $metrics, $batch['context_question']);

        $response = self::callOpenAI($apiKey, $model, [
            [
                'role' => 'system',
                'content' => 'Você é um analista de vendas sênior. Analisa transcrições de atendimento para '
                    . 'identificar em que ponto a negociação travou e por quê. É rigoroso: só afirma o que a '
                    . 'transcrição sustenta, e cita trechos literais como evidência. Responde APENAS JSON válido.'
            ],
            ['role' => 'user', 'content' => $prompt],
        ], 0.2, 1200);

        $analysis = self::parseAnalysis($response, $metrics);

        $tokens = (int)($response['usage']['total_tokens'] ?? 0);
        $cost = self::calculateCost($model, (int)($response['usage']['prompt_tokens'] ?? 0), (int)($response['usage']['completion_tokens'] ?? 0));

        ConversationAnalysisItem::update((int)$item['id'], [
            'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'analysis' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
            'outcome' => $analysis['outcome'],
            'primary_reason' => $analysis['primary_reason'],
            'drop_off_stage_id' => $metrics['drop_off_stage_id'],
            'who_stopped' => $metrics['who_stopped'],
            'agent_id' => $conversation['agent_id'] ?: null,
            'confidence' => $analysis['confidence'],
            'status' => ConversationAnalysisItem::STATUS_ANALYZED,
            'tokens_used' => $tokens,
            'cost' => $cost,
            'analyzed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ======================================================================
    // FASE 0 — MÉTRICAS DETERMINÍSTICAS
    // ======================================================================

    /**
     * Métricas calculadas sem IA. São a base auditável do relatório:
     * "quem parou de responder" aqui é um fato, não uma opinião do modelo.
     */
    public static function computeMetrics(int $conversationId, ?array $messages = null): array
    {
        $messages = $messages ?? self::getMessages($conversationId);

        $totals = ['contact' => 0, 'human' => 0, 'ai' => 0, 'system' => 0];
        $lastByContact = null;
        $lastByAgent = null;
        $firstContactAt = null;
        $responseTimes = [];
        $maxGap = 0;
        $pendingContactAt = null;
        $previousAt = null;

        foreach ($messages as $message) {
            $createdAt = strtotime($message['created_at']);
            $type = self::classifySender($message);

            $totals[$type] = ($totals[$type] ?? 0) + 1;

            if ($previousAt !== null) {
                $maxGap = max($maxGap, $createdAt - $previousAt);
            }
            $previousAt = $createdAt;

            if ($type === 'contact') {
                $firstContactAt = $firstContactAt ?? $createdAt;
                $lastByContact = $createdAt;

                // Só conta como "aguardando resposta" a primeira de uma sequência
                if ($pendingContactAt === null) {
                    $pendingContactAt = $createdAt;
                }
            } elseif ($type === 'human' || $type === 'ai') {
                $lastByAgent = $createdAt;

                if ($pendingContactAt !== null) {
                    $responseTimes[] = $createdAt - $pendingContactAt;
                    $pendingContactAt = null;
                }
            }
        }

        $lastMessage = end($messages) ?: null;
        $lastMessageAt = $lastMessage ? $lastMessage['created_at'] : null;
        $lastSpeaker = $lastMessage ? self::classifySender($lastMessage) : null;

        // Quem deixou de responder:
        //  - última mensagem do cliente  -> o VENDEDOR não respondeu
        //  - última mensagem do agente   -> o CLIENTE sumiu
        $whoStopped = 'ninguem';
        if ($lastSpeaker === 'contact') {
            $whoStopped = 'vendedor';
        } elseif ($lastSpeaker === 'human') {
            $whoStopped = 'cliente';
        } elseif ($lastSpeaker === 'ai') {
            $whoStopped = 'cliente';
        }

        // Se a última mensagem é recente, ninguém "abandonou" ainda
        $silenceDays = $lastMessageAt ? (int)floor((time() - strtotime($lastMessageAt)) / 86400) : 0;
        if ($silenceDays < 2) {
            $whoStopped = 'ninguem';
        }

        // Etapa vigente quando a conversa parou
        $dropOffStage = null;
        if ($lastMessageAt) {
            $dropOffStage = FunnelStageHistory::getStageAt($conversationId, $lastMessageAt);
        }

        if (!$dropOffStage) {
            $current = Database::fetch(
                "SELECT c.funnel_stage_id AS stage_id, fs.name AS stage_name
                 FROM conversations c
                 LEFT JOIN funnel_stages fs ON fs.id = c.funnel_stage_id
                 WHERE c.id = ?",
                [$conversationId]
            );
            $dropOffStage = $current && $current['stage_id'] ? $current : null;
        }

        // Trocas de agente (handoffs)
        $handoffs = Database::fetch(
            "SELECT COUNT(DISTINCT agent_id) AS agents FROM conversation_assignments WHERE conversation_id = ?",
            [$conversationId]
        );

        return [
            'messages_total' => count($messages),
            'messages_contact' => $totals['contact'] ?? 0,
            'messages_human' => $totals['human'] ?? 0,
            'messages_ai' => $totals['ai'] ?? 0,
            'first_contact_at' => $firstContactAt ? date('Y-m-d H:i:s', $firstContactAt) : null,
            'last_message_at' => $lastMessageAt,
            'last_speaker' => $lastSpeaker,
            'who_stopped' => $whoStopped,
            'silence_days' => $silenceDays,
            'unanswered_by_agent' => $pendingContactAt !== null,
            'avg_response_seconds' => !empty($responseTimes) ? (int)round(array_sum($responseTimes) / count($responseTimes)) : null,
            'first_response_seconds' => $responseTimes[0] ?? null,
            'max_gap_seconds' => $maxGap,
            'drop_off_stage_id' => $dropOffStage['stage_id'] ?? null,
            'drop_off_stage_name' => $dropOffStage['stage_name'] ?? null,
            'agents_involved' => (int)($handoffs['agents'] ?? 0),
            'last_contact_at' => $lastByContact ? date('Y-m-d H:i:s', $lastByContact) : null,
            'last_agent_at' => $lastByAgent ? date('Y-m-d H:i:s', $lastByAgent) : null,
        ];
    }

    /**
     * Classificar o remetente conforme a convenção do sistema:
     * mensagem de IA = sender_type 'agent' COM ai_agent_id preenchido.
     */
    private static function classifySender(array $message): string
    {
        $senderType = $message['sender_type'] ?? '';

        if ($senderType === 'contact') {
            return 'contact';
        }

        if ($senderType === 'agent') {
            if (!empty($message['ai_agent_id'])) {
                return 'ai';
            }
            // sender_id 0/null são mensagens de sistema disfarçadas de agente
            return !empty($message['sender_id']) ? 'human' : 'system';
        }

        return 'system';
    }

    // ======================================================================
    // FASE 1 — PROMPT E PARSING
    // ======================================================================

    private static function getMessages(int $conversationId): array
    {
        return Database::fetchAll(
            "SELECT id, sender_type, sender_id, ai_agent_id, content, message_type, created_at
             FROM messages
             WHERE conversation_id = ?
             ORDER BY created_at ASC, id ASC",
            [$conversationId]
        );
    }

    /**
     * Transcrição legível, com marcação de silêncio (que é o sinal de abandono)
     * e anonimização dos dados do cliente antes de sair para a OpenAI.
     */
    public static function buildTranscript(array $messages, ?string $contactName = null): string
    {
        $messages = self::truncateMessages($messages);

        $lines = [];
        $previousAt = null;

        foreach ($messages as $message) {
            if ($message === '__GAP__') {
                $lines[] = "\n[... trecho intermediário omitido ...]\n";
                $previousAt = null;
                continue;
            }

            $type = self::classifySender($message);

            $label = match ($type) {
                'contact' => 'CLIENTE',
                'human' => 'VENDEDOR',
                'ai' => 'IA',
                default => 'SISTEMA',
            };

            $createdAt = strtotime($message['created_at']);

            if ($previousAt !== null) {
                $diff = $createdAt - $previousAt;
                if ($diff > 3600) {
                    $lines[] = "\n[... " . self::humanizeInterval($diff) . " de silêncio ...]\n";
                }
            }
            $previousAt = $createdAt;

            $content = trim((string)($message['content'] ?? ''));

            if ($content === '' && !empty($message['message_type']) && $message['message_type'] !== 'text') {
                $content = '(' . $message['message_type'] . ')';
            }

            if ($content === '') {
                continue;
            }

            $lines[] = '[' . date('d/m H:i', $createdAt) . "] {$label}: " . $content;
        }

        $transcript = implode("\n", $lines);

        // Privacidade: mesma anonimização usada pelo Copiloto antes de ir à OpenAI
        if (class_exists('\App\Services\ManualGeneratorService')) {
            try {
                $transcript = ManualGeneratorService::anonymize($transcript, $contactName);
            } catch (\Exception $e) {
                Logger::error("ConversationBatchAnalysisService - Falha ao anonimizar: " . $e->getMessage());
            }
        }

        return $transcript;
    }

    /**
     * Conversas longas: manter o começo (contexto) e o fim (onde está o abandono)
     */
    private static function truncateMessages(array $messages): array
    {
        $max = self::HEAD_MESSAGES + self::TAIL_MESSAGES;

        if (count($messages) <= $max) {
            return $messages;
        }

        $head = array_slice($messages, 0, self::HEAD_MESSAGES);
        $tail = array_slice($messages, -self::TAIL_MESSAGES);

        return array_merge($head, ['__GAP__'], $tail);
    }

    private static function humanizeInterval(int $seconds): string
    {
        if ($seconds >= 86400) {
            $days = (int)round($seconds / 86400);
            return $days . ' dia' . ($days > 1 ? 's' : '');
        }

        $hours = (int)round($seconds / 3600);
        return $hours . ' hora' . ($hours > 1 ? 's' : '');
    }

    private static function buildConversationPrompt(array $conversation, array $messages, array $metrics, string $contextQuestion): string
    {
        $transcript = self::buildTranscript($messages, $conversation['contact_name'] ?? null);

        $outcomes = implode("\n", array_map(
            static fn($key, $label) => "  - {$key}: {$label}",
            array_keys(self::OUTCOMES),
            self::OUTCOMES
        ));

        $reasons = implode("\n", array_map(
            static fn($key, $label) => "  - {$key}: {$label}",
            array_keys(self::REASONS),
            self::REASONS
        ));

        $whoStoppedLabel = match ($metrics['who_stopped']) {
            'cliente' => 'o CLIENTE parou de responder',
            'vendedor' => 'o VENDEDOR não respondeu a última mensagem do cliente',
            default => 'a conversa ainda está ativa',
        };

        $facts = "FATOS JÁ APURADOS (não contradiga, use como base):\n"
            . "- Última mensagem: {$metrics['last_message_at']} ({$metrics['silence_days']} dias de silêncio)\n"
            . "- Quem parou: {$whoStoppedLabel}\n"
            . "- Etapa do funil quando parou: " . ($metrics['drop_off_stage_name'] ?? 'não informada') . "\n"
            . "- Mensagens: {$metrics['messages_total']} no total "
            . "({$metrics['messages_contact']} do cliente, {$metrics['messages_human']} do vendedor, {$metrics['messages_ai']} da IA)\n";

        if ($metrics['avg_response_seconds'] !== null) {
            $facts .= '- Tempo médio de resposta do atendimento: ' . self::humanizeInterval((int)$metrics['avg_response_seconds']) . "\n";
        }

        return <<<PROMPT
CONTEXTO DA ANÁLISE (o que o gestor quer entender):
{$contextQuestion}

{$facts}

TRANSCRIÇÃO DA CONVERSA:
---
{$transcript}
---

TAREFA:
Analise a conversa acima e responda em JSON. Regras obrigatórias:
1. "outcome" e "primary_reason" DEVEM ser um dos valores listados. Nunca invente valores.
2. "evidence_quotes" deve conter trechos LITERAIS da transcrição que sustentam sua conclusão.
   Se não houver evidência literal, use confidence baixa (< 0.5).
3. Não deduza motivo que a conversa não mostra. "sumiu_sem_sinal" existe exatamente para isso.
4. "drop_off_moment.message_excerpt" deve ser o trecho onde a negociação travou.

VALORES VÁLIDOS PARA "outcome":
{$outcomes}

VALORES VÁLIDOS PARA "primary_reason":
{$reasons}

FORMATO DE RESPOSTA (JSON puro, sem markdown):
{
  "outcome": "string (um dos valores acima)",
  "primary_reason": "string (um dos valores acima)",
  "reason_explanation": "string, 1-2 frases explicando o motivo com base na conversa",
  "drop_off_moment": {
    "message_excerpt": "string, trecho literal onde travou",
    "what_happened": "string, 1 frase"
  },
  "objections": ["objeções levantadas pelo cliente"],
  "buying_signals": ["sinais de interesse de compra demonstrados"],
  "agent_mistakes": ["erros concretos do atendimento, se houver"],
  "recoverable": true/false,
  "recovery_action": "string, ação concreta para retomar (ou vazio se não recuperável)",
  "confidence": 0.0 a 1.0,
  "evidence_quotes": ["trechos literais da conversa"]
}
PROMPT;
    }

    /**
     * Validar e normalizar a resposta do modelo contra a taxonomia fechada.
     * Valor fora da taxonomia vira 'outro'/'indeterminado' em vez de poluir a agregação.
     */
    private static function parseAnalysis(array $response, array $metrics): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);

        if (!is_array($data)) {
            throw new \Exception('Resposta da IA não é um JSON válido');
        }

        $outcome = $data['outcome'] ?? '';
        if (!isset(self::OUTCOMES[$outcome])) {
            $outcome = 'indeterminado';
        }

        $reason = $data['primary_reason'] ?? '';
        if (!isset(self::REASONS[$reason])) {
            $reason = 'outro';
        }

        $confidence = isset($data['confidence']) ? (float)$data['confidence'] : 0.5;
        $confidence = max(0, min(1, $confidence));

        // Sem citação literal, a confiança não pode ser alta
        $quotes = self::toStringArray($data['evidence_quotes'] ?? []);
        if (empty($quotes) && $confidence > 0.5) {
            $confidence = 0.5;
        }

        return [
            'outcome' => $outcome,
            'primary_reason' => $reason,
            'reason_explanation' => (string)($data['reason_explanation'] ?? ''),
            'drop_off_moment' => [
                'message_excerpt' => (string)($data['drop_off_moment']['message_excerpt'] ?? ''),
                'what_happened' => (string)($data['drop_off_moment']['what_happened'] ?? ''),
                'stage_name' => $metrics['drop_off_stage_name'] ?? null,
            ],
            'objections' => self::toStringArray($data['objections'] ?? []),
            'buying_signals' => self::toStringArray($data['buying_signals'] ?? []),
            'agent_mistakes' => self::toStringArray($data['agent_mistakes'] ?? []),
            'recoverable' => !empty($data['recoverable']),
            'recovery_action' => (string)($data['recovery_action'] ?? ''),
            'confidence' => round($confidence, 2),
            'evidence_quotes' => $quotes,
        ];
    }

    private static function toStringArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return array_slice($out, 0, 8);
    }

    // ======================================================================
    // FASE 2 — AGREGAÇÃO E SÍNTESE
    // ======================================================================

    /**
     * Agregar os resultados do lote (tudo em SQL, sem IA)
     */
    public static function aggregate(int $batchId): array
    {
        $byReason = Database::fetchAll(
            "SELECT primary_reason, COUNT(*) AS total
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status = 'analyzed' AND primary_reason IS NOT NULL
             GROUP BY primary_reason
             ORDER BY total DESC",
            [$batchId]
        );

        $byOutcome = Database::fetchAll(
            "SELECT outcome, COUNT(*) AS total
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status = 'analyzed' AND outcome IS NOT NULL
             GROUP BY outcome
             ORDER BY total DESC",
            [$batchId]
        );

        $byStage = Database::fetchAll(
            "SELECT i.drop_off_stage_id, fs.name AS stage_name, COUNT(*) AS total
             FROM conversation_analysis_items i
             LEFT JOIN funnel_stages fs ON fs.id = i.drop_off_stage_id
             WHERE i.batch_id = ? AND i.status = 'analyzed'
             GROUP BY i.drop_off_stage_id, fs.name
             ORDER BY total DESC",
            [$batchId]
        );

        $byWhoStopped = Database::fetchAll(
            "SELECT who_stopped, COUNT(*) AS total
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status IN ('analyzed', 'skipped') AND who_stopped IS NOT NULL
             GROUP BY who_stopped
             ORDER BY total DESC",
            [$batchId]
        );

        $byAgent = Database::fetchAll(
            "SELECT i.agent_id, u.name AS agent_name,
                    COUNT(*) AS total,
                    SUM(i.who_stopped = 'vendedor') AS stopped_by_agent,
                    SUM(i.primary_reason = 'falta_followup') AS no_followup,
                    SUM(i.outcome = 'ganho') AS won
             FROM conversation_analysis_items i
             LEFT JOIN users u ON u.id = i.agent_id
             WHERE i.batch_id = ? AND i.status = 'analyzed' AND i.agent_id IS NOT NULL
             GROUP BY i.agent_id, u.name
             ORDER BY total DESC",
            [$batchId]
        );

        $reasonByStage = Database::fetchAll(
            "SELECT fs.name AS stage_name, i.primary_reason, COUNT(*) AS total
             FROM conversation_analysis_items i
             LEFT JOIN funnel_stages fs ON fs.id = i.drop_off_stage_id
             WHERE i.batch_id = ? AND i.status = 'analyzed' AND i.primary_reason IS NOT NULL
             GROUP BY fs.name, i.primary_reason
             HAVING total > 0
             ORDER BY total DESC
             LIMIT 40",
            [$batchId]
        );

        $timing = Database::fetch(
            "SELECT
                COUNT(*) AS analyzed,
                AVG(confidence) AS avg_confidence,
                SUM(JSON_UNQUOTE(JSON_EXTRACT(metrics, '$.unanswered_by_agent')) = 'true') AS unanswered
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status = 'analyzed'",
            [$batchId]
        );

        return [
            'by_reason' => $byReason,
            'by_outcome' => $byOutcome,
            'by_stage' => $byStage,
            'by_who_stopped' => $byWhoStopped,
            'by_agent' => $byAgent,
            'reason_by_stage' => $reasonByStage,
            'analyzed' => (int)($timing['analyzed'] ?? 0),
            'avg_confidence' => round((float)($timing['avg_confidence'] ?? 0), 2),
            'unanswered_by_agent' => (int)($timing['unanswered'] ?? 0),
            'reason_labels' => self::REASONS,
            'outcome_labels' => self::OUTCOMES,
        ];
    }

    /**
     * Síntese executiva — o modelo forte vê só os AGREGADOS e uma amostra de
     * citações, nunca as transcrições completas (custo e privacidade).
     */
    public static function synthesize(int $batchId, array $batch, array $metrics): ?array
    {
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            return null;
        }

        if ((int)($metrics['analyzed'] ?? 0) === 0) {
            return null;
        }

        $samples = Database::fetchAll(
            "SELECT primary_reason, analysis
             FROM conversation_analysis_items
             WHERE batch_id = ? AND status = 'analyzed' AND confidence >= 0.6
             ORDER BY confidence DESC
             LIMIT 25",
            [$batchId]
        );

        $quotes = [];
        foreach ($samples as $sample) {
            $analysis = json_decode($sample['analysis'] ?? '', true);
            if (!is_array($analysis)) {
                continue;
            }

            $quote = $analysis['evidence_quotes'][0] ?? ($analysis['reason_explanation'] ?? '');
            if ($quote) {
                $quotes[] = '[' . $sample['primary_reason'] . '] ' . mb_substr($quote, 0, 220);
            }
        }

        $model = $batch['model_reduce'] ?: self::DEFAULT_MODEL_REDUCE;

        $prompt = "PERGUNTA DO GESTOR:\n{$batch['context_question']}\n\n"
            . "PERÍODO: {$batch['date_from']} a {$batch['date_to']}\n"
            . "CONVERSAS ANALISADAS: {$metrics['analyzed']}\n\n"
            . "DADOS AGREGADOS (contagens reais, calculadas no banco):\n"
            . json_encode([
                'motivos' => $metrics['by_reason'],
                'desfechos' => $metrics['by_outcome'],
                'etapa_onde_travou' => $metrics['by_stage'],
                'quem_parou_de_responder' => $metrics['by_who_stopped'],
                'por_vendedor' => $metrics['by_agent'],
                'motivo_por_etapa' => $metrics['reason_by_stage'],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nAMOSTRA DE EVIDÊNCIAS (trechos reais das conversas):\n- " . implode("\n- ", array_slice($quotes, 0, 25))
            . "\n\nTAREFA:\nEscreva um diagnóstico executivo em JSON. Use os NÚMEROS acima — não invente "
            . "percentuais. Priorize por impacto (quantidade de conversas afetadas). Seja concreto: "
            . "recomendação sem ação executável não serve.\n\n"
            . "FORMATO (JSON puro):\n"
            . "{\n"
            . '  "headline": "1 frase com o achado principal",' . "\n"
            . '  "leak_points": [{"where": "etapa ou momento", "share": "ex: 42% das conversas", "why": "explicação", "impact": "alto|medio|baixo"}],' . "\n"
            . '  "who_is_dropping": "análise de cliente vs vendedor com os números",' . "\n"
            . '  "top_reasons": [{"reason": "rótulo legível", "count": 0, "insight": "o que fazer a respeito"}],' . "\n"
            . '  "agent_insights": [{"agent": "nome", "observation": "observação factual"}],' . "\n"
            . '  "recommendations": [{"action": "ação concreta", "expected_impact": "resultado esperado", "priority": 1}],' . "\n"
            . '  "caveats": ["limitações desta análise"]' . "\n"
            . "}";

        $response = self::callOpenAI($apiKey, $model, [
            [
                'role' => 'system',
                'content' => 'Você é um head de vendas analisando dados de CRM. Trabalha só com os números '
                    . 'fornecidos, nunca inventa estatística. Responde APENAS JSON válido.'
            ],
            ['role' => 'user', 'content' => $prompt],
        ], 0.3, 2500);

        $content = $response['choices'][0]['message']['content'] ?? '';
        $summary = json_decode($content, true);

        if (!is_array($summary)) {
            Logger::error("ConversationBatchAnalysisService::synthesize - JSON inválido no lote {$batchId}");
            return null;
        }

        $summary['_meta'] = [
            'model' => $model,
            'tokens' => (int)($response['usage']['total_tokens'] ?? 0),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        // O custo da síntese entra no total do lote
        $cost = self::calculateCost(
            $model,
            (int)($response['usage']['prompt_tokens'] ?? 0),
            (int)($response['usage']['completion_tokens'] ?? 0)
        );

        Database::execute(
            "UPDATE conversation_analysis_batches
             SET cost = cost + ?, tokens_used = tokens_used + ?
             WHERE id = ?",
            [round($cost, 4), (int)($response['usage']['total_tokens'] ?? 0), $batchId]
        );

        return $summary;
    }

    /**
     * Fechar o lote: agregar, sintetizar e marcar como concluído
     */
    private static function finalizeBatch(int $batchId, ?string $note = null): array
    {
        $batch = ConversationAnalysisBatch::find($batchId);
        if (!$batch) {
            return ['status' => 'failed', 'processed' => 0];
        }

        ConversationAnalysisBatch::refreshTotals($batchId);

        $metrics = self::aggregate($batchId);

        $summary = null;
        try {
            $summary = self::synthesize($batchId, $batch, $metrics);
        } catch (\Exception $e) {
            Logger::error("ConversationBatchAnalysisService::finalizeBatch - Falha na síntese do lote {$batchId}: " . $e->getMessage());
        }

        ConversationAnalysisBatch::update($batchId, [
            'status' => ConversationAnalysisBatch::STATUS_COMPLETED,
            'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'summary' => $summary ? json_encode($summary, JSON_UNESCAPED_UNICODE) : null,
            'error_message' => $note,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::info("ConversationBatchAnalysisService - Lote {$batchId} concluído");

        // Avisar quem pediu a análise
        try {
            if ($batch['created_by'] && class_exists('\App\Services\NotificationService')) {
                NotificationService::create([
                    'user_id' => (int)$batch['created_by'],
                    'type' => 'system',
                    'title' => 'Análise de conversas concluída',
                    'message' => "A análise \"{$batch['name']}\" terminou. {$metrics['analyzed']} conversas analisadas.",
                    'link' => '/conversation-insights/' . $batchId,
                    'data' => ['batch_id' => $batchId],
                ]);
            }
        } catch (\Exception $e) {
            Logger::error("ConversationBatchAnalysisService - Falha ao notificar: " . $e->getMessage());
        }

        return ['status' => 'completed', 'processed' => 0, 'analyzed' => $metrics['analyzed']];
    }

    private static function failBatch(int $batchId, string $message): void
    {
        ConversationAnalysisBatch::update($batchId, [
            'status' => ConversationAnalysisBatch::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::error("ConversationBatchAnalysisService - Lote {$batchId} falhou: {$message}");
    }

    /**
     * Cancelar um lote em andamento
     */
    public static function cancelBatch(int $batchId): bool
    {
        $batch = ConversationAnalysisBatch::find($batchId);
        if (!$batch || $batch['status'] === ConversationAnalysisBatch::STATUS_COMPLETED) {
            return false;
        }

        ConversationAnalysisBatch::update($batchId, [
            'status' => ConversationAnalysisBatch::STATUS_CANCELLED,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    // ======================================================================
    // CUSTO
    // ======================================================================

    /**
     * Estimar o custo ANTES de rodar — é o número que a UI mostra para o
     * usuário confirmar.
     */
    public static function estimateCost(int $conversations, int $avgMessages, ?string $modelMap = null, ?string $modelReduce = null): array
    {
        if (!$modelMap || !$modelReduce) {
            $settings = self::getSettings();
            $modelMap = $modelMap ?: $settings['model_map'];
            $modelReduce = $modelReduce ?: $settings['model_reduce'];
        }

        // ~45 tokens por mensagem + ~700 de prompt fixo; saída ~400 tokens
        $cappedMessages = min($avgMessages, self::HEAD_MESSAGES + self::TAIL_MESSAGES);
        $promptTokens = (int)($cappedMessages * 45) + 700;
        $completionTokens = 400;

        $mapCost = self::calculateCost($modelMap, $promptTokens, $completionTokens) * $conversations;
        $reduceCost = self::calculateCost($modelReduce, 6000, 1500);

        return [
            'map' => round($mapCost, 4),
            'reduce' => round($reduceCost, 4),
            'total' => round($mapCost + $reduceCost, 4),
            'model_map' => $modelMap,
            'model_reduce' => $modelReduce,
            'tokens_per_conversation' => $promptTokens + $completionTokens,
        ];
    }

    private static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // USD por 1K tokens
        $prices = [
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];

        $price = $prices[$model] ?? $prices['gpt-4o-mini'];

        return round(
            ($promptTokens / 1000 * $price['input']) + ($completionTokens / 1000 * $price['output']),
            6
        );
    }

    // ======================================================================
    // INFRAESTRUTURA
    // ======================================================================

    public static function getSettings(): array
    {
        try {
            $stored = Setting::get('conversation_insights_settings');

            if (is_string($stored) && $stored !== '') {
                $decoded = json_decode($stored, true);
                if (is_array($decoded)) {
                    return array_merge(self::getDefaultSettings(), $decoded);
                }
            }
        } catch (\Throwable $e) {
            Logger::error('ConversationBatchAnalysisService::getSettings - ' . $e->getMessage());
        }

        return self::getDefaultSettings();
    }

    public static function getDefaultSettings(): array
    {
        return [
            'model_map' => self::DEFAULT_MODEL_MAP,
            'model_reduce' => self::DEFAULT_MODEL_REDUCE,
            'cost_limit_per_batch' => 25.00,
            'items_per_run' => 10,
        ];
    }

    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');

        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }

        return $apiKey ?: null;
    }

    private static function callOpenAI(string $apiKey, string $model, array $messages, float $temperature, int $maxTokens): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception('Erro de conexão com a OpenAI: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $message = $errorData['error']['message'] ?? 'Erro desconhecido';
            throw new \Exception("Erro da API OpenAI ({$httpCode}): {$message}");
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta inválida da OpenAI');
        }

        return $data;
    }
}
