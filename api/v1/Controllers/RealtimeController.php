<?php
/**
 * RealtimeController - API v1
 * Polling de atualizações em tempo real para o app mobile (JWT/API Token).
 * Porta a lógica de App\Controllers\RealtimeController::poll para a superfície da API.
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;

class RealtimeController
{
    /**
     * Configuração de tempo real para o app
     * GET /api/v1/realtime/config
     */
    public function config(): void
    {
        try {
            $settings = \App\Services\SettingService::getDefaultWebSocketSettings();

            ApiResponse::success([
                'polling_interval' => max((int)($settings['websocket_polling_interval'] ?? 3000), 3000),
                'connection_type' => 'polling',
            ]);
        } catch (\Exception $e) {
            ApiResponse::success([
                'polling_interval' => 5000,
                'connection_type' => 'polling',
            ]);
        }
    }

    /**
     * Polling de atualizações
     * POST /api/v1/realtime/poll
     * Body: { subscribed_conversations: number[], last_update_time: number(ms), activity_type?: string }
     */
    public function poll(): void
    {
        $userId = ApiAuthMiddleware::userId();

        if (!ApiAuthMiddleware::can('conversations.view.own') && !ApiAuthMiddleware::can('conversations.view.all')) {
            ApiResponse::forbidden('Acesso negado');
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?: [];

            $subscribedConversations = isset($data['subscribed_conversations']) && is_array($data['subscribed_conversations'])
                ? array_map('intval', $data['subscribed_conversations'])
                : [];

            $lastUpdateTime = isset($data['last_update_time']) && !is_array($data['last_update_time'])
                ? (int)$data['last_update_time']
                : 0;

            // Registrar presença (supressão de push) e heartbeat de disponibilidade
            \App\Services\PushNotificationService::markPresence($userId);

            $activityType = isset($data['activity_type']) ? (string)$data['activity_type'] : null;
            if ($activityType && $activityType !== 'background') {
                try {
                    \App\Services\AvailabilityService::updateActivity($userId, $activityType);
                    \App\Services\AvailabilityService::processHeartbeat($userId);
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("API Realtime: erro no heartbeat: " . $e->getMessage());
                }
            }

            $updates = [
                'new_messages' => [],
                'conversation_updates' => [],
                'new_conversations' => [],
                'message_status_updates' => [],
            ];

            $lastUpdateSeconds = $lastUpdateTime > 0 ? (int)($lastUpdateTime / 1000) : 0;
            $lastMessageTime = $lastUpdateSeconds > 0 ? date('Y-m-d H:i:s', $lastUpdateSeconds) : null;

            // 1) Novas mensagens nas conversas inscritas (conversa aberta no app)
            foreach ($subscribedConversations as $convId) {
                if ($convId <= 0) {
                    continue;
                }

                try {
                    $messages = Message::getNewMessagesSince($convId, $lastMessageTime);

                    foreach ($messages as $msg) {
                        if (!isset($msg['id'])) {
                            continue;
                        }

                        $updates['new_messages'][] = [
                            'conversation_id' => $convId,
                            'id' => $msg['id'],
                            'content' => $msg['content'] ?? '',
                            'sender_type' => $msg['sender_type'] ?? 'contact',
                            'sender_id' => $msg['sender_id'] ?? null,
                            'sender_name' => $msg['sender_name'] ?? null,
                            'created_at' => $msg['created_at'] ?? date('Y-m-d H:i:s'),
                            'message_type' => $msg['message_type'] ?? 'text',
                            'is_note' => (($msg['message_type'] ?? '') === 'note'),
                            'attachments' => $msg['attachments'] ?? [],
                            'status' => $msg['status'] ?? 'sent',
                            'quoted_message_id' => $msg['quoted_message_id'] ?? null,
                            'quoted_text' => $msg['quoted_text'] ?? null,
                            'quoted_sender_name' => $msg['quoted_sender_name'] ?? null,
                            'ai_agent_id' => $msg['ai_agent_id'] ?? null,
                            'delivered_at' => $msg['delivered_at'] ?? null,
                            'read_at' => $msg['read_at'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("API Realtime: erro na conversa {$convId}: " . $e->getMessage());
                }
            }

            // 2) Atualizações de status de mensagens (entregue/lido)
            try {
                $statusUpdates = Message::getStatusUpdatesSince($lastMessageTime);
                foreach ($statusUpdates as $su) {
                    $updates['message_status_updates'][] = [
                        'conversation_id' => $su['conversation_id'],
                        'message_id' => $su['id'],
                        'status' => $su['status'],
                        'delivered_at' => $su['delivered_at'] ?? null,
                        'read_at' => $su['read_at'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("API Realtime: erro em status updates: " . $e->getMessage());
            }

            // 3) Conversas do usuário criadas/atualizadas desde o último poll
            try {
                $userConversations = ConversationService::getUserConversations($userId);
                $checkedCount = 0;
                $maxChecks = 50;

                if (is_array($userConversations)) {
                    foreach ($userConversations as $conv) {
                        if ($checkedCount >= $maxChecks) {
                            break;
                        }
                        $checkedCount++;

                        if (!isset($conv['id'], $conv['updated_at'])) {
                            continue;
                        }

                        $updatedAt = strtotime($conv['updated_at']);
                        if ($updatedAt === false || ($lastUpdateSeconds > 0 && $updatedAt <= $lastUpdateSeconds)) {
                            continue;
                        }

                        // Primeira requisição (lastUpdateTime=0): não inundar com o estado inicial
                        if ($lastUpdateSeconds === 0) {
                            continue;
                        }

                        if (($conv['status'] ?? 'open') !== 'open') {
                            continue;
                        }

                        $isNew = false;
                        if (!empty($conv['created_at'])) {
                            $createdAt = strtotime($conv['created_at']);
                            $isNew = ($createdAt !== false && $createdAt > $lastUpdateSeconds);
                        }

                        $conversationData = [
                            'id' => $conv['id'],
                            'status' => $conv['status'] ?? 'open',
                            'unread_count' => $conv['unread_count'] ?? 0,
                            'updated_at' => $conv['updated_at'],
                            'created_at' => $conv['created_at'] ?? $conv['updated_at'],
                            'contact_name' => $conv['contact_name'] ?? null,
                            'contact_avatar' => $conv['contact_avatar'] ?? null,
                            'last_message' => $conv['last_message'] ?? null,
                            'last_message_at' => $conv['last_message_at'] ?? null,
                            'channel' => $conv['channel'] ?? 'whatsapp',
                            'agent_id' => $conv['agent_id'] ?? null,
                            'department_id' => $conv['department_id'] ?? null,
                            'funnel_id' => $conv['funnel_id'] ?? null,
                            'funnel_stage_id' => $conv['funnel_stage_id'] ?? null,
                            'pinned' => $conv['pinned'] ?? 0,
                        ];

                        if ($isNew) {
                            $updates['new_conversations'][] = $conversationData;
                        } else {
                            $updates['conversation_updates'][] = $conversationData;
                        }
                    }
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("API Realtime: erro nas conversas do usuário: " . $e->getMessage());
            }

            ApiResponse::success(array_merge($updates, [
                'timestamp' => (int)(microtime(true) * 1000),
            ]));
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao verificar atualizações', $e);
        }
    }
}
