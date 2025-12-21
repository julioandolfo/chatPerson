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
use App\Helpers\Database;

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
        
        try {
            // Obter agente
            $agent = AIAgent::find($agentId);
            if (!$agent || !$agent['enabled']) {
                throw new \Exception('Agente de IA não encontrado ou inativo');
            }

            // Verificar rate limiting e limites de custo
            $costControlCheck = \App\Services\AICostControlService::canProcessMessage($agentId);
            if (!$costControlCheck['allowed']) {
                throw new \Exception($costControlCheck['reason'] ?? 'Limite de rate ou custo atingido');
            }

            // Obter API Key
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('API Key da OpenAI não configurada. Configure em Configurações > OpenAI');
            }

            // Obter tools do agente
            $tools = AIAgent::getTools($agentId);
            $functions = [];
            foreach ($tools as $tool) {
                $functionSchema = is_string($tool['function_schema']) 
                    ? json_decode($tool['function_schema'], true) 
                    : ($tool['function_schema'] ?? []);
                
                if (!empty($functionSchema)) {
                    $functions[] = $functionSchema;
                }
            }

            // Construir mensagens do histórico
            $messages = self::buildMessages($agent, $message, $context);

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
                    return ['type' => 'function', 'function' => $func];
                }, $functions);
            }

            // Fazer requisição à API
            $response = self::makeRequest($apiKey, $payload);
            
            // Processar resposta
            $assistantMessage = $response['choices'][0]['message'] ?? null;
            if (!$assistantMessage) {
                throw new \Exception('Resposta inválida da API OpenAI');
            }

            $content = $assistantMessage['content'] ?? '';
            $toolCalls = $assistantMessage['tool_calls'] ?? null;

            // Se há tool calls, executar e reenviar
            if (!empty($toolCalls)) {
                $functionResults = self::executeToolCalls($toolCalls, $conversationId, $agentId, $context);
                
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
            throw $e;
        }
    }

    /**
     * Construir array de mensagens para a API
     */
    private static function buildMessages(array $agent, string $userMessage, array $context): array
    {
        $messages = [];

        // Mensagem do sistema (prompt do agente)
        $systemPrompt = $agent['prompt'];
        
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

        foreach ($toolCalls as $call) {
            $toolCallId = $call['id'] ?? null;
            $functionName = $call['function']['name'] ?? null;
            $functionArguments = json_decode($call['function']['arguments'] ?? '{}', true);

            if (!$functionName || !$toolCallId) {
                continue;
            }

            try {
                // Buscar tool pelo nome da function
                $tool = AITool::findBySlug($functionName);
                if (!$tool || !$tool['enabled']) {
                    $results[] = [
                        'name' => $functionName,
                        'result' => ['error' => 'Tool não encontrada ou inativa']
                    ];
                    continue;
                }

                // Verificar se tool está atribuída ao agente
                $agentTools = AIAgent::getTools($agentId);
                $toolAssigned = false;
                foreach ($agentTools as $agentTool) {
                    if ($agentTool['id'] == $tool['id']) {
                        $toolAssigned = true;
                        break;
                    }
                }

                if (!$toolAssigned) {
                    $results[] = [
                        'name' => $functionName,
                        'result' => ['error' => 'Tool não atribuída a este agente']
                    ];
                    continue;
                }

                // Executar tool
                $result = self::executeTool($tool, $functionArguments, $conversationId, $context);

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
                error_log("Erro ao executar tool {$functionName}: " . $e->getMessage());
                $results[] = [
                    'tool_call_id' => $toolCallId,
                    'name' => $functionName,
                    'result' => ['error' => $e->getMessage()]
                ];
            }
        }

        return $results;
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
                return self::executeN8NTool($tool, $arguments, $config);
            
            case 'api':
                return self::executeAPITool($tool, $arguments, $config);
            
            case 'document':
                return self::executeDocumentTool($tool, $arguments, $config);
            
            default:
                return ['error' => 'Tipo de tool não suportado: ' . $toolType];
        }
    }

    /**
     * Executar System Tools
     */
    private static function executeSystemTool(array $tool, array $arguments, int $conversationId, array $context): array
    {
        $functionName = $tool['name'] ?? '';

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
                
                if (!$tagId && !$tagName) {
                    return ['error' => 'ID ou nome da tag não fornecido'];
                }
                
                // Se forneceu nome, buscar ID
                if (!$tagId && $tagName) {
                    $tag = \App\Models\Tag::whereFirst('name', '=', $tagName);
                    if (!$tag) {
                        return ['error' => 'Tag não encontrada: ' . $tagName];
                    }
                    $tagId = $tag['id'];
                }
                
                \App\Services\TagService::addToConversation($conversationId, (int)$tagId);
                return ['success' => true, 'message' => 'Tag adicionada com sucesso'];

            case 'mover_para_estagio':
                $stageId = $arguments['stage_id'] ?? null;
                if (!$stageId) {
                    return ['error' => 'ID do estágio não fornecido'];
                }
                
                \App\Services\FunnelService::moveConversation($conversationId, (int)$stageId, null);
                return ['success' => true, 'message' => 'Conversa movida para o estágio'];

            case 'escalar_para_humano':
                // Marcar conversa para escalação
                Conversation::update($conversationId, [
                    'status' => 'open',
                    'assigned_to' => null // Será atribuída automaticamente
                ]);
                
                // Atualizar status da conversa de IA
                $aiConversation = AIConversation::whereFirst('conversation_id', '=', $conversationId);
                if ($aiConversation) {
                    AIConversation::updateStatus($aiConversation['id'], 'escalated');
                }
                
                return ['success' => true, 'message' => 'Conversa escalada para agente humano'];

            default:
                return ['error' => 'System tool não reconhecida: ' . $functionName];
        }
    }

    /**
     * Executar Followup Tools
     */
    private static function executeFollowupTool(array $tool, array $arguments, int $conversationId, array $context): array
    {
        $functionName = $tool['name'] ?? '';

        switch ($functionName) {
            case 'verificar_status_conversa':
                $conversation = Conversation::find($conversationId);
                if (!$conversation) {
                    return ['error' => 'Conversa não encontrada'];
                }
                
                // Buscar última mensagem
                $lastMessage = Database::fetch(
                    "SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 1",
                    [$conversationId]
                );
                
                return [
                    'conversation_id' => $conversationId,
                    'status' => $conversation['status'],
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage['content'],
                        'sender_type' => $lastMessage['sender_type'],
                        'created_at' => $lastMessage['created_at']
                    ] : null,
                    'created_at' => $conversation['created_at'],
                    'updated_at' => $conversation['updated_at']
                ];

            case 'verificar_ultima_interacao':
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
                
                $now = time();
                $lastInteractionTime = strtotime($lastMessage['created_at']);
                $minutesAgo = round(($now - $lastInteractionTime) / 60);
                $hoursAgo = round($minutesAgo / 60);
                $daysAgo = round($hoursAgo / 24);
                
                return [
                    'has_interaction' => true,
                    'last_message' => [
                        'content' => $lastMessage['content'],
                        'sender_type' => $lastMessage['sender_type'],
                        'created_at' => $lastMessage['created_at']
                    ],
                    'time_ago' => [
                        'minutes' => $minutesAgo,
                        'hours' => $hoursAgo,
                        'days' => $daysAgo,
                        'human_readable' => $daysAgo > 0 
                            ? "{$daysAgo} dia(s) atrás"
                            : ($hoursAgo > 0 
                                ? "{$hoursAgo} hora(s) atrás"
                                : "{$minutesAgo} minuto(s) atrás")
                    ]
                ];

            default:
                return ['error' => 'Followup tool não reconhecida: ' . $functionName];
        }
    }

    /**
     * Executar WooCommerce Tools (integração com WooCommerce REST API)
     */
    private static function executeWooCommerceTool(array $tool, array $arguments, array $config): array
    {
        $functionName = $tool['name'] ?? '';
        $wcUrl = $config['woocommerce_url'] ?? null;
        $consumerKey = $config['consumer_key'] ?? null;
        $consumerSecret = $config['consumer_secret'] ?? null;
        
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
                    return ['error' => 'WooCommerce tool não reconhecida: ' . $functionName];
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
    private static function executeN8NTool(array $tool, array $arguments, array $config): array
    {
        $functionName = $tool['name'] ?? '';
        $n8nUrl = $config['n8n_url'] ?? null;
        $webhookId = $config['webhook_id'] ?? null;
        $apiKey = $config['api_key'] ?? null;
        
        if (!$n8nUrl) {
            return ['error' => 'URL do N8N não configurada'];
        }
        
        switch ($functionName) {
            case 'executar_workflow_n8n':
                $workflowId = $arguments['workflow_id'] ?? $webhookId;
                $data = $arguments['data'] ?? [];
                
                if (!$workflowId) {
                    return ['error' => 'ID do workflow não fornecido'];
                }
                
                // Construir URL do webhook
                $webhookUrl = rtrim($n8nUrl, '/') . '/webhook/' . $workflowId;
                
                // Preparar headers
                $headers = [
                    'Content-Type: application/json'
                ];
                
                if ($apiKey) {
                    $headers[] = 'X-N8N-API-KEY: ' . $apiKey;
                }
                
                // Fazer requisição POST ao webhook
                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($data),
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => 60, // N8N pode demorar mais
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    return ['error' => 'Erro ao executar workflow N8N: ' . $error];
                }
                
                $responseData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $responseData = ['raw_response' => $response];
                }
                
                return [
                    'success' => $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'workflow_id' => $workflowId,
                    'response' => $responseData
                ];
            
            case 'buscar_dados_n8n':
                $endpoint = $arguments['endpoint'] ?? null;
                $queryParams = $arguments['query_params'] ?? [];
                
                if (!$endpoint) {
                    return ['error' => 'Endpoint não fornecido'];
                }
                
                // Construir URL
                $url = rtrim($n8nUrl, '/') . '/api/v1/' . ltrim($endpoint, '/');
                if (!empty($queryParams)) {
                    $url .= '?' . http_build_query($queryParams);
                }
                
                // Preparar headers
                $headers = [];
                if ($apiKey) {
                    $headers[] = 'X-N8N-API-KEY: ' . $apiKey;
                }
                
                // Fazer requisição GET
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($error) {
                    return ['error' => 'Erro ao buscar dados do N8N: ' . $error];
                }
                
                $responseData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $responseData = ['raw_response' => $response];
                }
                
                return [
                    'success' => $httpCode >= 200 && $httpCode < 300,
                    'http_code' => $httpCode,
                    'data' => $responseData
                ];
            
            default:
                return ['error' => 'N8N tool não reconhecida: ' . $functionName];
        }
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
            return null;
        }

        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            error_log('OpenAIService::classifyIntent - API Key não configurada');
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
}
