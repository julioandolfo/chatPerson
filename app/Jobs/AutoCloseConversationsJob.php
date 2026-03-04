<?php

namespace App\Jobs;

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ConversationService;
use App\Services\ConversationSettingsService;
use App\Services\AutomationService;

class AutoCloseConversationsJob
{
    private const BATCH_SIZE = 50;

    public static function run(): void
    {
        try {
            $settings = ConversationSettingsService::getSettings();
            $autoClose = $settings['auto_close'] ?? [];

            if (empty($autoClose['enabled'])) {
                return;
            }

            $closedCount = 0;
            $actionCount = 0;

            if (!empty($autoClose['close_inactive_enabled']) && ($autoClose['close_inactive_days'] ?? 0) > 0) {
                $closedCount += self::processGeneralInactivity((int)$autoClose['close_inactive_days'], $autoClose);
            }

            if (!empty($autoClose['close_waiting_client_enabled']) && ($autoClose['close_waiting_client_days'] ?? 0) > 0) {
                $closedCount += self::processWaitingClient((int)$autoClose['close_waiting_client_days'], $autoClose);
            }

            if (!empty($autoClose['agent_inactivity_enabled']) && ($autoClose['agent_inactivity_days'] ?? 0) > 0) {
                $actionCount += self::processAgentInactivity(
                    (int)$autoClose['agent_inactivity_days'],
                    $autoClose['agent_inactivity_action'] ?? 'notify',
                    !empty($autoClose['agent_inactivity_target_id']) ? (int)$autoClose['agent_inactivity_target_id'] : null,
                    $autoClose
                );
            }

            if ($closedCount > 0 || $actionCount > 0) {
                error_log("AutoCloseConversationsJob: {$closedCount} conversa(s) fechada(s), {$actionCount} acao(oes) por inatividade do agente");
            }

        } catch (\Throwable $e) {
            error_log("AutoCloseConversationsJob ERRO: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Fechar conversas sem nenhuma interacao por X dias
     */
    private static function processGeneralInactivity(int $days, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $conversations = Database::fetchAll(
            "SELECT c.id, c.agent_id, c.contact_id
             FROM conversations c
             LEFT JOIN (
                 SELECT conversation_id, MAX(created_at) as last_msg_at
                 FROM messages
                 GROUP BY conversation_id
             ) m ON m.conversation_id = c.id
             WHERE c.status = 'open'
               AND COALESCE(m.last_msg_at, c.created_at) < ?
             LIMIT " . self::BATCH_SIZE,
            [$cutoff]
        );

        $count = 0;
        foreach ($conversations as $conv) {
            self::closeConversation((int)$conv['id'], $autoClose);
            $count++;
        }

        return $count;
    }

    /**
     * Fechar conversas onde o agente respondeu e o cliente nao responde por X dias
     */
    private static function processWaitingClient(int $days, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $conversations = Database::fetchAll(
            "SELECT c.id, c.agent_id, c.contact_id
             FROM conversations c
             INNER JOIN (
                 SELECT m1.conversation_id, m1.sender_type, m1.created_at as last_msg_at
                 FROM messages m1
                 INNER JOIN (
                     SELECT conversation_id, MAX(created_at) as max_at
                     FROM messages
                     GROUP BY conversation_id
                 ) m2 ON m1.conversation_id = m2.conversation_id AND m1.created_at = m2.max_at
             ) lm ON lm.conversation_id = c.id
             WHERE c.status = 'open'
               AND lm.sender_type = 'agent'
               AND lm.last_msg_at < ?
             LIMIT " . self::BATCH_SIZE,
            [$cutoff]
        );

        $count = 0;
        foreach ($conversations as $conv) {
            self::closeConversation((int)$conv['id'], $autoClose);
            $count++;
        }

        return $count;
    }

    /**
     * Processar conversas onde o cliente respondeu e o agente nao responde por X dias
     */
    private static function processAgentInactivity(int $days, string $action, ?int $targetId, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $conversations = Database::fetchAll(
            "SELECT c.id, c.agent_id, c.department_id, c.funnel_id, c.funnel_stage_id, c.contact_id, c.inactivity_alert_at
             FROM conversations c
             INNER JOIN (
                 SELECT m1.conversation_id, m1.sender_type, m1.created_at as last_msg_at
                 FROM messages m1
                 INNER JOIN (
                     SELECT conversation_id, MAX(created_at) as max_at
                     FROM messages
                     GROUP BY conversation_id
                 ) m2 ON m1.conversation_id = m2.conversation_id AND m1.created_at = m2.max_at
             ) lm ON lm.conversation_id = c.id
             WHERE c.status = 'open'
               AND lm.sender_type = 'contact'
               AND lm.last_msg_at < ?
             LIMIT " . self::BATCH_SIZE,
            [$cutoff]
        );

        $count = 0;
        foreach ($conversations as $conv) {
            $convId = (int)$conv['id'];

            try {
                switch ($action) {
                    case 'notify':
                        if (empty($conv['inactivity_alert_at'])) {
                            Conversation::update($convId, [
                                'inactivity_alert_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        break;

                    case 'reassign_specific':
                        if ($targetId) {
                            ConversationService::assignToAgent($convId, $targetId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                        }
                        break;

                    case 'roundrobin':
                        $currentAgentId = !empty($conv['agent_id']) ? (int)$conv['agent_id'] : null;
                        $newAgentId = ConversationSettingsService::autoAssignConversation(
                            $convId,
                            !empty($conv['department_id']) ? (int)$conv['department_id'] : null,
                            !empty($conv['funnel_id']) ? (int)$conv['funnel_id'] : null,
                            !empty($conv['funnel_stage_id']) ? (int)$conv['funnel_stage_id'] : null,
                            $currentAgentId
                        );
                        if ($newAgentId && $newAgentId !== $currentAgentId) {
                            ConversationService::assignToAgent($convId, $newAgentId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                        }
                        break;

                    case 'move_department':
                        if ($targetId) {
                            ConversationService::updateDepartment($convId, $targetId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                        }
                        break;

                    case 'automation':
                        if ($targetId) {
                            AutomationService::executeAutomation($targetId, $convId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                        }
                        break;

                    case 'close':
                        self::closeConversation($convId, $autoClose);
                        break;
                }

                $count++;
            } catch (\Throwable $e) {
                error_log("AutoCloseConversationsJob: erro ao processar conversa {$convId} (acao={$action}): " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Fechar uma conversa com mensagem opcional de sistema
     */
    private static function closeConversation(int $conversationId, array $autoClose): void
    {
        if (!empty($autoClose['send_closing_message'])) {
            $message = $autoClose['closing_message'] ?? 'Esta conversa foi encerrada automaticamente por inatividade.';
            try {
                Message::createMessage([
                    'conversation_id' => $conversationId,
                    'sender_type' => 'system',
                    'sender_id' => null,
                    'content' => $message,
                    'message_type' => 'system',
                    'status' => 'sent',
                ]);
            } catch (\Throwable $e) {
                error_log("AutoCloseConversationsJob: erro ao enviar mensagem de sistema para conversa {$conversationId}: " . $e->getMessage());
            }
        }

        try {
            ConversationService::close($conversationId);
        } catch (\Throwable $e) {
            error_log("AutoCloseConversationsJob: erro ao fechar conversa {$conversationId}: " . $e->getMessage());
        }
    }
}
