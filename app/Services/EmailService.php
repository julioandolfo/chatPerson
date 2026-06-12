<?php
/**
 * EmailService
 * Provider do canal Email (provider='imap', channel='email').
 *
 * - sendMessage(): saída via SMTP, chamado por IntegrationService::sendMessage
 *   (logo, a resposta do atendente herda o mesmo fluxo dos demais canais).
 * - checkConnection(): testa IMAP.
 * - pollAccount(): busca e ingere emails novos de uma conta.
 */

namespace App\Services;

use App\Models\IntegrationAccount;
use App\Models\Conversation;
use App\Models\Contact;
use App\Services\Email\ImapClient;
use App\Services\Email\SmtpMailer;
use App\Helpers\Encryption;
use App\Helpers\Logger;

class EmailService
{
    /** Valor de integration_accounts.provider para contas de email. */
    public const PROVIDER = 'imap';

    /** Base pública para resolver caminho local de anexos enviados pelo agente. */
    private const PUBLIC_BASE = __DIR__ . '/../../public/';

    /** Evita rodar o ensureSchema mais de uma vez por processo. */
    private static bool $schemaChecked = false;

    /**
     * Garante que as tabelas do canal de email existam (idempotente).
     * Auto-provisiona em ambientes onde a migration 150 ainda não rodou.
     */
    public static function ensureSchema(): void
    {
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;

        try {
            $db = \App\Helpers\Database::getInstance();
            $db->exec("CREATE TABLE IF NOT EXISTS email_ingestion_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                integration_account_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                priority INT NOT NULL DEFAULT 0,
                match_type VARCHAR(10) NOT NULL DEFAULT 'any',
                conditions JSON NULL,
                actions JSON NULL,
                stop_on_match TINYINT(1) NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account (integration_account_id),
                INDEX idx_active (is_active),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $db->exec("CREATE TABLE IF NOT EXISTS email_ingestion_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                integration_account_id INT NOT NULL,
                email_message_id VARCHAR(255) NULL,
                email_uid INT NULL,
                from_email VARCHAR(320) NULL,
                subject VARCHAR(998) NULL,
                decision VARCHAR(20) NOT NULL,
                matched_rule_id INT NULL,
                conversation_id INT NULL,
                message_id INT NULL,
                reason VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_account_msgid (integration_account_id, email_message_id),
                INDEX idx_account (integration_account_id),
                INDEX idx_decision (decision),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Throwable $e) {
            \App\Helpers\Logger::log('[EMAIL] ensureSchema: ' . $e->getMessage(), 'email.log');
        }
    }

    /**
     * Envio (outbound) — assinatura compatível com IntegrationService::sendMessage.
     * @return array ['success'=>bool,'external_id'=>?string,'message_id'=>?string]
     */
    public function sendMessage(int $accountId, string $to, string $message, array $options = []): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            throw new \Exception('Conta de email não encontrada: ' . $accountId);
        }
        $cfg = self::config($account);

        if (empty($cfg['smtp_host']) && empty($cfg['imap_host'])) {
            throw new \Exception('Conta de email sem SMTP configurado');
        }

        // Contexto de thread (assunto Re: + In-Reply-To) a partir da conversa do contato
        $ctx = self::threadContext($accountId, $to);
        $subject = $options['subject'] ?? $ctx['subject'];
        $inReplyTo = !empty($options['quoted_message_external_id']) ? $options['quoted_message_external_id'] : $ctx['in_reply_to'];

        // Anexo enviado pelo agente (media_url -> caminho local)
        $attachments = [];
        if (!empty($options['media_url'])) {
            $attachments[] = self::localAttachmentFromUrl(
                $options['media_url'],
                $options['media_name'] ?? null,
                $options['media_mime'] ?? null
            );
            if (!empty($options['caption']) && trim($message) === '') {
                $message = (string)$options['caption'];
            }
        }

        $res = SmtpMailer::send($cfg, [
            'to'          => $to,
            'subject'     => $subject,
            'body'        => $message,
            'in_reply_to' => $inReplyTo,
            'references'  => $ctx['references'],
            'attachments' => array_values(array_filter($attachments)),
        ]);

        if (!empty($res['success'])) {
            Logger::log("[EMAIL] enviado acc={$accountId} to={$to} extId=" . ($res['external_id'] ?? ''), 'email.log');
            return [
                'success'     => true,
                'external_id' => $res['external_id'] ?? null,
                'message_id'  => $res['external_id'] ?? null,
            ];
        }

        throw new \Exception('Falha no envio SMTP: ' . ($res['error'] ?? 'desconhecido'));
    }

