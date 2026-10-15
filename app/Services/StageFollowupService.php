<?php
/**
 * Service StageFollowupService
 *
 * Follow-up automático por etapa do funil ("continuar conversa").
 *
 * Cada etapa pode ter uma sequência de mensagens, com tempos contados a partir
 * da ÚLTIMA mensagem enviada por nós na conversa. Ex.:
 *   - após 2h  → "Vamos continuar?"
 *   - após 6h  → "Ainda está por aí?"
 *
 * Ciclo de vida (uma linha por conversa em conversation_followups):
 *   - Toda mensagem nossa (agente/IA/automação, exceto notas) arma ou rearma a
 *     sequência a partir dela (onOutgoingMessage).
 *   - O cliente respondeu, a conversa foi encerrada ou a etapa ficou sem
 *     follow-up → a sequência para sozinha (reconcile).
 *   - O agente pode cancelar manualmente pelo chat (cancel). A próxima
 *     mensagem enviada liga a sequência de novo.
 *   - O StageFollowupJob (cron) dispara os passos vencidos (processDue).
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\FunnelStage;
use App\Helpers\Database;
use App\Helpers\Logger;

class StageFollowupService
{
    /** Unidades aceitas e seu valor em minutos */
    public const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    public const MAX_STEPS = 20;
    private const MAX_DELAY_MINUTES = 90 * 1440; // 90 dias
    private const MAX_MESSAGE_LENGTH = 4000;

    /** Falhas seguidas de envio antes de desistir da sequência */
    private const MAX_ATTEMPTS = 3;
    private const RETRY_MINUTES = 5;

    /** Tempo de "reserva" de um passo enquanto o cron o processa (evita envio duplicado) */
    private const CLAIM_MINUTES = 5;

    private const OPEN_STATUSES = ['open', 'pending'];

    /** Limites anti-spam padrão (por número/conta de envio), ajustáveis na aba Follow-up */
    public const DEFAULT_LIMITS = [
        'min_interval_seconds' => 15,
        'max_interval_seconds' => 45,
        'max_per_hour' => 60,
        'max_per_day' => 300,
    ];

    /** Tempo máximo que uma execução do cron fica enviando (o job tem 30s no run-scheduled-jobs) */
    private const RUN_TIME_BUDGET_SECONDS = 20;

    /** true enquanto o próprio follow-up envia — a mensagem enviada não pode rearmar a sequência */
    private static bool $sending = false;

    // =====================================================================
    // CONFIGURAÇÃO POR ETAPA
    // =====================================================================

    /**
     * Configuração da etapa (sempre retorna estrutura completa, mesmo sem registro)
     */
    public static function getStageConfig(int $stageId): array
    {
        $row = Database::fetch("SELECT * FROM funnel_stage_followups WHERE funnel_stage_id = ?", [$stageId]);

        $steps = [];
        if ($row && !empty($row['steps'])) {
            $decoded = is_string($row['steps']) ? json_decode($row['steps'], true) : $row['steps'];
            $steps = is_array($decoded) ? array_values($decoded) : [];
        }

        return [
            'funnel_stage_id' => $stageId,
            'enabled' => !empty($row['enabled']),
            'only_business_hours' => !empty($row['only_business_hours']),
            'only_human_messages' => !empty($row['only_human_messages']),
            'steps' => $steps,
        ];
    }

    /**
     * Salvar configuração da etapa
     */
    public static function saveStageConfig(int $stageId, array $data): array
    {
        if (!FunnelStage::find($stageId)) {
            throw new \InvalidArgumentException('Etapa não encontrada');
        }

        $steps = self::validateSteps($data['steps'] ?? []);
        $enabled = self::toBool($data['enabled'] ?? false);

        if ($enabled && empty($steps)) {
            throw new \InvalidArgumentException('Adicione pelo menos uma mensagem para ativar o follow-up');
        }

        Database::execute(
            "INSERT INTO funnel_stage_followups (funnel_stage_id, enabled, only_business_hours, only_human_messages, steps)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                only_business_hours = VALUES(only_business_hours),
                only_human_messages = VALUES(only_human_messages),
                steps = VALUES(steps)",
            [
                $stageId,
                $enabled ? 1 : 0,
                self::toBool($data['only_business_hours'] ?? false) ? 1 : 0,
                self::toBool($data['only_human_messages'] ?? false) ? 1 : 0,
                json_encode($steps, JSON_UNESCAPED_UNICODE),
            ]
        );

        if (!$enabled) {
            Database::execute(
                "UPDATE conversation_followups
                 SET status = 'stopped', stop_reason = ?, next_run_at = NULL
                 WHERE funnel_stage_id = ? AND status = 'active'",
                ['Follow-up desativado na etapa', $stageId]
            );
        }

        Logger::info("StageFollowup: configuração da etapa {$stageId} salva (enabled=" . ($enabled ? 1 : 0) . ", passos=" . count($steps) . ")");

        return self::getStageConfig($stageId);
    }

    /**
     * Validar e normalizar passos. Os tempos são cumulativos (contados a partir
     * da última mensagem enviada), então precisam ser crescentes.
     */
    private static function validateSteps($steps): array
    {
        if (is_string($steps)) {
            $steps = json_decode($steps, true);
        }
        if (!is_array($steps)) {
            return [];
        }
        if (count($steps) > self::MAX_STEPS) {
            throw new \InvalidArgumentException('Máximo de ' . self::MAX_STEPS . ' mensagens por etapa');
        }

        $result = [];
        $previousMinutes = 0;

        foreach (array_values($steps) as $i => $step) {
            $n = $i + 1;
            $message = trim((string)($step['message'] ?? ''));
            $value = (int)($step['delay_value'] ?? 0);
            $unit = (string)($step['delay_unit'] ?? 'hours');

            if ($message === '') {
                throw new \InvalidArgumentException("Mensagem {$n}: o texto é obrigatório");
            }
            if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
                throw new \InvalidArgumentException("Mensagem {$n}: máximo de " . self::MAX_MESSAGE_LENGTH . " caracteres");
            }
            if (!isset(self::UNITS[$unit])) {
                throw new \InvalidArgumentException("Mensagem {$n}: unidade de tempo inválida");
            }
            if ($value < 1) {
                throw new \InvalidArgumentException("Mensagem {$n}: o tempo deve ser maior que zero");
            }

            $minutes = $value * self::UNITS[$unit];
            if ($minutes > self::MAX_DELAY_MINUTES) {
                throw new \InvalidArgumentException("Mensagem {$n}: o tempo máximo é de 90 dias");
            }
            if ($minutes <= $previousMinutes) {
                throw new \InvalidArgumentException("Mensagem {$n}: o tempo deve ser maior que o da mensagem anterior (os tempos contam a partir da sua última mensagem)");
            }
            $previousMinutes = $minutes;

            $result[] = ['delay_value' => $value, 'delay_unit' => $unit, 'message' => $message];
        }

        return $result;
    }

    // =====================================================================
    // ARMAR / CANCELAR
    // =====================================================================

    /**
     * Chamado por ConversationService::sendMessage após gravar uma mensagem nossa.
     * Ela passa a ser a "última mensagem": a sequência recomeça a contar a partir dela.
     */
    public static function onOutgoingMessage(array $conversation, int $messageId, bool $isHuman): void
    {
        if (self::$sending || $messageId <= 0) {
            return;
        }

        $conversationId = (int)$conversation['id'];
        $stageId = (int)($conversation['funnel_stage_id'] ?? 0);

        try {
            $config = $stageId > 0 ? self::getStageConfig($stageId) : null;
        } catch (\PDOException $e) {
            // Migration 159 ainda não executada: recurso simplesmente fica desligado
            if ($e->getCode() === '42S02') {
                return;
            }
            throw $e;
        }

        if (!self::isUsable($config) || !in_array($conversation['status'] ?? 'open', self::OPEN_STATUSES, true)) {
            // Esta é a nova última mensagem e ela não tem follow-up: a sequência anterior perdeu o sentido
            self::stopActive($conversationId, 'Nova mensagem enviada em etapa sem follow-up');
            return;
        }

        if ($config['only_human_messages'] && !$isHuman) {
            // Mensagens de IA/automação não mexem na sequência desta etapa
            return;
        }

        self::arm($conversationId, $stageId, $messageId, date('Y-m-d H:i:s'), $config);
    }

    /**
     * Cancelamento manual pelo agente (vale até a próxima mensagem enviada)
     */
    public static function cancel(int $conversationId, int $userId): array
    {
        $affected = Database::execute(
            "UPDATE conversation_followups
             SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, next_run_at = NULL, stop_reason = NULL
             WHERE conversation_id = ? AND status = 'active'",
            [$userId, date('Y-m-d H:i:s'), $conversationId]
        );

        if ($affected === 0) {
            throw new \InvalidArgumentException('Não há follow-up ativo nesta conversa');
        }

        Logger::info("StageFollowup: conversa {$conversationId} — follow-up cancelado pelo usuário {$userId}");

        return self::getConversationStatus($conversationId);
    }

    /**
     * Desfazer um cancelamento manual, continuando de onde parou
     */
    public static function reactivate(int $conversationId, int $userId): array
    {
        $state = self::getState($conversationId);
        if (!$state || $state['status'] !== 'cancelled') {
            throw new \InvalidArgumentException('Não há follow-up cancelado para reativar');
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation || !in_array($conversation['status'], self::OPEN_STATUSES, true)) {
            throw new \InvalidArgumentException('A conversa não está aberta');
        }
        if (self::contactRepliedSince($state)) {
            throw new \InvalidArgumentException('O cliente já respondeu depois da sua última mensagem');
        }

        $stageId = (int)($conversation['funnel_stage_id'] ?? 0);
        $config = $stageId > 0 ? self::getStageConfig($stageId) : null;
        if (!self::isUsable($config)) {
            throw new \InvalidArgumentException('A etapa atual da conversa não tem follow-up ativo');
        }

        $sameStage = (int)$state['funnel_stage_id'] === $stageId;
        $fromStep = $sameStage ? (int)$state['next_step'] : 0;
        $anchorAt = $sameStage ? $state['anchor_at'] : date('Y-m-d H:i:s');

        if (!isset($config['steps'][$fromStep])) {
            throw new \InvalidArgumentException('Todas as mensagens da sequência já foram enviadas');
        }

        self::arm($conversationId, $stageId, $state['anchor_message_id'] ? (int)$state['anchor_message_id'] : null, $anchorAt, $config, $fromStep, true);

        Logger::info("StageFollowup: conversa {$conversationId} — follow-up reativado pelo usuário {$userId}");

        return self::getConversationStatus($conversationId);
    }

    /**
     * Criar/sobrescrever o estado da conversa como ativo
     */
    private static function arm(int $conversationId, int $stageId, ?int $anchorMessageId, string $anchorAt, array $config, int $fromStep = 0, bool $notBeforeNow = false): void
    {
        $runTs = self::computeRunTs($anchorAt, $config, $fromStep, false);
        if ($notBeforeNow) {
            $runTs = max($runTs, time() + 60);
        }

        Database::execute(
            "INSERT INTO conversation_followups
                (conversation_id, funnel_stage_id, anchor_message_id, anchor_at, next_step, next_run_at, status, stop_reason, attempts, cancelled_by, cancelled_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NULL, 0, NULL, NULL)
             ON DUPLICATE KEY UPDATE
                funnel_stage_id = VALUES(funnel_stage_id),
                anchor_message_id = VALUES(anchor_message_id),
                anchor_at = VALUES(anchor_at),
                next_step = VALUES(next_step),
                next_run_at = VALUES(next_run_at),
                status = 'active',
                stop_reason = NULL,
                attempts = 0,
                cancelled_by = NULL,
                cancelled_at = NULL",
            [$conversationId, $stageId, $anchorMessageId, $anchorAt, $fromStep, date('Y-m-d H:i:s', $runTs)]
        );
    }

    private static function stopActive(int $conversationId, string $reason): void
    {
        Database::execute(
            "UPDATE conversation_followups SET status = 'stopped', stop_reason = ?, next_run_at = NULL
             WHERE conversation_id = ? AND status = 'active'",
            [$reason, $conversationId]
        );
    }

    // =====================================================================
    // RECONCILIAÇÃO (cliente respondeu / conversa fechou / mudou de etapa)
    // =====================================================================

    /**
     * Ajusta um estado ativo à situação atual da conversa.
     * Retorna o estado (possivelmente rearmado) se continuar ativo, ou null se parou.
     */
    private static function reconcile(array $state, ?array $conversation): ?array
    {
        $conversationId = (int)$state['conversation_id'];

        if (!$conversation || !in_array($conversation['status'] ?? '', self::OPEN_STATUSES, true)) {
            self::finish($state, 'stopped', 'Conversa encerrada');
            return null;
        }

        if (self::contactRepliedSince($state)) {
            self::finish($state, 'replied', 'Cliente respondeu');
            return null;
        }

        $currentStageId = (int)($conversation['funnel_stage_id'] ?? 0);
        if ($currentStageId !== (int)$state['funnel_stage_id']) {
            $config = $currentStageId > 0 ? self::getStageConfig($currentStageId) : null;
            if (!self::isUsable($config)) {
                self::finish($state, 'stopped', 'Conversa mudou para uma etapa sem follow-up');
                return null;
            }

            // Recomeça a sequência da nova etapa, contando a partir da mudança de etapa
            $movedAt = self::stageEnteredAt($conversationId, $currentStageId) ?? date('Y-m-d H:i:s');
            $anchorAt = max($movedAt, $state['anchor_at']);
            self::arm($conversationId, $currentStageId, $state['anchor_message_id'] ? (int)$state['anchor_message_id'] : null, $anchorAt, $config);

            $rearmed = self::getState($conversationId);
            if ($rearmed) {
                $rearmed['_rearmed'] = true;
            }
            return $rearmed;
        }

        return $state;
    }

    private static function contactRepliedSince(array $state): bool
    {
        if (!empty($state['anchor_message_id'])) {
            $row = Database::fetch(
                "SELECT id FROM messages WHERE conversation_id = ? AND sender_type = 'contact' AND id > ? LIMIT 1",
                [(int)$state['conversation_id'], (int)$state['anchor_message_id']]
            );
        } else {
            $row = Database::fetch(
                "SELECT id FROM messages WHERE conversation_id = ? AND sender_type = 'contact' AND created_at > ? LIMIT 1",
                [(int)$state['conversation_id'], $state['anchor_at']]
            );
        }
        return !empty($row);
    }

    private static function stageEnteredAt(int $conversationId, int $stageId): ?string
    {
        try {
            $row = Database::fetch(
                "SELECT MAX(created_at) AS moved_at FROM funnel_stage_history WHERE conversation_id = ? AND to_stage_id = ?",
                [$conversationId, $stageId]
            );
            return $row['moved_at'] ?? null;
        } catch (\Throwable $e) {
            return null; // histórico de etapas pode não existir em instalações antigas
        }
    }

    /**
     * Encerrar o estado, sem sobrescrever se ele foi rearmado no meio do caminho
     */
    private static function finish(array $state, string $status, ?string $reason): void
    {
        Database::execute(
            "UPDATE conversation_followups SET status = ?, stop_reason = ?, next_run_at = NULL
             WHERE id = ? AND status = 'active' AND anchor_message_id <=> ? AND next_step = ?",
            [$status, $reason, (int)$state['id'], $state['anchor_message_id'], (int)$state['next_step']]
        );
    }

    // =====================================================================
    // PROCESSAMENTO (cron)
    // =====================================================================

    /**
     * Enviar os passos vencidos. Chamado pelo StageFollowupJob a cada execução do cron.
     *
     * Anti-spam por número de envio: intervalo aleatório entre mensagens e máximo por
     * hora/dia. O que não couber fica na fila (continua vencido) e sai nas próximas
     * execuções, na ordem em que venceu.
     */
    public static function processDue(int $limit = 300, ?int $timeBudgetSeconds = null): array
    {
        $stats = ['due' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'throttled' => 0];
        $deadline = microtime(true) + ($timeBudgetSeconds ?? self::RUN_TIME_BUDGET_SECONDS);
        $limits = self::getLimits();

        $rows = Database::fetchAll(
            "SELECT f.*, c.integration_account_id AS _integration_account_id, c.whatsapp_account_id AS _whatsapp_account_id
             FROM conversation_followups f
             JOIN conversations c ON c.id = f.conversation_id
             WHERE f.status = 'active' AND f.next_run_at IS NOT NULL AND f.next_run_at <= ?
             ORDER BY f.next_run_at ASC
             LIMIT " . (int)$limit,
            [date('Y-m-d H:i:s')]
        );
        $stats['due'] = count($rows);

        $blockedAccounts = []; // números sem vaga nesta execução (limite ou intervalo)
        foreach ($rows as $i => $state) {
            if (microtime(true) >= $deadline) {
                $stats['throttled'] += count($rows) - $i;
                break;
            }

            $accountKey = self::accountKey($state);
            if (isset($blockedAccounts[$accountKey])) {
                $stats['throttled']++;
                continue;
            }

            try {
                $result = self::processOne($state, $accountKey, $limits, $deadline);
            } catch (\Throwable $e) {
                $result = 'failed';
                Logger::error("StageFollowup: erro ao processar conversa {$state['conversation_id']}: " . $e->getMessage());
            }

            if ($result === 'throttled') {
                $blockedAccounts[$accountKey] = true;
            }
            $stats[$result]++;
        }

        return $stats;
    }

    /**
     * Número/conta por onde a mensagem sai (o limite anti-spam é por número)
     */
    private static function accountKey(array $state): string
    {
        if (!empty($state['_integration_account_id'])) {
            return 'i:' . (int)$state['_integration_account_id'];
        }
        if (!empty($state['_whatsapp_account_id'])) {
            return 'w:' . (int)$state['_whatsapp_account_id'];
        }
        return 'default';
    }

    /**
     * Reserva a vez deste número para enviar agora. Respeita máximo por hora/dia e o
     * intervalo aleatório desde o último envio; se a vez chega dentro do tempo desta
     * execução, espera por ela.
     */
    private static function acquireSendSlot(string $accountKey, array $limits, float $deadline): bool
    {
        $now = date('Y-m-d H:i:s');
        $counts = Database::fetch(
            "SELECT COALESCE(SUM(created_at >= ?), 0) AS last_hour, COUNT(*) AS last_day
             FROM conversation_followup_messages
             WHERE account_key = ? AND status = 'sent' AND created_at >= ?",
            [date('Y-m-d H:i:s', time() - 3600), $accountKey, date('Y-m-d H:i:s', time() - 86400)]
        );
        if ((int)$counts['last_hour'] >= $limits['max_per_hour'] || (int)$counts['last_day'] >= $limits['max_per_day']) {
            return false;
        }

        Database::execute(
            "INSERT IGNORE INTO followup_send_pacing (account_key, next_allowed_at, version) VALUES (?, ?, 0)",
            [$accountKey, '2000-01-01 00:00:00']
        );
        $row = Database::fetch("SELECT next_allowed_at, version FROM followup_send_pacing WHERE account_key = ?", [$accountKey]);

        $wait = strtotime($row['next_allowed_at']) - microtime(true);
        if ($wait > 0) {
            if (microtime(true) + $wait > $deadline) {
                return false;
            }
            usleep((int)ceil($wait * 1000000));
        }

        // Próxima vez deste número: agora (arredondado para cima) + intervalo aleatório.
        // Atômico pela versão: se outra execução do cron pegou a vez antes, esta desiste.
        $interval = random_int($limits['min_interval_seconds'], $limits['max_interval_seconds']);
        return Database::execute(
            "UPDATE followup_send_pacing SET next_allowed_at = ?, version = version + 1 WHERE account_key = ? AND version = ?",
            [date('Y-m-d H:i:s', $interval > 0 ? (int)ceil(microtime(true)) + $interval : time()), $accountKey, (int)$row['version']]
        ) > 0;
    }

    // =====================================================================
    // LIMITES ANTI-SPAM (valem para todas as etapas)
    // =====================================================================

    public static function getLimits(): array
    {
        $limits = self::DEFAULT_LIMITS;
        foreach ($limits as $key => $default) {
            $value = SettingService::get('followup_' . $key, null);
            if ($value !== null && $value !== '' && is_numeric($value)) {
                $limits[$key] = (int)$value;
            }
        }
        $limits['max_interval_seconds'] = max($limits['max_interval_seconds'], $limits['min_interval_seconds']);
        return $limits;
    }

    public static function saveLimits(array $data): array
    {
        $min = (int)($data['min_interval_seconds'] ?? self::DEFAULT_LIMITS['min_interval_seconds']);
        $max = (int)($data['max_interval_seconds'] ?? self::DEFAULT_LIMITS['max_interval_seconds']);
        $perHour = (int)($data['max_per_hour'] ?? self::DEFAULT_LIMITS['max_per_hour']);
        $perDay = (int)($data['max_per_day'] ?? self::DEFAULT_LIMITS['max_per_day']);

        if ($min < 0 || $max > 3600) {
            throw new \InvalidArgumentException('Intervalo entre mensagens deve ficar entre 0 e 3600 segundos');
        }
        if ($max < $min) {
            throw new \InvalidArgumentException('O intervalo máximo deve ser maior ou igual ao mínimo');
        }
        if ($perHour < 1 || $perHour > 1000) {
            throw new \InvalidArgumentException('Máximo por hora deve ficar entre 1 e 1000');
        }
        if ($perDay < 1 || $perDay > 20000) {
            throw new \InvalidArgumentException('Máximo por dia deve ficar entre 1 e 20000');
        }
        if ($perDay < $perHour) {
            throw new \InvalidArgumentException('O máximo por dia não pode ser menor que o máximo por hora');
        }

        foreach (['min_interval_seconds' => $min, 'max_interval_seconds' => $max, 'max_per_hour' => $perHour, 'max_per_day' => $perDay] as $key => $value) {
            SettingService::set('followup_' . $key, (string)$value, 'integer', 'followup');
        }

        return self::getLimits();
    }

    private static function processOne(array $state, string $accountKey, array $limits, float $deadline): string
    {
        $dueAt = $state['next_run_at'];
        $conversationId = (int)$state['conversation_id'];

        // Reservar o passo: se outra execução do cron já pegou, pula
        $claimed = Database::execute(
            "UPDATE conversation_followups SET next_run_at = ?
             WHERE id = ? AND status = 'active' AND anchor_message_id <=> ? AND next_step = ? AND next_run_at = ?",
            [date('Y-m-d H:i:s', time() + self::CLAIM_MINUTES * 60), (int)$state['id'], $state['anchor_message_id'], (int)$state['next_step'], $state['next_run_at']]
        );
        if ($claimed === 0) {
            return 'skipped';
        }

        $conversation = Conversation::find($conversationId);
        $state = self::reconcile($state, $conversation);
        if ($state === null || !empty($state['_rearmed'])) {
            return 'skipped';
        }

        $config = self::getStageConfig((int)$state['funnel_stage_id']);
        $stepIndex = (int)$state['next_step'];
        $step = $config['steps'][$stepIndex] ?? null;

        if (!$config['enabled'] || $step === null) {
            self::finish($state, $config['enabled'] ? 'completed' : 'stopped', $config['enabled'] ? null : 'Follow-up desativado na etapa');
            return 'skipped';
        }

        if ($config['only_business_hours']) {
            $businessTs = self::nextBusinessTs(time());
            if ($businessTs > time() + 60) {
                self::reschedule($state, $businessTs, (int)$state['attempts']);
                return 'skipped';
            }
        }

        // Anti-spam: espera a vez deste número. Sem vaga agora → volta para a fila
        // mantendo o horário original (continua na frente dos que venceram depois)
        if (!self::acquireSendSlot($accountKey, $limits, $deadline)) {
            self::reschedule($state, strtotime($dueAt), (int)$state['attempts']);
            return 'throttled';
        }

        $content = AutomationService::processVariablesPublic($step['message'], $conversation);

        self::$sending = true;
        try {
            // senderId 0 = sistema: não dispara a lógica de "agente humano assumiu" (remover IA, reatribuir...)
            $messageId = ConversationService::sendMessage($conversationId, $content, 'agent', 0, [], null, null, null, null, true);
        } catch (\Throwable $e) {
            self::$sending = false;
            self::handleSendFailure($state, $stepIndex, $e->getMessage());
            return 'failed';
        }
        self::$sending = false;

        self::logStep($state, $stepIndex, $messageId ? (int)$messageId : null, 'sent', null, $accountKey);

        $nextIndex = $stepIndex + 1;
        if (isset($config['steps'][$nextIndex])) {
            Database::execute(
                "UPDATE conversation_followups SET next_step = ?, next_run_at = ?, attempts = 0
                 WHERE id = ? AND status = 'active' AND anchor_message_id <=> ? AND next_step = ?",
                [$nextIndex, date('Y-m-d H:i:s', self::computeRunTs($state['anchor_at'], $config, $nextIndex, true)), (int)$state['id'], $state['anchor_message_id'], $stepIndex]
            );
        } else {
            Database::execute(
                "UPDATE conversation_followups SET next_step = ?, next_run_at = NULL, status = 'completed', attempts = 0
                 WHERE id = ? AND status = 'active' AND anchor_message_id <=> ? AND next_step = ?",
                [$nextIndex, (int)$state['id'], $state['anchor_message_id'], $stepIndex]
            );
        }

        Logger::info("StageFollowup: conversa {$conversationId} — passo " . ($stepIndex + 1) . "/" . count($config['steps']) . " enviado (msg={$messageId})");

        return 'sent';
    }

    private static function handleSendFailure(array $state, int $stepIndex, string $error): void
    {
        self::logStep($state, $stepIndex, null, 'failed', $error);

        $attempts = (int)$state['attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            self::finish($state, 'stopped', mb_substr('Falha ao enviar: ' . $error, 0, 255));
        } else {
            self::reschedule($state, time() + self::RETRY_MINUTES * 60, $attempts);
        }

        Logger::error("StageFollowup: conversa {$state['conversation_id']} — falha no passo " . ($stepIndex + 1) . " (tentativa {$attempts}): {$error}");
    }

    private static function reschedule(array $state, int $runTs, int $attempts): void
    {
        Database::execute(
            "UPDATE conversation_followups SET next_run_at = ?, attempts = ?
             WHERE id = ? AND status = 'active' AND anchor_message_id <=> ? AND next_step = ?",
            [date('Y-m-d H:i:s', $runTs), $attempts, (int)$state['id'], $state['anchor_message_id'], (int)$state['next_step']]
        );
    }

    private static function logStep(array $state, int $stepIndex, ?int $messageId, string $status, ?string $error, ?string $accountKey = null): void
    {
        try {
            Database::insert(
                "INSERT INTO conversation_followup_messages
                    (conversation_followup_id, conversation_id, account_key, funnel_stage_id, anchor_message_id, step_index, message_id, status, error_message)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [(int)$state['id'], (int)$state['conversation_id'], $accountKey, $state['funnel_stage_id'], $state['anchor_message_id'], $stepIndex, $messageId, $status, $error]
            );
        } catch (\Throwable $e) {
            Logger::error("StageFollowup: erro ao registrar histórico: " . $e->getMessage());
        }
    }

    // =====================================================================
    // STATUS PARA O CHAT
    // =====================================================================

    /**
     * Situação do follow-up da conversa, no formato usado pelo aviso no chat
     */
    public static function getConversationStatus(int $conversationId): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        $currentStageId = (int)($conversation['funnel_stage_id'] ?? 0);
        $stageConfig = $currentStageId > 0 ? self::getStageConfig($currentStageId) : null;

        $state = self::getState($conversationId);
        if ($state && $state['status'] === 'active') {
            $reconciled = self::reconcile($state, $conversation);
            $state = $reconciled ?? self::getState($conversationId);
        } elseif ($state && $state['status'] === 'cancelled' && self::contactRepliedSince($state)) {
            // Cliente respondeu depois do cancelamento: não há mais o que mostrar/reativar
            Database::execute(
                "UPDATE conversation_followups SET status = 'replied', stop_reason = 'Cliente respondeu' WHERE id = ? AND status = 'cancelled'",
                [(int)$state['id']]
            );
            $state = self::getState($conversationId);
        }

        $result = [
            'stage_enabled' => self::isUsable($stageConfig),
            'followup' => null,
        ];

        if (!$state) {
            return $result;
        }

        $config = $state['funnel_stage_id'] ? self::getStageConfig((int)$state['funnel_stage_id']) : ['steps' => []];
        $nextStep = (int)$state['next_step'];

        $sentAt = [];
        $sentRows = Database::fetchAll(
            "SELECT step_index, created_at FROM conversation_followup_messages
             WHERE conversation_followup_id = ? AND anchor_message_id <=> ? AND status = 'sent'",
            [(int)$state['id'], $state['anchor_message_id']]
        );
        foreach ($sentRows as $row) {
            $sentAt[(int)$row['step_index']] = self::iso($row['created_at']);
        }

        $steps = [];
        foreach ($config['steps'] as $i => $step) {
            $steps[] = [
                'index' => $i,
                'delay_label' => self::delayLabel($step),
                'message' => $step['message'],
                'sent_at' => $sentAt[$i] ?? null,
            ];
        }

        $cancelledByName = null;
        if (!empty($state['cancelled_by'])) {
            $user = \App\Models\User::find((int)$state['cancelled_by']);
            $cancelledByName = $user['name'] ?? null;
        }

        $result['followup'] = [
            'status' => $state['status'],
            'stop_reason' => $state['stop_reason'],
            'next_step' => $nextStep,
            'total_steps' => count($config['steps']),
            'next_run_at' => $state['status'] === 'active' ? self::iso($state['next_run_at']) : null,
            'next_message' => $state['status'] === 'active' ? ($config['steps'][$nextStep]['message'] ?? null) : null,
            'anchor_at' => self::iso($state['anchor_at']),
            'steps' => $steps,
            'cancelled_by_name' => $cancelledByName,
            'cancelled_at' => self::iso($state['cancelled_at']),
            'can_reactivate' => $state['status'] === 'cancelled'
                && self::isUsable($stageConfig)
                && isset($stageConfig['steps'][(int)$state['funnel_stage_id'] === $currentStageId ? $nextStep : 0]),
        ];

        return $result;
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    private static function getState(int $conversationId): ?array
    {
        return Database::fetch("SELECT * FROM conversation_followups WHERE conversation_id = ?", [$conversationId]);
    }

    private static function isUsable(?array $config): bool
    {
        return $config !== null && $config['enabled'] && !empty($config['steps']);
    }

    private static function stepDelayMinutes(array $step): int
    {
        return (int)$step['delay_value'] * (self::UNITS[$step['delay_unit']] ?? 60);
    }

    /**
     * Quando o passo deve sair. Os tempos contam a partir da âncora (última mensagem).
     * Após um envio, se o cron atrasou, preserva o intervalo entre as mensagens em vez
     * de disparar duas seguidas.
     */
    private static function computeRunTs(string $anchorAt, array $config, int $stepIndex, bool $afterSend): int
    {
        $steps = $config['steps'];
        $delay = self::stepDelayMinutes($steps[$stepIndex]);
        $runTs = strtotime($anchorAt) + $delay * 60;

        if ($afterSend && $stepIndex > 0) {
            $gap = $delay - self::stepDelayMinutes($steps[$stepIndex - 1]);
            $runTs = max($runTs, time() + $gap * 60);
        }

        if (!empty($config['only_business_hours'])) {
            $runTs = self::nextBusinessTs($runTs);
        }

        return $runTs;
    }

    /**
     * Próximo instante dentro do horário de atendimento (Configurações > horário de trabalho)
     */
    private static function nextBusinessTs(int $ts): int
    {
        try {
            $from = (new \DateTime())->setTimestamp($ts);
            return \App\Helpers\WorkingHoursCalculator::getNextWorkingTime($from)->getTimestamp();
        } catch (\Throwable $e) {
            return $ts;
        }
    }

    public static function delayLabel(array $step): string
    {
        $value = (int)$step['delay_value'];
        $labels = [
            'minutes' => ['minuto', 'minutos'],
            'hours' => ['hora', 'horas'],
            'days' => ['dia', 'dias'],
        ];
        $unit = $labels[$step['delay_unit']] ?? $labels['hours'];
        return $value . ' ' . ($value === 1 ? $unit[0] : $unit[1]);
    }

    private static function iso(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }
        $ts = strtotime($datetime);
        return $ts ? date('c', $ts) : null;
    }

    private static function toBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
