<?php
/**
 * Service AutomationService
 * Lógica de negócio para automações
 */

namespace App\Services;

use App\Models\Automation;
use App\Models\AutomationNode;
use App\Models\Conversation;
use App\Models\WhatsAppAccount;
use App\Helpers\Validator;

class AutomationService
{
    /**
     * Criar automação
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string|in:new_conversation,message_received,conversation_updated,conversation_moved,conversation_resolved,time_based,contact_created,contact_updated,agent_activity,webhook',
            'trigger_config' => 'nullable|array',
            'funnel_id' => 'nullable|integer',
            'stage_id' => 'nullable|integer',
            'status' => 'nullable|string|in:active,inactive'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Serializar trigger_config
        if (isset($data['trigger_config']) && is_array($data['trigger_config'])) {
            $data['trigger_config'] = json_encode($data['trigger_config']);
        }

        // Converter funnel_id e stage_id para int ou null
        if (isset($data['funnel_id']) && $data['funnel_id'] === '') {
            $data['funnel_id'] = null;
        } elseif (isset($data['funnel_id'])) {
            $data['funnel_id'] = (int)$data['funnel_id'];
        }
        
        if (isset($data['stage_id']) && $data['stage_id'] === '') {
            $data['stage_id'] = null;
        } elseif (isset($data['stage_id'])) {
            $data['stage_id'] = (int)$data['stage_id'];
        }

        $data['status'] = $data['status'] ?? 'active';
        $data['is_active'] = $data['is_active'] ?? true;

        return Automation::create($data);
    }

    /**
     * Atualizar automação
     */
    public static function update(int $automationId, array $data): bool
    {
        $automation = Automation::find($automationId);
        if (!$automation) {
            throw new \InvalidArgumentException('Automação não encontrada');
        }

        // Serializar trigger_config se for array
        if (isset($data['trigger_config']) && is_array($data['trigger_config'])) {
            $data['trigger_config'] = json_encode($data['trigger_config']);
        }

        return Automation::update($automationId, $data);
    }

    /**
     * Criar nó da automação
     */
    public static function createNode(int $automationId, array $data): int
    {
        $automation = Automation::find($automationId);
        if (!$automation) {
            throw new \InvalidArgumentException('Automação não encontrada');
        }

        $errors = Validator::validate($data, [
            'node_type' => 'required|string',
            'node_data' => 'required|array',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer'
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Serializar node_data
        $data['node_data'] = json_encode($data['node_data']);
        $data['automation_id'] = $automationId;
        $data['position_x'] = $data['position_x'] ?? 0;
        $data['position_y'] = $data['position_y'] ?? 0;

        return AutomationNode::create($data);
    }

    /**
     * Atualizar nó
     */
    public static function updateNode(int $nodeId, array $data): bool
    {
        $node = AutomationNode::find($nodeId);
        if (!$node) {
            throw new \InvalidArgumentException('Nó não encontrado');
        }

        // Serializar node_data se for array
        if (isset($data['node_data']) && is_array($data['node_data'])) {
            $data['node_data'] = json_encode($data['node_data']);
        }

        return AutomationNode::update($nodeId, $data);
    }

    /**
     * Deletar nó
     */
    public static function deleteNode(int $nodeId): bool
    {
        return AutomationNode::delete($nodeId);
    }

    /**
     * Executar automação para nova conversa
     */
    public static function executeForNewConversation(int $conversationId): void
    {
        Logger::debug("=== executeForNewConversation INÍCIO === conversationId: {$conversationId}", 'automacao.log');
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            Logger::debug("ERRO: Conversa não encontrada! conversationId: {$conversationId}", 'automacao.log');
            return;
        }
        
        Logger::debug("Conversa encontrada: " . json_encode($conversation), 'automacao.log');

        // Buscar automações ativas para new_conversation
        $triggerData = [
            'channel' => $conversation['channel'] ?? null,
            'whatsapp_account_id' => $conversation['whatsapp_account_id'] ?? null
        ];

        // Filtrar por funil/estágio se a conversa já estiver em um
        $funnelId = $conversation['funnel_id'] ?? null;
        $stageId = $conversation['funnel_stage_id'] ?? null;

        Logger::debug("Buscando automações com: triggerData=" . json_encode($triggerData) . ", funnelId={$funnelId}, stageId={$stageId}", 'automacao.log');

        $automations = Automation::getActiveByTrigger('new_conversation', $triggerData, $funnelId, $stageId);

        Logger::debug("Automações encontradas: " . count($automations), 'automacao.log');
        
        if (!empty($automations)) {
            Logger::debug("Lista de automações: " . json_encode($automations), 'automacao.log');
        }

        foreach ($automations as $automation) {
            Logger::debug("Executando automação ID: {$automation['id']}, Nome: {$automation['name']}", 'automacao.log');
            try {
                self::executeAutomation($automation['id'], $conversationId);
                Logger::debug("Automação ID: {$automation['id']} executada com SUCESSO", 'automacao.log');
            } catch (\Exception $e) {
                Logger::debug("ERRO ao executar automação ID: {$automation['id']} - " . $e->getMessage(), 'automacao.log');
            }
        }
        
        Logger::debug("=== executeForNewConversation FIM ===", 'automacao.log');
    }

    /**
     * Executar automação para mensagem recebida
     */
    public static function executeForMessageReceived(int $messageId): void
    {
        $message = \App\Models\Message::find($messageId);
        if (!$message || $message['sender_type'] === 'agent') {
            return; // Não executar para mensagens de agentes
        }

        $conversation = Conversation::find($message['conversation_id']);
        if (!$conversation) {
            return;
        }

        // Se há um chatbot ativo aguardando resposta, tentar roteá-lo primeiro
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        if (!empty($metadata['chatbot_active'])) {
            $handled = self::handleChatbotResponse($conversation, $message);
            if ($handled) {
                return; // Já roteou para o próximo nó do chatbot, não disparar outras automações aqui
            }
        }

        // Buscar automações ativas para message_received
        $triggerData = [
            'channel' => $conversation['channel'] ?? null,
            'whatsapp_account_id' => $conversation['whatsapp_account_id'] ?? null,
            'contact_id' => $conversation['contact_id'] ?? null
        ];

        $automations = Automation::getActiveByTrigger('message_received', $triggerData);

        foreach ($automations as $automation) {
            // Verificar se mensagem contém palavra-chave se configurado
            $config = json_decode($automation['trigger_config'], true);
            if (!empty($config['keyword'])) {
                if (stripos($message['content'], $config['keyword']) === false) {
                    continue; // Pular se não contém palavra-chave
                }
            }
            
            self::executeAutomation($automation['id'], $conversation['id']);
        }
    }

    /**
     * Executar automação para conversa atualizada
     */
    public static function executeForConversationUpdated(int $conversationId, array $changes = []): void
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        // Buscar automações ativas para conversation_updated
        $triggerData = [
            'channel' => $conversation['channel'] ?? null,
            'whatsapp_account_id' => $conversation['whatsapp_account_id'] ?? null
        ];

        $automations = Automation::getActiveByTrigger('conversation_updated', $triggerData);

        foreach ($automations as $automation) {
            // Verificar se mudança específica foi configurada
            $config = json_decode($automation['trigger_config'], true);
            if (!empty($config['field'])) {
                if (!isset($changes[$config['field']])) {
                    continue; // Pular se campo específico não mudou
                }
            }
            
            self::executeAutomation($automation['id'], $conversationId);
        }
    }

