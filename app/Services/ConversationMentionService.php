<?php
/**
 * Service ConversationMentionService
 * 
 * Lógica de negócio para menções/convites de agentes em conversas.
 */

namespace App\Services;

use App\Models\ConversationMention;
use App\Models\ConversationParticipant;
use App\Models\Conversation;
use App\Models\User;
use App\Helpers\Validator;

class ConversationMentionService
{
    /**
     * Mencionar/convidar um agente para uma conversa
     */
    public static function mention(
        int $conversationId,
        int $mentionedUserId,
        int $mentionedBy,
        ?string $note = null,
        ?int $messageId = null,
        ?string $expiresAt = null
    ): array {
        // Validar conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }
        
        // Validar usuário mencionado existe e é agente
        $mentionedUser = User::find($mentionedUserId);
        if (!$mentionedUser) {
            throw new \InvalidArgumentException('Usuário não encontrado');
        }
        
        // Não pode mencionar a si mesmo
        if ($mentionedUserId === $mentionedBy) {
            throw new \InvalidArgumentException('Você não pode se mencionar');
        }
        
        // Verificar se já é participante da conversa
        $isParticipant = ConversationParticipant::isParticipant($conversationId, $mentionedUserId);
        if ($isParticipant) {
            throw new \InvalidArgumentException('Este usuário já é participante da conversa');
        }
        
        // Verificar se é o agente principal da conversa
        if ($conversation['agent_id'] == $mentionedUserId) {
            throw new \InvalidArgumentException('Este usuário já é o agente responsável pela conversa');
        }
        
        // Verificar se já existe menção pendente
        $hasPending = ConversationMention::hasPendingMention($conversationId, $mentionedUserId);
        if ($hasPending) {
            throw new \InvalidArgumentException('Já existe um convite pendente para este usuário nesta conversa');
        }
        
        // Criar menção
        $mentionId = ConversationMention::createMention(
            $conversationId,
            $mentionedBy,
            $mentionedUserId,
            $messageId,
            $note,
            $expiresAt
        );
        
        // Buscar dados completos
        $mention = ConversationMention::findWithDetails($mentionId);
        
        // Criar notificação para o usuário mencionado
        self::createNotification($mention);
        
        // Notificar via WebSocket
        self::notifyWebSocket($mention, 'new_mention');
        
        return $mention;
    }

    /**
     * Aceitar convite de menção
     */
    public static function accept(int $mentionId, int $userId): array
    {
        // Buscar menção
        $mention = ConversationMention::findWithDetails($mentionId);
        if (!$mention) {
            throw new \InvalidArgumentException('Convite não encontrado');
        }
        
        // Verificar se é o usuário correto
        if ($mention['mentioned_user_id'] != $userId) {
            throw new \InvalidArgumentException('Este convite não é para você');
        }
        
        // Verificar se está pendente
        if ($mention['status'] !== 'pending') {
            throw new \InvalidArgumentException('Este convite já foi respondido');
        }
        
        // Verificar se não expirou
        if ($mention['expires_at'] && strtotime($mention['expires_at']) < time()) {
            ConversationMention::decline($mentionId); // Marcar como expirado
            throw new \InvalidArgumentException('Este convite expirou');
        }
        
        // Aceitar menção
        ConversationMention::accept($mentionId);
        
        // Adicionar como participante da conversa
        ConversationParticipant::addParticipant(
            $mention['conversation_id'],
            $userId,
            $mention['mentioned_by']
        );
        
        // Buscar dados atualizados
        $updatedMention = ConversationMention::findWithDetails($mentionId);
        
        // Notificar quem fez a menção
        self::notifyMentionResponse($updatedMention, 'accepted');
        
        // Notificar via WebSocket
        self::notifyWebSocket($updatedMention, 'mention_accepted');
        
        return $updatedMention;
    }

    /**
     * Recusar convite de menção
     */
    public static function decline(int $mentionId, int $userId): array
    {
        // Buscar menção
        $mention = ConversationMention::findWithDetails($mentionId);
        if (!$mention) {
            throw new \InvalidArgumentException('Convite não encontrado');
        }
        
        // Verificar se é o usuário correto
        if ($mention['mentioned_user_id'] != $userId) {
            throw new \InvalidArgumentException('Este convite não é para você');
        }
        
        // Verificar se está pendente
        if ($mention['status'] !== 'pending') {
            throw new \InvalidArgumentException('Este convite já foi respondido');
        }
        
        // Recusar menção
        ConversationMention::decline($mentionId);
        
        // Buscar dados atualizados
        $updatedMention = ConversationMention::findWithDetails($mentionId);
        
        // Notificar quem fez a menção
        self::notifyMentionResponse($updatedMention, 'declined');
        
        // Notificar via WebSocket
        self::notifyWebSocket($updatedMention, 'mention_declined');
        
        return $updatedMention;
    }

