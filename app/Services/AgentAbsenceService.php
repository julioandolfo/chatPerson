<?php
/**
 * AgentAbsenceService
 * Mensagem de ausência do agente: "Estou ausente, retorno às 11:00".
 *
 * O agente cadastra períodos (início/fim), a mensagem e quanto tempo esperar depois da
 * mensagem do cliente. Durante o período, quando um cliente escreve numa conversa atribuída
 * a ele, a mensagem sai UMA vez por conversa (ou de novo só depois de X horas, se configurado).
 *
 * Envio: no recebimento grava um envio pendente (agent_absence_replies) e dispara um processo
 * em segundo plano que espera o atraso e envia. Se não der para disparar o processo, o cron
 * (AgentAbsenceJob) envia na próxima execução.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Conversation;

class AgentAbsenceService
{
    public const DEFAULT_MESSAGE = "Olá, {{contact.name}}! No momento estou ausente e retorno {{absence.return}}. Assim que voltar, respondo sua mensagem. 😊";

    public const MAX_DELAY_SECONDS = 3600;
    /** Até este atraso o envio sai por processo em segundo plano (preciso); acima, pelo cron */
    private const WORKER_MAX_DELAY_SECONDS = 600;
    private const MAX_DAYS = 366;
    private const MAX_ATTEMPTS = 3;
    private const RETRY_SECONDS = 120;
    private const STALE_SENDING_MINUTES = 10;

    // ---------------------------------------------------------------------------------
    // Cadastro (perfil do agente)
    // ---------------------------------------------------------------------------------

    /** Ausências do usuário: as que ainda não acabaram e as encerradas nos últimos 30 dias */
    public static function listForUser(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM agent_absence_replies r WHERE r.absence_id = a.id AND r.status = 'sent') AS sent_count,
                    (SELECT COUNT(*) FROM agent_absence_replies r WHERE r.absence_id = a.id AND r.status IN ('pending', 'sending')) AS pending_count
             FROM agent_absences a
             WHERE a.user_id = ? AND a.ends_at > NOW() - INTERVAL 30 DAY
             ORDER BY a.starts_at DESC
             LIMIT 50",
            [$userId]
        );
        $list = array_map([self::class, 'present'], $rows);

        // Ativa primeiro, depois as próximas (da mais perto para a mais longe), depois as encerradas
        $order = ['active' => 0, 'scheduled' => 1, 'paused' => 2, 'ended' => 3];
        usort($list, function ($a, $b) use ($order) {
            $byState = $order[$a['state']] <=> $order[$b['state']];
            if ($byState !== 0) {
                return $byState;
            }
            return $a['state'] === 'ended' ? strcmp($b['starts_at'], $a['starts_at']) : strcmp($a['starts_at'], $b['starts_at']);
        });
        return $list;
    }

    public static function getActive(int $userId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM agent_absences
             WHERE user_id = ? AND enabled = 1 AND starts_at <= NOW() AND ends_at > NOW()
             ORDER BY starts_at ASC LIMIT 1",
            [$userId]
        );
        return $row ?: null;
    }

    public static function save(int $userId, array $data, ?int $id = null): array
    {
        $existing = null;
        if ($id !== null) {
            $existing = self::findOwned($id, $userId);
        }

        $startsTs = self::parseDateTime($data['starts_at'] ?? null);
        $endsTs = self::parseDateTime($data['ends_at'] ?? null);
        if ($startsTs === null || $endsTs === null) {
            throw new \InvalidArgumentException('Informe a data e a hora de início e de fim da ausência.');
        }
        if ($endsTs <= $startsTs) {
            throw new \InvalidArgumentException('O fim da ausência deve ser depois do início.');
        }
        if ($endsTs <= time()) {
            throw new \InvalidArgumentException('O fim da ausência já passou.');
        }
        if ($endsTs - $startsTs > self::MAX_DAYS * 86400) {
            throw new \InvalidArgumentException('A ausência pode durar no máximo 1 ano.');
        }

        $message = trim((string)($data['message'] ?? ''));
        if ($message === '') {
            throw new \InvalidArgumentException('Escreva a mensagem de ausência.');
        }
        if (mb_strlen($message) > 4000) {
            throw new \InvalidArgumentException('A mensagem pode ter no máximo 4000 caracteres.');
        }

        $delay = (int)($data['delay_seconds'] ?? 10);
        if ($delay < 0 || $delay > self::MAX_DELAY_SECONDS) {
            throw new \InvalidArgumentException('O tempo de espera deve ficar entre 0 segundos e 1 hora.');
        }
        $resend = (int)($data['resend_after_hours'] ?? 0);
        if ($resend < 0 || $resend > 720) {
            throw new \InvalidArgumentException('O reenvio deve ficar entre 0 (não reenviar) e 720 horas.');
        }
        $enabled = array_key_exists('enabled', $data) ? filter_var($data['enabled'], FILTER_VALIDATE_BOOLEAN) : true;

        $startsAt = date('Y-m-d H:i:s', $startsTs);
        $endsAt = date('Y-m-d H:i:s', $endsTs);

        if ($enabled) {
            $overlap = Database::fetch(
                "SELECT starts_at, ends_at FROM agent_absences
                 WHERE user_id = ? AND enabled = 1 AND starts_at < ? AND ends_at > ? AND id <> ?
                 LIMIT 1",
                [$userId, $endsAt, $startsAt, $id ?? 0]
            );
            if ($overlap) {
                throw new \InvalidArgumentException(
                    'Já existe uma ausência neste período (' . self::periodLabel($overlap['starts_at'], $overlap['ends_at']) . ').'
                );
            }
        }

        if ($existing) {
            Database::execute(
                "UPDATE agent_absences
                 SET starts_at = ?, ends_at = ?, message = ?, delay_seconds = ?, resend_after_hours = ?, enabled = ?
                 WHERE id = ? AND user_id = ?",
                [$startsAt, $endsAt, $message, $delay, $resend, $enabled ? 1 : 0, $id, $userId]
            );
            if (!$enabled) {
                self::cancelPending($id, 'Ausência desativada');
            }
        } else {
            $id = Database::insert(
                "INSERT INTO agent_absences (user_id, starts_at, ends_at, message, delay_seconds, resend_after_hours, enabled, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $startsAt, $endsAt, $message, $delay, $resend, $enabled ? 1 : 0, $userId]
            );
        }

        return self::present(self::findOwned($id, $userId) + ['sent_count' => null, 'pending_count' => null]);
    }

    /** Voltou antes: encerra a ausência agora */
    public static function endNow(int $id, int $userId): array
    {
        $absence = self::findOwned($id, $userId);
        if (strtotime($absence['starts_at']) > time()) {
            throw new \InvalidArgumentException('Esta ausência ainda não começou. Para cancelá-la, exclua.');
        }
        if (strtotime($absence['ends_at']) <= time()) {
            throw new \InvalidArgumentException('Esta ausência já terminou.');
        }
        Database::execute("UPDATE agent_absences SET ends_at = ? WHERE id = ? AND user_id = ?", [date('Y-m-d H:i:s'), $id, $userId]);
        self::cancelPending($id, 'Ausência encerrada pelo agente');
        return self::present(self::findOwned($id, $userId) + ['sent_count' => null, 'pending_count' => null]);
    }

    public static function delete(int $id, int $userId): void
    {
        self::findOwned($id, $userId);
        self::cancelPending($id, 'Ausência excluída');
        Database::execute("DELETE FROM agent_absences WHERE id = ? AND user_id = ?", [$id, $userId]);
    }

    /** Para onde a mensagem foi (ou vai) */
    public static function listReplies(int $id, int $userId): array
    {
        self::findOwned($id, $userId);
        $rows = Database::fetchAll(
            "SELECT r.id, r.conversation_id, r.status, r.reason, r.run_at, r.updated_at,
                    ct.name AS contact_name, ct.last_name AS contact_last_name, ct.phone AS contact_phone
             FROM agent_absence_replies r
             LEFT JOIN conversations c ON c.id = r.conversation_id
             LEFT JOIN contacts ct ON ct.id = c.contact_id
             WHERE r.absence_id = ?
             ORDER BY r.id DESC
             LIMIT 200",
            [$id]
        );
        return array_map(function ($row) {
            return [
                'conversation_id' => (int)$row['conversation_id'],
                'contact_name' => trim(($row['contact_name'] ?? '') . ' ' . ($row['contact_last_name'] ?? '')) ?: ($row['contact_phone'] ?? 'Contato'),
                'status' => $row['status'],
                'reason' => $row['reason'],
                'at' => date('c', strtotime($row['status'] === 'pending' ? $row['run_at'] : $row['updated_at'])),
            ];
        }, $rows);
    }

    /** Texto final da mensagem para a conversa (variáveis substituídas) */
    public static function renderMessage(string $message, string $endsAt, $conversation): string
    {
        $endTs = strtotime($endsAt);
        $time = date('H:i', $endTs);
        $day = date('Y-m-d', $endTs);
        if ($day === date('Y-m-d')) {
            $return = 'hoje às ' . $time;
        } elseif ($day === date('Y-m-d', strtotime('+1 day'))) {
            $return = 'amanhã às ' . $time;
        } else {
            $return = 'em ' . date('d/m', $endTs) . ' às ' . $time;
        }

        $text = strtr($message, [
            '{{absence.return}}' => $return,
            '{{absence.return_time}}' => $time,
            '{{absence.return_date}}' => date('d/m/Y', $endTs),
        ]);
        return AutomationService::processVariablesPublic($text, $conversation);
    }

    // ---------------------------------------------------------------------------------
    // Recebimento e envio
    // ---------------------------------------------------------------------------------

    /**
     * Chamado quando chega mensagem do cliente (todos os canais).
     * Agenda a mensagem de ausência se o agente da conversa estiver ausente agora.
     */
    public static function onIncomingContactMessage(int $conversationId, int $messageId): void
    {
        try {
            $conversation = Database::fetch("SELECT id, agent_id FROM conversations WHERE id = ?", [$conversationId]);
            if (!$conversation || empty($conversation['agent_id'])) {
                return;
            }
            $absence = self::getActive((int)$conversation['agent_id']);
            if (!$absence || self::aiIsActive($conversationId)) {
                return;
            }
            $absenceId = (int)$absence['id'];

            $rows = Database::fetchAll(
                "SELECT round_no, status, updated_at FROM agent_absence_replies
                 WHERE absence_id = ? AND conversation_id = ? ORDER BY round_no DESC",
                [$absenceId, $conversationId]
            );
            foreach ($rows as $row) {
                if (in_array($row['status'], ['pending', 'sending'], true)) {
                    return; // já vai sair
                }
            }
            foreach ($rows as $row) {
                if (in_array($row['status'], ['sent', 'answered'], true)) {
                    // Já enviada (ou o agente respondeu): só repete se configurado e passou o tempo
                    $hours = (int)$absence['resend_after_hours'];
                    if ($hours <= 0 || strtotime($row['updated_at']) > time() - $hours * 3600) {
                        return;
                    }
                    break;
                }
            }
            $round = $rows ? (int)$rows[0]['round_no'] + 1 : 0;

            $delay = max(0, (int)$absence['delay_seconds']);
            try {
                $replyId = Database::insert(
                    "INSERT INTO agent_absence_replies (absence_id, conversation_id, round_no, trigger_message_id, status, run_at)
                     VALUES (?, ?, ?, ?, 'pending', ?)",
                    [$absenceId, $conversationId, $round, $messageId ?: null, date('Y-m-d H:i:s', time() + $delay)]
                );
            } catch (\PDOException $e) {
                if ($e->getCode() === '23000') {
                    return; // outra mensagem do cliente chegou junto e já agendou
                }
                throw $e;
            }

            Logger::info("AgentAbsence: conversa {$conversationId} — mensagem de ausência agendada para {$delay}s (envio #{$replyId})");

            if ($delay <= self::WORKER_MAX_DELAY_SECONDS && !self::spawnWorker($replyId)) {
                Logger::info("AgentAbsence: sem processo em segundo plano; envio #{$replyId} sai pelo cron");
            }
        } catch (\PDOException $e) {
            // Tabelas ainda não criadas (migration 163 pendente): não atrapalhar o recebimento
            if (!in_array($e->getCode(), ['42S02', '42S22'], true)) {
                Logger::error('AgentAbsence::onIncomingContactMessage: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            Logger::error('AgentAbsence::onIncomingContactMessage: ' . $e->getMessage());
        }
    }

    /** Processo em segundo plano (scripts/absence-reply-worker.php): espera o atraso e envia */
    public static function runWorker(int $replyId): void
    {
        $reply = Database::fetch("SELECT status, run_at FROM agent_absence_replies WHERE id = ?", [$replyId]);
        if (!$reply || $reply['status'] !== 'pending') {
            return;
        }
        $wait = strtotime($reply['run_at']) - time();
        if ($wait > self::WORKER_MAX_DELAY_SECONDS + 60) {
            return; // longo demais para esperar aqui: fica para o cron
        }
        if ($wait > 0) {
            sleep($wait);
        }
        self::processReply($replyId);
    }

    /** Cron: envia os pendentes vencidos (se o processo em segundo plano não enviou) */
    public static function processDue(int $limit = 100, int $timeBudgetSeconds = 20): array
    {
        $stats = ['due' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        // Processo que morreu no meio do envio: não reenvia (poderia duplicar)
        Database::execute(
            "UPDATE agent_absence_replies SET status = 'failed', reason = 'Envio interrompido'
             WHERE status = 'sending' AND updated_at < NOW() - INTERVAL " . self::STALE_SENDING_MINUTES . " MINUTE"
        );

        $rows = Database::fetchAll(
            "SELECT id FROM agent_absence_replies WHERE status = 'pending' AND run_at <= NOW() ORDER BY run_at ASC LIMIT " . (int)$limit
        );
        $deadline = microtime(true) + $timeBudgetSeconds;
        foreach ($rows as $row) {
            if (microtime(true) > $deadline) {
                break;
            }
            $stats['due']++;
            $result = self::processReply((int)$row['id']);
            if ($result === 'sent') {
                $stats['sent']++;
            } elseif ($result === 'failed') {
                $stats['failed']++;
            } else {
                $stats['skipped']++;
            }
        }
        return $stats;
    }

    /**
     * Envia um pendente. Confere de novo tudo no momento do envio.
     * @return string sent | answered | cancelled | retry | failed | skipped
     */
    public static function processReply(int $replyId): string
    {
        $claimed = Database::execute(
            "UPDATE agent_absence_replies SET status = 'sending', attempts = attempts + 1 WHERE id = ? AND status = 'pending'",
            [$replyId]
        );
        if ($claimed === 0) {
            return 'skipped'; // outro processo pegou
        }

        $reply = Database::fetch("SELECT * FROM agent_absence_replies WHERE id = ?", [$replyId]);
        $absence = Database::fetch("SELECT * FROM agent_absences WHERE id = ?", [(int)$reply['absence_id']]);
        $conversationId = (int)$reply['conversation_id'];
        $conversation = Conversation::find($conversationId);

        $finish = function (string $status, ?string $reason, ?int $messageId = null) use ($replyId): string {
            Database::execute(
                "UPDATE agent_absence_replies SET status = ?, reason = ?, sent_message_id = ? WHERE id = ?",
                [$status, $reason, $messageId, $replyId]
            );
            return $status;
        };

        if (!$absence || empty($absence['enabled'])) {
            return $finish('cancelled', 'Ausência excluída ou desativada');
        }
        if (strtotime($absence['ends_at']) <= time()) {
            return $finish('cancelled', 'A ausência terminou antes do envio');
        }
        if (!$conversation) {
            return $finish('cancelled', 'Conversa não encontrada');
        }
        if (in_array($conversation['status'] ?? '', ['closed', 'resolved'], true)) {
            return $finish('cancelled', 'Conversa encerrada');
        }
        if ((int)($conversation['agent_id'] ?? 0) !== (int)$absence['user_id']) {
            return $finish('cancelled', 'Conversa transferida para outro agente');
        }
        if (self::aiIsActive($conversationId)) {
            return $finish('cancelled', 'Agente de IA está atendendo a conversa');
        }
        if (self::answeredAfter($conversationId, (int)($reply['trigger_message_id'] ?? 0))) {
            return $finish('answered', 'A conversa foi respondida antes do envio');
        }

        $content = self::renderMessage($absence['message'], $absence['ends_at'], $conversation);
        $before = Database::fetch(
            "SELECT first_response_at, first_human_response_at, sla_warning_sent FROM conversations WHERE id = ?",
            [$conversationId]
        );

        try {
            // senderId 0 = sistema: não reatribui, não desliga IA; skipAutomations: não liga follow-up/automações
            $messageId = ConversationService::sendMessage($conversationId, $content, 'agent', 0, [], null, null, null, null, true);
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 250);
            if ((int)$reply['attempts'] < self::MAX_ATTEMPTS) {
                Database::execute(
                    "UPDATE agent_absence_replies SET status = 'pending', reason = ?, run_at = ? WHERE id = ?",
                    [$error, date('Y-m-d H:i:s', time() + self::RETRY_SECONDS), $replyId]
                );
                Logger::error("AgentAbsence: falha no envio #{$replyId} (tentativa {$reply['attempts']}): {$error}");
                return 'retry';
            }
            Logger::error("AgentAbsence: envio #{$replyId} desistido após {$reply['attempts']} tentativas: {$error}");
            return $finish('failed', $error);
        }

        // Resposta automática não é primeira resposta do agente (não maquia o SLA)
        if ($before && (empty($before['first_response_at']) || empty($before['first_human_response_at']))) {
            Database::execute(
                "UPDATE conversations SET first_response_at = ?, first_human_response_at = ?, sla_warning_sent = ? WHERE id = ?",
                [$before['first_response_at'], $before['first_human_response_at'], $before['sla_warning_sent'], $conversationId]
            );
        }

        Logger::info("AgentAbsence: conversa {$conversationId} — mensagem de ausência enviada (msg={$messageId})");
        return $finish('sent', null, $messageId ? (int)$messageId : null);
    }

    // ---------------------------------------------------------------------------------
    // Identificar as mensagens automáticas de ausência (não contam como resposta do agente)
    // ---------------------------------------------------------------------------------

    /** As tabelas da ausência existem? (migration 163) */
    public static function tableReady(): bool
    {
        static $ready = null;
        if ($ready === null) {
            try {
                $ready = (bool)Database::fetch("SHOW TABLES LIKE 'agent_absence_replies'");
            } catch (\Throwable $e) {
                $ready = false;
            }
        }
        return $ready;
    }

    /** Trecho SQL "e a mensagem {alias} não é resposta automática de ausência" */
    public static function notAutoReplySql(string $alias = 'm'): string
    {
        if (!self::tableReady()) {
            return '';
        }
        return " AND NOT EXISTS (SELECT 1 FROM agent_absence_replies ar WHERE ar.sent_message_id = {$alias}.id)";
    }

    /**
     * Marca auto_reply = true nas mensagens que são a mensagem de ausência.
     * Inclui a que está saindo agora (o id só é gravado depois que o envio termina).
     */
    public static function flagAutoReplies(array $messages): array
    {
        $candidates = [];
        foreach ($messages as $i => $msg) {
            $messages[$i]['auto_reply'] = false;
            if (($msg['sender_type'] ?? '') === 'agent' && (int)($msg['sender_id'] ?? 0) === 0 && !empty($msg['id'])) {
                $candidates[$i] = (int)$msg['id'];
            }
        }
        if (!$candidates || !self::tableReady()) {
            return $messages;
        }

        try {
            $ids = array_values(array_unique($candidates));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sent = array_map('intval', array_column(Database::fetchAll(
                "SELECT sent_message_id FROM agent_absence_replies WHERE sent_message_id IN ($placeholders)",
                $ids
            ), 'sent_message_id'));

            $sendingConversations = [];
            foreach ($candidates as $i => $id) {
                $convId = (int)($messages[$i]['conversation_id'] ?? 0);
                if (!in_array($id, $sent, true) && $convId > 0) {
                    $sendingConversations[$convId] = $convId;
                }
            }
            $sending = [];
            if ($sendingConversations) {
                $convIds = array_values($sendingConversations);
                $placeholders = implode(',', array_fill(0, count($convIds), '?'));
                $sending = array_map('intval', array_column(Database::fetchAll(
                    "SELECT conversation_id FROM agent_absence_replies WHERE status = 'sending' AND conversation_id IN ($placeholders)",
                    $convIds
                ), 'conversation_id'));
            }

            foreach ($candidates as $i => $id) {
                $messages[$i]['auto_reply'] = in_array($id, $sent, true)
                    || in_array((int)($messages[$i]['conversation_id'] ?? 0), $sending, true);
            }
        } catch (\Throwable $e) {
            // sem marcação: a mensagem aparece como resposta comum
        }
        return $messages;
    }

    // ---------------------------------------------------------------------------------
    // Internos
    // ---------------------------------------------------------------------------------

    private static function findOwned(int $id, int $userId): array
    {
        $row = Database::fetch("SELECT * FROM agent_absences WHERE id = ? AND user_id = ?", [$id, $userId]);
        if (!$row) {
            throw new \InvalidArgumentException('Ausência não encontrada.');
        }
        return $row;
    }

    private static function cancelPending(int $absenceId, string $reason): void
    {
        Database::execute(
            "UPDATE agent_absence_replies SET status = 'cancelled', reason = ? WHERE absence_id = ? AND status = 'pending'",
            [$reason, $absenceId]
        );
    }

    /** Alguém (agente, IA, automação) respondeu depois da mensagem do cliente? */
    private static function answeredAfter(int $conversationId, int $afterMessageId): bool
    {
        return (bool)Database::fetch(
            "SELECT 1 FROM messages
             WHERE conversation_id = ? AND id > ?
               AND sender_type NOT IN ('contact', 'system')
               AND COALESCE(message_type, 'text') NOT IN ('note', 'system')
             LIMIT 1",
            [$conversationId, $afterMessageId]
        );
    }

    private static function aiIsActive(int $conversationId): bool
    {
        try {
            $ai = \App\Models\AIConversation::getByConversationId($conversationId);
            return $ai && ($ai['status'] ?? '') === 'active';
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function spawnWorker(int $replyId): bool
    {
        if (PHP_OS_FAMILY === 'Windows' || !self::canExec()) {
            return false;
        }
        $php = self::phpBinary();
        $script = dirname(__DIR__, 2) . '/scripts/absence-reply-worker.php';
        if ($php === null || !is_file($script)) {
            return false;
        }

        $command = escapeshellarg($php) . ' -d variables_order=EGPCS ' . escapeshellarg($script) . ' ' . (int)$replyId
            . ' > /dev/null 2>&1 &';
        @exec($command, $output, $exitCode);
        return $exitCode === 0;
    }

    private static function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('exec', $disabled, true);
    }

    private static function phpBinary(): ?string
    {
        $candidates = [];
        // No Apache, PHP_BINARY não é o PHP de linha de comando
        if (PHP_BINARY !== '' && preg_match('/^php[\d.]*$/', basename(PHP_BINARY))) {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = PHP_BINDIR . '/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';
        foreach ($candidates as $path) {
            if (@is_file($path) && @is_executable($path)) {
                return $path;
            }
        }
        return null;
    }

    private static function parseDateTime($value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $dt = \DateTime::createFromFormat('!' . $format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->getTimestamp();
            }
        }
        return null;
    }

    private static function periodLabel(string $startsAt, string $endsAt): string
    {
        $s = strtotime($startsAt);
        $e = strtotime($endsAt);
        return date('d/m H:i', $s) . ' – ' . (date('Y-m-d', $s) === date('Y-m-d', $e) ? date('H:i', $e) : date('d/m H:i', $e));
    }

    private static function present(array $row): array
    {
        $now = time();
        $s = strtotime($row['starts_at']);
        $e = strtotime($row['ends_at']);
        if ($e <= $now) {
            $state = 'ended';
        } elseif (empty($row['enabled'])) {
            $state = 'paused';
        } elseif ($s <= $now) {
            $state = 'active';
        } else {
            $state = 'scheduled';
        }

        return [
            'id' => (int)$row['id'],
            'starts_at' => date('Y-m-d\TH:i', $s),
            'ends_at' => date('Y-m-d\TH:i', $e),
            'period_label' => self::periodLabel($row['starts_at'], $row['ends_at']),
            'message' => $row['message'],
            'delay_seconds' => (int)$row['delay_seconds'],
            'resend_after_hours' => (int)$row['resend_after_hours'],
            'enabled' => !empty($row['enabled']),
            'state' => $state,
            'sent_count' => isset($row['sent_count']) ? (int)$row['sent_count'] : null,
            'pending_count' => isset($row['pending_count']) ? (int)$row['pending_count'] : null,
        ];
    }
}
