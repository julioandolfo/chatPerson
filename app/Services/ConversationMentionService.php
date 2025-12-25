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
     * Cancelar convite de menção (quem enviou pode cancelar)
     */
    public static function cancel(int $mentionId, int $userId): array
    {
        // Buscar menção
        $mention = ConversationMention::findWithDetails($mentionId);
        if (!$mention) {
            throw new \InvalidArgumentException('Convite não encontrado');
        }
        
        // Verificar se é quem enviou o convite
        if ($mention['mentioned_by'] != $userId) {
            throw new \InvalidArgumentException('Você não pode cancelar este convite');
        }
        
        // Verificar se está pendente
        if ($mention['status'] !== 'pending') {
            throw new \InvalidArgumentException('Este convite já foi respondido');
        }
        
        // Cancelar menção (usando decline internamente)
        ConversationMention::cancel($mentionId);
        
        // Buscar dados atualizados
        $updatedMention = ConversationMention::findWithDetails($mentionId);
        
        // Notificar via WebSocket
        self::notifyWebSocket($updatedMention, 'mention_cancelled');
        
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
                
                // Notificar para solicitações de participação
                if ($event === 'new_participation_request') {
                    // Notificar agente atribuído e participantes da conversa
                    $conversationId = $mention['conversation_id'];
                    $conversation = Conversation::find($conversationId);
                    
                    // Notificar agente atribuído
                    if (!empty($conversation['agent_id'])) {
                        \App\Helpers\WebSocket::notifyUser($conversation['agent_id'], $event, $data);
                    }
                    
                    // Notificar participantes
                    $participants = ConversationParticipant::getByConversation($conversationId);
                    foreach ($participants as $participant) {
                        \App\Helpers\WebSocket::notifyUser($participant['user_id'], $event, $data);
                    }
                }
                
                // Notificar respostas de solicitações
                if (in_array($event, ['request_approved', 'request_rejected'])) {
                    \App\Helpers\WebSocket::notifyUser($mention['mentioned_by'], $event, $data);
                }
                
                // Notificar todos os participantes da conversa
                \App\Helpers\WebSocket::notifyConversationUpdate($mention['conversation_id'], $data);
            }
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
    }

    // ============================================
    // SISTEMA DE SOLICITAÇÃO DE PARTICIPAÇÃO
    // ============================================

    /**
     * Solicitar participação em uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @param int $requestingUserId ID do usuário que está solicitando
     * @param string|null $note Motivo/nota da solicitação
     * @return array Dados da solicitação criada
     */
    public static function requestParticipation(
        int $conversationId,
        int $requestingUserId,
        ?string $note = null
    ): array {
        // Validar conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }
        
        // Verificar se já é participante
        $isParticipant = ConversationParticipant::isParticipant($conversationId, $requestingUserId);
        if ($isParticipant) {
            throw new \InvalidArgumentException('Você já é participante desta conversa');
        }
        
        // Verificar se é o agente principal da conversa
        if ($conversation['agent_id'] == $requestingUserId) {
            throw new \InvalidArgumentException('Você já é o agente responsável por esta conversa');
        }
        
        // Verificar se já existe solicitação pendente
        $hasPending = ConversationMention::hasPendingRequest($conversationId, $requestingUserId);
        if ($hasPending) {
            throw new \InvalidArgumentException('Você já tem uma solicitação pendente para esta conversa');
        }
        
        // Criar solicitação (tipo request)
        // mentioned_by = quem está solicitando
        // mentioned_user_id = agente atribuído (quem precisa aprovar) ou 0 se não tiver
        $approverId = $conversation['agent_id'] ?? 0;
        
        $requestId = ConversationMention::createRequest(
            $conversationId,
            $requestingUserId,
            $approverId,
            $note
        );
        
        // Buscar dados completos
        $request = ConversationMention::findWithDetails($requestId);
        
        // Criar notificação para quem precisa aprovar
        self::createRequestNotification($request, $conversation);
        
        // Notificar via WebSocket
        self::notifyWebSocket($request, 'new_participation_request');
        
        return $request;
    }

    /**
     * Aprovar solicitação de participação
     * 
     * @param int $requestId ID da solicitação
     * @param int $approverId ID do usuário que está aprovando
     * @return array Dados da solicitação atualizada
     */
    public static function approveRequest(int $requestId, int $approverId): array
    {
        // Buscar solicitação
        $request = ConversationMention::findWithDetails($requestId);
        if (!$request) {
            throw new \InvalidArgumentException('Solicitação não encontrada');
        }
        
        // Verificar se é uma solicitação (não convite)
        if (($request['type'] ?? 'invite') !== 'request') {
            throw new \InvalidArgumentException('Esta não é uma solicitação de participação');
        }
        
        // Verificar se está pendente
        if ($request['status'] !== 'pending') {
            throw new \InvalidArgumentException('Esta solicitação já foi respondida');
        }
        
        // Verificar se quem está aprovando tem permissão
        // (é o agente atribuído ou participante da conversa)
        $conversation = Conversation::find($request['conversation_id']);
        $isAgentAtribuido = $conversation && $conversation['agent_id'] == $approverId;
        $isParticipante = ConversationParticipant::isParticipant($request['conversation_id'], $approverId);
        
        if (!$isAgentAtribuido && !$isParticipante) {
            // Verificar se é admin/supervisor
            $approverLevel = User::getMaxLevel($approverId);
            if ($approverLevel > 2) { // Nível > 2 = não é admin/supervisor
                throw new \InvalidArgumentException('Você não tem permissão para aprovar esta solicitação');
            }
        }
        
        // Aprovar solicitação
        ConversationMention::accept($requestId);
        
        // Adicionar como participante da conversa
        ConversationParticipant::addParticipant(
            $request['conversation_id'],
            $request['mentioned_by'], // Quem solicitou
            $approverId // Quem aprovou
        );
        
        // Buscar dados atualizados
        $updatedRequest = ConversationMention::findWithDetails($requestId);
        
        // Notificar quem solicitou
        self::notifyRequestResponse($updatedRequest, 'approved', $approverId);
        
        // Notificar via WebSocket
        self::notifyWebSocket($updatedRequest, 'request_approved');
        
        return $updatedRequest;
    }

    /**
     * Recusar solicitação de participação
     * 
     * @param int $requestId ID da solicitação
     * @param int $rejecterId ID do usuário que está recusando
     * @return array Dados da solicitação atualizada
     */
    public static function rejectRequest(int $requestId, int $rejecterId): array
    {
        // Buscar solicitação
        $request = ConversationMention::findWithDetails($requestId);
        if (!$request) {
            throw new \InvalidArgumentException('Solicitação não encontrada');
        }
        
        // Verificar se é uma solicitação (não convite)
        if (($request['type'] ?? 'invite') !== 'request') {
            throw new \InvalidArgumentException('Esta não é uma solicitação de participação');
        }
        
        // Verificar se está pendente
        if ($request['status'] !== 'pending') {
            throw new \InvalidArgumentException('Esta solicitação já foi respondida');
        }
        
        // Verificar se quem está recusando tem permissão
        $conversation = Conversation::find($request['conversation_id']);
        $isAgentAtribuido = $conversation && $conversation['agent_id'] == $rejecterId;
        $isParticipante = ConversationParticipant::isParticipant($request['conversation_id'], $rejecterId);
        
        if (!$isAgentAtribuido && !$isParticipante) {
            // Verificar se é admin/supervisor
            $rejecterLevel = User::getMaxLevel($rejecterId);
            if ($rejecterLevel > 2) {
                throw new \InvalidArgumentException('Você não tem permissão para recusar esta solicitação');
            }
        }
        
        // Recusar solicitação
        ConversationMention::decline($requestId);
        
        // Buscar dados atualizados
        $updatedRequest = ConversationMention::findWithDetails($requestId);
        
        // Notificar quem solicitou
        self::notifyRequestResponse($updatedRequest, 'rejected', $rejecterId);
        
        // Notificar via WebSocket
        self::notifyWebSocket($updatedRequest, 'request_rejected');
        
        return $updatedRequest;
    }

    /**
     * Obter solicitações pendentes para uma conversa
     */
    public static function getPendingRequestsForConversation(int $conversationId): array
    {
        return ConversationMention::getPendingRequestsForConversation($conversationId);
    }

    /**
     * Verificar se usuário tem solicitação pendente para uma conversa
     */
    public static function hasPendingRequest(int $conversationId, int $userId): bool
    {
        return ConversationMention::hasPendingRequest($conversationId, $userId);
    }

    /**
     * Verificar acesso do usuário a uma conversa
     * Retorna informações sobre o tipo de acesso
     * 
     * @return array ['can_view' => bool, 'is_participant' => bool, 'is_assigned' => bool, 'has_pending_request' => bool]
     */
    public static function checkUserAccess(int $conversationId, int $userId): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [
                'can_view' => false,
                'is_participant' => false,
                'is_assigned' => false,
                'has_pending_request' => false,
                'reason' => 'conversation_not_found'
            ];
        }
        
        // Verificar se é o agente atribuído
        $isAssigned = $conversation['agent_id'] == $userId;
        
        // Verificar se é participante
        $isParticipant = ConversationParticipant::isParticipant($conversationId, $userId);
        
        // Verificar se tem solicitação pendente
        $hasPendingRequest = ConversationMention::hasPendingRequest($conversationId, $userId);
        
        // Usuário pode ver se é atribuído OU participante
        $canView = $isAssigned || $isParticipant;
        
        return [
            'can_view' => $canView,
            'is_participant' => $isParticipant,
            'is_assigned' => $isAssigned,
            'has_pending_request' => $hasPendingRequest,
            'conversation' => $conversation,
            'reason' => $canView ? 'authorized' : 'not_authorized'
        ];
    }

    /**
     * Criar notificação para solicitação de participação
     */
    private static function createRequestNotification(array $request, array $conversation): void
    {
        try {
            if (!class_exists('\App\Services\NotificationService')) {
                return;
            }
            
            $requesterName = $request['mentioned_by_name'] ?? 'Um agente';
            $contactName = $request['contact_name'] ?? 'um contato';
            
            // Notificar agente atribuído
            if (!empty($conversation['agent_id'])) {
                \App\Services\NotificationService::create(
                    $conversation['agent_id'],
                    'participation_request',
                    'Solicitação de participação',
                    sprintf(
                        '%s está solicitando participar da conversa com %s',
                        $requesterName,
                        $contactName
                    ),
                    '/conversations?id=' . $request['conversation_id'],
                    [
                        'request_id' => $request['id'],
                        'conversation_id' => $request['conversation_id'],
                        'requester_id' => $request['mentioned_by']
                    ]
                );
            }
            
            // Notificar participantes
            $participants = ConversationParticipant::getByConversation($request['conversation_id']);
            foreach ($participants as $participant) {
                \App\Services\NotificationService::create(
                    $participant['user_id'],
                    'participation_request',
                    'Solicitação de participação',
                    sprintf(
                        '%s está solicitando participar da conversa com %s',
                        $requesterName,
                        $contactName
                    ),
                    '/conversations?id=' . $request['conversation_id'],
                    [
                        'request_id' => $request['id'],
                        'conversation_id' => $request['conversation_id'],
                        'requester_id' => $request['mentioned_by']
                    ]
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação de solicitação: " . $e->getMessage());
        }
    }

    /**
     * Notificar resposta de solicitação
     */
    private static function notifyRequestResponse(array $request, string $response, int $responderId): void
    {
        try {
            if (!class_exists('\App\Services\NotificationService')) {
                return;
            }
            
            $responderName = User::find($responderId)['name'] ?? 'Um agente';
            $contactName = $request['contact_name'] ?? 'um contato';
            
            $title = $response === 'approved' 
                ? 'Solicitação aprovada!' 
                : 'Solicitação recusada';
            
            $message = $response === 'approved'
                ? sprintf('%s aprovou sua participação na conversa com %s', $responderName, $contactName)
                : sprintf('%s recusou sua solicitação de participação na conversa com %s', $responderName, $contactName);
            
            \App\Services\NotificationService::create(
                $request['mentioned_by'], // Quem solicitou
                'participation_response',
                $title,
                $message,
                '/conversations?id=' . $request['conversation_id'],
                [
                    'request_id' => $request['id'],
                    'conversation_id' => $request['conversation_id'],
                    'response' => $response,
                    'responder_id' => $responderId
                ]
            );
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação de resposta: " . $e->getMessage());
        }
    }
}

