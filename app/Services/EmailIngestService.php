<?php
/**
 * EmailIngestService
 * Transforma um email recebido em conversa/mensagem, aplicando as regras de
 * validação. A mensagem é criada via ConversationService::sendMessage para
 * herdar exatamente o mesmo fluxo dos demais canais (automações, IA, SLA,
 * Kanban, métricas, etc.).
 */

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\EmailRule;
use App\Models\EmailIngestionLog;
use App\Helpers\Logger;

class EmailIngestService
{
    private const ATTACH_BASE = __DIR__ . '/../../public/assets/media/attachments/';

    /**
     * Processa um email normalizado.
     * @return string decisão: ingested|ignored|duplicate|error
     */
    public static function ingest(array $account, array $cfg, array $email): string
    {
        $accountId = (int)$account['id'];
        $messageId = (string)($email['message_id'] ?? '');

        // 1) Dedup (Message-ID já visto no log OU já existe mensagem com este external_id)
        if ($messageId !== '') {
            if (EmailIngestionLog::existsByMessageId($accountId, $messageId) || Message::findByExternalId($messageId)) {
                return 'duplicate';
            }
        }

        // 2) Anti-loop: ignora respostas automáticas / bounces / no-reply
        $autoReason = self::looksAutomated($email);
        if ($autoReason !== null) {
            self::logDecision($account, $email, 'ignored', null, null, null, 'auto:' . $autoReason);
            return 'ignored';
        }

        // 3) Regras de validação
        $rules = EmailRule::getForAccount($accountId, true);
        $unmatched = $cfg['unmatched_action'] ?? 'ignore';
        $decision = EmailRuleEngine::evaluate($rules, $email, $unmatched);

        if (empty($decision['ingest'])) {
            self::logDecision($account, $email, 'ignored', $decision['matched_rule']['id'] ?? null, null, null, $decision['reason']);
            return 'ignored';
        }

        // 4) Contato (por email, normalizado)
        $fromEmail = $email['from_email'] !== '' ? $email['from_email'] : ('desconhecido+' . ($email['uid'] ?? '0') . '@email.local');
        $contact = Contact::findByEmail($fromEmail);
        if (!$contact) {
            $contact = Contact::findOrCreate([
                'name'  => $email['from_name'] !== '' ? $email['from_name'] : $fromEmail,
                'email' => $fromEmail,
            ]);
        }
        if (!$contact || empty($contact['id'])) {
            self::logDecision($account, $email, 'error', $decision['matched_rule']['id'] ?? null, null, null, 'sem_contato');
            return 'error';
        }
        $contactId = (int)$contact['id'];

        // 5) Conversa (threading -> existente -> nova)
        $actions = is_array($decision['actions'] ?? null) ? $decision['actions'] : [];
        [$conversationId, $isNew] = self::resolveConversation($account, $cfg, $contactId, $email, $actions);
        if (!$conversationId) {
            self::logDecision($account, $email, 'error', $decision['matched_rule']['id'] ?? null, null, null, 'sem_conversa');
            return 'error';
        }

        // 6) Mensagem inbound (via sendMessage -> herda automações/IA/Kanban)
        $content = self::buildContent($email, $cfg);
        $attachments = self::saveAttachments($email, $conversationId);
        $messageType = empty($attachments) ? 'text' : 'document';

        $newMessageId = ConversationService::sendMessage(
            $conversationId,
            $content,
            'contact',
            $contactId,
            $attachments,
            $messageType,
            null,                                   // quotedMessageId
            null,                                   // aiAgentId
            (int)($email['timestamp'] ?? time()),   // messageTimestamp
            false,                                  // skipAutomations
            false,                                  // deferIntegrationSend
            $messageId !== '' ? $messageId : null   // externalId (Message-ID, p/ dedup e threading)
        );

        // 7) Automação de nova conversa (espelha o que o webhook dos outros canais faz)
        if ($isNew) {
            try {
                AutomationService::executeForNewConversation($conversationId);
            } catch (\Throwable $e) {
                Logger::log('[EMAIL] executeForNewConversation: ' . $e->getMessage(), 'email.log');
            }
        }

        self::logDecision(
            $account, $email, 'ingested',
            $decision['matched_rule']['id'] ?? null,
            $conversationId,
            $newMessageId ? (int)$newMessageId : null,
            $decision['reason']
        );
        return 'ingested';
    }

