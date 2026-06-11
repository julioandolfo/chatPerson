<?php
/**
 * Model EmailIngestionLog
 * Auditoria e idempotência da ingestão de email.
 */

namespace App\Models;

class EmailIngestionLog extends Model
{
    protected string $table = 'email_ingestion_log';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'integration_account_id', 'email_message_id', 'email_uid',
        'from_email', 'subject', 'decision', 'matched_rule_id',
        'conversation_id', 'message_id', 'reason'
    ];
    protected bool $timestamps = false;

    /**
     * Já existe registro para este Message-ID nesta conta? (dedup)
     */
    public static function existsByMessageId(int $accountId, string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }
        $row = \App\Helpers\Database::fetch(
            "SELECT id FROM email_ingestion_log
             WHERE integration_account_id = ? AND email_message_id = ?
             LIMIT 1",
            [$accountId, $messageId]
        );
        return !empty($row);
    }

    /**
     * Registra a decisão; nunca lança (unique violation = já processado).
     */
    public static function record(array $data): int
    {
        if (isset($data['subject'])) {
            $data['subject'] = mb_substr((string)$data['subject'], 0, 990);
        }
        try {
            return self::create($data);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::log('EmailIngestionLog::record: ' . $e->getMessage(), 'email.log');
            return 0;
        }
    }

    /**
     * Últimos registros de uma conta (para exibir no painel).
     */
    public static function recent(int $accountId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return \App\Helpers\Database::fetchAll(
            "SELECT * FROM email_ingestion_log
             WHERE integration_account_id = ?
             ORDER BY id DESC LIMIT {$limit}",
            [$accountId]
        );
    }
}
