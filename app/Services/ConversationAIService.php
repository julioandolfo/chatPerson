<?php
/**
 * Service ConversationAIService
 * Lógica de negócio para gerenciar agentes de IA em conversas
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\AIConversation;
use App\Models\AIAgent;
use App\Models\Message;
use App\Helpers\Validator;

class ConversationAIService
{
    /**
     * Obter status da IA na conversa
     */
    public static function getAIStatus(int $conversationId): array
    {
        error_log("ConversationAIService::getAIStatus - Iniciando para conversationId: {$conversationId}");
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            error_log("ConversationAIService::getAIStatus - Conversa não encontrada: {$conversationId}");
            throw new \Exception('Conversa não encontrada');
        }

        self::logAI("getAIStatus - Buscando AIConversation para conversationId={$conversationId}");
        $aiConversation = AIConversation::getByConversationId($conversationId);
        self::logAI("getAIStatus - AIConversation: " . ($aiConversation ? "ID={$aiConversation['id']}, status={$aiConversation['status']}, ai_agent_id={$aiConversation['ai_agent_id']}" : 'null'));
        
        if (!$aiConversation || $aiConversation['status'] !== 'active') {
            self::logAI("getAIStatus - Retornando has_ai=false (aiConversation=" . ($aiConversation ? "status={$aiConversation['status']}" : 'null') . ")");
            return [
                'has_ai' => false,
                'ai_agent' => null,
                'ai_conversation' => null,
                'messages_count' => 0,
                'tools_used' => []
            ];
        }
        
        self::logAI("getAIStatus - IA ativa encontrada, buscando dados do agente...");

        $aiAgent = AIAgent::find($aiConversation['ai_agent_id']);
        
        // Contar mensagens da IA usando SQL direto
        $messagesCountSql = "SELECT COUNT(*) as total 
                            FROM messages 
                            WHERE conversation_id = ? 
                            AND ai_agent_id = ? 
                            AND sender_type = 'agent'";
        $messagesCountResult = \App\Helpers\Database::fetch($messagesCountSql, [
            $conversationId,
            $aiConversation['ai_agent_id']
        ]);
        $messagesCount = (int)($messagesCountResult['total'] ?? 0);

        // Obter tools utilizadas
        $toolsUsed = [];
        if (!empty($aiConversation['tools_used'])) {
            $toolsData = is_string($aiConversation['tools_used']) 
                ? json_decode($aiConversation['tools_used'], true) 
                : $aiConversation['tools_used'];
            
            if (is_array($toolsData)) {
                foreach ($toolsData as $toolUsage) {
                    if (isset($toolUsage['tool'])) {
                        $toolsUsed[] = $toolUsage['tool'];
                    }
                }
            }
        }
        $toolsUsed = array_unique($toolsUsed);

        // Obter última interação usando SQL direto
        $lastMessageSql = "SELECT * 
                          FROM messages 
                          WHERE conversation_id = ? 
                          AND ai_agent_id = ? 
                          AND sender_type = 'agent'
                          ORDER BY created_at DESC 
                          LIMIT 1";
        $lastMessage = \App\Helpers\Database::fetch($lastMessageSql, [
            $conversationId,
            $aiConversation['ai_agent_id']
        ]);

        return [
            'has_ai' => true,
            'ai_agent' => $aiAgent ? [
                'id' => $aiAgent['id'],
                'name' => $aiAgent['name'],
                'type' => $aiAgent['agent_type'],
                'description' => $aiAgent['description']
            ] : null,
            'ai_conversation' => [
                'id' => $aiConversation['id'],
                'status' => $aiConversation['status'],
                'created_at' => $aiConversation['created_at'],
                'last_interaction' => $lastMessage ? $lastMessage['created_at'] : null,
                'tokens_used' => $aiConversation['tokens_used'] ?? 0,
                'cost' => $aiConversation['cost'] ?? 0
            ],
            'messages_count' => $messagesCount,
            'tools_used' => $toolsUsed
        ];
    }

    /**
     * Obter mensagens da IA na conversa
     */
    public static function getAIMessages(int $conversationId, int $limit = 50, int $offset = 0): array
    {
        $aiConversation = AIConversation::getByConversationId($conversationId);
        if (!$aiConversation || $aiConversation['status'] !== 'active') {
            return [];
        }

        // Buscar mensagens da IA usando SQL direto
        $sql = "SELECT m.*, aia.name as ai_agent_name
                FROM messages m
                LEFT JOIN ai_agents aia ON m.ai_agent_id = aia.id
                WHERE m.conversation_id = ? 
                AND m.ai_agent_id = ?
                AND m.sender_type = 'agent'
                ORDER BY m.created_at DESC
                LIMIT ? OFFSET ?";
        
        $messages = \App\Helpers\Database::fetchAll($sql, [
            $conversationId,
            $aiConversation['ai_agent_id'],
            $limit,
            $offset
        ]);

        $result = [];
        foreach ($messages as $message) {
            // Buscar tools utilizadas nesta mensagem específica (se houver metadata)
            $toolsUsed = [];
            if (!empty($message['metadata'])) {
                $metadata = is_string($message['metadata']) 
                    ? json_decode($message['metadata'], true) 
                    : $message['metadata'];
                
                if (isset($metadata['tools_used']) && is_array($metadata['tools_used'])) {
                    $toolsUsed = $metadata['tools_used'];
                }
            }

            $result[] = [
                'id' => $message['id'],
                'content' => $message['content'],
                'created_at' => $message['created_at'],
                'tools_used' => $toolsUsed,
                'confidence' => null // Pode ser adicionado no futuro
            ];
        }

        return $result;
    }

    /**
     * Log específico para agentes de IA
     */
    private static function logAI(string $message): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/ai-agents.log';
        $line = '[' . date('Y-m-d H:i:s') . "] {$message}\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    /**
     * Adicionar agente de IA à conversa
     */
    public static function addAIAgent(int $conversationId, array $data): array
    {
        self::logAI("=== addAIAgent INÍCIO === conversationId={$conversationId}");
        self::logAI("Dados recebidos: " . json_encode($data));
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            self::logAI("ERRO: Conversa não encontrada");
            throw new \Exception('Conversa não encontrada');
        }
        self::logAI("Conversa encontrada: ID={$conversation['id']}, status={$conversation['status']}");

        $errors = Validator::validate($data, [
            'ai_agent_id' => 'required|integer',
            'process_immediately' => 'nullable|boolean',
            'assume_conversation' => 'nullable|boolean',
            'only_if_unassigned' => 'nullable|boolean'
        ]);

        if (!empty($errors)) {
            self::logAI("ERRO: Validação falhou: " . json_encode($errors));
            throw new \InvalidArgumentException('Dados inválidos: ' . json_encode($errors));
        }

        $aiAgentId = (int)$data['ai_agent_id'];
        $processImmediately = $data['process_immediately'] ?? false;
        $assumeConversation = $data['assume_conversation'] ?? false;
        $onlyIfUnassigned = $data['only_if_unassigned'] ?? false;
        
        self::logAI("Parâmetros: aiAgentId={$aiAgentId}, processImmediately=" . ($processImmediately ? 'true' : 'false') . 
                   ", assumeConversation=" . ($assumeConversation ? 'true' : 'false') . 
                   ", onlyIfUnassigned=" . ($onlyIfUnassigned ? 'true' : 'false'));

        // Verificar se agente existe e está ativo
        $aiAgent = AIAgent::find($aiAgentId);
        if (!$aiAgent || !$aiAgent['enabled']) {
            self::logAI("ERRO: Agente de IA não encontrado ou inativo (aiAgentId={$aiAgentId})");
            throw new \Exception('Agente de IA não encontrado ou inativo');
        }
        self::logAI("Agente encontrado: {$aiAgent['name']} (ID={$aiAgent['id']})");

        // Verificar se pode receber mais conversas
        if (!AIAgent::canReceiveMoreConversations($aiAgentId)) {
            self::logAI("ERRO: Agente atingiu limite de conversas");
            throw new \Exception('Agente de IA atingiu o limite máximo de conversas');
        }

        // Verificar se já tem IA ativa
        $existingAI = AIConversation::getByConversationId($conversationId);
        self::logAI("IA existente: " . ($existingAI ? "ID={$existingAI['id']}, status={$existingAI['status']}" : "nenhuma"));
        
        if ($existingAI && $existingAI['status'] === 'active') {
            self::logAI("ERRO: Conversa já possui IA ativa");
            throw new \Exception('Conversa já possui um agente de IA ativo');
        }

        // Verificar se tem agente humano (se only_if_unassigned = true)
        if ($onlyIfUnassigned && !empty($conversation['agent_id'])) {
            self::logAI("ERRO: Conversa já possui agente humano e only_if_unassigned=true");
            throw new \Exception('Conversa já possui agente humano atribuído');
        }

        // Se assume_conversation = true, remover agente humano
        if ($assumeConversation && !empty($conversation['agent_id'])) {
            $oldAgentId = $conversation['agent_id'];
            self::logAI("Removendo agente humano ID={$oldAgentId}");
            Conversation::update($conversationId, ['agent_id' => null]);
            
            // Atualizar contagem do agente antigo
            \App\Models\User::updateConversationsCount($oldAgentId);
        }

        // Criar registro de conversa de IA
        self::logAI("Criando registro AIConversation...");
        $aiConversationId = AIConversation::create([
            'conversation_id' => $conversationId,
            'ai_agent_id' => $aiAgentId,
            'messages' => json_encode([]),
            'status' => 'active'
        ]);
        self::logAI("AIConversation criada: ID={$aiConversationId}");

        // Atualizar contagem de conversas do agente
        AIAgent::updateConversationsCount($aiAgentId);
        self::logAI("Contagem do agente atualizada");

        // Processar mensagem imediatamente se solicitado
        if ($processImmediately) {
            try {
                // Buscar última mensagem do contato usando SQL direto
                $lastMessageSql = "SELECT * 
                                  FROM messages 
                                  WHERE conversation_id = ? 
                                  AND sender_type = 'contact'
                                  ORDER BY created_at DESC 
                                  LIMIT 1";
                $lastMessage = \App\Helpers\Database::fetch($lastMessageSql, [$conversationId]);

                if ($lastMessage) {
                    AIAgentService::processMessage(
                        $conversationId,
                        $aiAgentId,
                        $lastMessage['content']
                    );
                } else {
                    // Se não há mensagens, processar conversa (pode enviar boas-vindas)
                    AIAgentService::processConversation($conversationId, $aiAgentId);
                }
            } catch (\Exception $e) {
                error_log("Erro ao processar mensagem imediatamente: " . $e->getMessage());
                // Não falhar a criação se processamento falhar
            }
        }

        // Criar mensagem de sistema informando adição da IA
        try {
            ConversationService::sendMessage(
                $conversationId,
                "🤖 Agente de IA '{$aiAgent['name']}' foi adicionado à conversa.",
                'system',
                null,
                [],
                'system'
            );
        } catch (\Exception $e) {
            error_log("Erro ao criar mensagem de sistema: " . $e->getMessage());
        }

        // Invalidar cache
        ConversationService::invalidateCache($conversationId);

        // Notificar via WebSocket
        try {
            $updatedConversation = Conversation::findWithRelations($conversationId);
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $updatedConversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }

        return [
            'success' => true,
            'ai_conversation_id' => $aiConversationId,
            'message' => 'Agente de IA adicionado com sucesso'
        ];
    }

    /**
     * Remover agente de IA da conversa
     */
    public static function removeAIAgent(int $conversationId, array $data = []): array
    {
        self::logAI("=== removeAIAgent INÍCIO === conversationId={$conversationId}");
        self::logAI("Dados recebidos: " . json_encode($data));
        
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            self::logAI("ERRO: Conversa não encontrada");
            throw new \Exception('Conversa não encontrada');
        }

        $assignToHuman = $data['assign_to_human'] ?? false;
        $humanAgentId = isset($data['human_agent_id']) ? (int)$data['human_agent_id'] : null;
        $reason = $data['reason'] ?? 'Removido manualmente';
        
        self::logAI("Parâmetros: assignToHuman=" . ($assignToHuman ? 'true' : 'false') . 
                   ", humanAgentId=" . ($humanAgentId ?? 'null') . 
                   ", reason={$reason}");

        // Verificar se tem IA ativa
        $aiConversation = AIConversation::getByConversationId($conversationId);
        self::logAI("AIConversation: " . ($aiConversation ? "ID={$aiConversation['id']}, status={$aiConversation['status']}" : "nenhuma"));
        
        if (!$aiConversation || $aiConversation['status'] !== 'active') {
            self::logAI("ERRO: Conversa não possui IA ativa");
            throw new \Exception('Conversa não possui agente de IA ativo');
        }

        $aiAgent = AIAgent::find($aiConversation['ai_agent_id']);
        $aiAgentName = $aiAgent ? $aiAgent['name'] : 'Agente de IA';
        self::logAI("Removendo agente: {$aiAgentName} (ID={$aiConversation['ai_agent_id']})");

        // Desativar conversa de IA
        AIConversation::updateStatus($aiConversation['id'], 'removed');
        self::logAI("Status atualizado para 'removed'");
        
        // Atualizar contagem de conversas do agente
        AIAgent::updateConversationsCount($aiConversation['ai_agent_id']);
        self::logAI("Contagem do agente atualizada");

        // Atribuir a humano se solicitado
        if ($assignToHuman) {
            if ($humanAgentId) {
                // Atribuir a agente específico
                try {
                    ConversationService::assignToAgent($conversationId, $humanAgentId, true);
                } catch (\Exception $e) {
                    error_log("Erro ao atribuir agente humano: " . $e->getMessage());
                }
            } else {
                // Atribuição automática
                try {
                    $assignedId = ConversationSettingsService::autoAssignConversation(
                        $conversationId,
                        $conversation['department_id'] ?? null,
                        $conversation['funnel_id'] ?? null,
                        $conversation['funnel_stage_id'] ?? null
                    );
                    
                    // Se retornou negativo, ainda é IA - não atribuir
                    if ($assignedId !== null && $assignedId > 0) {
                        ConversationService::assignToAgent($conversationId, $assignedId, true);
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao atribuir automaticamente: " . $e->getMessage());
                }
            }
        }

        // Criar mensagem de sistema informando remoção
        try {
            $message = "🤖 Agente de IA '{$aiAgentName}' foi removido da conversa.";
            if ($reason && $reason !== 'Removido manualmente') {
                $message .= " Motivo: {$reason}";
            }
            
            ConversationService::sendMessage(
                $conversationId,
                $message,
                'system',
                null,
                [],
                'system'
            );
        } catch (\Exception $e) {
            error_log("Erro ao criar mensagem de sistema: " . $e->getMessage());
        }

        // Invalidar cache
        ConversationService::invalidateCache($conversationId);

        // Notificar via WebSocket
        try {
            $updatedConversation = Conversation::findWithRelations($conversationId);
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $updatedConversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Agente de IA removido com sucesso'
        ];
    }

    /**
     * Listar agentes de IA disponíveis
     */
    public static function getAvailableAgents(): array
    {
        return AIAgent::getAvailableAgents();
    }
}