    /**
     * Resolve a conversa: por threading (In-Reply-To/References), depois conversa
     * aberta do contato no canal, senão cria nova.
     * @return array [conversationId(int), isNew(bool)]
     */
    private static function resolveConversation(array $account, array $cfg, int $contactId, array $email, array $actions): array
    {
        $accountId = (int)$account['id'];

        // Threading por referências de email
        foreach (self::referenceIds($email) as $ref) {
            $prev = Message::findByExternalId($ref);
            if ($prev && !empty($prev['conversation_id'])) {
                return [(int)$prev['conversation_id'], false];
            }
        }

        // Conversa aberta existente do contato neste canal/conta
        $existing = Conversation::findByContactAndChannel($contactId, 'email', null, $accountId);
        if ($existing && !empty($existing['id'])) {
            return [(int)$existing['id'], false];
        }

        // Nova conversa
        $data = [
            'contact_id'             => $contactId,
            'channel'                => 'email',
            'integration_account_id' => $accountId,
        ];
        foreach (['department_id', 'funnel_id', 'stage_id', 'agent_id'] as $k) {
            if (!empty($actions[$k])) {
                $data[$k] = (int)$actions[$k];
            }
        }

        try {
            $conv = ConversationService::create($data, false);
        } catch (\Throwable $e) {
            Logger::log('[EMAIL] create conversa: ' . $e->getMessage(), 'email.log');
            return [0, false];
        }
        $convId = (int)($conv['id'] ?? 0);
        if (!$convId) {
            return [0, false];
        }

        // Guarda assunto/threading no metadata + ações pós-criação
        try {
            $fresh = Conversation::find($convId);
            $meta = json_decode($fresh['metadata'] ?? '{}', true) ?: [];
            $meta['email_subject'] = mb_substr((string)($email['subject'] ?? ''), 0, 255);
            $meta['email_root_message_id'] = (string)($email['message_id'] ?? '');
            if (!empty($actions['tag'])) {
                $meta['email_rule_tag'] = (string)$actions['tag'];
            }
            $update = ['metadata' => json_encode($meta, JSON_UNESCAPED_UNICODE)];
            if (!empty($actions['priority'])) {
                $update['priority'] = (string)$actions['priority'];
            }
            Conversation::update($convId, $update);
        } catch (\Throwable $e) {
            // não crítico
        }

        return [$convId, true];
    }

