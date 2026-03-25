<?php
/**
 * Service OpenAIService
 * Integração com OpenAI API para processamento de conversas com agentes de IA
 */

namespace App\Services;

use App\Models\AIAgent;
use App\Models\AITool;
use App\Models\AIConversation;
use App\Models\Setting;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Contact;
use App\Models\User;
use App\Models\Department;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\Activity;
use App\Helpers\Database;
use App\Services\ConversationAIService;
use App\Services\RAGService;
use App\Services\FeedbackDetectionService;
use App\Services\AgentMemoryService;

class OpenAIService
{
    const API_URL = 'https://api.openai.com/v1/chat/completions';
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1; // segundos

    /**
     * Log auxiliar para debug de intents (mesmo arquivo de intents da AutomationService)
     */
    private static function logIntentDebug(string $message): void
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
     * Obter API Key das configurações
     */
    private static function getApiKey(): ?string
    {
        $apiKey = Setting::get('openai_api_key');
        if (empty($apiKey)) {
            // Tentar variável de ambiente como fallback
            $apiKey = getenv('OPENAI_API_KEY') ?: null;
        }
        return $apiKey;
    }

    /**
     * Processar mensagem com agente de IA
     */
    public static function processMessage(int $conversationId, int $agentId, string $message, array $context = []): array
    {
        $startTime = microtime(true);
        
        // Debug log
        \App\Helpers\ConversationDebug::aiAgent($conversationId, "processMessage iniciado", [
            'agentId' => $agentId,
            'message' => substr($message, 0, 200),
            'contextKeys' => array_keys($context)
        ]);
        
        try {
            // Obter agente
            $agent = AIAgent::find($agentId);
            if (!$agent || !$agent['enabled']) {
                \App\Helpers\ConversationDebug::error($conversationId, 'processMessage', 'Agente não encontrado ou inativo', ['agentId' => $agentId]);
                throw new \Exception('Agente de IA não encontrado ou inativo');
            }
            
            \App\Helpers\ConversationDebug::aiAgent($conversationId, "Agente encontrado: {$agent['name']}", [
                'model' => $agent['model'],
                'temperature' => $agent['temperature']
            ]);

            // Verificar rate limiting e limites de custo
            $costControlCheck = \App\Services\AICostControlService::canProcessMessage($agentId);
            if (!$costControlCheck['allowed']) {
                \App\Helpers\ConversationDebug::error($conversationId, 'processMessage', 'Rate limit/custo atingido', $costControlCheck);
                throw new \Exception($costControlCheck['reason'] ?? 'Limite de rate ou custo atingido');
            }

            // Obter API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                \App\Helpers\ConversationDebug::error($conversationId, 'processMessage', 'API Key não configurada');
                throw new \Exception('API Key da OpenAI não configurada. Configure em Configurações > OpenAI');
            }

            // Obter tools do agente
            $tools = AIAgent::getTools($agentId);
            $functions = [];
            $toolDescriptions = []; // Para incluir no prompt
            foreach ($tools as $tool) {
                $functionSchema = is_string($tool['function_schema']) 
                    ? json_decode($tool['function_schema'], true) 
                    : ($tool['function_schema'] ?? []);
                
                if (!empty($functionSchema)) {
                    // Corrigir schema se properties for array vazio (deveria ser objeto)
                    $functionSchema = self::normalizeToolSchema($functionSchema);
                    if (($tool['tool_type'] ?? '') === 'woocommerce') {
                        $wcHint = ' Invocar somente quando o cliente pediu dados ou ação sobre pedido, produto, entrega ou loja; não usar em saudações (oi/olá) nem conversa genérica.';
                        if (isset($functionSchema['function']['description']) && is_string($functionSchema['function']['description'])) {
                            $functionSchema['function']['description'] .= $wcHint;
                        } elseif (isset($functionSchema['description']) && is_string($functionSchema['description'])) {
                            $functionSchema['description'] .= $wcHint;
                        }
                    }
                    $functions[] = $functionSchema;
                    
                    // Extrair nome e descrição da tool para o prompt
                    $funcData = $functionSchema['function'] ?? $functionSchema;
                    $toolName = $funcData['name'] ?? 'unknown';
                    $toolDesc = $funcData['description'] ?? 'Sem descrição';
                    $toolDescriptions[] = "- **{$toolName}**: {$toolDesc}";
                }
            }
            
            \App\Helpers\ConversationDebug::aiAgent($conversationId, "Tools carregadas: " . count($functions), [
                'tools' => array_map(fn($f) => $f['function']['name'] ?? $f['name'] ?? 'unknown', $functions),
                'tools_full' => $functions
            ]);

            $toolTypesAttached = [];
            foreach ($tools as $t) {
                $tt = $t['tool_type'] ?? '';
                if ($tt !== '') {
                    $toolTypesAttached[$tt] = true;
                }
            }

            // Construir mensagens do histórico (passando tools para incluir no prompt)
            $messages = self::buildMessages($agent, $message, $context, $toolDescriptions, array_keys($toolTypesAttached));

            // Preparar payload
            $payload = [
                'model' => $agent['model'] ?? 'gpt-4',
                'messages' => $messages,
                'temperature' => (float)($agent['temperature'] ?? 0.7),
                'max_tokens' => (int)($agent['max_tokens'] ?? 2000),
            ];

            // Adicionar tools se houver
            if (!empty($functions)) {
                $payload['tools'] = array_map(function($func) {
                    // Se já tem o wrapper {type: function, function: {...}}, usar diretamente
                    if (isset($func['type']) && $func['type'] === 'function' && isset($func['function'])) {
                        return $func;
                    }
                    // Senão, adicionar o wrapper
                    return ['type' => 'function', 'function' => $func];
                }, $functions);
                
                // IMPORTANTE: Usar 'auto' permite que a IA escolha, mas com instruções claras no prompt
                // Use 'required' apenas se quiser FORÇAR o uso de uma tool
                $payload['tool_choice'] = 'auto';
            }

            // Fazer requisição à API
            \App\Helpers\ConversationDebug::openAIRequest($conversationId, 'chat/completions', [
                'model' => $payload['model'],
                'messages_count' => count($payload['messages']),
                'tools_count' => count($functions),
                'temperature' => $payload['temperature']
            ]);
            
            $response = self::makeRequest($apiKey, $payload);
            
            // Processar resposta
            $assistantMessage = $response['choices'][0]['message'] ?? null;
            if (!$assistantMessage) {
                \App\Helpers\ConversationDebug::error($conversationId, 'OpenAI', 'Resposta inválida', $response);
                throw new \Exception('Resposta inválida da API OpenAI');
            }

            $content = $assistantMessage['content'] ?? '';
            $toolCalls = $assistantMessage['tool_calls'] ?? null;
            
            \App\Helpers\ConversationDebug::openAIResponse($conversationId, 'chat/completions', [
                'content_preview' => substr($content, 0, 200),
                'tool_calls' => $toolCalls ? count($toolCalls) : 0,
                'tokens' => $response['usage'] ?? []
            ], $response['usage']['total_tokens'] ?? 0);

            // Se há tool calls, executar e reenviar
            if (!empty($toolCalls)) {
                \App\Helpers\ConversationDebug::aiAgent($conversationId, "OpenAI solicitou " . count($toolCalls) . " tool calls");
                $functionResults = self::executeToolCalls($toolCalls, $conversationId, $agentId, $context);
                
                // Verificar se alguma tool retornou resposta direta (use_raw_response)
                $directResponse = null;
                foreach ($functionResults as $result) {
                    if (!empty($result['use_raw_response']) && !empty($result['raw_message'])) {
                        $directResponse = $result['raw_message'];
                        break;
                    }
                }
                
                // Se há resposta direta, usar sem reenviar para OpenAI
                if ($directResponse !== null) {
                    $content = $directResponse;
                    // Não contabilizar tokens adicionais da OpenAI
                } else {
                    // Fluxo normal: reenviar para OpenAI com resultados
                // Adicionar mensagem do assistente com tool calls
                $messages[] = $assistantMessage;
                
                // Adicionar resultados das tools
                foreach ($functionResults as $result) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $result['tool_call_id'],
                        'content' => json_encode($result['result'], JSON_UNESCAPED_UNICODE)
                    ];
                }

                // Reenviar para OpenAI com resultados
                $payload['messages'] = $messages;
                $response = self::makeRequest($apiKey, $payload);
                
                $assistantMessage = $response['choices'][0]['message'] ?? null;
                $content = $assistantMessage['content'] ?? '';
                
                // Adicionar tokens adicionais
                $usage = $response['usage'] ?? [];
                $tokensUsed += $usage['total_tokens'] ?? 0;
                $tokensPrompt += $usage['prompt_tokens'] ?? 0;
                $tokensCompletion += $usage['completion_tokens'] ?? 0;
                }
            }

            // Calcular tokens e custo
            $usage = $response['usage'] ?? [];
            $tokensUsed = $usage['total_tokens'] ?? 0;
            $tokensPrompt = $usage['prompt_tokens'] ?? 0;
            $tokensCompletion = $usage['completion_tokens'] ?? 0;
            $cost = self::calculateCost($agent['model'] ?? 'gpt-4', $tokensPrompt, $tokensCompletion);

            // Registrar ou atualizar log de conversa
            $executionTime = (microtime(true) - $startTime) * 1000; // ms
            
            $aiConversation = AIConversation::whereFirst('conversation_id', '=', $conversationId);
            if ($aiConversation) {
                // Atualizar conversa existente
                AIConversation::updateStats(
                    $aiConversation['id'],
                    $tokensUsed,
                    $tokensPrompt,
                    $tokensCompletion,
                    $cost
                );
                
                // Adicionar mensagem ao histórico
                AIConversation::addMessage($aiConversation['id'], [
                    'role' => 'user',
                    'content' => $message,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                AIConversation::addMessage($aiConversation['id'], [
                    'role' => 'assistant',
                    'content' => $content,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Criar nova conversa de IA
                AIConversation::create([
                    'conversation_id' => $conversationId,
                    'ai_agent_id' => $agentId,
                    'messages' => json_encode([
                        [
                            'role' => 'user',
                            'content' => $message,
                            'timestamp' => date('Y-m-d H:i:s')
                        ],
                        [
                            'role' => 'assistant',
                            'content' => $content,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]
                    ], JSON_UNESCAPED_UNICODE),
                    'tokens_used' => $tokensUsed,
                    'tokens_prompt' => $tokensPrompt,
                    'tokens_completion' => $tokensCompletion,
                    'cost' => $cost,
                    'status' => 'active',
                    'metadata' => json_encode([
                        'execution_time_ms' => $executionTime,
                        'model' => $agent['model'] ?? 'gpt-4'
                    ], JSON_UNESCAPED_UNICODE)
                ]);
            }

            // Detectar automaticamente se resposta foi inadequada e registrar feedback
            try {
                $lastMessage = Message::whereFirst('conversation_id', '=', $conversationId);
                $messageId = $lastMessage['id'] ?? null;
                
                if ($messageId && \App\Helpers\PostgreSQL::isAvailable()) {
                    FeedbackDetectionService::detectAndRegister(
                        $agentId,
                        $conversationId,
                        $messageId,
                        $message,
                        $content
                    );
                }
            } catch (\Exception $feedbackError) {
                // Não interromper fluxo se detecção de feedback falhar
                Logger::warning("OpenAIService::processMessage - Erro ao detectar feedback: " . $feedbackError->getMessage());
            }

            // Extrair e salvar memórias automaticamente (após algumas mensagens)
            try {
                if (\App\Helpers\PostgreSQL::isAvailable()) {
                    // Extrair memórias apenas após 3+ mensagens na conversa
                    $messageCount = count(Message::where('conversation_id', '=', $conversationId));
                    if ($messageCount >= 3 && $messageCount % 5 === 0) { // A cada 5 mensagens
                        AgentMemoryService::extractAndSave($agentId, $conversationId);
                    }
                }
            } catch (\Exception $memoryError) {
                // Não interromper fluxo se extração de memória falhar
                Logger::warning("OpenAIService::processMessage - Erro ao extrair memórias: " . $memoryError->getMessage());
            }

            return [
                'content' => $content,
                'tokens_used' => $tokensUsed,
                'tokens_prompt' => $tokensPrompt,
                'tokens_completion' => $tokensCompletion,
                'cost' => $cost,
                'execution_time_ms' => $executionTime
            ];

        } catch (\Exception $e) {
            error_log("Erro ao processar mensagem com OpenAI: " . $e->getMessage());
            \App\Helpers\ConversationDebug::error($conversationId, 'OpenAI::processMessage', $e->getMessage(), [
                'trace' => substr($e->getTraceAsString(), 0, 1000)
            ]);
            throw $e;
        }
    }

    /**
     * Construir array de mensagens para a API
     */
    private static function buildMessages(array $agent, string $userMessage, array $context, array $toolDescriptions = [], array $attachedToolTypes = []): array
    {
        $messages = [];

        // Mensagem do sistema (prompt do agente)
        $systemPrompt = $agent['prompt'];
        $hasWooCommerceTools = in_array('woocommerce', $attachedToolTypes, true);
        
        // IMPORTANTE: Adicionar instruções sobre tools disponíveis
        if (!empty($toolDescriptions)) {
            $systemPrompt .= "\n\n## FERRAMENTAS DISPONÍVEIS\n\n";
            $systemPrompt .= "Você tem acesso às seguintes ferramentas (tools). Elas existem para quando forem **realmente necessárias** — não é obrigatório usá-las em toda mensagem.\n\n";
            $systemPrompt .= implode("\n", $toolDescriptions);

            if ($hasWooCommerceTools) {
                $systemPrompt .= "\n\n### Uso de ferramentas WooCommerce (pedidos / produtos / loja)\n";
                $systemPrompt .= "- Chame essas ferramentas **somente** quando o cliente pedir algo objetivo sobre **pedido, compra, entrega, rastreamento, produto, estoque, preço ou cupom**, ou quando já houver **dados concretos** (ex.: número do pedido, SKU) na mensagem.\n";
                $systemPrompt .= "- **Não** chame WooCommerce em **saudações** (\"oi\", \"olá\", \"bom dia\"), agradecimentos, conversa geral ou quando o cliente ainda não pediu nada sobre a loja.\n";
                $systemPrompt .= "- Se precisar de um dado que a ferramenta exige (ex.: ID do pedido) e o cliente não informou, **pergunte em texto** antes de chamar a ferramenta.\n";
                $systemPrompt .= "- Se não houver pedido explícito relacionado à loja, responda normalmente **sem** tool calls.\n";
            }
            
            // Verificar se agente tem preferência por usar tools
            $settings = is_string($agent['settings'] ?? '') 
                ? json_decode($agent['settings'], true) 
                : ($agent['settings'] ?? []);
            $preferTools = !empty($settings['prefer_tools']);
            
            if ($preferTools) {
                $systemPrompt .= "\n\n**INSTRUÇÃO CRÍTICA**: Use as ferramentas sempre que a solicitação do cliente envolver **dados ou ações do sistema** que essas ferramentas cobrem. Não invente fatos verificáveis por ferramenta quando o cliente pediu essa verificação.";
                if ($hasWooCommerceTools) {
                    $systemPrompt .= " **Exceção obrigatória:** ferramentas WooCommerce — aplique as regras da seção \"Uso de ferramentas WooCommerce\" acima (nunca em saudação ou conversa genérica).";
                } else {
                    $systemPrompt .= " Se houver dúvida razoável se uma ferramenta resolve o pedido, prefira usá-la.";
                }
            } else {
                $systemPrompt .= "\n\n**IMPORTANTE (modo padrão — preferência por tools desligada)**:\n";
                $systemPrompt .= "- A resposta **padrão** é em **texto direto**, sem chamar ferramentas, principalmente em saudações e conversa leve.\n";
                $systemPrompt .= "- Chame uma ferramenta **somente** quando o cliente pedir **claramente** uma consulta ou ação que só ela pode resolver **e** você tiver (ou puder pedir e depois obter) os dados necessários.\n";
                $systemPrompt .= "- **Não** use ferramentas por precaução, por hábito ou porque estão listadas; omitir tool call é correto na maioria das mensagens curtas ou genéricas.\n";
                $systemPrompt .= "- Quando o cliente **explicitamente** pedir uma verificação no sistema, aí sim use a ferramenta adequada — sem inventar o resultado.";
            }
        }
        
        // Adicionar contexto se disponível
        if (!empty($context['contact'])) {
            $systemPrompt .= "\n\nInformações do contato:\n";
            if (!empty($context['contact']['name'])) {
                $systemPrompt .= "- Nome: " . $context['contact']['name'] . "\n";
            }
            if (!empty($context['contact']['email'])) {
                $systemPrompt .= "- Email: " . $context['contact']['email'] . "\n";
            }
            if (!empty($context['contact']['phone'])) {
                $systemPrompt .= "- Telefone: " . $context['contact']['phone'] . "\n";
            }
        }

        if (!empty($context['conversation'])) {
            $systemPrompt .= "\n\nInformações da conversa:\n";
            if (!empty($context['conversation']['subject'])) {
                $systemPrompt .= "- Assunto: " . $context['conversation']['subject'] . "\n";
            }
            if (!empty($context['conversation']['status'])) {
                $systemPrompt .= "- Status: " . $context['conversation']['status'] . "\n";
            }
        }

        // Buscar histórico de mensagens da conversa (últimas 20 para contexto, condensadas)
        $conversationId = $context['conversation']['id'] ?? 0;
        $conversationMessages = [];
        if ($conversationId) {
            $sql = "SELECT sender_type, content FROM messages 
                    WHERE conversation_id = ? 
                    ORDER BY id DESC 
                    LIMIT 20";
            $conversationMessages = Database::fetchAll($sql, [$conversationId]);
            $conversationMessages = array_reverse($conversationMessages); // ordem cronológica
        }

        // Recuperar trechos mais antigos relevantes (RAG leve por palavras-chave)
        $relevantSnippets = [];
        if ($conversationId) {
            $relevantSnippets = self::getRelevantOldMessages($conversationId, $userMessage, 3, 50);
        }

        // Montar contexto condensado (rolling light) das últimas mensagens
        $condensed = [];
        foreach ($conversationMessages as $msg) {
            $role = $msg['sender_type'] ?? 'user';
            $txt = trim($msg['content'] ?? '');
            if ($txt === '') continue;
            // limitar cada trecho para evitar estouro de tokens
            $condensed[] = strtoupper($role) . ': ' . mb_substr($txt, 0, 220);
        }
        if (!empty($condensed)) {
            $systemPrompt .= "\n\nContexto recente (últimas mensagens):\n" . implode("\n", $condensed);
        }

        if (!empty($relevantSnippets)) {
            $systemPrompt .= "\n\nTrechos relevantes (histórico anterior):\n" . implode("\n", $relevantSnippets);
        }

        // Adicionar contexto RAG (Knowledge Base + Memórias) se disponível
        $agentId = $agent['id'] ?? 0;
        if ($agentId && \App\Helpers\PostgreSQL::isAvailable()) {
            try {
                $ragContext = RAGService::getFullContext($agentId, $userMessage, $conversationId);
                if (!empty($ragContext)) {
                    $systemPrompt .= $ragContext;
                    \App\Helpers\ConversationDebug::aiAgent($conversationId, "Contexto RAG adicionado ao prompt", [
                        'context_length' => strlen($ragContext)
                    ]);
                }
            } catch (\Exception $e) {
                // Log erro mas não interrompe o fluxo
                \App\Helpers\ConversationDebug::error($conversationId, 'RAG', 'Erro ao buscar contexto RAG: ' . $e->getMessage());
                Logger::warning("OpenAIService::buildMessages - Erro ao buscar contexto RAG: " . $e->getMessage());
            }
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt
        ];

        // Adicionar mensagens do histórico bruto recente (curta janela: últimas 12)
        $recentWindow = array_slice($conversationMessages, -12);
        foreach ($recentWindow as $msg) {
            $role = $msg['sender_type'] === 'contact' ? 'user' : 'assistant';
            $content = trim($msg['content'] ?? '');
            if ($content === '') continue;
            $messages[] = [
                'role' => $role,
                'content' => $content
            ];
        }

        // Adicionar mensagem atual do usuário
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage
        ];

        return $messages;
    }

    /**
     * RAG leve: busca trechos mais antigos por palavras-chave do texto atual.
     * - Evita novas chamadas de embedding
     * - Limita a consultas simples (LIKE) e filtra no PHP
     */
    private static function getRelevantOldMessages(int $conversationId, string $query, int $limit = 3, int $offsetRecent = 50): array
    {
        // Extrair palavras significativas
        $tokens = preg_split('/\s+/', mb_strtolower($query));
        $tokens = array_filter($tokens, function ($t) {
            return mb_strlen($t) >= 4; // ignorar palavras muito curtas
        });
        if (empty($tokens)) {
            return [];
        }

        // Buscar mensagens mais antigas (além da janela recente)
        $placeholders = implode(',', array_fill(0, count($tokens), '?'));
        // Pegar um lote de mensagens antigas (ignorando as últimas offsetRecent)
        $sql = "SELECT sender_type, content FROM messages 
                WHERE conversation_id = ? 
                AND id <= (SELECT IFNULL(MAX(id) - ?, 0) FROM messages WHERE conversation_id = ?)
                ORDER BY id DESC
                LIMIT 200";
        $rows = Database::fetchAll($sql, [$conversationId, $offsetRecent, $conversationId]);

        $ranked = [];
        foreach ($rows as $row) {
            $text = mb_strtolower($row['content'] ?? '');
            if ($text === '') continue;
            $score = 0;
            foreach ($tokens as $tk) {
                if (strpos($text, $tk) !== false) {
                    $score++;
                }
            }
            if ($score > 0) {
                $role = $row['sender_type'] ?? 'user';
                $ranked[] = [
                    'score' => $score,
                    'snippet' => strtoupper($role) . ': ' . mb_substr($row['content'], 0, 240)
                ];
            }
        }

        if (empty($ranked)) {
            return [];
        }

        // Ordenar por score desc e pegar top-K
        usort($ranked, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice(array_map(fn($r) => $r['snippet'], $ranked), 0, $limit);
    }

    /**
     * Fazer requisição à API OpenAI
     */
    private static function makeRequest(string $apiKey, array $payload, int $retry = 0): array
    {
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
            
            // Rate limit - tentar novamente
            if ($httpCode === 429 && $retry < self::MAX_RETRIES) {
                sleep(self::RETRY_DELAY * ($retry + 1));
                return self::makeRequest($apiKey, $payload, $retry + 1);
            }
            
            throw new \Exception('Erro da API OpenAI (' . $httpCode . '): ' . $errorMessage);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta inválida da API OpenAI');
        }

        return $data;
    }

    /**
     * Executar tool calls retornadas pela IA
     */
    private static function executeToolCalls(array $toolCalls, int $conversationId, int $agentId, array $context): array
    {
        $results = [];

        \App\Helpers\Logger::aiTools("[TOOL EXECUTION] Iniciando execução de " . count($toolCalls) . " tool calls para conversationId={$conversationId}, agentId={$agentId}");

        $agentTools = AIAgent::getTools($agentId);
        $agentToolRowByToolId = [];
        foreach ($agentTools as $row) {
            $agentToolRowByToolId[(int)($row['id'] ?? 0)] = $row;
        }

        foreach ($toolCalls as $call) {
            $toolCallId = $call['id'] ?? null;
            $functionName = $call['function']['name'] ?? null;
            $functionArguments = json_decode($call['function']['arguments'] ?? '{}', true);

            \App\Helpers\Logger::aiTools("[TOOL EXECUTION] Tool Call: id={$toolCallId}, function={$functionName}, args=" . json_encode($functionArguments));

            \App\Helpers\ConversationDebug::toolCall($conversationId, $functionName ?? 'unknown', $functionArguments);

            if (!$functionName || !$toolCallId) {
                \App\Helpers\Logger::aiTools("[TOOL EXECUTION ERROR] Tool call sem nome ou ID");
                \App\Helpers\ConversationDebug::error($conversationId, 'executeToolCalls', 'Tool call sem nome ou ID');
                continue;
            }

            try {
                // Buscar tool pelo nome da function
                $tool = AITool::findBySlug($functionName);
                \App\Helpers\Logger::aiTools("[TOOL EXECUTION] Tool encontrada: " . ($tool ? "ID={$tool['id']}, name='{$tool['name']}', slug='{$tool['slug']}', tipo={$tool['tool_type']}" : "NÃO ENCONTRADA para functionName='{$functionName}'"));
                
                if (!$tool || !$tool['enabled']) {
                    \App\Helpers\Logger::aiTools("[TOOL EXECUTION ERROR] Tool não encontrada ou inativa: {$functionName}");
                    \App\Helpers\ConversationDebug::toolResponse($conversationId, $functionName, 'Tool não encontrada ou inativa', false);
                    $results[] = [
                        'tool_call_id' => $toolCallId,
                        'name' => $functionName,
                        'result' => ['error' => 'Desculpe, a ferramenta solicitada não está disponível no momento.']
                    ];
                    continue;
                }

                // Verificar se tool está atribuída ao agente e obter config específica do vínculo (ai_agent_tools)
                $assignedRow = $agentToolRowByToolId[(int)$tool['id']] ?? null;
                $toolAssigned = $assignedRow !== null;

                \App\Helpers\Logger::aiTools("[TOOL EXECUTION] Tool atribuída ao agente: " . ($toolAssigned ? "SIM" : "NÃO"));

                if (!$toolAssigned) {
                    \App\Helpers\Logger::aiTools("[TOOL EXECUTION ERROR] Tool não atribuída ao agente: {$functionName}");
                    \App\Helpers\ConversationDebug::toolResponse($conversationId, $functionName, 'Tool não atribuída a este agente', false);
                    $results[] = [
                        'tool_call_id' => $toolCallId,
                        'name' => $functionName,
                        'result' => ['error' => 'Tool não atribuída a este agente']
                    ];
                    continue;
                }

                // Mesclar config da tool global com a config por agente (credenciais WooCommerce, N8N, etc. ficam no vínculo)
                $toolForExec = $tool;
                $toolForExec['config'] = self::mergeAgentToolExecutionConfig(
                    $tool['config'] ?? null,
                    $assignedRow['agent_tool_config'] ?? null
                );

                // Executar tool
                $result = self::executeTool($toolForExec, $functionArguments, $conversationId, $context);
                
                \App\Helpers\ConversationDebug::toolResponse($conversationId, $functionName, $result, !isset($result['error']));

                // Registrar uso da tool
                $aiConversation = AIConversation::whereFirst('conversation_id', '=', $conversationId);
                if ($aiConversation) {
                    AIConversation::logToolUsage(
                        $aiConversation['id'],
                        $functionName,
                        $functionArguments,
                        $result
                    );
                }

                $results[] = [
                    'tool_call_id' => $toolCallId,
                    'name' => $functionName,
                    'result' => $result
                ];

            } catch (\Exception $e) {
                \App\Helpers\Logger::aiTools("[TOOL EXECUTION ERROR] Erro ao executar tool {$functionName}: " . $e->getMessage());
                \App\Helpers\Logger::aiTools("[TOOL EXECUTION ERROR] Stack trace: " . $e->getTraceAsString());
                $results[] = [
                    'tool_call_id' => $toolCallId,
                    'name' => $functionName,
                    'result' => [
                        'error' => 'Desculpe, não consegui executar esta ação no momento.',
                        'details' => $e->getMessage(),
                        'fallback_message' => 'Por favor, informe ao cliente que houve um problema temporário ao buscar as informações e peça para tentar novamente em alguns instantes ou contatar o suporte.'
                    ]
                ];
            }
        }

        \App\Helpers\Logger::aiTools("[TOOL EXECUTION] Finalizou execução de tools. Total de resultados: " . count($results));

        return $results;
    }

    /**
     * Decodifica JSON de config de tool (ai_tools ou ai_agent_tools).
     */
    private static function decodeToolConfigJson($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Base: config da tool (ai_tools). Sobreposição: config do vínculo agente–tool (ai_agent_tools).
     */
    private static function mergeAgentToolExecutionConfig($toolConfig, $agentLinkConfig): array
    {
        $base = self::decodeToolConfigJson($toolConfig);
        $overlay = self::decodeToolConfigJson($agentLinkConfig);
        if ($overlay === []) {
            return $base;
        }
        if ($base === []) {
            return $overlay;
        }
        return array_merge($base, $overlay);
    }

    /**
     * Nome da função usado na invocação (igual ao enviado pela OpenAI): function_schema, depois slug, depois name.
     */
    private static function getToolInvocationFunctionName(array $tool): string
    {
        $schema = $tool['function_schema'] ?? null;
        if (is_string($schema)) {
            $schema = json_decode($schema, true) ?: [];
        }
        if (is_array($schema)) {
            $n = $schema['function']['name'] ?? $schema['name'] ?? '';
            if (is_string($n) && trim($n) !== '') {
                return trim($n);
            }
        }
        $slug = trim((string)($tool['slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }
        return trim((string)($tool['name'] ?? ''));
    }

    /**
     * Alinha nomes vindos do schema ("Buscar Pedidos Woocommerce") aos cases em snake_case.
     */
    private static function normalizeWooCommerceFunctionName(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/\s+/', '_', $n);
        return preg_replace('/_+/', '_', $n);
    }

    /**
     * Mapeia slugs customizados (ex.: buscar_pedidos_personizi) para o handler interno correspondente.
     * Ordem: padrões mais específicos antes dos genéricos (buscar_pedidos antes de buscar_pedido).
     */
    private static function canonicalizeWooCommerceFunctionName(string $normalizedName): string
    {
        $n = $normalizedName;
        if (preg_match('/^buscar_pedidos[_a-z0-9]*$/', $n)) {
            return 'buscar_pedidos_woocommerce';
        }
        if (preg_match('/^listar_pedidos[_a-z0-9]*$/', $n)) {
            return 'listar_pedidos_cliente_woocommerce';
        }
        if (preg_match('/^buscar_pedido[_a-z0-9]*$/', $n)) {
            return 'buscar_pedido_woocommerce';
        }
        if (preg_match('/^buscar_produto[_a-z0-9]*$/', $n)) {
            return 'buscar_produto_woocommerce';
        }
        if (preg_match('/^criar_pedido[_a-z0-9]*$/', $n)) {
            return 'criar_pedido_woocommerce';
        }
        if (preg_match('/^atualizar_status_pedido[_a-z0-9]*$/', $n)) {
            return 'atualizar_status_pedido';
        }
        return $n;
    }

    /**
     * Executar uma tool específica
     */
    private static function executeTool(array $tool, array $arguments, int $conversationId, array $context): array
    {
        $toolType = $tool['tool_type'] ?? '';
        $config = is_string($tool['config']) 
            ? json_decode($tool['config'], true) 
            : ($tool['config'] ?? []);

        switch ($toolType) {
            case 'system':
                return self::executeSystemTool($tool, $arguments, $conversationId, $context);
            
            case 'followup':
                return self::executeFollowupTool($tool, $arguments, $conversationId, $context);
            
            case 'woocommerce':
                return self::executeWooCommerceTool($tool, $arguments, $config);
            
            case 'database':
                return self::executeDatabaseTool($tool, $arguments, $config);
            
            case 'n8n':
                return self::executeN8NTool($tool, $arguments, $config, $conversationId, $context);
            
            case 'api':
                return self::executeAPITool($tool, $arguments, $config);
            
            case 'document':
                return self::executeDocumentTool($tool, $arguments, $config);
            
            case 'human_escalation':
                return self::executeHumanEscalationTool($tool, $arguments, $config, $conversationId, $context);
            
            case 'funnel_stage':
                return self::executeFunnelStageTool($tool, $arguments, $config, $conversationId, $context);
            
            case 'funnel_stage_smart':
                return self::executeFunnelStageSmartTool($tool, $arguments, $config, $conversationId, $context);
            
            default:
                return ['error' => 'Tipo de tool não suportado: ' . $toolType];
        }
    }

    /**
     * Executar System Tools
     */
    private static function executeSystemTool(array $tool, array $arguments, int $conversationId, array $context): array
    {
        // IMPORTANTE: Usar slug ao invés de name para identificar a tool
        $functionName = $tool['slug'] ?? $tool['name'] ?? '';
        
        \App\Helpers\Logger::aiTools("[SYSTEM TOOL] Executando: slug='{$functionName}', name='{$tool['name']}', id={$tool['id']}");

        switch ($functionName) {
            case 'buscar_conversas_anteriores':
                $contactId = $context['conversation']['contact_id'] ?? null;
                if (!$contactId) {
                    return ['error' => 'ID do contato não encontrado'];
                }
                
                $sql = "SELECT * FROM conversations 
                        WHERE contact_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5";
                $conversations = Database::fetchAll($sql, [$contactId]);
                
                return [
                    'conversations' => array_map(function($c) {
                        return [
                            'id' => $c['id'],
                            'subject' => $c['subject'],
                            'status' => $c['status'],
                            'created_at' => $c['created_at']
                        ];
                    }, $conversations)
                ];

            case 'buscar_informacoes_contato':
                $contactId = $context['conversation']['contact_id'] ?? null;
                if (!$contactId) {
                    return ['error' => 'ID do contato não encontrado'];
                }
                
                $contact = Contact::find($contactId);
                if (!$contact) {
                    return ['error' => 'Contato não encontrado'];
                }
                
                return [
                    'id' => $contact['id'],
                    'name' => $contact['name'],
                    'email' => $contact['email'],
                    'phone' => $contact['phone'],
                    'custom_attributes' => $contact['custom_attributes'] ?? []
                ];

            case 'adicionar_tag':
            case 'adicionar_tag_conversa':
                // Aceitar tag_id ou tag (nome)
                $tagId = $arguments['tag_id'] ?? null;
                $tagName = $arguments['tag'] ?? null;
                $autoCreate = $config['auto_create_tag'] ?? false;
                
                if (!$tagId && !$tagName) {
                    return ['error' => 'ID ou nome da tag não fornecido'];
                }
                
                // Se forneceu nome, buscar ID
                if (!$tagId && $tagName) {
                    $tag = \App\Models\Tag::whereFirst('name', '=', $tagName);
                    
                    if (!$tag) {
                        if ($autoCreate) {
                            // Criar tag automaticamente
                            $tagId = \App\Services\TagService::create([
                                'name' => $tagName,
                                'color' => '#' . substr(md5($tagName), 0, 6) // Cor aleatória baseada no nome
                            ]);
                        } else {
                            return ['error' => 'Tag não encontrada: ' . $tagName . '. Tags disponíveis devem ser criadas previamente.'];
                        }
                    } else {
                        $tagId = $tag['id'];
                    }
                }
                
                try {
                    \App\Services\TagService::addToConversation($conversationId, (int)$tagId);
                    return ['success' => true, 'message' => 'Tag adicionada com sucesso'];
                } catch (\Exception $e) {
                    return ['error' => 'Erro ao adicionar tag: ' . $e->getMessage()];
                }

            case 'mover_para_estagio':
                $stageId = $arguments['stage_id'] ?? null;
                if (!$stageId) {
                    return ['error' => 'ID do estágio não fornecido'];
                }
                
                $keepAgent = $config['keep_agent'] ?? true;
                $triggerAutomations = $config['trigger_automations'] ?? true;
                $addNote = $config['add_note'] ?? true;
                
                try {
                    // Buscar informações do estágio
                    $stage = \App\Models\FunnelStage::find((int)$stageId);
                    if (!$stage) {
                        return ['error' => 'Estágio não encontrado'];
                    }
                    
                    // Mover conversa
                    \App\Services\FunnelService::moveConversation($conversationId, (int)$stageId, null);
                    
                    // Adicionar nota se configurado
                    if ($addNote) {
                        $noteText = "🤖 **IA moveu a conversa**\n\nEstágio: {$stage['name']}\nData/Hora: " . date('d/m/Y H:i:s');
                        
                        \App\Models\Message::create([
                            'conversation_id' => $conversationId,
                            'message_type' => 'note',
                            'content' => $noteText,
                            'sender_type' => 'system',
                            'status' => 'sent'
                        ]);
                    }
                    
                    // Disparar automações se configurado
                    if ($triggerAutomations) {
                        \App\Services\AutomationService::checkStageAutomations($conversationId, (int)$stageId);
                    }
                    
                    return [
                        'success' => true,
                        'message' => 'Conversa movida para o estágio: ' . $stage['name'],
                        'stage_id' => $stageId,
                        'stage_name' => $stage['name']
                    ];
                } catch (\Exception $e) {
                    return ['error' => 'Erro ao mover para estágio: ' . $e->getMessage()];
                }

            case 'escalar_para_humano':
                // ✅ USAR IMPLEMENTAÇÃO COMPLETA (executeHumanEscalationTool)
                // Buscar config da tool do banco ou usar valores padrão
                $escalationTool = $tool; // Manter referência original
                $escalationTool['tool_type'] = 'human_escalation';
                
                // Buscar configuração da tool se disponível
                $toolConfig = [];
                if (!empty($tool['id'])) {
                    $toolData = \App\Models\AITool::find($tool['id']);
                    if ($toolData && !empty($toolData['config'])) {
                        $toolConfig = is_array($toolData['config']) ? $toolData['config'] : json_decode($toolData['config'], true) ?? [];
                    }
                }
                
                // Valores padrão se não houver config
                if (empty($toolConfig)) {
                    $toolConfig = [
                        'escalation_type' => 'auto',
                        'distribution_method' => 'round_robin',
                        'consider_availability' => true,
                        'consider_limits' => true,
                        'remove_ai_after' => true,
                        'send_notification' => true
                    ];
                }
                
                return self::executeHumanEscalationTool($escalationTool, $arguments, $toolConfig, $conversationId, $context);

            default:
                \App\Helpers\Logger::aiTools("[SYSTEM TOOL ERROR] Tool não reconhecida no switch: slug='{$functionName}', name='{$tool['name']}'. Cases disponíveis: buscar_conversas_anteriores, buscar_informacoes_contato, adicionar_tag, mover_para_estagio, escalar_para_humano");
                return ['error' => 'System tool não reconhecida: ' . $functionName . ' (slug: ' . ($tool['slug'] ?? 'N/A') . ')'];
        }
    }

    /**
     * Escalar conversa para agente humano (DEPRECATED)
     * @deprecated Usar executeHumanEscalationTool() - mantido apenas para compatibilidade
     */
    private static function escalateToHuman(int $conversationId, array $arguments, array $config, array $context): array
    {
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ['error' => 'Conversa não encontrada'];
            }

            // Configurações da tool
            $escalationType = $config['escalation_type'] ?? 'auto';
            $departmentId = $config['department_id'] ?? null;
            $agentId = $config['agent_id'] ?? null;
            $funnelStageId = $config['funnel_stage_id'] ?? null;
            $priority = $config['priority'] ?? 'normal';
            $addNote = $config['add_escalation_note'] ?? true;
            $notifyAgent = $config['notify_agent'] ?? false;

            // Argumentos da IA (motivo da escalação, etc)
            $reason = $arguments['reason'] ?? $arguments['motivo'] ?? 'Escalação solicitada pelo agente de IA';
            $notes = $arguments['notes'] ?? $arguments['observacoes'] ?? '';

            $assignedTo = null;
            $escalationMethod = 'auto';

            // Determinar para quem atribuir baseado no tipo de escalação
            switch ($escalationType) {
                case 'agent':
                    // Atribuir a agente específico
                    if ($agentId) {
                        $agent = \App\Models\User::find($agentId);
                        if ($agent && $agent['role'] !== 'ai_agent') {
                            $assignedTo = $agentId;
                            $escalationMethod = 'agent_specific';
                        }
                    }
                    break;

                case 'department':
                    // Atribuir a setor específico (round-robin dentro do setor)
                    if ($departmentId) {
                        $assignedTo = self::assignToDepartment($conversationId, $departmentId);
                        $escalationMethod = 'department';
                    }
                    break;

                case 'round_robin':
                    // Distribuição round-robin entre todos agentes disponíveis
                    $assignedTo = self::assignRoundRobin($conversationId);
                    $escalationMethod = 'round_robin';
                    break;

                case 'funnel_stage':
                    // Mover para etapa do funil e usar automação dela
                    if ($funnelStageId) {
                Conversation::update($conversationId, [
                            'funnel_stage_id' => $funnelStageId
                        ]);
                        
                        // Executar automação da etapa (se houver)
                        \App\Services\AutomationService::checkStageAutomations($conversationId, $funnelStageId);
                        
                        $escalationMethod = 'funnel_stage';
                        // Não atribuir agente aqui, deixar automação decidir
                    }
                    break;

                case 'auto':
                default:
                    // Sistema decide automaticamente (usa regras de distribuição)
                    $assignedTo = self::autoAssignAgent($conversationId);
                    $escalationMethod = 'auto';
                    break;
            }

            // Atualizar conversa
            $updateData = [
                'status' => 'open',
                'priority' => $priority
            ];

            if ($assignedTo !== null) {
                $updateData['assigned_to'] = $assignedTo;
            }

            Conversation::update($conversationId, $updateData);
                
                // Atualizar status da conversa de IA
            $aiConversation = \App\Models\AIConversation::whereFirst('conversation_id', '=', $conversationId);
                if ($aiConversation) {
                \App\Models\AIConversation::updateStatus($aiConversation['id'], 'escalated');
                }
                
            // Adicionar nota interna
            if ($addNote) {
                $noteText = "🤖 **Escalação Automática via IA**\n\n";
                $noteText .= "**Motivo**: {$reason}\n";
                $noteText .= "**Método**: {$escalationMethod}\n";
                if ($notes) {
                    $noteText .= "**Observações**: {$notes}\n";
                }
                $noteText .= "**Prioridade**: {$priority}\n";
                $noteText .= "**Data/Hora**: " . date('d/m/Y H:i:s');

                \App\Models\Message::create([
                    'conversation_id' => $conversationId,
                    'message_type' => 'note',
                    'content' => $noteText,
                    'sender_type' => 'system',
                    'status' => 'sent'
                ]);
            }

            // Notificar agente (se configurado e agente foi atribuído)
            if ($notifyAgent && $assignedTo) {
                self::notifyAssignedAgent($assignedTo, $conversationId, $reason);
            }

            // Enviar mensagem de transição ao cliente (opcional)
            if ($config['send_transition_message'] ?? false) {
                $transitionMessage = $config['transition_message'] ?? 'Vou transferir você para um de nossos especialistas. Aguarde um momento, por favor.';
                
                \App\Models\Message::create([
                    'conversation_id' => $conversationId,
                    'message_type' => 'text',
                    'content' => $transitionMessage,
                    'sender_type' => 'agent',
                    'sender_id' => 0, // Sistema
                    'status' => 'sent'
                ]);

                // Enviar via canal (WhatsApp, etc)
                if ($conversation['channel'] === 'whatsapp' && !empty($conversation['integration_id'])) {
                    \App\Services\WhatsAppService::sendMessage(
                        $conversation['integration_id'],
                        $conversation['contact_identifier'],
                        $transitionMessage
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Conversa escalada para agente humano',
                'escalation_method' => $escalationMethod,
                'assigned_to' => $assignedTo,
                'priority' => $priority
            ];

        } catch (\Exception $e) {
            error_log("Erro ao escalar conversa {$conversationId}: " . $e->getMessage());
            return ['error' => 'Erro ao escalar conversa: ' . $e->getMessage()];
        }
    }

    /**
     * Notificar agente atribuído
     */
    private static function notifyAssignedAgent(int $agentId, int $conversationId, string $reason): void
    {
        try {
            $agent = \App\Models\User::find($agentId);
            $conversation = Conversation::find($conversationId);

            if (!$agent || !$conversation) {
                return;
            }

            // Notificação via WebSocket (tempo real)
            \App\Helpers\WebSocket::notifyUser($agentId, [
                'type' => 'escalation_assigned',
                'conversation_id' => $conversationId,
                'reason' => $reason,
                'priority' => $conversation['priority'] ?? 'normal'
            ]);

            // TODO: Implementar notificação via WhatsApp/Email se necessário
            // if ($agent['notification_preferences']['escalation_external'] ?? false) {
            //     // Enviar WhatsApp ou Email
            // }

        } catch (\Exception $e) {
            error_log("Erro ao notificar agente {$agentId}: " . $e->getMessage());
        }
    }

    /**
     * Executar Followup Tools
     */
    private static function executeFollowupTool(array $tool, array $arguments, int $conversationId, array $context): array
    {
        // IMPORTANTE: Usar slug ao invés de name para identificar a tool
        $functionName = $tool['slug'] ?? $tool['name'] ?? '';
        $config = is_string($tool['config'] ?? null) ? json_decode($tool['config'], true) : ($tool['config'] ?? []);
        
        \App\Helpers\Logger::aiTools("[FOLLOWUP TOOL] Executando: slug='{$functionName}', name='{$tool['name']}', id={$tool['id']}");

        switch ($functionName) {
            case 'verificar_status_conversa':
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    return ['error' => 'Conversa não encontrada'];
                }
                
                $includeLastMessage = $config['include_last_message'] ?? true;
                $includeAgentInfo = $config['include_agent_info'] ?? true;
                $includeTimestamps = $config['include_timestamps'] ?? true;
                
                $result = [
                    'conversation_id' => $conversationId,
                    'status' => $conversation['status']
                ];
                
                // Incluir última mensagem
                if ($includeLastMessage) {
                    $lastMessage = Database::fetch(
                        "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1",
                        [$conversationId]
                    );
                    
                    $result['last_message'] = $lastMessage ? [
                        'content' => $lastMessage['content'],
                        'sender_type' => $lastMessage['sender_type'],
                        'created_at' => $lastMessage['created_at']
                    ] : null;
                }
                
                // Incluir informações do agente
                if ($includeAgentInfo && $conversation['assigned_user_id']) {
                    $agent = \App\Models\User::find($conversation['assigned_user_id']);
                    $result['agent'] = $agent ? [
                        'id' => $agent['id'],
                        'name' => $agent['name'],
                        'email' => $agent['email']
                    ] : null;
                }
                
                // Incluir timestamps
                if ($includeTimestamps) {
                    $result['created_at'] = $conversation['created_at'];
                    $result['updated_at'] = $conversation['updated_at'];
                }
                
                return $result;

            case 'verificar_ultima_interacao':
                $includeContent = $config['include_message_content'] ?? true;
                $includeSender = $config['include_sender_info'] ?? true;
                $calculateTime = $config['calculate_time_ago'] ?? true;
                
                $lastMessage = Database::fetch(
                    "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1",
                    [$conversationId]
                );
                
                if (!$lastMessage) {
                    return [
                        'has_interaction' => false,
                        'message' => 'Nenhuma interação encontrada nesta conversa'
                    ];
                }
                
                $result = ['has_interaction' => true];
                
                // Incluir conteúdo da mensagem
                if ($includeContent) {
                    $result['last_message'] = [
                        'content' => $lastMessage['content'],
                        'created_at' => $lastMessage['created_at']
                    ];
                    
                    // Incluir informações do remetente
                    if ($includeSender) {
                        $result['last_message']['sender_type'] = $lastMessage['sender_type'];
                        $result['last_message']['sender_id'] = $lastMessage['sender_id'];
                    }
                }
                
                // Calcular tempo decorrido
                if ($calculateTime) {
                    $now = time();
                    $lastInteractionTime = strtotime($lastMessage['created_at']);
                    $minutesAgo = round(($now - $lastInteractionTime) / 60);
                    $hoursAgo = round($minutesAgo / 60);
                    $daysAgo = round($hoursAgo / 24);
                    
                    $result['time_ago'] = [
                        'minutes' => $minutesAgo,
                        'hours' => $hoursAgo,
                        'days' => $daysAgo,
                        'human_readable' => $daysAgo > 0 
                            ? "{$daysAgo} dia(s) atrás"
                            : ($hoursAgo > 0 
                                ? "{$hoursAgo} hora(s) atrás"
                                : "{$minutesAgo} minuto(s) atrás")
                    ];
                }
                
                return $result;

            default:
                \App\Helpers\Logger::aiTools("[FOLLOWUP TOOL ERROR] Tool não reconhecida no switch: slug='{$functionName}', name='{$tool['name']}'. Cases disponíveis: verificar_status_conversa, verificar_ultima_interacao");
                return ['error' => 'Followup tool não reconhecida: ' . $functionName . ' (slug: ' . ($tool['slug'] ?? 'N/A') . ')'];
        }
    }

    /**
     * Executar WooCommerce Tools (integração com WooCommerce REST API)
     */
    private static function executeWooCommerceTool(array $tool, array $arguments, array $config): array
    {
        $invocationName = self::normalizeWooCommerceFunctionName(self::getToolInvocationFunctionName($tool));
        $functionName = self::canonicalizeWooCommerceFunctionName($invocationName);
        // UI das tools usa "url"; integração nativa usa "woocommerce_url"; fontes externas podem usar "store_url"
        $wcUrl = $config['woocommerce_url'] ?? $config['url'] ?? $config['store_url'] ?? null;
        $consumerKey = $config['consumer_key'] ?? null;
        $consumerSecret = $config['consumer_secret'] ?? null;

        $trimStr = static function ($v) {
            if ($v === null) {
                return null;
            }
            if (!is_string($v)) {
                return $v;
            }
            $t = trim($v);
            return $t === '' ? null : $t;
        };
        $wcUrl = $trimStr($wcUrl);
        $consumerKey = $trimStr($consumerKey);
        $consumerSecret = $trimStr($consumerSecret);

        if (!$wcUrl || !$consumerKey || !$consumerSecret) {
            return ['error' => 'Configurações do WooCommerce não completas (URL, Consumer Key, Consumer Secret)'];
        }
        
        // Função auxiliar para fazer requisições à API do WooCommerce
        $makeWCRequest = function($endpoint, $method = 'GET', $data = []) use ($wcUrl, $consumerKey, $consumerSecret) {
            $url = rtrim($wcUrl, '/') . '/wp-json/wc/v3/' . ltrim($endpoint, '/');
            
            // Autenticação OAuth 1.0 ou Basic Auth
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10
            ]);
            
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception('Erro ao chamar WooCommerce API: ' . $error);
            }
            
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Resposta inválida da API WooCommerce');
            }
            
            return [
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'data' => $responseData
            ];
        };
        
        try {
            switch ($functionName) {
                case 'buscar_pedido_woocommerce':
                    $orderId = $arguments['order_id'] ?? null;
                    
                    if (!$orderId) {
                        return ['error' => 'ID do pedido não fornecido'];
                    }
                    
                    $result = $makeWCRequest("orders/{$orderId}");
                    return [
                        'success' => $result['success'],
                        'order' => $result['data']
                    ];

                case 'buscar_pedidos_woocommerce':
                case 'listar_pedidos_cliente_woocommerce':
                    $q = [];
                    $customer = $arguments['customer'] ?? $arguments['customer_id'] ?? null;
                    if ($customer !== null && $customer !== '') {
                        $q['customer'] = (int)$customer;
                    }
                    foreach (['status', 'search', 'after', 'before', 'product'] as $k) {
                        if (!empty($arguments[$k])) {
                            $q[$k] = $arguments[$k];
                        }
                    }
                    $perPage = min(max((int)($arguments['per_page'] ?? 20), 1), 100);
                    $page = max((int)($arguments['page'] ?? 1), 1);
                    $q['per_page'] = $perPage;
                    $q['page'] = $page;
                    $query = http_build_query($q);
                    $result = $makeWCRequest('orders?' . $query);
                    $data = $result['data'] ?? [];
                    return [
                        'success' => $result['success'],
                        'orders' => is_array($data) ? $data : [],
                        'http_code' => $result['http_code'] ?? null
                    ];
                
                case 'buscar_produto_woocommerce':
                    $productId = $arguments['product_id'] ?? null;
                    $sku = $arguments['sku'] ?? null;
                    $search = $arguments['search'] ?? null;
                    $limit = min((int)($arguments['limit'] ?? 10), 100);
                    
                    if ($productId) {
                        $result = $makeWCRequest("products/{$productId}");
                        return [
                            'success' => $result['success'],
                            'product' => $result['data']
                        ];
                    } elseif ($sku) {
                        $result = $makeWCRequest("products?sku={$sku}&per_page={$limit}");
                        return [
                            'success' => $result['success'],
                            'products' => $result['data'] ?? []
                        ];
                    } elseif ($search) {
                        $result = $makeWCRequest("products?search={$search}&per_page={$limit}");
                        return [
                            'success' => $result['success'],
                            'products' => $result['data'] ?? []
                        ];
                    } else {
                        return ['error' => 'Forneça product_id, sku ou search'];
                    }
                
                case 'criar_pedido_woocommerce':
                    $lineItems = $arguments['line_items'] ?? [];
                    $billing = $arguments['billing'] ?? [];
                    $shipping = $arguments['shipping'] ?? [];
                    $paymentMethod = $arguments['payment_method'] ?? 'bacs';
                    $status = $arguments['status'] ?? 'pending';
                    
                    if (empty($lineItems)) {
                        return ['error' => 'Itens do pedido não fornecidos'];
                    }
                    
                    $orderData = [
                        'payment_method' => $paymentMethod,
                        'payment_method_title' => $arguments['payment_method_title'] ?? 'Pagamento direto',
                        'status' => $status,
                        'line_items' => $lineItems,
                        'billing' => $billing,
                        'shipping' => $shipping
                    ];
                    
                    $result = $makeWCRequest('orders', 'POST', $orderData);
                    return [
                        'success' => $result['success'],
                        'order' => $result['data']
                    ];
                
                case 'atualizar_status_pedido':
                    $orderId = $arguments['order_id'] ?? null;
                    $status = $arguments['status'] ?? null;
                    
                    if (!$orderId || !$status) {
                        return ['error' => 'ID do pedido e novo status são obrigatórios'];
                    }
                    
                    $validStatuses = ['pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed'];
                    if (!in_array($status, $validStatuses)) {
                        return ['error' => 'Status inválido. Use: ' . implode(', ', $validStatuses)];
                    }
                    
                    $result = $makeWCRequest("orders/{$orderId}", 'PUT', ['status' => $status]);
                    return [
                        'success' => $result['success'],
                        'order' => $result['data']
                    ];
                
                default:
                    return ['error' => 'WooCommerce tool não reconhecida: ' . $invocationName];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Executar Database Tools (consultas seguras ao banco de dados)
     */
    private static function executeDatabaseTool(array $tool, array $arguments, array $config): array
    {
        $functionName = $tool['name'] ?? '';
        $allowedTables = $config['allowed_tables'] ?? [];
        $readOnly = $config['read_only'] ?? true;
        
        // Validar que há tabelas permitidas configuradas
        if (empty($allowedTables)) {
            return ['error' => 'Nenhuma tabela permitida configurada para esta tool'];
        }
        
        switch ($functionName) {
            case 'consultar_banco_dados':
                $table = $arguments['table'] ?? null;
                $where = $arguments['where'] ?? [];
                $limit = min((int)($arguments['limit'] ?? 10), 100); // Máximo 100 registros
                $orderBy = $arguments['order_by'] ?? null;
                
                if (!$table) {
                    return ['error' => 'Nome da tabela não fornecido'];
                }
                
                // Validar que a tabela está na lista de permitidas
                if (!in_array($table, $allowedTables)) {
                    return ['error' => 'Tabela não permitida: ' . $table];
                }
                
                // Construir query segura
                $sql = "SELECT * FROM `" . str_replace('`', '', $table) . "` WHERE 1=1";
                $params = [];
                
                // Adicionar condições WHERE (apenas campos permitidos)
                $allowedColumns = $config['allowed_columns'][$table] ?? [];
                if (!empty($where) && !empty($allowedColumns)) {
                    foreach ($where as $column => $value) {
                        if (in_array($column, $allowedColumns)) {
                            $sql .= " AND `" . str_replace('`', '', $column) . "` = ?";
                            $params[] = $value;
                        }
                    }
                }
                
                // Adicionar ORDER BY (apenas se coluna permitida)
                if ($orderBy && !empty($allowedColumns)) {
                    $orderColumn = str_replace('`', '', $orderBy);
                    if (in_array($orderColumn, $allowedColumns)) {
                        $sql .= " ORDER BY `{$orderColumn}` DESC";
                    }
                }
                
                // Adicionar LIMIT
                $sql .= " LIMIT ?";
                $params[] = $limit;
                
                try {
                    $results = Database::fetchAll($sql, $params);
                    return [
                        'success' => true,
                        'table' => $table,
                        'count' => count($results),
                        'data' => $results
                    ];
                } catch (\Exception $e) {
                    return ['error' => 'Erro ao consultar banco de dados: ' . $e->getMessage()];
                }
            
            default:
                return ['error' => 'Database tool não reconhecida: ' . $functionName];
        }
    }

    /**
     * Executar N8N Tools (integração com N8N workflows)
     */
    private static function executeN8NTool(array $tool, array $arguments, array $config, int $conversationId = 0, array $context = []): array
    {
        $functionName = $tool['name'] ?? '';
        $n8nUrl = $config['n8n_url'] ?? null;
        $webhookId = $config['webhook_id'] ?? null;
        $webhookPath = $config['webhook_path'] ?? '/webhook';
        $apiKey = $config['api_key'] ?? null;
        $useRawResponse = !empty($config['use_raw_response']);
        $rawResponseField = $config['raw_response_field'] ?? 'message';
        $timeout = (int)($config['timeout'] ?? 120);
        
        \App\Helpers\Logger::aiTools("[N8N TOOL] Iniciando execução: function={$functionName}, conversationId={$conversationId}");
        \App\Helpers\Logger::aiTools("[N8N TOOL] Config: url={$n8nUrl}, webhookId={$webhookId}, useRawResponse=" . ($useRawResponse ? 'true' : 'false'));
        \App\Helpers\Logger::aiTools("[N8N TOOL] Arguments: " . json_encode($arguments));
        
        if (!$n8nUrl) {
            \App\Helpers\Logger::aiTools("[N8N TOOL ERROR] URL do N8N não configurada");
            return ['error' => 'URL do N8N não configurada'];
        }
        
        // Para qualquer função N8N, executar o webhook genérico
        // A função específica é identificada pelo function_schema, mas a execução é via webhook
                $workflowId = $arguments['workflow_id'] ?? $webhookId;
                
        // Se não tem workflow_id nos argumentos nem na config, usar o próprio functionName como webhook
                if (!$workflowId) {
            $workflowId = $webhookId;
        }
        
        if (!$workflowId) {
            return ['error' => 'ID do webhook não configurado'];
        }
        
        // Adicionar ID da conversa e contexto aos argumentos (para memória do agente no N8N)
        if ($conversationId > 0) {
            $arguments['conversation_id'] = $conversationId;
            $arguments['session_id'] = (string)$conversationId;
            $arguments['thread_id'] = (string)$conversationId;
        }
        
        // Adicionar informações do contato se disponíveis
        if (!empty($context['contact'])) {
            $arguments['contact'] = $context['contact'];
        }
        
        // IMPORTANTE: Adicionar a mensagem do usuário automaticamente se não estiver nos argumentos
        // Isso é necessário porque o schema da tool pode não definir o parâmetro 'message'
        if (empty($arguments['message']) && !empty($context['user_message'])) {
            $arguments['message'] = $context['user_message'];
        }
        if (empty($arguments['client_message']) && !empty($context['user_message'])) {
            $arguments['client_message'] = $context['user_message'];
        }
        
        // ============================================================
        // CONTEXTO COMPLETO PARA O N8N (histórico + resumo + agente)
        // ============================================================
        
        // 1. Buscar histórico das últimas mensagens (resumido)
        $includeHistory = $config['include_history'] ?? true; // Ativado por padrão
        $historyLimit = (int)($config['history_limit'] ?? 10);
        
        if ($includeHistory && $conversationId > 0) {
            $historyMessages = self::getConversationHistoryForN8N($conversationId, $historyLimit);
            if (!empty($historyMessages)) {
                $arguments['conversation_history'] = $historyMessages;
            }
        }
        
        // 2. Buscar resumo da conversa (se existir no metadata)
        if ($conversationId > 0 && !empty($context['conversation'])) {
            $metadata = $context['conversation']['metadata'] ?? null;
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }
            
            if (!empty($metadata['conversation_summary'])) {
                $arguments['conversation_summary'] = $metadata['conversation_summary'];
            }
            
            // Adicionar outros dados úteis do metadata
            if (!empty($metadata['ai_collected_data'])) {
                $arguments['collected_data'] = $metadata['ai_collected_data'];
            }
        }
        
        // 3. Adicionar informações do agente (para manter consistência de persona)
        $includeAgentInfo = $config['include_agent_info'] ?? true;
        if ($includeAgentInfo && !empty($context['agent'])) {
            $agent = $context['agent'];
            $arguments['agent_info'] = [
                'name' => $agent['name'] ?? 'Assistente',
                'persona' => $agent['persona'] ?? null,
                'prompt_summary' => isset($agent['prompt']) ? substr($agent['prompt'], 0, 500) : null
            ];
                }
                
                // Construir URL do webhook
        $webhookUrl = rtrim($n8nUrl, '/') . rtrim($webhookPath, '/') . '/' . ltrim($workflowId, '/');
                
                // Preparar headers
                $headers = [
                    'Content-Type: application/json'
                ];
                
                if ($apiKey) {
                    $headers[] = 'X-N8N-API-KEY: ' . $apiKey;
                }
                
        // Adicionar headers customizados se configurados
        $customHeaders = $config['custom_headers'] ?? [];
        if (is_string($customHeaders)) {
            $customHeaders = json_decode($customHeaders, true) ?? [];
        }
        foreach ($customHeaders as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        
        // Log do payload que será enviado ao N8N
        \App\Helpers\ConversationDebug::info($conversationId, "N8N Payload", [
            'url' => $webhookUrl,
            'has_history' => !empty($arguments['conversation_history']),
            'history_count' => count($arguments['conversation_history'] ?? []),
            'has_summary' => !empty($arguments['conversation_summary']),
            'has_agent_info' => !empty($arguments['agent_info']),
            'message' => $arguments['message'] ?? $arguments['client_message'] ?? null
        ]);
        
        // Fazer requisição POST ao webhook (passa todos os arguments da IA)
        \App\Helpers\Logger::aiTools("[N8N TOOL] Enviando requisição para: {$webhookUrl}");
        \App\Helpers\Logger::aiTools("[N8N TOOL] Payload: " . json_encode($arguments, JSON_UNESCAPED_UNICODE));
        
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($arguments, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
        \App\Helpers\Logger::aiTools("[N8N TOOL] Resposta recebida: httpCode={$httpCode}, error=" . ($error ?: 'none'));
        \App\Helpers\Logger::aiTools("[N8N TOOL] Response body: " . substr($response, 0, 1000));
                
                if ($error) {
            \App\Helpers\Logger::aiTools("[N8N TOOL ERROR] Erro cURL: {$error}");
                    return ['error' => 'Erro ao executar workflow N8N: ' . $error];
                }
                
                $responseData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
            // Se não é JSON válido, tratar como texto
            \App\Helpers\Logger::aiTools("[N8N TOOL] Resposta não é JSON válido, tratando como texto");
                    $responseData = ['raw_response' => $response];
                }
                
        $result = [
                    'success' => $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'workflow_id' => $workflowId,
                    'response' => $responseData
                ];
            
        \App\Helpers\Logger::aiTools("[N8N TOOL] Resultado: success=" . ($result['success'] ? 'true' : 'false') . ", useRawResponse=" . ($useRawResponse ? 'true' : 'false'));
            
        // Se use_raw_response está ativo, extrair a mensagem direta do N8N
        if ($useRawResponse && $result['success']) {
            $rawMessage = self::extractN8NMessage($responseData, $response, $rawResponseField);
            
            \App\Helpers\Logger::aiTools("[N8N TOOL] Raw message extraída: " . ($rawMessage ? substr($rawMessage, 0, 200) : 'null'));
            
            if ($rawMessage !== null) {
                $result['use_raw_response'] = true;
                $result['raw_message'] = $rawMessage;
                $result['extracted_from'] = $rawResponseField;
            }
        }
        
        \App\Helpers\Logger::aiTools("[N8N TOOL] Finalizando execução com sucesso");
        return $result;
    }

    /**
     * Extrair mensagem da resposta do N8N
     * Lida com diferentes formatos: arrays, objetos, campos aninhados
     */
    private static function extractN8NMessage($responseData, string $rawResponse, string $configuredField): ?string
    {
        // Se responseData é null, tentar parsear rawResponse
        if ($responseData === null) {
            $responseData = json_decode($rawResponse, true);
                }
                
        // Campos a tentar, começando pelo configurado
        $fieldsToTry = array_unique(array_filter([
            $configuredField, 
            'output', 
            'message', 
            'response', 
            'text', 
            'content', 
            'reply',
            'answer',
            'result'
        ]));
        
        // Se a resposta é um array indexado (ex: [{...}]), pegar o primeiro elemento
        if (is_array($responseData) && isset($responseData[0]) && is_array($responseData[0])) {
            $responseData = $responseData[0];
        }
        
        // Tentar extrair de cada campo
        foreach ($fieldsToTry as $field) {
            // Suporta notação com ponto para campos aninhados (ex: "data.message")
            $value = self::getNestedValue($responseData, $field);
                
            if ($value !== null && is_string($value) && !empty(trim($value))) {
                return trim($value);
            }
        }
        
        // Se a resposta bruta é uma string simples (não JSON), usar diretamente
        if (is_string($rawResponse) && !empty(trim($rawResponse))) {
            $decoded = json_decode($rawResponse, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                // Não é JSON válido, usar a string direta
                return trim($rawResponse);
            }
                }
                
        return null;
    }

    /**
     * Obter valor aninhado de um array usando notação de ponto
     * Ex: getNestedValue($data, "data.user.name")
     */
    private static function getNestedValue($data, string $path)
    {
        if (!is_array($data)) {
            return null;
        }
        
        // Primeiro tentar acesso direto
        if (isset($data[$path])) {
            return $data[$path];
        }
        
        // Tentar notação com ponto
        $keys = explode('.', $path);
        $value = $data;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * Executar API Tools (chamadas genéricas a APIs externas)
     */
    private static function executeAPITool(array $tool, array $arguments, array $config): array
    {
        $functionName = $tool['name'] ?? '';
        $apiUrl = $config['api_url'] ?? null;
        $apiKey = $config['api_key'] ?? null;
        $method = strtoupper($config['method'] ?? 'GET');
        
        if (!$apiUrl) {
            return ['error' => 'URL da API não configurada'];
        }
        
        switch ($functionName) {
            case 'chamar_api_externa':
                $endpoint = $arguments['endpoint'] ?? '';
                $body = $arguments['body'] ?? [];
                $headers = $arguments['headers'] ?? [];
                
                // Construir URL completa
                $fullUrl = rtrim($apiUrl, '/') . '/' . ltrim($endpoint, '/');
                
                // Preparar headers
                $requestHeaders = [
                    'Content-Type: application/json'
                ];
                
                if ($apiKey) {
                    $requestHeaders[] = 'Authorization: Bearer ' . $apiKey;
                }
                
                // Adicionar headers customizados
                foreach ($headers as $key => $value) {
                    $requestHeaders[] = $key . ': ' . $value;
                }
                
                // Fazer requisição
                $ch = curl_init($fullUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_HTTPHEADER => $requestHeaders,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);
                
                if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($body)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    return ['error' => 'Erro ao chamar API: ' . $error];
                }
                
                $responseData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $responseData = ['raw_response' => $response];
                }
                
                return [
                    'success' => $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'url' => $fullUrl,
                    'response' => $responseData
                ];
            
            default:
                return ['error' => 'API tool não reconhecida: ' . $functionName];
        }
    }

    /**
     * Executar Document Tools (busca e extração de texto de documentos)
     */
    private static function executeDocumentTool(array $tool, array $arguments, array $config): array
    {
        $functionName = $tool['name'] ?? '';
        $documentsPath = $config['documents_path'] ?? null;
        
        switch ($functionName) {
            case 'buscar_documento':
                $searchTerm = $arguments['search_term'] ?? null;
                $documentType = $arguments['document_type'] ?? null; // pdf, docx, txt
                $limit = min((int)($arguments['limit'] ?? 10), 50);
                
                if (!$searchTerm) {
                    return ['error' => 'Termo de busca não fornecido'];
                }
                
                if (!$documentsPath || !is_dir($documentsPath)) {
                    return ['error' => 'Diretório de documentos não configurado ou não existe'];
                }
                
                // Buscar arquivos no diretório
                $files = [];
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($documentsPath)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $extension = strtolower($file->getExtension());
                        $allowedExtensions = ['pdf', 'docx', 'doc', 'txt'];
                        
                        if ($documentType && $extension !== $documentType) {
                            continue;
                        }
                        
                        if (in_array($extension, $allowedExtensions)) {
                            $fileName = $file->getFilename();
                            $filePath = $file->getPathname();
                            
                            // Buscar termo no nome do arquivo ou no conteúdo (simplificado)
                            if (stripos($fileName, $searchTerm) !== false) {
                                $files[] = [
                                    'name' => $fileName,
                                    'path' => $filePath,
                                    'size' => $file->getSize(),
                                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                                ];
                                
                                if (count($files) >= $limit) {
                                    break;
                                }
                            }
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'search_term' => $searchTerm,
                    'count' => count($files),
                    'documents' => $files
                ];
            
            case 'extrair_texto_documento':
                $documentPath = $arguments['document_path'] ?? null;
                
                if (!$documentPath) {
                    return ['error' => 'Caminho do documento não fornecido'];
                }
                
                // Validar que o arquivo existe e está no diretório permitido
                if (!file_exists($documentPath)) {
                    return ['error' => 'Documento não encontrado'];
                }
                
                if ($documentsPath && strpos(realpath($documentPath), realpath($documentsPath)) !== 0) {
                    return ['error' => 'Acesso negado: documento fora do diretório permitido'];
                }
                
                $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));
                $text = '';
                
                try {
                    switch ($extension) {
                        case 'txt':
                            $text = file_get_contents($documentPath);
                            break;
                        
                        case 'pdf':
                            // Requer biblioteca externa (ex: smalot/pdfparser)
                            // Por enquanto, retornar erro informando necessidade de biblioteca
                            return [
                                'error' => 'Extração de PDF requer biblioteca adicional (ex: smalot/pdfparser)',
                                'suggestion' => 'Instale: composer require smalot/pdfparser'
                            ];
                        
                        case 'docx':
                        case 'doc':
                            // Requer biblioteca externa (ex: phpoffice/phpspreadsheet)
                            return [
                                'error' => 'Extração de DOCX requer biblioteca adicional (ex: phpoffice/phpspreadsheet)',
                                'suggestion' => 'Instale: composer require phpoffice/phpspreadsheet'
                            ];
                        
                        default:
                            return ['error' => 'Tipo de documento não suportado: ' . $extension];
                    }
                    
                    return [
                        'success' => true,
                        'document_path' => $documentPath,
                        'text' => $text,
                        'length' => strlen($text)
                    ];
                } catch (\Exception $e) {
                    return ['error' => 'Erro ao extrair texto: ' . $e->getMessage()];
                }
            
            default:
                return ['error' => 'Document tool não reconhecida: ' . $functionName];
        }
    }

    /**
     * Executar Human Escalation Tool (Escalar para Humano)
     */
    private static function executeHumanEscalationTool(array $tool, array $arguments, array $config, int $conversationId, array $context): array
    {
        try {
            \App\Helpers\ConversationDebug::log($conversationId, "🧑‍💼 Executando Human Escalation Tool");
            
            $escalationType = $config['escalation_type'] ?? 'auto';
            $departmentId = $config['department_id'] ?? null;
            $agentId = $config['agent_id'] ?? null;
            $distributionMethod = $config['distribution_method'] ?? 'round_robin';
            $considerAvailability = !empty($config['consider_availability']);
            $considerLimits = !empty($config['consider_limits']);
            $allowAIAgents = !empty($config['allow_ai_agents']);
            $forceAssign = !empty($config['force_assign']);
            $removeAIAfter = $config['remove_ai_after'] ?? true;
            $sendNotification = $config['send_notification'] ?? true;
            $escalationMessage = $config['escalation_message'] ?? null;
            
            // Razão e notas passadas pela IA
            $reason = $arguments['reason'] ?? 'Solicitado pela IA';
            $notes = $arguments['notes'] ?? null;
            
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ['error' => 'Conversa não encontrada'];
            }
            
            $assignedAgentId = null;
            $assignedAgentName = null;
            
            switch ($escalationType) {
                case 'agent':
                    // Atribuir a agente específico
                    if (!$agentId) {
                        return ['error' => 'Agente não configurado na tool'];
                    }
                    
                    $agent = User::find($agentId);
                    if (!$agent) {
                        return ['error' => 'Agente não encontrado'];
                    }
                    
                    // Verificar disponibilidade se não forçar
                    if (!$forceAssign && $considerAvailability && $agent['status'] !== 'active') {
                        return ['error' => 'Agente não está disponível no momento'];
                    }
                    
                    $assignedAgentId = $agentId;
                    $assignedAgentName = $agent['name'];
                    break;
                    
                case 'department':
                    // Atribuir a agente do setor
                    if (!$departmentId) {
                        return ['error' => 'Setor não configurado na tool'];
                    }
                    
                    $agentsInDept = Department::getAgents($departmentId);
                    if (empty($agentsInDept)) {
                        return ['error' => 'Nenhum agente encontrado no setor'];
                    }
                    
                    // Filtrar por disponibilidade se necessário
                    if ($considerAvailability) {
                        $agentsInDept = array_filter($agentsInDept, fn($a) => $a['status'] === 'active');
                    }
                    
                    if (empty($agentsInDept)) {
                        return ['error' => 'Nenhum agente disponível no setor'];
                    }
                    
                    // Escolher agente (round robin simplificado - pegar com menos conversas)
                    $selectedAgent = null;
                    $minConversations = PHP_INT_MAX;
                    
                    foreach ($agentsInDept as $agent) {
                        $count = Database::fetchColumn(
                            "SELECT COUNT(*) FROM conversations WHERE assigned_user_id = ? AND status = 'open'",
                            [$agent['id']]
                        );
                        
                        if ($considerLimits) {
                            $maxConversations = $agent['max_conversations'] ?? 50;
                            if ($count >= $maxConversations) continue;
                        }
                        
                        if ($count < $minConversations) {
                            $minConversations = $count;
                            $selectedAgent = $agent;
                        }
                    }
                    
                    if (!$selectedAgent) {
                        return ['error' => 'Todos os agentes do setor estão no limite'];
                    }
                    
                    $assignedAgentId = $selectedAgent['id'];
                    $assignedAgentName = $selectedAgent['name'];
                    break;
                    
                case 'custom':
                    // Distribuição personalizada
                    $settings = [
                        'method' => $distributionMethod,
                        'department_id' => $departmentId,
                        'consider_availability' => $considerAvailability,
                        'consider_limits' => $considerLimits,
                        'allow_ai_agents' => $allowAIAgents
                    ];
                    
                    $assignedAgentId = ConversationService::autoAssignAgent($conversationId, $settings);
                    
                    if (!$assignedAgentId) {
                        return ['error' => 'Não foi possível encontrar um agente disponível'];
                    }
                    
                    $agent = User::find($assignedAgentId);
                    $assignedAgentName = $agent['name'] ?? 'Agente';
                    break;
                    
                case 'auto':
                default:
                    // Usar distribuição automática do sistema
                    $assignedAgentId = ConversationService::autoAssignAgent($conversationId);
                    
                    if (!$assignedAgentId) {
                        return ['error' => 'Não foi possível atribuir a um agente automaticamente'];
                    }
                    
                    $agent = User::find($assignedAgentId);
                    $assignedAgentName = $agent['name'] ?? 'Agente';
                    break;
            }
            
            // Atribuir conversa ao agente
            $updateData = [
                'assigned_user_id' => $assignedAgentId,
                'status' => 'open'
            ];
            
            // Atualizar prioridade se configurado
            if (!empty($config['priority'])) {
                $updateData['priority'] = $config['priority'];
            }
            
            Conversation::update($conversationId, $updateData);
            
            // Atualizar status da conversa de IA
            $aiConversation = \App\Models\AIConversation::whereFirst('conversation_id', '=', $conversationId);
            if ($aiConversation) {
                \App\Models\AIConversation::updateStatus($aiConversation['id'], 'escalated', $assignedAgentId);
            }
            
            // Remover IA da conversa se configurado
            if ($removeAIAfter) {
                ConversationAIService::removeAIAgent($conversationId);
            }
            
            // Adicionar nota interna
            if ($notes || $reason) {
                Activity::create([
                    'conversation_id' => $conversationId,
                    'user_id' => null,
                    'activity_type' => 'ai_escalation',
                    'content' => json_encode([
                        'reason' => $reason,
                        'notes' => $notes,
                        'assigned_to' => $assignedAgentName,
                        'escalation_type' => $escalationType
                    ]),
                    'is_internal' => true
                ]);
            }
            
            // Notificar agente humano
            if ($sendNotification && $assignedAgentId) {
                // TODO: Implementar notificação via WebSocket
                \App\Helpers\ConversationDebug::log($conversationId, "Notificação enviada para agente: {$assignedAgentName}");
            }
            
            \App\Helpers\ConversationDebug::log($conversationId, "✅ Escalado para: {$assignedAgentName} (ID: {$assignedAgentId})");
            
            return [
                'success' => true,
                'message' => "Conversa transferida para {$assignedAgentName}",
                'assigned_agent_id' => $assignedAgentId,
                'assigned_agent_name' => $assignedAgentName,
                'escalation_type' => $escalationType,
                'reason' => $reason,
                // Se tiver mensagem de escalação, retornar como resposta direta
                'use_raw_response' => !empty($escalationMessage),
                'raw_message' => $escalationMessage
            ];
            
        } catch (\Exception $e) {
            \App\Helpers\ConversationDebug::error($conversationId, 'executeHumanEscalationTool', $e->getMessage());
            return ['error' => 'Erro ao escalar para humano: ' . $e->getMessage()];
        }
    }

    /**
     * Executar Funnel Stage Tool (Mover para Funil/Etapa)
     */
    private static function executeFunnelStageTool(array $tool, array $arguments, array $config, int $conversationId, array $context): array
    {
        try {
            \App\Helpers\ConversationDebug::log($conversationId, "📊 Executando Funnel Stage Tool");
            
            $funnelId = $config['funnel_id'] ?? null;
            $stageId = $config['stage_id'] ?? null;
            $keepAgent = $config['keep_agent'] ?? true;
            $removeAIAfter = !empty($config['remove_ai_after']);
            $addNote = $config['add_note'] ?? true;
            $noteTemplate = $config['note_template'] ?? 'Movido para {stage_name} pela IA. Motivo: {reason}';
            $triggerAutomation = $config['trigger_automation'] ?? true;
            
            // Razão passada pela IA
            $reason = $arguments['reason'] ?? 'Ação automática da IA';
            
            if (!$funnelId || !$stageId) {
                return ['error' => 'Funil e/ou Etapa não configurados na tool'];
            }
            
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ['error' => 'Conversa não encontrada'];
            }
            
            // Verificar se funil e etapa existem
            $funnel = Funnel::find($funnelId);
            if (!$funnel) {
                return ['error' => 'Funil não encontrado'];
            }
            
            $stage = FunnelStage::find($stageId);
            if (!$stage || $stage['funnel_id'] != $funnelId) {
                return ['error' => 'Etapa não encontrada ou não pertence ao funil'];
            }
            
            $oldStageId = $conversation['funnel_stage_id'];
            $oldFunnelId = $conversation['funnel_id'];
            
            // Atualizar conversa
            $updateData = [
                'funnel_id' => $funnelId,
                'funnel_stage_id' => $stageId
            ];
            
            // Se não manter agente, remover atribuição
            if (!$keepAgent) {
                $updateData['assigned_user_id'] = null;
            }
            
            Conversation::update($conversationId, $updateData);
            
            // Remover IA se configurado
            if ($removeAIAfter) {
                ConversationAIService::removeAIAgent($conversationId);
            }
            
            // Adicionar nota interna
            if ($addNote) {
                $noteContent = str_replace(
                    ['{stage_name}', '{funnel_name}', '{reason}'],
                    [$stage['name'], $funnel['name'], $reason],
                    $noteTemplate
                );
                
                Activity::create([
                    'conversation_id' => $conversationId,
                    'user_id' => null,
                    'activity_type' => 'stage_change',
                    'content' => json_encode([
                        'from_funnel_id' => $oldFunnelId,
                        'to_funnel_id' => $funnelId,
                        'from_stage_id' => $oldStageId,
                        'to_stage_id' => $stageId,
                        'note' => $noteContent,
                        'by' => 'ai_tool'
                    ]),
                    'is_internal' => true
                ]);
            }
            
            // Disparar automação da etapa se configurado
            if ($triggerAutomation) {
                AutomationService::triggerByStageChange($conversationId, $stageId);
            }
            
            \App\Helpers\ConversationDebug::log($conversationId, "✅ Movido para Funil: {$funnel['name']} / Etapa: {$stage['name']}");
            
            return [
                'success' => true,
                'message' => "Conversa movida para {$funnel['name']} / {$stage['name']}",
                'funnel_id' => $funnelId,
                'funnel_name' => $funnel['name'],
                'stage_id' => $stageId,
                'stage_name' => $stage['name'],
                'reason' => $reason
            ];
            
        } catch (\Exception $e) {
            \App\Helpers\ConversationDebug::error($conversationId, 'executeFunnelStageTool', $e->getMessage());
            return ['error' => 'Erro ao mover para etapa: ' . $e->getMessage()];
        }
    }

    /**
     * Executar Funnel Stage Smart Tool (Mover para Funil/Etapa Inteligente)
     * A IA analisa a conversa e decide qual o melhor funil/etapa baseado nas descrições
     */
    private static function executeFunnelStageSmartTool(array $tool, array $arguments, array $config, int $conversationId, array $context): array
    {
        try {
            \App\Helpers\ConversationDebug::log($conversationId, "🧠 Executando Funnel Stage Smart Tool");
            
            // Configurações
            $maxOptions = (int)($config['max_options'] ?? 30);
            $allowedFunnels = $config['allowed_funnels'] ?? []; // Array de IDs
            $minConfidence = (int)($config['min_confidence'] ?? 70) / 100; // Converter para decimal
            $fallbackFunnelId = $config['fallback_funnel_id'] ?? null;
            $fallbackStageId = $config['fallback_stage_id'] ?? null;
            $fallbackAction = $config['fallback_action'] ?? 'use_fallback';
            $includeHistory = $config['include_history'] ?? true;
            $historyLimit = (int)($config['history_limit'] ?? 10);
            $keepAgent = $config['keep_agent'] ?? true;
            $removeAIAfter = !empty($config['remove_ai_after']);
            $addNote = $config['add_note'] ?? true;
            $triggerAutomation = $config['trigger_automation'] ?? true;
            
            // Contexto passado pela IA
            $aiContext = $arguments['context'] ?? $arguments['reason'] ?? '';
            
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                return ['error' => 'Conversa não encontrada'];
            }
            
            // Buscar funis e etapas com descrições para IA
            $funnelsData = self::getFunnelsWithAIDescriptions($allowedFunnels, $maxOptions);
            
            if (empty($funnelsData)) {
                return ['error' => 'Nenhum funil/etapa encontrado para classificação'];
            }
            
            // Montar contexto da conversa
            $conversationContext = '';
            if ($includeHistory) {
                $messages = Database::fetchAll(
                    "SELECT content, sender_type FROM messages 
                     WHERE conversation_id = ? AND content IS NOT NULL AND content != ''
                     ORDER BY created_at DESC LIMIT ?",
                    [$conversationId, $historyLimit]
                );
                
                $messages = array_reverse($messages);
                foreach ($messages as $msg) {
                    $role = $msg['sender_type'] === 'contact' ? 'Cliente' : 'Atendente';
                    $conversationContext .= "{$role}: {$msg['content']}\n";
                }
            }
            
            // Adicionar contexto passado pela IA
            if ($aiContext) {
                $conversationContext .= "\nObservação da IA: {$aiContext}\n";
            }
            
            // Classificar com OpenAI
            $classification = self::classifyFunnelStage($conversationContext, $funnelsData, $conversationId);
            
            \App\Helpers\ConversationDebug::log($conversationId, "Classificação: " . json_encode($classification));
            
            // Verificar confiança
            $confidence = $classification['confidence'] ?? 0;
            $selectedFunnelId = $classification['funnel_id'] ?? null;
            $selectedStageId = $classification['stage_id'] ?? null;
            $reason = $classification['reason'] ?? 'Classificação automática pela IA';
            
            if ($confidence < $minConfidence || !$selectedFunnelId || !$selectedStageId) {
                \App\Helpers\ConversationDebug::log($conversationId, "Confiança baixa ({$confidence} < {$minConfidence}), usando fallback: {$fallbackAction}");
                
                switch ($fallbackAction) {
                    case 'use_fallback':
                        if ($fallbackFunnelId && $fallbackStageId) {
                            $selectedFunnelId = $fallbackFunnelId;
                            $selectedStageId = $fallbackStageId;
                            $reason = "Classificação com baixa confiança. Usando fallback.";
                        } else {
                            return ['error' => 'Classificação incerta e fallback não configurado'];
                        }
                        break;
                        
                    case 'keep_current':
                        return [
                            'success' => true,
                            'message' => 'Mantendo etapa atual devido a baixa confiança na classificação',
                            'classification_confidence' => $confidence,
                            'action' => 'kept_current'
                        ];
                        
                    case 'ask_client':
                        return [
                            'success' => false,
                            'needs_clarification' => true,
                            'message' => 'Não consegui identificar com certeza. Você pode me dizer mais sobre o que precisa?',
                            'classification_confidence' => $confidence
                        ];
                        
                    case 'escalate':
                        // Chamar tool de escalação
                        return [
                            'success' => false,
                            'needs_escalation' => true,
                            'message' => 'Classificação incerta, recomendo escalar para humano',
                            'classification_confidence' => $confidence
                        ];
                        
                    default:
                        return ['error' => 'Ação de fallback não reconhecida'];
                }
            }
            
            // Verificar se funil e etapa existem
            $funnel = Funnel::find($selectedFunnelId);
            if (!$funnel) {
                return ['error' => 'Funil classificado não encontrado: ' . $selectedFunnelId];
            }
            
            $stage = FunnelStage::find($selectedStageId);
            if (!$stage || $stage['funnel_id'] != $selectedFunnelId) {
                return ['error' => 'Etapa classificada não encontrada ou não pertence ao funil'];
            }
            
            $oldStageId = $conversation['funnel_stage_id'];
            $oldFunnelId = $conversation['funnel_id'];
            
            // Atualizar conversa
            $updateData = [
                'funnel_id' => $selectedFunnelId,
                'funnel_stage_id' => $selectedStageId
            ];
            
            if (!$keepAgent) {
                $updateData['assigned_user_id'] = null;
            }
            
            Conversation::update($conversationId, $updateData);
            
            // Remover IA se configurado
            if ($removeAIAfter) {
                ConversationAIService::removeAIAgent($conversationId);
            }
            
            // Adicionar nota com justificativa da IA
            if ($addNote) {
                Activity::create([
                    'conversation_id' => $conversationId,
                    'user_id' => null,
                    'activity_type' => 'stage_change',
                    'content' => json_encode([
                        'from_funnel_id' => $oldFunnelId,
                        'to_funnel_id' => $selectedFunnelId,
                        'from_stage_id' => $oldStageId,
                        'to_stage_id' => $selectedStageId,
                        'ai_classification' => [
                            'confidence' => $confidence,
                            'reason' => $reason,
                            'funnel_name' => $funnel['name'],
                            'stage_name' => $stage['name']
                        ],
                        'by' => 'ai_smart_tool'
                    ]),
                    'is_internal' => true
                ]);
            }
            
            // Disparar automação
            if ($triggerAutomation) {
                AutomationService::triggerByStageChange($conversationId, $selectedStageId);
            }
            
            \App\Helpers\ConversationDebug::log($conversationId, "✅ Smart: Movido para {$funnel['name']} / {$stage['name']} (confiança: " . round($confidence * 100) . "%)");
            
            return [
                'success' => true,
                'message' => "Conversa classificada e movida para {$funnel['name']} / {$stage['name']}",
                'funnel_id' => $selectedFunnelId,
                'funnel_name' => $funnel['name'],
                'stage_id' => $selectedStageId,
                'stage_name' => $stage['name'],
                'confidence' => $confidence,
                'reason' => $reason
            ];
            
        } catch (\Exception $e) {
            \App\Helpers\ConversationDebug::error($conversationId, 'executeFunnelStageSmartTool', $e->getMessage());
            return ['error' => 'Erro ao classificar/mover: ' . $e->getMessage()];
        }
    }

    /**
     * Buscar funis e etapas com descrições para IA
     */
    private static function getFunnelsWithAIDescriptions(array $allowedFunnelIds = [], int $maxOptions = 30): array
    {
        $sql = "SELECT f.id as funnel_id, f.name as funnel_name, f.description as funnel_description, 
                       f.ai_description as funnel_ai_description,
                       fs.id as stage_id, fs.name as stage_name, fs.description as stage_description,
                       fs.ai_description as stage_ai_description, fs.ai_keywords
                FROM funnels f
                INNER JOIN funnel_stages fs ON f.id = fs.funnel_id
                WHERE f.status = 'active'";
        
        $params = [];
        
        if (!empty($allowedFunnelIds)) {
            $placeholders = implode(',', array_fill(0, count($allowedFunnelIds), '?'));
            $sql .= " AND f.id IN ({$placeholders})";
            $params = array_merge($params, $allowedFunnelIds);
        }
        
        $sql .= " ORDER BY f.name, fs.stage_order, fs.position LIMIT ?";
        $params[] = $maxOptions;
        
        return Database::fetchAll($sql, $params);
    }

    /**
     * Classificar conversa para funil/etapa usando OpenAI
     */
    private static function classifyFunnelStage(string $conversationContext, array $funnelsData, int $conversationId): array
    {
        $apiKey = Setting::get('openai_api_key');
        if (!$apiKey) {
            return ['error' => 'API Key OpenAI não configurada'];
        }
        
        // Montar lista de opções para o prompt
        $optionsText = "";
        $currentFunnel = "";
        
        foreach ($funnelsData as $row) {
            if ($row['funnel_name'] !== $currentFunnel) {
                $currentFunnel = $row['funnel_name'];
                $funnelDesc = $row['funnel_ai_description'] ?: $row['funnel_description'] ?: 'Sem descrição';
                $optionsText .= "\n### Funil: {$row['funnel_name']} (ID: {$row['funnel_id']})\n";
                $optionsText .= "Descrição: {$funnelDesc}\n";
                $optionsText .= "Etapas:\n";
            }
            
            $stageDesc = $row['stage_ai_description'] ?: $row['stage_description'] ?: 'Sem descrição';
            $keywords = $row['ai_keywords'] ? " [Keywords: {$row['ai_keywords']}]" : '';
            $optionsText .= "- {$row['stage_name']} (ID: {$row['stage_id']}): {$stageDesc}{$keywords}\n";
        }
        
        $systemPrompt = <<<PROMPT
Você é um classificador de conversas. Analise o contexto da conversa e determine o melhor funil e etapa para esta conversa.

## Contexto da Conversa:
{$conversationContext}

## Funis e Etapas Disponíveis:
{$optionsText}

## Instruções:
1. Analise o contexto da conversa
2. Identifique a intenção principal do cliente
3. Escolha o funil e etapa mais apropriados
4. Retorne sua análise em JSON

## Responda APENAS em JSON válido:
{
  "funnel_id": <número>,
  "funnel_name": "<nome do funil>",
  "stage_id": <número>,
  "stage_name": "<nome da etapa>",
  "confidence": <número entre 0 e 1>,
  "reason": "<justificativa breve da escolha>"
}
PROMPT;

        try {
            $response = self::makeRequest($apiKey, [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um classificador preciso. Responda apenas em JSON válido.'],
                    ['role' => 'user', 'content' => $systemPrompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 300
            ]);
            
            $content = $response['choices'][0]['message']['content'] ?? '';
            
            // Extrair JSON da resposta
            $content = trim($content);
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $content = $matches[0];
            }
            
            $result = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($result['funnel_id'])) {
                return $result;
            }
            
            \App\Helpers\ConversationDebug::error($conversationId, 'classifyFunnelStage', 'Resposta inválida: ' . $content);
            return ['confidence' => 0, 'error' => 'Resposta inválida da IA'];
            
        } catch (\Exception $e) {
            \App\Helpers\ConversationDebug::error($conversationId, 'classifyFunnelStage', $e->getMessage());
            return ['confidence' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalizar schema de tool para formato correto da OpenAI
     * Corrige problemas como properties: [] ao invés de properties: {}
     */
    private static function normalizeToolSchema(array $schema): array
    {
        // Se é o formato wrapper {type: function, function: {...}}
        if (isset($schema['function'])) {
            $schema['function'] = self::normalizeFunctionSchema($schema['function']);
        } else {
            // É o schema direto
            $schema = self::normalizeFunctionSchema($schema);
        }
        
        return $schema;
    }

    /**
     * Normalizar o schema da função
     */
    private static function normalizeFunctionSchema(array $func): array
    {
        // Corrigir parameters
        if (isset($func['parameters'])) {
            $params = &$func['parameters'];
            
            // Garantir que type é 'object'
            if (!isset($params['type'])) {
                $params['type'] = 'object';
            }
            
            // Corrigir properties: [] para properties: {}
            if (isset($params['properties']) && is_array($params['properties']) && empty($params['properties'])) {
                $params['properties'] = new \stdClass(); // Será convertido para {} no JSON
            }
            
            // Garantir required existe
            if (!isset($params['required'])) {
                $params['required'] = [];
            }
        } else {
            // Adicionar parameters padrão se não existir
            $func['parameters'] = [
                'type' => 'object',
                'properties' => new \stdClass(),
                'required' => []
            ];
        }
        
        return $func;
    }

    /**
     * Obter histórico da conversa formatado para enviar ao N8N
     * Retorna um resumo compacto das últimas mensagens
     */
    private static function getConversationHistoryForN8N(int $conversationId, int $limit = 10): array
    {
        $sql = "SELECT m.content, m.sender_type, m.created_at,
                       CASE 
                           WHEN m.sender_type = 'contact' THEN ct.name
                           WHEN m.sender_type = 'agent' AND m.ai_agent_id IS NOT NULL THEN ai.name
                           WHEN m.sender_type = 'agent' THEN u.name
                           ELSE 'Sistema'
                       END as sender_name
                FROM messages m
                LEFT JOIN contacts ct ON m.sender_type = 'contact' AND m.sender_id = ct.id
                LEFT JOIN users u ON m.sender_type = 'agent' AND m.sender_id = u.id
                LEFT JOIN ai_agents ai ON m.ai_agent_id = ai.id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at DESC
                LIMIT ?";
        
        $messages = \App\Helpers\Database::fetchAll($sql, [$conversationId, $limit]);
        
        if (empty($messages)) {
            return [];
        }
        
        // Inverter para ordem cronológica
        $messages = array_reverse($messages);
        
        // Formatar para envio
        $history = [];
        foreach ($messages as $msg) {
            $role = $msg['sender_type'] === 'contact' ? 'cliente' : 'assistente';
            $content = $msg['content'];
            
            // Truncar mensagens muito longas
            if (strlen($content) > 300) {
                $content = substr($content, 0, 300) . '...';
            }
            
            $history[] = [
                'role' => $role,
                'sender' => $msg['sender_name'],
                'message' => $content,
                'time' => $msg['created_at']
            ];
        }
        
        return $history;
    }

    /**
     * Calcular custo baseado em tokens e modelo
     */
    private static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        // Preços por 1K tokens (atualizados em 2024)
        $prices = [
            'gpt-4' => [
                'prompt' => 0.03,   // $0.03 por 1K tokens
                'completion' => 0.06 // $0.06 por 1K tokens
            ],
            'gpt-4-turbo' => [
                'prompt' => 0.01,
                'completion' => 0.03
            ],
            'gpt-3.5-turbo' => [
                'prompt' => 0.0015,
                'completion' => 0.002
            ]
        ];

        $modelPrices = $prices[$model] ?? $prices['gpt-4'];
        
        $promptCost = ($promptTokens / 1000) * $modelPrices['prompt'];
        $completionCost = ($completionTokens / 1000) * $modelPrices['completion'];
        
        return round($promptCost + $completionCost, 4);
    }

    /**
     * Classificar intenção de forma semântica usando OpenAI
     */
    public static function classifyIntent(string $text, array $intents, float $minConfidence = 0.35, string $context = ''): ?array
    {
        if (empty($intents)) {
            self::logIntentDebug("classify_intent_skip no_intents text='" . $text . "'");
            return null;
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            error_log('OpenAIService::classifyIntent - API Key não configurada');
            self::logIntentDebug("classify_intent_error api_key_missing");
            return null;
        }

        // Preparar intents (nome + descrição)
        $intentList = array_map(function ($intent) {
            return [
                'intent' => $intent['intent'] ?? '',
                'description' => $intent['description'] ?? ''
            ];
        }, $intents);

        $messages = [
            [
                'role' => 'system',
                'content' => 'Você é um classificador de intenções. Considere o CONTEXTO DA CONVERSA e o texto atual do cliente. Escolha o intent mais adequado da lista fornecida e retorne um JSON { "intent": "...", "confidence": 0-1 }. Se não tiver segurança, devolva intent vazio.'
            ],
            [
                'role' => 'user',
                'content' =>
                    "Contexto (recentes):\n" . ($context ?: 'sem contexto') . "\n\n" .
                    "Texto do cliente: \"{$text}\"\n\n" .
                    "Intents disponíveis:\n" . json_encode($intentList, JSON_UNESCAPED_UNICODE)
            ]
        ];

        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 200,
            'response_format' => ['type' => 'json_object']
        ];

        self::logIntentDebug("classify_intent_request model=" . ($payload['model'] ?? '') . " minConf={$minConfidence} text='" . $text . "' context_snip='" . substr($context, 0, 300) . "'");

        try {
            $response = self::makeRequest($apiKey, $payload);
            $assistantMessage = $response['choices'][0]['message'] ?? null;
            $content = $assistantMessage['content'] ?? null;
            self::logIntentDebug("classify_intent_response:" . substr(json_encode($response), 0, 2000));
            if (!$content) {
                self::logIntentDebug("classify_intent_content_empty");
                return null;
            }
            $json = json_decode($content, true);
            if (!$json || empty($json['intent'])) {
                self::logIntentDebug("classify_intent_json_empty content={$content}");
                return null;
            }
            $confidence = isset($json['confidence']) ? (float)$json['confidence'] : 0.0;
            if ($confidence < $minConfidence) {
                self::logIntentDebug("classify_intent_conf_low intent=" . ($json['intent'] ?? '') . " conf={$confidence}");
                return null;
            }

            // Encontrar intent correspondente pelo slug/nome
            foreach ($intents as $intent) {
                $name = $intent['intent'] ?? '';
                if ($name && strcasecmp($name, $json['intent']) === 0) {
                    return $intent;
                }
            }

            // Se não achou match exato, tentar match parcial
            foreach ($intents as $intent) {
                $name = $intent['intent'] ?? '';
                if ($name && stripos($name, $json['intent']) !== false) {
                    return $intent;
                }
            }

            self::logIntentDebug("classify_intent_no_match intent_returned=" . ($json['intent'] ?? ''));
            return null;
        } catch (\Exception $e) {
            error_log('OpenAIService::classifyIntent - erro: ' . $e->getMessage());
            self::logIntentDebug("classify_intent_error:" . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Gerar texto simples com OpenAI
     * Usado para gerar mensagens personalizadas em campanhas
     * 
     * @param string $prompt Prompt do sistema (instruções)
     * @param array $variables Variáveis disponíveis para o prompt
     * @param float $temperature Criatividade (0.0 = determinístico, 1.0 = criativo)
     * @param string $model Modelo a usar (default: gpt-4o-mini por custo)
     * @return string|null Texto gerado ou null em caso de erro
     */
    public static function generateText(string $prompt, array $variables = [], float $temperature = 0.7, string $model = 'gpt-4o-mini'): ?string
    {
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            \App\Helpers\Logger::error('OpenAIService::generateText - API Key não configurada');
            return null;
        }
        
        // Substituir variáveis no prompt
        $processedPrompt = $prompt;
        foreach ($variables as $key => $value) {
            $processedPrompt = str_replace('{{' . $key . '}}', $value ?? '', $processedPrompt);
        }
        
        // Montar contexto com informações do contato
        $contactInfo = [];
        foreach ($variables as $key => $value) {
            if (!empty($value)) {
                $contactInfo[] = ucfirst(str_replace('_', ' ', $key)) . ": " . $value;
            }
        }
        
        $systemPrompt = "Você é um assistente que gera mensagens personalizadas para campanhas de WhatsApp.
REGRAS IMPORTANTES:
1. Gere APENAS o texto da mensagem, sem aspas, sem prefixos, sem explicações
2. A mensagem deve parecer natural e humana
3. Use as informações do contato quando disponíveis
4. Seja criativo e varie o estilo para não parecer robótico
5. Mantenha um tom amigável e profissional
6. NÃO use emojis em excesso (máximo 2)
7. A mensagem deve ser direta e objetiva

Informações do contato:
" . (!empty($contactInfo) ? implode("\n", $contactInfo) : "Nenhuma informação adicional");

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "Gere uma mensagem seguindo estas instruções:\n\n" . $processedPrompt
            ]
        ];
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => max(0, min(1, $temperature)),
            'max_tokens' => 500
        ];
        
        try {
            $response = self::makeRequest($apiKey, $payload);
            $content = $response['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                \App\Helpers\Logger::error('OpenAIService::generateText - Resposta vazia');
                return null;
            }
            
            // Limpar aspas externas se houver
            $content = trim($content);
            if ((str_starts_with($content, '"') && str_ends_with($content, '"')) ||
                (str_starts_with($content, "'") && str_ends_with($content, "'"))) {
                $content = substr($content, 1, -1);
            }
            
            \App\Helpers\Logger::info("OpenAIService::generateText - Mensagem gerada com sucesso (len=" . strlen($content) . ")");
            
            return $content;
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::error('OpenAIService::generateText - Erro: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sugere palavra-chave e localização para prospecção Google Places a partir de uma descrição em linguagem natural.
     * A IA não acessa a internet: apenas traduz o objetivo em termos adequados à API Places (negócios locais).
     *
     * @return array{keyword:string,location:string,alternatives:array<int,array{keyword:string,location:string}>}|null
     */
    public static function suggestGoogleMapsProspectTerms(string $userDescription, string $model = 'gpt-4o-mini'): ?array
    {
        $userDescription = trim($userDescription);
        if ($userDescription === '') {
            return null;
        }

        $descLen = function_exists('mb_strlen') ? mb_strlen($userDescription, 'UTF-8') : strlen($userDescription);
        if ($descLen > 2000) {
            $userDescription = function_exists('mb_substr')
                ? mb_substr($userDescription, 0, 2000, 'UTF-8')
                : substr($userDescription, 0, 2000);
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            \App\Helpers\Logger::error('OpenAIService::suggestGoogleMapsProspectTerms - API Key não configurada');
            return null;
        }

        $system = <<<'SYS'
Você é especialista em prospecção B2B no Brasil. O usuário descreve o público-alvo em linguagem natural.
Sua tarefa é sugerir termos para busca na API Google Places (estabelecimentos no mapa), NÃO para "Google pesquisa web".

Responda APENAS um objeto JSON (sem markdown, sem texto fora do JSON) com exatamente estas chaves:
- "keyword": string curta em português (2 a 8 palavras), o que alguém digitaria no Maps (ex: "clínicas odontológicas", "auto peças caminhão").
- "location": cidade/estado/região em português (ex: "Curitiba, PR"). Se não houver região, use "Brasil".
- "alternatives": array com até 3 objetos {"keyword":"...","location":"..."} com combinações úteis (sinônimos ou nichos próximos).

Não inclua telefones, e-mails ou URLs inventados. Apenas termos de busca e localização.
SYS;

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $userDescription],
            ],
            'temperature' => 0.35,
            'max_tokens' => 500,
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = self::makeRequest($apiKey, $payload);
            $content = $response['choices'][0]['message']['content'] ?? '';
            $decoded = json_decode($content, true);
            if (!is_array($decoded) || empty($decoded['keyword']) || empty($decoded['location'])) {
                \App\Helpers\Logger::error('OpenAIService::suggestGoogleMapsProspectTerms - JSON inválido');
                return null;
            }

            $out = [
                'keyword' => trim((string)$decoded['keyword']),
                'location' => trim((string)$decoded['location']),
                'alternatives' => [],
            ];

            if (!empty($decoded['alternatives']) && is_array($decoded['alternatives'])) {
                foreach ($decoded['alternatives'] as $alt) {
                    if (!is_array($alt)) {
                        continue;
                    }
                    $k = trim((string)($alt['keyword'] ?? ''));
                    $l = trim((string)($alt['location'] ?? ''));
                    if ($k !== '' && $l !== '') {
                        $out['alternatives'][] = ['keyword' => $k, 'location' => $l];
                    }
                    if (count($out['alternatives']) >= 3) {
                        break;
                    }
                }
            }

            return $out;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error('OpenAIService::suggestGoogleMapsProspectTerms - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Gerar mensagem de campanha personalizada para um contato
     * 
     * @param string $prompt Prompt base da campanha
     * @param array $contact Dados do contato
     * @param string|null $baseMessage Mensagem base para referência (opcional)
     * @param float $temperature Temperatura da IA
     * @return string|null Mensagem gerada
     */
    public static function generateCampaignMessage(string $prompt, array $contact, ?string $baseMessage = null, float $temperature = 0.7): ?string
    {
        // Preparar variáveis do contato
        $variables = [
            'nome' => $contact['name'] ?? '',
            'primeiro_nome' => !empty($contact['name']) ? explode(' ', $contact['name'])[0] : '',
            'sobrenome' => $contact['last_name'] ?? '',
            'email' => $contact['email'] ?? '',
            'telefone' => $contact['phone'] ?? '',
            'cidade' => $contact['city'] ?? '',
            'empresa' => $contact['company'] ?? '',
        ];
        
        // Adicionar custom attributes se existirem
        if (!empty($contact['custom_attributes'])) {
            $customAttrs = is_string($contact['custom_attributes']) 
                ? json_decode($contact['custom_attributes'], true) 
                : $contact['custom_attributes'];
            if (is_array($customAttrs)) {
                $variables = array_merge($variables, $customAttrs);
            }
        }
        
        // Incluir mensagem base no prompt se fornecida
        $finalPrompt = $prompt;
        if (!empty($baseMessage)) {
            $finalPrompt .= "\n\nMensagem de referência (use como base, mas varie o estilo):\n" . $baseMessage;
        }
        
        return self::generateText($finalPrompt, $variables, $temperature);
    }
}
