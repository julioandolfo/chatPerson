<?php
/**
 * Service MediaRateLimitService
 *
 * Rate limiting de envio de mídia para providers Evolution/Quepasa.
 * Limites configuráveis via settings (group: rate_limit).
 *
 * Escopos: por número (account_id), por conversa, por usuário (agente).
 * Auto-throttle: pausa envio após N falhas 502 consecutivas.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Setting;

class MediaRateLimitService
{
    public const MEDIA_TYPES = ['image', 'audio', 'video', 'document', 'voice', 'sticker'];
    public const APPLICABLE_PROVIDERS = ['evolution', 'quepasa'];

    /**
     * Verifica se um envio de mídia é permitido.
     *
     * @return array{allowed: bool, scope?: string, current?: int, limit?: int, window?: string, retry_after_seconds?: int, paused_until?: string, reason?: string}
     */
    public static function check(int $accountId, ?int $conversationId, ?int $userId): array
    {
        // Auto-pause ativa?
        $pause = self::getActivePause($accountId);
        if ($pause) {
            $retryAfter = max(1, strtotime($pause['paused_until']) - time());
            return [
                'allowed' => false,
                'scope' => 'auto_pause',
                'reason' => $pause['reason'] ?? 'Pausa automática por falhas consecutivas',
                'paused_until' => $pause['paused_until'],
                'retry_after_seconds' => $retryAfter,
            ];
        }

        $limits = self::getLimits();

        // 1) Por conta (minuto)
        $count = self::countSince('account_id', $accountId, 60);
        if ($count >= $limits['account_per_minute']) {
            return self::buildBlocked('account', 'minute', $count, $limits['account_per_minute'], 60);
        }

        // 2) Por conta (hora)
        $count = self::countSince('account_id', $accountId, 3600);
        if ($count >= $limits['account_per_hour']) {
            return self::buildBlocked('account', 'hour', $count, $limits['account_per_hour'], 3600);
        }

        // 3) Por conversa (minuto)
        if ($conversationId) {
            $count = self::countSince('conversation_id', $conversationId, 60);
            if ($count >= $limits['conversation_per_minute']) {
                return self::buildBlocked('conversation', 'minute', $count, $limits['conversation_per_minute'], 60);
            }

            // 4) Por conversa (hora)
            $count = self::countSince('conversation_id', $conversationId, 3600);
            if ($count >= $limits['conversation_per_hour']) {
                return self::buildBlocked('conversation', 'hour', $count, $limits['conversation_per_hour'], 3600);
            }
        }

        // 5) Por usuário (minuto)
        if ($userId) {
            $count = self::countSince('user_id', $userId, 60);
            if ($count >= $limits['user_per_minute']) {
                return self::buildBlocked('user', 'minute', $count, $limits['user_per_minute'], 60);
            }
        }

        return ['allowed' => true];
    }

    /**
     * Registra um envio de mídia bem-sucedido no log.
     */
    public static function record(int $accountId, ?int $conversationId, ?int $userId, ?int $messageId, string $mediaType, string $provider): void
    {
        try {
            Database::execute(
                "INSERT INTO media_rate_log (account_id, conversation_id, user_id, message_id, media_type, provider, sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$accountId, $conversationId, $userId, $messageId, $mediaType, $provider]
            );
            // Reset contador de falhas consecutivas no envio bem-sucedido
            self::resetFailures($accountId);
        } catch (\Throwable $e) {
            Logger::error("MediaRateLimitService::record - " . $e->getMessage());
        }
    }

    /**
     * Registra uma falha 502 e ativa pausa automática se atingir threshold.
     */
    public static function recordFailure(int $accountId, ?string $reason = null): void
    {
        $settings = self::getLimits();
        if (!$settings['auto_pause_enabled']) {
            return;
        }

        try {
            $row = Database::fetch(
                "SELECT consecutive_failures FROM media_rate_pauses WHERE account_id = ? LIMIT 1",
                [$accountId]
            );
            $failures = ($row['consecutive_failures'] ?? 0) + 1;

            if ($failures >= $settings['auto_pause_threshold']) {
                $until = date('Y-m-d H:i:s', time() + ($settings['auto_pause_minutes'] * 60));
                $reasonText = $reason ?? "Pausa automática após {$failures} falhas 502 consecutivas";

                Database::execute(
                    "INSERT INTO media_rate_pauses (account_id, paused_until, reason, consecutive_failures, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE paused_until = VALUES(paused_until), reason = VALUES(reason), consecutive_failures = VALUES(consecutive_failures)",
                    [$accountId, $until, $reasonText, $failures]
                );
                Logger::info("MediaRateLimitService::recordFailure - Conta {$accountId} pausada até {$until} ({$failures} falhas)");
            } else {
                Database::execute(
                    "INSERT INTO media_rate_pauses (account_id, paused_until, reason, consecutive_failures, created_at) VALUES (?, NOW(), ?, ?, NOW()) ON DUPLICATE KEY UPDATE consecutive_failures = VALUES(consecutive_failures)",
                    [$accountId, "Falha {$failures}/{$settings['auto_pause_threshold']}", $failures]
                );
            }
        } catch (\Throwable $e) {
            Logger::error("MediaRateLimitService::recordFailure - " . $e->getMessage());
        }
    }

    /**
     * Reseta contador de falhas consecutivas após sucesso.
     */
    public static function resetFailures(int $accountId): void
    {
        try {
            Database::execute(
                "UPDATE media_rate_pauses SET consecutive_failures = 0 WHERE account_id = ? AND paused_until <= NOW()",
                [$accountId]
            );
        } catch (\Throwable $e) {
            // não-crítico
        }
    }

    /**
     * Obtém estatísticas atuais para exibição na UI.
     */
    public static function getStats(int $accountId, ?int $conversationId, ?int $userId): array
    {
        $limits = self::getLimits();
        $pause = self::getActivePause($accountId);

        $stats = [
            'limits' => $limits,
            'paused' => $pause !== null,
            'paused_until' => $pause['paused_until'] ?? null,
            'pause_reason' => $pause['reason'] ?? null,
            'account' => [
                'minute' => self::countSince('account_id', $accountId, 60),
                'hour' => self::countSince('account_id', $accountId, 3600),
            ],
            'conversation' => null,
            'user' => null,
        ];

        if ($conversationId) {
            $stats['conversation'] = [
                'minute' => self::countSince('conversation_id', $conversationId, 60),
                'hour' => self::countSince('conversation_id', $conversationId, 3600),
            ];
        }
        if ($userId) {
            $stats['user'] = [
                'minute' => self::countSince('user_id', $userId, 60),
            ];
        }

        return $stats;
    }

    /**
     * Verifica se o tipo de mensagem deve ser sujeito ao rate limit.
     */
    public static function isMediaType(?string $messageType): bool
    {
        return $messageType !== null && in_array(strtolower($messageType), self::MEDIA_TYPES, true);
    }

    /**
     * Verifica se o provider está sujeito ao rate limit.
     */
    public static function appliesToProvider(?string $provider): bool
    {
        return $provider !== null && in_array(strtolower($provider), self::APPLICABLE_PROVIDERS, true);
    }

    /**
     * Limpa registros antigos (>2h). Chamar via cron ou inline.
     */
    public static function cleanup(): void
    {
        try {
            Database::execute("DELETE FROM media_rate_log WHERE sent_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
            Database::execute("DELETE FROM media_rate_pauses WHERE paused_until < DATE_SUB(NOW(), INTERVAL 1 DAY) AND consecutive_failures = 0");
        } catch (\Throwable $e) {
            Logger::error("MediaRateLimitService::cleanup - " . $e->getMessage());
        }
    }

    // ========== Internos ==========

    private static function countSince(string $column, int $value, int $seconds): int
    {
        $row = Database::fetch(
            "SELECT COUNT(*) AS total FROM media_rate_log WHERE {$column} = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$value, $seconds]
        );
        return (int)($row['total'] ?? 0);
    }

    private static function getActivePause(int $accountId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM media_rate_pauses WHERE account_id = ? AND paused_until > NOW() LIMIT 1",
            [$accountId]
        );
        return $row ?: null;
    }

    private static function buildBlocked(string $scope, string $window, int $current, int $limit, int $windowSeconds): array
    {
        // Calcular retry_after = quanto tempo até o registro mais antigo da janela "expirar"
        $column = $scope === 'account' ? 'account_id' : ($scope === 'conversation' ? 'conversation_id' : 'user_id');
        $row = Database::fetch(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MIN(sent_at), INTERVAL ? SECOND)) AS retry FROM media_rate_log WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)",
            [$windowSeconds, $windowSeconds]
        );
        $retry = max(1, (int)($row['retry'] ?? 30));

        return [
            'allowed' => false,
            'scope' => $scope,
            'window' => $window,
            'current' => $current,
            'limit' => $limit,
            'retry_after_seconds' => $retry,
        ];
    }

    private static function getLimits(): array
    {
        return [
            'account_per_minute' => (int)Setting::get('rate_limit_media_account_per_minute', 15),
            'account_per_hour' => (int)Setting::get('rate_limit_media_account_per_hour', 200),
            'conversation_per_minute' => (int)Setting::get('rate_limit_media_conversation_per_minute', 5),
            'conversation_per_hour' => (int)Setting::get('rate_limit_media_conversation_per_hour', 30),
            'user_per_minute' => (int)Setting::get('rate_limit_media_user_per_minute', 10),
            'auto_pause_enabled' => (int)Setting::get('rate_limit_media_auto_pause_enabled', 1) === 1,
            'auto_pause_threshold' => (int)Setting::get('rate_limit_media_auto_pause_threshold', 3),
            'auto_pause_minutes' => (int)Setting::get('rate_limit_media_auto_pause_minutes', 10),
        ];
    }
}
