<?php
/**
 * Service WebhookAuditService
 *
 * Auditoria de webhooks de WhatsApp. Grava um registro assim que o webhook
 * chega (antes de qualquer validação) e depois marca o desfecho.
 *
 * Uso típico:
 *   WebhookAuditService::start($rawInput, $payload, 'whatsapp-webhook.php');
 *   ... processamento ...
 *   WebhookAuditService::drop('grupo_desabilitado', "chatid={$chatid}");
 *   WebhookAuditService::processed($conversationId, $messageId);
 *
 * Todas as chamadas são "fail-safe": qualquer erro de auditoria é engolido para
 * nunca derrubar o processamento de uma mensagem real.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;

class WebhookAuditService
{
    /** Tamanho máximo do payload gravado (evita estourar MEDIUMTEXT com mídia base64) */
    private const MAX_PAYLOAD = 60000;

    private static ?int $currentId = null;
    private static ?string $requestId = null;
    private static ?float $startedAt = null;
    private static bool $finalized = false;
    private static bool $available = true;

    /**
     * ID único da request — também usado nos logs para correlacionar
     */
    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = substr(md5(uniqid((string)mt_rand(), true)), 0, 12);
        }
        return self::$requestId;
    }

    /**
     * Registrar a chegada de um webhook. Deve ser chamado o mais cedo possível.
     */
    public static function start(string $rawInput, ?array $payload, string $entrypoint): void
    {
        self::$finalized = false;
        self::$startedAt = microtime(true);
        self::$currentId = null;

        if (!self::$available) {
            return;
        }

        try {
            $payload = $payload ?? [];
            $provider = self::detectProvider($payload);

            $sql = "INSERT INTO whatsapp_webhook_audit
                        (request_id, provider, entrypoint, external_id, from_raw, message_type, payload, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'received')";

            self::$currentId = Database::insert($sql, [
                self::requestId(),
                $provider,
                mb_substr($entrypoint, 0, 60),
                self::extractExternalId($payload),
                mb_substr((string)self::extractFrom($payload), 0, 255) ?: null,
                mb_substr((string)($payload['type'] ?? ''), 0, 50) ?: null,
                mb_substr($rawInput, 0, self::MAX_PAYLOAD),
            ]);
        } catch (\Throwable $e) {
            // Tabela ainda não migrada ou banco indisponível: desliga a auditoria
            // para o resto da request e segue o processamento normalmente.
            self::$available = false;
            Logger::quepasa("WebhookAudit - indisponível (seguindo sem auditoria): " . $e->getMessage());
        }
    }

    /**
     * Enriquecer o registro com dados descobertos durante o processamento
     * (conta resolvida, telefone normalizado, direção da mensagem).
     */
    public static function context(array $fields): void
    {
        if (!self::$currentId) {
            return;
        }

        $allowed = ['account_id', 'phone', 'from_raw', 'external_id', 'message_type', 'direction', 'conversation_id', 'message_id', 'provider'];
        $sets = [];
        $params = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $sets[] = "{$key} = ?";
                $params[] = $fields[$key];
            }
        }

        if (empty($sets)) {
            return;
        }

        $params[] = self::$currentId;

        try {
            Database::execute("UPDATE whatsapp_webhook_audit SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        } catch (\Throwable $e) {
            // silencioso por design
        }
    }

    /**
     * Marcar que o webhook foi descartado por uma regra de negócio.
     *
     * @param string $reason Slug curto e estável da regra (ex: 'grupo_desabilitado')
     * @param string $detail Contexto livre para o debug
     */
    public static function drop(string $reason, string $detail = ''): void
    {
        Logger::quepasa("WebhookAudit - ⛔ DESCARTADO [{$reason}] rid=" . self::requestId() . " | {$detail}");
        self::finalize('dropped', $reason, $detail);
    }

    /**
     * Marcar que o webhook gerou mensagem no CRM
     */
    public static function processed(?int $conversationId = null, ?int $messageId = null, string $detail = ''): void
    {
        self::finalize('processed', null, $detail, $conversationId, $messageId);
    }

    /**
     * Marcar erro tratado (exception capturada)
     */
    public static function error(string $detail): void
    {
        self::finalize('error', null, $detail);
    }

    /**
     * Marcar erro fatal (Throwable/shutdown). Chamado pelo shutdown handler.
     */
    public static function fatal(string $detail): void
    {
        self::finalize('fatal', null, $detail);
    }

    /**
     * Já foi finalizado? Usado pelo shutdown handler para detectar requests
     * que morreram no meio (timeout, OOM, fatal error) sem nenhum desfecho.
     */
    public static function isFinalized(): bool
    {
        return self::$finalized || self::$currentId === null;
    }

    private static function finalize(
        string $status,
        ?string $reason = null,
        string $detail = '',
        ?int $conversationId = null,
        ?int $messageId = null
    ): void {
        if (!self::$currentId || self::$finalized) {
            return;
        }

        self::$finalized = true;

        $duration = self::$startedAt ? (int)round((microtime(true) - self::$startedAt) * 1000) : null;

        try {
            Database::execute(
                "UPDATE whatsapp_webhook_audit
                    SET status = ?, drop_reason = ?, detail = ?, duration_ms = ?,
                        conversation_id = COALESCE(?, conversation_id),
                        message_id = COALESCE(?, message_id)
                  WHERE id = ?",
                [
                    $status,
                    $reason ? mb_substr($reason, 0, 120) : null,
                    $detail !== '' ? mb_substr($detail, 0, 5000) : null,
                    $duration,
                    $conversationId,
                    $messageId,
                    self::$currentId,
                ]
            );
        } catch (\Throwable $e) {
            // silencioso por design
        }
    }

    /**
     * Registrar o shutdown handler que captura fatais/timeouts.
     * Sem isso, um `max_execution_time` estourado deixa zero rastro.
     */
    public static function registerFatalHandler(): void
    {
        register_shutdown_function(static function () {
            if (self::isFinalized()) {
                return;
            }

            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                $detail = "{$error['message']} em {$error['file']}:{$error['line']}";
            } else {
                $detail = 'Request encerrada sem desfecho (timeout, OOM ou conexão abortada pelo cliente)';
            }

            Logger::error("WhatsApp webhook FATAL rid=" . self::requestId() . " - {$detail}");
            self::fatal($detail);
        });
    }

    private static function detectProvider(array $payload): string
    {
        if (($payload['source'] ?? '') === 'native') {
            return 'native';
        }
        if (isset($payload['event']) && isset($payload['instance'])) {
            return 'evolution';
        }
        return 'quepasa';
    }

    private static function extractExternalId(array $payload): ?string
    {
        $id = $payload['id']
            ?? $payload['message_id']
            ?? ($payload['message']['id'] ?? null)
            ?? ($payload['data']['id'] ?? null)
            ?? ($payload['data']['key']['id'] ?? null)
            ?? null;

        return is_scalar($id) ? mb_substr((string)$id, 0, 255) : null;
    }

    private static function extractFrom(array $payload): ?string
    {
        $candidates = [
            $payload['from'] ?? null,
            $payload['phone'] ?? null,
            $payload['chat']['id'] ?? null,
            $payload['chat']['phone'] ?? null,
            $payload['data']['key']['remoteJid'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string)$candidate !== '') {
                return (string)$candidate;
            }
        }

        return null;
    }
}
