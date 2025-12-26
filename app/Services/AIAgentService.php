<?php
/**
 * Service AIAgentService
 * Lógica de negócio para agentes de IA
 */

namespace App\Services;

use App\Models\AIAgent;
use App\Models\AITool;
use App\Helpers\Validator;

class AIAgentService
{
    /**
     * Buffer de mensagens por conversa (para timer de contexto)
     * Estrutura: [conversation_id => ['messages' => [...], 'timer_start' => timestamp, 'agent_id' => X, 'scheduled' => bool]]
     */
    private static array $messageBuffers = [];
    
    /**
     * Locks por conversa (para evitar processamento duplicado)
     */
    private static array $processingLocks = [];
    
    /**
     * Criar agente de IA
     */
    public static function create(array $data): int
    {
        // Converter valores antes da validação
        // Converter max_conversations vazio para null ANTES da validação
        if (isset($data['max_conversations']) && ($data['max_conversations'] === '' || $data['max_conversations'] === null)) {
            $data['max_conversations'] = null;
        } elseif (isset($data['max_conversations']) && $data['max_conversations'] !== null) {
            // Converter para int se tiver valor
            $data['max_conversations'] = (int)$data['max_conversations'];
        }
        
        // Converter temperature para float se presente
        if (isset($data['temperature']) && $data['temperature'] !== '' && $data['temperature'] !== null) {
            $data['temperature'] = (float)$data['temperature'];
        }
        
        // Converter max_tokens para int se presente
        if (isset($data['max_tokens']) && $data['max_tokens'] !== '' && $data['max_tokens'] !== null) {
            $data['max_tokens'] = (int)$data['max_tokens'];
        }
        
        // Processar settings com delay humanizado
        $settings = [];
        if (isset($data['settings']) && is_string($data['settings'])) {
            $settings = json_decode($data['settings'], true) ?? [];
        } elseif (isset($data['settings']) && is_array($data['settings'])) {
            $settings = $data['settings'];
        }
        
        // Adicionar delay humanizado às settings
        if (isset($data['response_delay_min'])) {
            $settings['response_delay_min'] = (int)$data['response_delay_min'];
            unset($data['response_delay_min']);
        }
        if (isset($data['response_delay_max'])) {
            $settings['response_delay_max'] = (int)$data['response_delay_max'];
            unset($data['response_delay_max']);
        }
        
        // ✅ NOVO: Adicionar timer de contexto às settings
        if (isset($data['context_timer_seconds'])) {
            $settings['context_timer_seconds'] = (int)$data['context_timer_seconds'];
            unset($data['context_timer_seconds']);
        }
        
        if (!empty($settings)) {
            $data['settings'] = json_encode($settings, JSON_UNESCAPED_UNICODE);
        }

        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agent_type' => 'required|string|in:SDR,CS,CLOSER,FOLLOWUP,SUPPORT,ONBOARDING,GENERAL',
            'prompt' => 'required|string',
            'model' => 'nullable|string|max:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'enabled' => 'nullable|boolean',
            'max_conversations' => 'nullable|integer|min:1',
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Valores padrão
        $data['model'] = $data['model'] ?? 'gpt-4';
        $data['temperature'] = $data['temperature'] ?? 0.7;
        $data['max_tokens'] = $data['max_tokens'] ?? 2000;
        $data['enabled'] = isset($data['enabled']) ? (bool)$data['enabled'] : true;
        $data['current_conversations'] = 0;

        // Serializar settings se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        return AIAgent::create($data);
    }

