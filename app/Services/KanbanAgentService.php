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
     * Helper para log com arquivo correto
     */
    private static function logInfo(string $message): void
    {
        Logger::info($message, 'kanban_agents.log');
    }
    
    private static function logError(string $message): void
    {
        Logger::error($message, 'kanban_agents.log');
    }
    
    private static function logWarning(string $message): void
    {
        Logger::warning($message, 'kanban_agents.log');
    }
    
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
                self::logError("KanbanAgentService::executeReadyAgents - Erro ao executar agente {$agent['id']}: " . $e->getMessage());
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
        self::logInfo("KanbanAgentService::executeAgent - Iniciando execução do agente $agentId (tipo: $executionType)");
        
        $agent = AIKanbanAgent::find($agentId);
        if (!$agent || !$agent['enabled']) {
            self::logWarning("KanbanAgentService::executeAgent - Agente $agentId não encontrado ou inativo");
            throw new \Exception('Agente não encontrado ou inativo');
        }

        self::logInfo("KanbanAgentService::executeAgent - Agente '{$agent['name']}' (ID: $agentId) carregado com sucesso");

        // Criar registro de execução
        $executionId = AIKanbanAgentExecution::createExecution($agentId, $executionType);
        self::logInfo("KanbanAgentService::executeAgent - Registro de execução criado (ID: $executionId)");

        try {
            // Buscar conversas alvo
            self::logInfo("KanbanAgentService::executeAgent - Buscando conversas alvo (funis: " . json_encode($agent['target_funnel_ids']) . ", etapas: " . json_encode($agent['target_stage_ids']) . ")");
            $conversations = self::getTargetConversations($agent);
            self::logInfo("KanbanAgentService::executeAgent - Total de conversas encontradas: " . count($conversations));
            
            $stats = [
                'conversations_found' => count($conversations),
                'conversations_filtered' => 0,
                'conversations_analyzed' => 0,
                'conversations_acted_upon' => 0,
                'actions_executed' => 0,
                'errors_count' => 0,
                'results' => []
            ];

            // PASSO 1: Separar condições (com e sem IA)
            self::logInfo("KanbanAgentService::executeAgent - Separando condições (com e sem IA)");
            $separatedConditions = self::separateConditions($agent['conditions']);
            $hasConditionsWithoutAI = !empty($separatedConditions['without_ai']['conditions']);
            $hasConditionsWithAI = !empty($separatedConditions['with_ai']['conditions']);
            
            self::logInfo("KanbanAgentService::executeAgent - Condições sem IA: " . count($separatedConditions['without_ai']['conditions']));
            self::logInfo("KanbanAgentService::executeAgent - Condições com IA: " . count($separatedConditions['with_ai']['conditions']));

            // PASSO 2: Filtrar conversas com condições simples (SEM IA)
            $filteredConversations = [];
            
            if ($hasConditionsWithoutAI) {
                self::logInfo("KanbanAgentService::executeAgent - Filtrando conversas com condições básicas (sem IA)...");
                
                foreach ($conversations as $conversation) {
                    $basicConditionsMet = self::evaluateConditionsWithoutAI($separatedConditions['without_ai'], $conversation);
                    
                    if ($basicConditionsMet['met']) {
                        $filteredConversations[] = $conversation;
                    }
                }
                
                self::logInfo("KanbanAgentService::executeAgent - Conversas que passaram no filtro básico: " . count($filteredConversations) . " de " . count($conversations));
                $stats['conversations_filtered'] = count($filteredConversations);
            } else {
                // Se não há condições básicas, todas passam
                $filteredConversations = $conversations;
                $stats['conversations_filtered'] = count($conversations);
                self::logInfo("KanbanAgentService::executeAgent - Sem condições básicas, todas as conversas serão analisadas");
            }

            // PASSO 3: Limitar conversas para análise com IA
            $maxConversations = $agent['max_conversations_per_execution'] ?? 50;
            $totalBeforeLimit = count($filteredConversations);
            $conversationsToAnalyze = array_slice($filteredConversations, 0, $maxConversations);
            
            if ($totalBeforeLimit > $maxConversations) {
                self::logInfo("KanbanAgentService::executeAgent - Limitando análise a $maxConversations conversas (total filtradas: $totalBeforeLimit)");
            }

            self::logInfo("KanbanAgentService::executeAgent - Iniciando análise de " . count($conversationsToAnalyze) . " conversas com IA");

            // PASSO 4: Aplicar filtro de cooldown
            $forceExecution = $trigger === 'manual_force'; // Permitir forçar execução
            $conversationsAfterCooldown = [];
            $skippedByCooldown = 0;
            
            foreach ($conversationsToAnalyze as $conversation) {
                [$shouldSkip, $reason] = self::shouldSkipConversation($agent, $conversation, $forceExecution);
                
                if ($shouldSkip) {
                    $skippedByCooldown++;
                    self::logInfo("Conversa {$conversation['id']}: PULADA - motivo: $reason");
                    continue;
                }
                
                $conversationsAfterCooldown[] = $conversation;
            }
            
            self::logInfo("KanbanAgentService::executeAgent - Conversas após filtro de cooldown: " . count($conversationsAfterCooldown) . " de " . count($conversationsToAnalyze) . " (puladas: $skippedByCooldown)");

            // PASSO 5: Analisar conversas que passaram pelo cooldown com IA
            foreach ($conversationsAfterCooldown as $index => $conversation) {
                try {
                    $stats['conversations_analyzed']++;
                    self::logInfo("KanbanAgentService::executeAgent - ===== Conversa " . ($index + 1) . "/" . count($conversationsToAnalyze) . " =====");
                    self::logInfo("KanbanAgentService::executeAgent - Analisando conversa {$conversation['id']} (total analisadas: {$stats['conversations_analyzed']})");
                    
                    // Analisar conversa com IA
                    self::logInfo("KanbanAgentService::executeAgent - Chamando OpenAI para análise da conversa {$conversation['id']}");
                    $analysis = self::analyzeConversation($agent, $conversation);
                    self::logInfo("KanbanAgentService::executeAgent - Análise concluída para conversa {$conversation['id']}: Score={$analysis['score']}, Sentiment={$analysis['sentiment']}, Urgency={$analysis['urgency']}");
                    
                    // PASSO 5: Avaliar condições de IA
                    $aiConditionsMet = ['met' => true, 'details' => []];
                    
                    if ($hasConditionsWithAI) {
                        self::logInfo("KanbanAgentService::executeAgent - Avaliando condições de IA para conversa {$conversation['id']}");
                        $aiConditionsMet = self::evaluateConditions($separatedConditions['with_ai'], $conversation, $analysis);
                        self::logInfo("KanbanAgentService::executeAgent - Condições de IA " . ($aiConditionsMet['met'] ? 'ATENDIDAS' : 'NÃO ATENDIDAS') . " para conversa {$conversation['id']}");
                    }
                    
                    // Todas as condições foram atendidas?
                    $allConditionsMet = $aiConditionsMet['met'];
                    
                    // PASSO 6: Executar ações se todas as condições foram atendidas
                    if ($allConditionsMet) {
                        $stats['conversations_acted_upon']++;
                        self::logInfo("KanbanAgentService::executeAgent - Executando ações para conversa {$conversation['id']} (total com ações: {$stats['conversations_acted_upon']})");
                        
                        // Executar ações
                        $actionsResult = self::executeActions($agent['actions'], $conversation, $analysis, $agentId, $executionId);
                        
                        $stats['actions_executed'] += $actionsResult['executed'];
                        $stats['errors_count'] += $actionsResult['errors'];
                        
                        self::logInfo("KanbanAgentService::executeAgent - Ações executadas para conversa {$conversation['id']}: {$actionsResult['executed']} sucesso(s), {$actionsResult['errors']} erro(s)");
                        
                        // Criar snapshot do estado atual da conversa
                        $conversationSnapshot = self::createConversationSnapshot($conversation);
                        
                        // Registrar log de ação com snapshot
                        try {
                            $logData = [
                                'ai_kanban_agent_id' => $agentId,
                                'execution_id' => $executionId,
                                'conversation_id' => $conversation['id'],
                                'analysis_summary' => $analysis['summary'] ?? null,
                                'analysis_score' => $analysis['score'] ?? null,
                                'conditions_met' => true,
                                'conditions_details' => array_merge(
                                    $separatedConditions['without_ai']['conditions'] ?? [],
                                    $aiConditionsMet['details'] ?? []
                                ),
                                'actions_executed' => $actionsResult['actions'] ?? [],
                                'success' => $actionsResult['errors'] === 0,
                                'conversation_snapshot' => $conversationSnapshot
                            ];
                            
                            $logId = AIKanbanAgentActionLog::createLog($logData);
                            self::logInfo("KanbanAgentService::executeAgent - Log registrado com sucesso no banco (ID: $logId)");
                        } catch (\Throwable $e) {
                            self::logError("KanbanAgentService::executeAgent - ERRO ao registrar log no banco: " . $e->getMessage());
                            // Não interromper execução por erro de log
                        }
                    } else {
                        // Condições NÃO atendidas
                        self::logInfo("KanbanAgentService::executeAgent - Condições NÃO atendidas para conversa {$conversation['id']} - nenhuma ação será executada");
                        
                        // Criar snapshot do estado atual da conversa
                        $conversationSnapshot = self::createConversationSnapshot($conversation);
                        
                        // Registrar log mesmo sem ações executadas (para cooldown funcionar)
                        try {
                            $logData = [
                                'ai_kanban_agent_id' => $agentId,
                                'execution_id' => $executionId,
                                'conversation_id' => $conversation['id'],
                                'analysis_summary' => $analysis['summary'] ?? null,
                                'analysis_score' => $analysis['score'] ?? null,
                                'conditions_met' => false,
                                'conditions_details' => array_merge(
                                    $separatedConditions['without_ai']['conditions'] ?? [],
                                    $aiConditionsMet['details'] ?? []
                                ),
                                'actions_executed' => [],
                                'success' => true,
                                'conversation_snapshot' => $conversationSnapshot
                            ];
                            
                            $logId = AIKanbanAgentActionLog::createLog($logData);
                            self::logInfo("KanbanAgentService::executeAgent - Log registrado (ID: $logId)");
                        } catch (\Throwable $e) {
                            self::logError("KanbanAgentService::executeAgent - ERRO ao registrar log: " . $e->getMessage());
                            // Não interromper execução por erro de log
                        }
                    }
                } catch (\Throwable $e) {
                    // Captura TODOS os erros (Exception, Error, ParseError, etc)
                    $stats['errors_count']++;
                    self::logError("KanbanAgentService::executeAgent - ERRO ao processar conversa {$conversation['id']}");
                    self::logError("KanbanAgentService::executeAgent - Tipo: " . get_class($e));
                    self::logError("KanbanAgentService::executeAgent - Mensagem: " . $e->getMessage());
                    self::logError("KanbanAgentService::executeAgent - Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")");
                    self::logError("KanbanAgentService::executeAgent - Stack trace: " . $e->getTraceAsString());
                }
                
                self::logInfo("KanbanAgentService::executeAgent - Fim do processamento da conversa {$conversation['id']}");
            }
            
            self::logInfo("KanbanAgentService::executeAgent - Loop de conversas finalizado. Total processadas: " . count($conversations));

            // Finalizar execução
            self::logInfo("KanbanAgentService::executeAgent - Finalizando execução $executionId: {$stats['conversations_analyzed']} analisadas, {$stats['conversations_acted_upon']} com ações, {$stats['actions_executed']} ações executadas, {$stats['errors_count']} erros");
            
            try {
                AIKanbanAgentExecution::completeExecution($executionId, $stats);
                self::logInfo("KanbanAgentService::executeAgent - Execução completada com sucesso no banco");
            } catch (\Throwable $e) {
                self::logError("KanbanAgentService::executeAgent - Erro ao completar execução no banco: " . $e->getMessage());
                throw $e;
            }
            
            // Atualizar próxima execução
            try {
                AIKanbanAgent::updateNextExecution($agentId);
                self::logInfo("KanbanAgentService::executeAgent - Próxima execução agendada para o agente $agentId");
            } catch (\Throwable $e) {
                self::logError("KanbanAgentService::executeAgent - Erro ao agendar próxima execução: " . $e->getMessage());
                throw $e;
            }

            $message = "Agente executado com sucesso. {$stats['conversations_found']} conversas encontradas, {$stats['conversations_filtered']} passaram no filtro básico, {$stats['conversations_analyzed']} analisadas com IA, {$stats['conversations_acted_upon']} com ações executadas.";
            self::logInfo("KanbanAgentService::executeAgent - $message");
            self::logInfo("KanbanAgentService::executeAgent - ===== EXECUÇÃO FINALIZADA COM SUCESSO =====");

            return [
                'success' => true,
                'message' => $message,
                'stats' => $stats
            ];

        } catch (\Throwable $e) {
            // Captura TODOS os erros possíveis
            self::logError("KanbanAgentService::executeAgent - ERRO FATAL na execução do agente $agentId");
            self::logError("KanbanAgentService::executeAgent - Tipo: " . get_class($e));
            self::logError("KanbanAgentService::executeAgent - Mensagem: " . $e->getMessage());
            self::logError("KanbanAgentService::executeAgent - Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")");
            self::logError("KanbanAgentService::executeAgent - Stack trace: " . $e->getTraceAsString());
            
            try {
                AIKanbanAgentExecution::completeExecution($executionId, [], $e->getMessage());
            } catch (\Throwable $completionError) {
                self::logError("KanbanAgentService::executeAgent - Erro ao completar execução: " . $completionError->getMessage());
            }
            
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
            Logger::info("KanbanAgentService::getTargetConversations - Filtrando por funis: " . implode(', ', $funnelIds));
        } else {
            Logger::info("KanbanAgentService::getTargetConversations - Buscando em TODOS os funis");
        }

        if ($stageIds && is_array($stageIds) && !empty($stageIds)) {
            $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
            $sql .= " AND c.funnel_stage_id IN ($placeholders)";
            $params = array_merge($params, $stageIds);
            Logger::info("KanbanAgentService::getTargetConversations - Filtrando por etapas: " . implode(', ', $stageIds));
        } else {
            Logger::info("KanbanAgentService::getTargetConversations - Buscando em TODAS as etapas");
        }

        $sql .= " ORDER BY c.updated_at DESC";
        
        Logger::info("KanbanAgentService::getTargetConversations - SQL: $sql");
        Logger::info("KanbanAgentService::getTargetConversations - Params: " . json_encode($params));

        $conversations = Database::fetchAll($sql, $params);
        Logger::info("KanbanAgentService::getTargetConversations - Retornando " . count($conversations) . " conversas");
        
        return $conversations;
    }

    /**
     * Analisar conversa com IA
     */
    private static function analyzeConversation(array $agent, array $conversation): array
    {
        try {
            self::logInfo("KanbanAgentService::analyzeConversation - Iniciando análise da conversa {$conversation['id']}");
            
            // Buscar mensagens da conversa
            $messages = Message::where('conversation_id', '=', $conversation['id']);
            self::logInfo("KanbanAgentService::analyzeConversation - Total de mensagens encontradas: " . count($messages));
            $messages = array_slice($messages, -20); // Últimas 20 mensagens
            self::logInfo("KanbanAgentService::analyzeConversation - Usando " . count($messages) . " mensagens para análise");

            // Buscar informações do contato
            $contact = Contact::find($conversation['contact_id']);
            self::logInfo("KanbanAgentService::analyzeConversation - Contato: " . ($contact ? $contact['name'] : 'N/A'));

            // Buscar informações do funil/etapa
            $funnel = null;
            $stage = null;
            if ($conversation['funnel_id']) {
                $funnel = Funnel::find($conversation['funnel_id']);
                self::logInfo("KanbanAgentService::analyzeConversation - Funil: " . ($funnel ? $funnel['name'] : 'N/A'));
            }
            if ($conversation['funnel_stage_id']) {
                $stage = FunnelStage::find($conversation['funnel_stage_id']);
                self::logInfo("KanbanAgentService::analyzeConversation - Etapa: " . ($stage ? $stage['name'] : 'N/A'));
            }

            // Montar contexto
            self::logInfo("KanbanAgentService::analyzeConversation - Montando contexto da conversa");
            $context = self::buildConversationContext($conversation, $messages, $contact, $funnel, $stage);
            self::logInfo("KanbanAgentService::analyzeConversation - Contexto montado (tamanho: " . strlen($context) . " caracteres)");

            // Montar prompt de análise
            $prompt = self::buildAnalysisPrompt($agent['prompt'], $context);
            self::logInfo("KanbanAgentService::analyzeConversation - Prompt montado (tamanho: " . strlen($prompt) . " caracteres)");

            // Chamar OpenAI
            self::logInfo("KanbanAgentService::analyzeConversation - Chamando OpenAI API...");
            $response = self::callOpenAI($agent, $prompt);
            self::logInfo("KanbanAgentService::analyzeConversation - Resposta recebida da OpenAI (tamanho: " . strlen($response) . " caracteres)");
            
            // Parsear resposta
            $analysis = self::parseAnalysisResponse($response);
            self::logInfo("KanbanAgentService::analyzeConversation - Análise parseada com sucesso");
            
            return $analysis;
        } catch (\Exception $e) {
            self::logError("KanbanAgentService::analyzeConversation - Erro: " . $e->getMessage());
            self::logError("KanbanAgentService::analyzeConversation - Stack trace: " . $e->getTraceAsString());
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
     * Separar condições em dois grupos: com e sem necessidade de IA
     */
    private static function separateConditions(array $conditions): array
    {
        $conditionsWithoutAI = [];
        $conditionsWithAI = [];
        
        $conditionList = $conditions['conditions'] ?? [];
        
        foreach ($conditionList as $condition) {
            $type = $condition['type'] ?? '';
            
            // Condições que NÃO precisam de IA
            if (in_array($type, ['stage_duration_hours', 'has_tag', 'no_tag', 'assigned_to', 'unassigned', 'has_messages'])) {
                $conditionsWithoutAI[] = $condition;
            } else {
                // Condições que PRECISAM de IA (sentiment, score, urgency)
                $conditionsWithAI[] = $condition;
            }
        }
        
        return [
            'without_ai' => [
                'operator' => $conditions['operator'] ?? 'AND',
                'conditions' => $conditionsWithoutAI
            ],
            'with_ai' => [
                'operator' => $conditions['operator'] ?? 'AND',
                'conditions' => $conditionsWithAI
            ]
        ];
    }
    
    /**
     * Avaliar condições sem IA (filtro rápido)
     */
    private static function evaluateConditionsWithoutAI(array $conditions, array $conversation): array
    {
        if (empty($conditions['conditions'])) {
            return ['met' => true, 'details' => []];
        }

        $operator = $conditions['operator'] ?? 'AND';
        $conditionList = $conditions['conditions'] ?? [];

        $results = [];
        foreach ($conditionList as $condition) {
            // Passar análise vazia pois não precisa de IA
            $result = self::evaluateSingleCondition($condition, $conversation, []);
            $results[] = [
                'condition' => $condition,
                'result' => $result
            ];
        }

        // Aplicar operador lógico
        if ($operator === 'AND') {
            $met = !in_array(false, array_column($results, 'result'));
        } elseif ($operator === 'OR') {
            $met = in_array(true, array_column($results, 'result'));
        } else {
            $met = !in_array(false, array_column($results, 'result'));
        }

        return [
            'met' => $met,
            'details' => $results
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
                // Usar moved_at se existir, senão updated_at, senão created_at
                $dateField = $conversation['moved_at'] ?? $conversation['updated_at'] ?? $conversation['created_at'] ?? null;
                
                if (!$dateField) {
                    return false;
                }
                
                $hours = (time() - strtotime($dateField)) / 3600;
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
        
        Logger::info("KanbanAgentService::executeSingleAction - Executando ação '$type' na conversa {$conversation['id']}");

        switch ($type) {
            case 'analyze_conversation':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'analyze_conversation': conversa {$conversation['id']} já foi analisada");
                return ['message' => 'Conversa já analisada', 'analysis' => $analysis];
            
            case 'send_followup_message':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'send_followup_message': enviando mensagem para conversa {$conversation['id']}");
                return self::actionSendFollowupMessage($conversation, $analysis, $config);
            
            case 'move_to_stage':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'move_to_stage': movendo conversa {$conversation['id']} para etapa " . ($config['stage_id'] ?? 'N/A'));
                return self::actionMoveToStage($conversation, $config);
            
            case 'move_to_next_stage':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'move_to_next_stage': movendo conversa {$conversation['id']} para próxima etapa");
                return self::actionMoveToNextStage($conversation);
            
            case 'assign_to_agent':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'assign_to_agent': atribuindo conversa {$conversation['id']} a agente");
                return self::actionAssignToAgent($conversation, $config);
            
            case 'assign_ai_agent':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'assign_ai_agent': atribuindo agente de IA " . ($config['ai_agent_id'] ?? 'N/A') . " à conversa {$conversation['id']}");
                return self::actionAssignAIAgent($conversation, $config);
            
            case 'add_tag':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'add_tag': adicionando tags " . json_encode($config['tags'] ?? []) . " à conversa {$conversation['id']}");
                return self::actionAddTag($conversation, $config);
            
            case 'create_summary':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'create_summary': criando resumo para conversa {$conversation['id']}");
                return self::actionCreateSummary($conversation, $analysis, $config);
            
            case 'create_note':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'create_note': criando nota para conversa {$conversation['id']}");
                return self::actionCreateNote($conversation, $analysis, $config);
            
            case 'send_internal_message':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'send_internal_message': enviando mensagem interna para conversa {$conversation['id']}");
                return self::actionSendInternalMessage($conversation, $analysis, $config);
            
            default:
                Logger::error("KanbanAgentService::executeSingleAction - Tipo de ação desconhecido: $type");
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
        
        Logger::info("KanbanAgentService::actionSendFollowupMessage - Gerando mensagem (IA: " . ($useAIGenerated ? 'sim' : 'não') . ")");
        
        if ($useAIGenerated) {
            // Gerar mensagem com IA
            $message = self::generateFollowupMessage($conversation, $analysis);
            Logger::info("KanbanAgentService::actionSendFollowupMessage - Mensagem gerada por IA: " . substr($message, 0, 50) . "...");
        } else {
            // Usar template
            $message = self::processTemplate($template, $conversation, $analysis);
            Logger::info("KanbanAgentService::actionSendFollowupMessage - Mensagem gerada por template: " . substr($message, 0, 50) . "...");
        }

        if (empty(trim($message))) {
            Logger::error("KanbanAgentService::actionSendFollowupMessage - ERRO: Mensagem de followup vazia");
            throw new \Exception('Mensagem de followup não pode estar vazia');
        }

        // Enviar mensagem usando ConversationService
        Logger::info("KanbanAgentService::actionSendFollowupMessage - Enviando mensagem para conversa {$conversation['id']}");
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

        Logger::info("KanbanAgentService::actionSendFollowupMessage - Mensagem enviada com sucesso (ID: $messageId)");
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
        Logger::info("KanbanAgentService::actionAddTag - Tags a adicionar: " . json_encode($tags));
        
        if (empty($tags)) {
            Logger::error("KanbanAgentService::actionAddTag - ERRO: Nenhuma tag especificada");
            throw new \Exception('Nenhuma tag especificada');
        }

        $addedTags = [];
        $errors = [];

        foreach ($tags as $tag) {
            try {
                // Se for ID numérico, usar diretamente
                if (is_numeric($tag)) {
                    Logger::info("KanbanAgentService::actionAddTag - Adicionando tag ID $tag à conversa {$conversation['id']}");
                    \App\Services\TagService::addToConversation($conversation['id'], (int)$tag);
                    $tagObj = \App\Models\Tag::find((int)$tag);
                    $tagName = $tagObj ? $tagObj['name'] : "Tag #{$tag}";
                    $addedTags[] = $tagName;
                    Logger::info("KanbanAgentService::actionAddTag - Tag '$tagName' adicionada com sucesso");
                } else {
                    // Se for nome, buscar tag por nome
                    Logger::info("KanbanAgentService::actionAddTag - Buscando tag por nome: '$tag'");
                    $tagObj = \App\Models\Tag::whereFirst('name', '=', $tag);
                    if ($tagObj) {
                        \App\Services\TagService::addToConversation($conversation['id'], $tagObj['id']);
                        $addedTags[] = $tagObj['name'];
                        Logger::info("KanbanAgentService::actionAddTag - Tag '{$tagObj['name']}' adicionada com sucesso");
                    } else {
                        $errorMsg = "Tag '{$tag}' não encontrada";
                        $errors[] = $errorMsg;
                        Logger::warning("KanbanAgentService::actionAddTag - $errorMsg");
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = "Erro ao adicionar tag '{$tag}': " . $e->getMessage();
                $errors[] = $errorMsg;
                Logger::error("KanbanAgentService::actionAddTag - $errorMsg");
            }
        }

        if (!empty($errors)) {
            Logger::warning("KanbanAgentService::actionAddTag - Total de erros: " . count($errors));
        }
        
        $resultMessage = !empty($addedTags) ? 'Tags adicionadas: ' . implode(', ', $addedTags) : 'Nenhuma tag adicionada';
        Logger::info("KanbanAgentService::actionAddTag - Resultado: $resultMessage");

        return [
            'message' => $resultMessage,
            'added_tags' => $addedTags,
            'errors' => $errors
        ];
    }

    /**
     * Ação: Criar resumo
     */
    private static function actionCreateSummary(array $conversation, array $analysis, array $config): array
    {
        $summaryType = $config['summary_type'] ?? 'public'; // Padrão: público
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
        $isInternal = $config['is_internal'] ?? false; // Padrão: nota pública

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
     * Ação: Enviar mensagem interna (nota no chat)
     */
    private static function actionSendInternalMessage(array $conversation, array $analysis, array $config): array
    {
        $message = $config['message'] ?? '';
        
        if (empty(trim($message))) {
            throw new \Exception('Conteúdo da mensagem interna não pode estar vazio');
        }

        // Processar template da mensagem
        $messageContent = self::processTemplate($message, $conversation, $analysis);

        Logger::info("KanbanAgentService::actionSendInternalMessage - Criando mensagem interna na conversa {$conversation['id']}");

        // Buscar usuário do sistema
        $systemUserId = self::getSystemUserId();

        // Criar mensagem interna (is_internal = 1) diretamente na tabela messages
        $sql = "INSERT INTO messages (
            conversation_id, 
            sender_id, 
            sender_type, 
            content, 
            type,
            is_internal,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $messageId = Database::insert($sql, [
                $conversation['id'],
                $systemUserId,
                'agent',
                $messageContent,
                'text',
                1, // is_internal = true
                'sent'
            ]);
            
            Logger::info("KanbanAgentService::actionSendInternalMessage - Mensagem interna criada com sucesso (ID: $messageId)");
            
            // Notificar via WebSocket se disponível
            try {
                \App\Helpers\WebSocket::notifyConversationUpdated($conversation['id']);
            } catch (\Exception $e) {
                // WebSocket pode não estar disponível, ignorar erro
                Logger::warning("KanbanAgentService::actionSendInternalMessage - WebSocket não disponível: " . $e->getMessage());
            }
            
            return ['message' => 'Mensagem interna enviada', 'message_id' => $messageId, 'content' => $messageContent];
        } catch (\Exception $e) {
            Logger::error("KanbanAgentService::actionSendInternalMessage - Erro: " . $e->getMessage());
            throw new \Exception('Erro ao criar mensagem interna: ' . $e->getMessage());
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
        // Tentar buscar um usuário super_admin ou admin
        $admin = \App\Models\User::whereFirst('role', '=', 'super_admin');
        if ($admin) {
            return (int)$admin['id'];
        }
        
        // Tentar admin
        $admin = \App\Models\User::whereFirst('role', '=', 'admin');
        if ($admin) {
            return (int)$admin['id'];
        }
        
        // Se não encontrar, buscar qualquer usuário ativo
        $user = \App\Models\User::whereFirst('status', '=', 'active');
        if ($user) {
            return (int)$user['id'];
        }
        
        // Fallback: retornar 1 (usuário ID 1 geralmente é admin)
        return 1;
    }

    /**
     * Obter última execução com ações para uma conversa específica
     */
    private static function getLastExecutionLog(int $agentId, int $conversationId): ?array
    {
        $sql = "SELECT * FROM ai_kanban_agent_actions_log 
                WHERE ai_kanban_agent_id = ? 
                AND conversation_id = ? 
                ORDER BY executed_at DESC 
                LIMIT 1";
        
        $logs = Database::fetchAll($sql, [$agentId, $conversationId]);
        return !empty($logs) ? $logs[0] : null;
    }

    /**
     * Calcular diferença em horas entre duas datas
     */
    private static function calculateHoursDiff(string $datetime): float
    {
        $executionTime = new \DateTime($datetime);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $executionTime->getTimestamp();
        return round($diff / 3600, 2); // Converter segundos para horas
    }

    /**
     * Criar snapshot do estado atual da conversa
     */
    private static function createConversationSnapshot(array $conversation): array
    {
        // Buscar última mensagem
        $lastMessage = Message::getLastByConversation($conversation['id']);
        
        // Buscar tags da conversa
        $tags = [];
        $tagsData = Database::fetchAll(
            "SELECT tag_id FROM conversation_tags WHERE conversation_id = ?",
            [$conversation['id']]
        );
        foreach ($tagsData as $tag) {
            $tags[] = (int)$tag['tag_id'];
        }
        
        return [
            'funnel_stage_id' => (int)($conversation['funnel_stage_id'] ?? 0),
            'agent_id' => (int)($conversation['agent_id'] ?? 0),
            'last_message_id' => (int)($lastMessage['id'] ?? 0),
            'last_message_at' => $lastMessage['created_at'] ?? null,
            'status' => $conversation['status'] ?? 'open',
            'tags' => $tags,
            'updated_at' => $conversation['updated_at'] ?? null
        ];
    }

    /**
     * Detectar mudanças significativas na conversa
     */
    private static function hasSignificantChanges(array $conversation, ?array $snapshot): bool
    {
        if (!$snapshot) {
            return true; // Sem snapshot anterior, considerar mudança
        }
        
        // Mudou de etapa?
        if ((int)($conversation['funnel_stage_id'] ?? 0) != (int)($snapshot['funnel_stage_id'] ?? 0)) {
            self::logInfo("Mudança detectada: etapa alterada (de {$snapshot['funnel_stage_id']} para {$conversation['funnel_stage_id']})");
            return true;
        }
        
        // Nova mensagem?
        $lastMessage = Message::getLastByConversation($conversation['id']);
        $currentLastMessageId = (int)($lastMessage['id'] ?? 0);
        $snapshotLastMessageId = (int)($snapshot['last_message_id'] ?? 0);
        
        if ($currentLastMessageId != $snapshotLastMessageId) {
            self::logInfo("Mudança detectada: nova mensagem (ID atual: $currentLastMessageId, snapshot: $snapshotLastMessageId)");
            return true;
        }
        
        // Agente mudou?
        if ((int)($conversation['agent_id'] ?? 0) != (int)($snapshot['agent_id'] ?? 0)) {
            self::logInfo("Mudança detectada: agente alterado (de {$snapshot['agent_id']} para {$conversation['agent_id']})");
            return true;
        }
        
        // Status mudou?
        if (($conversation['status'] ?? 'open') != ($snapshot['status'] ?? 'open')) {
            self::logInfo("Mudança detectada: status alterado (de {$snapshot['status']} para {$conversation['status']})");
            return true;
        }
        
        // Tags mudaram?
        $currentTags = [];
        $tagsData = Database::fetchAll(
            "SELECT tag_id FROM conversation_tags WHERE conversation_id = ?",
            [$conversation['id']]
        );
        foreach ($tagsData as $tag) {
            $currentTags[] = (int)$tag['tag_id'];
        }
        sort($currentTags);
        
        $snapshotTags = $snapshot['tags'] ?? [];
        sort($snapshotTags);
        
        if ($currentTags != $snapshotTags) {
            self::logInfo("Mudança detectada: tags alteradas");
            return true;
        }
        
        self::logInfo("Nenhuma mudança significativa detectada");
        return false;
    }

    /**
     * Verificar se deve pular conversa por cooldown
     * Retorna [shouldSkip, reason]
     */
    private static function shouldSkipConversation(array $agent, array $conversation, bool $forceExecution = false): array
    {
        // Se forçar execução, não pular
        if ($forceExecution) {
            self::logInfo("Conversa {$conversation['id']}: execução forçada, ignorando cooldown");
            return [false, 'forced'];
        }
        
        // Verificar última execução
        $lastExecution = self::getLastExecutionLog($agent['id'], $conversation['id']);
        
        if (!$lastExecution) {
            self::logInfo("Conversa {$conversation['id']}: sem execução anterior, processando");
            return [false, 'no_previous_execution'];
        }
        
        // Calcular tempo desde última execução
        $hoursSinceLastExecution = self::calculateHoursDiff($lastExecution['executed_at']);
        $cooldownHours = (int)($agent['cooldown_hours'] ?? 24);
        
        self::logInfo("Conversa {$conversation['id']}: última execução há {$hoursSinceLastExecution}h (cooldown: {$cooldownHours}h)");
        
        // Se ainda está dentro do cooldown
        if ($hoursSinceLastExecution < $cooldownHours) {
            $allowReexecution = (bool)($agent['allow_reexecution_on_change'] ?? true);
            
            if ($allowReexecution) {
                // Verificar mudanças significativas
                $snapshot = null;
                if (!empty($lastExecution['conversation_snapshot'])) {
                    $snapshot = json_decode($lastExecution['conversation_snapshot'], true);
                }
                
                $hasChanges = self::hasSignificantChanges($conversation, $snapshot);
                
                if (!$hasChanges) {
                    self::logInfo("Conversa {$conversation['id']}: PULANDO - cooldown ativo e sem mudanças");
                    return [true, 'cooldown_no_changes'];
                }
                
                self::logInfo("Conversa {$conversation['id']}: PROCESSANDO - mudanças detectadas durante cooldown");
                return [false, 'changes_detected'];
            } else {
                self::logInfo("Conversa {$conversation['id']}: PULANDO - cooldown ativo e re-execução desabilitada");
                return [true, 'cooldown_strict'];
            }
        }
        
        self::logInfo("Conversa {$conversation['id']}: PROCESSANDO - cooldown expirado");
        return [false, 'cooldown_expired'];
    }
}

