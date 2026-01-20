<?php
/**
 * Service AgentPerformanceAnalysisService
 * Análise de performance de vendedores usando OpenAI
 */

namespace App\Services;

use App\Models\AgentPerformanceAnalysis;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Helpers\Database;
use App\Helpers\Logger;

class AgentPerformanceAnalysisService
{
    const API_URL = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Dimensões de avaliação
     */
    const DIMENSIONS = [
        'proactivity' => 'Proatividade',
        'objection_handling' => 'Quebra de Objeções',
        'rapport' => 'Rapport e Empatia',
        'closing_techniques' => 'Técnicas de Fechamento',
        'qualification' => 'Qualificação do Lead',
        'clarity' => 'Clareza e Comunicação',
        'value_proposition' => 'Apresentação de Valor',
        'response_time' => 'Tempo de Resposta',
        'follow_up' => 'Follow-up',
        'professionalism' => 'Profissionalismo'
    ];
    
    /**
     * Obter configurações
     */
    private static function getSettings(): array
    {
        $cs = ConversationSettingsService::getSettings();
        return $cs['agent_performance_analysis'] ?? self::getDefaultSettings();
    }
    
    /**
     * Configurações padrão
     */
    private static function getDefaultSettings(): array
    {
        return [
            'enabled' => false,
            'model' => 'gpt-4-turbo',
            'temperature' => 0.3,
            'check_interval_hours' => 24,
            'max_conversation_age_days' => 7,
            'min_messages_to_analyze' => 5,
            'min_agent_messages' => 3,
            'analyze_closed_only' => true,
            'cost_limit_per_day' => 10.00,
            
            // Dimensões ativas (todas habilitadas por padrão)
            'dimensions' => [
                'proactivity' => ['enabled' => true, 'weight' => 1.0],
                'objection_handling' => ['enabled' => true, 'weight' => 1.5],
                'rapport' => ['enabled' => true, 'weight' => 1.0],
                'closing_techniques' => ['enabled' => true, 'weight' => 1.5],
                'qualification' => ['enabled' => true, 'weight' => 1.2],
                'clarity' => ['enabled' => true, 'weight' => 1.0],
                'value_proposition' => ['enabled' => true, 'weight' => 1.3],
                'response_time' => ['enabled' => true, 'weight' => 0.8],
                'follow_up' => ['enabled' => true, 'weight' => 1.0],
                'professionalism' => ['enabled' => true, 'weight' => 1.0]
            ],
            
            // Filtros
            'filters' => [
                'only_sales_funnels' => false,
                'funnel_ids' => [],
                'only_sales_stages' => [],
                'exclude_agents' => [],
                'only_agents' => [],
                'min_conversation_value' => 0,
                'tags_to_include' => [],
                'tags_to_exclude' => []
            ],
            
            // Relatórios
            'reports' => [
                'generate_individual_report' => true,
                'generate_agent_ranking' => true,
                'generate_team_average' => true,
                'send_to_agent' => false,
                'send_to_supervisor' => true,
                'auto_tag_low_performance' => true,
                'low_performance_threshold' => 2.5
            ],
            
            // Gamificação
            'gamification' => [
                'enabled' => true,
                'award_badges' => true,
                'show_ranking' => true,
                'celebrate_achievements' => true
            ],
            
            // Coaching
            'coaching' => [
                'enabled' => true,
                'auto_create_goals' => true,
                'auto_send_feedback' => false,
                'save_best_practices' => true,
                'min_score_for_best_practice' => 4.5
            ]
        ];
    }
    
    /**
     * Obter API Key da OpenAI
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        return $apiKey;
    }
    
    /**
     * Verificar limite de custo diário
     */
    private static function checkDailyCostLimit(): bool
    {
        $settings = self::getSettings();
        $limit = (float)($settings['cost_limit_per_day'] ?? 0);
        
        if ($limit <= 0) {
            return true;
        }
        
        $sql = "SELECT SUM(cost) as total_cost 
                FROM agent_performance_analysis 
                WHERE DATE(analyzed_at) = CURDATE()";
        $result = Database::fetch($sql);
        $todayCost = (float)($result['total_cost'] ?? 0);
        
        return $todayCost < $limit;
    }
    
    /**
     * Analisar conversa
     */
    public static function analyzeConversation(int $conversationId, bool $force = false): ?array
    {
        $settings = self::getSettings();
        
        if (!$settings['enabled']) {
            Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Análise desabilitada");
            return null;
        }
        
        // Verificar limite de custo
        if (!self::checkDailyCostLimit()) {
            Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Limite de custo diário atingido");
            return null;
        }
        
        // Verificar se já foi analisada
        if (!$force) {
            $existing = AgentPerformanceAnalysis::getByConversation($conversationId);
            if ($existing) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Conversa {$conversationId} já analisada");
                return $existing;
            }
        }
        
        try {
            // Obter conversa
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                throw new \Exception("Conversa não encontrada: {$conversationId}");
            }
            
