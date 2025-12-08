<?php
/**
 * Service ReminderService
 * Lógica de negócio para lembretes de conversas
 */

namespace App\Services;

use App\Models\ConversationReminder;
use App\Models\Conversation;
use App\Models\Notification;
use App\Helpers\Validator;
use App\Helpers\Logger;

class ReminderService
{
    /**
     * Criar lembrete
     */
    public static function create(
        int $conversationId, 
        int $userId, 
        string $reminderAt, 
        ?string $note = null
    ): int {
        // Validar conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        // Validar data/hora (deve ser futuro)
        $reminderDateTime = new \DateTime($reminderAt);
        $now = new \DateTime();
        if ($reminderDateTime <= $now) {
            throw new \InvalidArgumentException('Data/hora do lembrete deve ser no futuro');
        }

        // Preparar dados
        $data = [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'reminder_at' => $reminderDateTime->format('Y-m-d H:i:s'),
            'is_resolved' => 0
        ];

        if ($note !== null) {
            $data['note'] = $note;
        }

        // Criar lembrete
        $id = ConversationReminder::create($data);

        Logger::info("Lembrete criado: ID={$id}, Conversation={$conversationId}, ReminderAt={$reminderAt}");

        return $id;
    }

    /**
     * Processar lembretes pendentes
     */
    public static function processPending(int $limit = 50): array
    {
        $processed = [];
        $reminders = ConversationReminder::getPendingToNotify($limit);

        foreach ($reminders as $reminder) {
            try {
                // Criar notificação para o usuário
                $contactName = $reminder['contact_name'] ?? 'Contato';
                $title = "Lembrete: {$contactName}";
                $message = !empty($reminder['note']) 
                    ? $reminder['note'] 
                    : "Lembrete para conversa com {$contactName}";

                Notification::create([
                    'user_id' => $reminder['user_id'],
                    'type' => 'reminder',
                    'title' => $title,
                    'message' => $message,
                    'link' => "/conversations?id={$reminder['conversation_id']}",
                    'data' => json_encode([
                        'conversation_id' => $reminder['conversation_id'],
                        'reminder_id' => $reminder['id']
                    ])
                ]);

                $processed[] = [
                    'id' => $reminder['id'],
                    'status' => 'notified'
                ];

                Logger::info("Lembrete processado: ID={$reminder['id']}, Conversation={$reminder['conversation_id']}");

            } catch (\Exception $e) {
                $processed[] = [
                    'id' => $reminder['id'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];

                Logger::error("Erro ao processar lembrete ID={$reminder['id']}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Marcar lembrete como resolvido
     */
    public static function markAsResolved(int $reminderId, int $userId): bool
    {
        $reminder = ConversationReminder::find($reminderId);
        if (!$reminder) {
            throw new \InvalidArgumentException('Lembrete não encontrado');
        }

        // Verificar se usuário tem permissão (só quem criou pode resolver)
        if ($reminder['user_id'] != $userId) {
            throw new \Exception('Você não tem permissão para resolver este lembrete');
        }

        return ConversationReminder::markAsResolved($reminderId);
    }

    /**
     * Obter lembretes de uma conversa
     */
    public static function getByConversation(int $conversationId, bool $onlyActive = false): array
    {
        return ConversationReminder::getByConversation($conversationId, $onlyActive);
    }

    /**
     * Obter lembretes ativos de um usuário
     */
    public static function getActiveByUser(int $userId, int $limit = 20): array
    {
        return ConversationReminder::getActiveByUser($userId, $limit);
    }
}