    /**
     * IDs de referência (In-Reply-To + References) sem < >.
     */
    private static function referenceIds(array $email): array
    {
        $ids = [];
        if (!empty($email['in_reply_to'])) {
            $ids[] = trim((string)$email['in_reply_to'], '<>');
        }
        if (!empty($email['references'])) {
            foreach (preg_split('/\s+/', (string)$email['references']) as $r) {
                $r = trim($r, '<>');
                if ($r !== '') {
                    $ids[] = $r;
                }
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Texto final da mensagem: assunto + corpo (preferindo text/plain), com
     * remoção opcional do histórico citado.
     */
    private static function buildContent(array $email, array $cfg): string
    {
        $text = (string)($email['text'] ?? '');
        if ($text === '' && !empty($email['html'])) {
            $text = \App\Services\Email\HtmlToText::convert((string)$email['html']);
        }
        if (!empty($cfg['strip_quoted'])) {
            $text = self::stripQuoted($text);
        }

        $subject = trim((string)($email['subject'] ?? ''));
        $prefix = $subject !== '' ? ('Assunto: ' . $subject . "\n\n") : '';
        $content = trim($prefix . trim($text));

        if ($content === '') {
            $content = $subject !== '' ? ('Assunto: ' . $subject) : '(email sem conteúdo)';
        }
        return $content;
    }

    private static function stripQuoted(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $out = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*>/', $line)) {
                break;
            }
            if (preg_match('/^\s*(Em|On)\s.+(escreveu|wrote)\s*:?\s*$/iu', $line)) {
                break;
            }
            if (preg_match('/^\s*-{2,}\s*(Mensagem original|Original Message|Forwarded message)/iu', $line)) {
                break;
            }
            if (preg_match('/^\s*_{5,}\s*$/', $line)) {
                break;
            }
            $out[] = $line;
        }
        $result = trim(implode("\n", $out));
        return $result !== '' ? $result : trim($text);
    }

    /**
     * Salva anexos no mesmo padrão dos demais canais (public/assets/media/attachments/{conv}/).
     */
    private static function saveAttachments(array $email, int $conversationId): array
    {
        $result = [];
        $atts = $email['attachments'] ?? [];
        if (empty($atts)) {
            return $result;
        }

        $dir = self::ATTACH_BASE . $conversationId . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        foreach ($atts as $i => $att) {
            try {
                $content = $att['content'] ?? '';
                if ($content === '') {
                    continue;
                }
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)($att['name'] ?? ('anexo-' . $i)));
                $filename = 'email_' . ($email['uid'] ?? '0') . '_' . $i . '_' . $safe;
                $abs = $dir . $filename;
                if (@file_put_contents($abs, $content) === false) {
                    continue;
                }
                $mime = (string)($att['mime'] ?? 'application/octet-stream');
                $relative = 'assets/media/attachments/' . $conversationId . '/' . $filename;
                $result[] = [
                    'filename'      => $filename,
                    'original_name' => $att['name'] ?? $filename,
                    'path'          => $relative,
                    'url'           => \App\Helpers\Url::to($relative),
                    'type'          => self::typeFromMime($mime),
                    'mime_type'     => $mime,
                    'size'          => (int)($att['size'] ?? strlen($content)),
                ];
            } catch (\Throwable $e) {
                Logger::log('[EMAIL] saveAttachment: ' . $e->getMessage(), 'email.log');
            }
        }
        return $result;
    }

    private static function typeFromMime(string $mime): string
    {
        if (strpos($mime, 'image/') === 0) {
            return 'image';
        }
        if (strpos($mime, 'video/') === 0) {
            return 'video';
        }
        if (strpos($mime, 'audio/') === 0) {
            return 'audio';
        }
        return 'document';
    }

    /**
     * Detecta emails automáticos (loops/ruído). Retorna o motivo ou null.
     */
    private static function looksAutomated(array $email): ?string
    {
        $from = strtolower((string)($email['from_email'] ?? ''));
        if ($from !== '') {
            $localPart = explode('@', $from)[0];
            $blocked = ['no-reply', 'noreply', 'nao-responda', 'naoresponda', 'mailer-daemon', 'postmaster', 'bounce', 'bounces', 'donotreply', 'do-not-reply'];
            foreach ($blocked as $b) {
                if (strpos($localPart, $b) !== false) {
                    return 'remetente_' . $b;
                }
            }
        }

        $headers = strtolower((string)($email['raw_headers'] ?? ''));
        if ($headers !== '') {
            if (strpos($headers, 'auto-submitted: auto-') !== false) {
                return 'auto_submitted';
            }
            if (preg_match('/precedence:\s*(bulk|list|junk)/', $headers)) {
                return 'precedence_bulk';
            }
            if (strpos($headers, 'x-autoreply') !== false || strpos($headers, 'x-autorespond') !== false) {
                return 'autoreply_header';
            }
        }
        return null;
    }

    private static function logDecision(array $account, array $email, string $decision, $ruleId, $convId, $msgId, string $reason): void
    {
        EmailIngestionLog::record([
            'integration_account_id' => (int)$account['id'],
            'email_message_id'       => ($email['message_id'] ?? '') !== '' ? $email['message_id'] : null,
            'email_uid'              => isset($email['uid']) ? (int)$email['uid'] : null,
            'from_email'             => mb_substr((string)($email['from_email'] ?? ''), 0, 320),
            'subject'                => (string)($email['subject'] ?? ''),
            'decision'               => $decision,
            'matched_rule_id'        => $ruleId ? (int)$ruleId : null,
            'conversation_id'        => $convId ? (int)$convId : null,
            'message_id'             => $msgId ? (int)$msgId : null,
            'reason'                 => mb_substr($reason, 0, 250),
        ]);
    }
}
