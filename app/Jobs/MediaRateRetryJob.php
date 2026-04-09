<?php
/**
 * Job MediaRateRetryJob
 *
 * Reprocessa mensagens de mídia que ficaram em fila de rate limit.
 * Status: 'queued_rate_limit'
 */

namespace App\Jobs;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Services\ConversationService;
use App\Services\MediaRateLimitService;

class MediaRateRetryJob
{
    public static function run(): void
    {
        try {
            $rows = Database::fetchAll(
                "SELECT id, conversation_id FROM messages
                 WHERE status = 'queued_rate_limit'
                 ORDER BY id ASC LIMIT 50"
            );

            if (empty($rows)) {
                return;
            }

            Logger::info("MediaRateRetryJob: " . \count($rows) . " mensagem(ns) em fila de rate limit para reprocessar");

            foreach ($rows as $row) {
                try {
                    self::reprocess((int)$row['id']);
                } catch (\Throwable $e) {
                    Logger::error("MediaRateRetryJob - Erro msg={$row['id']}: " . $e->getMessage());
                }
            }

            // Cleanup periódico
            MediaRateLimitService::cleanup();
        } catch (\Throwable $e) {
            Logger::error("MediaRateRetryJob - " . $e->getMessage());
        }
    }

    private static function reprocess(int $messageId): void
    {
        $msg = Database::fetch("SELECT * FROM messages WHERE id = ? LIMIT 1", [$messageId]);
        if (!$msg) {
            return;
        }

        $conversationId = (int)$msg['conversation_id'];
        $conv = Database::fetch("SELECT * FROM conversations WHERE id = ? LIMIT 1", [$conversationId]);
        if (!$conv) {
            Database::execute("UPDATE messages SET status = 'error', error_message = 'Conversa não encontrada' WHERE id = ?", [$messageId]);
            return;
        }

        // Verificar se já podemos enviar agora
        $accountId = (int)($conv['integration_account_id'] ?? $conv['whatsapp_account_id'] ?? 0);
        if (!$accountId) {
            Database::execute("UPDATE messages SET status = 'error', error_message = 'Sem account_id' WHERE id = ?", [$messageId]);
            return;
        }

        $check = MediaRateLimitService::check($accountId, $conversationId, (int)($msg['sender_id'] ?? 0));
        if (!$check['allowed']) {
            // Continua na fila
            return;
        }

        // Liberou — marca como pending e enfileira para envio via background normal
        Database::execute("UPDATE messages SET status = 'pending', error_message = NULL WHERE id = ?", [$messageId]);

        // Disparar envio via integração (background task) usando o sendMessage existente
        try {
            ConversationService::queueBackgroundTask('integration_send', ['messageId' => $messageId]);
            Logger::info("MediaRateRetryJob: msg={$messageId} liberada e enfileirada para envio");
        } catch (\Throwable $e) {
            // Fallback: enviar inline
            try {
                ConversationService::processIntegrationSend($messageId);
            } catch (\Throwable $e2) {
                Logger::error("MediaRateRetryJob: falha ao reenviar msg={$messageId}: " . $e2->getMessage());
            }
        }
    }
}
