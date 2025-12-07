<?php
/**
 * Service ConversationService
 * Lógica de negócio para conversas
 */

namespace App\Services;

use App\Models\Conversation;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Helpers\Validator;

class ConversationService
{
    /**
     * Diretório de cache
     */
    private static string $cacheDir = __DIR__ . '/../../storage/cache/conversations/';
    
    /**
     * TTL do cache em segundos (5 minutos)
     */
    private static int $cacheTTL = 300;
    
    /**
     * Criar nova conversa
     */
    public static function create(array $data): array
    {
        // Validar dados
        $errors = Validator::validate($data, [
            'contact_id' => 'required|integer',
            'channel' => 'required|string|in:whatsapp,email,chat,telegram'
        ]);

        // Converter erros para array simples
        $flatErrors = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $flatErrors[] = $error;
            }
        }
        
        if (!empty($flatErrors)) {
            throw new \Exception('Dados inválidos: ' . implode(', ', $flatErrors));
        }

        // Verificar se contato existe
        $contact = Contact::find($data['contact_id']);
        if (!$contact) {
            throw new \Exception('Contato não encontrado');
        }

        // Criar conversa
        // Verificar se deve atribuir automaticamente
        $agentId = $data['agent_id'] ?? null;
        $aiAgentId = null;
        
        if (!$agentId) {
            // Tentar atribuição automática usando configurações
            try {
                $assignedId = \App\Services\ConversationSettingsService::autoAssignConversation(
                    0, // conversationId ainda não existe
                    $data['department_id'] ?? null,
                    $data['funnel_id'] ?? null,
                    $data['stage_id'] ?? null
                );
                
                // Se ID for negativo, é um agente de IA
                if ($assignedId !== null && $assignedId < 0) {
                    $aiAgentId = abs($assignedId);
                    $agentId = null; // Não atribuir a usuário humano
                } else {
                    $agentId = $assignedId;
                }
            } catch (\Exception $e) {
                error_log("Erro ao atribuir automaticamente: " . $e->getMessage());
            }
        }
        
        $conversationData = [
            'contact_id' => $data['contact_id'],
            'channel' => $data['channel'],
            'status' => 'open',
            'agent_id' => $agentId,
            'department_id' => $data['department_id'] ?? null,
            'funnel_id' => $data['funnel_id'] ?? null,
            'funnel_stage_id' => $data['stage_id'] ?? null,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null
        ];

        $id = Conversation::create($conversationData);
        
        // Se foi atribuído a agente humano, atualizar contagem
        if ($agentId) {
            \App\Models\User::updateConversationsCount($agentId);
        }
        
        // Se foi atribuído a agente de IA, criar registro de conversa de IA e processar
        if ($aiAgentId) {
            \App\Models\AIConversation::create([
                'conversation_id' => $id,
                'ai_agent_id' => $aiAgentId,
                'messages' => json_encode([]),
                'status' => 'active'
            ]);
            \App\Models\AIAgent::updateConversationsCount($aiAgentId);
            
            // Processar conversa com agente de IA em background
            try {
                \App\Services\AIAgentService::processConversation($id, $aiAgentId);
            } catch (\Exception $e) {
                error_log("Erro ao processar conversa com agente de IA: " . $e->getMessage());
                // Continuar normalmente mesmo se falhar
            }
        }
        
        // Invalidar cache de conversas
        self::invalidateCache($id);
        
        // Obter conversa criada para notificar via WebSocket
        $conversation = Conversation::findWithRelations($id);
        try {
            \App\Helpers\WebSocket::notifyNewConversation($conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações para nova conversa
        try {
            \App\Services\AutomationService::executeForNewConversation($id);
        } catch (\Exception $e) {
            // Log erro mas não interromper criação da conversa
            error_log("Erro ao executar automações: " . $e->getMessage());
        }
        
        return $conversation;
    }

    /**
     * Listar conversas com filtros
     */
    public static function list(array $filters = [], int $userId = null): array
    {
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }
        
        try {
            // Verificar se pode usar cache (apenas para listas sem filtros complexos)
            $canUseCache = self::canUseCache($filters);
            
            if ($canUseCache) {
                $cacheKey = "user_{$userId}_conversations_" . md5(json_encode($filters));
                $cached = self::getCache($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
            
            // Obter todas as conversas
            $conversations = Conversation::getAll($filters);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("Erro em ConversationService::list: " . $e->getMessage(), 'conversas.log');
            \App\Helpers\Log::context("Filtros", $filters, 'conversas.log', 'ERROR');
            \App\Helpers\Log::error("Stack trace: " . $e->getTraceAsString(), 'conversas.log');
            throw $e;
        }
        
        // Filtrar por permissões
        $filtered = [];
        foreach ($conversations as $conversation) {
            if (\App\Services\PermissionService::canViewConversation($userId, $conversation)) {
                $filtered[] = $conversation;
            }
        }
        
        // Salvar no cache se aplicável
        if ($canUseCache) {
            $cacheKey = "user_{$userId}_conversations_" . md5(json_encode($filters));
            self::setCache($cacheKey, $filtered);
        }
        
        return $filtered;
    }

    /**
     * Obter conversas do usuário (para polling)
     */
    public static function getUserConversations(int $userId): array
    {
        return self::list([], $userId);
    }
    
    /**
     * Verificar se pode usar cache baseado nos filtros
     */
    private static function canUseCache(array $filters): bool
    {
        // Não usar cache se houver filtros complexos que mudam frequentemente
        $excludedFilters = ['date_from', 'date_to', 'search', 'message_search'];
        
        foreach ($excludedFilters as $filter) {
            if (!empty($filters[$filter])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Limpar cache de conversas do usuário
     */
    public static function clearUserCache(int $userId): void
    {
        $pattern = self::$cacheDir . "user_{$userId}_*";
        $files = glob($pattern);
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Limpar todo o cache de conversas
     */
    public static function clearAllCache(): void
    {
        $files = glob(self::$cacheDir . "*");
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Invalidar cache quando conversa é atualizada
     */
    public static function invalidateCache(int $conversationId = null): void
    {
        // Limpar cache de todos os usuários quando conversa é atualizada
        self::clearAllCache();
    }
    
    /**
     * Obter valor do cache
     */
    private static function getCache(string $key): ?array
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key) . '.cache';
        
        if (!file_exists($file)) {
            return null;
        }
        
        // Verificar TTL
        if (time() - filemtime($file) > self::$cacheTTL) {
            @unlink($file);
            return null;
        }
        
        $data = @file_get_contents($file);
        if ($data === false) {
            return null;
        }
        
        $decoded = @json_decode($data, true);
        return $decoded['value'] ?? null;
    }
    
    /**
     * Salvar valor no cache
     */
    private static function setCache(string $key, array $value): void
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key) . '.cache';
        $data = json_encode([
            'key' => $key,
            'value' => $value,
            'created_at' => time()
        ]);
        
        @file_put_contents($file, $data);
    }

    /**
     * Obter conversa com mensagens
     */
    public static function getConversation(int $conversationId): ?array
    {
        $conversation = Conversation::findWithRelations($conversationId);
        
        if (!$conversation) {
            return null;
        }

        // Obter mensagens
        $messages = Message::getMessagesWithSenderDetails($conversationId);
        
        // Formatar mensagens para a view (adicionar type e direction)
        foreach ($messages as &$msg) {
            // Determinar type baseado em message_type
            if (($msg['message_type'] ?? 'text') === 'note') {
                $msg['type'] = 'note';
            } else {
                $msg['type'] = 'message';
            }
            
            // Determinar direction baseado em sender_type
            // Mensagens de agentes são sempre outgoing (enviadas pelo sistema/agente)
            // Mensagens de contatos são sempre incoming (recebidas)
            if (($msg['sender_type'] ?? '') === 'agent') {
                $msg['direction'] = 'outgoing';
            } else {
                $msg['direction'] = 'incoming';
            }
        }
        unset($msg); // Limpar referência
        
        $conversation['messages'] = $messages;
        
        // Obter tags da conversa
        try {
            if (class_exists('\App\Models\Tag')) {
                $conversation['tags'] = \App\Models\Tag::getByConversation($conversationId);
            } else {
                $conversation['tags'] = [];
            }
        } catch (\Exception $e) {
            $conversation['tags'] = [];
        }
        
        // Verificar se conversa está com agente de IA
        try {
            $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
            if ($aiConversation && $aiConversation['status'] === 'active') {
                $conversation['has_ai_agent'] = true;
                $conversation['ai_agent_id'] = $aiConversation['ai_agent_id'];
                $aiAgent = \App\Models\AIAgent::find($aiConversation['ai_agent_id']);
                $conversation['ai_agent_name'] = $aiAgent ? $aiAgent['name'] : null;
            } else {
                $conversation['has_ai_agent'] = false;
                $conversation['ai_agent_id'] = null;
                $conversation['ai_agent_name'] = null;
            }
        } catch (\Exception $e) {
            $conversation['has_ai_agent'] = false;
            $conversation['ai_agent_id'] = null;
            $conversation['ai_agent_name'] = null;
        }

        return $conversation;
    }

    /**
     * Obter conversa com mensagens (método legado)
     */
    public static function getWithMessages(int $conversationId, int $userId = null): ?array
    {
        return self::getConversation($conversationId);
    }

    /**
     * Atribuir conversa a agente
     */
    public static function assignToAgent(int $conversationId, int $agentId): array
    {
        // Obter conversa para verificar contexto
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        // Verificar se agente existe e está ativo
        $agent = User::find($agentId);
        if (!$agent || $agent['status'] !== 'active') {
            throw new \Exception('Agente não encontrado ou inativo');
        }

        // Verificar limites usando ConversationSettingsService
        if (!\App\Services\ConversationSettingsService::canAssignToAgent(
            $agentId,
            $conversation['department_id'] ?? null,
            $conversation['funnel_id'] ?? null,
            $conversation['funnel_stage_id'] ?? null
        )) {
            throw new \Exception('Agente atingiu o limite máximo de conversas ou não está disponível');
        }

        // Atribuir
        $oldAgentId = $conversation['agent_id'] ?? null;
        Conversation::update($conversationId, ['agent_id' => $agentId]);
        
        // Invalidar cache de conversas
        self::invalidateCache($conversationId);
        
        // Atualizar contagem de conversas dos agentes
        if ($oldAgentId && $oldAgentId != $agentId) {
            User::updateConversationsCount($oldAgentId);
        }
        User::updateConversationsCount($agentId);
        
        // Obter conversa atualizada para notificar via WebSocket
        $conversation = Conversation::findWithRelations($conversationId);
        
        // Criar notificação para o agente
        try {
            if (class_exists('\App\Services\NotificationService')) {
                \App\Services\NotificationService::notifyConversationAssigned(
                    $agentId,
                    $conversationId,
                    $conversation['contact_name'] ?? 'Contato'
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
        }
        
        try {
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações para atualização
        try {
            \App\Services\AutomationService::executeForConversationUpdated($conversationId, ['agent_id' => $agentId]);
        } catch (\Exception $e) {
            error_log("Erro ao executar automações: " . $e->getMessage());
        }
        
        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logConversationAssigned($conversationId, $agentId, $oldAgentId);
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }

        return $conversation;
    }

    /**
     * Escalar conversa de agente de IA para humano
     */
    public static function escalateFromAI(int $conversationId, ?int $agentId = null): array
    {
        // Verificar se conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }

        // Verificar se conversa está com agente de IA
        $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
        if (!$aiConversation || $aiConversation['status'] !== 'active') {
            throw new \Exception('Conversa não está atribuída a um agente de IA ativo');
        }

        // Se não foi especificado agente, tentar atribuição automática
        if (!$agentId) {
            try {
                $assignedId = \App\Services\ConversationSettingsService::autoAssignConversation(
                    $conversationId,
                    $conversation['department_id'] ?? null,
                    $conversation['funnel_id'] ?? null,
                    $conversation['funnel_stage_id'] ?? null
                );
                
                // Se retornar negativo, ainda é IA - não escalar
                if ($assignedId !== null && $assignedId < 0) {
                    throw new \Exception('Nenhum agente humano disponível. Tente selecionar um agente manualmente.');
                }
                
                $agentId = $assignedId;
            } catch (\Exception $e) {
                throw new \Exception('Erro ao atribuir automaticamente: ' . $e->getMessage());
            }
        }

        // Verificar se agente existe e está ativo
        $agent = User::find($agentId);
        if (!$agent || $agent['status'] !== 'active') {
            throw new \Exception('Agente não encontrado ou inativo');
        }

        // Verificar limites
        if (!\App\Services\ConversationSettingsService::canAssignToAgent(
            $agentId,
            $conversation['department_id'] ?? null,
            $conversation['funnel_id'] ?? null,
            $conversation['funnel_stage_id'] ?? null
        )) {
            throw new \Exception('Agente atingiu o limite máximo de conversas ou não está disponível');
        }

        // Atualizar status da conversa de IA
        \App\Models\AIConversation::updateStatus(
            $aiConversation['id'],
            'escalated',
            $agentId
        );

        // Atribuir conversa ao agente humano
        Conversation::update($conversationId, ['agent_id' => $agentId]);
        
        // Invalidar cache
        self::invalidateCache($conversationId);
        
        // Atualizar contagem de conversas
        User::updateConversationsCount($agentId);
        
        // Obter conversa atualizada
        $conversation = Conversation::findWithRelations($conversationId);
        
        // Criar mensagem de sistema informando escalação
        try {
            $aiAgent = \App\Models\AIAgent::find($aiConversation['ai_agent_id']);
            $aiAgentName = $aiAgent ? $aiAgent['name'] : 'Assistente IA';
            $agentName = $agent['name'] ?? 'Agente';
            
            self::sendMessage(
                $conversationId,
                "🔄 Esta conversa foi escalada de {$aiAgentName} para {$agentName}.",
                'system',
                null,
                [],
                'system'
            );
        } catch (\Exception $e) {
            error_log("Erro ao criar mensagem de sistema: " . $e->getMessage());
        }
        
        // Criar notificação para o agente
        try {
            if (class_exists('\App\Services\NotificationService')) {
                \App\Services\NotificationService::notifyConversationAssigned(
                    $agentId,
                    $conversationId,
                    $conversation['contact_name'] ?? 'Contato',
                    'Conversa escalada de IA'
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao criar notificação: " . $e->getMessage());
        }
        
        // Notificar via WebSocket
        try {
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações
        try {
            \App\Services\AutomationService::executeForConversationUpdated($conversationId, [
                'agent_id' => $agentId,
                'escalated_from_ai' => true
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao executar automações: " . $e->getMessage());
        }
        
        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logConversationEscalated(
                    $conversationId,
                    $aiConversation['ai_agent_id'],
                    $agentId
                );
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }

        return $conversation;
    }

    /**
     * Fechar conversa
     */
    public static function close(int $conversationId): array
    {
        // Obter conversa antes de fechar para atualizar contagem do agente
        $conversation = Conversation::find($conversationId);
        $agentId = $conversation['agent_id'] ?? null;
        
        if (Conversation::update($conversationId, [
            'status' => 'closed',
            'resolved_at' => date('Y-m-d H:i:s')
        ])) {
            // Invalidar cache de conversas
            self::invalidateCache($conversationId);
            
            // Atualizar contagem de conversas do agente
            if ($agentId) {
                User::updateConversationsCount($agentId);
            }
            
            // Obter conversa atualizada para notificar via WebSocket
            $conversation = Conversation::findWithRelations($conversationId);
            
            // Criar notificação para o agente (se houver)
            if (!empty($conversation['agent_id'])) {
                try {
                    if (class_exists('\App\Services\NotificationService')) {
                        \App\Services\NotificationService::notifyConversationClosed(
                            $conversation['agent_id'],
                            $conversationId,
                            $conversation['contact_name'] ?? 'Contato'
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao criar notificação: " . $e->getMessage());
                }
            }
            
            try {
                \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
            // Executar automações para resolução
            try {
                \App\Services\AutomationService::executeForConversationResolved($conversationId);
            } catch (\Exception $e) {
                error_log("Erro ao executar automações: " . $e->getMessage());
            }
            
            // Log de atividade
            try {
                if (class_exists('\App\Services\ActivityService')) {
                    \App\Services\ActivityService::logConversationClosed($conversationId, \App\Helpers\Auth::id());
                }
            } catch (\Exception $e) {
                error_log("Erro ao logar atividade: " . $e->getMessage());
            }
            
            return $conversation;
        }
        return Conversation::findWithRelations($conversationId);
    }

    /**
     * Reabrir conversa
     */
    public static function reopen(int $conversationId): array
    {
        // Obter conversa antes de reabrir para atualizar contagem do agente
        $conversation = Conversation::find($conversationId);
        $agentId = $conversation['agent_id'] ?? null;
        
        if (Conversation::update($conversationId, ['status' => 'open'])) {
            // Invalidar cache de conversas
            self::invalidateCache($conversationId);
            
            // Atualizar contagem de conversas do agente
            if ($agentId) {
                User::updateConversationsCount($agentId);
            }
            
            // Obter conversa atualizada para notificar via WebSocket
            $conversation = Conversation::findWithRelations($conversationId);
            
            // Criar notificação para o agente (se houver)
            if (!empty($conversation['agent_id'])) {
                try {
                    if (class_exists('\App\Services\NotificationService')) {
                        \App\Services\NotificationService::notifyConversationReopened(
                            $conversation['agent_id'],
                            $conversationId,
                            $conversation['contact_name'] ?? 'Contato'
                        );
                    }
                } catch (\Exception $e) {
                    error_log("Erro ao criar notificação: " . $e->getMessage());
                }
            }
            
            try {
                \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
            // Executar automações para atualização
            try {
                \App\Services\AutomationService::executeForConversationUpdated($conversationId, ['status' => 'open']);
            } catch (\Exception $e) {
                error_log("Erro ao executar automações: " . $e->getMessage());
            }
            
            return $conversation;
        }
        return Conversation::findWithRelations($conversationId);
    }

    /**
     * Enviar mensagem na conversa
     */
    public static function sendMessage(int $conversationId, string $content, string $senderType = 'agent', ?int $senderId = null, array $attachments = [], ?string $messageType = null, ?int $quotedMessageId = null, ?int $aiAgentId = null): ?int
    {
        if ($senderId === null && $aiAgentId === null) {
            $senderId = \App\Helpers\Auth::id();
        }

        // Validar que há conteúdo ou anexos
        if (empty(trim($content)) && empty($attachments)) {
            throw new \Exception('Mensagem não pode estar vazia');
        }
        
        // Verificar se conversa existe
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }

        // Processar anexos se houver
        $attachmentsData = [];
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['path'])) {
                    $attachmentsData[] = $attachment;
                }
            }
        }

        // Determinar tipo de mensagem
        if ($messageType === null) {
            $messageType = 'text';
            if (!empty($attachmentsData)) {
                $firstAttachment = $attachmentsData[0];
                $messageType = $firstAttachment['type'] ?? 'text';
            }
        }

        // Criar mensagem
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId ?? 0, // Se for IA, pode ser 0
            'sender_type' => $senderType,
            'content' => $content,
            'message_type' => $messageType,
            'status' => 'sent'
        ];
        
        // Adicionar ai_agent_id se fornecido
        if ($aiAgentId !== null) {
            $messageData['ai_agent_id'] = $aiAgentId;
        }

        // Adicionar quoted_message_id se houver (salvar em campos separados, SEM modificar o content)
        if ($quotedMessageId) {
            $messageData['quoted_message_id'] = $quotedMessageId;
            
            // Buscar mensagem citada para obter informações
            $quotedMessage = Message::find($quotedMessageId);
            if ($quotedMessage) {
                $quotedSenderName = 'Remetente';
                if ($quotedMessage['sender_type'] === 'agent') {
                    $sender = \App\Models\User::find($quotedMessage['sender_id']);
                    $quotedSenderName = $sender['name'] ?? 'Agente';
                } else {
                    $contact = \App\Models\Contact::find($quotedMessage['sender_id']);
                    $quotedSenderName = $contact['name'] ?? 'Contato';
                }
                
                // Pegar o conteúdo original da mensagem citada (sem o prefixo ↩️ se tiver)
                $quotedText = $quotedMessage['content'] ?? '';
                
                // Salvar nome do remetente e texto citado
                $messageData['quoted_sender_name'] = $quotedSenderName;
                $messageData['quoted_text'] = $quotedText;
            }
        }

        if (!empty($attachmentsData)) {
            $messageData['attachments'] = $attachmentsData;
        }

        $messageId = Message::createMessage($messageData);

        // **ENVIAR PARA WHATSAPP** se a mensagem for do agente e canal for WhatsApp
        if ($senderType === 'agent' && $conversation['channel'] === 'whatsapp' && !empty($conversation['whatsapp_account_id'])) {
            try {
                // Obter contato para pegar o telefone
                $contact = \App\Models\Contact::find($conversation['contact_id']);
                if ($contact && !empty($contact['phone'])) {
                    // Enviar mensagem via WhatsApp
                    $whatsappResult = \App\Services\WhatsAppService::sendMessage(
                        $conversation['whatsapp_account_id'],
                        $contact['phone'],
                        $content,
                        []
                    );
                    
                    // Atualizar status e external_id da mensagem
                    if ($whatsappResult['success']) {
                        Message::update($messageId, [
                            'external_id' => $whatsappResult['message_id'] ?? null,
                            'status' => 'sent'
                        ]);
                    }
                }
            } catch (\Exception $e) {
                error_log("Erro ao enviar mensagem para WhatsApp: " . $e->getMessage());
                // Marcar mensagem como erro
                Message::update($messageId, [
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }
        }

        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logMessageSent($messageId, $conversationId, $senderType, $senderId);
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }

        // Atualizar updated_at da conversa
        Conversation::update($conversationId, []);
        
        // Invalidar cache de conversas (mensagem nova atualiza a lista)
        self::invalidateCache($conversationId);
        
        // Obter mensagem criada com detalhes do remetente para notificar via WebSocket
        $messages = Message::getMessagesWithSenderDetails($conversationId);
        $message = null;
        foreach ($messages as $msg) {
            if ($msg['id'] == $messageId) {
                $message = $msg;
                break;
            }
        }
        
        if ($message) {
            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyNewMessage($conversationId, $message);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
        // Se mensagem é do contato e conversa está atribuída a agente de IA, processar automaticamente
        if ($senderType === 'contact') {
            $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
            if ($aiConversation && $aiConversation['status'] === 'active') {
                try {
                    // Processar mensagem com agente de IA em background (assíncrono)
                    // Por enquanto, processar diretamente (em produção, usar fila de jobs)
                    $aiResponse = \App\Services\AIAgentService::processMessage(
                        $conversationId,
                        $aiConversation['ai_agent_id'],
                        $content
                    );
                    
                    // A resposta já foi enviada pelo processMessage
                } catch (\Exception $e) {
                    error_log("Erro ao processar mensagem com agente de IA: " . $e->getMessage());
                    // Continuar normalmente mesmo se falhar
                }
            }
        }
        
        // Criar notificações para agentes relacionados à conversa
        try {
            if (class_exists('\App\Services\NotificationService')) {
                // Notificar agente atribuído (se houver e não for IA)
                if (!empty($conversation['agent_id']) && $senderType === 'contact') {
                    // Verificar se não é agente de IA
                    $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
                    if (!$aiConversation || $aiConversation['status'] !== 'active') {
                        \App\Services\NotificationService::notifyNewMessage(
                            $conversation['agent_id'],
                            $conversationId,
                            $message
                        );
                    }
                }
                
                // Notificar setor (se não houver agente atribuído e mensagem for do contato)
                if (empty($conversation['agent_id']) && $senderType === 'contact' && !empty($conversation['department_id'])) {
                    \App\Services\NotificationService::notifyDepartment($conversation['department_id'], [
                        'type' => 'message',
                        'title' => 'Nova mensagem',
                        'message' => 'Nova mensagem recebida de ' . ($conversation['contact_name'] ?? 'Contato'),
                        'link' => '/conversations/' . $conversationId,
                            'data' => [
                                'conversation_id' => $conversationId,
                                'message_id' => $messageId
                            ]
                        ]);
                    }
                }
            } catch (\Exception $e) {
                error_log("Erro ao criar notificações: " . $e->getMessage());
            }
        }
        
        // Executar automações para mensagem recebida (se for do contato)
        if ($senderType === 'contact') {
            try {
                \App\Services\AutomationService::executeForMessageReceived($messageId);
            } catch (\Exception $e) {
                error_log("Erro ao executar automações: " . $e->getMessage());
            }
        }

        return $messageId;
    }

    /**
     * Encaminhar mensagem para outra conversa
     */
    public static function forwardMessage(int $messageId, int $targetConversationId, ?int $senderId = null): ?int
    {
        if ($senderId === null) {
            $senderId = \App\Helpers\Auth::id();
        }

        // Buscar mensagem original
        $originalMessage = Message::find($messageId);
        if (!$originalMessage) {
            throw new \Exception('Mensagem não encontrada');
        }

        // Verificar se conversa destino existe
        $targetConversation = Conversation::find($targetConversationId);
        if (!$targetConversation) {
            throw new \Exception('Conversa destino não encontrada');
        }

        // Preparar conteúdo encaminhado
        $forwardedContent = $originalMessage['content'] ?? '';
        
        // Adicionar prefixo de encaminhamento
        $forwardPrefix = '↪️ Mensagem encaminhada';
        if (!empty($forwardedContent)) {
            $forwardedContent = $forwardPrefix . "\n\n" . $forwardedContent;
        } else {
            $forwardedContent = $forwardPrefix;
        }

        // Copiar anexos se houver
        $attachments = [];
        if (!empty($originalMessage['attachments'])) {
            $originalAttachments = is_string($originalMessage['attachments']) 
                ? json_decode($originalMessage['attachments'], true) 
                : $originalMessage['attachments'];
            
            if (is_array($originalAttachments)) {
                foreach ($originalAttachments as $attachment) {
                    // Copiar arquivo físico se necessário
                    if (isset($attachment['path']) && file_exists($attachment['path'])) {
                        $attachments[] = $attachment;
                    }
                }
            }
        }

        // Criar nova mensagem na conversa destino
        $messageData = [
            'conversation_id' => $targetConversationId,
            'sender_id' => $senderId,
            'sender_type' => 'agent',
            'content' => $forwardedContent,
            'message_type' => $originalMessage['message_type'] ?? 'text',
            'status' => 'sent'
        ];

        if (!empty($attachments)) {
            $messageData['attachments'] = $attachments;
        }

        $newMessageId = Message::createMessage($messageData);

        // Atualizar updated_at da conversa destino
        Conversation::update($targetConversationId, []);
        
        // Invalidar cache de conversas
        self::invalidateCache($targetConversationId);

        // Obter mensagem criada com detalhes do remetente para notificar via WebSocket
        $messages = Message::getMessagesWithSenderDetails($targetConversationId);
        $message = null;
        foreach ($messages as $msg) {
            if ($msg['id'] == $newMessageId) {
                $message = $msg;
                break;
            }
        }

        if ($message) {
            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyNewMessage($targetConversationId, $message);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
        }

        return $newMessageId;
    }

    /**
     * Listar conversas para encaminhamento (excluindo a conversa atual)
     */
    public static function listForForwarding(int $excludeConversationId = null, int $userId = null): array
    {
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }

        $filters = [];
        if ($excludeConversationId) {
            // Não usar filtro de exclusão aqui, vamos filtrar depois
        }

        // Obter todas as conversas
        $conversations = Conversation::getAll($filters);

        // Filtrar por permissões e excluir conversa atual
        $filtered = [];
        foreach ($conversations as $conversation) {
            // Excluir conversa atual
            if ($excludeConversationId && isset($conversation['id']) && $conversation['id'] == $excludeConversationId) {
                continue;
            }

            if (\App\Services\PermissionService::canViewConversation($userId, $conversation)) {
                // Formatar para o modal
                $filtered[] = [
                    'id' => $conversation['id'] ?? null,
                    'contact_name' => $conversation['contact_name'] ?? 'Sem nome',
                    'contact_phone' => $conversation['contact_phone'] ?? null,
                    'contact_email' => $conversation['contact_email'] ?? null,
                    'channel' => $conversation['channel'] ?? 'chat',
                    'status' => $conversation['status'] ?? 'open',
                    'last_message' => $conversation['last_message'] ?? null,
                    'last_message_at' => $conversation['last_message_at'] ?? null
                ];
            }
        }

        return $filtered;
    }
}

