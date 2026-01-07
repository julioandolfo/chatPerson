<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationActionButton;
use App\Models\ConversationActionLog;
use App\Models\ConversationActionStep;
use App\Models\ConversationParticipant;
use App\Models\Activity;
use App\Helpers\Permission;
use App\Helpers\Log;

class ConversationActionButtonService
{
    /**
    * Lista botões ativos ordenados
    */
    public static function listActive(): array
    {
        $sql = "SELECT * FROM conversation_action_buttons WHERE is_active = 1 ORDER BY sort_order ASC, id ASC";
        return \App\Helpers\Database::fetchAll($sql) ?? [];
    }

    /**
    * Lista botões com steps
    */
    public static function listWithSteps(?int $userId = null): array
    {
        $buttons = self::listActive();
        $buttonIds = array_column($buttons, 'id');
        $steps = [];
        if (!empty($buttonIds)) {
            $placeholders = implode(',', array_fill(0, count($buttonIds), '?'));
            $sql = "SELECT * FROM conversation_action_steps WHERE button_id IN ({$placeholders}) ORDER BY sort_order ASC, id ASC";
            $steps = \App\Helpers\Database::fetchAll($sql, $buttonIds) ?? [];
        }
        $stepsByButton = [];
        foreach ($steps as $step) {
            $stepsByButton[$step['button_id']][] = $step;
        }
        foreach ($buttons as &$btn) {
            $btn['visibility'] = json_decode($btn['visibility'] ?? '', true) ?: [];
            $btn['steps'] = $stepsByButton[$btn['id']] ?? [];
        }
        // Filtrar por visibilidade (se userId informado)
        if ($userId) {
            $buttons = array_values(array_filter($buttons, function($btn) use ($userId) {
                $vis = $btn['visibility'];
                if (empty($vis)) return true; // sem restrição
                
                // Lista de agentes explicitamente permitidos
                if (!empty($vis['agents']) && is_array($vis['agents'])) {
                    return in_array((int)$userId, array_map('intval', $vis['agents']), true);
                }
                
                // Futuro: outros filtros (cargos, departamentos) poderiam entrar aqui
                return true;
            }));
        }
        return $buttons;
    }

    /**
    * Executa um botão em uma conversa
    */
    public static function run(int $conversationId, int $buttonId, int $userId): array
    {
        Permission::abortIfCannot('conversations.actions.run');

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \InvalidArgumentException('Conversa não encontrada');
        }

        $button = ConversationActionButton::find($buttonId);
        if (!$button || (int)$button['is_active'] !== 1) {
            throw new \InvalidArgumentException('Botão não encontrado ou inativo');
        }

        $steps = ConversationActionStep::where('button_id', '=', $buttonId);
        usort($steps, function($a, $b) {
            if ($a['sort_order'] == $b['sort_order']) return $a['id'] <=> $b['id'];
            return $a['sort_order'] <=> $b['sort_order'];
        });

        if (empty($steps)) {
            throw new \InvalidArgumentException('Botão sem etapas configuradas');
        }

        $executed = [];
        // Log detalhado antes da execução
        try {
            Log::info("[ActionButton] Iniciando execução. conversation_id={$conversationId}, button_id={$buttonId}, user_id={$userId}, steps=" . json_encode($steps), 'automacao.log');
        } catch (\Throwable $t) {
            // não interromper a execução se o log falhar
        }

