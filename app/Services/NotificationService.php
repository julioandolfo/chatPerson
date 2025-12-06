<?php
/**
 * Service NotificationService
 * Gerencia criação e envio de notificações
 */

namespace App\Services;

use App\Models\Notification;
use App\Helpers\Logger;

class NotificationService
{
    /**
     * Criar notificação
     */
    public static function create(array $data): int
    {
        $errors = [];
        
        if (empty($data['user_id'])) {
            $errors[] = 'user_id é obrigatório';
        }
        if (empty($data['type'])) {
            $errors[] = 'type é obrigatório';
        }
        if (empty($data['title'])) {
            $errors[] = 'title é obrigatório';
        }
        if (empty($data['message'])) {
            $errors[] = 'message é obrigatório';
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . implode(', ', $errors));
        }
        
        return Notification::createNotification($data);
    }

    /**
     * Notificar nova mensagem
     */
    public static function notifyNewMessage(int $userId, int $conversationId, array $message): int
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'message',
            'title' => 'Nova mensagem',
            'message' => 'Você recebeu uma nova mensagem',
            'link' => '/conversations/' . $conversationId,
            'data' => [
                'conversation_id' => $conversationId,
                'message_id' => $message['id'] ?? null
            ]
        ]);
    }

    /**
     * Notificar conversa atribuída
     */
    public static function notifyConversationAssigned(int $userId, int $conversationId, string $contactName): int
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'assignment',
            'title' => 'Conversa atribuída',
            'message' => 'Uma conversa com ' . $contactName . ' foi atribuída a você',
            'link' => '/conversations/' . $conversationId,
            'data' => [
                'conversation_id' => $conversationId,
                'contact_name' => $contactName
            ]
        ]);
    }

    /**
     * Notificar conversa fechada
     */
    public static function notifyConversationClosed(int $userId, int $conversationId, string $contactName): int
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'conversation',
            'title' => 'Conversa fechada',
            'message' => 'A conversa com ' . $contactName . ' foi fechada',
            'link' => '/conversations/' . $conversationId,
            'data' => [
                'conversation_id' => $conversationId
            ]
        ]);
    }

    /**
     * Notificar conversa reaberta
     */
    public static function notifyConversationReopened(int $userId, int $conversationId, string $contactName): int
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'conversation',
            'title' => 'Conversa reaberta',
            'message' => 'A conversa com ' . $contactName . ' foi reaberta',
            'link' => '/conversations/' . $conversationId,
            'data' => [
                'conversation_id' => $conversationId
            ]
        ]);
    }

    /**
     * Notificar múltiplos usuários
     */
    public static function notifyMultiple(array $userIds, array $data): array
    {
        $notificationIds = [];
        foreach ($userIds as $userId) {
            try {
                $data['user_id'] = $userId;
                $notificationIds[] = self::create($data);
            } catch (\Exception $e) {
                Logger::error("Erro ao criar notificação para usuário {$userId}: " . $e->getMessage());
            }
        }
        return $notificationIds;
    }

    /**
     * Notificar setor inteiro
     */
    public static function notifyDepartment(int $departmentId, array $data): array
    {
        // Obter agentes do setor
        $agents = \App\Models\Department::getAgents($departmentId);
        $userIds = array_column($agents, 'id');
        
        if (empty($userIds)) {
            return [];
        }
        
        return self::notifyMultiple($userIds, $data);
    }

    /**
     * Obter notificações não lidas do usuário atual
     */
    public static function getUnreadForCurrentUser(int $limit = 20): array
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            return [];
        }
        
        return Notification::getUnread($userId, $limit);
    }

    /**
     * Contar notificações não lidas do usuário atual
     */
    public static function countUnreadForCurrentUser(): int
    {
        $userId = \App\Helpers\Auth::id();
        if (!$userId) {
            return 0;
        }
        
        return Notification::countUnread($userId);
    }

    /**
     * Notificar usuário específico (método genérico)
     */
    public static function notifyUser(int $userId, string $event, array $data): int
    {
        $title = $data['title'] ?? 'Notificação';
        $message = $data['message'] ?? '';
        $link = $data['link'] ?? null;
        $type = $data['type'] ?? $event;
        
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'data' => $data['data'] ?? $data
        ]);
    }

    /**
     * Notificar conversa reatribuída
     */
    public static function notifyConversationReassigned(int $userId, int $conversationId, string $reason): int
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'reassignment',
            'title' => 'Conversa reatribuída',
            'message' => $reason,
            'link' => '/conversations/' . $conversationId,
            'data' => [
                'conversation_id' => $conversationId,
                'reason' => $reason
            ]
        ]);
    }
}