    /**
     * Atualizar agente de IA
     */
    public static function update(int $id, array $data): bool
    {
        $agent = AIAgent::find($id);
        if (!$agent) {
            throw new \Exception('Agente de IA não encontrado');
        }

        // Converter valores antes da validação
        // Converter max_conversations vazio para null ANTES da validação
        if (isset($data['max_conversations']) && ($data['max_conversations'] === '' || $data['max_conversations'] === null)) {
            $data['max_conversations'] = null;
        } elseif (isset($data['max_conversations']) && $data['max_conversations'] !== null) {
            // Converter para int se tiver valor
            $data['max_conversations'] = (int)$data['max_conversations'];
        }
        
        // Converter temperature para float se presente
        if (isset($data['temperature']) && $data['temperature'] !== '' && $data['temperature'] !== null) {
            $data['temperature'] = (float)$data['temperature'];
        }
        
        // Converter max_tokens para int se presente
        if (isset($data['max_tokens']) && $data['max_tokens'] !== '' && $data['max_tokens'] !== null) {
            $data['max_tokens'] = (int)$data['max_tokens'];
        }
        
        // Processar settings com delay humanizado (merge com existentes)
        $existingSettings = [];
        if (!empty($agent['settings'])) {
            $existingSettings = is_string($agent['settings']) 
                ? (json_decode($agent['settings'], true) ?? []) 
                : ($agent['settings'] ?? []);
        }
        
        // Adicionar delay humanizado às settings
        if (isset($data['response_delay_min'])) {
            $existingSettings['response_delay_min'] = (int)$data['response_delay_min'];
            unset($data['response_delay_min']);
        }
        if (isset($data['response_delay_max'])) {
            $existingSettings['response_delay_max'] = (int)$data['response_delay_max'];
            unset($data['response_delay_max']);
        }
        
        if (!empty($existingSettings)) {
            $data['settings'] = json_encode($existingSettings, JSON_UNESCAPED_UNICODE);
        }

        $errors = Validator::validate($data, [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'agent_type' => 'nullable|string|in:SDR,CS,CLOSER,FOLLOWUP,SUPPORT,ONBOARDING,GENERAL',
            'prompt' => 'nullable|string',
            'model' => 'nullable|string|max:100',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'enabled' => 'nullable|boolean',
            'max_conversations' => 'nullable|integer|min:1',
        ]);

        if (!empty($errors)) {
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        // Converter enabled para boolean se presente
        if (isset($data['enabled'])) {
            $data['enabled'] = (bool)$data['enabled'];
        }

        // Serializar settings se for array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        return AIAgent::update($id, $data);
    }

    /**
     * Listar agentes
     */
    public static function list(array $filters = []): array
    {
        $sql = "SELECT * FROM ai_agents WHERE 1=1";
        $params = [];

        if (!empty($filters['agent_type'])) {
            $sql .= " AND agent_type = ?";
            $params[] = $filters['agent_type'];
        }

        if (isset($filters['enabled'])) {
            $sql .= " AND enabled = ?";
            $params[] = $filters['enabled'] ? 1 : 0;
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (name LIKE ? OR description LIKE ?)";
            $search = "%{$filters['search']}%";
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY name ASC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }

        return \App\Helpers\Database::fetchAll($sql, $params);
    }

    /**
     * Obter agente com tools
     */
    public static function get(int $id): ?array
    {
        $agent = AIAgent::find($id);
        if (!$agent) {
            return null;
        }

        try {
            $agent['tools'] = AIAgent::getTools($id);
        } catch (\Exception $e) {
            error_log("Erro ao buscar tools do agente {$id}: " . $e->getMessage());
            $agent['tools'] = [];
        }

        try {
            $agent['stats'] = \App\Models\AIConversation::getAgentStats($id);
        } catch (\Exception $e) {
            error_log("Erro ao buscar estatísticas do agente {$id}: " . $e->getMessage());
            $agent['stats'] = [];
        }

        return $agent;
    }

    /**
     * Adicionar tool ao agente
     */
    public static function addTool(int $agentId, int $toolId, array $config = [], bool $enabled = true): bool
    {
        return AIAgent::addTool($agentId, $toolId, $config, $enabled);
    }

    /**
     * Remover tool do agente
     */
    public static function removeTool(int $agentId, int $toolId): bool
    {
        return AIAgent::removeTool($agentId, $toolId);
    }

    /**
     * Processar conversa com agente de IA (quando conversa é atribuída)
     */
    public static function processConversation(int $conversationId, int $agentId): void
    {
        try {
            $conversation = \App\Models\Conversation::findWithRelations($conversationId);
            if (!$conversation) {
                throw new \Exception('Conversa não encontrada');
            }

            // Verificar se há mensagens do contato para processar
            $messages = \App\Models\Message::where('conversation_id', '=', $conversationId);
            $contactMessages = array_filter($messages, function($msg) {
                return $msg['sender_type'] === 'contact';
            });

            if (!empty($contactMessages)) {
                // Processar última mensagem do contato
                $lastMessage = end($contactMessages);
                self::processMessage($conversationId, $agentId, $lastMessage['content']);
            } else {
                // Se não há mensagens, enviar mensagem de boas-vindas se configurado
                $agent = AIAgent::find($agentId);
                if ($agent && !empty($agent['settings'])) {
                    $settings = is_string($agent['settings']) 
                        ? json_decode($agent['settings'], true) 
                        : $agent['settings'];
                    
                    if (isset($settings['welcome_message']) && !empty($settings['welcome_message'])) {
                        \App\Services\ConversationService::sendMessage(
                            $conversationId,
                            $settings['welcome_message'],
                            'agent',
                            null,
                            []
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Erro ao processar conversa com agente de IA: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Processar mensagem com agente
     */
    public static function processMessage(int $conversationId, int $agentId, string $message): array
    {
        \App\Helpers\Logger::info("═══ AIAgentService::processMessage INÍCIO ═══ conv={$conversationId}, agent={$agentId}, msgLen=" . strlen($message));
        
        // Obter contexto da conversa
        $conversation = \App\Models\Conversation::findWithRelations($conversationId);
        $contact = \App\Models\Contact::find($conversation['contact_id'] ?? null);
        $agent = \App\Models\AIAgent::find($agentId);
        
        $context = [
            'conversation' => $conversation,
            'contact' => $contact ? [
                'name' => $contact['name'],
                'email' => $contact['email'],
                'phone' => $contact['phone']
            ] : null,
            'user_message' => $message, // Mensagem do usuário para passar às tools
            'agent' => $agent ? [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'persona' => $agent['persona'] ?? null,
                'prompt' => $agent['prompt'] ?? null
            ] : null
        ];

        // Processar com OpenAI
        try {
            \App\Helpers\Logger::info("AIAgentService::processMessage - Chamando OpenAIService::processMessage");
            $response = OpenAIService::processMessage($conversationId, $agentId, $message, $context);
            \App\Helpers\Logger::info("AIAgentService::processMessage - OpenAI respondeu (contentLen=" . strlen($response['content'] ?? '') . ")");

            // Delay humanizado antes de responder (configurável por agente)
            $settings = is_string($agent['settings'] ?? null) 
                ? json_decode($agent['settings'], true) 
                : ($agent['settings'] ?? []);
            
            $minDelay = (int)($settings['response_delay_min'] ?? 0); // segundos
            $maxDelay = (int)($settings['response_delay_max'] ?? 0); // segundos
            
            if ($minDelay > 0 && $maxDelay >= $minDelay) {
                $delay = rand($minDelay, $maxDelay);
                \App\Helpers\ConversationDebug::log($conversationId, 'delay', "Delay humanizado: {$delay}s (min: {$minDelay}, max: {$maxDelay})");
                sleep($delay);
            }

            // ✅ NOVO: Verificar se cliente pediu áudio explicitamente
            $clientMessageLower = mb_strtolower($message);
            $audioRequestKeywords = [
                'manda um áudio', 'manda um audio', 'envia um áudio', 'envia um audio',
                'manda áudio', 'manda audio', 'envia áudio', 'envia audio',
                'quero áudio', 'quero audio', 'preciso de áudio', 'preciso de audio',
                'manda em áudio', 'manda em audio', 'envia em áudio', 'envia em audio',
                'não estou conseguindo ler', 'não consigo ler', 'não consigo ler o texto',
                'prefiro áudio', 'prefiro audio', 'gostaria de áudio', 'gostaria de audio',
                'pode mandar áudio', 'pode mandar audio', 'pode enviar áudio', 'pode enviar audio',
                'me manda um áudio', 'me manda um audio', 'me envia um áudio', 'me envia um audio'
            ];
            
            $clientRequestedAudio = false;
            foreach ($audioRequestKeywords as $keyword) {
                if (stripos($clientMessageLower, $keyword) !== false) {
                    $clientRequestedAudio = true;
                    \App\Helpers\Logger::info("AIAgentService::processMessage - 🎤 Cliente PEDIU explicitamente um áudio!");
                    break;
                }
            }
            
            // ✅ NOVO: Gerar áudio se TTS estiver habilitado E (auto_generate_audio OU cliente pediu)
            $audioAttachment = null;
            $ttsSettings = \App\Services\TTSService::getSettings();
            
            \App\Helpers\Logger::info("AIAgentService::processMessage - ⚙️ TTS Settings: enabled=" . ($ttsSettings['enabled'] ? 'YES' : 'NO') . ", auto=" . ($ttsSettings['auto_generate_audio'] ? 'YES' : 'NO') . ", provider=" . ($ttsSettings['provider'] ?? 'none') . ", clientRequested=" . ($clientRequestedAudio ? 'YES' : 'NO'));
            
            // Gerar áudio se: (TTS habilitado E auto_generate) OU (TTS habilitado E cliente pediu)
            $shouldGenerateAudio = !empty($ttsSettings['enabled']) && (
                !empty($ttsSettings['auto_generate_audio']) || 
                $clientRequestedAudio
            );
            
            if ($shouldGenerateAudio) {
                try {
                    \App\Helpers\Logger::info("AIAgentService::processMessage - 🎤 Gerando áudio com TTS (provider=" . ($ttsSettings['provider'] ?? 'openai') . ", len=" . strlen($response['content']) . ")");
                    \App\Helpers\Logger::info("AIAgentService::processMessage - 🎤 TTS Options: voice=" . ($ttsSettings['voice_id'] ?? 'null') . ", model=" . ($ttsSettings['model'] ?? 'null') . ", lang=" . ($ttsSettings['language'] ?? 'null') . ", speed=" . ($ttsSettings['speed'] ?? 'null'));
                    
                    $ttsResult = \App\Services\TTSService::generateAudio($response['content'], [
                        'voice_id' => $ttsSettings['voice_id'] ?? null,
                        'model' => $ttsSettings['model'] ?? null,
                        'language' => $ttsSettings['language'] ?? 'pt',
                        'speed' => $ttsSettings['speed'] ?? 1.0,
                        'stability' => $ttsSettings['stability'] ?? 0.5,
                        'similarity_boost' => $ttsSettings['similarity_boost'] ?? 0.75,
                        'convert_to_whatsapp_format' => $ttsSettings['convert_to_whatsapp_format'] ?? true
                    ]);
                    
                    \App\Helpers\Logger::info("AIAgentService::processMessage - 🎤 TTS Result: success=" . ($ttsResult['success'] ? 'YES' : 'NO') . ", error=" . ($ttsResult['error'] ?? 'none'));
                    
                    if ($ttsResult['success'] && !empty($ttsResult['audio_path'])) {
                        \App\Helpers\Logger::info("AIAgentService::processMessage - 🎤 Audio file exists: " . (file_exists($ttsResult['audio_path']) ? 'YES' : 'NO') . ", size=" . (file_exists($ttsResult['audio_path']) ? filesize($ttsResult['audio_path']) : '0'));
                        
                        // Criar attachment para o áudio
                        // ✅ CORRIGIDO: Usar caminho relativo correto e adicionar URL
                        $audioAttachment = [
                            'path' => ltrim($ttsResult['audio_url'], '/'), // Remove / inicial para consistência
                            'url' => $ttsResult['audio_url'], // ✅ NOVO: URL completa para renderização no chat
                            'type' => 'audio',
                            'mime_type' => 'audio/ogg; codecs=opus', // ✅ CORRIGIDO: MIME type completo com codec
                            'mimetype' => 'audio/ogg; codecs=opus', // Compatibilidade
                            'filename' => basename($ttsResult['audio_path']),
                            'size' => filesize($ttsResult['audio_path']),
                            'extension' => 'ogg',
                            // ✅ NOVO: Adicionar texto original para exibir como "transcrição"
                            'tts_original_text' => $response['content']
                        ];
                        
                        \App\Helpers\Logger::info("AIAgentService::processMessage - ✅ Áudio gerado: " . $ttsResult['audio_path'] . " (cost=$" . $ttsResult['cost'] . ", url=" . $ttsResult['audio_url'] . ")");
                    } else {
                        \App\Helpers\Logger::error("AIAgentService::processMessage - ❌ FALHA ao gerar áudio!");
                        \App\Helpers\Logger::error("AIAgentService::processMessage - ❌ Error details: " . json_encode($ttsResult));
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("AIAgentService::processMessage - ❌ EXCEPTION ao gerar áudio: " . $e->getMessage());
                    \App\Helpers\Logger::error("AIAgentService::processMessage - ❌ Stack trace: " . $e->getTraceAsString());
                    // Continuar mesmo se falhar
                }
            }

            // ✅ NOVO: Decidir conteúdo da mensagem baseado no modo de envio
            $sendMode = $ttsSettings['send_mode'] ?? 'intelligent'; // 'text_only', 'audio_only', 'both', 'intelligent'
            $messageContent = $response['content'];
            $attachments = [];
            $messageType = null;
            
            \App\Helpers\Logger::info("AIAgentService::processMessage - ANTES de decidir envio (audio=" . (!empty($audioAttachment) ? 'SIM' : 'NÃO') . ", mode={$sendMode}, contentLen=" . strlen($messageContent) . ")");
            
            // ✅ FALLBACK FINAL: Se não há áudio, forçar modo text_only
            if (empty($audioAttachment) && $shouldGenerateAudio) {
                \App\Helpers\Logger::warning("AIAgentService::processMessage - ⚠️ TTS estava habilitado mas falhou, forçando text_only");
                $sendMode = 'text_only';
            }
            
            // Se modo é 'intelligent' ou 'adaptive', decidir automaticamente
            if (in_array($sendMode, ['intelligent', 'adaptive']) && $audioAttachment) {
                $intelligentRules = $ttsSettings['intelligent_rules'] ?? [];
                
                // ✅ CORREÇÃO: As regras já estão dentro de intelligent_rules, não precisa mover
                \App\Helpers\Logger::info("AIAgentService::processMessage - Regras inteligentes: " . json_encode([
                    'first_msg_text' => ($intelligentRules['first_message_always_text'] ?? false),
                    'mode' => $sendMode
                ]));
                
                // ✅ NOVO: Passar também a mensagem do cliente para detectar solicitações
                $sendMode = \App\Services\TTSIntelligentService::decideSendMode(
                    $response['content'],
                    $conversationId,
                    $intelligentRules,
                    $message // Passar mensagem do cliente para detecção
                );
                
                \App\Helpers\Logger::info("AIAgentService::processMessage - Modo inteligente escolheu: {$sendMode}");
            }
            
            // ✅ FALLBACK FINAL: Se não há áudio mas TTS estava habilitado, forçar text_only
            if (empty($audioAttachment) && $shouldGenerateAudio) {
                \App\Helpers\Logger::warning("AIAgentService::processMessage - ⚠️ TTS estava habilitado mas falhou (ElevenLabs + OpenAI), forçando text_only");
                $sendMode = 'text_only';
            }
            
            if ($audioAttachment) {
                // Aplicar modo de envio decidido
                switch ($sendMode) {
                    case 'audio_only':
                        // Enviar SOMENTE áudio (sem texto)
                        $messageContent = ''; // Texto vazio
                        $attachments = [$audioAttachment];
                        $messageType = 'audio';
                        break;
                    
                    case 'both':
                        // Enviar texto + áudio (texto como legenda)
                        $attachments = [$audioAttachment];
                        $messageType = 'audio';
                        break;
                    
                    case 'text_only':
                    default:
                        // Enviar SOMENTE texto (ignorar áudio gerado)
                        $attachments = [];
                        $messageType = null;
                        break;
                }
            }
            
            \App\Helpers\Logger::info("AIAgentService::processMessage - ANTES de sendMessage (contentLen=" . strlen($messageContent) . ", attachments=" . count($attachments) . ", type={$messageType})");
            
            // Criar mensagem na conversa
            $aiMessageId = \App\Services\ConversationService::sendMessage(
                $conversationId,
                $messageContent,
                'agent',
                null, // AI agent não tem user_id
                $attachments,
                $messageType,
                null, // quotedMessageId
                $agentId // aiAgentId
            );
            
            \App\Helpers\Logger::info("AIAgentService::processMessage - DEPOIS de sendMessage (aiMessageId={$aiMessageId})");

            // ✅ REMOVIDO: Verificação de intent na resposta da IA
            // Agora a verificação é feita ANTES de chamar a IA, na mensagem do cliente
            // Isso evita que a IA responda antes de detectar a intenção

            \App\Helpers\Logger::info("═══ AIAgentService::processMessage SUCESSO ═══ conv={$conversationId}, aiMessageId={$aiMessageId}");
            
            return $response;
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("═══ AIAgentService::processMessage ERRO ═══ conv={$conversationId}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            
            error_log("Erro ao processar mensagem com agente de IA: " . $e->getMessage());
            
            // Log de debug detalhado
            \App\Helpers\ConversationDebug::error($conversationId, 'AIAgentService::processMessage', $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            // Enviar mensagem de erro ao usuário
            \App\Services\ConversationService::sendMessage(
                $conversationId,
                "Desculpe, ocorreu um erro ao processar sua mensagem. Por favor, tente novamente ou entre em contato com um agente humano.",
                'agent',
                null,
                []
            );
            
            throw $e;
        }
    }
    
    /**
     * 🆕 Adicionar mensagem ao buffer e agendar processamento (timer de contexto)
     * 
     * @param int $conversationId ID da conversa
     * @param int $agentId ID do agente de IA
     * @param string $message Conteúdo da mensagem
     * @return void
     */
    public static function bufferMessage(int $conversationId, int $agentId, string $message): void
    {
        \App\Helpers\Logger::info("AIAgentService::bufferMessage - Adicionando mensagem ao buffer (conv={$conversationId}, agent={$agentId}, msgLen=" . strlen($message) . ")");
        
        // Verificar se há buffer antigo que precisa ser processado primeiro
        self::checkAndProcessOldBuffers($conversationId);
        
        // Obter configurações do agente
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            \App\Helpers\Logger::error("AIAgentService::bufferMessage - Agente não encontrado: {$agentId}");
            // Fallback: processar imediatamente
            self::processMessage($conversationId, $agentId, $message);
            return;
        }
        
        $settings = is_string($agent['settings'] ?? null) 
            ? json_decode($agent['settings'], true) 
            : ($agent['settings'] ?? []);
        
        $contextTimer = (int)($settings['context_timer_seconds'] ?? 5); // Padrão: 5 segundos
        
        // Se timer for 0, processar imediatamente (sem buffer)
        if ($contextTimer <= 0) {
            \App\Helpers\Logger::info("AIAgentService::bufferMessage - Timer desabilitado (0), processando imediatamente");
            self::processMessage($conversationId, $agentId, $message);
            return;
        }
        
        $now = time();
        $wasScheduled = false;
        
        // Verificar se já existe buffer
        if (isset(self::$messageBuffers[$conversationId])) {
            $wasScheduled = self::$messageBuffers[$conversationId]['scheduled'];
        }
        
        // Inicializar buffer se não existir
        if (!isset(self::$messageBuffers[$conversationId])) {
            self::$messageBuffers[$conversationId] = [
                'messages' => [],
                'timer_start' => $now,
                'agent_id' => $agentId,
                'timer_seconds' => $contextTimer,
                'scheduled' => false
            ];
        }
        
        // Adicionar mensagem ao buffer
        self::$messageBuffers[$conversationId]['messages'][] = [
            'content' => $message,
            'timestamp' => $now
        ];
        
        // Reiniciar timer (última mensagem recebida)
        self::$messageBuffers[$conversationId]['timer_start'] = $now;
        
        \App\Helpers\Logger::info("AIAgentService::bufferMessage - Mensagem adicionada ao buffer (total: " . count(self::$messageBuffers[$conversationId]['messages']) . ", timer: {$contextTimer}s)");
        
        // Se já há um timer agendado, não criar outro (apenas reiniciar timer)
        if ($wasScheduled) {
            \App\Helpers\Logger::info("AIAgentService::bufferMessage - Timer já agendado, apenas reiniciado");
            return;
        }
        
        // Marcar como agendado
        self::$messageBuffers[$conversationId]['scheduled'] = true;
        
        // Agendar processamento após timer (usar execução assíncrona)
        self::scheduleProcessing($conversationId, $contextTimer);
    }
    
    /**
     * 🆕 Agendar processamento após timer de contexto
     * Usa execução em background não-bloqueante
     * 
     * @param int $conversationId ID da conversa
     * @param int $timerSeconds Segundos para aguardar
     * @return void
     */
    private static function scheduleProcessing(int $conversationId, int $timerSeconds): void
    {
        \App\Helpers\Logger::info("AIAgentService::scheduleProcessing - Agendando processamento (conv={$conversationId}, timer={$timerSeconds}s)");
        
        // ✅ CORRIGIDO: Obter dados do buffer ANTES de criar o script
        if (!isset(self::$messageBuffers[$conversationId])) {
            \App\Helpers\Logger::warning("AIAgentService::scheduleProcessing - Buffer não encontrado para conv={$conversationId}");
            return;
        }
        
        $buffer = self::$messageBuffers[$conversationId];
        $agentId = $buffer['agent_id'];
        $messages = array_map(function($msg) {
            return $msg['content'];
        }, $buffer['messages']);
        $groupedMessage = implode("\n\n", $messages);
        
        // Criar script PHP temporário que aguarda e processa
        $tempScript = sys_get_temp_dir() . '/ai_buffer_' . $conversationId . '_' . time() . '.php';
        
        // Obter caminho base do projeto
        $basePath = dirname(__DIR__, 2); // Volta 2 níveis de app/Services para raiz
        
        $phpCode = "<?php\n";
        $phpCode .= "// Auto-delete após execução\n";
        $phpCode .= "\$scriptPath = __FILE__;\n";
        $phpCode .= "register_shutdown_function(function() use (\$scriptPath) {\n";
        $phpCode .= "    if (file_exists(\$scriptPath)) {\n";
        $phpCode .= "        @unlink(\$scriptPath);\n";
        $phpCode .= "    }\n";
        $phpCode .= "});\n\n";
        $phpCode .= "\$basePath = " . var_export($basePath, true) . ";\n";
        $phpCode .= "require_once \$basePath . '/vendor/autoload.php';\n";
        $phpCode .= "require_once \$basePath . '/app/Helpers/Database.php';\n";
        $phpCode .= "require_once \$basePath . '/app/Services/AIAgentService.php';\n\n";
        $phpCode .= "sleep({$timerSeconds});\n\n";
        $phpCode .= "// ✅ CORRIGIDO: Passar dados diretamente em vez de usar buffer estático\n";
        $phpCode .= "\$conversationId = " . (int)$conversationId . ";\n";
        $phpCode .= "\$agentId = " . (int)$agentId . ";\n";
        $phpCode .= "\$groupedMessage = " . var_export($groupedMessage, true) . ";\n\n";
        $phpCode .= "try {\n";
        $phpCode .= "    \App\Helpers\Logger::info('Background script - Processando mensagem agrupada (conv=' . \$conversationId . ', agent=' . \$agentId . ', msgLen=' . strlen(\$groupedMessage) . ')');\n";
        $phpCode .= "    \\App\\Services\\AIAgentService::processMessage(\$conversationId, \$agentId, \$groupedMessage);\n";
        $phpCode .= "    \App\Helpers\Logger::info('Background script - ✅ Processamento concluído');\n";
        $phpCode .= "} catch (\\Exception \$e) {\n";
        $phpCode .= "    \App\Helpers\Logger::error('Background script - ❌ Erro: ' . \$e->getMessage());\n";
        $phpCode .= "    error_log('Erro ao processar buffer: ' . \$e->getMessage());\n";
        $phpCode .= "}\n";
        
        file_put_contents($tempScript, $phpCode);
        
        // ✅ CORRIGIDO: Limpar buffer AGORA (dados já foram passados para o script)
        \App\Helpers\Logger::info("AIAgentService::scheduleProcessing - Limpando buffer (dados já salvos no script)");
        unset(self::$messageBuffers[$conversationId]);
        
        // Executar em background (não-bloqueante)
        $phpExecutable = PHP_BINARY;
        $command = escapeshellarg($phpExecutable) . ' ' . escapeshellarg($tempScript);
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: usar start /B para background
            $command = "start /B {$command} > NUL 2>&1";
            pclose(popen($command, 'r'));
        } else {
            // Linux/Unix: usar & para background
            $command .= ' > /dev/null 2>&1 &';
            exec($command);
        }
        
        \App\Helpers\Logger::info("AIAgentService::scheduleProcessing - ✅ Processamento agendado em background (script: {$tempScript}, msgCount=" . count($messages) . ", totalLen=" . strlen($groupedMessage) . ")");
    }
    
    /**
     * 🆕 Processar mensagens do buffer (chamado após timer expirar)
     * 
     * @param int $conversationId ID da conversa
     * @return void
     */
    public static function processBufferedMessages(int $conversationId): void
    {
        \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - INÍCIO (conv={$conversationId})");
        
        // Verificar se há lock (evitar processamento duplicado)
        if (isset(self::$processingLocks[$conversationId])) {
            \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - Já está processando, ignorando");
            return;
        }
        
        // Verificar se há buffer
        if (!isset(self::$messageBuffers[$conversationId])) {
            \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - Buffer não encontrado, ignorando");
            return;
        }
        
        $buffer = self::$messageBuffers[$conversationId];
        
        // Verificar se timer realmente expirou (pode ter sido reiniciado)
        $elapsed = time() - $buffer['timer_start'];
        if ($elapsed < $buffer['timer_seconds']) {
            \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - Timer ainda não expirou ({$elapsed}s < {$buffer['timer_seconds']}s), reagendando...");
            // Reagendar para o tempo restante
            $remaining = $buffer['timer_seconds'] - $elapsed;
            self::$messageBuffers[$conversationId]['scheduled'] = false;
            self::scheduleProcessing($conversationId, $remaining);
            return;
        }
        
        // Adicionar lock
        self::$processingLocks[$conversationId] = true;
        
        try {
            // Verificar se há mensagens no buffer
            if (empty($buffer['messages'])) {
                \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - Buffer vazio, ignorando");
                unset(self::$messageBuffers[$conversationId]);
                return;
            }
            
            // Agrupar mensagens em uma única string
            $messages = array_map(function($msg) {
                return $msg['content'];
            }, $buffer['messages']);
            
            $groupedMessage = implode("\n\n", $messages);
            
            \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - Processando " . count($buffer['messages']) . " mensagens agrupadas (totalLen=" . strlen($groupedMessage) . ")");
            
            // Limpar buffer ANTES de processar (evitar reprocessamento)
            $agentId = $buffer['agent_id'];
            unset(self::$messageBuffers[$conversationId]);
            
            // Processar mensagem agrupada
            self::processMessage($conversationId, $agentId, $groupedMessage);
            
            \App\Helpers\Logger::info("AIAgentService::processBufferedMessages - ✅ SUCESSO");
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("AIAgentService::processBufferedMessages - ❌ ERRO: " . $e->getMessage());
            \App\Helpers\Logger::error("AIAgentService::processBufferedMessages - Stack trace: " . $e->getTraceAsString());
            // Limpar buffer mesmo em caso de erro
            unset(self::$messageBuffers[$conversationId]);
        } finally {
            // Remover lock
            unset(self::$processingLocks[$conversationId]);
        }
    }
    
    /**
     * 🆕 Verificar e processar buffers pendentes (chamado quando nova mensagem chega)
     * Verifica se há buffer antigo que precisa ser processado
     * 
     * @param int $conversationId ID da conversa
     * @return void
     */
    public static function checkAndProcessOldBuffers(int $conversationId): void
    {
        if (!isset(self::$messageBuffers[$conversationId])) {
            return;
        }
        
        $buffer = self::$messageBuffers[$conversationId];
        $elapsed = time() - $buffer['timer_start'];
        
        // Se timer expirou e não está agendado, processar imediatamente
        if ($elapsed >= $buffer['timer_seconds'] && !$buffer['scheduled']) {
            \App\Helpers\Logger::info("AIAgentService::checkAndProcessOldBuffers - Buffer expirado encontrado, processando imediatamente");
            self::processBufferedMessages($conversationId);
        }
    }
}