    /**
     * Testa a conexão (IMAP) — usado por IntegrationService::checkStatus.
     */
    public function checkConnection(int $accountId): array
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            return ['status' => 'error', 'connected' => false, 'message' => 'Conta não encontrada'];
        }
        $cfg = self::config($account);
        $imap = ImapClient::testConnection($cfg);
        return [
            'status'    => $imap['success'] ? 'active' : 'error',
            'connected' => (bool)$imap['success'],
            'message'   => $imap['message'] ?? '',
        ];
    }

    /**
     * Monta a config efetiva da conta, decriptando a senha.
     */
    public static function config(array $account): array
    {
        $cfg = $account['config'] ?? [];
        if (is_string($cfg)) {
            $cfg = json_decode($cfg, true) ?: [];
        }
        $cfg['auth_user'] = $cfg['auth_user'] ?? ($account['username'] ?? '');

        $enc = $cfg['auth_pass_enc'] ?? '';
        if ($enc !== '') {
            $cfg['auth_pass'] = Encryption::decrypt($enc) ?? '';
        } else {
            $cfg['auth_pass'] = $cfg['auth_pass'] ?? '';
        }
        $cfg['from_address'] = $cfg['from_address'] ?? $cfg['auth_user'];
        return $cfg;
    }

    /**
     * Poll de uma conta: busca novos emails (uid > last_uid) e ingere.
     * @return array estatísticas
     */
    public static function pollAccount(array $account): array
    {
        self::ensureSchema();
        $cfg = self::config($account);
        $lastUid = (int)($cfg['last_uid'] ?? 0);
        $lookback = (int)($cfg['poll_lookback_days'] ?? 2);

        $stats = ['fetched' => 0, 'ingested' => 0, 'ignored' => 0, 'duplicate' => 0, 'error' => 0, 'max_uid' => $lastUid];

        $emails = ImapClient::fetchNew($cfg, $lastUid, $lookback);
        foreach ($emails as $email) {
            $stats['fetched']++;
            try {
                $decision = EmailIngestService::ingest($account, $cfg, $email);
                $stats[$decision] = ($stats[$decision] ?? 0) + 1;
            } catch (\Throwable $e) {
                $stats['error']++;
                Logger::log('[EMAIL] ingest uid=' . ($email['uid'] ?? '?') . ': ' . $e->getMessage(), 'email.log');
            }
            $uid = (int)($email['uid'] ?? 0);
            if ($uid > $stats['max_uid']) {
                $stats['max_uid'] = $uid;
            }
        }

        if ($stats['max_uid'] > $lastUid) {
            self::saveLastUid((int)$account['id'], $stats['max_uid']);
        }
        IntegrationAccount::update((int)$account['id'], ['last_sync_at' => date('Y-m-d H:i:s')]);

        return $stats;
    }

    private static function saveLastUid(int $accountId, int $uid): void
    {
        $account = IntegrationAccount::find($accountId);
        if (!$account) {
            return;
        }
        $cfg = $account['config'] ?? [];
        if (is_string($cfg)) {
            $cfg = json_decode($cfg, true) ?: [];
        }
        $cfg['last_uid'] = $uid;
        IntegrationAccount::update($accountId, ['config' => json_encode($cfg, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * Descobre assunto (Re:) e In-Reply-To a partir da conversa de email do contato.
     */
    private static function threadContext(int $accountId, string $to): array
    {
        $out = ['subject' => 'Atendimento', 'in_reply_to' => '', 'references' => ''];

        $contact = Contact::findByEmail(strtolower(trim($to)));
        if (!$contact || empty($contact['id'])) {
            return $out;
        }
        $conv = Conversation::findByContactAndChannel((int)$contact['id'], 'email', null, $accountId);
        if (!$conv || empty($conv['id'])) {
            return $out;
        }

        $meta = json_decode($conv['metadata'] ?? '{}', true) ?: [];
        $subject = (string)($meta['email_subject'] ?? '');
        if ($subject !== '') {
            $out['subject'] = preg_match('/^\s*re:/i', $subject) ? $subject : ('Re: ' . $subject);
        }

        $last = \App\Helpers\Database::fetch(
            "SELECT external_id FROM messages
             WHERE conversation_id = ? AND sender_type = 'contact'
               AND external_id IS NOT NULL AND external_id <> ''
             ORDER BY id DESC LIMIT 1",
            [(int)$conv['id']]
        );
        if ($last && !empty($last['external_id'])) {
            $id = trim((string)$last['external_id'], '<>');
            $out['in_reply_to'] = $id;
            $out['references'] = '<' . $id . '>';
        }

        return $out;
    }

    private static function localAttachmentFromUrl(string $url, ?string $name, ?string $mime): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $abs = self::PUBLIC_BASE . ltrim($path, '/');
        return [
            'abs_path' => is_file($abs) ? $abs : null,
            'name'     => $name ?: basename($path),
            'mime'     => $mime ?: 'application/octet-stream',
        ];
    }
}
