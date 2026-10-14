<?php
/**
 * Service PushService
 *
 * Notificações push do app (PWA) — chegam com o app/navegador fechado.
 *
 * - Toda notificação interna (NotificationService::create) também vira push para os
 *   aparelhos inscritos do usuário.
 * - Os envios são acumulados e disparados juntos (em paralelo) no fim da requisição,
 *   para não atrasar a resposta nem o processamento do webhook.
 * - Chaves VAPID: variáveis de ambiente VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY /
 *   VAPID_SUBJECT, ou geradas automaticamente na primeira vez e guardadas em settings.
 */

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\WebPush;

class PushService
{
    /** Serviços de push aceitos (evita que o servidor faça POST para endereços arbitrários) */
    private const ALLOWED_PUSH_HOST_SUFFIXES = ['googleapis.com', 'mozilla.com', 'windows.com', 'apple.com'];

    private const MAX_FAILURES = 20;
    private const HTTP_TIMEOUT = 5;

    /** @var array<int, array> mensagens aguardando envio no fim da requisição */
    private static array $queue = [];
    private static bool $shutdownRegistered = false;
    private static ?array $vapid = null;

    // =====================================================================
    // INSCRIÇÕES
    // =====================================================================

    public static function subscribe(int $userId, array $subscription, bool $showPreview = true): array
    {
        $endpoint = trim((string)($subscription['endpoint'] ?? ''));
        $p256dh = (string)($subscription['keys']['p256dh'] ?? $subscription['p256dh'] ?? '');
        $auth = (string)($subscription['keys']['auth'] ?? $subscription['auth'] ?? '');

        self::assertValidEndpoint($endpoint);
        try {
            if (strlen(WebPush::b64uDecode($p256dh)) !== 65 || strlen(WebPush::b64uDecode($auth)) !== 16) {
                throw new \InvalidArgumentException();
            }
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('Chaves da inscrição inválidas');
        }

        Database::execute(
            "INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth_secret, show_preview, user_agent, failures)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth_secret = VALUES(auth_secret),
                show_preview = VALUES(show_preview), user_agent = VALUES(user_agent), failures = 0",
            [
                $userId,
                $endpoint,
                hash('sha256', $endpoint),
                $p256dh,
                $auth,
                $showPreview ? 1 : 0,
                mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );

        return self::getSubscription($userId, $endpoint) ?? [];
    }

    public static function unsubscribe(int $userId, string $endpoint): bool
    {
        return Database::execute(
            "DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?",
            [hash('sha256', $endpoint), $userId]
        ) > 0;
    }

    public static function setShowPreview(int $userId, string $endpoint, bool $showPreview): bool
    {
        Database::execute(
            "UPDATE push_subscriptions SET show_preview = ? WHERE endpoint_hash = ? AND user_id = ?",
            [$showPreview ? 1 : 0, hash('sha256', $endpoint), $userId]
        );
        return self::getSubscription($userId, $endpoint) !== null;
    }

    public static function getSubscription(int $userId, string $endpoint): ?array
    {
        $row = Database::fetch(
            "SELECT id, show_preview, user_agent, last_success_at, created_at FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?",
            [hash('sha256', $endpoint), $userId]
        );
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'show_preview' => (bool)$row['show_preview'],
            'device' => RememberMeService::describeUserAgent($row['user_agent'] ?? ''),
            'last_success_at' => $row['last_success_at'],
        ];
    }