        try {
            foreach ($steps as $step) {
                self::executeStep($conversationId, $step, $userId, $button);
                $executed[] = ['id' => $step['id'], 'type' => $step['type']];
            }

            ConversationActionLog::create([
                'button_id' => $buttonId,
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'result' => 'success',
                'steps_executed' => json_encode($executed)
            ]);

            // Registrar atividade agregada do botão
            try {
                Activity::log(
                    'action_button',
                    'conversation',
                    $conversationId,
                    $userId,
                    "Botão '{$button['name']}' executado",
                    [
                        'button_id' => $buttonId,
                        'button_name' => $button['name'] ?? null,
                        'steps_executed' => $executed
                    ]
                );
            } catch (\Throwable $t) {}

            try {
                Log::info("[ActionButton] Sucesso. conversation_id={$conversationId}, button_id={$buttonId}, executed=" . json_encode($executed), 'automacao.log');
            } catch (\Throwable $t) {}

            return ['success' => true, 'message' => 'Ação executada', 'executed' => $executed];
        } catch (\Exception $e) {
            ConversationActionLog::create([
                'button_id' => $buttonId,
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'result' => 'error',
                'steps_executed' => json_encode($executed),
                'error_message' => $e->getMessage()
            ]);

            try {
                Log::error("[ActionButton] Erro. conversation_id={$conversationId}, button_id={$buttonId}, executed=" . json_encode($executed) . ", erro=" . $e->getMessage(), 'automacao.log');
            } catch (\Throwable $t) {}

            throw $e;
        }
    }

    protected static function executeStep(int $conversationId, array $step, int $userId, ?array $button = null): void
    {
        $type = $step['type'];
        $payload = $step['payload'];
        if (is_string($payload)) {
            // Primeira tentativa de decodificação
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Alguns registros antigos armazenaram o JSON como string dentro de string
                if (is_string($decoded)) {
                    $decoded2 = json_decode($decoded, true);
                    $payload = (json_last_error() === JSON_ERROR_NONE && is_array($decoded2)) ? $decoded2 : [];
                } else {
                    $payload = is_array($decoded) ? $decoded : [];
                }
            } else {
                $payload = [];
            }
        }
        try {
            Log::info("[ActionButton] Executando step={$type}, payload=" . json_encode($payload) . ", conversation_id={$conversationId}, user_id={$userId}", 'automacao.log');
        } catch (\Throwable $t) {}

        switch ($type) {
            case 'set_funnel_stage':
                Permission::abortIfCannot('conversations.edit.own');
                $stageId = $payload['stage_id'] ?? null;
                if (!$stageId) {
                    throw new \InvalidArgumentException('Etapa não informada');
                }
                \App\Services\FunnelService::moveConversation($conversationId, (int)$stageId, $userId);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Moveu para etapa ID ' . $stageId,
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'assign_agent':
                Permission::abortIfCannot('conversations.assign.own');
                $agentId = $payload['agent_id'] ?? null;
                if (!$agentId) {
                    throw new \InvalidArgumentException('Agente não informado');
                }
                \App\Services\ConversationService::assignToAgent($conversationId, (int)$agentId, true);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Atribuiu agente ID ' . $agentId,
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'add_participant':
                Permission::abortIfCannot('conversations.edit.own');
                $participantId = $payload['participant_id'] ?? null;
                if (!$participantId) {
                    throw new \InvalidArgumentException('Participante não informado');
                }
                ConversationParticipant::addParticipant($conversationId, (int)$participantId, $userId);
                \App\Services\ConversationService::invalidateCache($conversationId);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Adicionou participante ID ' . $participantId,
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'close_conversation':
                Permission::abortIfCannot('conversations.edit.own');
                \App\Services\ConversationService::close($conversationId);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Encerrou a conversa',
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'add_tag':
                Permission::abortIfCannot('conversations.edit.own');
                $tagId = $payload['tag_id'] ?? null;
                if (!$tagId) {
                    throw new \InvalidArgumentException('Tag não informada');
                }
                \App\Services\TagService::addToConversation($conversationId, (int)$tagId);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Adicionou tag ID ' . $tagId,
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'remove_tag':
                Permission::abortIfCannot('conversations.edit.own');
                $tagId = $payload['tag_id'] ?? null;
                if (!$tagId) {
                    throw new \InvalidArgumentException('Tag não informada');
                }
                \App\Services\TagService::removeFromConversation($conversationId, (int)$tagId);
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Removeu tag ID ' . $tagId,
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            case 'leave_conversation':
                // Permitir que o próprio participante saia, mesmo sem permissão de editar
                $removed = ConversationParticipant::removeParticipant($conversationId, $userId);
                \App\Services\ConversationService::invalidateCache($conversationId);
                if (!$removed) {
                    throw new \InvalidArgumentException('Não foi possível sair da conversa');
                }
                try {
                    Activity::log(
                        'action_button_step',
                        'conversation',
                        $conversationId,
                        $userId,
                        ($button['name'] ?? 'Botão de Ação') . ' - Saiu da conversa',
                        [
                            'button_id' => $button['id'] ?? null,
                            'button_name' => $button['name'] ?? null,
                            'step_type' => $type,
                            'payload' => $payload
                        ]
                    );
                } catch (\Throwable $t) {}
                break;
            default:
                throw new \InvalidArgumentException('Tipo de etapa não suportado: ' . $type);
        }
    }
}
