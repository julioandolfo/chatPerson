<?php
/**
 * Service PushNotificationService
 * Envio de push notifications para o app mobile (Chat Privus) via Expo Push API.
 * Um único endpoint HTTP cobre iOS (APNs) e Android (FCM) — credenciais gerenciadas pelo EAS.
 */

namespace App\Services;

use App\Helpers\Logger;
use App\Models\DeviceToken;

class PushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    private const BATCH_SIZE = 100;

    /** Janela (segundos) em que o usuário é considerado "presente" no app (poll ativo) */
    private const PRESENCE_WINDOW = 30;

    /**
     * Enviar push para todos os dispositivos ativos de um usuário
     *
     * @param int    $userId  Usuário destinatário
     * @param string $title   Título da notificação
     * @param string $body    Corpo da notificação
     * @param array  $data    Payload extra (ex: ['type' => 'new_message', 'conversation_id' => 123])
     * @param array  $options Opções: ['skip_if_present' => bool, 'badge' => int, 'sound' => string, 'channel_id' => string, 'thread_id' => string]
     */
    public static function sendToUser(int $userId, string $title, string $body, array $data = [], array $options = []): void
    {
        try {
            // Supressão por presença: não notificar quem está com o app aberto (poll ativo)
            $skipIfPresent = $options['skip_if_present'] ?? true;
            if ($skipIfPresent && self::isUserPresent($userId)) {
                return;
            }

            $tokens = DeviceToken::getActiveByUser($userId);
            if (empty($tokens)) {
                return;
            }

            $messages = [];
            foreach ($tokens as $device) {
                $message = [
                    'to' => $device['token'],
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'priority' => 'high',
                    'sound' => $options['sound'] ?? 'default',
                ];

                if (isset($options['badge'])) {
                    $message['badge'] = (int)$options['badge'];
                }

                // Agrupamento por conversa
                if (!empty($options['thread_id'])) {
                    $message['threadId'] = $options['thread_id']; // iOS
                }
                if (!empty($options['channel_id'])) {
                    $message['channelId'] = $options['channel_id']; // Android
                }

                $messages[] = $message;
            }

            foreach (array_chunk($messages, self::BATCH_SIZE) as $batch) {
                self::sendBatch($batch);
            }
        } catch (\Exception $e) {
            // Push nunca pode quebrar o fluxo principal
            Logger::error("PushNotificationService::sendToUser Error (user {$userId}): " . $e->getMessage());
        }
    }

    /**
     * Enviar push para vários usuários
     */
    public static function sendToUsers(array $userIds, string $title, string $body, array $data = [], array $options = []): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            self::sendToUser($userId, $title, $body, $data, $options);
        }
    }

    /**
     * Enviar um lote de mensagens à Expo Push API e processar erros por token
     */
    private static function sendBatch(array $messages): void
    {
        $payload = json_encode($messages, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Accept-Encoding: gzip, deflate',
        ];

        // Access token opcional da Expo (segurança adicional)
        $accessToken = self::getExpoAccessToken();
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        $ch = curl_init(self::EXPO_PUSH_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            Logger::error("PushNotificationService: falha na Expo Push API (HTTP {$httpCode}): {$curlError} " . substr((string)$response, 0, 300));
            return;
        }

        $result = json_decode($response, true);
        $tickets = $result['data'] ?? [];

        // Processar tickets: revogar tokens inválidos
        foreach ($tickets as $index => $ticket) {
            if (($ticket['status'] ?? '') === 'error') {
                $errorType = $ticket['details']['error'] ?? '';
                $token = $messages[$index]['to'] ?? null;

                if ($token && $errorType === 'DeviceNotRegistered') {
                    DeviceToken::revoke($token);
                    Logger::info("PushNotificationService: token revogado (DeviceNotRegistered): " . substr($token, 0, 30) . "...");
                } else {
                    Logger::error("PushNotificationService: erro no ticket: " . json_encode($ticket));
                }
            }
        }
    }

    /**
     * Registrar presença do usuário (chamado pelo endpoint de polling da API)
     */
    public static function markPresence(int $userId): void
    {
        try {
            $dir = self::presenceDir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($dir . '/user_' . $userId, (string)time());
        } catch (\Exception $e) {
            // Silencioso: presença é best-effort
        }
    }

    /**
     * Verificar se o usuário está presente (poll ativo há menos de PRESENCE_WINDOW segundos)
     */
    public static function isUserPresent(int $userId): bool
    {
        $file = self::presenceDir() . '/user_' . $userId;
        if (!is_file($file)) {
            return false;
        }
        $timestamp = (int)@file_get_contents($file);
        return $timestamp > 0 && (time() - $timestamp) < self::PRESENCE_WINDOW;
    }

    private static function presenceDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/cache/presence';
    }

    private static function getExpoAccessToken(): ?string
    {
        $token = $_ENV['EXPO_ACCESS_TOKEN'] ?? getenv('EXPO_ACCESS_TOKEN') ?: null;
        if ($token) {
            return $token;
        }

        try {
            $setting = \App\Models\Setting::get('expo_access_token');
            return $setting ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
