<?php
/**
 * Service ScheduledMessageService
 * Lógica de negócio para mensagens agendadas
 */

namespace App\Services;

use App\Models\ScheduledMessage;
use App\Models\Conversation;
use App\Helpers\Validator;
use App\Helpers\Logger;

class ScheduledMessageService
{
    /**
     * Agendar mensagem
     */
    public static function schedule(
        int $conversationId, 
        int $userId, 
        string $content, 
        string $scheduledAt, 
        array $attachments = [], 
        bool $cancelIfResolved = false, 
        bool $cancelIfResponded = false
    ): int {
        // Validar conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        // Validar data/hora (deve ser futuro)
        $scheduledDateTime = new \DateTime($scheduledAt);
        $now = new \DateTime();
        if ($scheduledDateTime <= $now) {
            throw new \InvalidArgumentException('Data/hora agendada deve ser no futuro');
        }

        // Validar conteúdo não vazio
        if (empty(trim($content)) && empty($attachments)) {
            throw new \InvalidArgumentException('Mensagem não pode estar vazia');
        }

        // Preparar dados
        $data = [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'content' => $content,
            'scheduled_at' => $scheduledDateTime->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'cancel_if_resolved' => $cancelIfResolved ? 1 : 0,
            'cancel_if_responded' => $cancelIfResponded ? 1 : 0
        ];

        // Adicionar anexos se houver
        if (!empty($attachments)) {
            $data['attachments'] = json_encode($attachments);
        }

        // Criar mensagem agendada
        $id = ScheduledMessage::create($data);

        Logger::info("Mensagem agendada: ID={$id}, Conversation={$conversationId}, ScheduledAt={$scheduledAt}");

        return $id;
    }

    /**
     * Processar mensagens agendadas pendentes
     */
    public static function processPending(int $limit = 50): array
    {
        $processed = [];
        $messages = ScheduledMessage::getPendingToSend($limit);

        foreach ($messages as $message) {
            try {
                // Verificar condições de cancelamento
                if ($message['cancel_if_resolved'] && $message['conversation_status'] === 'resolved') {
                    ScheduledMessage::cancel($message['id']);
                    $processed[] = [
                        'id' => $message['id'],
                        'status' => 'cancelled',
                        'reason' => 'Conversa foi resolvida'
                    ];
                    continue;
                }

                if ($message['cancel_if_responded'] && $message['has_response_after'] > 0) {
                    ScheduledMessage::cancel($message['id']);
                    $processed[] = [
                        'id' => $message['id'],
                        'status' => 'cancelled',
                        'reason' => 'Conversa já foi respondida'
                    ];
                    continue;
                }

                // Enviar mensagem
                $attachments = $message['attachments'] ?? [];
                
                // Se attachments é string JSON, decodificar
                if (is_string($attachments) && !empty($attachments)) {
                    $attachments = json_decode($attachments, true) ?? [];
                }
                
                // Garantir que attachments seja array
                if (!is_array($attachments)) {
                    $attachments = [];
                }
                
                \App\Services\ConversationService::sendMessage(
                    $message['conversation_id'],
                    $message['content'],
                    'agent',
                    $message['user_id'],
                    $attachments
                );

                // Marcar como enviada
                ScheduledMessage::markAsSent($message['id']);

                $processed[] = [
                    'id' => $message['id'],
                    'status' => 'sent'
                ];

                Logger::info("Mensagem agendada enviada: ID={$message['id']}, Conversation={$message['conversation_id']}");

            } catch (\Exception $e) {
                // Marcar como falhada
                ScheduledMessage::markAsFailed($message['id'], $e->getMessage());

                $processed[] = [
                    'id' => $message['id'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                Logger::error("Erro ao processar mensagem agendada ID={$message['id']}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Cancelar mensagem agendada
     */
    public static function cancel(int $messageId, int $userId): bool
    {
        $message = ScheduledMessage::find($messageId);
        if (!$message) {
            throw new \InvalidArgumentException('Mensagem agendada não encontrada');
        }

        // Verificar se usuário tem permissão (só quem criou pode cancelar)
        if ($message['user_id'] != $userId) {
            throw new \Exception('Você não tem permissão para cancelar esta mensagem');
        }

        // Só pode cancelar se ainda estiver pendente
        if ($message['status'] !== 'pending') {
            throw new \Exception('Apenas mensagens pendentes podem ser canceladas');
        }

        return ScheduledMessage::cancel($messageId);
    }

    /**
     * Obter mensagens agendadas de uma conversa
     */
    public static function getByConversation(int $conversationId, ?string $status = null): array
    {
        return ScheduledMessage::getByConversation($conversationId, $status);
    }
}

