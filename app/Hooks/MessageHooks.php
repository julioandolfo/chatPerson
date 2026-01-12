<?php
/**
 * Hooks para Mensagens
 * Executados após criar/atualizar mensagens
 * para marcar contatos para recálculo de métricas
 */

namespace App\Hooks;

use App\Services\ContactMetricsService;
use App\Models\Conversation;

class MessageHooks
{
    /**
     * Hook: Após criar mensagem
     * Marca contato para recálculo de métricas
     * 
     * Chamar em:
     * - WhatsAppService::processIncomingMessage()
     * - ConversationService::sendMessage()
     * - Qualquer lugar que cria mensagens
     */
    public static function afterCreate(int $messageId, array $messageData): void
    {
        try {
            // Obter conversation_id da mensagem
            $conversationId = $messageData['conversation_id'] ?? null;
            
            if (!$conversationId) {
                return;
            }
            
            // Obter contact_id da conversa
            $conversation = Conversation::find($conversationId);
            
            if (!$conversation || !$conversation['contact_id']) {
                return;
            }
            
            $contactId = $conversation['contact_id'];
            
            // Determinar se é urgente (conversa aberta)
            $isUrgent = in_array($conversation['status'], ['open', 'pending']);
            
            // Marcar para recálculo
            ContactMetricsService::onNewMessage($contactId, $isUrgent);
            
        } catch (\Exception $e) {
            // Não falhar se hook der erro
            error_log("Erro no MessageHooks::afterCreate: " . $e->getMessage());
        }
    }
    
    /**
     * Hook: Após atualizar conversa (mudança de status)
     * Marca contato para recálculo se conversa foi fechada
     */
    public static function afterConversationUpdate(int $conversationId, array $oldData, array $newData): void
    {
        try {
            // Verificar se status mudou para fechado
            $oldStatus = $oldData['status'] ?? null;
            $newStatus = $newData['status'] ?? null;
            
            if ($oldStatus === $newStatus) {
                return; // Status não mudou
            }
            
            // Se mudou para fechado/resolvido
            if (in_array($newStatus, ['closed', 'resolved'])) {
                $conversation = Conversation::find($conversationId);
                
                if ($conversation && $conversation['contact_id']) {
                    ContactMetricsService::onConversationClosed($conversation['contact_id']);
                }
            }
            // Se mudou para aberto (reabriu conversa)
            elseif (in_array($newStatus, ['open', 'pending'])) {
                $conversation = Conversation::find($conversationId);
                
                if ($conversation && $conversation['contact_id']) {
                    ContactMetricsService::onNewMessage($conversation['contact_id'], true);
                }
            }
            
        } catch (\Exception $e) {
            // Não falhar se hook der erro
            error_log("Erro no MessageHooks::afterConversationUpdate: " . $e->getMessage());
        }
    }
}
