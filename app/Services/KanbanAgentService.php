<?php
/**
 * Service KanbanAgentService
 * Lógica de negócio para Agentes de IA Kanban
 */

namespace App\Services;

use App\Models\AIKanbanAgent;
use App\Models\AIKanbanAgentExecution;
use App\Models\AIKanbanAgentActionLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\User;
use App\Helpers\Database;
use App\Helpers\Logger;
use App\Services\OpenAIService;
use App\Services\TagService;
use App\Services\ConversationNoteService;

class KanbanAgentService
{
    /**
     * Executar todos os agentes prontos para execução
     */
    public static function executeReadyAgents(): array
    {
        $agents = AIKanbanAgent::getReadyForExecution();
        $results = [];

        foreach ($agents as $agent) {
            try {
                $result = self::executeAgent($agent['id'], 'scheduled');
                $results[] = [
                    'agent_id' => $agent['id'],
                    'agent_name' => $agent['name'],
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? ''
                ];
            } catch (\Exception $e) {
                Logger::error("KanbanAgentService::executeReadyAgents - Erro ao executar agente {$agent['id']}: " . $e->getMessage());
                $results[] = [
                    'agent_id' => $agent['id'],
                    'agent_name' => $agent['name'],
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Executar um agente específico
     */
    public static function executeAgent(int $agentId, string $executionType = 'manual'): array
    {
        $agent = AIKanbanAgent::find($agentId);
        if (!$agent || !$agent['enabled']) {
            throw new \Exception('Agente não encontrado ou inativo');
        }

        // Criar registro de execução
        $executionId = AIKanbanAgentExecution::createExecution($agentId, $executionType);

        try {
            // Buscar conversas alvo
            $conversations = self::getTargetConversations($agent);
            
            $stats = [
                'conversations_analyzed' => 0,
                'conversations_acted_upon' => 0,
                'actions_executed' => 0,
                'errors_count' => 0,
                'results' => []
            ];

            $maxConversations = $agent['max_conversations_per_execution'] ?? 50;
            $conversations = array_slice($conversations, 0, $maxConversations);

            foreach ($conversations as $conversation) {
                try {
                    $stats['conversations_analyzed']++;
                    
                    // Analisar conversa com IA
                    $analysis = self::analyzeConversation($agent, $conversation);
                    
                    // Avaliar condições
                    $conditionsMet = self::evaluateConditions($agent['conditions'], $conversation, $analysis);
                    
                    if ($conditionsMet['met']) {
                        $stats['conversations_acted_upon']++;
                        
                        // Executar ações
                        $actionsResult = self::executeActions($agent['actions'], $conversation, $analysis, $agentId, $executionId);
                        
                        $stats['actions_executed'] += $actionsResult['executed'];
                        $stats['errors_count'] += $actionsResult['errors'];
                        
                        // Registrar log de ação
                        AIKanbanAgentActionLog::createLog([
                            'ai_kanban_agent_id' => $agentId,
                            'execution_id' => $executionId,
                            'conversation_id' => $conversation['id'],
                            'analysis_summary' => $analysis['summary'] ?? null,
                            'analysis_score' => $analysis['score'] ?? null,
                            'conditions_met' => true,
                            'conditions_details' => $conditionsMet['details'] ?? [],
                            'actions_executed' => $actionsResult['actions'] ?? [],
                            'success' => $actionsResult['errors'] === 0
                        ]);
                    } else {
                        // Registrar que condições não foram atendidas
                        AIKanbanAgentActionLog::createLog([
                            'ai_kanban_agent_id' => $agentId,
                            'execution_id' => $executionId,
                            'conversation_id' => $conversation['id'],
                            'analysis_summary' => $analysis['summary'] ?? null,
                            'analysis_score' => $analysis['score'] ?? null,
                            'conditions_met' => false,
                            'conditions_details' => $conditionsMet['details'] ?? [],
                            'actions_executed' => [],
                            'success' => true
                        ]);
                    }
                } catch (\Exception $e) {
                    $stats['errors_count']++;
                    Logger::error("KanbanAgentService::executeAgent - Erro ao processar conversa {$conversation['id']}: " . $e->getMessage());
                }
            }

            // Finalizar execução
            AIKanbanAgentExecution::completeExecution($executionId, $stats);
            
            // Atualizar próxima execução
            AIKanbanAgent::updateNextExecution($agentId);

            return [
                'success' => true,
                'message' => "Agente executado com sucesso. {$stats['conversations_analyzed']} conversas analisadas, {$stats['conversations_acted_upon']} com ações executadas.",
                'stats' => $stats
            ];

        } catch (\Exception $e) {
            AIKanbanAgentExecution::completeExecution($executionId, [], $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar conversas alvo do agente
     */
    private static function getTargetConversations(array $agent): array
    {
        $funnelIds = $agent['target_funnel_ids'] ?? null;
        $stageIds = $agent['target_stage_ids'] ?? null;

        $sql = "SELECT c.* FROM conversations c WHERE c.status = 'open'";
        $params = [];

        if ($funnelIds && is_array($funnelIds) && !empty($funnelIds)) {
            $placeholders = implode(',', array_fill(0, count($funnelIds), '?'));
            $sql .= " AND c.funnel_id IN ($placeholders)";
            $params = array_merge($params, $funnelIds);
        }

        if ($stageIds && is_array($stageIds) && !empty($stageIds)) {
            $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
            $sql .= " AND c.funnel_stage_id IN ($placeholders)";
            $params = array_merge($params, $stageIds);
        }

        $sql .= " ORDER BY c.updated_at DESC";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Analisar conversa com IA
     */
    private static function analyzeConversation(array $agent, array $conversation): array
    {
        // Buscar mensagens da conversa
        $messages = Message::where('conversation_id', '=', $conversation['id']);
        $messages = array_slice($messages, -20); // Últimas 20 mensagens

        // Buscar informações do contato
        $contact = Contact::find($conversation['contact_id']);

        // Buscar informações do funil/etapa
        $funnel = null;
        $stage = null;
        if ($conversation['funnel_id']) {
            $funnel = Funnel::find($conversation['funnel_id']);
        }
        if ($conversation['funnel_stage_id']) {
            $stage = FunnelStage::find($conversation['funnel_stage_id']);
        }

        // Montar contexto
        $context = self::buildConversationContext($conversation, $messages, $contact, $funnel, $stage);

        // Montar prompt de análise
        $prompt = self::buildAnalysisPrompt($agent['prompt'], $context);

        try {
            // Chamar OpenAI
            $response = self::callOpenAI($agent, $prompt);
            
            // Parsear resposta
            return self::parseAnalysisResponse($response);
        } catch (\Exception $e) {
            Logger::error("KanbanAgentService::analyzeConversation - Erro: " . $e->getMessage());
            return [
                'summary' => 'Erro ao analisar conversa: ' . $e->getMessage(),
                'score' => 0,
                'sentiment' => 'neutral',
                'urgency' => 'low',
                'recommendations' => []
            ];
        }
    }

    /**
     * Construir contexto da conversa
     */
    private static function buildConversationContext(array $conversation, array $messages, ?array $contact, ?array $funnel, ?array $stage): string
    {
        $context = "=== CONTEXTO DA CONVERSA ===\n\n";
        
        $context .= "ID da Conversa: {$conversation['id']}\n";
        $context .= "Status: {$conversation['status']}\n";
        $context .= "Prioridade: " . ($conversation['priority'] ?? 'normal') . "\n";
        
        if ($funnel) {
            $context .= "Funil: {$funnel['name']}\n";
        }
        if ($stage) {
            $context .= "Etapa: {$stage['name']}\n";
        }
        
        if ($contact) {
            $context .= "\n=== INFORMAÇÕES DO CONTATO ===\n";
            $context .= "Nome: " . ($contact['name'] ?? 'N/A') . "\n";
            $context .= "Telefone: " . ($contact['phone'] ?? 'N/A') . "\n";
            $context .= "Email: " . ($contact['email'] ?? 'N/A') . "\n";
        }
        
        $context .= "\n=== HISTÓRICO DE MENSAGENS ===\n";
        foreach ($messages as $msg) {
            $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : ($msg['sender_type'] === 'agent' ? 'Agente' : 'Sistema');
            $context .= "[{$sender}] {$msg['content']}\n";
        }
        
        return $context;
    }

    /**
     * Construir prompt de análise
     */
    private static function buildAnalysisPrompt(string $agentPrompt, string $context): string
    {
        return $agentPrompt . "\n\n" . $context . "\n\nAnalise esta conversa e forneça:\n" .
               "1. Um resumo da situação\n" .
               "2. Um score de confiança (0-100)\n" .
               "3. Sentimento (positive, neutral, negative)\n" .
               "4. Urgência (low, medium, high)\n" .
               "5. Recomendações de ações\n\n" .
               "Responda em formato JSON: {\"summary\": \"...\", \"score\": 85, \"sentiment\": \"positive\", \"urgency\": \"medium\", \"recommendations\": [\"...\"]}";
    }

    /**
     * Chamar OpenAI API
     */
    private static function callOpenAI(array $agent, string $prompt): string
    {
        $apiKey = \App\Models\Setting::get('openai_api_key');
        if (empty($apiKey)) {
            throw new \Exception('API Key da OpenAI não configurada');
        }

        $model = $agent['model'] ?? 'gpt-4';
        $temperature = (float)($agent['temperature'] ?? 0.7);
        $maxTokens = (int)($agent['max_tokens'] ?? 2000);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Você é um assistente especializado em análise de conversas de atendimento e vendas.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];

        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Erro na API OpenAI: HTTP $httpCode - $response");
        }

        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Parsear resposta da análise
     */
    private static function parseAnalysisResponse(string $response): array
    {
        // Tentar extrair JSON da resposta
        if (preg_match('/\{[^}]+\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return [
                    'summary' => $json['summary'] ?? 'Análise realizada',
                    'score' => (int)($json['score'] ?? 50),
                    'sentiment' => $json['sentiment'] ?? 'neutral',
                    'urgency' => $json['urgency'] ?? 'low',
                    'recommendations' => $json['recommendations'] ?? []
                ];
            }
        }

        // Fallback: retornar resposta como summary
        return [
            'summary' => $response,
            'score' => 50,
            'sentiment' => 'neutral',
            'urgency' => 'low',
            'recommendations' => []
        ];
    }

    /**
     * Avaliar condições (público para testes)
     */
    public static function evaluateConditions(array $conditions, array $conversation, array $analysis): array
    {
        if (empty($conditions) || empty($conditions['conditions'])) {
            return ['met' => true, 'details' => []];
        }

        $operator = $conditions['operator'] ?? 'AND';
        $conditionList = $conditions['conditions'] ?? [];

        $results = [];
        foreach ($conditionList as $condition) {
            $result = self::evaluateSingleCondition($condition, $conversation, $analysis);
            $results[] = [
                'condition' => $condition,
                'result' => $result
            ];
        }

        // Aplicar operador lógico
        $met = self::applyLogicOperator($results, $operator);

        return [
            'met' => $met,
            'details' => $results
        ];
    }

    /**
     * Avaliar condição única
     */
    private static function evaluateSingleCondition(array $condition, array $conversation, array $analysis): bool
    {
        $type = $condition['type'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;

        switch ($type) {
            case 'conversation_status':
                return self::compare($conversation['status'] ?? '', $operator, $value);
            
            case 'conversation_priority':
                return self::compare($conversation['priority'] ?? 'normal', $operator, $value);
            
            case 'last_message_hours':
                $lastMessage = Message::whereFirst('conversation_id', '=', $conversation['id'], 'ORDER BY created_at DESC');
                if (!$lastMessage) {
                    return false;
                }
                $hours = (time() - strtotime($lastMessage['created_at'])) / 3600;
                return self::compare($hours, $operator, $value);
            
            case 'last_message_from':
                $lastMessage = Message::whereFirst('conversation_id', '=', $conversation['id'], 'ORDER BY created_at DESC');
                if (!$lastMessage) {
                    return false;
                }
                return self::compare($lastMessage['sender_type'] ?? '', $operator, $value);
            
            case 'client_no_response_minutes':
                // Buscar última mensagem do contato
                $lastClientMessage = Message::whereFirst(
                    'conversation_id', 
                    '=', 
                    $conversation['id'], 
                    'AND sender_type = \'contact\' ORDER BY created_at DESC'
                );
                if (!$lastClientMessage) {
                    return false;
                }
                $minutes = (time() - strtotime($lastClientMessage['created_at'])) / 60;
                return self::compare($minutes, $operator, $value);
            
            case 'agent_no_response_minutes':
                // Buscar última mensagem de agente
                $lastAgentMessage = Message::whereFirst(
                    'conversation_id', 
                    '=', 
                    $conversation['id'], 
                    'AND sender_type = \'agent\' ORDER BY created_at DESC'
                );
                if (!$lastAgentMessage) {
                    return false;
                }
                $minutes = (time() - strtotime($lastAgentMessage['created_at'])) / 60;
                return self::compare($minutes, $operator, $value);
            
            case 'stage_duration_hours':
                if (!$conversation['moved_at']) {
                    return false;
                }
                $hours = (time() - strtotime($conversation['moved_at'])) / 3600;
                return self::compare($hours, $operator, $value);
            
            case 'ai_analysis_score':
                return self::compare($analysis['score'] ?? 0, $operator, $value);
            
            case 'ai_sentiment':
                return self::compare($analysis['sentiment'] ?? 'neutral', $operator, $value);
            
            case 'ai_urgency':
                return self::compare($analysis['urgency'] ?? 'low', $operator, $value);
            
            default:
                return false;
        }
    }

    /**
     * Comparar valores
     */
    private static function compare($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case 'equals':
                return $actual == $expected;
            case 'not_equals':
                return $actual != $expected;
            case 'greater_than':
                return (float)$actual > (float)$expected;
            case 'less_than':
                return (float)$actual < (float)$expected;
            case 'greater_or_equal':
                return (float)$actual >= (float)$expected;
            case 'less_or_equal':
                return (float)$actual <= (float)$expected;
            case 'includes':
                return is_array($expected) && in_array($actual, $expected);
            case 'not_includes':
                return is_array($expected) && !in_array($actual, $expected);
            default:
                return false;
        }
    }

    /**
     * Aplicar operador lógico
     */
    private static function applyLogicOperator(array $results, string $operator): bool
    {
        $values = array_map(function($r) { return $r['result']; }, $results);
        
        switch (strtoupper($operator)) {
            case 'AND':
                return !in_array(false, $values, true);
            case 'OR':
                return in_array(true, $values, true);
            case 'NOT':
                return !in_array(true, $values, true);
            default:
                return !in_array(false, $values, true); // AND por padrão
        }
    }

    /**
     * Executar ações
     */
    private static function executeActions(array $actions, array $conversation, array $analysis, int $agentId, int $executionId): array
    {
        $executed = 0;
        $errors = 0;
        $actionResults = [];

        foreach ($actions as $action) {
            if (!($action['enabled'] ?? true)) {
                continue;
            }

            try {
                $result = self::executeSingleAction($action, $conversation, $analysis, $agentId, $executionId);
                $actionResults[] = [
                    'type' => $action['type'] ?? '',
                    'success' => true,
                    'result' => $result
                ];
                $executed++;
            } catch (\Exception $e) {
                $actionResults[] = [
                    'type' => $action['type'] ?? '',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $errors++;
                Logger::error("KanbanAgentService::executeActions - Erro ao executar ação {$action['type']}: " . $e->getMessage());
            }
        }

        return [
            'executed' => $executed,
            'errors' => $errors,
            'actions' => $actionResults
        ];
    }

    /**
     * Executar ação única
     */
    private static function executeSingleAction(array $action, array $conversation, array $analysis, int $agentId, int $executionId): array
    {
        $type = $action['type'] ?? '';
        $config = $action['config'] ?? [];

        switch ($type) {
            case 'analyze_conversation':
                // Já foi analisado, apenas retornar
                return ['message' => 'Conversa já analisada', 'analysis' => $analysis];
            
            case 'send_followup_message':
                return self::actionSendFollowupMessage($conversation, $analysis, $config);
            
            case 'move_to_stage':
                return self::actionMoveToStage($conversation, $config);
            
            case 'move_to_next_stage':
                return self::actionMoveToNextStage($conversation);
            
            case 'assign_to_agent':
                return self::actionAssignToAgent($conversation, $config);
            
            case 'assign_ai_agent':
                return self::actionAssignAIAgent($conversation, $config);
            
            case 'add_tag':
                return self::actionAddTag($conversation, $config);
            
            case 'create_summary':
                return self::actionCreateSummary($conversation, $analysis, $config);
            
            case 'create_note':
                return self::actionCreateNote($conversation, $analysis, $config);
            
            default:
                throw new \Exception("Tipo de ação desconhecido: $type");
        }
    }

    /**
     * Ação: Enviar mensagem de followup
     */
    private static function actionSendFollowupMessage(array $conversation, array $analysis, array $config): array
    {
        $useAIGenerated = $config['use_ai_generated'] ?? false;
        $template = $config['template'] ?? '';
        
        if ($useAIGenerated) {
            // Gerar mensagem com IA
            $message = self::generateFollowupMessage($conversation, $analysis);
        } else {
            // Usar template
            $message = self::processTemplate($template, $conversation, $analysis);
        }

        if (empty(trim($message))) {
            throw new \Exception('Mensagem de followup não pode estar vazia');
        }

        // Enviar mensagem usando ConversationService
        $messageId = \App\Services\ConversationService::sendMessage(
            $conversation['id'],
            $message,
            'agent',
            null, // Sistema
            [],
            'text',
            null,
            null // Não é agente de IA tradicional
        );

        return ['message' => 'Mensagem de followup enviada', 'message_id' => $messageId, 'content' => $message];
    }

    /**
     * Ação: Mover para etapa específica
     */
    private static function actionMoveToStage(array $conversation, array $config): array
    {
        $stageId = $config['stage_id'] ?? null;
        if (!$stageId) {
            throw new \Exception('ID da etapa não especificado');
        }

        Conversation::update($conversation['id'], [
            'funnel_stage_id' => $stageId,
            'moved_at' => date('Y-m-d H:i:s')
        ]);

        return ['message' => "Conversa movida para etapa $stageId"];
    }

    /**
     * Ação: Mover para próxima etapa
     */
    private static function actionMoveToNextStage(array $conversation): array
    {
        if (!$conversation['funnel_stage_id']) {
            throw new \Exception('Conversa não está em nenhuma etapa');
        }

        $currentStage = FunnelStage::find($conversation['funnel_stage_id']);
        if (!$currentStage) {
            throw new \Exception('Etapa atual não encontrada');
        }

        $nextStage = FunnelStage::whereFirst('funnel_id', '=', $currentStage['funnel_id'], "AND stage_order > {$currentStage['stage_order']} ORDER BY stage_order ASC");
        if (!$nextStage) {
            throw new \Exception('Não há próxima etapa');
        }

        Conversation::update($conversation['id'], [
            'funnel_stage_id' => $nextStage['id'],
            'moved_at' => date('Y-m-d H:i:s')
        ]);

        return ['message' => "Conversa movida para próxima etapa: {$nextStage['name']}"];
    }

    /**
     * Ação: Atribuir a agente
     */
    private static function actionAssignToAgent(array $conversation, array $config): array
    {
        $method = $config['method'] ?? 'round_robin';
        $agentId = null;

        if ($method === 'round_robin') {
            // Implementar round-robin
            $agentId = self::getRoundRobinAgent($config['department_id'] ?? null);
        }

        if (!$agentId) {
            throw new \Exception('Nenhum agente disponível');
        }

        Conversation::update($conversation['id'], [
            'agent_id' => $agentId,
            'assigned_at' => date('Y-m-d H:i:s')
        ]);

        return ['message' => "Conversa atribuída ao agente $agentId"];
    }

    /**
     * Ação: Atribuir Agente de IA
     */
    private static function actionAssignAIAgent(array $conversation, array $config): array
    {
        $aiAgentId = $config['ai_agent_id'] ?? null;
        
        if (!$aiAgentId) {
            throw new \Exception('Nenhum agente de IA especificado');
        }
        
        // Verificar se o agente de IA existe e está ativo
        $aiAgent = \App\Models\AIAgent::find($aiAgentId);
        if (!$aiAgent || !$aiAgent['enabled']) {
            throw new \Exception('Agente de IA não encontrado ou inativo');
        }
        
        // Atribuir o agente de IA à conversa
        Conversation::update($conversation['id'], [
            'ai_agent_id' => $aiAgentId
        ]);
        
        // Adicionar mensagem do sistema informando a atribuição
        \App\Services\ConversationService::sendMessage(
            $conversation['id'],
            "🤖 Agente de IA '{$aiAgent['name']}' foi adicionado à conversa.",
            'system',
            null,
            []
        );
        
        Logger::info("KanbanAgentService::actionAssignAIAgent - Agente de IA {$aiAgent['name']} (ID: {$aiAgentId}) atribuído à conversa {$conversation['id']}");
        
        return ['message' => "Agente de IA '{$aiAgent['name']}' atribuído à conversa"];
    }

    /**
     * Ação: Adicionar tag
     */
    private static function actionAddTag(array $conversation, array $config): array
    {
        $tags = $config['tags'] ?? [];
        if (empty($tags)) {
            throw new \Exception('Nenhuma tag especificada');
        }

        $addedTags = [];
        $errors = [];

        foreach ($tags as $tag) {
            try {
                // Se for ID numérico, usar diretamente
                if (is_numeric($tag)) {
                    \App\Services\TagService::addToConversation($conversation['id'], (int)$tag);
                    $tagObj = \App\Models\Tag::find((int)$tag);
                    $addedTags[] = $tagObj ? $tagObj['name'] : "Tag #{$tag}";
                } else {
                    // Se for nome, buscar tag por nome
                    $tagObj = \App\Models\Tag::whereFirst('name', '=', $tag);
                    if ($tagObj) {
                        \App\Services\TagService::addToConversation($conversation['id'], $tagObj['id']);
                        $addedTags[] = $tagObj['name'];
                    } else {
                        $errors[] = "Tag '{$tag}' não encontrada";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Erro ao adicionar tag '{$tag}': " . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            Logger::warning("KanbanAgentService::actionAddTag - Erros: " . implode(', ', $errors));
        }

        return [
            'message' => !empty($addedTags) ? 'Tags adicionadas: ' . implode(', ', $addedTags) : 'Nenhuma tag adicionada',
            'added_tags' => $addedTags,
            'errors' => $errors
        ];
    }

    /**
     * Ação: Criar resumo
     */
    private static function actionCreateSummary(array $conversation, array $analysis, array $config): array
    {
        $summaryType = $config['summary_type'] ?? 'internal';
        $summary = $analysis['summary'] ?? 'Resumo não disponível';
        $includeRecommendations = $config['include_recommendations'] ?? false;

        $noteContent = "📊 **Resumo da Análise**\n\n{$summary}";
        
        if ($includeRecommendations && !empty($analysis['recommendations'])) {
            $noteContent .= "\n\n**Recomendações:**\n";
            foreach ($analysis['recommendations'] as $rec) {
                $noteContent .= "- {$rec}\n";
            }
        }

        // Criar nota usando ConversationNoteService
        // Usar user_id = 0 para sistema (ou buscar um usuário admin)
        $systemUserId = self::getSystemUserId();
        
        try {
            $note = \App\Services\ConversationNoteService::create(
                $conversation['id'],
                $systemUserId,
                $noteContent,
                $summaryType === 'internal' // isPrivate
            );
            
            return ['message' => 'Resumo criado', 'note_id' => $note['id'] ?? null, 'summary' => $summary];
        } catch (\Exception $e) {
            Logger::error("KanbanAgentService::actionCreateSummary - Erro: " . $e->getMessage());
            throw new \Exception('Erro ao criar resumo: ' . $e->getMessage());
        }
    }

    /**
     * Ação: Criar nota
     */
    private static function actionCreateNote(array $conversation, array $analysis, array $config): array
    {
        $note = $config['note'] ?? '';
        $isInternal = $config['is_internal'] ?? true;

        if (empty(trim($note))) {
            throw new \Exception('Conteúdo da nota não pode estar vazio');
        }

        // Processar template da nota
        $noteContent = self::processTemplate($note, $conversation, $analysis);

        // Criar nota usando ConversationNoteService
        $systemUserId = self::getSystemUserId();
        
        try {
            $createdNote = \App\Services\ConversationNoteService::create(
                $conversation['id'],
                $systemUserId,
                $noteContent,
                $isInternal
            );
            
            return ['message' => 'Nota criada', 'note_id' => $createdNote['id'] ?? null, 'note' => $noteContent];
        } catch (\Exception $e) {
            Logger::error("KanbanAgentService::actionCreateNote - Erro: " . $e->getMessage());
            throw new \Exception('Erro ao criar nota: ' . $e->getMessage());
        }
    }

    /**
     * Gerar mensagem de followup com IA
     */
    private static function generateFollowupMessage(array $conversation, array $analysis): string
    {
        // Implementar geração de mensagem com IA
        // Por enquanto, retornar mensagem genérica
        return "Olá! Vi que você estava interessado em nossos produtos. Posso ajudar com alguma dúvida?";
    }

    /**
     * Processar template
     */
    private static function processTemplate(string $template, array $conversation, array $analysis): string
    {
        $contact = Contact::find($conversation['contact_id']);
        
        $replacements = [
            '{contact_name}' => $contact['name'] ?? 'Cliente',
            '{analysis_summary}' => $analysis['summary'] ?? '',
            '{conversation_id}' => $conversation['id']
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Obter agente round-robin
     */
    private static function getRoundRobinAgent(?int $departmentId = null): ?int
    {
        try {
            $sql = "SELECT u.id, u.name, 
                           (SELECT COUNT(*) FROM conversations c WHERE c.agent_id = u.id AND c.status = 'open') as active_conversations
                    FROM users u
                    WHERE u.status = 'active' AND u.role_id > 0";
            
            $params = [];
            
            if ($departmentId) {
                $sql .= " AND u.department_id = ?";
                $params[] = $departmentId;
            }
            
            $sql .= " ORDER BY active_conversations ASC, u.id ASC LIMIT 1";
            
            $agent = Database::fetch($sql, $params);
            
            return $agent ? (int)$agent['id'] : null;
        } catch (\Exception $e) {
            Logger::error("KanbanAgentService::getRoundRobinAgent - Erro: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter ID do usuário do sistema (para criar notas/atividades)
     */
    private static function getSystemUserId(): int
    {
        // Tentar buscar um usuário admin/super admin
        $admin = \App\Models\User::whereFirst('role_id', '=', 1); // Assumindo que role_id 1 é super admin
        if ($admin) {
            return (int)$admin['id'];
        }
        
        // Se não encontrar, buscar qualquer usuário ativo
        $user = \App\Models\User::whereFirst('status', '=', 'active');
        if ($user) {
            return (int)$user['id'];
        }
        
        // Fallback: retornar 0 (sistema)
        return 0;
    }
}

