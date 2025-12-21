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
     * Automation ID atual em execução (para uso em metadata de IA)
     */
    private static ?int $currentAutomationId = null;

    /**
     * Log específico para intents IA (arquivo dedicado)
     */
    private static function logIntent(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/ai-intents.log';
        $line = '[' . date('Y-m-d H:i:s') . "] {$message}\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
    /**
     * Criar automação
     */
    public static function create(array $data): int
    {
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|string|in:new_conversation,message_received,conversation_updated,conversation_moved,conversation_resolved,no_customer_response,no_agent_response,time_based,contact_created,contact_updated,agent_activity,webhook',
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
        \App\Helpers\Logger::automation("=== executeForNewConversation INÍCIO === conversationId: {$conversationId}");
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            \App\Helpers\Logger::automation("ERRO: Conversa não encontrada! conversationId: {$conversationId}");
            return;
        }
        
        \App\Helpers\Logger::automation("Conversa encontrada: " . json_encode($conversation));

        // Buscar automações ativas para new_conversation
        $triggerData = [
            'channel' => $conversation['channel'] ?? null,
            'whatsapp_account_id' => $conversation['whatsapp_account_id'] ?? null
        ];

        // Filtrar por funil/estágio se a conversa já estiver em um
        $funnelId = $conversation['funnel_id'] ?? null;
        $stageId = $conversation['funnel_stage_id'] ?? null;

        \App\Helpers\Logger::automation("Buscando automações com: triggerData=" . json_encode($triggerData) . ", funnelId={$funnelId}, stageId={$stageId}");

        $automations = Automation::getActiveByTrigger('new_conversation', $triggerData, $funnelId, $stageId);

        \App\Helpers\Logger::automation("Automações encontradas: " . count($automations));
        
        if (!empty($automations)) {
            \App\Helpers\Logger::automation("Lista de automações: " . json_encode($automations));
        }

        foreach ($automations as $automation) {
            \App\Helpers\Logger::automation("Executando automação ID: {$automation['id']}, Nome: {$automation['name']}");
            try {
                self::executeAutomation($automation['id'], $conversationId);
                \App\Helpers\Logger::automation("Automação ID: {$automation['id']} executada com SUCESSO");
            } catch (\Exception $e) {
                \App\Helpers\Logger::automation("ERRO ao executar automação ID: {$automation['id']} - " . $e->getMessage());
            }
        }
        
        \App\Helpers\Logger::automation("=== executeForNewConversation FIM ===");
    }

    /**
     * Executar automação para mensagem recebida
     */
    public static function executeForMessageReceived(int $messageId): void
    {
        \App\Helpers\Logger::automation("=== executeForMessageReceived INÍCIO ===");
        \App\Helpers\Logger::automation("Message ID: {$messageId}");
        
        $message = \App\Models\Message::find($messageId);
        if (!$message || $message['sender_type'] === 'agent') {
            \App\Helpers\Logger::automation("Mensagem não encontrada ou é de agente. Abortando.");
            return; // Não executar para mensagens de agentes
        }

        \App\Helpers\Logger::automation("Mensagem encontrada: sender_type={$message['sender_type']}, content='{$message['content']}'");

        $conversation = Conversation::find($message['conversation_id']);
        if (!$conversation) {
            \App\Helpers\Logger::automation("Conversa não encontrada. Abortando.");
            return;
        }

        \App\Helpers\Logger::automation("Conversa ID: {$conversation['id']}");
        \App\Helpers\Logger::automation("Metadata bruto: " . ($conversation['metadata'] ?? 'null'));

        // Se há um chatbot ativo aguardando resposta, tentar roteá-lo primeiro
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        \App\Helpers\Logger::automation("Metadata decodificado: " . json_encode($metadata));
        \App\Helpers\Logger::automation("chatbot_active? " . (isset($metadata['chatbot_active']) ? ($metadata['chatbot_active'] ? 'TRUE' : 'FALSE') : 'NÃO EXISTE'));
        
        // Verificar se ramificação de IA está ativa (prioridade)
        // NOTA: A detecção de intent agora é feita ANTES da IA processar (em ConversationService)
        // Esta verificação é mantida como fallback para mensagens antigas
        if (!empty($metadata['ai_branching_active'])) {
            \App\Helpers\Logger::automation("🤖 Ramificação de IA ATIVA detectada! (fallback)");
            
            // Se for mensagem do contato, já foi tratada em ConversationService
            if ($message['sender_type'] === 'contact') {
                \App\Helpers\Logger::automation("⚠️ Mensagem do contato já foi verificada em ConversationService. Pulando...");
            } else {
                // Mensagens da IA ainda precisam ser verificadas aqui
                \App\Helpers\Logger::automation("Analisando intent na mensagem da IA (fallback)...");
                $handled = self::handleAIBranchingResponse($conversation, $message);
                
                if ($handled) {
                    \App\Helpers\Logger::automation("✅ Ramificação tratou a mensagem. Roteou para nó específico.");
                    return;
                }
                \App\Helpers\Logger::automation("⚠️ handleAIBranchingResponse retornou false. Continuando...");
            }
        }
        
        if (!empty($metadata['chatbot_active'])) {
            \App\Helpers\Logger::automation("🤖 Chatbot ATIVO detectado!");
            
            // Verificar se esta é a primeira mensagem do contato (que pode ter disparado new_conversation)
            // Se o chatbot foi ativado recentemente, pode ser que esta mensagem tenha CRIADO a conversa
            // e o chatbot ainda não enviou a mensagem inicial (considerar delay + processamento)
            $isFirstContactMessage = false;
            $conversationCreatedAt = strtotime($conversation['created_at']);
            $messageCreatedAt = strtotime($message['created_at']);
            $timeDiff = abs($messageCreatedAt - $conversationCreatedAt);
            
            \App\Helpers\Logger::automation("Verificando se é primeira mensagem: conversationCreatedAt={$conversation['created_at']}, messageCreatedAt={$message['created_at']}, timeDiff={$timeDiff}s");
            
            if ($timeDiff <= 15) { // Se mensagem foi criada dentro de 15s da conversa (cobre delay + processamento)
                // Contar mensagens do contato antes desta (por ID, não por timestamp)
                $result = \App\Helpers\Database::query(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'contact' 
                     AND id < ?",
                    [$conversation['id'], $messageId]
                );
                
                $contactMessagesBefore = isset($result[0]['count']) ? (int)$result[0]['count'] : 0;
                
                // Também contar mensagens do bot/agente (usar ID para evitar race condition)
                // Conta mensagens do bot com ID menor (que foram inseridas antes)
                $botMessagesResult = \App\Helpers\Database::query(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'agent' 
                     AND id < ?",
                    [$conversation['id'], $messageId]
                );
                
                $botMessagesBefore = isset($botMessagesResult[0]['count']) ? (int)$botMessagesResult[0]['count'] : 0;
                
                // Debug: listar IDs das mensagens do bot para entender a ordem
                if ($botMessagesBefore == 0) {
                    $allBotMessages = \App\Helpers\Database::query(
                        "SELECT id, created_at, LEFT(content, 30) as content_preview 
                         FROM messages 
                         WHERE conversation_id = ? 
                         AND sender_type = 'agent' 
                         ORDER BY id",
                        [$conversation['id']]
                    );
                    \App\Helpers\Logger::automation("DEBUG: Não há mensagens do bot antes. Todas mensagens agent na conversa: " . json_encode($allBotMessages));
                }
                
                \App\Helpers\Logger::automation("Verificação primeira mensagem: messageId={$messageId}, conversationId={$conversation['id']}, timeDiff={$timeDiff}s, contactMessagesBefore={$contactMessagesBefore}, botMessagesBefore={$botMessagesBefore}");
                
                // É primeira mensagem SE:
                // 1. Não há mensagens do contato antes desta E
                // 2. Não há mensagens do bot com ID menor (bot ainda não inseriu sua mensagem no banco quando esta foi criada)
                //
                // Explicação: Se a mensagem do contato foi a que CRIOU a conversa, ela dispara new_conversation
                // que executa o chatbot. O chatbot insere sua mensagem no banco ANTES desta mensagem ser salva.
                // Então se não há mensagens do bot com ID menor, significa que esta mensagem foi salva DEPOIS
                // do chatbot ser executado, mas é a mensagem que DISPAROU o chatbot, não uma resposta.
                $isFirstContactMessage = ($contactMessagesBefore == 0 && $botMessagesBefore == 0);
                
                \App\Helpers\Logger::automation("→ Conclusão: isFirstContactMessage={$isFirstContactMessage}");
            } else {
                \App\Helpers\Logger::automation("TimeDiff {$timeDiff}s > 15s - não verificar se é primeira mensagem");
            }
            
            if ($isFirstContactMessage) {
                \App\Helpers\Logger::automation("⚠️ Esta é a PRIMEIRA mensagem do contato (que criou a conversa). Chatbot ainda não enviou mensagem inicial. Ignorando processamento pelo chatbot.");
                // Não processar pelo chatbot, deixar automações normais tratarem
            } else {
                \App\Helpers\Logger::automation("Chamando handleChatbotResponse...");
            $handled = self::handleChatbotResponse($conversation, $message);
            if ($handled) {
                    \App\Helpers\Logger::automation("✅ Chatbot tratou a mensagem. Não disparar outras automações.");
                return; // Já roteou para o próximo nó do chatbot, não disparar outras automações aqui
            }
                \App\Helpers\Logger::automation("⚠️ handleChatbotResponse retornou false. Continuando com automações normais...");
            }
        } else {
            \App\Helpers\Logger::automation("Chatbot NÃO está ativo. Buscando automações normais...");
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
        \App\Helpers\Logger::automation("=== executeAutomation INÍCIO === automationId: {$automationId}, conversationId: {$conversationId}");
        // Guardar Automation ID atual para uso em nós (ex: ramificação IA)
        self::$currentAutomationId = $automationId;
        
        $automation = Automation::findWithNodes($automationId);
        if (!$automation) {
            \App\Helpers\Logger::automation("ERRO: Automação não encontrada! automationId: {$automationId}");
            return;
        }
        
        if (empty($automation['nodes'])) {
            \App\Helpers\Logger::automation("ERRO: Automação sem nós! automationId: {$automationId}");
            return;
        }
        
        \App\Helpers\Logger::automation("Automação carregada com " . count($automation['nodes']) . " nós");

        // Criar log de execução
        $executionId = null;
        if ($logExecution) {
            try {
                \App\Helpers\Logger::automation("Criando log de execução no banco...");
                $executionId = \App\Models\AutomationExecution::createLog(
                    $automationId,
                    $conversationId,
                    'running'
                );
                \App\Helpers\Logger::automation("Log de execução criado: ID {$executionId}");
            } catch (\Exception $e) {
                error_log("Erro ao criar log de execução: " . $e->getMessage());
                \App\Helpers\Logger::automation("ERRO ao criar log de execução: " . $e->getMessage());
            }
        }

        try {
            // Encontrar nó inicial (trigger)
            \App\Helpers\Logger::automation("Procurando nó trigger...");
            $startNode = null;
            foreach ($automation['nodes'] as $node) {
                \App\Helpers\Logger::automation("  Nó ID {$node['id']}, Tipo: {$node['node_type']}");
                if ($node['node_type'] === 'trigger') {
                    $startNode = $node;
                    \App\Helpers\Logger::automation("  ✅ Nó trigger encontrado: ID {$node['id']}");
                    break;
                }
            }

            if (!$startNode) {
                \App\Helpers\Logger::automation("ERRO: Nó inicial (trigger) não encontrado!");
                if ($executionId) {
                    \App\Models\AutomationExecution::updateStatus($executionId, 'failed', 'Nó inicial não encontrado');
                }
                return;
            }

            // Executar fluxo começando do nó inicial
            \App\Helpers\Logger::automation("Iniciando execução do nó trigger ID: {$startNode['id']}");
            self::executeNode($startNode, $conversationId, $automation['nodes'], $executionId);
            
            \App\Helpers\Logger::automation("Execução do fluxo completada!");
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'completed');
                \App\Helpers\Logger::automation("Status atualizado para 'completed'");
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("ERRO FATAL: " . $e->getMessage());
            \App\Helpers\Logger::automation("Stack trace: " . $e->getTraceAsString());
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', $e->getMessage());
            }
            error_log("Erro ao executar automação {$automationId}: " . $e->getMessage());
            throw $e;
        } finally {
            // Limpar referência do Automation ID atual
            self::$currentAutomationId = null;
        }
        
        \App\Helpers\Logger::automation("=== executeAutomation FIM ===");
    }

    /**
     * Executar nó e seguir o fluxo
     */
    private static function executeNode(array $node, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        \App\Helpers\Logger::automation("  → executeNode: ID {$node['id']}, Tipo: {$node['node_type']}");
        
        $nodeData = $node['node_data'] ?? [];
        
        // Atualizar log com nó atual
        if ($executionId) {
            \App\Models\AutomationExecution::updateStatus($executionId, 'running', null, $node['id']);
            \App\Helpers\Logger::automation("  Status atualizado para nó ID: {$node['id']}");
        }
        
        switch ($node['node_type']) {
            case 'action_send_message':
                \App\Helpers\Logger::automation("  Executando: enviar mensagem");
                self::executeSendMessage($nodeData, $conversationId, $executionId);
                break;
            case 'action_assign_agent':
                \App\Helpers\Logger::automation("  Executando: atribuir agente");
                self::executeAssignAgent($nodeData, $conversationId, $executionId);
                break;
            case 'action_assign_advanced':
                \App\Helpers\Logger::automation("  Executando: atribuição avançada");
                self::executeAssignAdvanced($nodeData, $conversationId, $executionId);
                break;
            case 'action_assign_ai_agent':
                \App\Helpers\Logger::automation("  Executando: atribuir agente de IA");
                $branchingActive = self::executeAssignAIAgent($nodeData, $conversationId, $executionId);
                // Se ramificação por intent estiver ativa, pausar o fluxo aqui
                if ($branchingActive) {
                    \App\Helpers\Logger::automation("  🤖 Ramificação IA ativa - PAUSANDO fluxo até detecção de intent.");
                    return;
                }
                break;
            case 'action_move_stage':
                \App\Helpers\Logger::automation("  Executando: mover etapa");
                self::executeMoveStage($nodeData, $conversationId, $executionId);
                break;
            case 'action_set_tag':
                \App\Helpers\Logger::automation("  Executando: definir tag");
                self::executeSetTag($nodeData, $conversationId, $executionId);
                break;
            case 'action_chatbot':
                \App\Helpers\Logger::automation("  Executando: chatbot");
                self::executeChatbot($nodeData, $conversationId, $executionId);
                \App\Helpers\Logger::automation("  ⏸️ Chatbot executado - PAUSANDO execução. Aguardando resposta do usuário.");
                \App\Helpers\Logger::automation("  Próximos nós serão executados após resposta do usuário via handleChatbotResponse()");
                return; // ✅ CHATBOT PAUSA AQUI - não continuar para próximos nós!
            case 'condition':
                \App\Helpers\Logger::automation("  Executando: condição");
                self::executeCondition($nodeData, $conversationId, $allNodes, $executionId);
                return; // Condição já processa os próximos nós
            case 'delay':
                \App\Helpers\Logger::automation("  Executando: delay");
                self::executeDelay($nodeData, $conversationId, $allNodes, $executionId);
                return; // Delay precisa aguardar
            case 'trigger':
                \App\Helpers\Logger::automation("  Nó trigger - pulando execução");
                break;
            default:
                \App\Helpers\Logger::automation("  AVISO: Tipo de nó desconhecido: {$node['node_type']}");
        }

        // Seguir para próximos nós conectados
        if (!empty($nodeData['connections'])) {
            \App\Helpers\Logger::automation("  Nó tem " . count($nodeData['connections']) . " conexão(ões)");
            foreach ($nodeData['connections'] as $connection) {
                \App\Helpers\Logger::automation("    → Seguindo para nó: {$connection['target_node_id']}");
                $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                if ($nextNode) {
                    self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                } else {
                    \App\Helpers\Logger::automation("    ERRO: Nó {$connection['target_node_id']} não encontrado!");
                }
            }
        } else {
            \App\Helpers\Logger::automation("  Nó não tem conexões - fim do fluxo");
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
     * Executar ação: atribuir agente de IA
     */
    private static function executeAssignAIAgent(array $nodeData, int $conversationId, ?int $executionId = null): bool
    {
        \App\Helpers\Logger::automation("executeAssignAIAgent - INÍCIO. Conversa {$conversationId}");

        // Dados do form
        $aiAgentId         = !empty($nodeData['ai_agent_id']) ? (int)$nodeData['ai_agent_id'] : null;
        $processImmediately= !empty($nodeData['process_immediately']);
        $assumeConversation= !empty($nodeData['assume_conversation']);
        $onlyIfUnassigned  = !empty($nodeData['only_if_unassigned']);

        // Configuração de ramificação/intent
        $aiBranchingEnabled        = !empty($nodeData['ai_branching_enabled']);
        $aiIntents                 = $nodeData['ai_intents'] ?? [];
        $aiMaxInteractions         = isset($nodeData['ai_max_interactions']) ? (int)$nodeData['ai_max_interactions'] : 5;
        $aiFallbackNodeId          = !empty($nodeData['ai_fallback_node_id']) ? (int)$nodeData['ai_fallback_node_id'] : null;
        $aiSemanticEnabled         = array_key_exists('ai_intent_semantic_enabled', $nodeData) ? (bool)$nodeData['ai_intent_semantic_enabled'] : true;
        $aiIntentConfidence        = isset($nodeData['ai_intent_confidence']) ? (float)$nodeData['ai_intent_confidence'] : 0.35;

        // Se há intents configurados, força ramificação ligada
        if (!empty($aiIntents)) {
            $aiBranchingEnabled = true;
        }

        try {
            // Se não informou agente, pegar o primeiro disponível
            if (!$aiAgentId) {
                $available = \App\Services\ConversationAIService::getAvailableAgents();
                $first = $available[0]['id'] ?? null;
                if ($first) {
                    $aiAgentId = (int)$first;
                    \App\Helpers\Logger::automation("executeAssignAIAgent - usando primeiro agente disponível ID {$aiAgentId}");
                } else {
                    \App\Helpers\Logger::automation("executeAssignAIAgent - Nenhum agente de IA disponível.");
                    return false;
                }
            }

            // Atribuir IA à conversa
            \App\Services\ConversationAIService::addAIAgent($conversationId, [
                'ai_agent_id'       => $aiAgentId,
                'process_immediately'=> $processImmediately,
                'assume_conversation'=> $assumeConversation,
                'only_if_unassigned' => $onlyIfUnassigned,
            ]);

            \App\Helpers\Logger::automation("executeAssignAIAgent - IA atribuída com sucesso (ID {$aiAgentId}).");

            // Atualizar metadata para ramificação por intent
            $conversation = Conversation::find($conversationId);
            $metadata = json_decode($conversation['metadata'] ?? '{}', true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $metadata['ai_branching_active']          = (bool)$aiBranchingEnabled;
            $metadata['ai_intents']                   = is_array($aiIntents) ? $aiIntents : [];
            $metadata['ai_max_interactions']          = $aiMaxInteractions;
            $metadata['ai_fallback_node_id']          = $aiFallbackNodeId;
            $metadata['ai_intent_semantic_enabled']   = $aiSemanticEnabled;
            $metadata['ai_intent_confidence']         = $aiIntentConfidence;
            $metadata['ai_interaction_count']         = 0;
            $metadata['ai_branching_automation_id']   = self::$currentAutomationId;

            Conversation::update($conversationId, [
                'metadata' => json_encode($metadata)
            ]);

            \App\Helpers\Logger::automation("executeAssignAIAgent - Metadata atualizada (branching " . ($aiBranchingEnabled ? 'ON' : 'OFF') . ").");
            return (bool)$aiBranchingEnabled;
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("executeAssignAIAgent - ERRO: " . $e->getMessage());
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
        \App\Helpers\Logger::automation("executeMoveStage - INÍCIO");
        \App\Helpers\Logger::automation("  nodeData recebido: " . json_encode($nodeData));
        
        $stageId = $nodeData['stage_id'] ?? null;
        $funnelId = $nodeData['funnel_id'] ?? null;
        $validateRules = $nodeData['validate_rules'] ?? true;
        
        \App\Helpers\Logger::automation("  stage_id: " . ($stageId ?: 'NULL'));
        \App\Helpers\Logger::automation("  funnel_id: " . ($funnelId ?: 'NULL'));
        \App\Helpers\Logger::automation("  validate_rules: " . ($validateRules ? 'SIM' : 'NÃO'));
        
        if (!$stageId) {
            \App\Helpers\Logger::automation("  ❌ ERRO: stage_id não fornecido!");
            return;
        }

        try {
            // Buscar informações do estágio
            $stage = \App\Models\FunnelStage::find($stageId);
            if (!$stage) {
                \App\Helpers\Logger::automation("  ❌ ERRO: Estágio ID {$stageId} não encontrado!");
                throw new \Exception("Estágio não encontrado");
            }
            
            \App\Helpers\Logger::automation("  Estágio encontrado: {$stage['name']} (Funil ID: {$stage['funnel_id']})");
            
            // Buscar conversa atual
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                \App\Helpers\Logger::automation("  ❌ ERRO: Conversa ID {$conversationId} não encontrada!");
                throw new \Exception("Conversa não encontrada");
            }
            
            \App\Helpers\Logger::automation("  Conversa atual - Funil: {$conversation['funnel_id']}, Estágio: {$conversation['funnel_stage_id']}");
            
            // Mover conversa (com bypass de permissões pois é automação)
            \App\Helpers\Logger::automation("  Movendo conversa {$conversationId} para estágio {$stageId}...");
            
            try {
                $result = \App\Services\FunnelService::moveConversation($conversationId, $stageId, null, true);
                \App\Helpers\Logger::automation("  ✅ Conversa movida com sucesso! Resultado: " . ($result ? 'TRUE' : 'FALSE'));
            } catch (\Exception $moveException) {
                \App\Helpers\Logger::automation("  ❌ EXCEÇÃO ao chamar moveConversation: " . $moveException->getMessage());
                \App\Helpers\Logger::automation("  Stack trace: " . $moveException->getTraceAsString());
                throw $moveException;
            }
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("  ❌ ERRO GERAL ao executar mover conversa: " . $e->getMessage());
            \App\Helpers\Logger::automation("  Linha: " . $e->getLine() . ", Arquivo: " . $e->getFile());
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro ao mover estágio: " . $e->getMessage());
            }
            throw $e;
        }
        
        \App\Helpers\Logger::automation("executeMoveStage - FIM");
    }

    /**
     * Executar ação: adicionar ou remover tag
     */
    private static function executeSetTag(array $nodeData, int $conversationId, ?int $executionId = null): void
    {
        $tagId = $nodeData['tag_id'] ?? null;
        $tagAction = $nodeData['tag_action'] ?? 'add';
        
        if (!$tagId) {
            \App\Helpers\Logger::automation("  ⚠️ Tag ID não informado, pulando ação");
            return;
        }

        try {
            // Verificar se tag existe
            $tag = \App\Models\Tag::find($tagId);
            if (!$tag) {
                throw new \Exception("Tag ID {$tagId} não encontrada");
            }

            \App\Helpers\Logger::automation("  Tag: {$tag['name']} (ID: {$tagId}), Ação: {$tagAction}");
            
            // Executar ação (add ou remove)
            if ($tagAction === 'remove') {
                // Remover tag da conversa
                $sql = "DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?";
                \App\Helpers\Database::execute($sql, [$conversationId, $tagId]);
                \App\Helpers\Logger::automation("  ✅ Tag '{$tag['name']}' removida da conversa {$conversationId}");
            } else {
                // Adicionar tag à conversa (padrão)
            $sql = "INSERT IGNORE INTO conversation_tags (conversation_id, tag_id) VALUES (?, ?)";
            \App\Helpers\Database::execute($sql, [$conversationId, $tagId]);
                \App\Helpers\Logger::automation("  ✅ Tag '{$tag['name']}' adicionada à conversa {$conversationId}");
            }
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("  ❌ Erro ao processar tag: " . $e->getMessage());
            if ($executionId) {
                \App\Models\AutomationExecution::updateStatus($executionId, 'failed', "Erro ao processar tag: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Executar delay
     */
    private static function executeDelay(array $nodeData, int $conversationId, array $allNodes, ?int $executionId = null): void
    {
        // Compatibilidade: alguns nós usam delay_value + delay_unit em vez de delay_seconds
        $delaySeconds = $nodeData['delay_seconds'] ?? null;
        if ($delaySeconds === null) {
            $delayValue = (int)($nodeData['delay_value'] ?? 0);
            $delayUnit = $nodeData['delay_unit'] ?? 'seconds';
            switch ($delayUnit) {
                case 'minutes':
                    $delaySeconds = $delayValue * 60;
                    break;
                case 'hours':
                    $delaySeconds = $delayValue * 3600;
                    break;
                case 'seconds':
                default:
                    $delaySeconds = $delayValue;
                    break;
            }
        }

        if ($delaySeconds <= 0) {
            \App\Helpers\Logger::automation("executeDelay: delaySeconds calculado <= 0 (valor recebido: {$delaySeconds}). Abortando.");
            return;
        }

        \App\Helpers\Logger::automation("executeDelay INÍCIO: delaySeconds={$delaySeconds}, conversationId={$conversationId}");

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
            \App\Helpers\Logger::automation("executeDelay ERRO: não obteve automation_id para conversa {$conversationId}");
            return;
        }

        // Para delays pequenos (< 60s), usar sleep
        // Para delays maiores, usar fila de jobs
        if ($delaySeconds <= 60) {
            \App\Helpers\Logger::automation("executeDelay: modo síncrono (sleep) para {$delaySeconds}s, automationId={$automationId}");
            sleep($delaySeconds);
            
            // Após sleep, continuar execução normalmente
            if (!empty($nodeData['connections'])) {
                \App\Helpers\Logger::automation("executeDelay: retomando após sleep, conexões=" . count($nodeData['connections']));
                foreach ($nodeData['connections'] as $connection) {
                    \App\Helpers\Logger::automation("executeDelay: seguindo para nó {$connection['target_node_id']} após sleep");
                    $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                    if ($nextNode) {
                        self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                    }
                }
            } else {
                \App\Helpers\Logger::automation("executeDelay: nenhuma conexão encontrada após sleep");
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
                \App\Helpers\Logger::automation("executeDelay: agendando delay de {$delaySeconds}s (async) para conversa {$conversationId}, automation {$automationId}, node {$nodeId}, próximos nós: " . json_encode($nextNodes));
                \App\Services\AutomationDelayService::scheduleDelay(
                    $automationId,
                    $conversationId,
                    $nodeId,
                    $delaySeconds,
                    $nodeData,
                    $nextNodes,
                    $executionId
                );
                
                // Importante: não seguir para próximos nós nem marcar completed; aguardar cron retomar
                if ($executionId) {
                    \App\Models\AutomationExecution::updateStatus($executionId, 'waiting', "Delay agendado por {$delaySeconds}s");
                }
                
                error_log("Delay de {$delaySeconds}s agendado para conversa {$conversationId} (executará em " . date('Y-m-d H:i:s', time() + $delaySeconds) . ")");
                return; // Pausar aqui; retomará pelo cron
            } catch (\Exception $e) {
                error_log("Erro ao agendar delay: " . $e->getMessage());
                \App\Helpers\Logger::automation("executeDelay: erro ao agendar delay: " . $e->getMessage());
                // Em caso de erro, tentar executar imediatamente (fallback)
                if (!empty($nodeData['connections'])) {
                    foreach ($nodeData['connections'] as $connection) {
                        \App\Helpers\Logger::automation("executeDelay fallback: seguindo para nó {$connection['target_node_id']} após erro no agendamento");
                        $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                        if ($nextNode) {
                            self::executeNode($nextNode, $conversationId, $allNodes, $executionId);
                        }
                    }
                } else {
                    \App\Helpers\Logger::automation("executeDelay fallback: nenhuma conexão para seguir após erro no agendamento");
                }
            }
        }
        \App\Helpers\Logger::automation("executeDelay FIM");
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
        \App\Helpers\Logger::automation("    → executeChatbot: conversationId={$conversationId}");
        
        try {
            $chatbotType = $nodeData['chatbot_type'] ?? 'simple';
            $message = $nodeData['chatbot_message'] ?? '';
            $timeout = (int)($nodeData['chatbot_timeout'] ?? 300);
            $timeoutAction = $nodeData['chatbot_timeout_action'] ?? 'nothing';
            $options = $nodeData['chatbot_options'] ?? [];
            $connections = $nodeData['connections'] ?? [];
            
            \App\Helpers\Logger::automation("    Tipo: {$chatbotType}, Mensagem: " . substr($message, 0, 50) . "..., Opções: " . count($options));
            
            if (empty($message)) {
                \App\Helpers\Logger::automation("    ERRO: Chatbot sem mensagem configurada!");
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
            
            // Enviar mensagem inicial do chatbot VIA WHATSAPP
            \App\Helpers\Logger::automation("    Enviando mensagem do chatbot via WhatsApp...");
            
            try {
                // Buscar dados do contato
                $contact = \App\Models\Contact::find($conversation['contact_id']);
                if (!$contact) {
                    throw new \Exception("Contato não encontrado: {$conversation['contact_id']}");
                }
                
                $whatsappAccountId = $conversation['whatsapp_account_id'];
                if (!$whatsappAccountId) {
                    throw new \Exception("Conversa sem conta WhatsApp vinculada");
                }
                
                // Enviar via API do WhatsApp
                \App\Helpers\Logger::automation("    Enviando via WhatsApp para: {$contact['phone']}");
                $response = \App\Services\WhatsAppService::sendMessage(
                    $whatsappAccountId,
                    $contact['phone'],
                    $message
                );
                
                \App\Helpers\Logger::automation("    ✅ Mensagem enviada via WhatsApp! Response: " . json_encode($response));
                
                // Extrair external_id da resposta (se disponível) para evitar duplicatas quando webhook retornar
                $externalId = null;
                if (isset($response['id'])) {
                    $externalId = $response['id'];
                } elseif (isset($response['message_id'])) {
                    $externalId = $response['message_id'];
                } elseif (isset($response['data']['id'])) {
                    $externalId = $response['data']['id'];
                }
                
                // Salvar mensagem no banco (já foi enviada)
                $messageId = \App\Models\Message::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => null, // Sistema
                    'sender_type' => 'agent', // Marcar como 'agent' para aparecer como mensagem da empresa
                    'content' => $message,
                    'message_type' => 'text',
                    'channel' => 'whatsapp',
                    'external_id' => $externalId, // Salvar ID externo para evitar duplicatas
                    'metadata' => json_encode(['chatbot_message' => true, 'sent_at' => time()]) // Marcar como mensagem do chatbot
                ]);
                
                \App\Helpers\Logger::automation("    ✅ Mensagem salva no banco: ID {$messageId}, external_id={$externalId}");
                
            } catch (\Exception $e) {
                \App\Helpers\Logger::automation("    ❌ ERRO ao enviar via WhatsApp: " . $e->getMessage());
                // Se falhar o envio, ainda salvar no banco para registro
                $messageId = \App\Models\Message::create([
                    'conversation_id' => $conversationId,
                    'sender_id' => null,
                    'sender_type' => 'system',
                    'content' => $message . "\n\n[ERRO: Não foi possível enviar via WhatsApp]",
                    'message_type' => 'text',
                    'channel' => 'whatsapp'
                ]);
                throw $e; // Re-lançar erro para marcar automação como failed
            }
            
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
                        $optionsText = implode("\n", $labels);
                        
                        try {
                            // Enviar opções via WhatsApp
                            \App\Helpers\Logger::automation("    Enviando opções do menu via WhatsApp...");
                            $response = \App\Services\WhatsAppService::sendMessage(
                                $whatsappAccountId,
                                $contact['phone'],
                                $optionsText
                            );
                            
                            \App\Helpers\Logger::automation("    ✅ Opções enviadas via WhatsApp!");
                            
                            // Salvar no banco
                            \App\Models\Message::create([
                                'conversation_id' => $conversationId,
                                'sender_id' => null,
                                'sender_type' => 'agent',
                                'content' => $optionsText,
                                'message_type' => 'text',
                                'channel' => 'whatsapp'
                            ]);
                        } catch (\Exception $e) {
                            \App\Helpers\Logger::automation("    ⚠️ Erro ao enviar opções: " . $e->getMessage());
                            // Continuar mesmo se falhar (já enviou a mensagem principal)
                        }
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
            
            // Configurações de validação e feedback
            $currentMetadata['chatbot_max_attempts'] = (int)($nodeData['chatbot_max_attempts'] ?? 3);
            $currentMetadata['chatbot_invalid_feedback'] = $nodeData['chatbot_invalid_feedback'] ?? 'Opção inválida. Por favor, escolha uma das opções disponíveis.';
            $currentMetadata['chatbot_fallback_node_id'] = $nodeData['chatbot_fallback_node_id'] ?? null;
            $currentMetadata['chatbot_invalid_attempts'] = 0; // Resetar contador
            
            \App\Helpers\Logger::automation("    Salvando estado do chatbot no metadata...");
            \App\Helpers\Logger::automation("    Metadata a ser salvo: " . json_encode($currentMetadata));
            \App\Models\Conversation::update($conversationId, [
                'metadata' => json_encode($currentMetadata)
            ]);
            \App\Helpers\Logger::automation("    ✅ Estado salvo! Chatbot aguardando resposta do contato.");
            
            // Verificar se realmente salvou
            $conversationCheck = \App\Models\Conversation::find($conversationId);
            $metadataCheck = json_decode($conversationCheck['metadata'] ?? '{}', true);
            \App\Helpers\Logger::automation("    🔍 Verificação pós-salvamento: chatbot_active = " . ($metadataCheck['chatbot_active'] ? 'TRUE' : 'FALSE'));
            
            error_log("Chatbot ({$chatbotType}) executado para conversa {$conversationId}");
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("    ❌ ERRO no chatbot: " . $e->getMessage());
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
        \App\Helpers\Logger::automation("=== handleChatbotResponse INÍCIO ===");
        \App\Helpers\Logger::automation("Conversa ID: {$conversation['id']}, Mensagem: '{$message['content']}'");
        
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        \App\Helpers\Logger::automation("chatbot_active: " . ($metadata['chatbot_active'] ?? 'false'));
        
        if (empty($metadata['chatbot_active'])) {
            \App\Helpers\Logger::automation("Chatbot não está ativo. Retornando false.");
            return false;
        }

        $text = trim(mb_strtolower($message['content'] ?? ''));
        \App\Helpers\Logger::automation("Texto processado: '{$text}'");
        
        if ($text === '') {
            \App\Helpers\Logger::automation("Texto vazio. Retornando false.");
            return false;
        }

        $automationId = $metadata['chatbot_automation_id'] ?? null;
        $options = $metadata['chatbot_options'] ?? [];
        $nextNodes = $metadata['chatbot_next_nodes'] ?? [];
        
        \App\Helpers\Logger::automation("Automation ID: {$automationId}");
        \App\Helpers\Logger::automation("Opções: " . json_encode($options));
        \App\Helpers\Logger::automation("Next Nodes: " . json_encode($nextNodes));

        if (!$automationId || empty($options)) {
            \App\Helpers\Logger::automation("Sem automationId ou opções. Limpando flag.");
            // Nada a fazer, limpar flag para evitar loop
            $metadata['chatbot_active'] = false;
            \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
            return false;
        }

        // Contador de tentativas inválidas
        $invalidAttempts = (int)($metadata['chatbot_invalid_attempts'] ?? 0);
        $maxAttempts = (int)($metadata['chatbot_max_attempts'] ?? 3);

        // Encontrar opção correspondente
        $matchedIndex = null;
        foreach ($options as $idx => $optRaw) {
            $optText = is_array($optRaw) ? ($optRaw['text'] ?? '') : $optRaw;
            $optTarget = is_array($optRaw) ? ($optRaw['target_node_id'] ?? null) : null;
            $optKeywords = is_array($optRaw) ? ($optRaw['keywords'] ?? []) : [];
            $opt = mb_strtolower(trim((string)$optText));
            
            \App\Helpers\Logger::automation("  Testando opção [{$idx}]: '{$optText}' (normalizado: '{$opt}')");
            
            if ($opt === '') {
                \App\Helpers\Logger::automation("    Opção vazia, pulando");
                continue;
            }

            // Tentar casar por número inicial (ex.: "1 - Suporte")
            if (preg_match('/^(\\d+)/', $opt, $m)) {
                $num = $m[1];
                \App\Helpers\Logger::automation("    Número extraído: '{$num}', comparando com '{$text}'");
                if ($text === $num || str_starts_with($text, $num)) {
                    \App\Helpers\Logger::automation("    ✅ MATCH por número!");
                    $matchedIndex = $idx;
                    break;
                }
            }

            // Comparação direta do texto
            if ($text === $opt) {
                \App\Helpers\Logger::automation("    ✅ MATCH direto!");
                $matchedIndex = $idx;
                break;
            }

            // Palavras-chave configuradas para a opção
            if (!empty($optKeywords) && is_array($optKeywords)) {
                foreach ($optKeywords as $kwRaw) {
                    $kw = mb_strtolower(trim((string)$kwRaw));
                    \App\Helpers\Logger::automation("    Testando keyword: '{$kw}'");
                    if ($kw !== '' && $text === $kw) {
                        \App\Helpers\Logger::automation("    ✅ MATCH por keyword!");
                        $matchedIndex = $idx;
                        break 2;
                    }
                }
            }
        }

        if ($matchedIndex === null) {
            \App\Helpers\Logger::automation("❌ Nenhuma opção correspondeu!");
            
            // Incrementar contador de tentativas inválidas
            $invalidAttempts++;
            $metadata['chatbot_invalid_attempts'] = $invalidAttempts;
            
            \App\Helpers\Logger::automation("Tentativa inválida #{$invalidAttempts} de {$maxAttempts}");
            
            // Verificar se excedeu tentativas
            if ($invalidAttempts >= $maxAttempts) {
                \App\Helpers\Logger::automation("🚨 Máximo de tentativas excedido!");
                
                // Verificar se há nó fallback configurado
                $fallbackNodeId = $metadata['chatbot_fallback_node_id'] ?? null;
                
                if ($fallbackNodeId) {
                    \App\Helpers\Logger::automation("Executando nó fallback: {$fallbackNodeId}");
                    
                    // Carregar automação e executar nó fallback
                    $automation = \App\Models\Automation::findWithNodes((int)$automationId);
                    if ($automation && !empty($automation['nodes'])) {
                        $fallbackNode = self::findNodeById($fallbackNodeId, $automation['nodes']);
                        if ($fallbackNode) {
                            // Limpar estado do chatbot
                            $metadata['chatbot_active'] = false;
                            $metadata['chatbot_options'] = [];
                            $metadata['chatbot_next_nodes'] = [];
                            $metadata['chatbot_automation_id'] = null;
                            $metadata['chatbot_node_id'] = null;
                            $metadata['chatbot_invalid_attempts'] = 0;
                            \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
                            
                            // Executar nó fallback
                            self::executeNode($fallbackNode, $conversation['id'], $automation['nodes'], null);
                            return true;
                        }
                    }
                }
                
                // Se não tem fallback, limpar e enviar mensagem padrão
                $metadata['chatbot_active'] = false;
                $metadata['chatbot_invalid_attempts'] = 0;
                \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
                
                // Enviar mensagem de erro final
                try {
                    \App\Services\ConversationService::sendMessage(
                        $conversation['id'],
                        "Desculpe, não consegui entender suas respostas. Por favor, aguarde que um atendente entrará em contato.",
                        'agent',
                        null
                    );
                } catch (\Exception $e) {
                    \App\Helpers\Logger::automation("Erro ao enviar mensagem de erro: " . $e->getMessage());
                }
                
                return false;
            }
            
            // Ainda tem tentativas, salvar contador e enviar feedback
            \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
            
            // Enviar mensagem de feedback
            $feedbackMessage = $metadata['chatbot_invalid_feedback'] ?? "Opção inválida. Por favor, escolha uma das opções disponíveis.";
            try {
                \App\Services\ConversationService::sendMessage(
                    $conversation['id'],
                    $feedbackMessage,
                    'agent',
                    null
                );
                \App\Helpers\Logger::automation("✅ Mensagem de feedback enviada. Aguardando nova tentativa.");
            } catch (\Exception $e) {
                \App\Helpers\Logger::automation("Erro ao enviar feedback: " . $e->getMessage());
            }
            
            return true; // Chatbot tratou a mensagem (inválida), não disparar outras automações
        }

        \App\Helpers\Logger::automation("✅ Opção encontrada: índice {$matchedIndex}");

        // Priorizar target explícito na opção; fallback para lista de conexões em ordem
        $optTarget = is_array($options[$matchedIndex]) ? ($options[$matchedIndex]['target_node_id'] ?? null) : null;
        $targetNodeId = $optTarget ?: ($nextNodes[$matchedIndex] ?? null);
        
        \App\Helpers\Logger::automation("Target Node ID: {$targetNodeId}");
        
        if (!$targetNodeId) {
            \App\Helpers\Logger::automation("❌ Sem target node ID. Retornando false.");
            return false;
        }

        // Carregar automação e nós
        $automation = \App\Models\Automation::findWithNodes((int)$automationId);
        if (!$automation || empty($automation['nodes'])) {
            \App\Helpers\Logger::automation("❌ Automação não encontrada ou sem nós.");
            return false;
        }

        $nodes = $automation['nodes'];
        $targetNode = self::findNodeById($targetNodeId, $nodes);
        if (!$targetNode) {
            \App\Helpers\Logger::automation("❌ Target node não encontrado.");
            return false;
        }
        
        \App\Helpers\Logger::automation("✅ Target node encontrado: " . json_encode($targetNode));

        // Limpar estado do chatbot antes de continuar
        $metadata['chatbot_active'] = false;
        $metadata['chatbot_options'] = [];
        $metadata['chatbot_next_nodes'] = [];
        $metadata['chatbot_automation_id'] = null;
        $metadata['chatbot_node_id'] = null;
        $metadata['chatbot_invalid_attempts'] = 0;
        \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
        
        \App\Helpers\Logger::automation("Estado do chatbot limpo. Executando target node...");

        // Continuar fluxo a partir do nó de destino
        self::executeNode($targetNode, $conversation['id'], $nodes, null);
        
        \App\Helpers\Logger::automation("=== handleChatbotResponse FIM (true) ===");

        return true;
    }

    /**
     * Tratar resposta da IA e rotear para nó baseado em intent
     * Também pode ser chamado para detectar intent em mensagens do cliente
     */
    public static function handleAIBranchingResponse(array $conversation, array $message): bool
    {
        \App\Helpers\Logger::automation("=== handleAIBranchingResponse INÍCIO ===");
        $senderType = $message['sender_type'] ?? 'unknown';
        \App\Helpers\Logger::automation("Conversa ID: {$conversation['id']}, Sender: {$senderType}, Mensagem: '" . substr($message['content'] ?? '', 0, 100) . "'");
        self::logIntent("=== handleAIBranchingResponse === conv:{$conversation['id']} sender:{$senderType} msg:'" . ($message['content'] ?? '') . "'");
        
        $metadata = json_decode($conversation['metadata'] ?? '{}', true);
        
        if (empty($metadata['ai_branching_active'])) {
            \App\Helpers\Logger::automation("Ramificação de IA não está ativa. Retornando false.");
            self::logIntent("ramificacao_inativa");
            return false;
        }
        
        // Incrementar contador de interações
        $interactionCount = (int)($metadata['ai_interaction_count'] ?? 0) + 1;
        $maxInteractions = (int)($metadata['ai_max_interactions'] ?? 5);
        
        \App\Helpers\Logger::automation("Interação {$interactionCount}/{$maxInteractions}");
        self::logIntent("interacao {$interactionCount}/{$maxInteractions}");
        
        // Verificar se atingiu máximo de interações
        if ($interactionCount >= $maxInteractions) {
            \App\Helpers\Logger::automation("Máximo de interações atingido. Escalando para humano...");
            self::logIntent("max_interactions");
            return self::escalateFromAI($conversation['id'], $metadata);
        }
        
        // Analisar a mensagem para identificar intent (primeiro por keywords, depois por IA semântica)
        $detectedIntent = self::detectAIIntent($message['content'] ?? '', $metadata['ai_intents'] ?? []);
        if ($detectedIntent) {
            self::logIntent("match_keywords intent=" . ($detectedIntent['intent'] ?? ''));
        }

        // Fallback: detecção semântica via OpenAI (usando descrição do intent)
        if (!$detectedIntent && !empty($metadata['ai_intents'])) {
            // Ajuste: permitir confiança mínima mais baixa para intents simples
            $minConfidence = isset($metadata['ai_intent_confidence']) ? (float)$metadata['ai_intent_confidence'] : 0.35;
            $minConfidence = max(0.2, $minConfidence); // não deixar abaixo de 0.2
            $semanticEnabled = $metadata['ai_intent_semantic_enabled'] ?? true; // habilitado por padrão
            if ($semanticEnabled) {
                \App\Helpers\Logger::automation("Nenhum match por keywords. Tentando detecção semântica via OpenAI (min confidence {$minConfidence})");
                self::logIntent("semantica_on minConf={$minConfidence}");
                $detectedIntent = self::detectAIIntentSemantic($message['content'] ?? '', $metadata['ai_intents'] ?? [], $minConfidence, (int)$conversation['id']);
            } else {
                \App\Helpers\Logger::automation("Detecção semântica desabilitada; não será tentada.");
                self::logIntent("semantica_off");
            }
        }
        
        if ($detectedIntent) {
            \App\Helpers\Logger::automation("Intent detectado: {$detectedIntent['intent']}");
            self::logIntent("intent_detectado:" . ($detectedIntent['intent'] ?? ''));
            
            // Buscar nó de destino para este intent
            $targetNodeId = $detectedIntent['target_node_id'] ?? null;
            
            if ($targetNodeId) {
                \App\Helpers\Logger::automation("Executando nó de destino: {$targetNodeId}");
                self::logIntent("target=" . $targetNodeId);
                
                // ✅ PRIMEIRO: Remover a IA IMEDIATAMENTE para evitar que ela responda
                try {
                    \App\Services\ConversationAIService::removeAIAgent($conversation['id']);
                    \App\Helpers\Logger::automation("✅ IA removida IMEDIATAMENTE para evitar resposta.");
                    self::logIntent("ia_removida");
                } catch (\Exception $e) {
                    \App\Helpers\Logger::automation("Falha ao remover IA: " . $e->getMessage());
                    self::logIntent("ia_remover_erro:" . $e->getMessage());
                }
                
                // Limpar metadata de ramificação
                $metadata['ai_branching_active'] = false;
                $metadata['ai_interaction_count'] = 0;
                \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);
                self::logIntent("ramificacao_off");

                // Mensagem de saída (se configurada no intent)
                $exitMessage = $detectedIntent['exit_message'] ?? '';
                if (!empty($exitMessage)) {
                    try {
                        \App\Services\ConversationService::sendMessage(
                            $conversation['id'],
                            $exitMessage,
                            'agent',
                            null
                        );
                        \App\Helpers\Logger::automation("📤 Mensagem de saída do intent enviada.");
                        self::logIntent("exit_msg_enviada");
                    } catch (\Exception $e) {
                        \App\Helpers\Logger::automation("Falha ao enviar mensagem de saída do intent: " . $e->getMessage());
                        self::logIntent("exit_msg_erro:" . $e->getMessage());
                    }
                }
                
                // Buscar automação e nó de destino
                $automationId = $metadata['ai_branching_automation_id'];
                // Precisamos dos nós: usar findWithNodes para garantir que venham juntos
                $automation = \App\Models\Automation::findWithNodes((int)$automationId);
                
                if ($automation) {
                    $nodes = $automation['nodes'] ?? [];
                    $targetNode = array_values(array_filter($nodes, fn($n) => $n['id'] == $targetNodeId))[0] ?? null;
                    
                    if ($targetNode) {
                        \App\Helpers\Logger::automation("Nó de destino encontrado. Executando...");
                        self::logIntent("target_found exec");
                        // Executar nó de destino
                        self::executeNode($targetNode, $conversation['id'], $nodes, null);
                        
                        \App\Helpers\Logger::automation("=== handleAIBranchingResponse FIM (true - intent executado) ===");
                        self::logIntent("fim_true");
                        return true;
                    } else {
                        \App\Helpers\Logger::automation("ERRO: Nó de destino não encontrado com ID {$targetNodeId}");
                        self::logIntent("target_notfound");
                    }
                } else {
                    \App\Helpers\Logger::automation("ERRO: Automação não encontrada com ID {$automationId}");
                    self::logIntent("automation_notfound");
                }
            } else {
                \App\Helpers\Logger::automation("AVISO: Intent detectado mas sem target_node_id configurado");
                self::logIntent("intent_sem_target");
            }
        } else {
            \App\Helpers\Logger::automation("Nenhum intent detectado na resposta da IA");
            self::logIntent("intent_none");
        }

        // Se não detectou intent, tentar fallback configurado
        $fallbackNodeId = $metadata['ai_fallback_node_id'] ?? null;
        if ($targetNodeId ?? false) {
            // já tratado acima
        } elseif ($fallbackNodeId) {
            \App\Helpers\Logger::automation("Nenhum intent detectado. Executando nó de fallback: {$fallbackNodeId}");
            self::logIntent("fallback_exec target={$fallbackNodeId}");

            // Limpar metadata de ramificação
            $metadata['ai_branching_active'] = false;
            $metadata['ai_interaction_count'] = 0;
            \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);

            try {
                // Remover a IA antes de seguir fallback
                \App\Services\ConversationAIService::removeAIAgent($conversation['id']);
                self::logIntent("ia_removida_fallback");
            } catch (\Exception $e) {
                \App\Helpers\Logger::automation("Falha ao remover IA no fallback: " . $e->getMessage());
                self::logIntent("ia_remover_erro_fallback:" . $e->getMessage());
            }

            // Executar nó de fallback dentro da mesma automação
            $automationId = $metadata['ai_branching_automation_id'] ?? null;
            if ($automationId) {
                $automation = \App\Models\Automation::findWithNodes((int)$automationId);
                $nodes = $automation['nodes'] ?? [];
                $fallbackNode = self::findNodeById($fallbackNodeId, $nodes);
                if ($fallbackNode) {
                    self::executeNode($fallbackNode, $conversation['id'], $nodes, null);
                    \App\Helpers\Logger::automation("=== handleAIBranchingResponse FIM (fallback executado) ===");
                    self::logIntent("fallback_ok");
                    return true;
                } else {
                    \App\Helpers\Logger::automation("ERRO: Nó de fallback {$fallbackNodeId} não encontrado na automação {$automationId}");
                    self::logIntent("fallback_notfound");
                }
            }
        }
        
        // Não detectou intent e não há fallback: incrementar interação e enviar feedback
        $interactionCount++;
        $metadata['ai_interaction_count'] = $interactionCount;
        \App\Models\Conversation::update($conversation['id'], ['metadata' => json_encode($metadata)]);

        if ($interactionCount >= $maxInteractions) {
            \App\Helpers\Logger::automation("Nenhum intent detectado e limite de interações atingido. Escalando.");
            self::logIntent("escalate_sem_intent interaction={$interactionCount}");
            return self::escalateFromAI($conversation['id'], $metadata);
        }

        // Mensagem de esclarecimento para o usuário
        $clarifyMessage = "Não consegui identificar sua intenção. Pode esclarecer ou ser mais específico?";
        try {
            \App\Services\ConversationService::sendMessage(
                $conversation['id'],
                $clarifyMessage,
                'agent',
                null
            );
            \App\Helpers\Logger::automation("Feedback de não entendimento enviado ao usuário. Interação {$interactionCount}/{$maxInteractions}.");
            self::logIntent("clarify_enviado interaction={$interactionCount}");
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("Falha ao enviar feedback de não entendimento: " . $e->getMessage());
            self::logIntent("clarify_erro:" . $e->getMessage());
        }

        \App\Helpers\Logger::automation("=== handleAIBranchingResponse FIM (false - continua com IA) ===");
        self::logIntent("fim_false");
        return false; // Continua com IA normal
    }

    /**
     * Detectar intent baseado na resposta da IA
     */
    private static function detectAIIntent(string $aiResponse, array $intents): ?array
    {
        \App\Helpers\Logger::automation("Detectando intent. Total de intents configurados: " . count($intents));
        
        if (empty($intents)) {
            return null;
        }
        
        $aiResponseLower = mb_strtolower($aiResponse);
        
        // Método 1: Por palavras-chave (busca por múltiplas palavras)
        $matchScores = [];
        
        foreach ($intents as $index => $intent) {
            $keywords = $intent['keywords'] ?? [];
            $intentName = $intent['intent'] ?? "intent_{$index}";
            
            if (empty($keywords)) {
                continue;
            }
            
            $matchCount = 0;
            $matchedKeywords = [];
            
            foreach ($keywords as $keyword) {
                $keywordLower = mb_strtolower(trim($keyword));
                
                if (!empty($keywordLower) && stripos($aiResponseLower, $keywordLower) !== false) {
                    $matchCount++;
                    $matchedKeywords[] = $keyword;
                }
            }
            
            if ($matchCount > 0) {
                $matchScores[] = [
                    'intent' => $intent,
                    'score' => $matchCount,
                    'matched_keywords' => $matchedKeywords
                ];
                
                \App\Helpers\Logger::automation("Intent '{$intentName}' matched {$matchCount} keyword(s): " . implode(', ', $matchedKeywords));
            }
        }
        
        // Se encontrou matches, retornar o com maior score
        if (!empty($matchScores)) {
            // Ordenar por score (descendente)
            usort($matchScores, fn($a, $b) => $b['score'] - $a['score']);
            
            $bestMatch = $matchScores[0];
            \App\Helpers\Logger::automation("Melhor match: {$bestMatch['intent']['intent']} com score {$bestMatch['score']}");
            
            return $bestMatch['intent'];
        }
        
        \App\Helpers\Logger::automation("Nenhum intent matched");
        self::logIntent("keyword_none");
        return null;
    }

    /**
     * Detectar intent de forma semântica via OpenAI (usando descrição do intent)
     */
    private static function detectAIIntentSemantic(string $aiResponse, array $intents, float $minConfidence = 0.35, ?int $conversationId = null): ?array
    {
        \App\Helpers\Logger::automation("Detectando intent (semântico). Intents: " . count($intents) . ", minConfidence: {$minConfidence}");
        self::logIntent("semantic_call intents=" . count($intents) . " minConf={$minConfidence} text='" . $aiResponse . "'");

        if (empty($intents)) {
            return null;
        }

        // Montar contexto curto da conversa (últimas mensagens)
        $context = '';
        if ($conversationId) {
            try {
                $messages = \App\Helpers\Database::query(
                    "SELECT sender_type, content FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 8",
                    [$conversationId]
                );
                $messages = array_reverse($messages); // ordem cronológica
                $parts = [];
                foreach ($messages as $msg) {
                    $role = $msg['sender_type'] ?? 'user';
                    $txt = trim($msg['content'] ?? '');
                    if ($txt === '') continue;
                    // Limitar tamanho de cada trecho para evitar tokens excessivos
                    $parts[] = strtoupper($role) . ': ' . mb_substr($txt, 0, 200);
                }
                $context = implode("\n", $parts);
            } catch (\Exception $e) {
                \App\Helpers\Logger::automation("Erro ao montar contexto para intent semântico: " . $e->getMessage());
            }
        }

        try {
            $result = \App\Services\OpenAIService::classifyIntent($aiResponse, $intents, $minConfidence, $context);
            if ($result) {
                \App\Helpers\Logger::automation("Intent semântico detectado: " . ($result['intent'] ?? '[sem nome]'));
                self::logIntent("semantic_detected:" . ($result['intent'] ?? ''));
                return $result;
            }
            \App\Helpers\Logger::automation("Intent semântico não encontrado ou confiança abaixo do mínimo");
            self::logIntent("semantic_none");
            return null;
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("Erro ao detectar intent semântico: " . $e->getMessage());
            self::logIntent("semantic_error:" . $e->getMessage());
            return null;
        }
    }

    /**
     * Escalar de IA para agente humano
     */
    private static function escalateFromAI(int $conversationId, array $metadata): bool
    {
        \App\Helpers\Logger::automation("Escalando conversa {$conversationId} de IA para humano");
        
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                \App\Helpers\Logger::automation("ERRO: Conversa não encontrada");
                return false;
            }
            
            // Marcar AIConversation como escalada
            $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
            if ($aiConversation && $aiConversation['status'] === 'active') {
                \App\Models\AIConversation::updateStatus($aiConversation['id'], 'escalated');
                \App\Helpers\Logger::automation("AIConversation marcada como 'escalated'");
            }
            
            // Tentar atribuir a agente humano
            $agentId = \App\Services\ConversationSettingsService::autoAssignConversation(
                $conversationId,
                $conversation['department_id'] ?? null,
                $conversation['funnel_id'] ?? null,
                $conversation['funnel_stage_id'] ?? null
            );
            
            if ($agentId && $agentId > 0) {
                \App\Services\ConversationService::assignToAgent($conversationId, $agentId, false);
                \App\Helpers\Logger::automation("Conversa atribuída ao agente humano ID: {$agentId}");
            } else {
                \App\Helpers\Logger::automation("Não foi possível atribuir a agente humano (nenhum disponível)");
            }
            
            // Enviar mensagem de sistema
            \App\Services\ConversationService::sendMessage(
                $conversationId,
                "🤖 → 👤 Esta conversa foi escalada para um agente humano devido ao limite de interações da IA.",
                'system',
                null,
                [],
                'system'
            );
            
            // Executar nó de fallback se configurado
            $fallbackNodeId = $metadata['ai_fallback_node_id'] ?? null;
            if ($fallbackNodeId) {
                \App\Helpers\Logger::automation("Executando nó de fallback: {$fallbackNodeId}");
                
                $automationId = $metadata['ai_branching_automation_id'];
                $automation = \App\Models\Automation::find($automationId);
                
                if ($automation) {
                    $nodes = $automation['nodes'] ?? [];
                    $fallbackNode = array_values(array_filter($nodes, fn($n) => $n['id'] == $fallbackNodeId))[0] ?? null;
                    
                    if ($fallbackNode) {
                        self::executeNode($fallbackNode, $conversationId, $nodes, null);
                        \App\Helpers\Logger::automation("Nó de fallback executado com sucesso");
                    }
                }
            }
            
            // Limpar metadata
            $metadata['ai_branching_active'] = false;
            $metadata['ai_interaction_count'] = 0;
            \App\Models\Conversation::update($conversationId, ['metadata' => json_encode($metadata)]);
            
            \App\Helpers\Logger::automation("Escalação completa. Metadata limpo.");
            return true;
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::automation("ERRO ao escalar: " . $e->getMessage());
            return false;
        }
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
                    
                case 'action_chatbot':
                    $chatbotType = $nodeData['chatbot_type'] ?? 'simple';
                    $message = $nodeData['chatbot_message'] ?? '';
                    $options = $nodeData['chatbot_options'] ?? [];
                    $timeout = $nodeData['chatbot_timeout'] ?? 300;
                    
                    $preview = self::previewVariables($message, $conversationId);
                    
                    $optionsPreview = [];
                    if ($chatbotType === 'menu' && !empty($options)) {
                        foreach ($options as $idx => $opt) {
                            $optText = is_array($opt) ? ($opt['text'] ?? '') : $opt;
                            if (!empty($optText)) {
                                $optionsPreview[] = $optText;
                            }
                        }
                    }
                    
                    $step['action_preview'] = [
                        'type' => 'chatbot',
                        'chatbot_type' => $chatbotType,
                        'message' => $preview['processed'],
                        'options' => $optionsPreview,
                        'timeout' => $timeout,
                        'wait_for_response' => true,
                        'note' => '⏸️ Aguardando resposta do usuário (execução pausada)'
                    ];
                    
                    $step['status'] = 'waiting';
                    
                    // Adicionar aviso especial
                    $testData['warnings'][] = [
                        'node_id' => $node['id'],
                        'node_type' => 'action_chatbot',
                        'message' => 'Chatbot detectado: Em execução real, aguardaria resposta do usuário antes de continuar.'
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
        
        // Chatbot pausa a execução - não continuar para próximos nós no teste
        if ($node['node_type'] === 'action_chatbot') {
            // Adicionar informação sobre os próximos nós possíveis
            if (!empty($nodeData['connections'])) {
                $nextNodesInfo = [];
                foreach ($nodeData['connections'] as $connection) {
                    $nextNode = self::findNodeById($connection['target_node_id'], $allNodes);
                    if ($nextNode) {
                        $nextNodesInfo[] = [
                            'node_id' => $nextNode['id'],
                            'node_type' => $nextNode['node_type'],
                            'node_name' => $nextNode['node_data']['name'] ?? $nextNode['node_type']
                        ];
                    }
                }
                
                if (!empty($nextNodesInfo)) {
                    $testData['warnings'][] = [
                        'node_id' => $node['id'],
                        'node_type' => 'action_chatbot',
                        'message' => 'Próximos nós conectados (serão executados após resposta): ' . 
                                     implode(', ', array_map(function($n) { return $n['node_name']; }, $nextNodesInfo))
                    ];
                }
            }
            
            // NÃO continuar para próximos nós - chatbot pausa aqui
            return;
        }
        
        // Seguir para próximos nós (exceto se for chatbot - já retornou acima)
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
            $minutes = intval(floor($seconds / 60));
            return "{$minutes} minuto(s)";
        } else {
            $hours = intval(floor($seconds / 3600));
            $minutes = intval(floor(($seconds % 3600) / 60));
            if ($minutes > 0) {
                return "{$hours} hora(s) e {$minutes} minuto(s)";
            }
            return "{$hours} hora(s)";
        }
    }
}

