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
    private const LOG_FILE = 'auto_close.log';

    private static function log(string $message): void
    {
        Logger::info("[AutoClose] {$message}", self::LOG_FILE);
    }

    public static function run(): void
    {
        try {
            self::log("========================================");
            self::log("INÍCIO DO JOB - " . date('Y-m-d H:i:s'));
            self::log("========================================");

            $settings = ConversationSettingsService::getSettings();
            $autoClose = $settings['auto_close'] ?? [];

            self::log("Configurações auto_close carregadas: " . json_encode($autoClose, JSON_UNESCAPED_UNICODE));

            if (empty($autoClose['enabled'])) {
                self::log("⚠️ DESABILITADO: auto_close.enabled = false ou ausente. Encerrando job.");
                return;
            }

            self::log("✅ Auto-close HABILITADO. Processando regras...");

            $closedCount = 0;
            $actionCount = 0;

            // Regra 1: Inatividade Geral
            $rule1Enabled = !empty($autoClose['close_inactive_enabled']);
            $rule1Days = (int)($autoClose['close_inactive_days'] ?? 0);
            self::log("--- Regra 1: Inatividade Geral ---");
            self::log("  Habilitada: " . ($rule1Enabled ? 'SIM' : 'NÃO'));
            self::log("  Dias: {$rule1Days}");

            if ($rule1Enabled && $rule1Days > 0) {
                $count = self::processGeneralInactivity($rule1Days, $autoClose);
                $closedCount += $count;
                self::log("  Resultado: {$count} conversa(s) fechada(s)");
            } else {
                self::log("  Pulando: regra desabilitada ou dias = 0");
            }

            // Regra 2: Sem resposta do cliente
            $rule2Enabled = !empty($autoClose['close_waiting_client_enabled']);
            $rule2Days = (int)($autoClose['close_waiting_client_days'] ?? 0);
            self::log("--- Regra 2: Sem Resposta do Cliente ---");
            self::log("  Habilitada: " . ($rule2Enabled ? 'SIM' : 'NÃO'));
            self::log("  Dias: {$rule2Days}");

            if ($rule2Enabled && $rule2Days > 0) {
                $count = self::processWaitingClient($rule2Days, $autoClose);
                $closedCount += $count;
                self::log("  Resultado: {$count} conversa(s) fechada(s)");
            } else {
                self::log("  Pulando: regra desabilitada ou dias = 0");
            }

            // Regra 3: Inatividade do Agente
            $rule3Enabled = !empty($autoClose['agent_inactivity_enabled']);
            $rule3Days = (int)($autoClose['agent_inactivity_days'] ?? 0);
            $rule3Action = $autoClose['agent_inactivity_action'] ?? 'notify';
            $rule3TargetId = !empty($autoClose['agent_inactivity_target_id']) ? (int)$autoClose['agent_inactivity_target_id'] : null;
            self::log("--- Regra 3: Inatividade do Agente ---");
            self::log("  Habilitada: " . ($rule3Enabled ? 'SIM' : 'NÃO'));
            self::log("  Dias: {$rule3Days}");
            self::log("  Ação: {$rule3Action}");
            self::log("  Target ID: " . ($rule3TargetId ?? 'NULL'));

            if ($rule3Enabled && $rule3Days > 0) {
                $count = self::processAgentInactivity($rule3Days, $rule3Action, $rule3TargetId, $autoClose);
                $actionCount += $count;
                self::log("  Resultado: {$count} ação(ões) executada(s)");
            } else {
                self::log("  Pulando: regra desabilitada ou dias = 0");
            }

            self::log("========================================");
            self::log("RESUMO: {$closedCount} conversa(s) fechada(s), {$actionCount} ação(ões) por inatividade do agente");
            self::log("FIM DO JOB - " . date('Y-m-d H:i:s'));
            self::log("========================================");

            if ($closedCount > 0 || $actionCount > 0) {
                error_log("AutoCloseConversationsJob: {$closedCount} conversa(s) fechada(s), {$actionCount} acao(oes) por inatividade do agente");
            }

        } catch (\Throwable $e) {
            $msg = "ERRO FATAL: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine();
            self::log($msg);
            error_log("AutoCloseConversationsJob {$msg}");
        }
    }

    /**
     * Fechar conversas sem nenhuma interação por X dias
     */
    private static function processGeneralInactivity(int $days, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        self::log("  Cutoff: {$cutoff} ({$days} dias atrás)");

        $sql = "SELECT c.id, c.agent_id, c.contact_id, 
                       COALESCE(m.last_msg_at, c.created_at) as ultima_atividade
                FROM conversations c
                LEFT JOIN (
                    SELECT conversation_id, MAX(created_at) as last_msg_at
                    FROM messages
                    GROUP BY conversation_id
                ) m ON m.conversation_id = c.id
                WHERE c.status = 'open'
                  AND COALESCE(m.last_msg_at, c.created_at) < ?
                LIMIT " . self::BATCH_SIZE;

        $conversations = Database::fetchAll($sql, [$cutoff]);
        self::log("  Conversas encontradas: " . count($conversations));

        $count = 0;
        foreach ($conversations as $conv) {
            self::log("  → Fechando conversa ID={$conv['id']} (última atividade: {$conv['ultima_atividade']}, agent_id=" . ($conv['agent_id'] ?? 'NULL') . ")");
            self::closeConversation((int)$conv['id'], $autoClose);
            $count++;
        }

        return $count;
    }

    /**
     * Fechar conversas onde o agente respondeu e o cliente não responde por X dias
     */
    private static function processWaitingClient(int $days, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        self::log("  Cutoff: {$cutoff} ({$days} dias atrás)");

        $sql = "SELECT c.id, c.agent_id, c.contact_id, lm.last_msg_at, lm.sender_type
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
                LIMIT " . self::BATCH_SIZE;

        $conversations = Database::fetchAll($sql, [$cutoff]);
        self::log("  Conversas encontradas (agente respondeu, cliente não): " . count($conversations));

        $count = 0;
        foreach ($conversations as $conv) {
            self::log("  → Fechando conversa ID={$conv['id']} (última msg do agente: {$conv['last_msg_at']}, agent_id=" . ($conv['agent_id'] ?? 'NULL') . ")");
            self::closeConversation((int)$conv['id'], $autoClose);
            $count++;
        }

        return $count;
    }

    /**
     * Processar conversas onde o cliente respondeu e o agente não responde por X dias
     */
    private static function processAgentInactivity(int $days, string $action, ?int $targetId, array $autoClose): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        self::log("  Cutoff: {$cutoff} ({$days} dias atrás)");

        $sql = "SELECT c.id, c.agent_id, c.department_id, c.funnel_id, c.funnel_stage_id, 
                       c.contact_id, c.inactivity_alert_at, lm.last_msg_at, lm.sender_type
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
                LIMIT " . self::BATCH_SIZE;

        $conversations = Database::fetchAll($sql, [$cutoff]);
        self::log("  Conversas encontradas (cliente esperando agente): " . count($conversations));

        $count = 0;
        foreach ($conversations as $conv) {
            $convId = (int)$conv['id'];

            try {
                self::log("  → Conversa ID={$convId} | Ação: {$action} | Última msg contato: {$conv['last_msg_at']} | Agent: " . ($conv['agent_id'] ?? 'NULL'));

                switch ($action) {
                    case 'notify':
                        if (empty($conv['inactivity_alert_at'])) {
                            Conversation::update($convId, [
                                'inactivity_alert_at' => date('Y-m-d H:i:s')
                            ]);
                            self::log("    ✅ Alerta de inatividade definido");
                        } else {
                            self::log("    ⏭️ Já possui alerta em {$conv['inactivity_alert_at']}");
                        }
                        break;

                    case 'reassign_specific':
                        if ($targetId) {
                            ConversationService::assignToAgent($convId, $targetId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                            self::log("    ✅ Reatribuída para agente ID={$targetId}");
                        } else {
                            self::log("    ⚠️ target_id não definido para reassign_specific");
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
                            self::log("    ✅ Reatribuída via roundrobin: {$currentAgentId} → {$newAgentId}");
                        } else {
                            self::log("    ⚠️ Roundrobin não retornou novo agente (atual: {$currentAgentId}, retorno: " . ($newAgentId ?? 'NULL') . ")");
                        }
                        break;

                    case 'move_department':
                        if ($targetId) {
                            ConversationService::updateDepartment($convId, $targetId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                            self::log("    ✅ Movida para departamento ID={$targetId}");
                        } else {
                            self::log("    ⚠️ target_id não definido para move_department");
                        }
                        break;

                    case 'automation':
                        if ($targetId) {
                            AutomationService::executeAutomation($targetId, $convId);
                            Conversation::update($convId, ['inactivity_alert_at' => null]);
                            self::log("    ✅ Automação ID={$targetId} executada");
                        } else {
                            self::log("    ⚠️ target_id não definido para automation");
                        }
                        break;

                    case 'close':
                        self::closeConversation($convId, $autoClose);
                        self::log("    ✅ Conversa fechada");
                        break;

                    default:
                        self::log("    ⚠️ Ação desconhecida: {$action}");
                        break;
                }

                $count++;
            } catch (\Throwable $e) {
                self::log("    ❌ ERRO na conversa {$convId}: " . $e->getMessage());
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
                self::log("    Mensagem de encerramento enviada para conversa {$conversationId}");
            } catch (\Throwable $e) {
                self::log("    ⚠️ Erro ao enviar mensagem de sistema para conversa {$conversationId}: " . $e->getMessage());
            }
        }

        try {
            ConversationService::close($conversationId);
            self::log("    Conversa {$conversationId} fechada com sucesso");
        } catch (\Throwable $e) {
            self::log("    ❌ Erro ao fechar conversa {$conversationId}: " . $e->getMessage());
        }
    }
}
