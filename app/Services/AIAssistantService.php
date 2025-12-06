<?php
/**
 * Service AIAssistantService
 * Service principal do Assistente IA no chat
 */

namespace App\Services;

use App\Models\AIAssistantFeature;
use App\Models\AIAgent;
use App\Models\Message;
use App\Models\Conversation;
use App\Models\Contact;

class AIAssistantService
{
    /**
     * Gerar resposta usando Assistente IA
     */
    public static function generateResponse(
        int $userId,
        int $conversationId,
        string $featureKey = 'generate_response',
        array $options = []
    ): array {
        // Verificar se funcionalidade está disponível
        if (!AIAssistantFeatureService::isAvailableForUser($userId, $featureKey)) {
            throw new \Exception('Funcionalidade não disponível para este usuário');
        }

        // Obter funcionalidade
        $feature = AIAssistantFeatureService::getForUser($userId, $featureKey);
        if (!$feature) {
            throw new \Exception('Funcionalidade não encontrada');
        }

        // Construir contexto da conversa
        $context = AIAgentSelectorService::buildContext($conversationId);

        // Selecionar agente
        $agentId = AIAgentSelectorService::selectAgent($userId, $featureKey, $context);
        if (!$agentId) {
            throw new \Exception('Nenhum agente disponível para esta funcionalidade');
        }

        // Obter informações do agente
        $agentInfo = AIAgentSelectorService::getAgentInfo($agentId);
        if (!$agentInfo) {
            throw new \Exception('Agente não encontrado');
        }

        // Obter configurações da funcionalidade (mesclar padrão com personalizadas)
        $defaultSettings = $feature['settings'] ?? [];
        $userCustomSettings = AIAssistantUserSetting::getCustomSettings($userId, $featureKey);
        $settings = array_merge($defaultSettings, $userCustomSettings);
        
        $generateCount = $options['count'] ?? ($settings['generate_count'] ?? 3);
        $tone = $options['tone'] ?? ($settings['default_tone'] ?? 'professional');
        
        // Validar opções de tom disponíveis
        $availableTones = $settings['tone_options'] ?? ['professional', 'friendly', 'formal'];
        if (!in_array($tone, $availableTones)) {
            $tone = $availableTones[0] ?? 'professional';
        }

        // Obter mensagens da conversa
        $conversation = Conversation::findWithRelations($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }

        // Construir histórico de mensagens
        $maxMessages = $settings['max_context_messages'] ?? 20;
        $messages = Message::getMessagesWithSenderDetails($conversationId, $maxMessages);
        
        // Construir prompt baseado na funcionalidade
        $prompt = self::buildPrompt($featureKey, $conversation, $messages, $settings, $tone);

        // Gerar respostas usando OpenAI
        $responses = [];
        $startTime = microtime(true);
        
        for ($i = 0; $i < $generateCount; $i++) {
            try {
                // Construir mensagem para o prompt
                $userMessage = $prompt;
                
                $response = OpenAIService::processMessage(
                    $conversationId,
                    $agentId,
                    $userMessage,
                    [
                        'conversation' => $conversation,
                        'messages' => $messages,
                        'feature' => $featureKey,
                        'tone' => $tone
                    ]
                );

                $executionTime = (microtime(true) - $startTime) * 1000; // em milissegundos
                
                $responseData = [
                    'text' => $response['content'] ?? '',
                    'agent_id' => $agentId,
                    'agent_name' => $agentInfo['name'],
                    'tokens_used' => $response['tokens_used'] ?? 0,
                    'cost' => $response['cost'] ?? 0,
                    'tone' => $tone
                ];
                
                $responses[] = $responseData;
                
                // Salvar resposta no histórico
                try {
                    \App\Models\AIAssistantResponse::saveResponse(
                        $userId,
                        $conversationId,
                        $featureKey,
                        $agentId,
                        $responseData['text'],
                        $tone,
                        $responseData['tokens_used'],
                        $responseData['cost']
                    );
                } catch (\Exception $e) {
                    // Log erro mas não interrompe o fluxo
                    error_log("Erro ao salvar resposta no histórico: " . $e->getMessage());
                }
                
                // Registrar log de uso
                \App\Models\AIAssistantLog::log(
                    $userId,
                    $conversationId,
                    $featureKey,
                    $agentId,
                    [
                        'count' => $generateCount,
                        'tone' => $tone,
                        'attempt' => $i + 1
                    ],
                    [
                        'response_length' => mb_strlen($responseData['text']),
                        'tokens_used' => $responseData['tokens_used'],
                        'cost' => $responseData['cost']
                    ],
                    $responseData['tokens_used'],
                    $responseData['cost'],
                    (int)$executionTime,
                    true,
                    null
                );
            } catch (\Exception $e) {
                $executionTime = (microtime(true) - $startTime) * 1000;
                
                // Registrar log de erro
                \App\Models\AIAssistantLog::log(
                    $userId,
                    $conversationId,
                    $featureKey,
                    $agentId,
                    [
                        'count' => $generateCount,
                        'tone' => $tone,
                        'attempt' => $i + 1
                    ],
                    [],
                    0,
                    0.0,
                    (int)$executionTime,
                    false,
                    $e->getMessage()
                );
                
                // Se falhar, adicionar erro mas continuar tentando
                if ($i === 0) {
                    throw $e; // Se a primeira falhar, lançar exceção
                }
                // Para tentativas subsequentes, apenas logar
                error_log("Erro ao gerar resposta " . ($i + 1) . ": " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'responses' => $responses,
            'agent_used' => $agentInfo,
            'feature' => $featureKey
        ];
    }

    /**
     * Construir prompt baseado na funcionalidade
     */
    private static function buildPrompt(
        string $featureKey,
        array $conversation,
        array $messages,
        array $settings,
        string $tone
    ): string {
        $contactName = $conversation['contact_name'] ?? 'Cliente';
        $channel = $conversation['channel'] ?? 'chat';
        
        // Construir histórico de mensagens
        $history = '';
        foreach (array_slice($messages, -10) as $msg) {
            $sender = $msg['sender_type'] === 'contact' ? $contactName : 'Agente';
            $history .= "{$sender}: {$msg['content']}\n";
        }

        switch ($featureKey) {
            case 'generate_response':
                $toneInstructions = [
                    'professional' => 'Seja profissional e respeitoso',
                    'friendly' => 'Seja amigável e caloroso',
                    'formal' => 'Seja formal e polido'
                ];
                
                $toneText = $toneInstructions[$tone] ?? $toneInstructions['professional'];
                
                $includeContact = $settings['include_contact_info'] ?? true;
                $includeTags = $settings['include_tags'] ?? true;
                
                $prompt = "Você é um assistente de atendimento ao cliente. {$toneText}.\n\n";
                
                if ($includeContact && !empty($conversation['contact_name'])) {
                    $prompt .= "Cliente: {$conversation['contact_name']}\n";
                }
                
                if ($includeTags && !empty($conversation['tags'])) {
                    $tagsList = implode(', ', array_column($conversation['tags'], 'name'));
                    $prompt .= "Tags da conversa: {$tagsList}\n\n";
                }
                
                $prompt .= "Histórico da conversa:\n{$history}\n\n" .
                          "Gere uma resposta apropriada e útil para o cliente. " .
                          "A resposta deve ser clara, concisa e resolver a questão do cliente.";
                
                return $prompt;

            case 'summarize':
                $summaryLength = $settings['summary_length'] ?? 'medium';
                $includeKeyPoints = $settings['include_key_points'] ?? true;
                $includeActionItems = $settings['include_action_items'] ?? true;
                $includeSentiment = $settings['include_sentiment'] ?? true;
                $maxLength = $settings['max_length'] ?? 500;
                
                $lengthInstructions = [
                    'short' => 'Resumo curto e objetivo (máximo 200 palavras)',
                    'medium' => 'Resumo médio com detalhes importantes (máximo 500 palavras)',
                    'long' => 'Resumo completo e detalhado (máximo 1000 palavras)'
                ];
                
                $prompt = "Resuma a seguinte conversa de atendimento";
                if ($summaryLength !== 'medium') {
                    $prompt .= " ({$lengthInstructions[$summaryLength]})";
                }
                $prompt .= ", destacando:\n";
                
                if ($includeKeyPoints) {
                    $prompt .= "- Pontos principais discutidos\n";
                }
                if ($includeActionItems) {
                    $prompt .= "- Problemas ou questões levantadas\n";
                    $prompt .= "- Ações tomadas ou sugeridas\n";
                }
                if ($includeSentiment) {
                    $prompt .= "- Sentimento geral do cliente\n";
                }
                
                $prompt .= "\nHistórico:\n{$history}\n\n";
                $prompt .= "Mantenha o resumo dentro de {$maxLength} caracteres.";
                
                return $prompt;

            case 'suggest_tags':
                $maxTags = $settings['max_tags'] ?? 5;
                $confidenceThreshold = $settings['confidence_threshold'] ?? 0.7;
                $useExistingTags = $settings['use_existing_tags'] ?? true;
                
                $prompt = "Analise a seguinte conversa e sugira até {$maxTags} tags relevantes que descrevam:\n" .
                       "- O assunto principal\n" .
                       "- O tipo de problema ou questão\n" .
                       "- A urgência ou prioridade\n" .
                       "- O canal ou contexto\n\n" .
                       "Histórico:\n{$history}\n\n";
                
                if ($useExistingTags) {
                    $prompt .= "IMPORTANTE: Sugira apenas tags que já existem no sistema. ";
                }
                
                $prompt .= "Retorne apenas uma lista de tags separadas por vírgula. " .
                          "Apenas sugira tags com confiança acima de " . ($confidenceThreshold * 100) . "%.";
                
                return $prompt;

            case 'analyze_sentiment':
                return "Analise o sentimento e emoções expressas na seguinte conversa:\n\n" .
                       "Histórico:\n{$history}\n\n" .
                       "Identifique:\n" .
                       "- Sentimento geral (positivo, neutro, negativo)\n" .
                       "- Emoções específicas (frustração, satisfação, ansiedade, etc)\n" .
                       "- Mudanças de humor ao longo da conversa\n" .
                       "- Nível de urgência percebido";

            case 'translate':
                $targetLang = $settings['target_language'] ?? 'pt-BR';
                return "Traduza a seguinte mensagem para {$targetLang}, mantendo o contexto e tom original:\n\n" .
                       "Última mensagem do cliente:\n" . 
                       (end($messages)['content'] ?? '');

            case 'improve_grammar':
                return "Corrija e melhore a gramática, ortografia e clareza da seguinte mensagem, " .
                       "mantendo o tom e significado original:\n\n" .
                       (end($messages)['content'] ?? '');

            case 'suggest_next_steps':
                return "Analise a conversa e sugira os próximos passos apropriados:\n\n" .
                       "Histórico:\n{$history}\n\n" .
                       "Considere:\n" .
                       "- O que o cliente precisa\n" .
                       "- Ações que podem ser tomadas\n" .
                       "- Próximos passos lógicos\n" .
                       "- Oportunidades de melhoria";

            case 'extract_info':
                return "Extraia informações importantes da seguinte conversa:\n\n" .
                       "Histórico:\n{$history}\n\n" .
                       "Identifique e extraia:\n" .
                       "- Informações de contato (nome, email, telefone)\n" .
                       "- Datas mencionadas\n" .
                       "- Números importantes (valores, quantidades, IDs)\n" .
                       "- Palavras-chave e tópicos principais";

            default:
                return "Analise a seguinte conversa e forneça uma resposta útil:\n\n{$history}";
        }
    }

    /**
     * Executar outras funcionalidades do Assistente IA
     */
    public static function executeFeature(
        int $userId,
        int $conversationId,
        string $featureKey,
        array $options = []
    ): array {
        // Verificar se funcionalidade está disponível
        if (!AIAssistantFeatureService::isAvailableForUser($userId, $featureKey)) {
            throw new \Exception('Funcionalidade não disponível para este usuário');
        }

        // Obter funcionalidade
        $feature = AIAssistantFeatureService::getForUser($userId, $featureKey);
        if (!$feature) {
            throw new \Exception('Funcionalidade não encontrada');
        }

        // Construir contexto
        $context = AIAgentSelectorService::buildContext($conversationId);

        // Selecionar agente
        $agentId = AIAgentSelectorService::selectAgent($userId, $featureKey, $context);
        if (!$agentId) {
            throw new \Exception('Nenhum agente disponível para esta funcionalidade');
        }

        // Obter informações do agente
        $agentInfo = AIAgentSelectorService::getAgentInfo($agentId);
        if (!$agentInfo) {
            throw new \Exception('Agente não encontrado');
        }

        // Obter mensagens da conversa
        $conversation = Conversation::findWithRelations($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }

        // Obter configurações (mesclar padrão com personalizadas)
        $defaultSettings = $feature['settings'] ?? [];
        $userCustomSettings = AIAssistantUserSetting::getCustomSettings($userId, $featureKey);
        $settings = array_merge($defaultSettings, $userCustomSettings);
        
        // Determinar quantas mensagens incluir no contexto
        $maxMessages = $settings['max_context_messages'] ?? 50;
        $messages = Message::getMessagesWithSenderDetails($conversationId, $maxMessages);

        // Construir prompt
        $prompt = self::buildPrompt($featureKey, $conversation, $messages, $settings, 'professional');

        // Processar com OpenAI
        $userMessage = $prompt;
        $startTime = microtime(true);
        
        try {
            $response = OpenAIService::processMessage(
                $conversationId,
                $agentId,
                $userMessage,
                [
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'feature' => $featureKey
                ]
            );

            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Registrar log de uso
            \App\Models\AIAssistantLog::log(
                $userId,
                $conversationId,
                $featureKey,
                $agentId,
                [
                    'options' => $options
                ],
                [
                    'result_length' => mb_strlen($response['content'] ?? ''),
                    'tokens_used' => $response['tokens_used'] ?? 0,
                    'cost' => $response['cost'] ?? 0
                ],
                $response['tokens_used'] ?? 0,
                $response['cost'] ?? 0.0,
                (int)$executionTime,
                true,
                null
            );

            return [
                'success' => true,
                'result' => $response['content'] ?? '',
                'agent_used' => $agentInfo,
                'feature' => $featureKey,
                'tokens_used' => $response['tokens_used'] ?? 0,
                'cost' => $response['cost'] ?? 0
            ];
        } catch (\Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Registrar log de erro
            \App\Models\AIAssistantLog::log(
                $userId,
                $conversationId,
                $featureKey,
                $agentId,
                [
                    'options' => $options
                ],
                [],
                0,
                0.0,
                (int)$executionTime,
                false,
                $e->getMessage()
            );
            
            throw $e;
        }
    }
}

