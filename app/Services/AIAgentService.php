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
        
        // Obter configurações do agente
        $agent = AIAgent::find($agentId);
        if (!$agent) {
            \App\Helpers\Logger::error("AIAgentService::bufferMessage - Agente não encontrado: {$agentId}");
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
        
        // ✅ USAR ARQUIVOS para persistência entre webhooks
        $bufferDir = self::getBufferDirectory();
        $bufferFile = $bufferDir . '/buffer_' . $conversationId . '.json';
        $lockFile = $bufferDir . '/lock_' . $conversationId . '.lock';
        
        $now = time();
        
        // Ler buffer existente (se houver)
        $bufferData = [];
        if (file_exists($bufferFile)) {
            $content = file_get_contents($bufferFile);
            $bufferData = json_decode($content, true) ?? [];
        }
        
        // Inicializar se não existir
        if (empty($bufferData)) {
            $bufferData = [
                'messages' => [],
                'agent_id' => $agentId,
                'timer_seconds' => $contextTimer,
                'first_message_at' => $now,
                'scheduled' => false
            ];
        }
        
        // Adicionar nova mensagem
        $bufferData['messages'][] = [
            'content' => $message,
            'timestamp' => $now
        ];
        
        // ✅ CRÍTICO: Atualizar timestamp da última mensagem
        $bufferData['last_message_at'] = $now;
        $bufferData['expires_at'] = $now + $contextTimer;
        
        // Salvar buffer atualizado
        file_put_contents($bufferFile, json_encode($bufferData, JSON_UNESCAPED_UNICODE));
        
        $msgCount = count($bufferData['messages']);
        $wasScheduled = $bufferData['scheduled'] ?? false;
        
        \App\Helpers\Logger::info("AIAgentService::bufferMessage - Buffer salvo (msgs: {$msgCount}, expires: " . date('H:i:s', $bufferData['expires_at']) . ", wasScheduled: " . ($wasScheduled ? 'SIM' : 'NÃO') . ")");
        
        // Se já está agendado, apenas renovar o timer (não criar novo processamento)
        if ($wasScheduled) {
            \App\Helpers\Logger::info("AIAgentService::bufferMessage - ⏰ Timer RENOVADO (nova expiração: " . date('H:i:s', $bufferData['expires_at']) . ")");
            return;
        }
        
        // Marcar como agendado
        $bufferData['scheduled'] = true;
        file_put_contents($bufferFile, json_encode($bufferData, JSON_UNESCAPED_UNICODE));
        
        // Agendar processamento (apenas primeira vez)
        \App\Helpers\Logger::info("AIAgentService::bufferMessage - 🚀 Agendando NOVO processamento");
        self::scheduleProcessing($conversationId, $contextTimer);
    }
    
    /**
     * 🆕 Agendar processamento após timer de contexto
     * Usa requisição HTTP não-bloqueante para processar em background
     * 
     * @param int $conversationId ID da conversa
     * @param int $timerSeconds Segundos para aguardar
     * @return void
     */
    private static function scheduleProcessing(int $conversationId, int $timerSeconds): void
    {
        \App\Helpers\Logger::aiTools("[BUFFER] Agendamento criado: conv={$conversationId}, timer={$timerSeconds}s");
        
        // Fazer requisição HTTP não-bloqueante para processar o buffer
        // Isso faz com que o processamento aconteça em outra thread/processo
        self::triggerBackgroundProcessing($conversationId, $timerSeconds);
    }
    
    /**
     * Disparar processamento de buffer em background (sem bloquear)
     * Usa exec em background (Linux) ou lock file (Windows)
     */
    private static function triggerBackgroundProcessing(int $conversationId, int $timerSeconds): void
    {
        try {
            // MÉTODO 1: Usar exec em background (Linux/Unix)
            if (PHP_OS_FAMILY !== 'Windows') {
                $scriptPath = __DIR__ . '/../../public/process-single-buffer.php';
                $command = sprintf(
                    'php %s %d %d > /dev/null 2>&1 &',
                    escapeshellarg($scriptPath),
                    $conversationId,
                    $timerSeconds
                );
                exec($command);
                \App\Helpers\Logger::aiTools("[BUFFER] Processamento disparado via exec (Linux): conv={$conversationId}");
                return;
            }
            
            // MÉTODO 2: Windows - criar lock e usar endpoint de polling
            $lockFile = self::getBufferDirectory() . '/process_' . $conversationId . '.lock';
            file_put_contents($lockFile, json_encode([
                'conversation_id' => $conversationId,
                'timer_seconds' => $timerSeconds,
                'created_at' => time()
            ]));
            
            \App\Helpers\Logger::aiTools("[BUFFER] Lock criado para polling (Windows): conv={$conversationId}");
            
        } catch (\Exception $e) {
            \App\Helpers\Logger::aiTools("[BUFFER ERROR] Erro ao disparar processamento: " . $e->getMessage());
        }
    }
    
    /**
     * Obter diretório de buffers
     */
    private static function getBufferDirectory(): string
    {
        $basePath = dirname(__DIR__, 2);
        $bufferDir = $basePath . '/storage/ai_buffers';
        
        if (!is_dir($bufferDir)) {
            mkdir($bufferDir, 0755, true);
        }
        
        return $bufferDir;
    }
}
