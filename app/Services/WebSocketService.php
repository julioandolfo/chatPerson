<?php
/**
 * Service WebSocketService
 * Gerencia envio de mensagens via WebSocket
 */

namespace App\Services;

use App\Helpers\Logger;
use App\Services\SettingService;

class WebSocketService
{
    /**
     * URL do servidor WebSocket
     */
    private static string $wsUrl = 'ws://localhost:8080';

    /**
     * Enviar mensagem para clientes conectados via WebSocket
     * Nota: Esta é uma implementação básica. Em produção, use um servidor WebSocket real.
     */
    public static function broadcast(string $event, array $data, ?array $targets = null): void
    {
        // Respeitar configuração: se WebSocket estiver desabilitado ou em modo polling, não tentar enviar
        $settings = SettingService::getDefaultWebSocketSettings();
        $websocketEnabled = $settings['websocket_enabled'] ?? true;
        $connectionType = $settings['websocket_connection_type'] ?? 'auto';

        if (!$websocketEnabled || $connectionType === 'polling') {
            Logger::log("WebSocket Broadcast ignorado ({$event}) - WebSocket desabilitado/mode polling");
            return;
        }

        try {
            $message = json_encode([
                'event' => $event,
                'data' => $data,
                'timestamp' => time()
            ]);

            // Em produção, isso seria enviado via servidor WebSocket real
            // Por enquanto, apenas logamos
            Logger::log("WebSocket Broadcast: {$event} - " . substr($message, 0, 200));
            
            // TODO: Implementar conexão real com servidor WebSocket
            // self::sendToWebSocketServer($message, $targets);
        } catch (\Exception $e) {
            Logger::error("WebSocket Broadcast Error: " . $e->getMessage());
        }
    }

    /**
     * Notificar nova mensagem
     */
    public static function notifyNewMessage(int $conversationId, array $message): void
    {
        self::broadcast('new_message', [
            'conversation_id' => $conversationId,
            'message' => $message
        ]);
    }

    /**
     * Notificar atualização de conversa
     */
    public static function notifyConversationUpdated(int $conversationId, array $conversation): void
    {
        self::broadcast('conversation_updated', [
            'conversation_id' => $conversationId,
            'conversation' => $conversation
        ]);
    }

    /**
     * Notificar nova conversa
     */
    public static function notifyNewConversation(array $conversation): void
    {
        self::broadcast('new_conversation', [
            'conversation' => $conversation
        ]);
    }

    /**
     * Notificar status de agente (online/offline)
     */
    public static function notifyAgentStatus(int $agentId, string $status): void
    {
        self::broadcast('agent_status', [
            'agent_id' => $agentId,
            'status' => $status
        ]);
    }

    /**
     * Notificar indicador de digitação
     */
    public static function notifyTyping(int $conversationId, int $userId, bool $isTyping): void
    {
        self::broadcast('typing', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'is_typing' => $isTyping
        ]);
    }

    /**
     * Notificar leitura de mensagem
     */
    public static function notifyMessageRead(int $conversationId, int $messageId, int $userId): void
    {
        self::broadcast('message_read', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'user_id' => $userId
        ]);
    }

    /**
     * Enviar notificação para usuário específico
     */
    public static function notifyUser(int $userId, string $event, array $data): void
    {
        self::broadcast('user_notification', [
            'user_id' => $userId,
            'event' => $event,
            'data' => $data
        ], ['user_id' => $userId]);
    }
}

