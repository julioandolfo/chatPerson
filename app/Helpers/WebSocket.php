<?php
/**
 * Helper WebSocket
 * Facilita envio de mensagens via WebSocket
 */

namespace App\Helpers;

use App\Services\WebSocketService;

class WebSocket
{
    /**
     * Enviar mensagem para clientes conectados
     */
    public static function broadcast(string $event, array $data, ?array $targets = null): void
    {
        WebSocketService::broadcast($event, $data, $targets);
    }

    /**
     * Notificar nova mensagem
     */
    public static function notifyNewMessage(int $conversationId, array $message): void
    {
        WebSocketService::notifyNewMessage($conversationId, $message);
    }

    /**
     * Notificar atualização de conversa
     */
    public static function notifyConversationUpdated(int $conversationId, array $conversation): void
    {
        WebSocketService::notifyConversationUpdated($conversationId, $conversation);
    }

    /**
     * Notificar nova conversa
     */
    public static function notifyNewConversation(array $conversation): void
    {
        WebSocketService::notifyNewConversation($conversation);
    }

    /**
     * Notificar status de agente
     */
    public static function notifyAgentStatus(int $agentId, string $status): void
    {
        WebSocketService::notifyAgentStatus($agentId, $status);
    }

    /**
     * Notificar indicador de digitação
     */
    public static function notifyTyping(int $conversationId, int $userId, bool $isTyping): void
    {
        WebSocketService::notifyTyping($conversationId, $userId, $isTyping);
    }

    /**
     * Notificar leitura de mensagem
     */
    public static function notifyMessageRead(int $conversationId, int $messageId, int $userId): void
    {
        WebSocketService::notifyMessageRead($conversationId, $messageId, $userId);
    }

    /**
     * Notificar usuário específico
     */
    public static function notifyUser(int $userId, string $event, array $data): void
    {
        WebSocketService::notifyUser($userId, $event, $data);
    }

    /**
     * Notificar atualização de status de mensagem
     */
    public static function notifyMessageStatusUpdated(int $conversationId, int $messageId, string $status): void
    {
        WebSocketService::broadcast('message_status_updated', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'status' => $status
        ]);
    }
}