    private static function assertValidEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || strlen($endpoint) > 1000) {
            throw new \InvalidArgumentException('Endereço de push inválido');
        }
        foreach (self::ALLOWED_PUSH_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return;
            }
        }
        throw new \InvalidArgumentException('Serviço de push não suportado: ' . $host);
    }

    // =====================================================================
    // CHAVES VAPID
    // =====================================================================

    public static function getVapid(): array
    {
        if (self::$vapid !== null) {
            return self::$vapid;
        }

        $envPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?: '';
        $envPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?: '';
        $envSubject = $_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?: '';

        if ($envPublic && $envPrivate) {
            return self::$vapid = [
                'public' => $envPublic,
                'private' => $envPrivate,
                'subject' => $envSubject ?: self::defaultSubject(),
            ];
        }

        $public = SettingService::get('pwa_vapid_public_key', '');
        $private = SettingService::get('pwa_vapid_private_key', '');
        $subject = SettingService::get('pwa_vapid_subject', '');

        if (!$public || !$private) {
            $keys = WebPush::generateVapidKeys();
            $subject = $envSubject ?: self::defaultSubject();
            SettingService::set('pwa_vapid_public_key', $keys['public'], 'string', 'pwa');
            SettingService::set('pwa_vapid_private_key', $keys['private'], 'string', 'pwa');
            SettingService::set('pwa_vapid_subject', $subject, 'string', 'pwa');
            $public = $keys['public'];
            $private = $keys['private'];
            Logger::info("PushService: chaves VAPID geradas");
        }

        return self::$vapid = [
            'public' => $public,
            'private' => $private,
            'subject' => $envSubject ?: ($subject ?: self::defaultSubject()),
        ];
    }

    /** O "sub" do VAPID precisa ser um contato real (a Apple recusa domínios inválidos) */
    private static function defaultSubject(): string
    {
        try {
            $row = Database::fetch(
                "SELECT email FROM users WHERE status = 'active' AND email LIKE '%@%.%' ORDER BY (role LIKE '%admin%') DESC, id ASC LIMIT 1"
            );
            if (!empty($row['email'])) {
                return 'mailto:' . $row['email'];
            }
        } catch (\Throwable $e) {
            // segue para o padrão
        }
        return 'mailto:suporte@example.com';
    }

    // =====================================================================
    // ENFILEIRAR / ENVIAR
    // =====================================================================

    /**
     * Chamado por NotificationService::create — transforma a notificação interna em push
     */
    public static function queueFromNotification(array $notification): void
    {
        $userId = (int)($notification['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        // Quem disparou a ação está com o sistema aberto: não precisa de push
        if (PHP_SAPI !== 'cli' && \App\Helpers\Auth::id() === $userId) {
            return;
        }

        $data = $notification['data'] ?? [];
        if (is_string($data)) {
            $data = json_decode($data, true) ?: [];
        }

        $conversationId = isset($data['conversation_id']) ? (int)$data['conversation_id'] : null;
        $title = (string)$notification['title'];
        $body = (string)$notification['message'];
        $preview = null;

        // Mensagem nova: mostrar o nome do contato e o texto da mensagem
        if (($notification['type'] ?? '') === 'message' && $conversationId) {
            $details = self::messageDetails($conversationId, isset($data['message_id']) ? (int)$data['message_id'] : null);
            if ($details) {
                $title = $details['contact_name'];
                $preview = $details['text'];
                $body = 'Nova mensagem';
            }
        }

        self::queue($userId, [
            'title' => mb_substr($title, 0, 120),
            'body' => mb_substr($body, 0, 240),
            'preview' => $preview !== null ? mb_substr($preview, 0, 240) : null,
            'conversation_id' => $conversationId,
            'type' => $notification['type'] ?? 'system',
        ]);
    }

    /**
     * Enfileirar um push para todos os aparelhos do usuário
     */
    public static function queue(int $userId, array $message): void
    {
        self::$queue[] = ['user_id' => $userId, 'message' => $message];

        // CLI (cron, workers): envia na hora — num processo longo o "fim da requisição" não chega
        if (PHP_SAPI === 'cli') {
            self::flush();
            return;
        }

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /**
     * Enviar tudo que está na fila (em paralelo). Retorna estatísticas.
     */
    public static function flush(): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'removed' => 0];
        $items = self::$queue;
        self::$queue = [];
        if (empty($items)) {
            return $stats;
        }

        try {
            $userIds = array_values(array_unique(array_map(fn($i) => (int)$i['user_id'], $items)));
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $subscriptions = Database::fetchAll(
                "SELECT * FROM push_subscriptions WHERE user_id IN ({$placeholders})",
                $userIds
            );
        } catch (\Throwable $e) {
            // Migration 160 ainda não executada: push desligado
            return $stats;
        }
        if (empty($subscriptions)) {
            return $stats;
        }

        $byUser = [];
        foreach ($subscriptions as $sub) {
            $byUser[(int)$sub['user_id']][] = $sub;
        }

        $vapid = self::getVapid();
        $requests = [];
        foreach ($items as $item) {
            foreach ($byUser[(int)$item['user_id']] ?? [] as $sub) {
                try {
                    $payload = self::buildPayload($item['message'], (bool)$sub['show_preview']);
                    $topic = !empty($item['message']['conversation_id']) ? 'conv' . (int)$item['message']['conversation_id'] : null;
                    $requests[] = [
                        'subscription' => $sub,
                        'request' => WebPush::buildRequest(
                            ['endpoint' => $sub['endpoint'], 'p256dh' => $sub['p256dh'], 'auth' => $sub['auth_secret']],
                            $payload,
                            $vapid,
                            86400,
                            'high',
                            $topic
                        ),
                    ];
                } catch (\Throwable $e) {
                    Logger::error("PushService: erro ao montar push para inscrição {$sub['id']}: " . $e->getMessage());
                    $stats['failed']++;
                }
            }
        }

        foreach (self::sendAll($requests) as $result) {
            $sub = $result['subscription'];
            $code = $result['status'];

            if ($code >= 200 && $code < 300) {
                $stats['sent']++;
                Database::execute("UPDATE push_subscriptions SET failures = 0, last_success_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int)$sub['id']]);
            } elseif ($code === 404 || $code === 410) {
                // Inscrição expirada/cancelada no aparelho
                $stats['removed']++;
                Database::execute("DELETE FROM push_subscriptions WHERE id = ?", [(int)$sub['id']]);
            } else {
                $stats['failed']++;
                Logger::warning("PushService: falha no push (inscrição {$sub['id']}, HTTP {$code}): " . mb_substr($result['response'], 0, 300));
                Database::execute(
                    "UPDATE push_subscriptions SET failures = failures + 1 WHERE id = ?",
                    [(int)$sub['id']]
                );
                Database::execute("DELETE FROM push_subscriptions WHERE id = ? AND failures >= ?", [(int)$sub['id'], self::MAX_FAILURES]);
            }
        }

        return $stats;
    }

    /**
     * Conteúdo que vai criptografado para o aparelho (lido pelo service worker)
     */
    private static function buildPayload(array $message, bool $showPreview): string
    {
        $body = $message['body'];
        if ($showPreview && !empty($message['preview'])) {
            $body = $message['preview'];
        }

        $conversationId = $message['conversation_id'] ?? null;

        return json_encode([
            'title' => $message['title'],
            'body' => $body,
            // Relativo ao escopo do app: o service worker resolve contra a URL de instalação
            'url' => $conversationId ? 'conversations?id=' . $conversationId : 'conversations',
            'tag' => $conversationId ? 'conversation-' . $conversationId : 'notification-' . ($message['type'] ?? 'system'),
            'conversation_id' => $conversationId,
            'type' => $message['type'] ?? 'system',
            'timestamp' => time() * 1000,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Nome do contato e resumo da mensagem
     */
    private static function messageDetails(int $conversationId, ?int $messageId): ?array
    {
        $row = Database::fetch(
            "SELECT ct.name AS contact_name, ct.phone AS contact_phone
             FROM conversations c LEFT JOIN contacts ct ON ct.id = c.contact_id WHERE c.id = ?",
            [$conversationId]
        );
        if (!$row) {
            return null;
        }

        $text = null;
        if ($messageId) {
            $message = Database::fetch("SELECT content, message_type FROM messages WHERE id = ?", [$messageId]);
            if ($message) {
                $labels = [
                    'image' => '📷 Imagem', 'video' => '🎥 Vídeo', 'audio' => '🎤 Áudio', 'document' => '📄 Documento',
                    'sticker' => 'Figurinha', 'location' => '📍 Localização', 'contact' => '👤 Contato',
                ];
                $content = trim((string)($message['content'] ?? ''));
                $label = $labels[$message['message_type'] ?? ''] ?? null;
                $text = $content !== '' ? ($label ? $label . ' · ' . $content : $content) : ($label ?? 'Nova mensagem');
            }
        }

        return [
            'contact_name' => $row['contact_name'] ?: ($row['contact_phone'] ?: 'Contato'),
            'text' => $text,
        ];
    }

    /**
     * Enviar as requisições em paralelo (curl_multi)
     */
    private static function sendAll(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as $i => $item) {
            $req = $item['request'];
            $ch = curl_init($req['url']);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $req['body'],
                CURLOPT_HTTPHEADER => array_merge($req['headers'], ['Content-Length: ' . strlen($req['body'])]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => self::HTTP_TIMEOUT,
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$i] = $ch;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $i => $ch) {
            $results[] = [
                'subscription' => $requests[$i]['subscription'],
                'status' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'response' => (string)curl_multi_getcontent($ch) . (curl_error($ch) ? ' [' . curl_error($ch) . ']' : ''),
            ];
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $results;
    }
}