            // Verificar se tem agente
            if (!$conversation['agent_id']) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Conversa sem agente atribuído");
                return null;
            }
            
            // Verificar status
            $analyzeClosedOnly = $settings['analyze_closed_only'] ?? true;
            if ($analyzeClosedOnly && !in_array($conversation['status'], ['closed', 'resolved'])) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Conversa ainda não fechada");
                return null;
            }
            
            // Verificar filtros
            $filters = $settings['filters'] ?? [];
            if (!empty($filters) && !self::matchesFilters($conversation, $filters)) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Conversa não passa nos filtros");
                return null;
            }
            
            // Obter mensagens
            $messages = self::getMessagesForAnalysis($conversationId);
            
            if (empty($messages)) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Nenhuma mensagem encontrada");
                return null;
            }
            
            // Contar mensagens do agente
            $agentMessages = array_filter($messages, function($m) {
                return $m['sender_type'] === 'agent';
            });
            
            $agentMessageCount = count($agentMessages);
            $minAgentMessages = (int)($settings['min_agent_messages'] ?? 3);
            
            if ($agentMessageCount < $minAgentMessages) {
                Logger::log("AgentPerformanceAnalysisService::analyzeConversation - Mensagens do agente insuficientes ({$agentMessageCount} < {$minAgentMessages})");
                return null;
            }
            
            // Obter API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('API Key da OpenAI não configurada');
            }
            
            // Construir prompt
            $prompt = self::buildAnalysisPrompt($messages, $conversation, $settings);
            
            // Fazer requisição à OpenAI
            $response = self::makeOpenAIRequest($apiKey, $prompt, $settings);
            
            // Processar resposta
            $analysis = self::parseOpenAIResponse($response, $conversation, count($messages), $agentMessageCount, $settings['model']);
            
            // Salvar análise
            $analysisId = AgentPerformanceAnalysis::create($analysis);
            $analysis['id'] = $analysisId;
            
            Logger::log("AgentPerformanceAnalysisService::analyzeConversation - ✅ Análise concluída para conversa {$conversationId}: {$analysis['overall_score']}/5.0");
            
            // Processar ações pós-análise
            self::postAnalysisActions($analysis, $conversation, $settings);
            
            return $analysis;
            
        } catch (\Exception $e) {
            Logger::error("AgentPerformanceAnalysisService::analyzeConversation - Erro: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar se conversa passa nos filtros
     */
    private static function matchesFilters(array $conversation, array $filters): bool
    {
        // Se filtros vazios, passa tudo
        if (empty($filters)) {
            return true;
        }
        
        // Verificar funis
        if (($filters['only_sales_funnels'] ?? false) && empty($conversation['funnel_id'])) {
            return false;
        }
        
        if (!empty($filters['funnel_ids']) && !in_array($conversation['funnel_id'], $filters['funnel_ids'])) {
            return false;
        }
        
        // Verificar estágios
        if (!empty($filters['only_sales_stages']) && !in_array($conversation['stage'], $filters['only_sales_stages'])) {
            return false;
        }
        
        // Verificar agentes excluídos
        if (!empty($filters['exclude_agents']) && in_array($conversation['agent_id'], $filters['exclude_agents'])) {
            return false;
        }
        
        // Verificar agentes incluídos
        if (!empty($filters['only_agents']) && !in_array($conversation['agent_id'], $filters['only_agents'])) {
            return false;
        }
        
        // Verificar valor mínimo
        $minValue = (float)($filters['min_conversation_value'] ?? 0);
        if ($minValue > 0) {
            $conversationValue = (float)($conversation['estimated_value'] ?? 0);
            if ($conversationValue < $minValue) {
                return false;
            }
        }
        
        // Verificar tags (se necessário implementar)
        
        return true;
    }
    
    /**
     * Obter mensagens para análise
     */
    private static function getMessagesForAnalysis(int $conversationId): array
    {
        $sql = "SELECT * FROM messages 
                WHERE conversation_id = ? 
                ORDER BY created_at ASC";
        return Database::fetchAll($sql, [$conversationId]);
    }
    
    /**
     * Construir prompt para análise
     */
    private static function buildAnalysisPrompt(array $messages, array $conversation, array $settings): string
    {
        // Calcular métricas reais antes de enviar para IA
        $metrics = self::calculateConversationMetrics($messages);
        
        $history = self::formatMessagesForAnalysis($messages);
        $dimensions = $settings['dimensions'];
        
        $prompt = "Você é um especialista em análise de vendas e performance comercial.\n\n";
        $prompt .= "Analise a seguinte conversa de vendas entre um VENDEDOR e um CLIENTE.\n\n";
        
        // ADICIONAR MÉTRICAS REAIS
        $prompt .= "📊 MÉTRICAS CALCULADAS DA CONVERSA:\n";
        $prompt .= "- Total de mensagens: {$metrics['total_messages']}\n";
        $prompt .= "- Mensagens do vendedor: {$metrics['agent_messages']}\n";
        $prompt .= "- Mensagens do cliente: {$metrics['client_messages']}\n";
        $prompt .= "- Tempo médio de resposta do vendedor: " . self::formatMinutes($metrics['avg_response_time']) . "\n";
        $prompt .= "- Tempo máximo de resposta: " . self::formatMinutes($metrics['max_response_time']) . "\n";
        $prompt .= "- Duração total da conversa: " . self::formatMinutes($metrics['conversation_duration']) . "\n\n";
        
        // ADICIONAR BENCHMARKS
        $prompt .= "📋 BENCHMARKS DE REFERÊNCIA:\n";
        $prompt .= "Tempo de Resposta:\n";
        $prompt .= "  • 5.0 = EXCELENTE (< 3 minutos em média)\n";
        $prompt .= "  • 4.0 = BOM (3-5 minutos em média)\n";
        $prompt .= "  • 3.0 = ACEITÁVEL (5-10 minutos em média)\n";
        $prompt .= "  • 2.0 = PRECISA MELHORAR (10-20 minutos em média)\n";
        $prompt .= "  • 1.0 = CRÍTICO (> 20 minutos em média)\n\n";
        
        $prompt .= "Follow-up (Persistência e Ir Atrás):\n";
        $prompt .= "  • 5.0 = Cliente sumiu/enrolou e vendedor retornou MÚLTIPLAS vezes com persistência profissional\n";
        $prompt .= "  • 4.0 = Cliente disse 'vou pensar' e vendedor retornou cobrando posicionamento\n";
        $prompt .= "  • 3.0 = Vendedor tentou reativar conversa pelo menos uma vez\n";
        $prompt .= "  • 2.0 = Vendedor apenas mencionou 'qualquer coisa me chama' mas não foi atrás\n";
        $prompt .= "  • 1.0 = Vendedor deixou conversa morrer, não insistiu, desistiu fácil\n\n";
        
        $prompt .= "⚠️ Follow-up NÃO é agendar reunião! É sobre PERSISTÊNCIA:\n";
        $prompt .= "  - Cliente some = Vendedor reativa?\n";
        $prompt .= "  - Cliente enrola = Vendedor insiste?\n";
        $prompt .= "  - Cliente adia = Vendedor cobra?\n";
        $prompt .= "  - Ou vendedor desiste fácil?\n\n";
        
        $prompt .= "⚠️ IMPORTANTE: Use as métricas calculadas acima para avaliar 'Tempo de Resposta'. Não invente valores!\n\n";
        
        $prompt .= "Avalie a PERFORMANCE DO VENDEDOR nas seguintes dimensões (nota de 0 a 5, com 1 casa decimal):\n\n";
        
        // Adicionar dimensões habilitadas
        $dimensionCount = 1;
        foreach (self::DIMENSIONS as $key => $name) {
            if ($dimensions[$key]['enabled'] ?? true) {
                $prompt .= "{$dimensionCount}. {$name} (0-5)\n";
                $prompt .= self::getDimensionCriteria($key);
                $prompt .= "\n";
                $dimensionCount++;
            }
        }
        
        // Contexto adicional
        $prompt .= "\nCONTEXTO ADICIONAL:\n";
        $prompt .= "- Status da conversa: " . ($conversation['status'] ?? 'N/A') . "\n";
        if (!empty($conversation['stage'])) {
            $prompt .= "- Estágio do funil: " . $conversation['stage'] . "\n";
        }
        if (!empty($conversation['estimated_value'])) {
            $prompt .= "- Valor estimado: R$ " . number_format($conversation['estimated_value'], 2, ',', '.') . "\n";
        }
        
        $prompt .= "\nCONVERSA:\n";
        $prompt .= $history . "\n\n";
        
        $prompt .= "Retorne APENAS um JSON válido com a seguinte estrutura:\n";
        $prompt .= "{\n";
        $prompt .= "  \"scores\": {\n";
        
        foreach (self::DIMENSIONS as $key => $name) {
            if ($dimensions[$key]['enabled'] ?? true) {
                $prompt .= "    \"{$key}\": 0-5 com 1 casa decimal,\n";
            }
        }
        
        $prompt .= "  },\n";
        $prompt .= "  \"strengths\": [\"ponto forte 1\", \"ponto forte 2\", \"ponto forte 3\"],\n";
        $prompt .= "  \"weaknesses\": [\"ponto fraco 1\", \"ponto fraco 2\"],\n";
        $prompt .= "  \"improvement_suggestions\": [\"sugestão 1\", \"sugestão 2\", \"sugestão 3\"],\n";
        $prompt .= "  \"key_moments\": [\n";
        $prompt .= "    {\"timestamp\": \"HH:MM\", \"type\": \"positive|negative|neutral\", \"description\": \"descrição\"},\n";
        $prompt .= "    ...\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"detailed_analysis\": \"Análise detalhada em 2-3 parágrafos sobre a performance geral do vendedor\"\n";
        $prompt .= "}\n\n";
        $prompt .= "IMPORTANTE:\n";
        $prompt .= "- Retorne APENAS o JSON válido, sem markdown, sem explicações, sem ```json```\n";
        $prompt .= "- Seja crítico mas construtivo\n";
        $prompt .= "- Identifique momentos específicos da conversa\n";
        $prompt .= "- Dê sugestões práticas e acionáveis\n";
        
        return $prompt;
    }
    
    /**
     * Obter critérios de uma dimensão
     */
    private static function getDimensionCriteria(string $dimension): string
    {
        $criteria = [
            'proactivity' => "   - Toma iniciativa ou apenas responde?\n   - Faz perguntas abertas?\n   - Guia a conversa ativamente?\n   - Antecipa necessidades do cliente?",
            'objection_handling' => "   - Identifica objeções do cliente?\n   - Responde objeções estruturadamente?\n   - Usa técnicas de vendas (feel-felt-found, etc)?\n   - Transforma objeção em oportunidade?",
            'rapport' => "   - Cria conexão com o cliente?\n   - Usa o nome do cliente?\n   - Demonstra empatia?\n   - Tom amigável mas profissional?",
            'closing_techniques' => "   - Tenta fechar a venda?\n   - Usa técnicas de fechamento?\n   - Cria senso de urgência apropriado?\n   - Pede o pedido?",
            'qualification' => "   - Faz perguntas qualificadoras (BANT)?\n   - Entende orçamento, autoridade, necessidade, timing?\n   - Identifica fit do produto?\n   - Evita perder tempo com leads frios?",
            'clarity' => "   - Explica de forma clara?\n   - Evita jargões desnecessários?\n   - Organiza informações logicamente?\n   - Responde o que foi perguntado?",
            'value_proposition' => "   - Apresenta valor, não apenas features?\n   - Conecta produto a benefícios reais?\n   - Usa ROI, social proof, casos de sucesso?\n   - Diferencia da concorrência?",
            'response_time' => "   - ⚠️ USE AS MÉTRICAS CALCULADAS ACIMA!\n   - Tempo médio < 3min = 5.0 (excelente)\n   - Tempo médio 3-5min = 4.0 (bom)\n   - Tempo médio 5-10min = 3.0 (aceitável)\n   - Tempo médio 10-20min = 2.0 (precisa melhorar)\n   - Tempo médio > 20min = 1.0 (crítico)",
            'follow_up' => "   - Vai ATRÁS do cliente que não respondeu?\n   - Cliente disse 'vou pensar' e o vendedor retornou depois?\n   - Cliente sumiu e o vendedor reativou a conversa?\n   - Cliente disse 'volto depois' e o vendedor cobrou?\n   - Ou vendedor deixou a conversa morrer sem insistir?",
            'professionalism' => "   - Gramática e ortografia corretas?\n   - Tom profissional?\n   - Não usa gírias excessivas?\n   - Mantém postura adequada?"
        ];
        
        return $criteria[$dimension] ?? "";
    }
    
    /**
     * Calcular métricas da conversa
     */
    private static function calculateConversationMetrics(array $messages): array
    {
        $agentMessages = 0;
        $clientMessages = 0;
        $responseTimes = [];
        $lastClientMessageTime = null;
        $firstMessageTime = null;
        $lastMessageTime = null;
        
        foreach ($messages as $msg) {
            $messageTime = strtotime($msg['created_at']);
            
            if ($firstMessageTime === null) {
                $firstMessageTime = $messageTime;
            }
            $lastMessageTime = $messageTime;
            
            if ($msg['sender_type'] === 'agent') {
                $agentMessages++;
                
                // Calcular tempo de resposta (tempo entre mensagem do cliente e resposta do agente)
                if ($lastClientMessageTime !== null) {
                    $responseTime = ($messageTime - $lastClientMessageTime) / 60; // em minutos
                    if ($responseTime > 0 && $responseTime < 1440) { // ignora > 24h
                        $responseTimes[] = $responseTime;
                    }
                }
            } else {
                $clientMessages++;
                $lastClientMessageTime = $messageTime;
            }
        }
        
        $avgResponseTime = !empty($responseTimes) ? array_sum($responseTimes) / count($responseTimes) : 0;
        $maxResponseTime = !empty($responseTimes) ? max($responseTimes) : 0;
        $conversationDuration = ($lastMessageTime - $firstMessageTime) / 60; // em minutos
        
        return [
            'total_messages' => count($messages),
            'agent_messages' => $agentMessages,
            'client_messages' => $clientMessages,
            'avg_response_time' => round($avgResponseTime, 1),
            'max_response_time' => round($maxResponseTime, 1),
            'conversation_duration' => round($conversationDuration, 1),
            'response_count' => count($responseTimes)
        ];
    }
    
    /**
     * Formatar minutos em texto legível
     */
    private static function formatMinutes(float $minutes): string
    {
        if ($minutes < 1) {
            return round($minutes * 60) . " segundos";
        } elseif ($minutes < 60) {
            return round($minutes, 1) . " minutos";
        } else {
            $hours = floor($minutes / 60);
            $mins = round($minutes % 60);
            return "{$hours}h {$mins}min";
        }
    }
    
    /**
     * Formatar mensagens para análise
     */
    private static function formatMessagesForAnalysis(array $messages): string
    {
        $formatted = [];
        $lastTime = null;
        
        foreach ($messages as $msg) {
            $content = $msg['content'] ?? '';
            $time = date('H:i', strtotime($msg['created_at']));
            $sender = ($msg['sender_type'] === 'agent') ? 'VENDEDOR' : 'CLIENTE';
            
            // Calcular tempo entre mensagens
            if ($lastTime !== null) {
                $diff = strtotime($msg['created_at']) - strtotime($lastTime);
                if ($diff > 600) { // > 10 minutos
                    $minutes = round($diff / 60);
                    $formatted[] = "\n[... {$minutes} minutos depois ...]\n";
                }
            }
            
            $formatted[] = "[{$time}] {$sender}: {$content}";
            $lastTime = $msg['created_at'];
        }
        
        return implode("\n", $formatted);
    }
    
    /**
     * Fazer requisição à OpenAI
     */
    private static function makeOpenAIRequest(string $apiKey, string $prompt, array $settings): array
    {
        $model = $settings['model'] ?? 'gpt-4-turbo';
        $temperature = (float)($settings['temperature'] ?? 0.3);
        
        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um especialista em análise de vendas e coaching de vendedores. Seja crítico mas construtivo. Retorne APENAS JSON válido.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object']
        ];
        
        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \Exception('Erro de conexão com OpenAI: ' . $error);
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Erro desconhecido da API OpenAI';
            throw new \Exception('Erro da API OpenAI (' . $httpCode . '): ' . $errorMessage);
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta inválida da API OpenAI');
        }
        
        return $data;
    }
    
    /**
     * Processar resposta da OpenAI
     */
    private static function parseOpenAIResponse(array $response, array $conversation, int $totalMessages, int $agentMessages, string $model): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];
        
        // Parsear JSON
        $analysisData = json_decode($content, true);
        
        if (!$analysisData || json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta da OpenAI não contém JSON válido: ' . substr($content, 0, 200));
        }
        
        $scores = $analysisData['scores'] ?? [];
        
        // Calcular nota geral (média ponderada)
        $settings = self::getSettings();
        $dimensions = $settings['dimensions'];
        $totalWeight = 0;
        $weightedSum = 0;
        
        foreach (self::DIMENSIONS as $key => $name) {
            if (($dimensions[$key]['enabled'] ?? true) && isset($scores[$key])) {
                $weight = $dimensions[$key]['weight'] ?? 1.0;
                $score = (float)$scores[$key];
                $weightedSum += $score * $weight;
                $totalWeight += $weight;
            }
        }
        
        $overallScore = $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
        
        // Calcular duração da conversa
        $firstMsg = Database::fetch("SELECT created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 1", [$conversation['id']]);
        $lastMsg = Database::fetch("SELECT created_at FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1", [$conversation['id']]);
        
        $duration = null;
        if ($firstMsg && $lastMsg) {
            $diff = strtotime($lastMsg['created_at']) - strtotime($firstMsg['created_at']);
            $duration = round($diff / 60); // minutos
        }
        
        $tokensUsed = (int)($usage['total_tokens'] ?? 0);
        $cost = self::calculateCost($model, $tokensUsed);
        
        return [
            'conversation_id' => $conversation['id'],
            'agent_id' => $conversation['agent_id'],
            
            // Scores individuais
            'proactivity_score' => isset($scores['proactivity']) ? round($scores['proactivity'], 1) : null,
            'objection_handling_score' => isset($scores['objection_handling']) ? round($scores['objection_handling'], 1) : null,
            'rapport_score' => isset($scores['rapport']) ? round($scores['rapport'], 1) : null,
            'closing_techniques_score' => isset($scores['closing_techniques']) ? round($scores['closing_techniques'], 1) : null,
            'qualification_score' => isset($scores['qualification']) ? round($scores['qualification'], 1) : null,
            'clarity_score' => isset($scores['clarity']) ? round($scores['clarity'], 1) : null,
            'value_proposition_score' => isset($scores['value_proposition']) ? round($scores['value_proposition'], 1) : null,
            'response_time_score' => isset($scores['response_time']) ? round($scores['response_time'], 1) : null,
            'follow_up_score' => isset($scores['follow_up']) ? round($scores['follow_up'], 1) : null,
            'professionalism_score' => isset($scores['professionalism']) ? round($scores['professionalism'], 1) : null,
            
            // Nota geral
            'overall_score' => round($overallScore, 2),
            
            // Análises textuais
            'strengths' => !empty($analysisData['strengths']) ? json_encode($analysisData['strengths'], JSON_UNESCAPED_UNICODE) : null,
            'weaknesses' => !empty($analysisData['weaknesses']) ? json_encode($analysisData['weaknesses'], JSON_UNESCAPED_UNICODE) : null,
            'improvement_suggestions' => !empty($analysisData['improvement_suggestions']) ? json_encode($analysisData['improvement_suggestions'], JSON_UNESCAPED_UNICODE) : null,
            'key_moments' => !empty($analysisData['key_moments']) ? json_encode($analysisData['key_moments'], JSON_UNESCAPED_UNICODE) : null,
            'detailed_analysis' => $analysisData['detailed_analysis'] ?? null,
            
            // Metadados
            'messages_analyzed' => $totalMessages,
            'agent_messages_count' => $agentMessages,
            'client_messages_count' => $totalMessages - $agentMessages,
            'conversation_duration_minutes' => $duration,
            'funnel_stage' => $conversation['stage'] ?? null,
            'conversation_value' => $conversation['estimated_value'] ?? null,
            
            // IA
            'model_used' => $model,
            'tokens_used' => $tokensUsed,
            'cost' => $cost,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Calcular custo
     */
    private static function calculateCost(string $model, int $tokens): float
    {
        $prices = [
            'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
            'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
            'gpt-4' => ['input' => 0.03, 'output' => 0.06],
            'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],
        ];
        
        $modelPrices = $prices[$model] ?? $prices['gpt-3.5-turbo'];
        
        // Assumir 60% input, 40% output
        $inputTokens = (int)($tokens * 0.6);
        $outputTokens = (int)($tokens * 0.4);
        
        $cost = ($inputTokens / 1000 * $modelPrices['input']) + ($outputTokens / 1000 * $modelPrices['output']);
        
        return round($cost, 6);
    }
    
    /**
     * Ações pós-análise
     */
    private static function postAnalysisActions(array $analysis, array $conversation, array $settings): void
    {
        try {
            // Tag de baixa performance
            if ($settings['reports']['auto_tag_low_performance'] ?? false) {
                $threshold = (float)($settings['reports']['low_performance_threshold'] ?? 2.5);
                if ($analysis['overall_score'] < $threshold) {
                    // Adicionar tag (implementar se necessário)
                    Logger::log("AgentPerformanceAnalysisService - Baixa performance detectada: {$analysis['overall_score']}");
                }
            }
            
            // Gamificação
            if (($settings['gamification']['enabled'] ?? false) && ($settings['gamification']['award_badges'] ?? false)) {
                GamificationService::checkAndAwardBadges($analysis);
            }
            
            // Coaching
            if ($settings['coaching']['enabled'] ?? false) {
                // Salvar melhores práticas
                if ($settings['coaching']['save_best_practices'] ?? false) {
                    $minScore = (float)($settings['coaching']['min_score_for_best_practice'] ?? 4.5);
                    if ($analysis['overall_score'] >= $minScore) {
                        BestPracticesService::saveBestPractice($analysis, $conversation);
                    }
                }
                
                // Auto-criar metas
                if ($settings['coaching']['auto_create_goals'] ?? false) {
                    CoachingService::autoCreateGoals($analysis);
                }
                
                // Enviar feedback
                if ($settings['coaching']['auto_send_feedback'] ?? false) {
                    CoachingService::sendFeedback($analysis, $conversation);
                }
            }
            
        } catch (\Exception $e) {
            Logger::error("AgentPerformanceAnalysisService::postAnalysisActions - Erro: " . $e->getMessage());
        }
    }
    
    /**
     * Processar conversas pendentes (cron job)
     */
    public static function processPendingConversations(): array
    {
        $settings = self::getSettings();
        
        if (!$settings['enabled']) {
            return ['processed' => 0, 'errors' => 0, 'cost' => 0];
        }
        
        // Buscar conversas pendentes
        $conversations = AgentPerformanceAnalysis::getPendingConversations(50);
        
        $processed = 0;
        $errors = 0;
        $totalCost = 0.0;
        
        foreach ($conversations as $conv) {
            // Verificar limite de custo
            if (!self::checkDailyCostLimit()) {
                Logger::log("AgentPerformanceAnalysisService::processPendingConversations - Limite de custo atingido");
                break;
            }
            
            try {
                $result = self::analyzeConversation($conv['id']);
                if ($result) {
                    $processed++;
                    $totalCost += (float)($result['cost'] ?? 0);
                }
            } catch (\Exception $e) {
                $errors++;
                Logger::error("AgentPerformanceAnalysisService::processPendingConversations - Erro na conversa {$conv['id']}: " . $e->getMessage());
            }
        }
        
        return [
            'processed' => $processed,
            'errors' => $errors,
            'cost' => $totalCost
        ];
    }
    
    /**
     * Obter análise de uma conversa
     */
    public static function getAnalysis(int $conversationId): ?array
    {
        return AgentPerformanceAnalysis::getByConversation($conversationId);
    }
    
    /**
     * Obter análises de um agente
     */
    public static function getAgentAnalyses(int $agentId, int $limit = 50, int $offset = 0): array
    {
        return AgentPerformanceAnalysis::getByAgent($agentId, $limit, $offset);
    }
    
    /**
     * Obter ranking de agentes
     */
    public static function getAgentsRanking(?string $dateFrom = null, ?string $dateTo = null, int $limit = 50): array
    {
        if (!$dateFrom) $dateFrom = date('Y-m-d', strtotime('-30 days'));
        if (!$dateTo) $dateTo = date('Y-m-d');
        
        return AgentPerformanceAnalysis::getAgentsRanking($dateFrom, $dateTo, $limit);
    }
    
    /**
     * Obter estatísticas gerais
     */
    public static function getOverallStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        return AgentPerformanceAnalysis::getOverallStats($dateFrom, $dateTo);
    }
    
    /**
     * Analisar todas as participações de uma conversa
     * Cria análises separadas para cada agente que participou
     * 
     * @param int $conversationId
     * @param bool $force Forçar re-análise
     * @return array Array de análises criadas
     */
    public static function analyzeConversationParticipations(int $conversationId, bool $force = false): array
    {
        $settings = self::getSettings();
        
        if (!$settings['enabled']) {
            Logger::log("AgentPerformanceAnalysisService::analyzeConversationParticipations - Análise desabilitada");
            return [];
        }
        
        // Verificar limite de custo
        if (!self::checkDailyCostLimit()) {
            Logger::log("AgentPerformanceAnalysisService::analyzeConversationParticipations - Limite de custo diário atingido");
            return [];
        }
        
        // Obter conversa
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            Logger::error("AgentPerformanceAnalysisService::analyzeConversationParticipations - Conversa não encontrada: {$conversationId}");
            return [];
        }
        
        // Obter participações
        $participations = \App\Models\ConversationAssignment::getConversationParticipations($conversationId);
        
        if (empty($participations)) {
            Logger::warning("AgentPerformanceAnalysisService::analyzeConversationParticipations - Nenhuma participação encontrada para conversa {$conversationId}");
            return [];
        }
        
        Logger::log("AgentPerformanceAnalysisService::analyzeConversationParticipations - Encontradas " . count($participations) . " participações para conversa {$conversationId}");
        
        $analyses = [];
        
        foreach ($participations as $participation) {
            try {
                // Verificar se já existe análise
                if (!$force) {
                    $existing = \App\Models\ConversationAssignment::getParticipationAnalysis(
                        $conversationId,
                        $participation['agent_id']
                    );
                    
                    if ($existing) {
                        Logger::log("AgentPerformanceAnalysisService::analyzeConversationParticipations - Análise já existe para agente {$participation['agent_id']} na conversa {$conversationId}");
                        $analyses[] = $existing;
                        continue;
                    }
                }
                
                // Analisar participação individual
                $analysis = self::analyzeAgentParticipation(
                    $conversation,
                    $participation['agent_id'],
                    $participation['assigned_at'],
                    $participation['removed_at'],
                    $settings
                );
                
                if ($analysis) {
                    $analyses[] = $analysis;
                    Logger::log("AgentPerformanceAnalysisService::analyzeConversationParticipations - ✅ Análise criada para agente {$participation['agent_id']} (score: {$analysis['overall_score']})");
                }
                
            } catch (\Exception $e) {
                Logger::error("AgentPerformanceAnalysisService::analyzeConversationParticipations - Erro ao analisar participação do agente {$participation['agent_id']}: " . $e->getMessage());
            }
        }
        
        return $analyses;
    }
    
    /**
     * Analisar a participação de um agente específico em uma conversa
     * Considera apenas as mensagens enviadas durante o período de participação
     * 
     * @param array $conversation Dados da conversa
     * @param int $agentId ID do agente
     * @param string|null $assignedAt Data/hora de início da participação
     * @param string|null $removedAt Data/hora de fim da participação (null = ainda ativo)
     * @param array $settings Configurações
     * @return array|null Análise criada ou null
     */
    private static function analyzeAgentParticipation(
        array $conversation,
        int $agentId,
        ?string $assignedAt,
        ?string $removedAt,
        array $settings
    ): ?array {
        try {
            Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - Analisando participação do agente {$agentId} na conversa {$conversation['id']}");
            Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - Período: " . ($assignedAt ?? 'início') . " até " . ($removedAt ?? 'fim'));
            
            // Obter TODAS as mensagens da conversa no período (incluindo do cliente)
            $allMessages = \App\Models\ConversationAssignment::getAllMessagesInParticipation(
                $conversation['id'],
                $agentId,
                $assignedAt,
                $removedAt
            );
            
            if (empty($allMessages)) {
                Logger::warning("AgentPerformanceAnalysisService::analyzeAgentParticipation - Nenhuma mensagem encontrada no período de participação");
                return null;
            }
            
            Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - Total de mensagens no período: " . count($allMessages));
            
            // Contar mensagens do agente especificamente
            $agentMessages = array_filter($allMessages, function($m) use ($agentId) {
                return $m['sender_type'] === 'agent' && $m['sender_id'] == $agentId;
            });
            
            $agentMessageCount = count($agentMessages);
            $minAgentMessages = (int)($settings['min_agent_messages'] ?? 3);
            
            if ($agentMessageCount < $minAgentMessages) {
                Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - Mensagens do agente insuficientes ({$agentMessageCount} < {$minAgentMessages})");
                return null;
            }
            
            Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - Mensagens do agente: {$agentMessageCount}");
            
            // Obter API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('API Key da OpenAI não configurada');
            }
            
            // Construir prompt específico para esta participação
            $prompt = self::buildParticipationAnalysisPrompt($allMessages, $conversation, $agentId, $assignedAt, $removedAt, $settings);
            
            // Fazer requisição à OpenAI
            $response = self::makeOpenAIRequest($apiKey, $prompt, $settings);
            
            // Processar resposta
            $analysis = self::parseOpenAIResponse($response, $conversation, count($allMessages), $agentMessageCount, $settings['model']);
            
            // **IMPORTANTE**: Definir agent_id correto na análise
            $analysis['agent_id'] = $agentId;
            $analysis['conversation_id'] = $conversation['id'];
            
            // Salvar análise
            $analysisId = AgentPerformanceAnalysis::create($analysis);
            $analysis['id'] = $analysisId;
            
            Logger::log("AgentPerformanceAnalysisService::analyzeAgentParticipation - ✅ Análise criada (ID: {$analysisId}) - Score: {$analysis['overall_score']}/5.0");
            
            // Processar ações pós-análise
            self::postAnalysisActions($analysis, $conversation, $settings);
            
            return $analysis;
            
        } catch (\Exception $e) {
            Logger::error("AgentPerformanceAnalysisService::analyzeAgentParticipation - Erro: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Construir prompt para análise de participação específica
     */
    private static function buildParticipationAnalysisPrompt(
        array $messages,
        array $conversation,
        int $agentId,
        ?string $assignedAt,
        ?string $removedAt,
        array $settings
    ): string {
        // Calcular métricas reais antes de enviar para IA
        $metrics = self::calculateConversationMetrics($messages);
        
        $history = self::formatMessagesForAnalysis($messages);
        $dimensions = $settings['dimensions'];
        
        $prompt = "Você é um especialista em análise de vendas e performance comercial.\n\n";
        $prompt .= "Analise a PARTICIPAÇÃO ESPECÍFICA de um vendedor em uma conversa de vendas.\n\n";
        $prompt .= "⚠️ IMPORTANTE: Este vendedor atendeu o cliente APENAS durante o período especificado abaixo.\n";
        $prompt .= "Avalie SOMENTE as mensagens que este vendedor enviou, desconsiderando mensagens de outros agentes.\n\n";
        
        $prompt .= "PERÍODO DE PARTICIPAÇÃO:\n";
        $prompt .= "- Início: " . ($assignedAt ?? 'Início da conversa') . "\n";
        $prompt .= "- Fim: " . ($removedAt ?? 'Fim da conversa / Ainda ativo') . "\n\n";
        
        // ADICIONAR MÉTRICAS REAIS
        $prompt .= "📊 MÉTRICAS CALCULADAS DESTA PARTICIPAÇÃO:\n";
        $prompt .= "- Total de mensagens: {$metrics['total_messages']}\n";
        $prompt .= "- Mensagens do vendedor: {$metrics['agent_messages']}\n";
        $prompt .= "- Mensagens do cliente: {$metrics['client_messages']}\n";
        $prompt .= "- Tempo médio de resposta do vendedor: " . self::formatMinutes($metrics['avg_response_time']) . "\n";
        $prompt .= "- Tempo máximo de resposta: " . self::formatMinutes($metrics['max_response_time']) . "\n";
        $prompt .= "- Duração desta participação: " . self::formatMinutes($metrics['conversation_duration']) . "\n\n";
        
        // ADICIONAR BENCHMARKS
        $prompt .= "📋 BENCHMARKS DE REFERÊNCIA:\n";
        $prompt .= "Tempo de Resposta:\n";
        $prompt .= "  • 5.0 = EXCELENTE (< 3 minutos em média)\n";
        $prompt .= "  • 4.0 = BOM (3-5 minutos em média)\n";
        $prompt .= "  • 3.0 = ACEITÁVEL (5-10 minutos em média)\n";
        $prompt .= "  • 2.0 = PRECISA MELHORAR (10-20 minutos em média)\n";
        $prompt .= "  • 1.0 = CRÍTICO (> 20 minutos em média)\n\n";
        
        $prompt .= "Follow-up (Persistência e Ir Atrás):\n";
        $prompt .= "  • 5.0 = Cliente sumiu/enrolou e vendedor retornou MÚLTIPLAS vezes com persistência profissional\n";
        $prompt .= "  • 4.0 = Cliente disse 'vou pensar' e vendedor retornou cobrando posicionamento\n";
        $prompt .= "  • 3.0 = Vendedor tentou reativar conversa pelo menos uma vez\n";
        $prompt .= "  • 2.0 = Vendedor apenas mencionou 'qualquer coisa me chama' mas não foi atrás\n";
        $prompt .= "  • 1.0 = Vendedor deixou conversa morrer, não insistiu, desistiu fácil\n\n";
        
        $prompt .= "⚠️ Follow-up NÃO é agendar reunião! É sobre PERSISTÊNCIA:\n";
        $prompt .= "  - Cliente some = Vendedor reativa?\n";
        $prompt .= "  - Cliente enrola = Vendedor insiste?\n";
        $prompt .= "  - Cliente adia = Vendedor cobra?\n";
        $prompt .= "  - Ou vendedor desiste fácil?\n\n";
        
        $prompt .= "⚠️ IMPORTANTE: Use as métricas calculadas acima para avaliar 'Tempo de Resposta'. Não invente valores!\n\n";
        
        $prompt .= "Avalie a PERFORMANCE DESTE VENDEDOR nas seguintes dimensões (nota de 0 a 5, com 1 casa decimal):\n\n";
        
        // Adicionar dimensões habilitadas
        $dimensionCount = 1;
        foreach (self::DIMENSIONS as $key => $name) {
            if ($dimensions[$key]['enabled'] ?? true) {
                $prompt .= "{$dimensionCount}. {$name} (0-5)\n";
                $prompt .= self::getDimensionCriteria($key);
                $prompt .= "\n";
                $dimensionCount++;
            }
        }
        
        // Contexto adicional
        $prompt .= "\nCONTEXTO ADICIONAL:\n";
        $prompt .= "- Status da conversa: " . ($conversation['status'] ?? 'N/A') . "\n";
        if (!empty($conversation['stage'])) {
            $prompt .= "- Estágio do funil: " . $conversation['stage'] . "\n";
        }
        if (!empty($conversation['estimated_value'])) {
            $prompt .= "- Valor estimado: R$ " . number_format($conversation['estimated_value'], 2, ',', '.') . "\n";
        }
        
        $prompt .= "\nCONVERSA (mensagens durante a participação):\n";
        $prompt .= $history;
        $prompt .= "\n\nRESPONDA EM FORMATO JSON com a seguinte estrutura:\n";
        $prompt .= self::getExpectedJsonStructure($dimensions);
        
        return $prompt;
    }
}
