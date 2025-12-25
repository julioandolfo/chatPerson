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
use App\Models\WhatsAppAccount;
use App\Models\Funnel;
use App\Models\Setting;
use App\Helpers\Validator;
use App\Helpers\Logger;

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
     * 
     * @param array $data Dados da conversa
     * @param bool $executeAutomationsNow Se FALSE, não executa automações imediatamente (permite executar depois)
     */
    public static function create(array $data, bool $executeAutomationsNow = true): array
    {
        // Validar dados
        $errors = Validator::validate($data, [
            'contact_id' => 'required|integer',
            'channel' => 'required|string|in:whatsapp,instagram,facebook,telegram,mercadolivre,webchat,email,olx,linkedin,google_business,youtube,tiktok,chat'
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

        // ------------------------------------------------------------------
        // Resolver funil/etapa padrão (Integração -> WhatsApp (legacy) -> sistema)
        // ------------------------------------------------------------------
        $funnelId = $data['funnel_id'] ?? null;
        $stageId = $data['stage_id'] ?? null;

        // 1) Defaults da conta de integração, se aplicável (prioridade da integração)
        if (!empty($data['integration_account_id'])) {
            try {
                $account = \App\Models\IntegrationAccount::find((int)$data['integration_account_id']);
                if ($account && !empty($account['default_funnel_id'])) {
                    $funnelId = (int)$account['default_funnel_id'];
                    
                    // Se tem etapa específica configurada, usar ela
                    if (!empty($account['default_stage_id'])) {
                        $stageId = (int)$account['default_stage_id'];
                        Logger::debug("ConversationService::create - Integração: Funil ID {$funnelId}, Etapa específica ID {$stageId}", 'conversas.log');
                    } else {
                        // Se não tem etapa específica, buscar etapa "Entrada" do funil (sistema obrigatório)
                        Logger::debug("ConversationService::create - Integração: Funil ID {$funnelId}, sem etapa específica. Buscando 'Entrada'...", 'conversas.log');
                        $entradaStage = \App\Models\FunnelStage::getSystemStage($funnelId, 'entrada');
                        if ($entradaStage) {
                            $stageId = (int)$entradaStage['id'];
                            Logger::debug("ConversationService::create - Integração: Usando etapa 'Entrada' ID {$stageId}", 'conversas.log');
                        } else {
                            // Fallback: primeira etapa do funil
                            $stages = Funnel::getStages($funnelId);
                            if (!empty($stages)) {
                                $stageId = (int)$stages[0]['id'];
                                Logger::debug("ConversationService::create - Integração: 'Entrada' não encontrada, usando primeira etapa ID {$stageId}", 'conversas.log');
                            }
                        }
                    }
                } else {
                    Logger::debug("ConversationService::create - Integração sem configuração de funil/etapa", 'conversas.log');
                }
            } catch (\Exception $e) {
                error_log("Conversas: erro ao aplicar defaults da conta de integração: " . $e->getMessage());
            }
        }
        
        // 1.1) Defaults da conta WhatsApp (legacy), se aplicável (compatibilidade)
        if ((!$funnelId || !$stageId) && !empty($data['whatsapp_account_id'])) {
            try {
                $account = WhatsAppAccount::find((int)$data['whatsapp_account_id']);
                if ($account && !empty($account['default_funnel_id'])) {
                    $funnelId = $funnelId ?: (int)$account['default_funnel_id'];
                    
                    // Se tem etapa específica configurada, usar ela
                    if (!empty($account['default_stage_id'])) {
                        $stageId = $stageId ?: (int)$account['default_stage_id'];
                        Logger::debug("ConversationService::create - WhatsApp (legacy): Funil ID {$funnelId}, Etapa específica ID {$stageId}", 'conversas.log');
                    } else {
                        // Se não tem etapa específica, buscar etapa "Entrada" do funil (sistema obrigatório)
                        Logger::debug("ConversationService::create - WhatsApp (legacy): Funil ID {$funnelId}, sem etapa específica. Buscando 'Entrada'...", 'conversas.log');
                        $entradaStage = \App\Models\FunnelStage::getSystemStage($funnelId, 'entrada');
                        if ($entradaStage) {
                            $stageId = $stageId ?: (int)$entradaStage['id'];
                            Logger::debug("ConversationService::create - WhatsApp (legacy): Usando etapa 'Entrada' ID {$stageId}", 'conversas.log');
                        } else {
                            // Fallback: primeira etapa do funil
                            $stages = Funnel::getStages($funnelId);
                            if (!empty($stages)) {
                                $stageId = $stageId ?: (int)$stages[0]['id'];
                                Logger::debug("ConversationService::create - WhatsApp (legacy): 'Entrada' não encontrada, usando primeira etapa ID {$stageId}", 'conversas.log');
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("Conversas: erro ao aplicar defaults da conta WhatsApp (legacy): " . $e->getMessage());
            }
        }

        // 2) Fallback: padrão do sistema
        if (!$funnelId || !$stageId) {
            Logger::debug("ConversationService::create - Aplicando fallback do sistema. Funil atual: " . ($funnelId ?? 'NULL') . ", Etapa atual: " . ($stageId ?? 'NULL'), 'conversas.log');
            try {
                $defaultConfig = Setting::get('system_default_funnel_stage');
                if (is_array($defaultConfig)) {
                    $funnelId = $funnelId ?: ($defaultConfig['funnel_id'] ?? null);
                    $stageId = $stageId ?: ($defaultConfig['stage_id'] ?? null);
                    Logger::debug("ConversationService::create - Após fallback sistema: Funil ID " . ($funnelId ?? 'NULL') . ", Etapa ID " . ($stageId ?? 'NULL'), 'conversas.log');
                }
            } catch (\Exception $e) {
                error_log("Conversas: erro ao obter padrão do sistema: " . $e->getMessage());
            }
        } else {
            Logger::debug("ConversationService::create - Funil e etapa já definidos (integração ou outro). Funil ID {$funnelId}, Etapa ID {$stageId}", 'conversas.log');
        }

        // 3) Se tem funil mas não etapa, pegar etapa "Entrada" do funil (sistema obrigatório)
        if ($funnelId && !$stageId) {
            Logger::debug("ConversationService::create - Tem funil mas não tem etapa. Buscando etapa 'Entrada' do funil {$funnelId}", 'conversas.log');
            try {
                // ✅ Buscar etapa "Entrada" (sistema obrigatório)
                $entradaStage = \App\Models\FunnelStage::getSystemStage((int)$funnelId, 'entrada');
                
                if ($entradaStage) {
                    $stageId = (int)$entradaStage['id'];
                    Logger::debug("ConversationService::create - Etapa 'Entrada' encontrada: ID {$stageId}", 'conversas.log');
                } else {
                    // Fallback: se não encontrar etapa "Entrada", pegar primeira etapa qualquer
                    $stages = Funnel::getStages((int)$funnelId);
                    if (!empty($stages)) {
                        $stageId = (int)$stages[0]['id'];
                        Logger::debug("ConversationService::create - Etapa 'Entrada' não encontrada, usando primeira etapa do funil: ID {$stageId}", 'conversas.log');
                    }
                }
            } catch (\Exception $e) {
                error_log("Conversas: erro ao obter etapa de entrada do funil: " . $e->getMessage());
            }
        }

        // Aplicar nos dados
        if ($funnelId) {
            $data['funnel_id'] = $funnelId;
        }
        if ($stageId) {
            $data['stage_id'] = $stageId;
        }
        
        Logger::debug("ConversationService::create - FINAL: Conversa será criada em Funil ID " . ($data['funnel_id'] ?? 'NULL') . ", Etapa ID " . ($data['stage_id'] ?? 'NULL'), 'conversas.log');

        // Criar conversa
        // Verificar se deve atribuir automaticamente
        $agentId = $data['agent_id'] ?? null;
        $aiAgentId = null;
        
        if (!$agentId) {
            // PRIMEIRO: Verificar se há agente atribuído ao contato (conversa fechada anterior)
            try {
                $contactAgentId = \App\Services\ContactAgentService::shouldAutoAssignOnConversation(
                    $data['contact_id']
                );
                
                if ($contactAgentId) {
                    $agentId = $contactAgentId;
                    Logger::debug("Agente atribuído automaticamente do contato: {$agentId}", 'conversas.log');
                }
            } catch (\Exception $e) {
                error_log("Erro ao verificar agente do contato: " . $e->getMessage());
            }
            
            // Se ainda não tem agente, tentar atribuição automática usando configurações
            if (!$agentId) {
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
        }
        
        // NOTA: NÃO definir "agente do contato" aqui na criação da conversa.
        // O agente do contato só é definido quando a conversa for atribuída via assignToAgent()
        // que é chamado quando um agente é atribuído (manual ou automaticamente por automações).
        // Se a atribuição veio do "agente do contato" (shouldAutoAssignOnConversation),
        // significa que o contato já tinha um agente definido, então não precisa fazer nada aqui.
        
        $conversationData = [
            'contact_id' => $data['contact_id'],
            'channel' => $data['channel'],
            'status' => 'open',
            'agent_id' => $agentId,
            'department_id' => $data['department_id'] ?? null,
            'funnel_id' => $data['funnel_id'] ?? null,
            'funnel_stage_id' => $data['stage_id'] ?? null,
            'integration_account_id' => $data['integration_account_id'] ?? null,
            'whatsapp_account_id' => $data['whatsapp_account_id'] ?? null // Legacy, manter para compatibilidade
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
            Logger::debug("Notificando nova conversa via WebSocket: conversationId={$conversation['id']}, contactId={$conversation['contact_id']}", 'conversas.log');
            \App\Helpers\WebSocket::notifyNewConversation($conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações para nova conversa (se solicitado)
        if ($executeAutomationsNow) {
        try {
            \App\Helpers\Logger::automation("ConversationService::create - Tentando executar automações para conversa ID: {$id}, funnel_id: {$conversationData['funnel_id']}, stage_id: {$conversationData['funnel_stage_id']}");
            \App\Services\AutomationService::executeForNewConversation($id);
            \App\Helpers\Logger::automation("ConversationService::create - Automações executadas com sucesso para conversa ID: {$id}");
        } catch (\Exception $e) {
            // Log erro mas não interromper criação da conversa
            error_log("Erro ao executar automações: " . $e->getMessage());
            \App\Helpers\Logger::automation("ConversationService::create - ERRO ao executar automações: " . $e->getMessage());
            }
        } else {
            \App\Helpers\Logger::automation("ConversationService::create - Execução de automações ADIADA (será executada pelo chamador)");
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
        
        // Filtrar por permissões e participantes
        $filtered = [];
        $participantConversationIds = [];
        
        // Obter IDs de conversas onde o usuário é participante
        if (class_exists('\App\Models\ConversationParticipant')) {
            $participantConversationIds = \App\Models\ConversationParticipant::getConversationsByParticipant($userId);
        }
        
        foreach ($conversations as $conversation) {
            // Verificar se é participante
            $isParticipant = in_array($conversation['id'], $participantConversationIds);
            
            // Se for participante, sempre pode ver
            if ($isParticipant) {
                $filtered[] = $conversation;
                continue;
            }
            
            // Caso contrário, verificar permissões normais
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
    public static function assignToAgent(int $conversationId, int $agentId, bool $forceAssign = false): array
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

        // Verificar limites APENAS se não for atribuição forçada (manual)
        if (!$forceAssign) {
            if (!\App\Services\ConversationSettingsService::canAssignToAgent(
                $agentId,
                $conversation['department_id'] ?? null,
                $conversation['funnel_id'] ?? null,
                $conversation['funnel_stage_id'] ?? null
            )) {
                throw new \Exception('Agente atingiu o limite máximo de conversas ou não está disponível');
            }
        } else {
            Logger::debug("Atribuição FORÇADA (manual) - ignorando limites e status de disponibilidade", 'conversas.log');
        }

        // Atribuir
        $oldAgentId = $conversation['agent_id'] ?? null;
        Conversation::update($conversationId, ['agent_id' => $agentId]);
        
        // Definir agente do contato APENAS na PRIMEIRA atribuição
        // Agentes adicionais só podem ser adicionados manualmente via modal de gerenciamento
        try {
            // Verificar se o contato já tem algum agente na lista
            $existingAgents = \App\Models\ContactAgent::getByContact($conversation['contact_id']);
            
            // Se o contato NÃO tem nenhum agente ainda, este é o primeiro - adicionar como principal
            if (empty($existingAgents)) {
                \App\Models\ContactAgent::addAgent($conversation['contact_id'], $agentId, true, 0);
                error_log("ConversationService: Agente {$agentId} definido como agente principal do contato {$conversation['contact_id']} (primeira atribuição)");
            }
            // Se já tem agentes, NÃO adicionar automaticamente
            // Novos agentes só podem ser adicionados via modal de gerenciamento (ContactAgentController)
        } catch (\Exception $e) {
            error_log("Erro ao atualizar agente do contato: " . $e->getMessage());
            error_log("Trace: " . $e->getTraceAsString());
        }
        
        // Invalidar cache de conversas
        self::invalidateCache($conversationId);
        
        // Atualizar contagem de conversas dos agentes APENAS se o agente mudou
        if ($oldAgentId != $agentId) {
            // Decrementar contador do agente anterior (se houver)
            if ($oldAgentId) {
            User::updateConversationsCount($oldAgentId);
        }
            // Incrementar contador do novo agente
        User::updateConversationsCount($agentId);
            
            Logger::debug("Contadores atualizados: antigo agente {$oldAgentId} → novo agente {$agentId}", 'conversas.log');
        } else {
            Logger::debug("Agente não mudou (já era {$agentId}), contadores não foram alterados", 'conversas.log');
        }
        
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
     * Atualizar setor da conversa
     */
    public static function updateDepartment(int $conversationId, ?int $departmentId): array
    {
        // Obter conversa para verificar contexto
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        // Verificar se setor existe (se fornecido)
        if ($departmentId !== null) {
            $department = \App\Models\Department::find($departmentId);
            if (!$department) {
                throw new \Exception('Setor não encontrado');
            }
        }

        // Atualizar setor
        $oldDepartmentId = $conversation['department_id'] ?? null;
        Conversation::update($conversationId, ['department_id' => $departmentId]);
        
        // Invalidar cache de conversas
        self::invalidateCache($conversationId);
        
        // Obter conversa atualizada para notificar via WebSocket
        $conversation = Conversation::findWithRelations($conversationId);
        
        // Criar mensagem de sistema informando mudança de setor
        try {
            $oldDepartmentName = $oldDepartmentId ? (\App\Models\Department::find($oldDepartmentId)['name'] ?? 'Sem setor') : 'Sem setor';
            $newDepartmentName = $departmentId ? (\App\Models\Department::find($departmentId)['name'] ?? 'Sem setor') : 'Sem setor';
            
            self::sendMessage(
                $conversationId,
                "🔄 Setor alterado de '{$oldDepartmentName}' para '{$newDepartmentName}'.",
                'system',
                null,
                [],
                'system'
            );
        } catch (\Exception $e) {
            error_log("Erro ao criar mensagem de sistema: " . $e->getMessage());
        }
        
        try {
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Executar automações para atualização
        try {
            \App\Services\AutomationService::executeForConversationUpdated($conversationId, ['department_id' => $departmentId]);
        } catch (\Exception $e) {
            error_log("Erro ao executar automações: " . $e->getMessage());
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

        // Verificar se é primeira atribuição (antes estava com IA, não tinha agente humano)
        $oldAgentId = $conversation['agent_id'] ?? null;
        
        // Atribuir conversa ao agente humano
        Conversation::update($conversationId, ['agent_id' => $agentId]);
        
        // Definir agente do contato APENAS se for a primeira atribuição (contato não tem nenhum agente ainda)
        try {
            $existingAgents = \App\Models\ContactAgent::getByContact($conversation['contact_id']);
            
            // Se o contato NÃO tem nenhum agente ainda, este é o primeiro - adicionar como principal
            if (empty($existingAgents)) {
                \App\Models\ContactAgent::addAgent($conversation['contact_id'], $agentId, true, 0);
                error_log("ConversationService (escalação IA): Agente {$agentId} definido como agente principal do contato {$conversation['contact_id']} (primeira atribuição)");
            }
            // Se já tem agentes, NÃO adicionar automaticamente
        } catch (\Exception $e) {
            error_log("Erro ao atualizar agente principal na escalação de IA: " . $e->getMessage());
        }
        
        // Invalidar cache
        self::invalidateCache($conversationId);
        
        // Atualizar contagem de conversas APENAS se o agente mudou
        if ($oldAgentId != $agentId) {
            if ($oldAgentId) {
                User::updateConversationsCount($oldAgentId);
            }
        User::updateConversationsCount($agentId);
            Logger::debug("Escalação: contadores atualizados (antigo: {$oldAgentId} → novo: {$agentId})", 'conversas.log');
        }
        
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
        
        // ✅ MOVER PARA ETAPA "Fechadas / Resolvidas" do funil correspondente
        $updateData = [
            'status' => 'closed',
            'resolved_at' => date('Y-m-d H:i:s')
        ];
        
        // Se conversa tem funil, mover para etapa "Fechadas / Resolvidas"
        if (!empty($conversation['funnel_id'])) {
            $fechadasStage = \App\Models\FunnelStage::getSystemStage(
                $conversation['funnel_id'], 
                'fechadas'
            );
            
            if ($fechadasStage) {
                $updateData['funnel_stage_id'] = $fechadasStage['id'];
                error_log("ConversationService::close - Movendo conversa {$conversationId} para etapa 'Fechadas / Resolvidas' (ID: {$fechadasStage['id']})");
            }
        }
        
        if (Conversation::update($conversationId, $updateData)) {
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
     * Marcar conversa como SPAM
     */
    public static function markAsSpam(int $conversationId, ?int $userId = null): array
    {
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        if ($userId === null) {
            $userId = \App\Helpers\Auth::id();
        }
        
        // Marcar como spam
        Conversation::update($conversationId, [
            'is_spam' => 1,
            'spam_marked_at' => date('Y-m-d H:i:s'),
            'spam_marked_by' => $userId,
            'status' => 'closed' // Fechar automaticamente quando marcada como spam
        ]);
        
        // Invalidar cache de conversas
        self::invalidateCache($conversationId);
        
        // Obter conversa atualizada para notificar via WebSocket
        $conversation = Conversation::findWithRelations($conversationId);
        
        // Notificar via WebSocket
        try {
            \App\Helpers\WebSocket::notifyConversationUpdated($conversationId, $conversation);
        } catch (\Exception $e) {
            error_log("Erro ao notificar WebSocket: " . $e->getMessage());
        }
        
        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logConversationSpamMarked($conversationId, $userId);
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }
        
        return $conversation;
    }

    /**
     * Reabrir conversa
     */
    public static function reopen(int $conversationId): array
    {
        // Obter conversa antes de reabrir para atualizar contagem do agente
        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            throw new \Exception('Conversa não encontrada');
        }
        
        $oldAgentId = $conversation['agent_id'] ?? null;
        
        // Verificar se deve atribuir automaticamente ao agente do contato
        $shouldAssignToContactAgent = false;
        $contactAgentId = null;
        
        try {
            $contactAgentId = \App\Services\ContactAgentService::shouldAutoAssignOnConversation(
                $conversation['contact_id'],
                $conversationId
            );
            
            if ($contactAgentId) {
                $shouldAssignToContactAgent = true;
            }
        } catch (\Exception $e) {
            error_log("Erro ao verificar agente do contato ao reabrir: " . $e->getMessage());
        }
        
        // Preparar dados de atualização
        $updateData = [
            'status' => 'open',
            'resolved_at' => null
        ];
        
        // Se deve atribuir ao agente do contato, atualizar agent_id
        if ($shouldAssignToContactAgent && $contactAgentId) {
            $updateData['agent_id'] = $contactAgentId;
            Logger::debug("Conversa reaberta atribuída automaticamente ao agente do contato: {$contactAgentId}", 'conversas.log');
        }
        
        if (Conversation::update($conversationId, $updateData)) {
            // Invalidar cache de conversas
            self::invalidateCache($conversationId);
            
            // Atualizar contagem de conversas do agente APENAS se mudou
            $finalAgentId = $shouldAssignToContactAgent && $contactAgentId ? $contactAgentId : $oldAgentId;
            if ($finalAgentId && $finalAgentId != $oldAgentId) {
                // Se mudou de agente, atualizar contagem de ambos
                if ($oldAgentId) {
                    User::updateConversationsCount($oldAgentId);
                }
                User::updateConversationsCount($finalAgentId);
                Logger::debug("Reabertura: contadores atualizados (antigo: {$oldAgentId} → novo: {$finalAgentId})", 'conversas.log');
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
    public static function sendMessage(int $conversationId, string $content, string $senderType = 'agent', ?int $senderId = null, array $attachments = [], ?string $messageType = null, ?int $quotedMessageId = null, ?int $aiAgentId = null, ?int $messageTimestamp = null): ?int
    {
        \App\Helpers\Logger::info("═══ ConversationService::sendMessage INÍCIO ═══ conv={$conversationId}, type={$senderType}, sender={$senderId}, aiAgent={$aiAgentId}, contentLen=" . strlen($content) . ", attachments=" . count($attachments));
        
        // Debug log
        \App\Helpers\ConversationDebug::messageReceived($conversationId, $content, $senderType, [
            'senderId' => $senderId,
            'aiAgentId' => $aiAgentId,
            'attachments_count' => count($attachments),
            'messageType' => $messageType
        ]);
        
        if ($senderId === null && $aiAgentId === null) {
            $senderId = \App\Helpers\Auth::id();
        }

        // Validar que há conteúdo ou anexos
        if (empty(trim($content ?? '')) && empty($attachments)) {
            \App\Helpers\Logger::error("ConversationService::sendMessage - ERRO: Mensagem vazia sem anexos (conv={$conversationId})");
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
        
        // Se foi fornecido timestamp customizado (ex: do WhatsApp), usar ele para created_at
        if ($messageTimestamp !== null) {
            $messageData['created_at'] = date('Y-m-d H:i:s', $messageTimestamp);
        }
        
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

        \App\Helpers\Logger::info("ConversationService::sendMessage - Criando mensagem no banco (keys: " . implode(', ', array_keys($messageData)) . ")");
        
        $messageId = Message::createMessage($messageData);
        
        \App\Helpers\Logger::info("ConversationService::sendMessage - Mensagem criada no banco: messageId={$messageId}");

        // ✅ NOVO: Transcrever áudio automaticamente se for mensagem do contato com áudio
        if ($senderType === 'contact' && $messageType === 'audio' && !empty($attachmentsData)) {
            try {
                $transcriptionSettings = \App\Services\TranscriptionService::getSettings();
                
                // Verificar se transcrição está habilitada
                if (!empty($transcriptionSettings['enabled']) && !empty($transcriptionSettings['auto_transcribe'])) {
                    // Verificar se deve transcrever apenas para agentes de IA
                    $shouldTranscribe = true;
                    if (!empty($transcriptionSettings['only_for_ai_agents'])) {
                        $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
                        $shouldTranscribe = ($aiConversation && $aiConversation['status'] === 'active');
                    }
                    
                    if ($shouldTranscribe) {
                        \App\Helpers\Logger::info("ConversationService::sendMessage - Iniciando transcrição automática (msg={$messageId}, conv={$conversationId})");
                        
                        // Pegar primeiro anexo de áudio
                        $audioAttachment = null;
                        foreach ($attachmentsData as $att) {
                            if (($att['type'] ?? '') === 'audio') {
                                $audioAttachment = $att;
                                break;
                            }
                        }
                        
                        if ($audioAttachment && !empty($audioAttachment['path'])) {
                            // Construir caminho completo do arquivo
                            $audioFilePath = __DIR__ . '/../../public' . $audioAttachment['path'];
                            
                            // Verificar se arquivo existe
                            if (file_exists($audioFilePath)) {
                                // Transcrever
                                $transcriptionResult = \App\Services\TranscriptionService::transcribe($audioFilePath, [
                                    'language' => $transcriptionSettings['language'] ?? 'pt',
                                    'model' => $transcriptionSettings['model'] ?? 'whisper-1'
                                ]);
                                
                                if ($transcriptionResult['success'] && !empty($transcriptionResult['text'])) {
                                    // Atualizar conteúdo da mensagem com texto transcrito
                                    if (!empty($transcriptionSettings['update_message_content'])) {
                                        Message::update($messageId, [
                                            'content' => $transcriptionResult['text']
                                        ]);
                                        
                                        \App\Helpers\Logger::info("ConversationService::sendMessage - ✅ Transcrição concluída (msg={$messageId}, len=" . strlen($transcriptionResult['text']) . ", cost=$" . $transcriptionResult['cost'] . ")");
                                        
                                        // Atualizar variável $content para usar no processamento com IA abaixo
                                        $content = $transcriptionResult['text'];
                                    } else {
                                        // Salvar transcrição em metadata ou campo separado
                                        $metadata = json_decode(Message::find($messageId)['metadata'] ?? '{}', true);
                                        $metadata['transcription'] = [
                                            'text' => $transcriptionResult['text'],
                                            'cost' => $transcriptionResult['cost'],
                                            'duration' => $transcriptionResult['duration'] ?? null,
                                            'created_at' => date('Y-m-d H:i:s')
                                        ];
                                        Message::update($messageId, [
                                            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE)
                                        ]);
                                        
                                        // Usar texto transcrito para processamento com IA mesmo se não atualizar content
                                        $content = $transcriptionResult['text'];
                                        
                                        \App\Helpers\Logger::info("ConversationService::sendMessage - ✅ Transcrição salva em metadata (msg={$messageId})");
                                    }
                                } else {
                                    \App\Helpers\Logger::error("ConversationService::sendMessage - ❌ Falha na transcrição: " . ($transcriptionResult['error'] ?? 'Erro desconhecido'));
                                }
                            } else {
                                \App\Helpers\Logger::error("ConversationService::sendMessage - Arquivo de áudio não encontrado: {$audioFilePath}");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("ConversationService::sendMessage - Erro ao transcrever áudio: " . $e->getMessage());
                // Não bloquear criação da mensagem se transcrição falhar
            }
        }

        // **ENVIAR PARA INTEGRAÇÃO** se a mensagem for do agente
        $integrationAccountId = $conversation['integration_account_id'] ?? null;
        $whatsappAccountId = $conversation['whatsapp_account_id'] ?? null; // Legacy
        
        \App\Helpers\Logger::info("ConversationService::sendMessage - Verificando envio (type={$senderType}, channel={$conversation['channel']}, integration_id=" . ($integrationAccountId ?? 'NULL') . ", wa_id=" . ($whatsappAccountId ?? 'NULL') . ")");
        
        if ($senderType === 'agent' && ($integrationAccountId || ($conversation['channel'] === 'whatsapp' && $whatsappAccountId))) {
            \App\Helpers\Logger::info("ConversationService::sendMessage - Condições para WhatsApp atendidas, processando envio");
            try {
                // Obter contato para pegar o telefone
                $contact = \App\Models\Contact::find($conversation['contact_id']);
                if ($contact && !empty($contact['phone'])) {
                    \App\Helpers\Logger::info("ConversationService::sendMessage - Contato encontrado: phone={$contact['phone']}");
                    // Preparar opções para envio
                    $options = [];
                    
                    // Se houver reply, enviar referência usando external_id da mensagem citada
                    if ($quotedMessageId) {
                        $quoted = Message::find((int)$quotedMessageId);
                        if (!empty($quoted['external_id'])) {
                            $options['quoted_message_external_id'] = $quoted['external_id'];
                        } else {
                            Logger::quepasa("ConversationService::sendMessage - ⚠️ quoted sem external_id: quotedMessageId={$quotedMessageId}");
                        }
                    }
                    
                    // Se houver anexo (imagem, vídeo, áudio, documento), enviar via mídia
                    if (!empty($attachmentsData)) {
                        Logger::quepasa("ConversationService::sendMessage - Processando anexos para envio WhatsApp");
                        Logger::quepasa("ConversationService::sendMessage - Total de anexos: " . count($attachmentsData));
                        
                        $firstAttachment = $attachmentsData[0];
                        
                        Logger::quepasa("ConversationService::sendMessage - Primeiro anexo:");
                        Logger::quepasa("ConversationService::sendMessage -   path: " . ($firstAttachment['path'] ?? 'NULL'));
                        Logger::quepasa("ConversationService::sendMessage -   type: " . ($firstAttachment['type'] ?? 'NULL'));
                        Logger::quepasa("ConversationService::sendMessage -   mime_type: " . ($firstAttachment['mime_type'] ?? 'NULL'));
                        Logger::quepasa("ConversationService::sendMessage -   filename: " . ($firstAttachment['filename'] ?? 'NULL'));
                        Logger::quepasa("ConversationService::sendMessage -   extension: " . ($firstAttachment['extension'] ?? 'NULL'));
                        
                        // Construir URL ABSOLUTA do anexo
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
                        $baseUrl = $protocol . '://' . $host;
                        
                        // Garantir que o path comece com /
                        $attachmentPath = '/' . ltrim($firstAttachment['path'], '/');
                        $attachmentUrl = $baseUrl . $attachmentPath;
                        
                        // LOG: Debug da URL gerada
                        error_log("DEBUG WhatsApp - URL do anexo gerada: " . $attachmentUrl);
                        error_log("DEBUG WhatsApp - Path do anexo: " . $firstAttachment['path']);
                        error_log("DEBUG WhatsApp - Tipo: " . ($firstAttachment['type'] ?? 'document'));
                        
                        // Verificar se arquivo existe fisicamente
                        $filePath = $_SERVER['DOCUMENT_ROOT'] . $attachmentPath;
                        if (!file_exists($filePath)) {
                            error_log("ERRO WhatsApp - Arquivo NÃO existe: " . $filePath);
                        } else {
                            error_log("DEBUG WhatsApp - Arquivo existe: " . $filePath . " (" . filesize($filePath) . " bytes)");
                            
                            // Testar se a URL está acessível publicamente
                            $ch = curl_init($attachmentUrl);
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_NOBODY => true,
                                CURLOPT_TIMEOUT => 5,
                                CURLOPT_SSL_VERIFYPEER => false
                            ]);
                            curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            if ($httpCode === 200) {
                                error_log("DEBUG WhatsApp - URL acessível publicamente (HTTP 200)");
                            } else {
                                error_log("ERRO WhatsApp - URL NÃO acessível publicamente (HTTP {$httpCode})");
                                error_log("ERRO WhatsApp - Quepasa não conseguirá baixar este arquivo!");
                            }
                        }
                        
                        $options['media_url'] = $attachmentUrl;
                        $options['media_type'] = $firstAttachment['type'] ?? 'document';
                        if (!empty($firstAttachment['mime_type'])) {
                            $options['media_mime'] = $firstAttachment['mime_type'];
                        }
                        // Nome do arquivo para fallback de caption
                        if (!empty($firstAttachment['filename'])) {
                            $options['media_name'] = $firstAttachment['filename'];
                        } else {
                            $options['media_name'] = basename($firstAttachment['path']);
                        }
                        
                        Logger::quepasa("ConversationService::sendMessage - Opções preparadas para WhatsAppService:");
                        Logger::quepasa("ConversationService::sendMessage -   media_url: {$options['media_url']}");
                        Logger::quepasa("ConversationService::sendMessage -   media_type: {$options['media_type']}");
                        Logger::quepasa("ConversationService::sendMessage -   media_mime: " . ($options['media_mime'] ?? 'NULL'));
                        Logger::quepasa("ConversationService::sendMessage -   media_name: " . ($options['media_name'] ?? 'NULL'));
                        
                        // Verificar se é áudio e se mime_type está correto
                        if ($options['media_type'] === 'audio') {
                            Logger::quepasa("ConversationService::sendMessage - ⚠️ É ÁUDIO! Verificando mime_type...");
                            if (empty($options['media_mime']) || !str_contains($options['media_mime'], 'ogg')) {
                                Logger::quepasa("ConversationService::sendMessage - ⚠️ ATENÇÃO: mime_type não é OGG! mime_type=" . ($options['media_mime'] ?? 'NULL'));
                                Logger::quepasa("ConversationService::sendMessage - ⚠️ Verificar se conversão foi executada corretamente!");
                            } else {
                                Logger::quepasa("ConversationService::sendMessage - ✅ mime_type está correto (OGG): {$options['media_mime']}");
                            }
                        }
                        
                        // Para Quepasa, se for imagem/vídeo/áudio e houver legenda, usar content como caption
                        if (!empty($content)) {
                            $options['caption'] = $content;
                        }
                    }
                    
                    // Enviar mensagem via integração
                    $sendResult = null;
                    
                    if ($integrationAccountId) {
                        // Usar IntegrationService para nova estrutura
                        \App\Helpers\Logger::info("ConversationService::sendMessage - Chamando IntegrationService::sendMessage (integration_id={$integrationAccountId}, phone={$contact['phone']}, contentLen=" . strlen($content) . ")");
                        
                        try {
                            $sendResult = \App\Services\IntegrationService::sendMessage(
                                $integrationAccountId,
                                $contact['phone'] ?? $contact['identifier'] ?? '',
                                $content,
                                $options
                            );
                        } catch (\Exception $e) {
                            \App\Helpers\Logger::error("ConversationService::sendMessage - Erro IntegrationService: " . $e->getMessage());
                            throw $e;
                        }
                    } elseif ($whatsappAccountId && $conversation['channel'] === 'whatsapp') {
                        // Legacy: usar WhatsAppService diretamente
                        \App\Helpers\Logger::info("ConversationService::sendMessage - Chamando WhatsAppService::sendMessage (wa_id={$whatsappAccountId}, phone={$contact['phone']}, contentLen=" . strlen($content) . ")");
                        
                        $sendResult = \App\Services\WhatsAppService::sendMessage(
                            $whatsappAccountId,
                            $contact['phone'],
                            $content,
                            $options
                        );
                    }
                    
                    \App\Helpers\Logger::info("ConversationService::sendMessage - Integração respondeu: success=" . ($sendResult['success'] ?? false) . ", msg_id=" . ($sendResult['message_id'] ?? 'NULL'));
                    
                    // Atualizar status e external_id da mensagem
                    if ($sendResult && ($sendResult['success'] ?? false)) {
                        Message::update($messageId, [
                            'external_id' => $sendResult['message_id'] ?? null,
                            'status' => 'sent'
                        ]);
                    }
                } else {
                    \App\Helpers\Logger::warning("ConversationService::sendMessage - Contato não encontrado ou sem telefone");
                }
            } catch (\Exception $e) {
                \App\Helpers\Logger::error("ConversationService::sendMessage - ERRO ao enviar WhatsApp: " . $e->getMessage());
                error_log("Erro ao enviar mensagem para WhatsApp: " . $e->getMessage());
                // Marcar mensagem como erro
                Message::update($messageId, [
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }
        } else {
            \App\Helpers\Logger::info("ConversationService::sendMessage - Não envia WhatsApp (condições não atendidas)");
        }

        // Log de atividade
        try {
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::logMessageSent($messageId, $conversationId, $senderType, $senderId);
            }
        } catch (\Exception $e) {
            error_log("Erro ao logar atividade: " . $e->getMessage());
        }

        // Se é primeira mensagem do agente, atualizar first_response_at
        if ($senderType === 'agent' || $senderType === 'ai_agent') {
            $conv = Conversation::find($conversationId);
            if ($conv && empty($conv['first_response_at'])) {
                Conversation::update($conversationId, [
                    'first_response_at' => date('Y-m-d H:i:s')
                ]);
            }
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
            // Adicionar campos necessários para o frontend (type e direction)
            $message['type'] = ($message['message_type'] ?? 'text') === 'note' ? 'note' : 'message';
            
            // Determinar direction baseado em sender_type
            // Mensagens de agentes são sempre outgoing (enviadas pelo sistema/agente)
            // Mensagens de contatos são sempre incoming (recebidas)
            if (($message['sender_type'] ?? '') === 'agent') {
                $message['direction'] = 'outgoing';
            } else {
                $message['direction'] = 'incoming';
            }
            
            // Log detalhado para debug (usar info ao invés de debug para sempre logar)
            Logger::info("🔍 Mensagem preparada para WebSocket: messageId={$messageId}, sender_type={$message['sender_type']}, direction={$message['direction']}, type={$message['type']}", 'conversas.log');
            
            // Notificar via WebSocket
            try {
                Logger::info("📤 Notificando nova mensagem via WebSocket: conversationId={$conversationId}, messageId={$messageId}, direction={$message['direction']}", 'conversas.log');
                \App\Helpers\WebSocket::notifyNewMessage($conversationId, $message);
            } catch (\Exception $e) {
                Logger::error("❌ Erro ao notificar WebSocket: " . $e->getMessage(), 'conversas.log');
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
        // Se mensagem é do contato e conversa está atribuída a agente de IA, processar automaticamente
        if ($senderType === 'contact') {
            // ✅ NOVO: Se mensagem tem áudio e transcrição está habilitada, usar texto transcrito
            $processedContent = $content;
            if ($messageType === 'audio' && !empty($attachmentsData)) {
                try {
                    $transcriptionSettings = \App\Services\TranscriptionService::getSettings();
                    if (!empty($transcriptionSettings['enabled']) && !empty($transcriptionSettings['auto_transcribe'])) {
                        // Buscar transcrição na mensagem recém-criada (se foi atualizada)
                        $message = Message::find($messageId);
                        if ($message && !empty(trim($message['content'] ?? '')) && $message['content'] !== $content) {
                            // Mensagem foi atualizada com transcrição
                            $processedContent = $message['content'];
                            \App\Helpers\Logger::info("ConversationService::sendMessage - Usando texto transcrito para IA (msg={$messageId}, original=" . strlen($content) . ", transcribed=" . strlen($processedContent) . ")");
                        }
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("ConversationService::sendMessage - Erro ao obter transcrição: " . $e->getMessage());
                }
            }
            
            $aiConversation = \App\Models\AIConversation::getByConversationId($conversationId);
            \App\Helpers\Logger::info("ConversationService::sendMessage - Verificando AIConversation: conv={$conversationId}, aiConversation=" . ($aiConversation ? 'EXISTS' : 'NULL') . ", status=" . ($aiConversation['status'] ?? 'N/A'));
            
            if ($aiConversation && $aiConversation['status'] === 'active') {
                \App\Helpers\Logger::info("ConversationService::sendMessage - ✅ AIConversation ativa encontrada! Processando com IA (agentId=" . ($aiConversation['ai_agent_id'] ?? 'NULL') . ")");
                try {
                    // ✅ NOVO: Verificar intent na mensagem do CLIENTE antes de chamar IA
                    $conversation = \App\Models\Conversation::find($conversationId);
                    $metadata = json_decode($conversation['metadata'] ?? '{}', true);
                    
                    $intentDetected = false;
                    if (!empty($metadata['ai_branching_active'])) {
                        \App\Helpers\Logger::automation("🔍 AI Branching ativo - Verificando intent na mensagem do CLIENTE antes de processar com IA...");
                        
                        // ✅ CORRIGIDO: Usar nova função que detecta intent DIRETO na mensagem do cliente
                        $intentDetected = \App\Services\AutomationService::detectIntentInClientMessage(
                            $conversation, 
                            $processedContent
                        );
                        
                        if ($intentDetected) {
                            \App\Helpers\Logger::automation("✅ Intent detectado na mensagem do CLIENTE! Fluxo roteado SEM chamar IA.");
                            // Intent foi detectado, mensagem de saída já foi enviada, fluxo foi roteado
                            // NÃO processar com IA
                            return $messageId;
                        } else {
                            \App\Helpers\Logger::automation("⚠️ Nenhum intent detectado. Processando normalmente com IA...");
                        }
                    }
                    
                    // Se não detectou intent, processar com IA normalmente
                    if (!$intentDetected) {
                        \App\Helpers\Logger::info("ConversationService::sendMessage - Adicionando mensagem ao buffer (conv={$conversationId}, agent={$aiConversation['ai_agent_id']}, msgLen=" . strlen($processedContent) . ")");
                        
                        // ✅ NOVO: Usar buffer de mensagens com timer de contexto
                        \App\Services\AIAgentService::bufferMessage(
                            $conversationId,
                            $aiConversation['ai_agent_id'],
                            $processedContent // Usar conteúdo processado (transcrito se disponível)
                        );
                        
                        \App\Helpers\Logger::info("ConversationService::sendMessage - ✅ Mensagem adicionada ao buffer com sucesso");
                        // A resposta será enviada após o timer de contexto expirar
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::error("ConversationService::sendMessage - ❌ ERRO ao processar mensagem com agente de IA: " . $e->getMessage());
                    \App\Helpers\Logger::error("ConversationService::sendMessage - ❌ Stack trace: " . $e->getTraceAsString());
                    error_log("Erro ao processar mensagem com agente de IA: " . $e->getMessage());
                    // Continuar normalmente mesmo se falhar
                }
            }
            
            // Análise de sentimento automática (se habilitada)
            try {
                $settings = \App\Services\ConversationSettingsService::getSettings();
                $sentimentSettings = $settings['sentiment_analysis'] ?? [];
                
                if (!empty($sentimentSettings['enabled']) && !empty($sentimentSettings['analyze_on_new_message'])) {
                    // Contar mensagens do contato
                    $messageCount = \App\Models\Message::where('conversation_id', '=', $conversationId)
                        ->where('sender_type', '=', 'contact')
                        ->count();
                    
                    $minMessages = (int)($sentimentSettings['min_messages_to_analyze'] ?? 3);
                    $analyzeOnCount = (int)($sentimentSettings['analyze_on_message_count'] ?? 5);
                    
                    // Analisar se atingiu mínimo e está no intervalo configurado
                    if ($messageCount >= $minMessages && ($messageCount % $analyzeOnCount === 0)) {
                        // Analisar em background (não bloquear resposta)
                        try {
                            \App\Services\SentimentAnalysisService::analyzeConversation($conversationId, $messageId);
                        } catch (\Exception $e) {
                            Logger::error("Erro ao analisar sentimento: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Não bloquear se análise falhar
                Logger::error("Erro ao verificar análise de sentimento: " . $e->getMessage());
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

        \App\Helpers\Logger::info("═══ ConversationService::sendMessage FIM ═══ messageId={$messageId}, conv={$conversationId}");

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
            // Adicionar campos necessários para o frontend (type e direction)
            $message['type'] = ($message['message_type'] ?? 'text') === 'note' ? 'note' : 'message';
            
            // Determinar direction baseado em sender_type
            // Mensagens de agentes são sempre outgoing (enviadas pelo sistema/agente)
            // Mensagens de contatos são sempre incoming (recebidas)
            if (($message['sender_type'] ?? '') === 'agent') {
                $message['direction'] = 'outgoing';
            } else {
                $message['direction'] = 'incoming';
            }
            
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