    /**
     * Obter convites pendentes para um usuário
     */
    public static function getPendingInvites(int $userId): array
    {
        return ConversationMention::getPendingForUser($userId);
    }

    /**
     * Contar convites pendentes
     */
    public static function countPending(int $userId): int
    {
        return ConversationMention::countPendingForUser($userId);
    }

    /**
     * Obter menções de uma conversa
     */
    public static function getByConversation(int $conversationId): array
    {
        return ConversationMention::getByConversation($conversationId);
    }

    /**
     * Obter histórico de convites do usuário
     */
    public static function getHistory(int $userId, int $limit = 50, int $offset = 0): array
    {
        return ConversationMention::getHistoryForUser($userId, $limit, $offset);
    }

    /**
     * Obter agentes disponíveis para mencionar em uma conversa
     */
    public static function getAvailableAgents(int $conversationId, int $currentUserId): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [];
        }
        
        // Obter participantes atuais
        $participants = ConversationParticipant::getByConversation($conversationId);
        $participantIds = array_column($participants, 'user_id');
        
        // Obter menções pendentes
        $pendingMentions = ConversationMention::getByConversation($conversationId);
        $pendingMentionUserIds = array_column(
            array_filter($pendingMentions, fn($m) => $m['status'] === 'pending'),
            'mentioned_user_id'
        );
        
        // Obter todos os agentes ativos
        $agents = User::getActiveAgents();
        
        // Filtrar agentes que já são participantes, já foram mencionados, ou são o agente principal
        $available = [];
        foreach ($agents as $agent) {
            // Pular se é o agente principal da conversa
            if ($agent['id'] == $conversation['agent_id']) {
                continue;
            }
            
            // Pular se é o usuário atual
            if ($agent['id'] == $currentUserId) {
                continue;
            }
            
            // Pular se já é participante
            if (in_array($agent['id'], $participantIds)) {
                continue;
            }
            
            // Pular se já tem menção pendente
            if (in_array($agent['id'], $pendingMentionUserIds)) {
                continue;
            }
            
            $available[] = $agent;
        }
        
        return $available;
    }

    /**
     * Criar notificação para o usuário mencionado
     */
    private static function createNotification(array $mention): void
    {
        try {
            if (class_exists('\App\Services\NotificationService')) {
                \App\Services\NotificationService::create(
                    $mention['mentioned_user_id'],
                    'mention',
                    'Você foi mencionado em uma conversa',
                    sprintf(
                        '%s mencionou você na conversa com %s',
                        $mention['mentioned_by_name'] ?? 'Um agente',
                        $mention['contact_name'] ?? 'um contato'
                    ),
                    '/conversations?id=' . $mention['conversation_id'],
                    [
                        'mention_id' => $mention['id'],
                        'conversation_id' => $mention['conversation_id'],
                        'mentioned_by' => $mention['mentioned_by']
                    ]
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação de menção: " . $e->getMessage());
        }
    }

    /**
     * Notificar quem fez a menção sobre a resposta
     */
    private static function notifyMentionResponse(array $mention, string $response): void
    {
        try {
            if (class_exists('\App\Services\NotificationService')) {
                $title = $response === 'accepted' 
                    ? 'Convite aceito' 
                    : 'Convite recusado';
                
                $message = sprintf(
                    '%s %s seu convite para participar da conversa com %s',
                    $mention['mentioned_user_name'] ?? 'O agente',
                    $response === 'accepted' ? 'aceitou' : 'recusou',
                    $mention['contact_name'] ?? 'o contato'
                );
                
                \App\Services\NotificationService::create(
                    $mention['mentioned_by'],
                    'mention_response',
                    $title,
                    $message,
                    '/conversations?id=' . $mention['conversation_id'],
                    [
                        'mention_id' => $mention['id'],
                        'conversation_id' => $mention['conversation_id'],
                        'response' => $response
                    ]
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação de resposta: " . $e->getMessage());
        }
    }

    /**
     * Notificar via WebSocket
     */
    private static function notifyWebSocket(array $mention, string $event): void
    {
        try {
            if (class_exists('\App\Helpers\WebSocket')) {
                $data = [
                    'type' => $event,
                    'mention' => $mention
                ];
                
                // Notificar o usuário mencionado (para novos convites)
                if ($event === 'new_mention') {
                    \App\Helpers\WebSocket::notifyUser($mention['mentioned_user_id'], $event, $data);
                }
                
                // Notificar quem fez a menção (para respostas)
                if (in_array($event, ['mention_accepted', 'mention_declined'])) {
                    \App\Helpers\WebSocket::notifyUser($mention['mentioned_by'], $event, $data);
                }
                
                // Notificar todos os participantes da conversa
                \App\Helpers\WebSocket::notifyConversationUpdate($mention['conversation_id'], $data);
            }
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
    }
}

