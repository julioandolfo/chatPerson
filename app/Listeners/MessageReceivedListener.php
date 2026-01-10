<?php

namespace App\Listeners;

use App\Services\RealtimeCoachingService;
use App\Models\Message;
use App\Models\Conversation;

/**
 * Listener para quando uma nova mensagem é recebida
 * 
 * Dispara análise de coaching em tempo real
 */
class MessageReceivedListener
{
    /**
     * Processar mensagem recebida
     */
    public static function handle(int $messageId): void
    {
        try {
            $message = Message::find($messageId);
            if (!$message) {
                return;
            }
            
            // Só processar mensagens de clientes
            if ($message['sender_type'] !== 'contact') {
                return;
            }
            
            // Obter conversa
            $conversation = Conversation::find($message['conversation_id']);
            if (!$conversation || !$conversation['agent_id']) {
                return; // Sem agente atribuído
            }
            
            // Adicionar na fila de coaching
            RealtimeCoachingService::queueMessageForAnalysis(
                $messageId,
                $message['conversation_id'],
                $conversation['agent_id']
            );
            
        } catch (\Exception $e) {
            error_log("[MessageReceivedListener] Erro: " . $e->getMessage());
        }
    }
}
