<?php
/**
 * Service TTSIntelligentService
 * Lógica inteligente para decidir quando enviar áudio vs texto
 */

namespace App\Services;

use App\Helpers\Logger;

class TTSIntelligentService
{
    /**
     * 🆕 Detectar intenção de áudio/texto usando IA (mais assertivo)
     * Usa OpenAI para classificar a intenção do cliente
     * 
     * @param string $clientMessage Mensagem do cliente
     * @param int $conversationId ID da conversa
     * @return string|null 'prefer_audio', 'no_audio', ou null se não detectou
     */
    public static function detectAudioPreferenceWithAI(string $clientMessage, int $conversationId): ?string
    {
        try {
            // Verificar se OpenAI está configurada
            $apiKey = \App\Models\Setting::get('openai_api_key') ?: getenv('OPENAI_API_KEY');
            if (empty($apiKey)) {
                Logger::info("TTSIntelligentService::detectAudioPreferenceWithAI - OpenAI não configurada, pulando detecção por IA");
                return null;
            }
            
            // Obter contexto da conversa (últimas 3 mensagens)
            $sql = "SELECT sender_type, content FROM messages 
                    WHERE conversation_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 3";
            $recentMessages = \App\Helpers\Database::fetchAll($sql, [$conversationId]);
            
            $context = '';
            foreach (array_reverse($recentMessages) as $msg) {
                $sender = $msg['sender_type'] === 'contact' ? 'Cliente' : 'IA';
                $context .= "{$sender}: {$msg['content']}\n";
            }
            
            // Prompt para classificação
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Você é um classificador de intenções sobre preferência de comunicação. Analise a mensagem do cliente e determine se ele quer receber áudio ou texto. Retorne APENAS um JSON: {"preference": "prefer_audio" ou "no_audio" ou null, "confidence": 0.0-1.0}. Se não tiver certeza, retorne null.'
                ],
                [
                    'role' => 'user',
                    'content' => "Contexto da conversa:\n{$context}\n\nMensagem atual do cliente: \"{$clientMessage}\"\n\nO cliente está pedindo para receber áudio, pedindo para parar de receber áudio, ou apenas conversando normalmente?"
                ]
            ];
            
            $payload = [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.1,
                'max_tokens' => 100,
                'response_format' => ['type' => 'json_object']
            ];
            
