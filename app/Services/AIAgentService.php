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
            $response = OpenAIService::processMessage($conversationId, $agentId, $message, $context);

            // Delay humanizado antes de responder (configurável por agente)
            $settings = is_string($agent['settings'] ?? null) 
                ? json_decode($agent['settings'], true) 
                : ($agent['settings'] ?? []);
            
            $minDelay = (int)($settings['response_delay_min'] ?? 0); // segundos
            $maxDelay = (int)($settings['response_delay_max'] ?? 0); // segundos
            
            if ($minDelay > 0 && $maxDelay >= $minDelay) {
                $delay = rand($minDelay, $maxDelay);
                \App\Helpers\ConversationDebug::log($conversationId, "Delay humanizado: {$delay}s (min: {$minDelay}, max: {$maxDelay})");
                sleep($delay);
            }

            // Criar mensagem na conversa
            \App\Services\ConversationService::sendMessage(
                $conversationId,
                $response['content'],
                'agent',
                null, // AI agent não tem user_id
                [],
                null, // messageType
                null, // quotedMessageId
                $agentId // aiAgentId
            );

            return $response;
        } catch (\Exception $e) {
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
}

