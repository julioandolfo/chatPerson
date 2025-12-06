<?php
/**
 * Service ActivityService
 * Lógica de negócio para atividades e auditoria
 */

namespace App\Services;

use App\Models\Activity;

class ActivityService
{
    /**
     * Log de atividade de atribuição de conversa
     */
    public static function logConversationAssigned(int $conversationId, int $agentId, ?int $oldAgentId = null): void
    {
        $agent = \App\Models\User::find($agentId);
        $oldAgent = $oldAgentId ? \App\Models\User::find($oldAgentId) : null;
        
        $description = $oldAgentId 
            ? "Conversa atribuída de {$oldAgent['name']} para {$agent['name']}"
            : "Conversa atribuída para {$agent['name']}";
        
        Activity::log(
            'conversation_assigned',
            'conversation',
            $conversationId,
            null, // Sistema ou usuário que atribuiu
            $description,
            [
                'agent_id' => $agentId,
                'agent_name' => $agent['name'],
                'old_agent_id' => $oldAgentId,
                'old_agent_name' => $oldAgent ? $oldAgent['name'] : null
            ]
        );
    }

    /**
     * Log de atividade de fechamento de conversa
     */
    public static function logConversationClosed(int $conversationId, ?int $userId = null): void
    {
        $conversation = \App\Models\Conversation::find($conversationId);
        $contact = $conversation ? \App\Models\Contact::find($conversation['contact_id']) : null;
        
        $description = $contact 
            ? "Conversa fechada com {$contact['name']}"
            : "Conversa fechada";
        
        Activity::log(
            'conversation_closed',
            'conversation',
            $conversationId,
            $userId,
            $description,
            [
                'contact_id' => $conversation['contact_id'] ?? null,
                'contact_name' => $contact ? $contact['name'] : null
            ]
        );
    }

    /**
     * Log de atividade de reabertura de conversa
     */
    public static function logConversationReopened(int $conversationId, ?int $userId = null): void
    {
        $conversation = \App\Models\Conversation::find($conversationId);
        $contact = $conversation ? \App\Models\Contact::find($conversation['contact_id']) : null;
        
        $description = $contact 
            ? "Conversa reaberta com {$contact['name']}"
            : "Conversa reaberta";
        
        Activity::log(
            'conversation_reopened',
            'conversation',
            $conversationId,
            $userId,
            $description,
            [
                'contact_id' => $conversation['contact_id'] ?? null,
                'contact_name' => $contact ? $contact['name'] : null
            ]
        );
    }

    /**
     * Log de atividade de envio de mensagem
     */
    public static function logMessageSent(int $messageId, int $conversationId, string $senderType, ?int $senderId = null): void
    {
        $description = $senderType === 'agent' 
            ? "Mensagem enviada"
            : "Mensagem recebida do contato";
        
        Activity::log(
            'message_sent',
            'message',
            $messageId,
            $senderType === 'agent' ? $senderId : null,
            $description,
            [
                'conversation_id' => $conversationId,
                'sender_type' => $senderType,
                'sender_id' => $senderId
            ]
        );
    }

    /**
     * Log de atividade de movimentação no funil
     */
    public static function logStageMoved(int $conversationId, int $newStageId, ?int $oldStageId = null, ?int $userId = null): void
    {
        $newStage = \App\Models\FunnelStage::find($newStageId);
        $oldStage = $oldStageId ? \App\Models\FunnelStage::find($oldStageId) : null;
        
        $description = $oldStageId
            ? "Conversa movida de '{$oldStage['name']}' para '{$newStage['name']}'"
            : "Conversa movida para '{$newStage['name']}'";
        
        Activity::log(
            'stage_moved',
            'conversation',
            $conversationId,
            $userId,
            $description,
            [
                'new_stage_id' => $newStageId,
                'new_stage_name' => $newStage['name'],
                'old_stage_id' => $oldStageId,
                'old_stage_name' => $oldStage ? $oldStage['name'] : null,
                'funnel_id' => $newStage['funnel_id']
            ]
        );
    }

    /**
     * Log de atividade de adição de tag
     */
    public static function logTagAdded(int $conversationId, string $tagName, ?int $userId = null): void
    {
        Activity::log(
            'tag_added',
            'conversation',
            $conversationId,
            $userId,
            "Tag '{$tagName}' adicionada à conversa",
            ['tag_name' => $tagName]
        );
    }

    /**
     * Log de atividade de remoção de tag
     */
    public static function logTagRemoved(int $conversationId, string $tagName, ?int $userId = null): void
    {
        Activity::log(
            'tag_removed',
            'conversation',
            $conversationId,
            $userId,
            "Tag '{$tagName}' removida da conversa",
            ['tag_name' => $tagName]
        );
    }

    /**
     * Log de atividade de criação de usuário
     */
    public static function logUserCreated(int $userId, string $userName, ?int $createdBy = null): void
    {
        Activity::log(
            'user_created',
            'user',
            $userId,
            $createdBy,
            "Usuário '{$userName}' criado",
            ['user_name' => $userName]
        );
    }

    /**
     * Log de atividade de atualização de usuário
     */
    public static function logUserUpdated(int $userId, string $userName, array $changes = [], ?int $updatedBy = null): void
    {
        $changesList = [];
        foreach ($changes as $field => $value) {
            $changesList[] = "{$field}: {$value}";
        }
        
        $description = !empty($changesList)
            ? "Usuário '{$userName}' atualizado: " . implode(', ', $changesList)
            : "Usuário '{$userName}' atualizado";
        
        Activity::log(
            'user_updated',
            'user',
            $userId,
            $updatedBy,
            $description,
            ['user_name' => $userName, 'changes' => $changes]
        );
    }

    /**
     * Log de atividade de alteração de disponibilidade
     */
    public static function logAvailabilityChanged(int $userId, string $newStatus, string $oldStatus, ?int $changedBy = null): void
    {
        $user = \App\Models\User::find($userId);
        $statusLabels = [
            'online' => 'Online',
            'offline' => 'Offline',
            'away' => 'Ausente',
            'busy' => 'Ocupado'
        ];
        
        $description = "Status de disponibilidade de '{$user['name']}' alterado de '{$statusLabels[$oldStatus]}' para '{$statusLabels[$newStatus]}'";
        
        Activity::log(
            'availability_changed',
            'user',
            $userId,
            $changedBy ?? $userId,
            $description,
            [
                'user_name' => $user['name'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]
        );
    }
}