            Logger::info("TTSIntelligentService::detectAudioPreferenceWithAI - Chamando OpenAI para detectar preferência");
            
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_TIMEOUT => 5
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Logger::error("TTSIntelligentService::detectAudioPreferenceWithAI - Erro HTTP {$httpCode}: " . substr($response, 0, 200));
                return null;
            }
            
            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Logger::error("TTSIntelligentService::detectAudioPreferenceWithAI - Resposta vazia da OpenAI");
                return null;
            }
            
            $result = json_decode($content, true);
            $confidence = (float)($result['confidence'] ?? 0.0);
            $preference = $result['preference'] ?? null;
            
            // Só aceitar se confiança >= 0.7
            if ($confidence >= 0.7 && in_array($preference, ['prefer_audio', 'no_audio'])) {
                Logger::info("TTSIntelligentService::detectAudioPreferenceWithAI - ✅ Detectado: {$preference} (confiança: {$confidence})");
                return $preference;
            }
            
            Logger::info("TTSIntelligentService::detectAudioPreferenceWithAI - Confiança baixa ou preferência inválida: {$preference} (conf: {$confidence})");
            return null;
            
        } catch (\Exception $e) {
            Logger::error("TTSIntelligentService::detectAudioPreferenceWithAI - Erro: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Decidir modo de envio baseado em regras inteligentes
     * 
     * @param string $text Texto da mensagem da IA
     * @param int $conversationId ID da conversa
     * @param array $rules Regras de decisão inteligente
     * @param string|null $clientMessage Mensagem original do cliente (opcional, para detectar solicitações)
     * @return string 'text_only', 'audio_only', ou 'both'
     */
    public static function decideSendMode(string $text, int $conversationId, array $rules, ?string $clientMessage = null): string
    {
        $textLength = mb_strlen($text);
        $textLower = mb_strtolower($text);
        
        Logger::info("TTSIntelligentService::decideSendMode - Analisando (conv={$conversationId}, len={$textLength})");
        
        // ✅ PRIORIDADE MÁXIMA: Verificar se cliente PEDIU explicitamente um áudio
        // Verificar na mensagem do cliente (se fornecida) OU na última mensagem do cliente
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
        
        // Verificar na mensagem do cliente fornecida
        if ($clientMessage !== null) {
            $clientMessageLower = mb_strtolower($clientMessage);
            foreach ($audioRequestKeywords as $keyword) {
                if (stripos($clientMessageLower, $keyword) !== false) {
                    Logger::info("TTSIntelligentService::decideSendMode - 🎤 Cliente PEDIU explicitamente um áudio na mensagem! Forçando audio_only");
                    
                    // Salvar preferência na metadata da conversa
                    self::saveClientPreference($conversationId, 'prefer_audio');
                    
                    return 'audio_only';
                }
            }
        }
        
        // Se não encontrou na mensagem fornecida, verificar na última mensagem do cliente
        try {
            $sql = "SELECT content FROM messages 
                    WHERE conversation_id = ? AND sender_type = 'contact'
                    ORDER BY created_at DESC 
                    LIMIT 1";
            $lastClientMessage = \App\Helpers\Database::fetch($sql, [$conversationId]);
            
            if ($lastClientMessage && !empty($lastClientMessage['content'])) {
                $lastClientMessageLower = mb_strtolower($lastClientMessage['content']);
                
                // Verificar solicitação de áudio
                foreach ($audioRequestKeywords as $keyword) {
                    if (stripos($lastClientMessageLower, $keyword) !== false) {
                        Logger::info("TTSIntelligentService::decideSendMode - 🎤 Cliente PEDIU explicitamente um áudio na última mensagem! Forçando audio_only");
                        
                        // Salvar preferência na metadata da conversa
                        self::saveClientPreference($conversationId, 'prefer_audio');
                        
                        return 'audio_only';
                    }
                }
                
                // Verificar solicitação para NÃO enviar áudio
                $negativeKeywords = [
                    'não envie áudio', 'não mande áudio', 'sem áudio', 'apenas texto',
                    'só texto', 'somente texto', 'prefiro texto', 'não gosto de áudio',
                    'pare de enviar áudio', 'não quero áudio', 'volta com texto',
                    'pode voltar com texto', 'volta para texto', 'voltar com texto',
                    'pode voltar para texto', 'prefiro texto mesmo', 'só texto mesmo',
                    'apenas texto mesmo', 'sem áudio por favor', 'não precisa de áudio',
                    'não quero mais áudio', 'pare com áudio', 'chega de áudio'
                ];
                
                foreach ($negativeKeywords as $keyword) {
                    if (stripos($lastClientMessageLower, $keyword) !== false) {
                        Logger::info("TTSIntelligentService::decideSendMode - ⚠️ Cliente pediu para NÃO enviar áudios na última mensagem! Forçando text_only");
                        
                        // Salvar preferência na metadata da conversa (sobrescrever qualquer preferência anterior)
                        self::saveClientPreference($conversationId, 'no_audio');
                        
                        return 'text_only';
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::error("TTSIntelligentService::decideSendMode - Erro ao verificar última mensagem do cliente: " . $e->getMessage());
        }
        
        // ✅ NOVO: Modo Adaptativo - Espelhar comportamento do cliente
        if (!empty($rules['adaptive_mode'])) {
            Logger::info("TTSIntelligentService::decideSendMode - 🔄 Modo ADAPTATIVO ativado");
            return self::decideAdaptiveMode($conversationId, $textLower, $clientMessage);
        }
        
        // ✅ NOVO: Verificar se é primeira mensagem da IA
        Logger::info("TTSIntelligentService::decideSendMode - first_message_always_text configurado: " . (!empty($rules['first_message_always_text']) ? 'SIM' : 'NÃO'));
        
        if (!empty($rules['first_message_always_text'])) {
            try {
                // Buscar se já existe alguma mensagem da IA nesta conversa
                $sql = "SELECT COUNT(*) as count FROM messages 
                        WHERE conversation_id = ? AND sender_type = 'agent' AND message_type != 'system'";
                $result = \App\Helpers\Database::fetch($sql, [$conversationId]);
                $aiMessageCount = $result['count'] ?? 0;
                
                Logger::info("TTSIntelligentService::decideSendMode - Contagem de mensagens agent: {$aiMessageCount}");
                
                if ($aiMessageCount == 0) {
                    Logger::info("TTSIntelligentService::decideSendMode - ✅ Primeira mensagem da IA detectada! Retornando text_only");
                    return 'text_only';
                } else {
                    Logger::info("TTSIntelligentService::decideSendMode - Não é primeira mensagem (count={$aiMessageCount}), continuando análise");
                }
            } catch (\Exception $e) {
                Logger::error("TTSIntelligentService::decideSendMode - Erro ao verificar primeira mensagem: " . $e->getMessage());
            }
        }
        
        // 1. Verificar tamanho do texto
        if (!empty($rules['use_text_length'])) {
            if ($textLength > ($rules['min_chars_for_text'] ?? 1000)) {
                Logger::info("TTSIntelligentService - Texto muito longo ({$textLength} chars), forçando texto");
                return 'text_only';
            }
            
            if ($textLength > ($rules['max_chars_for_audio'] ?? 500)) {
                Logger::info("TTSIntelligentService - Texto médio ({$textLength} chars), preferindo texto");
                // Não força, mas influencia decisão
            }
        }
        
        // 2. Verificar URLs
        if (!empty($rules['force_text_if_urls'])) {
            $urlPattern = '/(https?:\/\/[^\s]+|www\.[^\s]+|[a-z0-9\-]+\.[a-z]{2,}(\/[^\s]*)?)/i';
            if (preg_match($urlPattern, $text)) {
                Logger::info("TTSIntelligentService - Contém URLs, forçando texto");
                return 'text_only';
            }
        }
        
        // 3. Verificar código/formatação
        if (!empty($rules['force_text_if_code'])) {
            // Verificar se contém código (backticks, blocos de código, etc)
            if (preg_match('/```|`[^`]+`|<\s*(code|pre)/i', $text)) {
                Logger::info("TTSIntelligentService - Contém código/formatação, forçando texto");
                return 'text_only';
            }
        }
        
        // 4. Verificar números (ex: códigos, valores)
        if (!empty($rules['force_text_if_numbers'])) {
            $numberCount = preg_match_all('/\d+/', $text);
            if ($numberCount > ($rules['max_numbers_for_audio'] ?? 5)) {
                Logger::info("TTSIntelligentService - Muitos números ({$numberCount}), forçando texto");
                return 'text_only';
            }
        }
        
        // 5. Verificar complexidade (palavras-chave técnicas)
        if (!empty($rules['use_complexity']) && !empty($rules['force_text_if_complex'])) {
            $complexityKeywords = $rules['complexity_keywords'] ?? [];
            foreach ($complexityKeywords as $keyword) {
                if (strpos($textLower, $keyword) !== false) {
                    Logger::info("TTSIntelligentService - Palavra-chave complexa encontrada: {$keyword}, forçando texto");
                    return 'text_only';
                }
            }
        }
        
        // 6. Verificar emojis
        if (!empty($rules['use_emojis'])) {
            $emojiPattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u';
            $emojiCount = preg_match_all($emojiPattern, $text);
            if ($emojiCount > ($rules['max_emojis_for_audio'] ?? 3)) {
                Logger::info("TTSIntelligentService - Muitos emojis ({$emojiCount}), preferindo texto");
                // Não força, mas influencia
            }
        }
        
        // 7. Verificar horário
        if (!empty($rules['use_time'])) {
            $timezone = new \DateTimeZone($rules['timezone'] ?? 'America/Sao_Paulo');
            $now = new \DateTime('now', $timezone);
            $hour = (int)$now->format('H');
            
            $startHour = $rules['audio_hours_start'] ?? 8;
            $endHour = $rules['audio_hours_end'] ?? 20;
            
            if ($hour < $startHour || $hour >= $endHour) {
                Logger::info("TTSIntelligentService - Fora do horário de áudio ({$hour}h), preferindo texto");
                // Não força, mas influencia
            }
        }
        
        // 8. Verificar histórico da conversa
        if (!empty($rules['use_conversation_history'])) {
            try {
                $conversation = \App\Models\Conversation::find($conversationId);
                if ($conversation) {
                    // Verificar últimas mensagens do cliente usando SQL direto
                    $sql = "SELECT * FROM messages 
                            WHERE conversation_id = ? AND sender_type = 'contact'
                            ORDER BY created_at DESC 
                            LIMIT 3";
                    $recentMessages = \App\Helpers\Database::fetchAll($sql, [$conversationId]);
                    
                    if (!empty($recentMessages)) {
                        $lastMessage = $recentMessages[0];
                        $lastMessageType = $lastMessage['message_type'] ?? null;
                        
                        if ($lastMessageType === 'audio' && !empty($rules['prefer_audio_if_client_sent_audio'])) {
                            Logger::info("TTSIntelligentService - Cliente enviou áudio recentemente, preferindo áudio");
                            return 'audio_only';
                        }
                        
                        if ($lastMessageType !== 'audio' && !empty($rules['prefer_text_if_client_sent_text'])) {
                            Logger::info("TTSIntelligentService - Cliente enviou texto recentemente, preferindo texto");
                            return 'text_only';
                        }
                    }
                }
            } catch (\Exception $e) {
                Logger::error("TTSIntelligentService - Erro ao verificar histórico: " . $e->getMessage());
            }
        }
        
        // 9. Decisão final baseada em pontuação
        $score = 0;
        
        // Texto curto = preferir áudio
        if ($textLength <= ($rules['max_chars_for_audio'] ?? 500)) {
            $score += 2;
        } elseif ($textLength <= ($rules['min_chars_for_text'] ?? 1000)) {
            $score += 1;
        } else {
            $score -= 2; // Texto longo = preferir texto
        }
        
        // Sem URLs/código = preferir áudio
        if (empty($rules['force_text_if_urls']) || !preg_match('/(https?:\/\/|```|`)/i', $text)) {
            $score += 1;
        }
        
        // Poucos emojis = preferir áudio
        if (!empty($rules['use_emojis'])) {
            $emojiPattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u';
            $emojiCount = preg_match_all($emojiPattern, $text);
            if ($emojiCount <= ($rules['max_emojis_for_audio'] ?? 3)) {
                $score += 1;
            } else {
                $score -= 1;
            }
        }
        
        // Decisão baseada em pontuação
        $defaultMode = $rules['default_mode'] ?? 'audio_only';
        
        if ($score >= 3) {
            Logger::info("TTSIntelligentService - Pontuação alta ({$score}), escolhendo áudio");
            return 'audio_only';
        } elseif ($score <= -1) {
            Logger::info("TTSIntelligentService - Pontuação baixa ({$score}), escolhendo texto");
            return 'text_only';
        } else {
            Logger::info("TTSIntelligentService - Pontuação neutra ({$score}), usando modo padrão: {$defaultMode}");
            return $defaultMode;
        }
    }
    
    /**
     * Obter estatísticas da decisão (para debug/logs)
     */
    public static function getDecisionStats(string $text, array $rules): array
    {
        $textLength = mb_strlen($text);
        $textLower = mb_strtolower($text);
        
        $stats = [
            'text_length' => $textLength,
            'has_urls' => false,
            'has_code' => false,
            'number_count' => 0,
            'emoji_count' => 0,
            'complexity_keywords_found' => [],
            'score' => 0,
        ];
        
        // URLs
        $urlPattern = '/(https?:\/\/[^\s]+|www\.[^\s]+|[a-z0-9\-]+\.[a-z]{2,}(\/[^\s]*)?)/i';
        $stats['has_urls'] = preg_match($urlPattern, $text);
        
        // Código
        $stats['has_code'] = preg_match('/```|`[^`]+`|<\s*(code|pre)/i', $text);
        
        // Números
        $stats['number_count'] = preg_match_all('/\d+/', $text);
        
        // Emojis
        $emojiPattern = '/[\x{1F300}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u';
        $stats['emoji_count'] = preg_match_all($emojiPattern, $text);
        
        // Palavras-chave de complexidade
        $complexityKeywords = $rules['complexity_keywords'] ?? [];
        foreach ($complexityKeywords as $keyword) {
            if (strpos($textLower, $keyword) !== false) {
                $stats['complexity_keywords_found'][] = $keyword;
            }
        }
        
        return $stats;
    }
    
    /**
     * 🆕 Modo Adaptativo: Espelha comportamento do cliente
     * - Cliente enviou áudio? IA envia áudio
     * - Cliente enviou texto? IA envia texto
     * - Cliente pediu para parar áudios? IA respeita
     */
    private static function decideAdaptiveMode(int $conversationId, string $textLower, ?string $clientMessage = null): string
    {
        Logger::info("TTSIntelligentService - 🔄 Modo Adaptativo: Analisando comportamento do cliente...");
        
        try {
            // 0️⃣ PRIMEIRO: Tentar detecção por IA (se habilitada e mensagem do cliente disponível)
            if ($clientMessage !== null && !empty(trim($clientMessage))) {
                Logger::info("TTSIntelligentService - 🤖 Tentando detecção por IA...");
                $aiPreference = self::detectAudioPreferenceWithAI($clientMessage, $conversationId);
                
                if ($aiPreference === 'prefer_audio') {
                    Logger::info("TTSIntelligentService - 🤖 IA detectou: PREFER_AUDIO");
                    self::saveClientPreference($conversationId, 'prefer_audio');
                    return 'audio_only';
                } elseif ($aiPreference === 'no_audio') {
                    Logger::info("TTSIntelligentService - 🤖 IA detectou: NO_AUDIO");
                    self::saveClientPreference($conversationId, 'no_audio');
                    return 'text_only';
                }
            }
            
            // 1️⃣ SEGUNDO: Verificar se cliente PEDIU explicitamente um áudio na mensagem atual OU na última mensagem
            $audioRequestKeywords = [
                'manda um áudio', 'manda um audio', 'envia um áudio', 'envia um audio',
                'manda áudio', 'manda audio', 'envia áudio', 'envia audio',
                'quero áudio', 'quero audio', 'preciso de áudio', 'preciso de audio',
                'manda em áudio', 'manda em audio', 'envia em áudio', 'envia em audio',
                'não estou conseguindo ler', 'não consigo ler', 'não consigo ler o texto',
                'prefiro áudio', 'prefiro audio', 'gostaria de áudio', 'gostaria de audio',
                'pode mandar áudio', 'pode mandar audio', 'pode enviar áudio', 'pode enviar audio',
                'me manda um áudio', 'me manda um audio', 'me envia um áudio', 'me envia um audio',
                'mande um áudio', 'mande um audio', 'mande áudio', 'mande audio' // ✅ NOVO: variações com "mande"
            ];
            
            // Verificar na mensagem do cliente fornecida (se houver)
            if ($clientMessage !== null) {
                $clientMessageLower = mb_strtolower($clientMessage);
                foreach ($audioRequestKeywords as $keyword) {
                    if (stripos($clientMessageLower, $keyword) !== false) {
                        Logger::info("TTSIntelligentService - 🎤 Cliente PEDIU explicitamente um áudio na mensagem! Forçando audio_only");
                        
                        // Salvar preferência na metadata da conversa
                        self::saveClientPreference($conversationId, 'prefer_audio');
                        
                        return 'audio_only';
                    }
                }
            }
            
            // Verificar na última mensagem do cliente (se não foi fornecida)
            try {
                $sql = "SELECT content FROM messages 
                        WHERE conversation_id = ? AND sender_type = 'contact'
                        ORDER BY created_at DESC 
                        LIMIT 1";
                $lastClientMessage = \App\Helpers\Database::fetch($sql, [$conversationId]);
                
                if ($lastClientMessage && !empty($lastClientMessage['content'])) {
                    $lastClientMessageLower = mb_strtolower($lastClientMessage['content']);
                    foreach ($audioRequestKeywords as $keyword) {
                        if (stripos($lastClientMessageLower, $keyword) !== false) {
                            Logger::info("TTSIntelligentService - 🎤 Cliente PEDIU explicitamente um áudio na última mensagem! Forçando audio_only");
                            
                            // Salvar preferência na metadata da conversa
                            self::saveClientPreference($conversationId, 'prefer_audio');
                            
                            return 'audio_only';
                        }
                    }
                }
            } catch (\Exception $e) {
                Logger::error("TTSIntelligentService - Erro ao verificar última mensagem do cliente: " . $e->getMessage());
            }
            
            // 1️⃣ PRIMEIRO: Verificar se cliente pediu para NÃO enviar áudios na mensagem atual OU última mensagem
            $negativeKeywords = [
                'não envie áudio', 'não mande áudio', 'sem áudio', 'apenas texto',
                'só texto', 'somente texto', 'prefiro texto', 'não gosto de áudio',
                'pare de enviar áudio', 'não quero áudio', 'volta com texto',
                'pode voltar com texto', 'volta para texto', 'voltar com texto',
                'pode voltar para texto', 'prefiro texto mesmo', 'só texto mesmo',
                'apenas texto mesmo', 'sem áudio por favor', 'não precisa de áudio',
                'não quero mais áudio', 'pare com áudio', 'chega de áudio'
            ];
            
            // Verificar na mensagem do cliente fornecida (se houver)
            if ($clientMessage !== null) {
                $clientMessageLower = mb_strtolower($clientMessage);
                foreach ($negativeKeywords as $keyword) {
                    if (stripos($clientMessageLower, $keyword) !== false) {
                        Logger::info("TTSIntelligentService - ⚠️ Cliente pediu para NÃO enviar áudios na mensagem atual! Usando text_only");
                        
                        // Salvar preferência na metadata da conversa (sobrescrever qualquer preferência anterior)
                        self::saveClientPreference($conversationId, 'no_audio');
                        
                        return 'text_only';
                    }
                }
            }
            
            // Verificar na última mensagem do cliente (se não foi fornecida)
            try {
                $sql = "SELECT content FROM messages 
                        WHERE conversation_id = ? AND sender_type = 'contact'
                        ORDER BY created_at DESC 
                        LIMIT 1";
                $lastClientMessage = \App\Helpers\Database::fetch($sql, [$conversationId]);
                
                if ($lastClientMessage && !empty($lastClientMessage['content'])) {
                    $lastClientMessageLower = mb_strtolower($lastClientMessage['content']);
                    foreach ($negativeKeywords as $keyword) {
                        if (stripos($lastClientMessageLower, $keyword) !== false) {
                            Logger::info("TTSIntelligentService - ⚠️ Cliente pediu para NÃO enviar áudios na última mensagem! Usando text_only");
                            
                            // Salvar preferência na metadata da conversa (sobrescrever qualquer preferência anterior)
                            self::saveClientPreference($conversationId, 'no_audio');
                            
                            return 'text_only';
                        }
                    }
                }
            } catch (\Exception $e) {
                Logger::error("TTSIntelligentService - Erro ao verificar última mensagem do cliente: " . $e->getMessage());
            }
            
            // 2️⃣ Verificar se há preferência salva (só se não detectou mudança na mensagem atual)
            $savedPreference = self::getClientPreference($conversationId);
            if ($savedPreference === 'prefer_audio') {
                Logger::info("TTSIntelligentService - 🎤 Cliente tem preferência salva: PREFER_AUDIO");
                return 'audio_only';
            }
            if ($savedPreference === 'no_audio') {
                Logger::info("TTSIntelligentService - ⚠️ Cliente tem preferência salva: NO_AUDIO");
                return 'text_only';
            }
            
            // 3️⃣ Verificar últimas 3 mensagens do cliente
            $sql = "SELECT message_type, content 
                    FROM messages 
                    WHERE conversation_id = ? AND sender_type = 'contact'
                    ORDER BY created_at DESC 
                    LIMIT 3";
            $recentMessages = \App\Helpers\Database::fetchAll($sql, [$conversationId]);
            
            if (empty($recentMessages)) {
                Logger::info("TTSIntelligentService - ℹ️ Nenhuma mensagem do cliente ainda. Usando text_only (seguro)");
                return 'text_only';
            }
            
            // Contar quantos áudios vs textos
            $audioCount = 0;
            $textCount = 0;
            
            foreach ($recentMessages as $msg) {
                if ($msg['message_type'] === 'audio') {
                    $audioCount++;
                } else {
                    $textCount++;
                }
            }
            
            Logger::info("TTSIntelligentService - 📊 Últimas 3 mensagens: {$audioCount} áudios, {$textCount} textos");
            
            // 4️⃣ Decisão baseada no comportamento do cliente
            if ($audioCount > 0 && $audioCount >= $textCount) {
                // Cliente usa áudio (metade ou mais)
                Logger::info("TTSIntelligentService - ✅ Cliente usa áudio! Enviando audio_only");
                return 'audio_only';
            } else {
                // Cliente prefere texto
                Logger::info("TTSIntelligentService - ✅ Cliente prefere texto! Enviando text_only");
                return 'text_only';
            }
            
        } catch (\Exception $e) {
            Logger::error("TTSIntelligentService - Erro no modo adaptativo: " . $e->getMessage());
            // Fallback seguro: texto
            return 'text_only';
        }
    }
    
    /**
     * 🆕 Salvar preferência do cliente na metadata da conversa
     */
    private static function saveClientPreference(int $conversationId, string $preference): void
    {
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                $metadata['tts_client_preference'] = $preference;
                $metadata['tts_preference_updated_at'] = date('Y-m-d H:i:s');
                
                \App\Models\Conversation::update($conversationId, [
                    'metadata' => json_encode($metadata)
                ]);
                
                Logger::info("TTSIntelligentService - ✅ Preferência do cliente salva: {$preference}");
            }
        } catch (\Exception $e) {
            Logger::error("TTSIntelligentService - Erro ao salvar preferência: " . $e->getMessage());
        }
    }
    
    /**
     * 🆕 Obter preferência salva do cliente
     */
    private static function getClientPreference(int $conversationId): ?string
    {
        try {
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                return $metadata['tts_client_preference'] ?? null;
            }
        } catch (\Exception $e) {
            Logger::error("TTSIntelligentService - Erro ao obter preferência: " . $e->getMessage());
        }
        
        return null;
    }
}

