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
     * Verificar se o agente está dentro do horário de funcionamento configurado
     * @return array ['allowed' => bool, 'reason' => string]
     */
    private static function isWithinWorkingHours(array $agent): array
    {
        $settings = $agent['settings'] ?? [];
        if (is_string($settings)) {
            $settings = json_decode($settings, true) ?? [];
        }
        
        $workingHours = $settings['working_hours'] ?? null;
        
        // Se não tem configuração de horário ou não está habilitado, permite
        if (!$workingHours || !($workingHours['enabled'] ?? false)) {
            return ['allowed' => true, 'reason' => 'Sem restrição de horário'];
        }
        
        // Obter data/hora atual no timezone do sistema
        date_default_timezone_set('America/Sao_Paulo');
        $now = new \DateTime();
        $currentDay = (int)$now->format('w'); // 0 = Domingo, 6 = Sábado
        $currentTime = $now->format('H:i');
        
        // Verificar dia da semana
        $allowedDays = $workingHours['days'] ?? [1, 2, 3, 4, 5]; // Padrão: Seg-Sex
        if (!in_array($currentDay, $allowedDays)) {
            $dayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
            return [
                'allowed' => false, 
                'reason' => "Dia não permitido ({$dayNames[$currentDay]}). Dias permitidos: " . 
                           implode(', ', array_map(fn($d) => $dayNames[$d], $allowedDays))
            ];
        }
        
        // Verificar horário
        $startTime = $workingHours['start_time'] ?? '08:00';
        $endTime = $workingHours['end_time'] ?? '18:00';
        
        if ($currentTime < $startTime || $currentTime > $endTime) {
            return [
                'allowed' => false, 
                'reason' => "Horário não permitido (atual: {$currentTime}). Horário permitido: {$startTime} às {$endTime}"
            ];
        }
        
        return ['allowed' => true, 'reason' => "Dentro do horário de funcionamento"];
    }
    
    /**
     * Executar agentes instantâneos para uma mensagem específica
     * Chamado quando uma mensagem é enviada (cliente ou agente)
     */
    public static function executeInstantAgents(int $conversationId, string $triggerType): array
    {
        self::logInfo("KanbanAgentService::executeInstantAgents - Iniciando para conversa $conversationId (trigger: $triggerType)");
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            self::logWarning("KanbanAgentService::executeInstantAgents - Conversa $conversationId não encontrada");
            return [];
        }
        
        // Mapear trigger para execution_type
        $executionTypes = [];
        if ($triggerType === 'client_message') {
            $executionTypes = ['instant_client_message', 'instant_any_message'];
        } elseif ($triggerType === 'agent_message') {
            $executionTypes = ['instant_agent_message', 'instant_any_message'];
        } else {
            $executionTypes = ['instant_any_message'];
        }
        
        // Buscar agentes instantâneos ativos
        $placeholders = implode(',', array_fill(0, count($executionTypes), '?'));
        $sql = "SELECT * FROM ai_kanban_agents 
                WHERE enabled = TRUE 
                AND execution_type IN ($placeholders)";
        $agents = Database::fetchAll($sql, $executionTypes);
        
        self::logInfo("KanbanAgentService::executeInstantAgents - Agentes encontrados: " . count($agents));
        
        $results = [];
        
        foreach ($agents as $agentData) {
            try {
                // Decodificar campos JSON
                $agent = AIKanbanAgent::find($agentData['id']);
                if (!$agent || !$agent['enabled']) {
                    continue;
                }
                
                // Verificar se a conversa está nos funis/etapas alvo
                $funnelIds = $agent['target_funnel_ids'] ?? null;
                $stageIds = $agent['target_stage_ids'] ?? null;
                
                // Verificar funil
                if ($funnelIds && is_array($funnelIds) && !empty($funnelIds)) {
                    if (!in_array($conversation['funnel_id'], $funnelIds)) {
                        self::logInfo("Conversa {$conversationId} não está nos funis alvo do agente {$agent['id']}");
                        continue;
                    }
                }
                
                // Verificar etapa
                if ($stageIds && is_array($stageIds) && !empty($stageIds)) {
                    if (!in_array($conversation['funnel_stage_id'], $stageIds)) {
                        self::logInfo("Conversa {$conversationId} não está nas etapas alvo do agente {$agent['id']}");
                        continue;
                    }
                }
                
                // Verificar horário de funcionamento
                $workingHoursCheck = self::isWithinWorkingHours($agent);
                if (!$workingHoursCheck['allowed']) {
                    self::logWarning("KanbanAgentService::executeInstantAgents - Agente {$agent['id']} fora do horário: " . $workingHoursCheck['reason']);
                    $results[] = [
                        'agent_id' => $agent['id'],
                        'agent_name' => $agent['name'],
                        'success' => false,
                        'message' => 'Fora do horário de funcionamento'
                    ];
                    continue;
                }
                
                self::logInfo("KanbanAgentService::executeInstantAgents - Executando agente {$agent['id']} para conversa $conversationId");
                
                // Executar agente para esta conversa específica
                $result = self::executeAgentForConversation($agent, $conversation, 'instant_' . $triggerType);
                
                $results[] = [
                    'agent_id' => $agent['id'],
                    'agent_name' => $agent['name'],
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? ''
                ];
                
            } catch (\Exception $e) {
                self::logError("KanbanAgentService::executeInstantAgents - Erro ao executar agente {$agentData['id']}: " . $e->getMessage());
                $results[] = [
                    'agent_id' => $agentData['id'],
                    'agent_name' => $agentData['name'] ?? 'Unknown',
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        self::logInfo("KanbanAgentService::executeInstantAgents - Finalizado. Resultados: " . count($results));
        return $results;
    }
    
    /**
     * Executar agente para uma conversa específica (usado por execução instantânea)
     */
    private static function executeAgentForConversation(array $agent, array $conversation, string $executionType): array
    {
        $agentId = $agent['id'];
        $conversationId = $conversation['id'];
        
        self::logInfo("KanbanAgentService::executeAgentForConversation - Agente {$agentId}, Conversa {$conversationId}");
        
        // Criar registro de execução
        $executionId = AIKanbanAgentExecution::createExecution($agentId, $executionType);
        
        try {
            $stats = [
                'conversations_found' => 1,
                'conversations_filtered' => 0,
                'conversations_analyzed' => 0,
                'conversations_acted_upon' => 0,
                'actions_executed' => 0,
                'errors_count' => 0,
                'results' => []
            ];
            
            // Separar condições
            $separatedConditions = self::separateConditions($agent['conditions']);
            $hasConditionsWithoutAI = !empty($separatedConditions['without_ai']['conditions']);
            $hasConditionsWithAI = !empty($separatedConditions['with_ai']['conditions']);
            
            // Avaliar condições sem IA primeiro (filtro rápido)
            if ($hasConditionsWithoutAI) {
                $basicConditionsMet = self::evaluateConditionsWithoutAI($separatedConditions['without_ai'], $conversation);
                if (!$basicConditionsMet['met']) {
                    self::logInfo("Conversa {$conversationId}: condições básicas NÃO atendidas");
                    AIKanbanAgentExecution::completeExecution($executionId, $stats);
                    return ['success' => true, 'message' => 'Condições básicas não atendidas', 'stats' => $stats];
                }
                $stats['conversations_filtered'] = 1;
            } else {
                $stats['conversations_filtered'] = 1;
            }
            
            // Verificar cooldown
            $forceExecution = strpos($executionType, 'manual') !== false;
            [$shouldSkip, $reason] = self::shouldSkipConversation($agent, $conversation, $forceExecution);
            
            if ($shouldSkip) {
                self::logInfo("Conversa {$conversationId}: PULADA - motivo: $reason");
                AIKanbanAgentExecution::completeExecution($executionId, $stats);
                return ['success' => true, 'message' => "Conversa pulada: $reason", 'stats' => $stats];
            }
            
            // Analisar conversa com IA
            $stats['conversations_analyzed'] = 1;
            self::logInfo("Analisando conversa {$conversationId} com IA");
            $analysis = self::analyzeConversation($agent, $conversation);
            
            // Avaliar condições de IA
            $aiConditionsMet = ['met' => true, 'details' => []];
            if ($hasConditionsWithAI) {
                $aiConditionsMet = self::evaluateConditions($separatedConditions['with_ai'], $conversation, $analysis);
            }
            
            // Executar ações se condições atendidas
            if ($aiConditionsMet['met']) {
                $stats['conversations_acted_upon'] = 1;
                self::logInfo("Executando ações para conversa {$conversationId}");
                
                $actionsResult = self::executeActions($agent['actions'], $conversation, $analysis, $agentId, $executionId);
                $stats['actions_executed'] = $actionsResult['executed'];
                $stats['errors_count'] = $actionsResult['errors'];
                
                // Criar snapshot e registrar log
                $conversationSnapshot = self::createConversationSnapshot($conversation);
                
                AIKanbanAgentActionLog::createLog([
                    'ai_kanban_agent_id' => $agentId,
                    'execution_id' => $executionId,
                    'conversation_id' => $conversationId,
                    'analysis_summary' => $analysis['summary'] ?? null,
                    'analysis_score' => $analysis['score'] ?? null,
                    'conditions_met' => true,
                    'conditions_details' => array_merge(
                        $separatedConditions['without_ai']['conditions'] ?? [],
                        $aiConditionsMet['details'] ?? []
                    ),
                    'actions_executed' => $actionsResult['actions'] ?? [],
                    'success' => (int)($actionsResult['errors'] === 0),
                    'conversation_snapshot' => $conversationSnapshot
                ]);
            } else {
                // Registrar log mesmo sem ações
                $conversationSnapshot = self::createConversationSnapshot($conversation);
                
                AIKanbanAgentActionLog::createLog([
                    'ai_kanban_agent_id' => $agentId,
                    'execution_id' => $executionId,
                    'conversation_id' => $conversationId,
                    'analysis_summary' => $analysis['summary'] ?? null,
                    'analysis_score' => $analysis['score'] ?? null,
                    'conditions_met' => false,
                    'conditions_details' => array_merge(
                        $separatedConditions['without_ai']['conditions'] ?? [],
                        $aiConditionsMet['details'] ?? []
                    ),
                    'actions_executed' => [],
                    'success' => 1,
                    'conversation_snapshot' => $conversationSnapshot
                ]);
            }
            
            // Finalizar execução
            AIKanbanAgentExecution::completeExecution($executionId, $stats);
            
            $message = "Execução instantânea concluída. Ações: {$stats['actions_executed']}, Erros: {$stats['errors_count']}";
            self::logInfo($message);
            
            return [
                'success' => true,
                'message' => $message,
                'stats' => $stats
            ];
            
        } catch (\Throwable $e) {
            self::logError("Erro na execução instantânea: " . $e->getMessage());
            AIKanbanAgentExecution::completeExecution($executionId, [], $e->getMessage());
            throw $e;
        }
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
        
        // Verificar horário de funcionamento (exceto se for manual_force)
        if ($executionType !== 'manual_force') {
            $workingHoursCheck = self::isWithinWorkingHours($agent);
            if (!$workingHoursCheck['allowed']) {
                self::logWarning("KanbanAgentService::executeAgent - Agente $agentId fora do horário de funcionamento: " . $workingHoursCheck['reason']);
                return [
                    'success' => false,
                    'message' => 'Fora do horário de funcionamento: ' . $workingHoursCheck['reason'],
                    'skipped_reason' => 'working_hours',
                    'stats' => []
                ];
            }
            self::logInfo("KanbanAgentService::executeAgent - Horário de funcionamento OK: " . $workingHoursCheck['reason']);
        }

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
                self::logInfo("KanbanAgentService::executeAgent - Condições a avaliar: " . json_encode($separatedConditions['without_ai']));
                
                $debugCount = 0;
                foreach ($conversations as $conversation) {
                    $basicConditionsMet = self::evaluateConditionsWithoutAI($separatedConditions['without_ai'], $conversation);
                    
                    // Log detalhado para as primeiras 5 conversas
                    if ($debugCount < 5) {
                        self::logInfo("DEBUG Conversa {$conversation['id']}: status='{$conversation['status']}', condição atendida=" . ($basicConditionsMet['met'] ? 'SIM' : 'NÃO') . ", detalhes=" . json_encode($basicConditionsMet['details']));
                        $debugCount++;
                    }
                    
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

            // PASSO 3: Aplicar filtro de cooldown ANTES de limitar
            // Isso garante que conversas novas (não processadas) tenham prioridade
            $forceExecution = $executionType === 'manual_force'; // Permitir forçar execução
            $conversationsAfterCooldown = [];
            $skippedByCooldown = 0;
            
            self::logInfo("KanbanAgentService::executeAgent - Aplicando filtro de cooldown em " . count($filteredConversations) . " conversas...");
            
            foreach ($filteredConversations as $conversation) {
                [$shouldSkip, $reason] = self::shouldSkipConversation($agent, $conversation, $forceExecution);
                
                if ($shouldSkip) {
                    $skippedByCooldown++;
                    // Só logar as primeiras 5 para não poluir
                    if ($skippedByCooldown <= 5) {
                        self::logInfo("Conversa {$conversation['id']}: PULADA - motivo: $reason");
                    }
                    continue;
                }
                
                $conversationsAfterCooldown[] = $conversation;
            }
            
            self::logInfo("KanbanAgentService::executeAgent - Conversas após filtro de cooldown: " . count($conversationsAfterCooldown) . " de " . count($filteredConversations) . " (puladas: $skippedByCooldown)");
            
            // PASSO 4: Limitar conversas para análise com IA (APÓS cooldown)
            $maxConversations = $agent['max_conversations_per_execution'] ?? 50;
            $totalBeforeLimitAfterCooldown = count($conversationsAfterCooldown);
            $conversationsToAnalyze = array_slice($conversationsAfterCooldown, 0, $maxConversations);
            
            if ($totalBeforeLimitAfterCooldown > $maxConversations) {
                self::logInfo("KanbanAgentService::executeAgent - Limitando análise a $maxConversations conversas (disponíveis após cooldown: $totalBeforeLimitAfterCooldown)");
            }

            self::logInfo("KanbanAgentService::executeAgent - Conversas prontas para análise com IA: " . count($conversationsToAnalyze));
            
            // IMPORTANTE: Se todas foram puladas pelo cooldown, avisar
            if (count($conversationsToAnalyze) === 0 && $skippedByCooldown > 0) {
                self::logWarning("⚠️ TODAS as " . count($filteredConversations) . " conversas foram puladas pelo cooldown! Cooldown configurado: {$agent['cooldown_hours']}h. Use 'Forçar Execução' para ignorar o cooldown.");
            }

            // PASSO 5: Analisar conversas prontas
            foreach ($conversationsToAnalyze as $index => $conversation) {
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
                                'success' => (int)($actionsResult['errors'] === 0),
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
                                'success' => 1,
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
            $errorMessage = $e->getMessage();
            $errorCode = $e->getCode();
            
            self::logError("KanbanAgentService::analyzeConversation - Erro: $errorMessage");
            self::logError("KanbanAgentService::analyzeConversation - Stack trace: " . $e->getTraceAsString());
            
            // Tratamento específico para quota excedida
            if ($errorCode === 429 && strpos($errorMessage, 'QUOTA_EXCEEDED') !== false) {
                self::logError("KanbanAgentService::analyzeConversation - QUOTA EXCEDIDA - Retornando análise padrão");
                
                // Retornar análise padrão neutra para não bloquear o fluxo
                return [
                    'summary' => 'Análise temporariamente indisponível (quota da OpenAI excedida). Verifique seu plano.',
                    'score' => 50, // Score neutro
                    'sentiment' => 'neutral',
                    'urgency' => 'low',
                    'recommendations' => ['Renovar quota da OpenAI para retomar análises automáticas'],
                    'error' => 'quota_exceeded',
                    'error_message' => 'Quota da OpenAI excedida'
                ];
            }
            
            // Tratamento específico para rate limit
            if ($errorCode === 429 && strpos($errorMessage, 'RATE_LIMIT') !== false) {
                self::logWarning("KanbanAgentService::analyzeConversation - RATE LIMIT - Retornando análise padrão");
                
                return [
                    'summary' => 'Análise temporariamente indisponível (rate limit da OpenAI). Aguarde alguns instantes.',
                    'score' => 50,
                    'sentiment' => 'neutral',
                    'urgency' => 'low',
                    'recommendations' => ['Aguardar alguns segundos e tentar novamente'],
                    'error' => 'rate_limit',
                    'error_message' => 'Rate limit temporário da OpenAI'
                ];
            }
            
            // Outros erros genéricos
            return [
                'summary' => 'Erro ao analisar conversa: ' . $errorMessage,
                'score' => 0,
                'sentiment' => 'neutral',
                'urgency' => 'low',
                'recommendations' => [],
                'error' => 'analysis_failed',
                'error_message' => $errorMessage
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Tratamento específico para diferentes códigos HTTP
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? $response;
            $errorType = $errorData['error']['type'] ?? 'unknown';
            $errorCode = $errorData['error']['code'] ?? '';
            
            // Log detalhado do erro
            self::logError("OpenAI API Error - HTTP $httpCode");
            self::logError("Error Type: $errorType");
            self::logError("Error Code: $errorCode");
            self::logError("Error Message: $errorMessage");
            
            // Tratamento específico para quota excedida
            if ($httpCode === 429 && $errorCode === 'insufficient_quota') {
                self::logError("QUOTA DA OPENAI EXCEDIDA! Verifique seu plano e faturamento.");
                self::logError("Acesse: https://platform.openai.com/account/billing");
                
                // Criar notificação persistente para admin
                self::createQuotaExceededAlert();
                
                // Retornar erro específico para tratamento superior
                throw new \Exception("QUOTA_EXCEEDED: A quota da OpenAI foi excedida. Verifique seu plano em https://platform.openai.com/account/billing", 429);
            }
            
            // Tratamento para rate limit temporário
            if ($httpCode === 429 && $errorCode === 'rate_limit_exceeded') {
                self::logWarning("Rate limit da OpenAI atingido temporariamente. Aguardando...");
                
                // Extrair tempo de espera do header Retry-After se disponível
                sleep(2); // Aguardar 2 segundos antes de retornar erro
                
                throw new \Exception("RATE_LIMIT: Rate limit da OpenAI atingido temporariamente. Tente novamente em alguns segundos.", 429);
            }
            
            // Outros erros
            throw new \Exception("Erro na API OpenAI: HTTP $httpCode - Type: $errorType - $errorMessage", $httpCode);
        }

        // Verificar erro de cURL
        if (!empty($curlError)) {
            self::logError("cURL Error: $curlError");
            throw new \Exception("Erro de conexão com OpenAI: $curlError");
        }

        $result = json_decode($response, true);
        
        if (!isset($result['choices'][0]['message']['content'])) {
            self::logError("Resposta inválida da OpenAI: " . json_encode($result));
            throw new \Exception("Resposta inválida da API OpenAI");
        }
        
        return $result['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * Criar alerta quando a quota da OpenAI é excedida
     */
    private static function createQuotaExceededAlert(): void
    {
        try {
            // Verificar se já existe um alerta recente (últimas 24h)
            $recentAlert = Database::fetch(
                "SELECT * FROM system_alerts 
                 WHERE type = 'openai_quota_exceeded' 
                 AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 ORDER BY created_at DESC LIMIT 1"
            );
            
            if ($recentAlert) {
                self::logInfo("Alerta de quota já existe (criado em {$recentAlert['created_at']})");
                return;
            }
            
            // Criar novo alerta
            $sql = "INSERT INTO system_alerts (
                type, 
                severity, 
                title, 
                message, 
                action_url,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            Database::insert($sql, [
                'openai_quota_exceeded',
                'critical',
                'Quota da OpenAI Excedida',
                'A quota da sua API da OpenAI foi excedida. Os agentes de IA Kanban estão temporariamente inativos até que a quota seja renovada ou o plano seja atualizado.',
                'https://platform.openai.com/account/billing'
            ]);
            
            self::logInfo("Alerta de quota excedida criado com sucesso");
            
        } catch (\Exception $e) {
            // Se a tabela não existir ou houver outro erro, apenas logar
            self::logWarning("Não foi possível criar alerta de quota: " . $e->getMessage());
        }
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
            if (in_array($type, [
                // Dados básicos da conversa
                'conversation_status', 'conversation_priority', 'conversation_channel',
                'conversation_funnel', 'conversation_stage', 'conversation_assigned', 'conversation_department',
                // Tempo e mensagens
                'last_message_hours', 'last_message_from', 'client_no_response_minutes', 'agent_no_response_minutes',
                'stage_duration_hours',
                // Tags e atribuição
                'has_tag', 'no_tag', 'assigned_to', 'unassigned',
                // Verificação de mensagens
                'has_messages', 'has_agent_message', 'has_client_message', 'no_client_message', 'no_agent_message', 'message_count',
                'last_message_content', 'last_agent_message_content', 'last_client_message_content', 'any_message_contains',
                // Tempo/Idade
                'conversation_age_hours'
            ])) {
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
        
        // Log para debug
        self::logInfo("evaluateSingleCondition - type={$type}, operator={$operator}, value=" . json_encode($value) . ", conv_id={$conversation['id']}");

        switch ($type) {
            case 'conversation_status':
                $actualStatus = $conversation['status'] ?? '';
                $result = self::compare($actualStatus, $operator, $value);
                self::logInfo("conversation_status: actual='{$actualStatus}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_priority':
                return self::compare($conversation['priority'] ?? 'normal', $operator, $value);
            
            case 'conversation_channel':
                $actualChannel = $conversation['channel'] ?? '';
                $result = self::compare($actualChannel, $operator, $value);
                self::logInfo("conversation_channel: actual='{$actualChannel}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_funnel':
                $actualFunnel = (string)($conversation['funnel_id'] ?? '');
                $result = self::compare($actualFunnel, $operator, $value);
                self::logInfo("conversation_funnel: actual='{$actualFunnel}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_stage':
                $actualStage = (string)($conversation['funnel_stage_id'] ?? '');
                $result = self::compare($actualStage, $operator, $value);
                self::logInfo("conversation_stage: actual='{$actualStage}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_assigned':
                $actualAgent = (string)($conversation['agent_id'] ?? '');
                // Operador is_empty verifica se não tem agente
                if ($operator === 'is_empty') {
                    $result = empty($actualAgent) || $actualAgent === '0';
                    self::logInfo("conversation_assigned: is_empty check, actual='{$actualAgent}', result=" . ($result ? 'TRUE' : 'FALSE'));
                    return $result;
                }
                $result = self::compare($actualAgent, $operator, $value);
                self::logInfo("conversation_assigned: actual='{$actualAgent}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_department':
                $actualDept = (string)($conversation['department_id'] ?? '');
                $result = self::compare($actualDept, $operator, $value);
                self::logInfo("conversation_department: actual='{$actualDept}', expected='{$value}', result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'has_tag':
                // Verificar se a conversa tem uma tag específica
                $tagsData = Database::fetchAll(
                    "SELECT t.name, t.id FROM conversation_tags ct 
                     JOIN tags t ON t.id = ct.tag_id 
                     WHERE ct.conversation_id = ?",
                    [$conversation['id']]
                );
                $tagNames = array_map(fn($t) => strtolower($t['name']), $tagsData);
                $tagIds = array_map(fn($t) => (string)$t['id'], $tagsData);
                
                // Value pode ser ID ou nome da tag
                $searchValue = strtolower($value ?? '');
                $hasTag = in_array($searchValue, $tagNames) || in_array($value, $tagIds);
                
                if ($operator === 'equals') {
                    return $hasTag;
                } elseif ($operator === 'not_equals') {
                    return !$hasTag;
                }
                return $hasTag;
            
            case 'no_tag':
                // Verificar se a conversa NÃO tem uma tag específica (inverso de has_tag)
                $tagsData = Database::fetchAll(
                    "SELECT t.name, t.id FROM conversation_tags ct 
                     JOIN tags t ON t.id = ct.tag_id 
                     WHERE ct.conversation_id = ?",
                    [$conversation['id']]
                );
                $tagNames = array_map(fn($t) => strtolower($t['name']), $tagsData);
                $tagIds = array_map(fn($t) => (string)$t['id'], $tagsData);
                
                $searchValue = strtolower($value ?? '');
                $hasTag = in_array($searchValue, $tagNames) || in_array($value, $tagIds);
                return !$hasTag; // Retorna true se NÃO tem a tag
            
            case 'assigned_to':
                // Verifica se está atribuída a um agente específico
                $actualAgent = (string)($conversation['agent_id'] ?? '');
                return self::compare($actualAgent, $operator, $value);
            
            case 'unassigned':
                // Verifica se a conversa não está atribuída a nenhum agente
                $agentId = $conversation['agent_id'] ?? null;
                $isUnassigned = empty($agentId) || $agentId == 0;
                
                if ($operator === 'equals') {
                    return $value === 'true' ? $isUnassigned : !$isUnassigned;
                }
                return $isUnassigned;
            
            case 'has_messages':
                // Verifica se a conversa tem mensagens
                $msgCount = Database::fetch(
                    "SELECT COUNT(*) as count FROM messages WHERE conversation_id = ?",
                    [$conversation['id']]
                );
                $hasMessages = isset($msgCount['count']) && (int)$msgCount['count'] > 0;
                
                if ($operator === 'equals') {
                    return $value === 'true' ? $hasMessages : !$hasMessages;
                }
                return $hasMessages;
            
            case 'message_count':
                // Conta total de mensagens na conversa
                $msgCount = Database::fetch(
                    "SELECT COUNT(*) as count FROM messages WHERE conversation_id = ?",
                    [$conversation['id']]
                );
                $count = (int)($msgCount['count'] ?? 0);
                $result = self::compare($count, $operator, (int)$value);
                self::logInfo("message_count: count={$count}, operator={$operator}, value={$value}, result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
            case 'conversation_age_hours':
                // Calcula idade da conversa em horas (desde a criação)
                $createdAt = $conversation['created_at'] ?? null;
                if (!$createdAt) {
                    return false;
                }
                $ageHours = (time() - strtotime($createdAt)) / 3600;
                $result = self::compare($ageHours, $operator, (float)$value);
                self::logInfo("conversation_age_hours: age=" . round($ageHours, 2) . "h, operator={$operator}, value={$value}, result=" . ($result ? 'TRUE' : 'FALSE'));
                return $result;
            
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
            
            case 'has_agent_message':
                // Verificar se há mensagem de AGENTE HUMANO na conversa
                // Filtrar: apenas sender_type='agent' + sender_id > 0 (excluir chatbot/sistema) + message_type != 'note' (excluir mensagens internas)
                $agentMessages = Database::fetchAll(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'agent'
                     AND sender_id > 0
                     AND message_type != 'note'",
                    [$conversation['id']]
                );
                $hasAgentMessage = isset($agentMessages[0]['count']) && (int)$agentMessages[0]['count'] > 0;
                // Operador: 'equals' com valor 'true' ou 'false'
                if ($operator === 'equals') {
                    return $value === 'true' ? $hasAgentMessage : !$hasAgentMessage;
                }
                return $hasAgentMessage; // Default: retorna se tem
            
            case 'has_client_message':
                // Verificar se há mensagem do cliente na conversa
                $clientMessages = Database::fetchAll(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'contact'",
                    [$conversation['id']]
                );
                $hasClientMessage = isset($clientMessages[0]['count']) && (int)$clientMessages[0]['count'] > 0;
                // Operador: 'equals' com valor 'true' ou 'false'
                if ($operator === 'equals') {
                    return $value === 'true' ? $hasClientMessage : !$hasClientMessage;
                }
                return $hasClientMessage; // Default: retorna se tem
            
            case 'no_client_message':
                // Verificar se NÃO há nenhuma mensagem do cliente na conversa
                $clientMsgCount = Database::fetchAll(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'contact'",
                    [$conversation['id']]
                );
                $noClientMessage = !isset($clientMsgCount[0]['count']) || (int)$clientMsgCount[0]['count'] === 0;
                if ($operator === 'equals') {
                    return $value === 'true' ? $noClientMessage : !$noClientMessage;
                }
                return $noClientMessage;
            
            case 'no_agent_message':
                // Verificar se NÃO há nenhuma mensagem de AGENTE HUMANO na conversa
                // Filtrar: apenas sender_type='agent' + sender_id > 0 (excluir chatbot/sistema) + message_type != 'note' (excluir mensagens internas)
                $agentMsgCount = Database::fetchAll(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND sender_type = 'agent'
                     AND sender_id > 0
                     AND message_type != 'note'",
                    [$conversation['id']]
                );
                $noAgentMessage = !isset($agentMsgCount[0]['count']) || (int)$agentMsgCount[0]['count'] === 0;
                if ($operator === 'equals') {
                    return $value === 'true' ? $noAgentMessage : !$noAgentMessage;
                }
                return $noAgentMessage;
            
            case 'last_message_content':
                // Verificar conteúdo da última mensagem (qualquer remetente)
                $lastMessage = Message::whereFirst('conversation_id', '=', $conversation['id'], 'ORDER BY created_at DESC');
                if (!$lastMessage) {
                    return false;
                }
                $content = $lastMessage['content'] ?? '';
                return self::compareText($content, $operator, $value);
            
            case 'last_agent_message_content':
                // Verificar conteúdo da última mensagem do AGENTE HUMANO
                // Filtrar: apenas sender_type='agent' + sender_id > 0 (excluir chatbot/sistema) + message_type != 'note' (excluir mensagens internas)
                $lastAgentMsg = Message::whereFirst(
                    'conversation_id', 
                    '=', 
                    $conversation['id'], 
                    "AND sender_type = 'agent' AND sender_id > 0 AND message_type != 'note' ORDER BY created_at DESC"
                );
                if (!$lastAgentMsg) {
                    return false;
                }
                $content = $lastAgentMsg['content'] ?? '';
                return self::compareText($content, $operator, $value);
            
            case 'last_client_message_content':
                // Verificar conteúdo da última mensagem do cliente
                $lastClientMsg = Message::whereFirst(
                    'conversation_id', 
                    '=', 
                    $conversation['id'], 
                    "AND sender_type = 'contact' ORDER BY created_at DESC"
                );
                if (!$lastClientMsg) {
                    return false;
                }
                $content = $lastClientMsg['content'] ?? '';
                return self::compareText($content, $operator, $value);
            
            case 'any_message_contains':
                // Verificar se qualquer mensagem contém o texto
                $messagesWithText = Database::fetchAll(
                    "SELECT COUNT(*) as count FROM messages 
                     WHERE conversation_id = ? 
                     AND LOWER(content) LIKE LOWER(?)",
                    [$conversation['id'], '%' . $value . '%']
                );
                return isset($messagesWithText[0]['count']) && (int)$messagesWithText[0]['count'] > 0;
            
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
     * Comparar texto com operadores específicos
     */
    private static function compareText(string $actual, string $operator, $expected): bool
    {
        $actualLower = strtolower($actual);
        $expectedLower = strtolower($expected ?? '');
        
        switch ($operator) {
            case 'equals':
                return $actualLower === $expectedLower;
            case 'not_equals':
                return $actualLower !== $expectedLower;
            case 'contains':
                return strpos($actualLower, $expectedLower) !== false;
            case 'not_contains':
                return strpos($actualLower, $expectedLower) === false;
            case 'starts_with':
                return strpos($actualLower, $expectedLower) === 0;
            case 'ends_with':
                return substr($actualLower, -strlen($expectedLower)) === $expectedLower;
            case 'is_empty':
                return empty(trim($actual));
            case 'is_not_empty':
                return !empty(trim($actual));
            case 'regex':
                // Suporte a expressão regular
                return preg_match('/' . $expected . '/i', $actual) === 1;
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
            
            case 'remove_tag':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'remove_tag': removendo tags " . json_encode($config['tags'] ?? []) . " da conversa {$conversation['id']}");
                return self::actionRemoveTag($conversation, $config);
            
            case 'change_priority':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'change_priority': alterando prioridade da conversa {$conversation['id']} para " . ($config['priority'] ?? 'N/A'));
                return self::actionChangePriority($conversation, $config);
            
            case 'change_status':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'change_status': alterando status da conversa {$conversation['id']} para " . ($config['status'] ?? 'N/A'));
                return self::actionChangeStatus($conversation, $config);
            
            case 'close_conversation':
                Logger::info("KanbanAgentService::executeSingleAction - Ação 'close_conversation': encerrando conversa {$conversation['id']}");
                return self::actionCloseConversation($conversation);
            
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

        // Buscar a etapa para obter o funnel_id correto
        $stage = FunnelStage::find($stageId);
        if (!$stage) {
            throw new \Exception("Etapa ID {$stageId} não encontrada");
        }

        $oldStageId = $conversation['funnel_stage_id'] ?? null;
        $oldFunnelId = $conversation['funnel_id'] ?? null;
        $newFunnelId = $stage['funnel_id'];
        
        // Atualizar tanto a etapa quanto o funil
        Conversation::update($conversation['id'], [
            'funnel_id' => $newFunnelId,
            'funnel_stage_id' => $stageId,
            'moved_at' => date('Y-m-d H:i:s')
        ]);
        
        Logger::info("KanbanAgentService::actionMoveToStage - Conversa {$conversation['id']} movida: Funil {$oldFunnelId} -> {$newFunnelId}, Etapa {$oldStageId} -> {$stageId}");
        
        // Notificar via WebSocket para atualizar UI em tempo real
        self::notifyConversationChange($conversation['id'], 'stage_changed', [
            'old_stage_id' => $oldStageId,
            'new_stage_id' => $stageId,
            'old_funnel_id' => $oldFunnelId,
            'new_funnel_id' => $newFunnelId,
            'stage_name' => $stage['name'] ?? ''
        ]);

        return ['message' => "Conversa movida para etapa {$stage['name']} (Funil ID: {$newFunnelId})"];
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

        $oldStageId = $conversation['funnel_stage_id'];
        
        Conversation::update($conversation['id'], [
            'funnel_stage_id' => $nextStage['id'],
            'moved_at' => date('Y-m-d H:i:s')
        ]);
        
        // Notificar via WebSocket para atualizar UI em tempo real
        self::notifyConversationChange($conversation['id'], 'stage_changed', [
            'old_stage_id' => $oldStageId,
            'new_stage_id' => $nextStage['id'],
            'new_stage_name' => $nextStage['name']
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
        
        // Notificar via WebSocket
        self::notifyConversationChange($conversation['id'], 'agent_assigned', [
            'agent_id' => $agentId
        ]);

        return ['message' => "Conversa atribuída ao agente $agentId"];
    }

    /**
     * Ação: Atribuir Agente de IA
     * Usa ConversationAIService para criar registro correto em ai_conversations
     * Se já tiver IA ativa e process_immediately=true, executa follow-up mesmo assim
     */
    private static function actionAssignAIAgent(array $conversation, array $config): array
    {
        $aiAgentId = $config['ai_agent_id'] ?? null;
        $processImmediately = $config['process_immediately'] ?? true; // Por padrão, processa imediatamente
        
        if (!$aiAgentId) {
            throw new \Exception('Nenhum agente de IA especificado');
        }
        
        self::logInfo("KanbanAgentService::actionAssignAIAgent - Atribuindo agente {$aiAgentId} à conversa {$conversation['id']} (process_immediately: " . ($processImmediately ? 'true' : 'false') . ")");
        
        // Verificar se já tem IA ativa na conversa
        $existingAI = \App\Models\AIConversation::getByConversationId($conversation['id']);
        $hasActiveAI = $existingAI && $existingAI['status'] === 'active';
        
        if ($hasActiveAI) {
            self::logInfo("KanbanAgentService::actionAssignAIAgent - Conversa {$conversation['id']} já possui IA ativa (agent_id: {$existingAI['ai_agent_id']})");
            
            // Se process_immediately está marcado, executar follow-up com o agente existente
            if ($processImmediately) {
                $activeAgentId = (int)$existingAI['ai_agent_id'];
                $aiAgent = \App\Models\AIAgent::find($activeAgentId);
                $agentName = $aiAgent ? $aiAgent['name'] : "ID {$activeAgentId}";
                
                self::logInfo("KanbanAgentService::actionAssignAIAgent - Executando follow-up com agente ativo '{$agentName}' na conversa {$conversation['id']}");
                
                try {
                    // Buscar última mensagem do contato e calcular tempo
                    $lastMessageSql = "SELECT *, 
                                       TIMESTAMPDIFF(HOUR, created_at, NOW()) as hours_ago,
                                       TIMESTAMPDIFF(DAY, created_at, NOW()) as days_ago
                                      FROM messages 
                                      WHERE conversation_id = ? 
                                      AND sender_type = 'contact'
                                      ORDER BY created_at DESC 
                                      LIMIT 1";
                    $lastMessage = \App\Helpers\Database::fetch($lastMessageSql, [$conversation['id']]);
                    
                    // Montar mensagem de follow-up com contexto temporal
                    $followupContext = "[FOLLOW-UP AUTOMÁTICO]\n";
                    $followupContext .= "Esta é uma ação de follow-up automático. Analise o histórico da conversa e retome o atendimento de forma natural.\n";
                    
                    if ($lastMessage) {
                        $hoursAgo = (int)($lastMessage['hours_ago'] ?? 0);
                        $daysAgo = (int)($lastMessage['days_ago'] ?? 0);
                        
                        if ($daysAgo > 0) {
                            $followupContext .= "Última mensagem do cliente foi há {$daysAgo} dia(s).\n";
                        } elseif ($hoursAgo > 0) {
                            $followupContext .= "Última mensagem do cliente foi há {$hoursAgo} hora(s).\n";
                        }
                        
                        $followupContext .= "Conteúdo: " . mb_substr($lastMessage['content'], 0, 200);
                        
                        // Processar com contexto de follow-up
                        \App\Services\AIAgentService::processMessage(
                            $conversation['id'],
                            $activeAgentId,
                            $followupContext
                        );
                        self::logInfo("KanbanAgentService::actionAssignAIAgent - Follow-up executado com sucesso para conversa {$conversation['id']}");
                    } else {
                        // Se não há mensagem do contato, processar conversa (pode enviar boas-vindas)
                        \App\Services\AIAgentService::processConversation($conversation['id'], $activeAgentId);
                        self::logInfo("KanbanAgentService::actionAssignAIAgent - Conversa processada (sem mensagem do contato) para {$conversation['id']}");
                    }
                    
                    return [
                        'message' => "Follow-up executado com agente ativo '{$agentName}'",
                        'ai_already_active' => true,
                        'ai_agent_id' => $activeAgentId
                    ];
                    
                } catch (\Exception $e) {
                    self::logError("KanbanAgentService::actionAssignAIAgent - Erro ao executar follow-up: " . $e->getMessage());
                    return [
                        'message' => "IA já ativa, mas erro ao executar follow-up: " . $e->getMessage(),
                        'ai_already_active' => true
                    ];
                }
            } else {
                // Se não quer processar imediatamente, apenas informar que já tem IA
                return ['message' => 'Conversa já possui agente de IA ativo', 'ai_already_active' => true];
            }
        }
        
        // Se não tem IA ativa, adicionar normalmente
        try {
            // Usar ConversationAIService para adicionar corretamente o agente
            // Isso cria o registro em ai_conversations e permite que o sidebar mostre corretamente
            $result = \App\Services\ConversationAIService::addAIAgent($conversation['id'], [
                'ai_agent_id' => $aiAgentId,
                'process_immediately' => $processImmediately, // IA analisa contexto e envia mensagem
                'assume_conversation' => false, // Não remove agente humano se houver
                'only_if_unassigned' => false   // Permite mesmo se tiver agente humano
            ]);
            
            // Buscar nome do agente para log
            $aiAgent = \App\Models\AIAgent::find($aiAgentId);
            $agentName = $aiAgent ? $aiAgent['name'] : "ID {$aiAgentId}";
            
            self::logInfo("KanbanAgentService::actionAssignAIAgent - Agente de IA '{$agentName}' atribuído com sucesso à conversa {$conversation['id']}");
            
            // Notificar via WebSocket (complementar à notificação do ConversationAIService)
            self::notifyConversationChange($conversation['id'], 'ai_agent_assigned', [
                'ai_agent_id' => $aiAgentId,
                'ai_agent_name' => $agentName,
                'process_immediately' => $processImmediately
            ]);
            
            $message = "Agente de IA '{$agentName}' atribuído à conversa";
            if ($processImmediately) {
                $message .= " e mensagem de follow-up enviada";
            }
            
            return [
                'message' => $message,
                'ai_conversation_id' => $result['ai_conversation_id'] ?? null
            ];
            
        } catch (\Exception $e) {
            self::logError("KanbanAgentService::actionAssignAIAgent - Erro: " . $e->getMessage());
            throw $e;
        }
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
        
        // Notificar via WebSocket se tags foram adicionadas
        if (!empty($addedTags)) {
            self::notifyConversationChange($conversation['id'], 'tags_updated', [
                'added_tags' => $addedTags
            ]);
        }

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
            
            // Notificar via WebSocket
            self::notifyConversationChange($conversation['id'], 'note_created', [
                'note_id' => $note['id'] ?? null,
                'note_type' => 'summary'
            ]);
            
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
            
            // Notificar via WebSocket
            self::notifyConversationChange($conversation['id'], 'note_created', [
                'note_id' => $createdNote['id'] ?? null,
                'is_internal' => $isInternal
            ]);
            
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
            message_type,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        try {
            $messageId = Database::insert($sql, [
                $conversation['id'],
                $systemUserId,
                'agent',
                $messageContent,
                'note', // mensagem interna
                'sent'
            ]);
            
            Logger::info("KanbanAgentService::actionSendInternalMessage - Mensagem interna criada com sucesso (ID: $messageId)");
            
            // Notificar via WebSocket - usar notificação de nova mensagem
            try {
                // Buscar dados do remetente para exibição
                $sender = User::find($systemUserId);
                $senderName = $sender ? $sender['name'] : 'Sistema';
                
                // Notificar nova mensagem com todos os campos necessários para renderização
                \App\Helpers\WebSocket::notifyNewMessage(
                    (int)$conversation['id'],
                    [
                        'id' => $messageId,
                        'conversation_id' => $conversation['id'],
                        'sender_id' => $systemUserId,
                        'sender_type' => 'agent',
                        'sender_name' => $senderName,
                        'content' => $messageContent,
                        'message_type' => 'note',
                        'type' => 'note', // Frontend usa 'type' para renderização
                        'direction' => 'outgoing', // Notas internas são sempre outgoing
                        'status' => 'sent',
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                );
                
                // Também notificar atualização da conversa
                \App\Helpers\WebSocket::notifyConversationUpdated(
                    (int)$conversation['id'],
                    Conversation::find($conversation['id'])
                );
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
     * Ação: Remover tag
     */
    private static function actionRemoveTag(array $conversation, array $config): array
    {
        $tags = $config['tags'] ?? [];
        Logger::info("KanbanAgentService::actionRemoveTag - Tags a remover: " . json_encode($tags));
        
        if (empty($tags)) {
            Logger::error("KanbanAgentService::actionRemoveTag - ERRO: Nenhuma tag especificada");
            throw new \Exception('Nenhuma tag especificada');
        }

        $removedTags = [];
        $errors = [];

        foreach ($tags as $tag) {
            try {
                $tagId = null;
                $tagName = $tag;
                
                // Se for ID numérico, usar diretamente
                if (is_numeric($tag)) {
                    $tagId = (int)$tag;
                    $tagObj = \App\Models\Tag::find($tagId);
                    $tagName = $tagObj ? $tagObj['name'] : "Tag #{$tag}";
                } else {
                    // Se for nome, buscar tag por nome
                    $tagObj = \App\Models\Tag::whereFirst('name', '=', $tag);
                    if ($tagObj) {
                        $tagId = $tagObj['id'];
                        $tagName = $tagObj['name'];
                    }
                }
                
                if ($tagId) {
                    // Remover associação tag-conversa
                    $deleted = Database::execute(
                        "DELETE FROM conversation_tags WHERE conversation_id = ? AND tag_id = ?",
                        [$conversation['id'], $tagId]
                    );
                    
                    if ($deleted > 0) {
                        $removedTags[] = $tagName;
                        Logger::info("KanbanAgentService::actionRemoveTag - Tag '$tagName' removida com sucesso");
                    } else {
                        Logger::info("KanbanAgentService::actionRemoveTag - Tag '$tagName' não estava associada à conversa");
                    }
                } else {
                    $errorMsg = "Tag '{$tag}' não encontrada";
                    $errors[] = $errorMsg;
                    Logger::warning("KanbanAgentService::actionRemoveTag - $errorMsg");
                }
            } catch (\Exception $e) {
                $errorMsg = "Erro ao remover tag '{$tag}': " . $e->getMessage();
                $errors[] = $errorMsg;
                Logger::error("KanbanAgentService::actionRemoveTag - $errorMsg");
            }
        }

        $resultMessage = !empty($removedTags) ? 'Tags removidas: ' . implode(', ', $removedTags) : 'Nenhuma tag removida';
        Logger::info("KanbanAgentService::actionRemoveTag - Resultado: $resultMessage");
        
        // Notificar via WebSocket se tags foram removidas
        if (!empty($removedTags)) {
            self::notifyConversationChange($conversation['id'], 'tags_updated', [
                'removed_tags' => $removedTags
            ]);
        }

        return [
            'message' => $resultMessage,
            'removed_tags' => $removedTags,
            'errors' => $errors
        ];
    }

    /**
     * Ação: Alterar prioridade
     */
    private static function actionChangePriority(array $conversation, array $config): array
    {
        $newPriority = $config['priority'] ?? null;
        
        if (!$newPriority) {
            throw new \Exception('Prioridade não especificada');
        }
        
        $validPriorities = ['low', 'normal', 'medium', 'high', 'urgent'];
        if (!in_array($newPriority, $validPriorities)) {
            throw new \Exception("Prioridade inválida: {$newPriority}. Valores aceitos: " . implode(', ', $validPriorities));
        }
        
        $oldPriority = $conversation['priority'] ?? 'normal';
        
        // Atualizar prioridade
        Conversation::update($conversation['id'], [
            'priority' => $newPriority
        ]);
        
        Logger::info("KanbanAgentService::actionChangePriority - Conversa {$conversation['id']}: prioridade alterada de '{$oldPriority}' para '{$newPriority}'");
        
        // Notificar via WebSocket
        self::notifyConversationChange($conversation['id'], 'priority_changed', [
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority
        ]);
        
        return [
            'message' => "Prioridade alterada de '{$oldPriority}' para '{$newPriority}'",
            'old_priority' => $oldPriority,
            'new_priority' => $newPriority
        ];
    }

    /**
     * Ação: Alterar status
     */
    private static function actionChangeStatus(array $conversation, array $config): array
    {
        $newStatus = $config['status'] ?? null;
        
        if (!$newStatus) {
            throw new \Exception('Status não especificado');
        }
        
        $validStatuses = ['open', 'closed', 'resolved', 'pending', 'spam'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \Exception("Status inválido: {$newStatus}. Valores aceitos: " . implode(', ', $validStatuses));
        }
        
        $oldStatus = $conversation['status'] ?? 'open';
        
        // Atualizar status
        $updateData = ['status' => $newStatus];
        
        // Se fechando, adicionar closed_at
        if (in_array($newStatus, ['closed', 'resolved']) && !in_array($oldStatus, ['closed', 'resolved'])) {
            $updateData['closed_at'] = date('Y-m-d H:i:s');
        }
        
        // Se reabrindo, limpar closed_at
        if (!in_array($newStatus, ['closed', 'resolved']) && in_array($oldStatus, ['closed', 'resolved'])) {
            $updateData['closed_at'] = null;
        }
        
        Conversation::update($conversation['id'], $updateData);
        
        Logger::info("KanbanAgentService::actionChangeStatus - Conversa {$conversation['id']}: status alterado de '{$oldStatus}' para '{$newStatus}'");
        
        // Notificar via WebSocket
        self::notifyConversationChange($conversation['id'], 'status_changed', [
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
        
        return [
            'message' => "Status alterado de '{$oldStatus}' para '{$newStatus}'",
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ];
    }

    /**
     * Ação: Encerrar conversa (fechar)
     */
    private static function actionCloseConversation(array $conversation): array
    {
        $oldStatus = $conversation['status'] ?? 'open';
        
        // Se já está fechada, não fazer nada
        if (in_array($oldStatus, ['closed', 'resolved'])) {
            Logger::info("KanbanAgentService::actionCloseConversation - Conversa {$conversation['id']} já está encerrada (status: {$oldStatus})");
            return [
                'message' => "Conversa já estava encerrada (status: {$oldStatus})",
                'old_status' => $oldStatus,
                'new_status' => $oldStatus
            ];
        }
        
        // Encerrar a conversa
        $updateData = [
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s')
        ];
        
        Conversation::update($conversation['id'], $updateData);
        
        Logger::info("KanbanAgentService::actionCloseConversation - Conversa {$conversation['id']}: encerrada (status anterior: '{$oldStatus}')");
        
        // Notificar via WebSocket
        self::notifyConversationChange($conversation['id'], 'status_changed', [
            'old_status' => $oldStatus,
            'new_status' => 'closed'
        ]);
        
        return [
            'message' => "Conversa encerrada (status anterior: '{$oldStatus}')",
            'old_status' => $oldStatus,
            'new_status' => 'closed'
        ];
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
     * Notificar mudança na conversa via WebSocket
     * Atualiza a UI em tempo real sem necessidade de refresh
     */
    private static function notifyConversationChange(int $conversationId, string $changeType, array $data = []): void
    {
        try {
            // Buscar conversa atualizada
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return;
            }
            
            // Buscar dados adicionais para a notificação
            $contact = null;
            if (!empty($conversation['contact_id'])) {
                $contact = Contact::find($conversation['contact_id']);
            }
            
            // Buscar etapa atual
            $stage = null;
            if (!empty($conversation['funnel_stage_id'])) {
                $stage = FunnelStage::find($conversation['funnel_stage_id']);
            }
            
            // Montar dados da notificação
            $notificationData = array_merge($data, [
                'change_type' => $changeType,
                'conversation_id' => $conversationId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Notificar conversa atualizada
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
            
            // Se for mudança de etapa, notificar também o evento específico
            if ($changeType === 'stage_changed' && $stage) {
                // Buscar informações do funil
                $funnel = null;
                $funnelName = '';
                if (!empty($stage['funnel_id'])) {
                    $funnel = Funnel::find($stage['funnel_id']);
                    $funnelName = $funnel['name'] ?? '';
                }
                
                \App\Helpers\WebSocket::broadcast('conversation_moved', [
                    'conversation_id' => $conversationId,
                    'old_stage_id' => $data['old_stage_id'] ?? null,
                    'new_stage_id' => $data['new_stage_id'] ?? null,
                    'old_funnel_id' => $data['old_funnel_id'] ?? null,
                    'new_funnel_id' => $data['new_funnel_id'] ?? null,
                    'stage_name' => $stage['name'] ?? '',
                    'stage_color' => $stage['color'] ?? null,
                    'funnel_name' => $funnelName,
                    'contact_name' => $contact['name'] ?? 'Desconhecido'
                ]);
            }
            
            // Notificar nova mensagem se houver
            if (in_array($changeType, ['message_sent', 'internal_message'])) {
                \App\Helpers\WebSocket::notifyNewMessage($conversationId, $data['message_id'] ?? 0, []);
            }
            
            self::logInfo("WebSocket notification sent: $changeType for conversation $conversationId");
            
        } catch (\Exception $e) {
            // WebSocket pode não estar disponível, logar mas não falhar
            self::logWarning("WebSocket notification failed: " . $e->getMessage());
        }
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
        $lastMessage = Message::getLastMessage($conversation['id']);
        
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
        $lastMessage = Message::getLastMessage($conversation['id']);
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

