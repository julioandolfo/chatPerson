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
        $logFile = __DIR__ . '/../../logs/coaching.log';
        
        try {
            $message = Message::find($messageId);
            if (!$message) {
                self::log($logFile, "⚠️ Mensagem #{$messageId} não encontrada");
                return;
            }
            
            self::log($logFile, "📩 Nova mensagem recebida - ID: {$messageId}, Conversa: {$message['conversation_id']}, Tipo: {$message['sender_type']}");
            
            // Só processar mensagens de clientes
            if ($message['sender_type'] !== 'contact') {
                self::log($logFile, "⏭️ Pulando - Não é mensagem de cliente (tipo: {$message['sender_type']})");
                return;
            }
            
            // Obter conversa
            $conversation = Conversation::find($message['conversation_id']);
            if (!$conversation || !$conversation['agent_id']) {
                self::log($logFile, "⏭️ Pulando - Conversa sem agente atribuído");
                return; // Sem agente atribuído
            }
            
            self::log($logFile, "👤 Agente atribuído: ID {$conversation['agent_id']}");
            
            // Adicionar na fila de coaching
            $queued = RealtimeCoachingService::queueMessageForAnalysis(
                $messageId,
                $message['conversation_id'],
                $conversation['agent_id']
            );
            
            if ($queued) {
                self::log($logFile, "✅ Mensagem adicionada na fila de coaching");
            } else {
                self::log($logFile, "⏭️ Mensagem NÃO adicionada na fila (veja logs de filtros abaixo)");
            }
            
        } catch (\Exception $e) {
            self::log($logFile, "❌ ERRO: " . $e->getMessage());
            error_log("[MessageReceivedListener] Erro: " . $e->getMessage());
        }
    }
    
    private static function log(string $file, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}