    /**
     * Executar automação para conversa movida no funil
     */
    public static function executeForConversationMoved(int $conversationId, int $fromStageId, int $toStageId): void
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        $funnelId = $conversation['funnel_id'] ?? null;

        // Buscar automações ativas para conversation_moved
        // Filtrar por funil e estágio de destino
        $triggerData = [
            'funnel_id' => $funnelId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId
        ];

        // Buscar automações vinculadas ao estágio de destino ou ao funil
        $automations = Automation::getActiveByTrigger('conversation_moved', $triggerData, $funnelId, $toStageId);

        foreach ($automations as $automation) {
            $config = json_decode($automation['trigger_config'], true);
            
            // Se automação está vinculada a um estágio específico, verificar se é o destino
            if (!empty($automation['stage_id'])) {
                if ($toStageId != $automation['stage_id']) {
                    continue; // Pular se não é o estágio vinculado
                }
            }
            
            // Verificar configuração adicional no trigger_config (compatibilidade)
            if (!empty($config['stage_id'])) {
                if ($toStageId != $config['stage_id']) {
                    continue;
                }
            }
            
            self::executeAutomation($automation['id'], $conversationId);
        }
    }

    /**
     * Executar automação para conversa resolvida
     */
    public static function executeForConversationResolved(int $conversationId): void
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        // Buscar automações ativas para conversation_resolved
        $triggerData = [
            'channel' => $conversation['channel'] ?? null,
            'funnel_id' => $conversation['funnel_id'] ?? null
        ];

        $automations = Automation::getActiveByTrigger('conversation_resolved', $triggerData);

        foreach ($automations as $automation) {
            self::executeAutomation($automation['id'], $conversationId);
        }
    }

    /**
     * Executar automações temporais (agendadas)
     */
    public static function executeScheduledAutomations(): void
    {
        // Buscar automações temporais ativas
        $automations = Automation::getActiveByTrigger('time_based');
        
        foreach ($automations as $automation) {
            $config = json_decode($automation['trigger_config'], true);
            
            if (empty($config['schedule_type'])) {
                continue;
            }
            
            $shouldExecute = false;
            
            switch ($config['schedule_type']) {
                case 'daily':
                    // Executar diariamente em horário específico
                    $scheduleTime = $config['time'] ?? '09:00';
                    $currentTime = date('H:i');
                    if ($currentTime === $scheduleTime) {
                        $shouldExecute = true;
                    }
                    break;
                    
                case 'weekly':
                    // Executar semanalmente em dia/hora específica
                    $scheduleDay = $config['day'] ?? 1; // 1 = segunda-feira
                    $scheduleTime = $config['time'] ?? '09:00';
                    $currentDay = date('N'); // 1-7 (segunda-domingo)
                    $currentTime = date('H:i');
                    if ($currentDay == $scheduleDay && $currentTime === $scheduleTime) {
                        $shouldExecute = true;
                    }
                    break;
                    
                case 'monthly':
                    // Executar mensalmente em dia/hora específica
                    $scheduleDay = $config['day'] ?? 1;
                    $scheduleTime = $config['time'] ?? '09:00';
                    $currentDay = date('j');
                    $currentTime = date('H:i');
                    if ($currentDay == $scheduleDay && $currentTime === $scheduleTime) {
                        $shouldExecute = true;
                    }
                    break;
                    
                case 'after_time':
                    // Executar após X tempo da criação/atualização
                    // Este precisa ser verificado por conversa individual
                    // Será implementado em verificação periódica
                    break;
            }
            
            if ($shouldExecute && !empty($config['action_type'])) {
                // Executar ação específica (não precisa de conversa)
                self::executeScheduledAction($automation['id'], $config);
            }
        }
    }

    /**
     * Executar ação agendada
     */
    private static function executeScheduledAction(int $automationId, array $config): void
    {
        // Implementar ações agendadas (ex: relatórios, limpezas, etc.)
        error_log("Executando ação agendada para automação {$automationId}");
    }

    /**
     * Executar automação
     */
    public static function executeAutomation(int $automationId, int $conversationId, bool $logExecution = true): void
    {
        $automation = Automation::findWithNodes($automationId);
        if (!$automation || empty($automation['nodes'])) {
            return;
        }

        // Criar log de execução
        $executionId = null;
        if ($logExecution) {
            try {
                $executionId = \App\Models\AutomationExecution::createLog(
                    $automationId,
                    $conversationId,
                    'running'
                );
            } catch (\Exception $e) {
                error_log("Erro ao criar log de execução: " . $e->getMessage());
            }
        }

        try {
            // Encontrar nó inicial (trigger)
            $startNode = null;
            foreach ($automation['nodes'] as $node) {
                if ($node['node_type'] === 'trigger') {
                    $startNode = $node;
                    break;
                }
            }

            if (!$startNode) {
                if ($executionId) {
                    \App\Models\AutomationExecution::updateStatus($executionId, 'failed', 'Nó inicial não encontrado');
                }
                return;
            }

            // Executar fluxo começando do nó inicial
            self::executeNode($startNode, $conversationId, $automation['nodes'], $executionId);
            
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'completed');
            }
        } catch (\Exception $e) {
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', $e->getMessage());
            }
            error_log("Erro ao executar automação {$automationId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Executar nó e seguir o fluxo
     */
    private static function executeNode(array $node, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        $nodeData = $node['node_data'] ?? [];
        
        // Atualizar log com nó atual
        if ($executionId) {
            \App\Models\AutomationExecution::updateStatus($executionId, 'running', null, $node['id']);
        }
        
        switch ($node['node_type']) {
            case 'action_send_message':
                self::executeSendMessage($nodeData, $conversationId, $executionId);
                break;
            case 'action_assign_agent':
                self::executeAssignAgent($nodeData, $conversationId, $executionId);
                break;
            case 'action_assign_advanced':
                self::executeAssignAdvanced($nodeData, $conversationId, $executionId);
                break;
            case 'action_move_stage':
                self::executeMoveStage($nodeData, $conversationId, $executionId);
                break;
            case 'action_set_tag':
                self::executeSetTag($nodeData, $conversationId, $executionId);
                break;
            case 'action_chatbot':
                self::executeChatbot($nodeData, $conversationId, $executionId);
                break;
            case 'condition':
                self::executeCondition($nodeData, $conversationId, $allNodes, $executionId);
                return; // Condição já processa os próximos nós
            case 'delay':
                self::executeDelay($nodeData, $conversationId, $allNodes, $executionId);
                return; // Delay precisa aguardar
        }

        // Seguir para próximos nós conectados
        if (!empty($nodeData['connections'])) {
            foreach ($nodeData['connections'] as $connection) {
                $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                if ($nextNode) {
                    self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                }
            }
        }
    }

    /**
     * Executar ação: enviar mensagem
     */
    private static function executeSendMessage(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        $message = $nodeData['message'] ?? '';
        if (empty($message)) {
            return;
        }

        // Processar variáveis e templates
        $message = self::processVariables($message, $conversationId);

        try {
            \App\Services\ConversationService::sendMessage(
                $conversationId,
                $message,
                'agent',
                null // Sistema
            );
            
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'running');
            }
        } catch (\Exception $e) {
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Processar variáveis em mensagens
     * @param string $message Mensagem com variáveis
     * @param int|array $conversationOrId ID da conversa ou array da conversa já carregado
     * @return string Mensagem com variáveis substituídas
     */
    private static function processVariables(string $message, $conversationOrId): string
    {
        // Se recebeu int, buscar conversa; se array, usar diretamente
        if (is_int($conversationOrId)) {
            $conversation = Conversation::find($conversationOrId);
            if (!$conversation) {
                return $message;
            }
        } elseif (is_array($conversationOrId)) {
            $conversation = $conversationOrId;
        } else {
            return $message;
        }

        $contact = \App\Models\Contact::find($conversation['contact_id']);
        $agent = $conversation['agent_id'] ? \App\Models\User::find($conversation['agent_id']) : null;

        // Variáveis disponíveis
        $variables = [
            '{{contact.name}}' => $contact ? ($contact['name'] ?? '') : '',
            '{{contact.phone}}' => $contact ? ($contact['phone'] ?? '') : '',
            '{{contact.email}}' => $contact ? ($contact['email'] ?? '') : '',
            '{{agent.name}}' => $agent ? ($agent['name'] ?? '') : '',
            '{{conversation.id}}' => $conversation['id'] ?? '',
            '{{conversation.subject}}' => $conversation['subject'] ?? '',
            '{{date}}' => date('d/m/Y'),
            '{{time}}' => date('H:i'),
            '{{datetime}}' => date('d/m/Y H:i'),
        ];

        // Substituir variáveis
        foreach ($variables as $key => $value) {
            $message = str_replace($key, $value, $message);
        }

        return $message;
    }

    /**
     * Executar ação: atribuir agente
     */
    private static function executeAssignAgent(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        $agentId = $nodeData['agent_id'] ?? null;
        if (!$agentId) {
            return;
        }

        try {
            \App\Services\ConversationService::assign($conversationId, $agentId);
        } catch (\Exception $e) {
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro ao atribuir agente: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Executar ação: atribuição avançada
     */
    private static function executeAssignAdvanced(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new \Exception('Conversa não encontrada');
            }
            
            $assignmentType = $nodeData['assignment_type'] ?? 'auto';
            $agentId = null;
            \App\Helpers\Logger::automation("executeAssignAdvanced - Tipo: {$assignmentType}, Conversa: {$conversationId}");
            
            switch ($assignmentType) {
                case 'specific_agent':
                    $agentId = (int)($nodeData['agent_id'] ?? 0);
                    $forceAssign = (bool)($nodeData['force_assign'] ?? false);
                    
                    \App\Helpers\Logger::automation("executeAssignAdvanced - Agente específico: {$agentId}, Forçar: " . ($forceAssign ? 'SIM' : 'NÃO'));
                    
                    if ($agentId) {
                        \App\Services\ConversationService::assignToAgent($conversationId, $agentId, $forceAssign);
                    }
                    break;
                    
                case 'department':
                    $departmentId = (int)($nodeData['department_id'] ?? 0);
                    
                    \App\Helpers\Logger::automation("executeAssignAdvanced - Setor específico: {$departmentId}");
                    
                    if ($departmentId) {
                        $agentId = \App\Services\ConversationSettingsService::autoAssignConversation(
                            $conversationId,
                            $departmentId,
                            $conversation['funnel_id'] ?? null,
                            $conversation['funnel_stage_id'] ?? null
                        );
                    }
                    break;
                    
                case 'custom_method':
                    $method = $nodeData['distribution_method'] ?? 'round_robin';
                    $filterDepartmentId = !empty($nodeData['filter_department_id']) ? (int)$nodeData['filter_department_id'] : null;
                    $considerAvailability = (bool)($nodeData['consider_availability'] ?? true);
                    $considerMaxConversations = (bool)($nodeData['consider_max_conversations'] ?? true);
                    $allowAI = (bool)($nodeData['allow_ai_agents'] ?? false);
                    
                    \App\Helpers\Logger::automation("executeAssignAdvanced - Método personalizado: {$method}, Setor filtro: {$filterDepartmentId}");
                    
                    // Se método é por porcentagem, processar regras
                    if ($method === 'percentage') {
                        $percentageAgentIds = $nodeData['percentage_agent_ids'] ?? [];
                        $percentageValues = $nodeData['percentage_values'] ?? [];
                        
                        if (!empty($percentageAgentIds) && !empty($percentageValues)) {
                            $rules = [];
                            foreach ($percentageAgentIds as $idx => $agId) {
                                if (!empty($agId) && !empty($percentageValues[$idx])) {
                                    $rules[] = [
                                        'agent_id' => (int)$agId,
                                        'percentage' => (int)$percentageValues[$idx]
                                    ];
                                }
                            }
                            
                            \App\Helpers\Logger::automation("executeAssignAdvanced - Regras de %: " . json_encode($rules));
                            
                            // Selecionar agente baseado em porcentagem
                            $agentId = self::selectAgentByPercentage($rules, $filterDepartmentId, $considerAvailability, $considerMaxConversations);
                        }
                    } else {
                        // Usar método padrão (round-robin, by_load, etc)
                        $agentId = self::selectAgentByMethod(
                            $method,
                            $filterDepartmentId,
                            $conversation['funnel_id'] ?? null,
                            $conversation['funnel_stage_id'] ?? null,
                            $considerAvailability,
                            $considerMaxConversations,
                            $allowAI
                        );
                    }
                    
                    if ($agentId) {
                        \App\Services\ConversationService::assignToAgent($conversationId, $agentId, false);
                    }
                    break;
                    
                case 'auto':
                default:
                    \App\Helpers\Logger::automation("executeAssignAdvanced - Automático (usa config global)");
                    $agentId = \App\Services\ConversationSettingsService::autoAssignConversation(
                        $conversationId,
                        $conversation['department_id'] ?? null,
                        $conversation['funnel_id'] ?? null,
                        $conversation['funnel_stage_id'] ?? null
                    );
                    if ($agentId) {
                        \App\Services\ConversationService::assignToAgent($conversationId, $agentId, false);
                    }
                    break;
            }
            
            // Se não conseguiu atribuir, executar fallback
            if (!$agentId) {
                $fallbackAction = $nodeData['fallback_action'] ?? 'leave_unassigned';
                
                \App\Helpers\Logger::automation("executeAssignAdvanced - Fallback: {$fallbackAction}");
                
                switch ($fallbackAction) {
                    case 'try_any_agent':
                        // Tenta qualquer agente disponível sem filtros
                        $agentId = \App\Services\ConversationSettingsService::assignRoundRobin(null, null, null, false);
                        if ($agentId) {
                            \App\Services\ConversationService::assignToAgent($conversationId, $agentId, false);
                        }
                        break;
                        
                    case 'assign_to_ai':
                        // Atribuir a um agente de IA
                        $aiAgents = \App\Models\User::where('is_ai_agent', '=', 1);
                        if (!empty($aiAgents)) {
                            \App\Services\ConversationService::assignToAgent($conversationId, $aiAgents[0]['id'], false);
                            $agentId = $aiAgents[0]['id'];
                        }
                        break;
                        
                    case 'move_to_stage':
                        $fallbackStageId = (int)($nodeData['fallback_stage_id'] ?? 0);
                        if ($fallbackStageId) {
                            \App\Services\FunnelService::moveConversationToStage($conversationId, $fallbackStageId);
                        }
                        break;
                        
                    case 'leave_unassigned':
                    default:
                        // Não faz nada, deixa sem atribuição
                        break;
                }
            }
            
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus(
                    $executionId,
                    'completed',
                    $agentId ? "Atribuído ao agente ID: {$agentId}" : "Não foi possível atribuir"
                );
            }
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("executeAssignAdvanced - ERRO: " . $e->getMessage());
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro na atribuição: " . $e->getMessage());
            }
            throw $e;
        }
    }
    
    /**
     * Selecionar agente por método
     */
    private static function selectAgentByMethod(string $method, ?int $departmentId, ?int $funnelId, ?int $stageId, bool $considerAvailability, bool $considerMaxConversations, bool $allowAI): ?int
    {
        \App\Helpers\Logger::automation("selectAgentByMethod - Método: {$method}, Setor: {$departmentId}");
        
        switch ($method) {
            case 'round_robin':
                return \App\Services\ConversationSettingsService::assignRoundRobin($departmentId, $funnelId, $stageId, $allowAI);
            case 'by_load':
                return \App\Services\ConversationSettingsService::assignByLoad($departmentId, $funnelId, $stageId, $allowAI);
            case 'by_performance':
                return \App\Services\ConversationSettingsService::assignByPerformance($departmentId, $funnelId, $stageId, $allowAI);
            case 'by_specialty':
                return \App\Services\ConversationSettingsService::assignBySpecialty($departmentId, $funnelId, $stageId, $allowAI);
            default:
                return \App\Services\ConversationSettingsService::assignRoundRobin($departmentId, $funnelId, $stageId, $allowAI);
        }
    }
    
    /**
     * Selecionar agente por porcentagem
     */
    private static function selectAgentByPercentage(array $rules, ?int $departmentId, bool $considerAvailability, bool $considerMaxConversations): ?int
    {
        if (empty($rules)) {
            return null;
        }
        
        \App\Helpers\Logger::automation("selectAgentByPercentage - " . count($rules) . " regras");
        
        // Normalizar porcentagens
        $total = array_sum(array_column($rules, 'percentage'));
        if ($total == 0) {
            return null;
        }
        
        foreach ($rules as &$rule) {
            $rule['normalized'] = ($rule['percentage'] / $total) * 100;
        }
        
        // Selecionar aleatório baseado em peso
        $rand = mt_rand(1, 100);
        $cumulative = 0;
        
        foreach ($rules as $rule) {
            $cumulative += $rule['normalized'];
            if ($rand <= $cumulative) {
                $agentId = $rule['agent_id'];
                
                // Verificar se agente está disponível
                $agent = \App\Models\User::find($agentId);
                if (!$agent || $agent['status'] !== 'active') {
                    \App\Helpers\Logger::automation("selectAgentByPercentage - Agente {$agentId} não disponível, pulando");
                    continue;
                }
                
                // Verificar disponibilidade
                if ($considerAvailability && $agent['availability_status'] !== 'online') {
                    \App\Helpers\Logger::automation("selectAgentByPercentage - Agente {$agentId} offline, pulando");
                    continue;
                }
                
                // Verificar limites
                if ($considerMaxConversations) {
                    if (!\App\Services\ConversationSettingsService::canAssignToAgent($agentId, $departmentId, null, null)) {
                        \App\Helpers\Logger::automation("selectAgentByPercentage - Agente {$agentId} no limite, pulando");
                        continue;
                    }
                }
                
                \App\Helpers\Logger::automation("selectAgentByPercentage - Selecionado: {$agentId}");
                return $agentId;
            }
        }
        
        return null;
    }

    /**
     * Executar ação: mover para estágio
     */
    private static function executeMoveStage(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        $stageId = $nodeData['stage_id'] ?? null;
        if (!$stageId) {
            return;
        }

        try {
            \App\Services\FunnelService::moveConversation($conversationId, $stageId);
        } catch (\Exception $e) {
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro ao mover estágio: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Executar ação: adicionar tag
     */
    private static function executeSetTag(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        $tagId = $nodeData['tag_id'] ?? null;
        if (!$tagId) {
            return;
        }

        try {
            // Verificar se tag existe
            $tag = \App\Models\Tag::find($tagId);
            if (!$tag) {
                throw new \Exception("Tag não encontrada");
            }

            // Adicionar tag à conversa
            $sql = "INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)";
            \App\Helpers\Database::execute($sql, [$conversationId, $tagId]);
        } catch (\Exception $e) {
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro ao adicionar tag: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Executar delay
     */
    private static function executeDelay(array $nodeData, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        $delaySeconds = $nodeData['delay_seconds'] ?? 0;
        if ($delaySeconds <= 0) {
            return;
        }

        // Obter automation_id da execução ou do contexto
        $automationId = null;
        if ($executionId) {
            $execution = \App\Models\AutomationExecution::find($executionId);
            if ($execution) {
                $automationId = (int)$execution['automation_id'];
            }
        }
        
        if (!$automationId) {
            // Tentar obter do contexto atual (se disponível)
            $automationId = self::getCurrentAutomationId($conversationId);
        }
        
        if (!$automationId) {
            error_log("Não foi possível obter automation_id para delay na conversa {$conversationId}");
            return;
        }

        // Para delays pequenos (< 60s), usar sleep
        // Para delays maiores, usar fila de jobs
        if ($delaySeconds <= 60) {
            sleep($delaySeconds);
            
            // Após sleep, continuar execução normalmente
            if (!empty($nodeData['connections'])) {
                foreach ($nodeData['connections'] as $connection) {
                    $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                    if ($nextNode) {
                        self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                    }
                }
            }
        } else {
            // Agendar delay para execução posterior
            $nodeId = $nodeData['node_id'] ?? uniqid('delay_');
            
            // Obter IDs dos próximos nós
            $nextNodes = [];
            if (!empty($nodeData['connections'])) {
                foreach ($nodeData['connections'] as $connection) {
                    $nextNodes[] = $connection['target_node_id'];
                }
            }
            
            try {
                \App\Services\AutomationDelayService::scheduleDelay(
                    $automationId,
                    $conversationId,
                    $nodeId,
                    $delaySeconds,
                    $nodeData,
                    $nextNodes,
                    $executionId
                );
                
                error_log("Delay de {$delaySeconds}s agendado para conversa {$conversationId} (executará em " . date('Y-m-d H:i:s', time() + $delaySeconds) . ")");
            } catch (\Exception $e) {
                error_log("Erro ao agendar delay: " . $e->getMessage());
                // Em caso de erro, tentar executar imediatamente (fallback)
                if (!empty($nodeData['connections'])) {
                    foreach ($nodeData['connections'] as $connection) {
                        $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                        if ($nextNode) {
                            self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                        }
                    }
                }
            }
        }
    }

    /**
     * Obter automation_id atual do contexto
     */
    private static function getCurrentAutomationId(int $conversationId): ?int
    {
        // Tentar obter da última execução da conversa
        $sql = "SELECT automation_id FROM automation_executions 
                WHERE conversation_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $result = \App\Helpers\Database::fetch($sql, [$conversationId]);
        return $result ? (int)$result['automation_id'] : null;
    }

    /**
     * Executar nó após delay (método público para AutomationDelayService)
     */
    public static function executeNodeForDelay(array $node, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        self::executeNode($node, $conversationId, $allNodes, $executionId);
    }

    /**
     * Executar ação: chatbot
     */
    private static function executeChatbot(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        try {
            $chatbotType = $nodeData['chatbot_type'] ?? 'simple';
            $message = $nodeData['chatbot_message'] ?? '';
            $timeout = (int)($nodeData['chatbot_timeout'] ?? 300);
            $timeoutAction = $nodeData['chatbot_timeout_action'] ?? 'nothing';
            $options = $nodeData['chatbot_options'] ?? [];
            $connections = $nodeData['connections'] ?? [];
            
            if (empty($message)) {
                error_log("Chatbot sem mensagem configurada para conversa {$conversationId}");
                return;
            }
            
            // Processar variáveis na mensagem
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                throw new \Exception("Conversa não encontrada: {$conversationId}");
            }
            
            $message = self::processVariables($message, $conversation);
            
            // Obter automation_id atual (para retomar o fluxo posteriormente)
            $automationId = null;
            if ($executionId) {
                $execution = \App\Models\AutomationExecution::find($executionId);
                if ($execution) {
                    $automationId = (int)$execution['automation_id'];
                }
            }
            if (!$automationId) {
                $automationId = self::getCurrentAutomationId($conversationId);
            }
            
            // Enviar mensagem inicial do chatbot
            \App\Models\Message::create([
                'conversation_id' => $conversationId,
                'sender_id' => null, // Sistema
                'sender_type' => 'system',
                'message' => $message,
                'type' => 'text',
                'channel' => $conversation['channel'],
                'direction' => 'outgoing',
                'status' => 'sent'
            ]);
            
            // Processar opções de menu (se tipo = menu)
            if ($chatbotType === 'menu') {
                if (!empty($options) && is_array($options)) {
                    // opções podem ser [{text, target_node_id}] ou strings antigas
                    $labels = array_map(function ($opt) {
                        if (is_array($opt) && isset($opt['text'])) return $opt['text'];
                        return $opt;
                    }, $options);
                    $labels = array_filter($labels);
                    if (!empty($labels)) {
                        $optionsText = "\n\n" . implode("\n", $labels);
                        
                        \App\Models\Message::create([
                            'conversation_id' => $conversationId,
                            'sender_id' => null,
                            'sender_type' => 'system',
                            'message' => $optionsText,
                            'type' => 'text',
                            'channel' => $conversation['channel'],
                            'direction' => 'outgoing',
                            'status' => 'sent'
                        ]);
                    }
                }
            }
            
            // Para chatbot condicional, salvar palavras-chave para monitorar
            if ($chatbotType === 'conditional') {
                $keywords = $nodeData['chatbot_keywords'] ?? '';
                if (!empty($keywords)) {
                    // Salvar keywords no metadata da conversa para monitoramento
                    $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                    $metadata['chatbot_keywords'] = array_map('trim', explode(',', $keywords));
                    $metadata['chatbot_timeout'] = time() + $timeout;
                    $metadata['chatbot_timeout_action'] = $timeoutAction;
                    
                    \App\Models\Conversation::update($conversationId, [
                        'metadata' => json_encode($metadata)
                    ]);
                }
            }
            
            // Marcar conversa como aguardando resposta do chatbot
            $currentMetadata = json_decode($conversation['metadata'] ?? '{}', true);
            $currentMetadata['chatbot_active'] = true;
            $currentMetadata['chatbot_type'] = $chatbotType;
            $currentMetadata['chatbot_timeout_at'] = time() + $timeout;
            $currentMetadata['chatbot_timeout_action'] = $timeoutAction;
            // Normalizar opções para formato [{text, target_node_id, keywords}]
            $normalizedOptions = [];
            if (!empty($options) && is_array($options)) {
                foreach ($options as $opt) {
                    if (is_array($opt)) {
                        $normalizedOptions[] = [
                            'text' => $opt['text'] ?? '',
                            'target_node_id' => $opt['target_node_id'] ?? null,
                            'keywords' => $opt['keywords'] ?? []
                        ];
                    } else {
                        $normalizedOptions[] = ['text' => $opt, 'target_node_id' => null, 'keywords' => []];
                    }
                }
            }
            $currentMetadata['chatbot_options'] = $normalizedOptions;
            // Se não houver target no item, manter compat com conexões em ordem
            $currentMetadata['chatbot_next_nodes'] = array_values(array_map(fn($c) => $c['target_node_id'] ?? null, $connections));
            $currentMetadata['chatbot_automation_id'] = $automationId;
            $currentMetadata['chatbot_node_id'] = $nodeData['node_id'] ?? null;
            
            \App\Models\Conversation::update($conversationId, [
                'metadata' => json_encode($currentMetadata)
            ]);
            
            error_log("Chatbot ({$chatbotType}) executado para conversa {$conversationId}");
            
        } catch (\Exception $e) {
            error_log("Erro ao executar chatbot: " . $e->getMessage());
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro no chatbot: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Tratar resposta do usuário para continuar o fluxo do chatbot
     */
    private static function handleChatbotResponse(array $conversation, array $message): bool
    {
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        if (empty($metadata['chatbot_active'])) {
            return false;
        }

        $text = trim(mb_strtolower($message['content'] ?? ''));
        if ($text === '') {
            return false;
        }

        $automationId = $metadata['chatbot_automation_id'] ?? null;
        $options = $metadata['chatbot_options'] ?? [];
        $nextNodes = $metadata['chatbot_next_nodes'] ?? [];

        if (!$automationId || empty($options)) {
            // Nada a fazer, limpar flag para evitar loop
            $metadata['chatbot_active'] = false;
            \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
            return false;
        }

        // Encontrar opção correspondente
        $matchedIndex = null;
        foreach ($options as $idx => $optRaw) {
            $optText = is_array($optRaw) ? ($optRaw['text'] ?? '') : $optRaw;
            $optTarget = is_array($optRaw) ? ($optRaw['target_node_id'] ?? null) : null;
            $optKeywords = is_array($optRaw) ? ($optRaw['keywords'] ?? []) : [];
            $opt = mb_strtolower(trim((string)$optText));
            if ($opt === '') {
                continue;
            }

            // Tentar casar por número inicial (ex.: "1 - Suporte")
            if (preg_match('/^(\\d+)/', $opt, $m)) {
                $num = $m[1];
                if ($text === $num || str_starts_with($text, $num)) {
                    $matchedIndex = $idx;
                    break;
                }
            }

            // Comparação direta do texto
            if ($text === $opt) {
                $matchedIndex = $idx;
                break;
            }

            // Palavras-chave configuradas para a opção
            if (!empty($optKeywords) && is_array($optKeywords)) {
                foreach ($optKeywords as $kwRaw) {
                    $kw = mb_strtolower(trim((string)$kwRaw));
                    if ($kw !== '' && $text === $kw) {
                        $matchedIndex = $idx;
                        break 2;
                    }
                }
            }
        }

        if ($matchedIndex === null) {
            return false; // Nenhuma opção casou; manter chatbot ativo
        }

        // Priorizar target explícito na opção; fallback para lista de conexões em ordem
        $optTarget = is_array($options[$matchedIndex]) ? ($options[$matchedIndex]['target_node_id'] ?? null) : null;
        $targetNodeId = $optTarget ?: ($nextNodes[$matchedIndex] ?? null);
        if (!$targetNodeId) {
            return false;
        }

        // Carregar automação e nós
        $automation = \App\Models\Automation::findWithNodes((int)$automationId);
        if (!$automation || empty($automation['nodes'])) {
            return false;
        }

        $nodes = $automation['nodes'];
        $targetNode = self::findNodeById($targetNodeId, $nodes);
        if (!$targetNode) {
            return false;
        }

        // Limpar estado do chatbot antes de continuar
        $metadata['chatbot_active'] = false;
        $metadata['chatbot_options'] = [];
        $metadata['chatbot_next_nodes'] = [];
        $metadata['chatbot_automation_id'] = null;
        $metadata['chatbot_node_id'] = null;
        \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);

        // Continuar fluxo a partir do nó de destino
        self::executeNode($targetNode, $conversation['id'], $nodes, null);

        return true;
    }

    /**
     * Executar condição
     */
    private static function executeCondition(array $nodeData, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        $conditions = $nodeData['conditions'] ?? [];
        $logicOperator = $nodeData['logic_operator'] ?? 'AND'; // AND, OR, NOT, XOR

        if (empty($conditions)) {
            // Compatibilidade com formato antigo (condição única)
            $condition = $nodeData['condition'] ?? null;
            if ($condition) {
                $conditions = [$condition];
            } else {
                return;
            }
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return;
        }

        // Avaliar todas as condições
        $results = [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if ($field && $operator) {
                $results[] = self::evaluateCondition($conversation, $field, $operator, $value);
            }
        }

        // Aplicar operador lógico
        $finalResult = self::evaluateLogicOperator($results, $logicOperator);

        // Seguir para o nó correspondente (true ou false)
        $connectionType = $finalResult ? 'true' : 'false';
        if (!empty($nodeData['connections'])) {
            foreach ($nodeData['connections'] as $connection) {
                if (($connection['type'] ?? 'next') === $connectionType) {
                    $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                    if ($nextNode) {
                        self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                    }
                }
            }
        }
    }

    /**
     * Avaliar operador lógico
     */
    private static function evaluateLogicOperator(array $results, string $operator): bool
    {
        if (empty($results)) {
            return false;
        }

        switch (strtoupper($operator)) {
            case 'AND':
                return !in_array(false, $results, true);
            case 'OR':
                return in_array(true, $results, true);
            case 'NOT':
                return !$results[0] ?? false;
            case 'XOR':
                $trueCount = count(array_filter($results));
                return $trueCount === 1;
            default:
                return $results[0] ?? false;
        }
    }

    /**
     * Avaliar condição
     */
    private static function evaluateCondition(array $conversation, string $field, string $operator, $value): bool
    {
        $conversationValue = $conversation[$field] ?? null;

        switch ($operator) {
            case 'equals':
            case '==':
                return $conversationValue == $value;
            case 'not_equals':
            case '!=':
                return $conversationValue != $value;
            case 'contains':
                return stripos($conversationValue ?? '', $value) !== false;
            case 'not_contains':
                return stripos($conversationValue ?? '', $value) === false;
            case 'greater_than':
            case '>':
                return ($conversationValue ?? 0) > $value;
            case 'less_than':
            case '<':
                return ($conversationValue ?? 0) < $value;
            case 'greater_or_equal':
            case '>=':
                return ($conversationValue ?? 0) >= $value;
            case 'less_or_equal':
            case '<=':
                return ($conversationValue ?? 0) <= $value;
            case 'is_empty':
                return empty($conversationValue);
            case 'is_not_empty':
                return !empty($conversationValue);
            case 'starts_with':
                return stripos($conversationValue ?? '', $value) === 0;
            case 'ends_with':
                return stripos(strrev($conversationValue ?? ''), strrev($value)) === 0;
            case 'in':
                $valueArray = is_array($value) ? $value : explode(',', $value);
                return in_array($conversationValue, $valueArray);
            case 'not_in':
                $valueArray = is_array($value) ? $value : explode(',', $value);
                return !in_array($conversationValue, $valueArray);
            default:
                return false;
        }
    }

    /**
     * Encontrar nó por ID (suporta int ou string)
     */
    private static function findNodeById($nodeId, array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] == $nodeId || (string)$node['id'] === (string)$nodeId) {
                return $node;
            }
        }
        return null;
    }

    /**
     * Executar automação para contato criado
     */
    public static function executeForContactCreated(int $contactId): void
    {
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            return;
        }

        // Buscar automações ativas para contact_created
        $triggerData = [];

        $automations = Automation::getActiveByTrigger('contact_created', $triggerData);

        foreach ($automations as $automation) {
            // Executar automação (pode criar conversa, enviar mensagem, etc.)
            self::executeAutomationForContact($automation['id'], $contactId);
        }
    }

    /**
     * Executar automação para contato atualizado
     */
    public static function executeForContactUpdated(int $contactId, array $changes = []): void
    {
        $contact = \App\Models\Contact::find($contactId);
        if (!$contact) {
            return;
        }

        // Buscar automações ativas para contact_updated
        $triggerData = [];

        $automations = Automation::getActiveByTrigger('contact_updated', $triggerData);

        foreach ($automations as $automation) {
            $config = json_decode($automation['trigger_config'], true);
            
            // Verificar se campo específico foi configurado
            if (!empty($config['field'])) {
                if (!isset($changes[$config['field']])) {
                    continue; // Pular se campo específico não mudou
                }
            }
            
            self::executeAutomationForContact($automation['id'], $contactId);
        }
    }

    /**
     * Executar automação para contato (sem conversa específica)
     */
    private static function executeAutomationForContact(int $automationId, int $contactId): void
    {
        $automation = Automation::findWithNodes($automationId);
        if (!$automation || empty($automation['nodes'])) {
            return;
        }

        // Encontrar nó inicial (trigger)
        $startNode = null;
        foreach ($automation['nodes'] as $node) {
            if ($node['node_type'] === 'trigger') {
                $startNode = $node;
                break;
            }
        }

        if (!$startNode) {
            return;
        }

        // Executar fluxo começando do nó inicial
        self::executeNodeForContact($startNode, $contactId, $automation['nodes']);
    }

    /**
     * Executar nó para contato
     */
    private static function executeNodeForContact(array $node, int $contactId, array $allNodes): void
    {
        $nodeData = $node['node_data'] ?? [];
        
        switch ($node['node_type']) {
            case 'action_create_conversation':
                // Criar conversa para o contato
                $channel = $nodeData['channel'] ?? 'whatsapp';
                try {
                    \App\Services\ConversationService::create([
                        'contact_id' => $contactId,
                        'channel' => $channel,
                        'subject' => $nodeData['subject'] ?? null
                    ]);
                } catch (\Exception $e) {
                    error_log("Erro ao criar conversa: " . $e->getMessage());
                }
                break;
                
            case 'action_send_message':
                // Enviar mensagem (precisa de conversa)
                // Criar conversa primeiro se não existir
                $conversations = \App\Models\Conversation::where('contact_id', '=', $contactId);
                if (empty($conversations)) {
                    $conversation = \App\Services\ConversationService::create([
                        'contact_id' => $contactId,
                        'channel' => $nodeData['channel'] ?? 'whatsapp'
                    ]);
                    $conversationId = $conversation['id'];
                } else {
                    $conversationId = $conversations[0]['id'];
                }
                
                self::executeSendMessage($nodeData, $conversationId);
                break;
                
            case 'action_set_tag':
                // Adicionar tag ao contato
                // TODO: Implementar sistema de tags
                break;
                
            case 'condition':
                // Avaliar condição e seguir fluxo
                $contact = \App\Models\Contact::find($contactId);
                if (!$contact) {
                    return;
                }
                
                $condition = $nodeData['condition'] ?? null;
                $field = $condition['field'] ?? null;
                $operator = $condition['operator'] ?? null;
                $value = $condition['value'] ?? null;

                if ($field && $operator) {
                    $result = self::evaluateContactCondition($contact, $field, $operator, $value);
                    
                    // Seguir para o nó correspondente (true ou false)
                    $connectionType = $result ? 'true' : 'false';
                    if (!empty($nodeData['connections'])) {
                        foreach ($nodeData['connections'] as $connection) {
                            if (($connection['type'] ?? 'next') === $connectionType) {
                                $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                                if ($nextNode) {
                                    self::executeNodeForContact($nextNode, $contactId, $allNodes);
                                }
                            }
                        }
                    }
                }
                return; // Condição já processa os próximos nós
        }

        // Seguir para próximos nós conectados
        if (!empty($nodeData['connections'])) {
            foreach ($nodeData['connections'] as $connection) {
                $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                if ($nextNode) {
                    self::executeNodeForContact($nextNode, $contactId, $allNodes);
                }
            }
        }
    }

    /**
     * Avaliar condição de contato
     */
    private static function evaluateContactCondition(array $contact, string $field, string $operator, $value): bool
    {
        $contactValue = $contact[$field] ?? null;

        switch ($operator) {
            case 'equals':
                return $contactValue == $value;
            case 'not_equals':
                return $contactValue != $value;
            case 'contains':
                return stripos($contactValue ?? '', $value) !== false;
            case 'greater_than':
                return ($contactValue ?? 0) > $value;
            case 'less_than':
                return ($contactValue ?? 0) < $value;
            case 'is_empty':
                return empty($contactValue);
            case 'is_not_empty':
                return !empty($contactValue);
            default:
                return false;
        }
    }

    /**
     * Processar webhook recebido
     */
    public static function processWebhook(string $webhookId, array $payload, array $headers = []): void
    {
        // Buscar automações ativas para webhook com URL específica
        $automations = Automation::getActiveByTrigger('webhook');
        
        foreach ($automations as $automation) {
            $config = json_decode($automation['trigger_config'], true);
            
            // Verificar se URL corresponde
            if (!empty($config['webhook_url']) && $config['webhook_url'] !== $webhookId) {
                continue;
            }
            
            // Verificar headers se configurado
            if (!empty($config['headers'])) {
                $matches = true;
                foreach ($config['headers'] as $headerName => $headerValue) {
                    if (!isset($headers[$headerName]) || $headers[$headerName] !== $headerValue) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }
            
            // Executar automação com payload do webhook
            self::executeAutomationWithPayload($automation['id'], $payload);
        }
    }

    /**
     * Executar automação com payload (para webhooks)
     */
    private static function executeAutomationWithPayload(int $automationId, array $payload): void
    {
        $automation = Automation::findWithNodes($automationId);
        if (!$automation || empty($automation['nodes'])) {
            return;
        }

        // Encontrar nó inicial (trigger)
        $startNode = null;
        foreach ($automation['nodes'] as $node) {
            if ($node['node_type'] === 'trigger') {
                $startNode = $node;
                break;
            }
        }

        if (!$startNode) {
            return;
        }

        // Executar fluxo com payload disponível nos nós
        // TODO: Implementar lógica específica para webhooks
        error_log("Executando automação {$automationId} com payload: " . json_encode($payload));
    }
    
    /**
     * Testar automação em modo teste (não executa ações reais)
     */
    public static function testAutomation(int $automationId, ?int $conversationId = null): array
    {
        $automation = Automation::findWithNodes($automationId);
        if (!$automation || empty($automation['nodes'])) {
            throw new \InvalidArgumentException('Automação não encontrada ou sem nós');
        }
        
        // Se não informar conversa, usar uma de exemplo ou criar dados mock
        $testData = [
            'automation_id' => $automationId,
            'automation_name' => $automation['name'],
            'conversation_id' => $conversationId,
            'steps' => [],
            'errors' => [],
            'warnings' => [],
            'simulated_actions' => []
        ];
        
        // Encontrar nó inicial
        $startNode = null;
        foreach ($automation['nodes'] as $node) {
            if ($node['node_type'] === 'trigger' || empty($node['position_x'])) {
                $startNode = $node;
                break;
            }
        }
        
        if (!$startNode) {
            // Se não houver trigger, usar o primeiro nó
            $startNode = $automation['nodes'][0];
        }
        
        // Simular execução sem executar ações reais
        $visitedNodes = [];
        self::testNode($startNode, $automation['nodes'], $conversationId, $testData, $visitedNodes);
        
        return $testData;
    }
    
    /**
     * Testar nó individual em modo teste
     */
    private static function testNode(array $node, array $allNodes, ?int $conversationId, array &$testData, array &$visitedNodes): void
    {
        // Evitar loops infinitos
        if (in_array($node['id'], $visitedNodes)) {
            return;
        }
        $visitedNodes[] = $node['id'];
        
        $nodeData = $node['node_data'] ?? [];
        $step = [
            'node_id' => $node['id'],
            'node_type' => $node['node_type'],
            'node_name' => $nodeData['name'] ?? $node['node_type'],
            'status' => 'simulated',
            'action_preview' => null,
            'condition_result' => null,
            'error' => null
        ];
        
        try {
            switch ($node['node_type']) {
                case 'action_send_message':
                    $message = $nodeData['message'] ?? '';
                    $preview = self::previewVariables($message, $conversationId);
                    $step['action_preview'] = [
                        'type' => 'send_message',
                        'message' => $preview['processed'],
                        'variables_used' => $preview['variables_used']
                    ];
                    break;
                    
                case 'action_assign_agent':
                    $agentId = $nodeData['agent_id'] ?? null;
                    $agent = $agentId ? \App\Models\User::find($agentId) : null;
                    $step['action_preview'] = [
                        'type' => 'assign_agent',
                        'agent_id' => $agentId,
                        'agent_name' => $agent ? $agent['name'] : 'Não especificado'
                    ];
                    break;
                    
                case 'action_assign_advanced':
                    $assignmentType = $nodeData['assignment_type'] ?? 'auto';
                    $previewText = 'Atribuição: ';
                    
                    switch ($assignmentType) {
                        case 'specific_agent':
                            $agentId = $nodeData['agent_id'] ?? null;
                            $agent = $agentId ? \App\Models\User::find($agentId) : null;
                            $previewText .= $agent ? $agent['name'] : 'Não especificado';
                            break;
                        case 'department':
                            $deptId = $nodeData['department_id'] ?? null;
                            $dept = $deptId ? \App\Models\Department::find($deptId) : null;
                            $previewText .= 'Setor ' . ($dept ? $dept['name'] : 'Não especificado');
                            break;
                        case 'custom_method':
                            $method = $nodeData['distribution_method'] ?? 'round_robin';
                            $methodNames = [
                                'round_robin' => 'Round-Robin',
                                'by_load' => 'Por Carga',
                                'by_performance' => 'Por Performance',
                                'by_specialty' => 'Por Especialidade',
                                'percentage' => 'Por Porcentagem'
                            ];
                            $previewText .= $methodNames[$method] ?? $method;
                            break;
                        case 'auto':
                        default:
                            $previewText .= 'Automática';
                            break;
                    }
                    
                    $step['action_preview'] = [
                        'type' => 'assign_advanced',
                        'preview_text' => $previewText
                    ];
                    break;
                    
                case 'action_move_stage':
                    $stageId = $nodeData['stage_id'] ?? null;
                    $stage = $stageId ? \App\Models\FunnelStage::find($stageId) : null;
                    $step['action_preview'] = [
                        'type' => 'move_stage',
                        'stage_id' => $stageId,
                        'stage_name' => $stage ? $stage['name'] : 'Não especificado'
                    ];
                    break;
                    
                case 'action_set_tag':
                    $tagId = $nodeData['tag_id'] ?? null;
                    $tag = $tagId ? \App\Models\Tag::find($tagId) : null;
                    $step['action_preview'] = [
                        'type' => 'set_tag',
                        'tag_id' => $tagId,
                        'tag_name' => $tag ? $tag['name'] : 'Não especificado'
                    ];
                    break;
                    
                case 'condition':
                    $conditionResult = self::testCondition($nodeData, $conversationId);
                    $step['condition_result'] = $conditionResult;
                    break;
                    
                case 'delay':
                    $delaySeconds = $nodeData['delay_seconds'] ?? 0;
                    $step['action_preview'] = [
                        'type' => 'delay',
                        'seconds' => $delaySeconds,
                        'formatted' => self::formatDelay($delaySeconds)
                    ];
                    break;
            }
            
            $testData['simulated_actions'][] = $step;
        } catch (\Exception $e) {
            $step['status'] = 'error';
            $step['error'] = $e->getMessage();
            $testData['errors'][] = [
                'node_id' => $node['id'],
                'node_type' => $node['node_type'],
                'error' => $e->getMessage()
            ];
        }
        
        $testData['steps'][] = $step;
        
        // Seguir para próximos nós
        if (!empty($nodeData['connections'])) {
            foreach ($nodeData['connections'] as $connection) {
                $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                if ($nextNode) {
                    // Para condições, seguir apenas o caminho verdadeiro em modo teste
                    if ($node['node_type'] === 'condition' && isset($step['condition_result'])) {
                        $connectionType = $connection['type'] ?? 'next';
                        if (($connectionType === 'true' && $step['condition_result']['result']) ||
                            ($connectionType === 'false' && !$step['condition_result']['result'])) {
                            self::testNode($nextNode, $allNodes, $conversationId, $testData, $visitedNodes);
                        }
                    } else {
                        self::testNode($nextNode, $allNodes, $conversationId, $testData, $visitedNodes);
                    }
                }
            }
        }
    }
    
    /**
     * Testar condição sem executar
     */
    private static function testCondition(array $nodeData, ?int $conversationId): array
    {
        $conditions = $nodeData['conditions'] ?? [];
        $logicOperator = $nodeData['logic_operator'] ?? 'AND';
        
        if (empty($conditions)) {
            return [
                'result' => false,
                'reason' => 'Nenhuma condição configurada',
                'conditions_evaluated' => []
            ];
        }
        
        // Se não houver conversa, simular dados
        if (!$conversationId) {
            return [
                'result' => true,
                'reason' => 'Modo teste: condição simulada',
                'conditions_evaluated' => array_map(function($cond) {
                    return [
                        'field' => $cond['field'] ?? '',
                        'operator' => $cond['operator'] ?? '',
                        'value' => $cond['value'] ?? '',
                        'result' => 'simulated'
                    ];
                }, $conditions)
            ];
        }
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            return [
                'result' => false,
                'reason' => 'Conversa não encontrada',
                'conditions_evaluated' => []
            ];
        }
        
        $results = [];
        $booleanResults = [];
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;
            
            if ($field && $operator) {
                $result = self::evaluateCondition($conversation, $field, $operator, $value);
                $results[] = [
                    'field' => $field,
                    'operator' => $operator,
                    'value' => $value,
                    'result' => $result
                ];
                $booleanResults[] = $result;
            }
        }
        
        $finalResult = self::evaluateLogicOperator($booleanResults, $logicOperator);
        
        return [
            'result' => $finalResult,
            'reason' => $finalResult ? 'Todas as condições atendidas' : 'Condições não atendidas',
            'conditions_evaluated' => $results,
            'logic_operator' => $logicOperator
        ];
    }
    
    /**
     * Preview de variáveis em mensagem
     */
    public static function previewVariables(string $message, ?int $conversationId = null): array
    {
        $variablesUsed = [];
        
        // Detectar variáveis usadas
        preg_match_all('/\{\{([^}]+)\}\}/', $message, $matches);
        if (!empty($matches[1])) {
            $variablesUsed = array_unique($matches[1]);
        }
        
        $processed = $message;
        $variablesData = [];
        
        if ($conversationId) {
            $conversation = Conversation::find($conversationId);
            if ($conversation) {
                $contact = \App\Models\Contact::find($conversation['contact_id']);
                $agent = $conversation['agent_id'] ? \App\Models\User::find($conversation['agent_id']) : null;
                
                $variables = [
                    'contact.name' => $contact ? ($contact['name'] ?? 'João Silva') : 'João Silva',
                    'contact.phone' => $contact ? ($contact['phone'] ?? '(11) 98765-4321') : '(11) 98765-4321',
                    'contact.email' => $contact ? ($contact['email'] ?? 'joao@exemplo.com') : 'joao@exemplo.com',
                    'agent.name' => $agent ? ($agent['name'] ?? 'Maria Santos') : 'Maria Santos',
                    'conversation.id' => $conversation['id'],
                    'conversation.subject' => $conversation['subject'] ?? 'Assunto da conversa',
                    'date' => date('d/m/Y'),
                    'time' => date('H:i'),
                    'datetime' => date('d/m/Y H:i')
                ];
            } else {
                // Dados de exemplo
                $variables = [
                    'contact.name' => 'João Silva',
                    'contact.phone' => '(11) 98765-4321',
                    'contact.email' => 'joao@exemplo.com',
                    'agent.name' => 'Maria Santos',
                    'conversation.id' => '123',
                    'conversation.subject' => 'Assunto da conversa',
                    'date' => date('d/m/Y'),
                    'time' => date('H:i'),
                    'datetime' => date('d/m/Y H:i')
                ];
            }
        } else {
            // Dados de exemplo
            $variables = [
                'contact.name' => 'João Silva',
                'contact.phone' => '(11) 98765-4321',
                'contact.email' => 'joao@exemplo.com',
                'agent.name' => 'Maria Santos',
                'conversation.id' => '123',
                'conversation.subject' => 'Assunto da conversa',
                'date' => date('d/m/Y'),
                'time' => date('H:i'),
                'datetime' => date('d/m/Y H:i')
            ];
        }
        
        // Processar variáveis
        foreach ($variables as $key => $value) {
            $variableKey = '{{' . $key . '}}';
            if (strpos($processed, $variableKey) !== false) {
                $processed = str_replace($variableKey, $value, $processed);
                $variablesData[$key] = $value;
            }
        }
        
        return [
            'original' => $message,
            'processed' => $processed,
            'variables_used' => $variablesUsed,
            'variables_data' => $variablesData
        ];
    }
    
    /**
     * Formatar delay em formato legível
     */
    private static function formatDelay(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} segundo(s)";
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes} minuto(s)";
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            if ($minutes > 0) {
                return "{$hours} hora(s) e {$minutes} minuto(s)";
            }
            return "{$hours} hora(s)";
        }
    }
}

