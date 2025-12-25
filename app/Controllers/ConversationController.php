<?php
/**
 * Controller de Conversas
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Services\ConversationService;
use App\Services\Api4ComService;
use App\Models\User;
use App\Models\Conversation;

class ConversationController
{
    /**
     * Preparar resposta JSON (desabilita display_errors e limpa buffer)
     * Retorna array com configurações antigas para restaurar no finally
     */
    private function prepareJsonResponse(): array
    {
        // Desabilitar display de erros para evitar HTML no JSON
        $oldDisplayErrors = ini_get('display_errors');
        $oldErrorReporting = error_reporting();
        ini_set('display_errors', '0');
        error_reporting(0);
        
        // Limpar qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Iniciar novo buffer
        ob_start();
        
        return [
            'display_errors' => $oldDisplayErrors,
            'error_reporting' => $oldErrorReporting
        ];
    }
    
    /**
     * Restaurar configurações após resposta JSON
     */
    private function restoreAfterJsonResponse(array $config): void
    {
        ini_set('display_errors', $config['display_errors']);
        error_reporting($config['error_reporting']);
    }
    
    /**
     * Listar conversas
     */
    public function index(): void
    {
        $userId = \App\Helpers\Auth::id();
        
        // Debug log
        \App\Helpers\Log::debug("ConversationController::index - userId: {$userId}", 'conversas.log');
        \App\Helpers\Log::context("GET params", $_GET, 'conversas.log', 'DEBUG');
        
        // Obter filtros da requisição
        $filters = [
            'status' => $_GET['status'] ?? 'open', // Padrão: Abertas
            'channel' => $_GET['channel'] ?? null,
            'channels' => isset($_GET['channels']) && is_array($_GET['channels']) ? $_GET['channels'] : (!empty($_GET['channel']) ? [$_GET['channel']] : null),
            'search' => $_GET['search'] ?? null,
            'agent_id' => isset($_GET['agent_id']) ? ($_GET['agent_id'] === '0' || $_GET['agent_id'] === 0 ? '0' : $_GET['agent_id']) : null,
            'agent_ids' => isset($_GET['agent_ids']) && is_array($_GET['agent_ids']) ? $_GET['agent_ids'] : (!empty($_GET['agent_id']) && $_GET['agent_id'] !== '0' ? [$_GET['agent_id']] : null),
            'department_id' => $_GET['department_id'] ?? null,
            'tag_id' => $_GET['tag_id'] ?? null,
            'tag_ids' => isset($_GET['tag_ids']) && is_array($_GET['tag_ids']) ? array_map('intval', $_GET['tag_ids']) : (!empty($_GET['tag_id']) ? [(int)$_GET['tag_id']] : null),
            'whatsapp_account_id' => $_GET['whatsapp_account_id'] ?? null,
            'whatsapp_account_ids' => isset($_GET['whatsapp_account_ids']) && is_array($_GET['whatsapp_account_ids']) ? array_map('intval', $_GET['whatsapp_account_ids']) : (!empty($_GET['whatsapp_account_id']) ? [(int)$_GET['whatsapp_account_id']] : null),
            'funnel_id' => !empty($_GET['funnel_id']) ? (int) $_GET['funnel_id'] : null,
            'funnel_ids' => isset($_GET['funnel_ids']) && is_array($_GET['funnel_ids']) ? array_map('intval', $_GET['funnel_ids']) : (!empty($_GET['funnel_id']) ? [(int)$_GET['funnel_id']] : null),
            'funnel_stage_id' => !empty($_GET['funnel_stage_id']) ? (int) $_GET['funnel_stage_id'] : null,
            'funnel_stage_ids' => isset($_GET['funnel_stage_ids']) && is_array($_GET['funnel_stage_ids']) ? array_map('intval', $_GET['funnel_stage_ids']) : (!empty($_GET['funnel_stage_id']) ? [(int)$_GET['funnel_stage_id']] : null),
            'unanswered' => isset($_GET['unanswered']) && $_GET['unanswered'] === '1' ? true : null,
            'answered' => isset($_GET['answered']) && $_GET['answered'] === '1' ? true : null,
            'is_spam' => isset($_GET['status']) && $_GET['status'] === 'spam' ? true : null, // Filtro de spam
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null,
            'pinned' => isset($_GET['pinned']) ? ($_GET['pinned'] === '1' ? true : false) : null,
            'order_by' => $_GET['order_by'] ?? null,
            'order_dir' => $_GET['order_dir'] ?? null,
            'limit' => $_GET['limit'] ?? 50,
            'offset' => $_GET['offset'] ?? 0
        ];

        // Remover filtros vazios (exceto pinned que pode ser false e arrays que podem estar vazios)
        $filters = array_filter($filters, function($value, $key) {
            if ($key === 'pinned') {
                return $value !== null; // Manter pinned mesmo se for false
            }
            if ($key === 'agent_id') {
                return $value !== null && $value !== ''; // Manter agent_id mesmo se for '0' (não atribuídas)
            }
            if ($key === 'search') {
                return $value !== null && trim($value) !== ''; // Manter busca mesmo se tiver espaços
            }
            // Manter arrays mesmo se vazios (serão processados depois)
            if (in_array($key, ['channels', 'tag_ids', 'whatsapp_account_ids', 'agent_ids']) && is_array($value)) {
                return true; // Manter arrays para processamento
            }
            return $value !== null && $value !== '';
        }, ARRAY_FILTER_USE_BOTH);
        
        // Log dos filtros após limpeza
        \App\Helpers\Log::context("Filtros após limpeza", $filters, 'conversas.log', 'DEBUG');

        try {
            // Verificar se é requisição JSON ANTES de processar dados
            $isAjax = \App\Helpers\Request::isAjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
            $isJsonFormat = isset($_GET['format']) && $_GET['format'] === 'json';
            
            // Se for requisição JSON, limpar qualquer output buffer antes
            if ($isAjax || $isJsonFormat) {
                // Limpar qualquer output buffer que possa ter sido iniciado
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
            
            $conversations = ConversationService::list($filters, $userId);
            
            \App\Helpers\Log::debug("Conversas retornadas do Service: " . count($conversations), 'conversas.log');
            
            
            // Se for requisição AJAX ou formato JSON, retornar apenas JSON com lista de conversas
            if ($isAjax || $isJsonFormat) {
                // Calcular ETag baseado em campos que alteram a lista (evita re-render se nada mudou)
                $signaturePayload = array_map(function($c) {
                    return [
                        $c['id'] ?? null,
                        $c['pinned'] ?? null,
                        $c['pinned_at'] ?? null,
                        $c['updated_at'] ?? null,
                        $c['status'] ?? null,
                        $c['unread_count'] ?? null,
                        isset($c['tags_data']) ? $c['tags_data'] : null,
                    ];
                }, $conversations);
                
                $etag = '"' . md5(json_encode($signaturePayload)) . '"';
                header('Cache-Control: no-cache, must-revalidate');
                header('ETag: ' . $etag);
                
                // Se o cliente já tem a mesma versão, responder 304
                $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
                if ($ifNoneMatch && trim($ifNoneMatch) === $etag) {
                    http_response_code(304);
                    exit;
                }
                
                Response::json([
                    'success' => true,
                    'conversations' => $conversations
                ]);
                return;
            }
            
            // Obter agentes, setores e tags para filtro
            $agents = User::getActiveAgents();
            $departments = \App\Models\Department::all();
            $tags = \App\Models\Tag::all();
            $whatsappAccounts = \App\Models\WhatsAppAccount::getActive();
            
            // Se houver ID de conversa na URL, carregar para exibir no chat
            $selectedConversationId = $_GET['id'] ?? null;
            $selectedConversation = null;
            $accessRestricted = false;
            $accessInfo = null;
            
            if ($selectedConversationId) {
                $userId = \App\Helpers\Auth::id();
                
                try {
                    // Carregar conversa
                    $selectedConversation = ConversationService::getConversation((int)$selectedConversationId);
                    
                    if ($selectedConversation && $userId) {
                        // 🔍 Verificar se usuário tem acesso à conversa
                        $accessInfo = \App\Services\ConversationMentionService::checkUserAccess((int)$selectedConversationId, $userId);
                        
                        \App\Helpers\Log::debug("🔍 [index] Verificando acesso via URL - conversationId={$selectedConversationId}, userId={$userId}", 'conversas.log');
                        \App\Helpers\Log::debug("🔍 [index] accessInfo=" . json_encode($accessInfo), 'conversas.log');
                        
                        if (!$accessInfo['can_view']) {
                            // Verificar se é admin/supervisor
                            $userLevel = \App\Models\User::getMaxLevel($userId);
                            $isAdminOrSupervisor = $userLevel <= 2;
                            
                            \App\Helpers\Log::debug("🔍 [index] Acesso negado - userLevel={$userLevel}, isAdminOrSupervisor=" . ($isAdminOrSupervisor ? 'true' : 'false'), 'conversas.log');
                            
                            if (!$isAdminOrSupervisor) {
                                // Usuário não tem acesso - marcar como restrito
                                $accessRestricted = true;
                                \App\Helpers\Log::debug("🔍 [index] ❌ Acesso restrito para usuário {$userId}", 'conversas.log');
                                
                                // Limpar mensagens da conversa para não expor
                                $msgCount = count($selectedConversation['messages'] ?? []);
                                \App\Helpers\Log::debug("🔍 [index] Mensagens antes de limpar: {$msgCount}", 'conversas.log');
                                $selectedConversation['messages'] = [];
                                \App\Helpers\Log::debug("🔍 [index] Mensagens após limpar: " . count($selectedConversation['messages']), 'conversas.log');
                            } else {
                                // Admin/Supervisor pode ver - marcar mensagens como lidas
                                try {
                                    \App\Models\Message::markAsRead((int)$selectedConversationId, $userId);
                                } catch (\Exception $e) {
                                    // Ignorar
                                }
                            }
                        } else {
                            // Usuário tem acesso - marcar mensagens como lidas
                            try {
                                \App\Models\Message::markAsRead((int)$selectedConversationId, $userId);
                            } catch (\Exception $e) {
                                error_log("Erro ao marcar mensagens como lidas na conversa {$selectedConversationId}: " . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar erro se conversa não encontrada
                    \App\Helpers\Log::error("Erro ao carregar conversa {$selectedConversationId}: " . $e->getMessage(), 'conversas.log');
                }
            }
            
            Response::view('conversations/index', [
                'conversations' => $conversations,
                'agents' => $agents,
                'departments' => $departments ?? [],
                'tags' => $tags ?? [],
                'whatsappAccounts' => $whatsappAccounts ?? [],
                'filters' => $filters,
                'selectedConversation' => $selectedConversation,
                'selectedConversationId' => $selectedConversationId,
                'accessRestricted' => $accessRestricted,
                'accessInfo' => $accessInfo
            ]);
        } catch (\Exception $e) {
            // Log do erro para debug
            \App\Helpers\Log::error("Erro no ConversationController::index: " . $e->getMessage(), 'conversas.log');
            \App\Helpers\Log::error("Stack trace: " . $e->getTraceAsString(), 'conversas.log');
            
            // Se for requisição JSON, retornar erro em JSON
            $isAjax = \App\Helpers\Request::isAjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
            $isJsonFormat = isset($_GET['format']) && $_GET['format'] === 'json';
            
            if ($isAjax || $isJsonFormat) {
                // Limpar qualquer output buffer
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao carregar conversas: ' . $e->getMessage(),
                    'conversations' => [],
                    'error' => ($_ENV['APP_DEBUG'] ?? 'true') === 'true' ? [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ] : null
                ], 500);
                return;
            }
            
            Response::view('conversations/index', [
                'conversations' => [],
                'agents' => [],
                'departments' => [],
                'tags' => [],
                'filters' => $filters,
                'selectedConversation' => null,
                'selectedConversationId' => null,
                'demoMode' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Deletar conversa
     */
    public function destroy(int $id): void
    {
        // Verificar permissão - admin global pode deletar qualquer conversa
        $user = \App\Helpers\Auth::user();
        if (!$user || ($user['role'] !== 'super_admin' && $user['role'] !== 'admin')) {
            Permission::abortIfCannot('conversations.delete');
        }
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Deletar mensagens relacionadas (cascade já faz isso, mas vamos garantir)
            \App\Helpers\Database::query("DELETE FROM messages WHERE conversation_id = ?", [$id]);
            
            // Deletar relacionamentos de tags
            \App\Helpers\Database::query("DELETE FROM conversation_tags WHERE conversation_id = ?", [$id]);
            
            // Deletar logs de automação relacionados
            try {
                \App\Helpers\Database::query("DELETE FROM automation_logs WHERE conversation_id = ?", [$id]);
            } catch (\Exception $e) {
                // Ignorar se tabela não existir
            }
            
            // Deletar conversas de IA relacionadas
            try {
                \App\Helpers\Database::query("DELETE FROM ai_conversations WHERE conversation_id = ?", [$id]);
            } catch (\Exception $e) {
                // Ignorar se tabela não existir
            }
            
            // Deletar conversa
            \App\Models\Conversation::delete($id);
            
            Response::json([
                'success' => true,
                'message' => 'Conversa deletada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deletar conversa: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mostrar conversa específica
     */
    public function show(int $id): void
    {
        try {
            $conversation = ConversationService::getConversation($id);
            
            if (!$conversation) {
                Response::notFound('Conversa não encontrada');
                return;
            }

            $userId = \App\Helpers\Auth::id();
            
            // 🔍 DEBUG: Log de verificação de acesso
            \App\Helpers\Log::debug("🔍 [show] Verificando acesso - conversationId={$id}, userId={$userId}", 'conversas.log');
            \App\Helpers\Log::debug("🔍 [show] Conversa agent_id=" . ($conversation['agent_id'] ?? 'NULL'), 'conversas.log');
            
            // Verificar tipo de acesso do usuário
            $accessInfo = \App\Services\ConversationMentionService::checkUserAccess($id, $userId);
            
            // 🔍 DEBUG: Log do resultado de checkUserAccess
            \App\Helpers\Log::debug("🔍 [show] accessInfo=" . json_encode($accessInfo), 'conversas.log');
            
            // Se for requisição AJAX, retornar JSON
            $isAjax = \App\Helpers\Request::isAjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
            \App\Helpers\Log::debug("🔍 [show] isAjax={$isAjax}, HTTP_X_REQUESTED_WITH=" . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NULL'), 'conversas.log');
            
            if ($isAjax) {
                // Limpar qualquer output buffer antes de retornar JSON
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Se não tem acesso direto (não é atribuído nem participante)
                if (!$accessInfo['can_view']) {
                    // Verificar se tem permissão geral de admin/supervisor
                    $userLevel = \App\Models\User::getMaxLevel($userId);
                    $isAdminOrSupervisor = $userLevel <= 2; // 0=SuperAdmin, 1=Admin, 2=Supervisor
                    
                    \App\Helpers\Log::debug("🔍 [show] Acesso negado - userLevel={$userLevel}, isAdminOrSupervisor={$isAdminOrSupervisor}", 'conversas.log');
                    
                    if ($isAdminOrSupervisor) {
                        // Admin/Supervisor pode ver normalmente
                        $accessInfo['can_view'] = true;
                        $accessInfo['is_admin'] = true;
                        \App\Helpers\Log::debug("🔍 [show] Admin/Supervisor - permitindo acesso", 'conversas.log');
                    } else {
                        \App\Helpers\Log::debug("🔍 [show] ❌ Retornando access_restricted=true", 'conversas.log');
                        // Retornar dados parciais para exibição ofuscada
                        Response::json([
                            'success' => true,
                            'access_restricted' => true,
                            'access_info' => [
                                'can_view' => false,
                                'is_participant' => $accessInfo['is_participant'],
                                'is_assigned' => $accessInfo['is_assigned'],
                                'has_pending_request' => $accessInfo['has_pending_request']
                            ],
                            'conversation' => [
                                'id' => $conversation['id'],
                                'contact_name' => $conversation['contact_name'] ?? 'Contato',
                                'contact_avatar' => $conversation['contact_avatar'] ?? null,
                                'channel' => $conversation['channel'] ?? 'whatsapp',
                                'status' => $conversation['status'] ?? 'open',
                                'agent_id' => $conversation['agent_id'] ?? null,
                                'agent_name' => $conversation['agent_name'] ?? null,
                                'created_at' => $conversation['created_at'] ?? null
                            ],
                            'messages' => [], // Não enviar mensagens
                            'tags' => []
                        ]);
                        return;
                    }
                } else {
                    \App\Helpers\Log::debug("🔍 [show] ✅ Acesso permitido - can_view=true", 'conversas.log');
                }
                
                // Marcar mensagens como lidas quando a conversa é aberta
                if ($userId) {
                    try {
                        $marked = \App\Models\Message::markAsRead($id, $userId);
                        error_log("Conversa {$id}: Marcadas mensagens como lidas. Resultado: " . ($marked ? 'sucesso' : 'nenhuma mensagem marcada'));
                    } catch (\Exception $e) {
                        error_log("Erro ao marcar mensagens como lidas na conversa {$id}: " . $e->getMessage());
                    }
                }
                
                // Obter tags da conversa
                try {
                    if (class_exists('\App\Models\Tag')) {
                        $tags = \App\Models\Tag::getByConversation($id);
                    } else {
                        $tags = [];
                    }
                } catch (\Exception $e) {
                    $tags = [];
                }
                
                // Se tiver parâmetro last_message_id, retornar apenas mensagens novas
                $lastMessageId = \App\Helpers\Request::get('last_message_id', 0);
                $messages = $conversation['messages'] ?? [];
                
                if ($lastMessageId > 0) {
                    // Filtrar apenas mensagens com ID maior que o último conhecido
                    $messages = array_filter($messages, function($msg) use ($lastMessageId) {
                        return isset($msg['id']) && $msg['id'] > $lastMessageId;
                    });
                    $messages = array_values($messages); // Reindexar array
                }
                
                // Recarregar conversa para obter unread_count atualizado
                $conversation = ConversationService::getConversation($id);
                
                // Log para debug
                error_log("Conversa {$id}: unread_count após marcar como lidas: " . ($conversation['unread_count'] ?? 'não definido'));
                
                // Notificar via WebSocket que a conversa foi atualizada (unread_count mudou)
                try {
                    \App\Helpers\WebSocket::notifyConversationUpdated($id, $conversation);
                } catch (\Exception $e) {
                    error_log("Erro ao notificar WebSocket: " . $e->getMessage());
                }
                
                // Obter solicitações pendentes para esta conversa (se for agente atribuído ou participante)
                $pendingRequests = [];
                if ($accessInfo['is_assigned'] || $accessInfo['is_participant']) {
                    $pendingRequests = \App\Services\ConversationMentionService::getPendingRequestsForConversation($id);
                }
                
                Response::json([
                    'success' => true,
                    'access_restricted' => false,
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'tags' => $tags,
                    'pending_requests' => $pendingRequests
                ]);
                return;
            }

            // Para requisições não-AJAX, verificar permissão tradicional
            if (!Permission::canViewConversation($conversation)) {
                Response::forbidden('Você não tem permissão para ver esta conversa.');
                return;
            }

            // Obter todas as tags disponíveis para gerenciamento
            $allTags = \App\Models\Tag::all();
            
            Response::view('conversations/show', [
                'conversation' => $conversation,
                'messages' => $conversation['messages'] ?? [],
                'allTags' => $allTags ?? []
            ]);
        } catch (\Exception $e) {
            // Log do erro
            \App\Helpers\Log::error("Erro em ConversationController::show({$id}): " . $e->getMessage(), 'conversas.log');
            \App\Helpers\Log::error("Stack trace: " . $e->getTraceAsString(), 'conversas.log');
            
            // Se for AJAX, retornar JSON de erro
            if (\App\Helpers\Request::isAjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao carregar conversa: ' . $e->getMessage()
                ], 500);
                return;
            }
            
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar nova conversa
     */
    public function store(): void
    {
        try {
            $data = [
                'contact_id' => $_POST['contact_id'] ?? null,
                'channel' => $_POST['channel'] ?? 'whatsapp',
                'subject' => $_POST['subject'] ?? null,
                'agent_id' => $_POST['agent_id'] ?? \App\Helpers\Auth::id()
            ];

            $conversation = ConversationService::create($data);
            
            Response::redirect('/conversations/' . $conversation['id']);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Criar nova conversa com contato e mensagem
     */
    public function newConversation(): void
    {
        Permission::abortIfCannot('conversations.create');
        
        try {
            // Aceitar tanto JSON quanto form-data (Request::post já trata JSON)
            $data = \App\Helpers\Request::post();
            
            $channel = trim($data['channel'] ?? 'whatsapp'); // Padrão: whatsapp
            $whatsappAccountId = !empty($data['whatsapp_account_id']) ? (int)$data['whatsapp_account_id'] : null;
            $name = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $message = trim($data['message'] ?? '');
            
            if (empty($channel) || empty($name) || empty($phone) || empty($message)) {
                Response::json(['success' => false, 'message' => 'Preencha todos os campos obrigatórios'], 400);
                return;
            }
            
            // Validar canal
            $validChannels = ['whatsapp', 'email', 'chat'];
            if (!in_array($channel, $validChannels)) {
                Response::json(['success' => false, 'message' => 'Canal inválido'], 400);
                return;
            }
            
            // Se canal for WhatsApp, validar integração
            if ($channel === 'whatsapp') {
                if (!$whatsappAccountId) {
                    Response::json(['success' => false, 'message' => 'Selecione uma integração WhatsApp'], 400);
                    return;
                }
                
                // Verificar se a conta WhatsApp existe e está ativa
                $whatsappAccount = \App\Models\WhatsAppAccount::find($whatsappAccountId);
                if (!$whatsappAccount || ($whatsappAccount['status'] ?? '') !== 'active') {
                    Response::json(['success' => false, 'message' => 'Integração WhatsApp inválida ou inativa'], 400);
                    return;
                }
            }
            
            // Normalizar telefone (remover +55 se presente, garantir formato correto)
            $phone = preg_replace('/^\+?55/', '', $phone); // Remove +55 do início
            $phone = preg_replace('/\D/', '', $phone); // Remove caracteres não numéricos
            
            if (strlen($phone) < 10 || strlen($phone) > 11) {
                Response::json(['success' => false, 'message' => 'Telefone inválido. Digite DDD + número'], 400);
                return;
            }
            
            // Formatar telefone completo com +55
            $fullPhone = '55' . $phone;
            
            // Criar ou encontrar contato
            $contactData = [
                'name' => $name,
                'phone' => $fullPhone
            ];
            
            // Adicionar whatsapp_id apenas se canal for WhatsApp
            if ($channel === 'whatsapp') {
                $contactData['whatsapp_id'] = $fullPhone . '@s.whatsapp.net';
            }
            
            $contact = \App\Services\ContactService::createOrUpdate($contactData);
            
            if (!$contact || !isset($contact['id'])) {
                Response::json(['success' => false, 'message' => 'Erro ao criar contato'], 500);
                return;
            }
            
            $currentUserId = \App\Helpers\Auth::id();
            
            // Verificar se já existe conversa com esse contato e canal
            $whatsappAccountIdForSearch = ($channel === 'whatsapp') ? $whatsappAccountId : null;
            $existingConversation = \App\Models\Conversation::findByContactAndChannel(
                $contact['id'], 
                $channel, 
                $whatsappAccountIdForSearch
            );
            
            // Se existe conversa, verificar se está atribuída a outro agente humano
            if ($existingConversation) {
                $existingAgentId = $existingConversation['agent_id'] ?? null;
                
                // Verificar se está atribuída a agente de IA (não considerar IA como agente)
                $isAIAssigned = false;
                try {
                    $aiConversation = \App\Models\AIConversation::getByConversationId($existingConversation['id']);
                    if ($aiConversation && $aiConversation['status'] === 'active') {
                        $isAIAssigned = true;
                    }
                } catch (\Exception $e) {
                    // Ignorar erro
                }
                
                // Se está atribuída a outro agente humano (não IA e não é o usuário atual)
                if ($existingAgentId && $existingAgentId != $currentUserId && !$isAIAssigned) {
                    $existingAgent = \App\Models\User::find($existingAgentId);
                    $existingAgentName = $existingAgent ? $existingAgent['name'] : 'Outro agente';
                    
                    // Apenas avisar, não criar
                    Response::json([
                        'success' => false,
                        'message' => "Já existe uma conversa com este contato atribuída ao agente: {$existingAgentName}",
                        'existing_agent' => $existingAgentName,
                        'existing_conversation_id' => $existingConversation['id']
                    ], 400);
                    return;
                }
                
                // Se está atribuída ao usuário atual ou é IA, usar a conversa existente
                $conversationId = $existingConversation['id'];
            } else {
                // Criar nova conversa
                $conversationData = [
                    'contact_id' => $contact['id'],
                    'channel' => $channel,
                    'agent_id' => $currentUserId
                ];
                
                // Adicionar whatsapp_account_id apenas se canal for WhatsApp
                if ($channel === 'whatsapp' && $whatsappAccountId) {
                    $conversationData['whatsapp_account_id'] = $whatsappAccountId;
                }
                
                $conversation = \App\Services\ConversationService::create($conversationData);
                
                $conversationId = $conversation['id'];
            }
            
            // Enviar mensagem
            $messageId = \App\Services\ConversationService::sendMessage(
                $conversationId,
                $message,
                'agent',
                $currentUserId
            );
            
            Response::json([
                'success' => true,
                'message' => 'Conversa criada e mensagem enviada com sucesso',
                'conversation_id' => $conversationId,
                'message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("Erro ao criar nova conversa: " . $e->getMessage());
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Atribuir conversa a agente
     */
    public function assign(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            // Verificar permissão sem abortar (retornar JSON se não tiver)
            if (!Permission::can('conversations.assign.all') && !Permission::can('conversations.assign.own')) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para atribuir conversas'
                ], 403);
                return;
            }
            
            // Ler dados (JSON ou form-data)
            $agentId = \App\Helpers\Request::post('agent_id');
            
            if (!$agentId) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Agente não informado'
                ], 400);
                return;
            }
            
            // Verificar se conversa existe
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Verificar se agente existe
            $agent = User::find($agentId);
            if (!$agent) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Agente não encontrado'
                ], 404);
                return;
            }

            // Atribuir forçadamente (ignora limites) quando é atribuição manual
            $conversation = ConversationService::assignToAgent($id, $agentId, true);
            
            ob_end_clean();
            
            Response::json([
                'success' => true,
                'message' => 'Conversa atribuída com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            error_log("ConversationController::assign - Erro: " . $e->getMessage());
            error_log("ConversationController::assign - Trace: " . $e->getTraceAsString());
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao atribuir conversa: ' . $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Atualizar setor da conversa
     */
    public function updateDepartment(int $id): void
    {
        try {
            Permission::abortIfCannot('conversations.edit');
            
            $departmentId = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;

            $conversation = ConversationService::updateDepartment($id, $departmentId);
            
            Response::json([
                'success' => true,
                'message' => 'Setor atualizado com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Escalar conversa de IA para humano
     */
    public function escalate(int $id): void
    {
        try {
            Permission::abortIfCannot('conversations.edit');
            
            $agentId = $_POST['agent_id'] ?? null;
            $agentId = $agentId ? (int)$agentId : null;

            $conversation = ConversationService::escalateFromAI($id, $agentId);
            
            Response::json([
                'success' => true,
                'message' => 'Conversa escalada com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Fechar conversa
     */
    public function close(int $id): void
    {
        try {
            $conversation = ConversationService::close($id);
            
            Response::json([
                'success' => true,
                'message' => 'Conversa fechada com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reabrir conversa
     */
    public function reopen(int $id): void
    {
        try {
            $conversation = ConversationService::reopen($id);
            
            Response::json([
                'success' => true,
                'message' => 'Conversa reaberta com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar conversas para encaminhamento
     */
    public function listForForwarding(): void
    {
        try {
            Permission::abortIfCannot('conversations.view');
            
            $excludeId = $_GET['exclude'] ?? null;
            $conversations = ConversationService::listForForwarding($excludeId ? (int)$excludeId : null);
            
            Response::json([
                'success' => true,
                'conversations' => $conversations
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao listar conversas para encaminhamento: " . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao carregar conversas: ' . $e->getMessage(),
                'conversations' => []
            ], 500);
        }
    }

    /**
     * Encaminhar mensagem
     */
    public function forwardMessage(int $id): void
    {
        Permission::abortIfCannot('conversations.send');
        
        $data = \App\Helpers\Request::post();
        $messageId = $data['message_id'] ?? null;
        $targetConversationId = $data['target_conversation_id'] ?? null;
        
        if (!$messageId || !$targetConversationId) {
            Response::json([
                'success' => false,
                'message' => 'ID da mensagem e conversa destino são obrigatórios'
            ], 400);
            return;
        }
        
        try {
            $newMessageId = ConversationService::forwardMessage((int)$messageId, (int)$targetConversationId);
            
            // Obter mensagem criada
            $targetConversation = ConversationService::getConversation((int)$targetConversationId);
            $message = null;
            if ($targetConversation && isset($targetConversation['messages'])) {
                foreach ($targetConversation['messages'] as $msg) {
                    if ($msg['id'] == $newMessageId) {
                        $message = $msg;
                        break;
                    }
                }
            }
            
            Response::json([
                'success' => true,
                'message' => $message,
                'message_id' => $newMessageId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Iniciar chamada Api4Com a partir de uma conversa
     */
    public function startApi4ComCall(int $id): void
    {
        Permission::abortIfCannot('api4com_calls.create');
        
        try {
            $conversation = Conversation::find($id);
            if (!$conversation) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }

            // Verificar se há conta Api4Com habilitada
            $account = \App\Models\Api4ComAccount::getFirstEnabled();
            if (!$account) {
                Response::json([
                    'success' => false,
                    'message' => 'Nenhuma conta Api4Com configurada'
                ], 400);
                return;
            }

            // Obter número do contato
            $contact = \App\Models\Contact::find($conversation['contact_id']);
            if (!$contact || empty($contact['phone'])) {
                Response::json([
                    'success' => false,
                    'message' => 'Contato não possui número de telefone'
                ], 400);
                return;
            }

            // Buscar ramal do usuário logado
            $userId = \App\Helpers\Auth::id();
            $extension = \App\Models\Api4ComExtension::findByUserAndAccount($userId, $account['id']);
            if (!$extension || $extension['status'] !== 'active') {
                Response::json([
                    'success' => false,
                    'message' => 'Ramal não configurado para seu usuário. Configure em Integrações → Api4Com'
                ], 400);
                return;
            }

            $data = [
                'api4com_account_id' => $account['id'],
                'contact_id' => $contact['id'],
                'to_number' => $contact['phone'],
                'agent_id' => $userId,
                'conversation_id' => $id,
                'extension_id' => $extension['id']
            ];

            $call = Api4ComService::createCall($data);
            
            Response::json([
                'success' => true,
                'message' => 'Chamada iniciada com sucesso!',
                'call' => $call
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao iniciar chamada: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar mensagem
     */
    public function sendMessage(int $id): void
    {
        try {
            // Verificar se é uma conversa demo e criar no banco se necessário
            $conversation = ConversationService::getConversation($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada.'], 404);
                return;
            }
            
            // Verificar permissão
            if (!Permission::canSendMessage($conversation)) {
                Response::json(['success' => false, 'message' => 'Você não tem permissão para enviar mensagens nesta conversa.'], 403);
                return;
            }
            
            $userId = \App\Helpers\Auth::id();
            $data = \App\Helpers\Request::post();
            $content = $data['content'] ?? $data['message'] ?? '';
            $isNote = $data['is_note'] ?? false;
            
            // Processar anexos se houver
            $attachments = [];
            if (!empty($_FILES['attachments'])) {
                // Se for array de arquivos
                if (is_array($_FILES['attachments']['name'])) {
                    $fileCount = count($_FILES['attachments']['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        $file = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                            'error' => $_FILES['attachments']['error'][$i],
                            'size' => $_FILES['attachments']['size'][$i]
                        ];
                        
                        if ($file['error'] === UPLOAD_ERR_OK) {
                            try {
                                $attachment = \App\Services\AttachmentService::upload($file, $id);
                                $attachments[] = $attachment;
                            } catch (\Exception $e) {
                                // Log erro mas continua
                                error_log("Erro ao fazer upload de anexo: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    // Arquivo único
                    if ($_FILES['attachments']['error'] === UPLOAD_ERR_OK) {
                        try {
                            $attachment = \App\Services\AttachmentService::upload($_FILES['attachments'], $id);
                            $attachments[] = $attachment;
                        } catch (\Exception $e) {
                            error_log("Erro ao fazer upload de anexo: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Se não tem conteúdo nem anexos, retornar erro
            if (empty($content) && empty($attachments)) {
                throw new \Exception('Mensagem não pode estar vazia');
            }

            // Determinar tipo de mensagem
            $messageType = $isNote ? 'note' : null;
            
            // Obter quoted_message_id se houver
            $quotedMessageId = $data['quoted_message_id'] ?? null;
            
            $messageId = ConversationService::sendMessage($id, $content, 'agent', $userId, $attachments, $messageType, $quotedMessageId);
            
            if ($messageId) {
                // Buscar mensagem criada com detalhes
                $messages = \App\Models\Message::getMessagesWithSenderDetails($id);
                $createdMessage = null;
                foreach ($messages as $msg) {
                    if ($msg['id'] == $messageId) {
                        $createdMessage = $msg;
                        break;
                    }
                }
                
                // Formatar mensagem para o frontend
                $messageData = null;
                if ($createdMessage) {
                    $messageData = [
                        'id' => $createdMessage['id'],
                        'content' => $createdMessage['content'],
                        'direction' => 'outgoing',
                        'type' => $isNote ? 'note' : 'message',
                        'created_at' => $createdMessage['created_at'],
                        'sender_name' => $createdMessage['sender_name'] ?? 'Você',
                        'sender_type' => $createdMessage['sender_type'],
                        'attachments' => $createdMessage['attachments'] ?? [],
                        // Campos de reply
                        'quoted_message_id' => $createdMessage['quoted_message_id'] ?? null,
                        'quoted_sender_name' => $createdMessage['quoted_sender_name'] ?? null,
                        'quoted_text' => $createdMessage['quoted_text'] ?? null,
                        // Status
                        'status' => $createdMessage['status'] ?? 'sent',
                        'delivered_at' => $createdMessage['delivered_at'] ?? null,
                        'read_at' => $createdMessage['read_at'] ?? null,
                        'error_message' => $createdMessage['error_message'] ?? null,
                        'message_type' => $createdMessage['message_type'] ?? null
                    ];
                }
                
                Response::json([
                    'success' => true,
                    'message' => $messageData,
                    'message_id' => $messageId
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao enviar mensagem'
                ], 500);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Fixar/Destacar conversa
     */
    public function pin(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            \App\Models\Conversation::update($id, [
                'pinned' => 1,
                'pinned_at' => date('Y-m-d H:i:s')
            ]);
            
            // Invalidar cache de conversas
            \App\Services\ConversationService::invalidateCache($id);
            
            Response::json(['success' => true, 'message' => 'Conversa fixada']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Desfixar conversa
     */
    public function unpin(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            \App\Models\Conversation::update($id, [
                'pinned' => 0,
                'pinned_at' => null
            ]);
            
            // Invalidar cache de conversas
            \App\Services\ConversationService::invalidateCache($id);
            
            Response::json(['success' => true, 'message' => 'Conversa desfixada']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Marcar conversa como SPAM
     */
    public function spam(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            // Verificar se já está marcada como spam
            if (!empty($conversation['is_spam'])) {
                Response::json(['success' => false, 'message' => 'Conversa já está marcada como spam'], 400);
                return;
            }
            
            \App\Services\ConversationService::markAsSpam($id);
            
            Response::json(['success' => true, 'message' => 'Conversa marcada como spam']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Mover conversa para outra etapa
     */
    public function moveStage(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $stageId = Request::post('stage_id');
            
            if (!$stageId) {
                Response::json(['success' => false, 'message' => 'ID da etapa não fornecido'], 400);
                return;
            }
            
            // Verificar se conversa existe
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            // Verificar se etapa existe
            $stage = \App\Models\FunnelStage::find($stageId);
            if (!$stage) {
                Response::json(['success' => false, 'message' => 'Etapa não encontrada'], 404);
                return;
            }
            
            // Usar o FunnelService para mover (já tem validações e logs)
            $userId = \App\Helpers\Auth::id();
            \App\Services\FunnelService::moveConversation($id, $stageId, $userId);
            
            Response::json(['success' => true, 'message' => 'Conversa movida com sucesso']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Marcar conversa como lida
     */
    public function markRead(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            $userId = \App\Helpers\Auth::id();
            \App\Models\Message::markAsRead($id, $userId);
            
            // Invalidar cache
            \App\Services\ConversationService::invalidateCache($id);
            
            Response::json(['success' => true, 'message' => 'Conversa marcada como lida']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Marcar conversa como não lida
     */
    public function markUnread(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            // Marcar todas mensagens do contato como não lidas (remover read_at)
            $sql = "UPDATE messages 
                    SET read_at = NULL 
                    WHERE conversation_id = ? 
                    AND sender_type = 'contact'";
            
            $affected = \App\Helpers\Database::execute($sql, [$id]);
            
            error_log("Conversa {$id}: Marcadas {$affected} mensagens como não lidas");
            
            // Invalidar cache ANTES de recarregar
            \App\Services\ConversationService::invalidateCache($id);
            
            // Recarregar conversa para obter unread_count atualizado (calculado via subquery)
            $conversation = \App\Services\ConversationService::getConversation($id);
            
            error_log("Conversa {$id}: unread_count após marcar como não lida = " . ($conversation['unread_count'] ?? 0));
            
            // Notificar via WebSocket
            try {
                \App\Helpers\WebSocket::notifyConversationUpdated($id, $conversation);
            } catch (\Exception $e) {
                error_log("Erro ao notificar WebSocket: " . $e->getMessage());
            }
            
            Response::json([
                'success' => true, 
                'message' => 'Conversa marcada como não lida',
                'unread_count' => $conversation['unread_count'] ?? 0
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Agendar mensagem
     */
    public function scheduleMessage(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            // Verificar se é JSON ou FormData
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isJson = strpos($contentType, 'application/json') !== false;
            
            if ($isJson) {
                $data = \App\Helpers\Request::json();
                $attachments = $data['attachments'] ?? [];
            } else {
                // FormData
                $data = $_POST;
                $attachments = [];
                
                // Processar anexo se houver
                if (!empty($_FILES['attachment'])) {
                    $file = $_FILES['attachment'];
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $attachmentData = \App\Services\AttachmentService::upload([
                            'name' => $file['name'],
                            'type' => $file['type'],
                            'tmp_name' => $file['tmp_name'],
                            'error' => $file['error'],
                            'size' => $file['size']
                        ], $id);
                        $attachments[] = $attachmentData;
                    }
                }
            }
            
            $content = $data['content'] ?? '';
            $scheduledAt = $data['scheduled_at'] ?? '';
            $cancelIfResolved = !empty($data['cancel_if_resolved']);
            $cancelIfResponded = !empty($data['cancel_if_responded']);
            
            if (empty($scheduledAt)) {
                Response::json(['success' => false, 'message' => 'Data/hora agendada é obrigatória'], 400);
                return;
            }
            
            $userId = \App\Helpers\Auth::id();
            $messageId = \App\Services\ScheduledMessageService::schedule(
                $id,
                $userId,
                $content,
                $scheduledAt,
                $attachments,
                $cancelIfResolved,
                $cancelIfResponded
            );
            
            Response::json([
                'success' => true, 
                'message' => 'Mensagem agendada com sucesso',
                'scheduled_message_id' => $messageId
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Obter mensagens agendadas de uma conversa
     */
    public function getScheduledMessages(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $status = \App\Helpers\Request::get('status'); // opcional: 'pending', 'sent', 'cancelled', 'failed'
            $messages = \App\Services\ScheduledMessageService::getByConversation($id, $status);
            
            Response::json(['success' => true, 'messages' => $messages]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Cancelar mensagem agendada
     */
    public function cancelScheduledMessage(int $id, int $messageId): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            \App\Services\ScheduledMessageService::cancel($messageId, $userId);
            
            Response::json(['success' => true, 'message' => 'Mensagem agendada cancelada']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Criar lembrete
     */
    public function createReminder(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $data = \App\Helpers\Request::json();
            
            $reminderAt = $data['reminder_at'] ?? '';
            $note = $data['note'] ?? null;
            
            if (empty($reminderAt)) {
                Response::json(['success' => false, 'message' => 'Data/hora do lembrete é obrigatória'], 400);
                return;
            }
            
            $userId = \App\Helpers\Auth::id();
            $reminderId = \App\Services\ReminderService::create($id, $userId, $reminderAt, $note);
            
            Response::json([
                'success' => true, 
                'message' => 'Lembrete criado com sucesso',
                'reminder_id' => $reminderId
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Obter lembretes de uma conversa
     */
    public function getReminders(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $onlyActive = \App\Helpers\Request::get('only_active') === '1';
            $reminders = \App\Services\ReminderService::getByConversation($id, $onlyActive);
            
            Response::json(['success' => true, 'reminders' => $reminders]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Marcar lembrete como resolvido
     */
    public function resolveReminder(int $reminderId): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            \App\Services\ReminderService::markAsResolved($reminderId, $userId);
            
            Response::json(['success' => true, 'message' => 'Lembrete marcado como resolvido']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Criar nota interna em uma conversa
     */
    public function createNote(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $content = $_POST['content'] ?? '';
            $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';
            
            if (empty(trim($content))) {
                throw new \Exception('Conteúdo da nota não pode estar vazio');
            }
            
            $note = \App\Services\ConversationNoteService::create($id, $userId, $content, $isPrivate);
            
            Response::json([
                'success' => true,
                'message' => 'Nota criada com sucesso',
                'note' => $note
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar notas de uma conversa
     */
    public function getNotes(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $notes = \App\Services\ConversationNoteService::list($id, $userId);
            
            Response::json([
                'success' => true,
                'notes' => $notes
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obter timeline completa de uma conversa
     */
    public function getTimeline($id): void
    {
        $conversationId = (int)$id;
        if ($conversationId <= 0) {
            Response::json([
                'success' => false,
                'message' => 'ID de conversa inválido'
            ], 400);
            return;
        }
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $conversation = \App\Models\Conversation::findWithRelations($conversationId);
            if (!$conversation) {
                throw new \Exception('Conversa não encontrada');
            }
            
            $events = [];
            
            // Buscar atividades da tabela activities
            if (class_exists('\App\Models\Activity')) {
                $activities = \App\Models\Activity::getByEntity('conversation', $conversationId);
                
                foreach ($activities as $activity) {
                    $metadata = $activity['metadata'] ?? [];
                    
                    switch ($activity['activity_type']) {
                        case 'tag_added':
                            $tagName = $metadata['tag_name'] ?? ($activity['description'] ?? 'Tag');
                            $events[] = [
                                'type' => 'tag_added',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-tag',
                                'color' => 'success',
                                'title' => "Tag '{$tagName}' adicionada",
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'tag_removed':
                            $tagName = $metadata['tag_name'] ?? ($activity['description'] ?? 'Tag');
                            $events[] = [
                                'type' => 'tag_removed',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-tag',
                                'color' => 'danger',
                                'title' => "Tag '{$tagName}' removida",
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'participant_added':
                            $events[] = [
                                'type' => 'participant_added',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-profile-user',
                                'color' => 'info',
                                'title' => $activity['description'] ?? 'Participante adicionado',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'participant_removed':
                            $events[] = [
                                'type' => 'participant_removed',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-profile-user',
                                'color' => 'warning',
                                'title' => $activity['description'] ?? 'Participante removido',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'department_changed':
                        case 'department_assigned':
                            $events[] = [
                                'type' => 'department_changed',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-arrows-circle',
                                'color' => 'primary',
                                'title' => $activity['description'] ?? 'Setor alterado',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'stage_moved':
                        case 'funnel_stage_changed':
                            $events[] = [
                                'type' => 'funnel_stage_changed',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-arrow-right',
                                'color' => 'info',
                                'title' => $activity['description'] ?? 'Estágio do funil alterado',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'conversation_assigned':
                            $events[] = [
                                'type' => 'assigned',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-profile-user',
                                'color' => 'info',
                                'title' => $activity['description'] ?? 'Conversa atribuída',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'conversation_closed':
                            $events[] = [
                                'type' => 'closed',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-cross-circle',
                                'color' => 'dark',
                                'title' => 'Conversa fechada',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'conversation_reopened':
                            $events[] = [
                                'type' => 'reopened',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-entrance-right',
                                'color' => 'success',
                                'title' => 'Conversa reaberta',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                    }
                }
            }
            
            // Buscar mensagens de sistema que indicam mudanças
            $systemMessages = \App\Helpers\Database::fetchAll(
                "SELECT * FROM messages WHERE conversation_id = ? AND message_type = 'system' ORDER BY created_at DESC",
                [$conversationId]
            );
            
            foreach ($systemMessages as $msg) {
                $content = strip_tags($msg['content'] ?? '');
                
                // Detectar tipo de mudança pelo conteúdo
                if (strpos($content, 'Setor alterado') !== false || strpos($content, 'setor') !== false) {
                    // Verificar se já não temos evento de mudança de setor mais recente
                    $hasRecentDeptChange = false;
                    foreach ($events as $event) {
                        if ($event['type'] === 'department_changed' && strtotime($event['date']) >= strtotime($msg['created_at'])) {
                            $hasRecentDeptChange = true;
                            break;
                        }
                    }
                    if (!$hasRecentDeptChange) {
                        $events[] = [
                            'type' => 'department_changed',
                            'date' => $msg['created_at'],
                            'icon' => 'ki-arrows-circle',
                            'color' => 'primary',
                            'title' => 'Setor alterado',
                            'description' => $content,
                            'user_name' => null
                        ];
                    }
                }
            }
            
            Response::json([
                'success' => true,
                'events' => $events
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'events' => []
            ], 400);
        }
    }

    /**
     * Atualizar nota
     */
    public function updateNote(int $id, int $noteId): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $content = $_POST['content'] ?? '';
            
            $note = \App\Services\ConversationNoteService::update($noteId, $userId, $content);
            
            Response::json([
                'success' => true,
                'message' => 'Nota atualizada com sucesso',
                'note' => $note
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar nota
     */
    public function deleteNote(int $id, int $noteId): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            
            \App\Services\ConversationNoteService::delete($noteId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Nota deletada com sucesso'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Buscar mensagens dentro de uma conversa
     */
    public function searchMessages(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $search = $_GET['q'] ?? '';
            $filters = [
                'message_type' => $_GET['message_type'] ?? null,
                'sender_type' => $_GET['sender_type'] ?? null,
                'sender_id' => isset($_GET['sender_id']) ? (int)$_GET['sender_id'] : null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'has_attachments' => isset($_GET['has_attachments']) ? filter_var($_GET['has_attachments'], FILTER_VALIDATE_BOOLEAN) : null,
                'ai_agent_id' => isset($_GET['ai_agent_id']) ? filter_var($_GET['ai_agent_id'], FILTER_VALIDATE_BOOLEAN) : null
            ];
            
            // Remover filtros vazios
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            // Se não tem busca nem filtros, retornar erro
            if (empty($search) && empty($filters)) {
                Response::json(['success' => false, 'message' => 'Termo de busca ou filtros devem ser fornecidos'], 400);
                return;
            }
            
            $messages = \App\Models\Message::searchInConversation($id, $search, $filters);
            
            Response::json([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Obter mensagens paginadas de uma conversa (para scroll infinito)
     */
    public function getMessages(int $id): void
    {
        // Limpar output buffer antes de processar
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
            
            // Validar limit
            if ($limit < 1 || $limit > 100) {
                $limit = 50;
            }
            
            // Buscar mensagens
            $messages = \App\Models\Message::getMessagesWithSenderDetails($id, $limit, null, $beforeId);
            
            // Adicionar campos type e direction para cada mensagem
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
            
            // Contar total de mensagens
            $total = \App\Models\Message::countByConversation($id);
            
            // Log para debug
            if (!empty($messages)) {
                \App\Helpers\Logger::info("📤 getMessages: Retornando " . count($messages) . " mensagens com direction", 'conversas.log');
                \App\Helpers\Logger::info("📤 getMessages: Primeira mensagem - id={$messages[0]['id']}, sender_type={$messages[0]['sender_type']}, direction={$messages[0]['direction']}", 'conversas.log');
            }
            
            // Limpar output buffer novamente antes de retornar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json([
                'success' => true,
                'messages' => $messages,
                'has_more' => $beforeId ? count($messages) === $limit : false,
                'total' => $total,
                'count' => count($messages)
            ]);
        } catch (\Exception $e) {
            // Limpar output buffer antes de retornar erro
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Obter sentimento atual de uma conversa
     */
    public function getSentiment($id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $conversationId = (int)$id;
            if ($conversationId <= 0) {
                ob_end_clean();
                Response::json(['success' => false, 'message' => 'ID inválido'], 400);
                return;
            }
            
            // Verificar permissão (conversations.view.own ou conversations.view.all)
            if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para visualizar sentimentos de conversas'
                ], 403);
                return;
            }
            
            // Verificar se o usuário tem acesso à conversa específica
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                ob_end_clean();
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            // Se não tem permissão para ver todas, verificar se tem acesso à conversa específica
            if (!Permission::can('conversations.view.all')) {
                if (!Permission::canViewConversation($conversation)) {
                    ob_end_clean();
                    Response::json([
                        'success' => false,
                        'message' => 'Você não tem permissão para ver esta conversa'
                    ], 403);
                    return;
                }
            }
            
            $sentiment = \App\Services\SentimentAnalysisService::getCurrentSentiment($conversationId);
            
            $this->restoreAfterJsonResponse($config);
            
            Response::json([
                'success' => true,
                'sentiment' => $sentiment
            ]);
        } catch (\Exception $e) {
            $this->restoreAfterJsonResponse($config);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Listar participantes de uma conversa
     */
    public function getParticipants($id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            // Converter ID para int e validar
            $conversationId = (int)$id;
            if ($conversationId <= 0) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'ID de conversa inválido',
                    'participants' => []
                ], 400);
                return;
            }
            
            // Verificar permissão
            if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para visualizar conversas'
                ], 403);
                return;
            }
            
            $participants = \App\Models\ConversationParticipant::getByConversation($conversationId);
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'participants' => $participants ?: []
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            error_log("ConversationController::getParticipants - Erro: " . $e->getMessage());
            
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'participants' => []
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Adicionar participante a uma conversa
     */
    public function addParticipant(int $id): void
    {
        error_log("=== addParticipant CHAMADO === ConversationID: {$id}");
        
        $config = $this->prepareJsonResponse();
        
        try {
            error_log("ConversationController::addParticipant - INÍCIO - ConversationID: {$id}");
            
            // Verificar permissão sem abortar (retornar JSON se não tiver)
            if (!Permission::can('conversations.edit.own') && !Permission::can('conversations.edit.all')) {
                error_log("ConversationController::addParticipant - Sem permissão");
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para editar conversas'
                ], 403);
                return;
            }
            
            // Ler dados (JSON ou form-data)
            $rawPost = \App\Helpers\Request::post();
            error_log("ConversationController::addParticipant - POST data: " . json_encode($rawPost));
            
            $userId = (int)\App\Helpers\Request::post('user_id');
            error_log("ConversationController::addParticipant - UserID: {$userId}");
            
            if (!$userId) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'ID do usuário é obrigatório'
                ], 400);
                return;
            }
            
            // Verificar se conversação existe
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Verificar se usuário existe
            $user = \App\Models\User::find($userId);
            if (!$user) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
                return;
            }
            
            $addedBy = \App\Helpers\Auth::id();
            error_log("ConversationController::addParticipant - AddedBy: {$addedBy}");
            error_log("ConversationController::addParticipant - Chamando ConversationParticipant::addParticipant");
            
            $success = \App\Models\ConversationParticipant::addParticipant($id, $userId, $addedBy);
            
            error_log("ConversationController::addParticipant - Success: " . ($success ? 'true' : 'false'));
            
            // Limpar buffer antes de enviar JSON
            ob_end_clean();
            
            if ($success) {
                // Invalidar cache da conversa
                ConversationService::invalidateCache($id);

                // Registrar no timeline
                if (class_exists('\App\Services\ActivityService')) {
                    try {
                        \App\Services\ActivityService::logParticipantAdded($id, $userId, $addedBy);
                    } catch (\Exception $e) {
                        error_log("Activity log participant_added falhou: " . $e->getMessage());
                    }
                }
                
                Response::json([
                    'success' => true,
                    'message' => 'Participante adicionado com sucesso'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao adicionar participante. Talvez ele já faça parte da conversa.'
                ], 400);
            }
        } catch (\Exception $e) {
            // Limpar buffer em caso de erro
            ob_end_clean();
            
            error_log("ConversationController::addParticipant - Erro: " . $e->getMessage());
            error_log("ConversationController::addParticipant - Trace: " . $e->getTraceAsString());
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao adicionar participante: ' . $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Obter status da IA na conversa
     */
    public function getAIStatus(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            Permission::abortIfCannot('conversations.view.own');
            
            $status = \App\Services\ConversationAIService::getAIStatus($id);
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Obter status da automação ativa na conversa
     */
    public function getAutomationStatus(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            Permission::abortIfCannot('conversations.view.own');
            
            $sql = "SELECT ae.automation_id, ae.status as execution_status, ae.created_at as execution_at,
                           a.name as automation_name, a.status as automation_status, a.trigger_type
                    FROM automation_executions ae
                    LEFT JOIN automations a ON a.id = ae.automation_id
                    WHERE ae.conversation_id = ?
                    ORDER BY ae.created_at DESC
                    LIMIT 1";
            $row = \App\Helpers\Database::fetch($sql, [$id]);
            
            $data = [
                'has_automation' => (bool)$row,
                'automation' => null
            ];
            
            if ($row) {
                $autoId = (int)$row['automation_id'];
                // Nome amigável mesmo se automação estiver sem nome ou removida
                $resolvedName = $row['automation_name'] ?? '';
                if (empty($resolvedName) && $autoId) {
                    $resolvedName = 'Automação #' . $autoId;
                }
                
                $data['automation'] = [
                    'id' => $autoId,
                    'name' => $resolvedName ?: 'Automação',
                    'automation_status' => $row['automation_status'] ?? 'inactive',
                    'execution_status' => $row['execution_status'] ?? null,
                    'trigger_type' => $row['trigger_type'] ?? null,
                    'last_execution_at' => $row['execution_at'] ?? null
                ];
            }
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Obter mensagens da IA na conversa
     */
    public function getAIMessages(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            Permission::abortIfCannot('conversations.view.own');
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $messages = \App\Services\ConversationAIService::getAIMessages($id, $limit, $offset);
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Adicionar agente de IA à conversa
     */
    public function addAIAgent(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            Permission::abortIfCannot('conversations.edit.own');
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            
            $result = \App\Services\ConversationAIService::addAIAgent($id, $data);
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Remover agente de IA da conversa
     */
    public function removeAIAgent(int $id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            Permission::abortIfCannot('conversations.edit.own');
            
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                $data = $_POST;
            }
            
            $result = \App\Services\ConversationAIService::removeAIAgent($id, $data);
            
            ob_end_clean();
            Response::json([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    /**
     * Listar agentes de IA disponíveis
     */
    public function getAvailableAIAgents(): void
    {
        // Limpar qualquer output anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            error_log('=== getAvailableAIAgents INÍCIO ===');
            
            // Qualquer usuário logado pode ver agentes disponíveis
            // Permission::abortIfCannot('conversations.view.own');
            
            error_log('Chamando ConversationAIService::getAvailableAgents()');
            $agents = \App\Services\ConversationAIService::getAvailableAgents();
            
            error_log('Agentes retornados: ' . count($agents));
            error_log('Dados: ' . json_encode($agents));
            
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $agents
            ]);
            exit;
        } catch (\Exception $e) {
            error_log('❌ ERRO em getAvailableAIAgents: ' . $e->getMessage());
            error_log('Arquivo: ' . $e->getFile() . ':' . $e->getLine());
            error_log('Stack: ' . $e->getTraceAsString());
            
            ob_end_clean();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }

    /**
     * Remover participante de uma conversa
     */
    public function removeParticipant(int $id, int $userId): void
    {
        error_log("=== removeParticipant CHAMADO === ConversationID: {$id}, UserID: {$userId}");
        
        $config = $this->prepareJsonResponse();
        
        try {
            error_log("ConversationController::removeParticipant - INÍCIO - ConversationID: {$id}, UserID: {$userId}");
            
            // Verificar permissão sem abortar (retornar JSON se não tiver)
            if (!Permission::can('conversations.edit.own') && !Permission::can('conversations.edit.all')) {
                error_log("ConversationController::removeParticipant - Sem permissão");
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para editar conversas'
                ], 403);
                return;
            }
            
            error_log("ConversationController::removeParticipant - Chamando ConversationParticipant::removeParticipant");
            $success = \App\Models\ConversationParticipant::removeParticipant($id, $userId);
            
            error_log("ConversationController::removeParticipant - Success: " . ($success ? 'true' : 'false'));
            
            // Limpar buffer antes de enviar JSON
            ob_end_clean();
            
            if ($success) {
                // Invalidar cache da conversa
                ConversationService::invalidateCache($id);

                // Registrar no timeline
                if (class_exists('\App\Services\ActivityService')) {
                    try {
                        $removedBy = \App\Helpers\Auth::id();
                        \App\Services\ActivityService::logParticipantRemoved($id, $userId, $removedBy);
                    } catch (\Exception $e) {
                        error_log("Activity log participant_removed falhou: " . $e->getMessage());
                    }
                }
                
                Response::json([
                    'success' => true,
                    'message' => 'Participante removido com sucesso'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao remover participante'
                ], 400);
            }
        } catch (\Exception $e) {
            // Limpar buffer em caso de erro
            ob_end_clean();
            
            error_log("ConversationController::removeParticipant - Erro: " . $e->getMessage());
            error_log("ConversationController::removeParticipant - Trace: " . $e->getTraceAsString());
            
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover participante: ' . $e->getMessage()
            ], 500);
        } finally {
            $this->restoreAfterJsonResponse($config);
        }
    }

    // ========================================================================
    // MENÇÕES / CONVITES DE AGENTES
    // ========================================================================

    /**
     * Mencionar/convidar um agente para uma conversa
     * POST /conversations/{id}/mention
     */
    public function mention(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $data = \App\Helpers\Request::json();
            
            $mentionedUserId = $data['user_id'] ?? null;
            $note = $data['note'] ?? null;
            
            if (!$mentionedUserId) {
                Response::json(['success' => false, 'message' => 'ID do usuário é obrigatório'], 400);
                return;
            }
            
            $mention = \App\Services\ConversationMentionService::mention(
                $id,
                (int) $mentionedUserId,
                $userId,
                $note
            );
            
            Response::json([
                'success' => true,
                'message' => 'Convite enviado com sucesso',
                'mention' => $mention
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao enviar convite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obter menções de uma conversa
     * GET /conversations/{id}/mentions
     */
    public function getMentions(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $mentions = \App\Services\ConversationMentionService::getByConversation($id);
            
            Response::json([
                'success' => true,
                'mentions' => $mentions
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obter agentes disponíveis para mencionar
     * GET /conversations/{id}/available-agents
     */
    public function getAvailableAgents(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $userId = \App\Helpers\Auth::id();
            $agents = \App\Services\ConversationMentionService::getAvailableAgents($id, $userId);
            
            Response::json([
                'success' => true,
                'agents' => $agents
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obter convites pendentes para o usuário logado
     * GET /conversations/invites
     */
    public function getInvites(): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            $invites = \App\Services\ConversationMentionService::getPendingInvites($userId);
            $count = \App\Services\ConversationMentionService::countPending($userId);
            
            Response::json([
                'success' => true,
                'invites' => $invites,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[getInvites] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Contar convites pendentes
     * GET /conversations/invites/count
     */
    public function countInvites(): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $count = \App\Services\ConversationMentionService::countPending($userId);
            
            Response::json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Aceitar convite de menção
     * POST /conversations/invites/{mentionId}/accept
     */
    public function acceptInvite(int $mentionId): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $mention = \App\Services\ConversationMentionService::accept($mentionId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Convite aceito! Você agora é participante da conversa.',
                'mention' => $mention
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao aceitar convite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recusar convite de menção
     * POST /conversations/invites/{mentionId}/decline
     */
    public function declineInvite(int $mentionId): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $mention = \App\Services\ConversationMentionService::decline($mentionId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Convite recusado.',
                'mention' => $mention
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao recusar convite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cancelar convite de menção (quem enviou pode cancelar)
     * POST /conversations/invites/{mentionId}/cancel
     */
    public function cancelInvite(int $mentionId): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $result = \App\Services\ConversationMentionService::cancel($mentionId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Convite cancelado.',
                'mention' => $result
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Erro ao cancelar convite: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obter histórico de convites do usuário
     * GET /conversations/invites/history
     */
    public function getInviteHistory(): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $limit = (int) (\App\Helpers\Request::get('limit') ?? 50);
            $offset = (int) (\App\Helpers\Request::get('offset') ?? 0);
            
            $history = \App\Services\ConversationMentionService::getHistory($userId, $limit, $offset);
            
            Response::json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================
    // SISTEMA DE SOLICITAÇÃO DE PARTICIPAÇÃO
    // ============================================

    /**
     * Solicitar participação em uma conversa
     * POST /conversations/{id}/request-participation
     */
    public function requestParticipation(int $id): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            $data = \App\Helpers\Request::json();
            $note = $data['note'] ?? null;
            
            \App\Helpers\Log::debug("🔍 [requestParticipation] conversationId={$id}, userId={$userId}", 'conversas.log');
            
            $request = \App\Services\ConversationMentionService::requestParticipation(
                $id,
                $userId,
                $note
            );
            
            \App\Helpers\Log::debug("🔍 [requestParticipation] Sucesso - request criada", 'conversas.log');
            
            Response::json([
                'success' => true,
                'message' => 'Solicitação enviada com sucesso! Aguarde aprovação.',
                'request' => $request
            ]);
        } catch (\InvalidArgumentException $e) {
            \App\Helpers\Log::error("[requestParticipation] InvalidArgumentException: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[requestParticipation] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => 'Erro ao solicitar participação: ' . $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Aprovar solicitação de participação
     * POST /conversations/requests/{requestId}/approve
     */
    public function approveRequest(int $requestId): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            \App\Helpers\Log::debug("[approveRequest] requestId={$requestId}, userId={$userId}", 'conversas.log');
            
            $request = \App\Services\ConversationMentionService::approveRequest($requestId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Solicitação aprovada! O agente agora é participante da conversa.',
                'request' => $request
            ]);
        } catch (\InvalidArgumentException $e) {
            \App\Helpers\Log::error("[approveRequest] InvalidArgumentException: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[approveRequest] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => 'Erro ao aprovar solicitação: ' . $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Recusar solicitação de participação
     * POST /conversations/requests/{requestId}/reject
     */
    public function rejectRequest(int $requestId): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            \App\Helpers\Log::debug("[rejectRequest] requestId={$requestId}, userId={$userId}", 'conversas.log');
            
            $request = \App\Services\ConversationMentionService::rejectRequest($requestId, $userId);
            
            Response::json([
                'success' => true,
                'message' => 'Solicitação recusada.',
                'request' => $request
            ]);
        } catch (\InvalidArgumentException $e) {
            \App\Helpers\Log::error("[rejectRequest] InvalidArgumentException: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[rejectRequest] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => 'Erro ao recusar solicitação: ' . $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Obter solicitações de participação pendentes que o usuário pode aprovar
     * GET /conversations/requests/pending
     */
    public function getPendingRequests(): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            $requests = \App\Models\ConversationMention::getPendingRequestsToApprove($userId);
            $count = \App\Models\ConversationMention::countPendingRequestsToApprove($userId);
            
            Response::json([
                'success' => true,
                'requests' => $requests,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[getPendingRequests] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Obter contadores de convites e solicitações pendentes
     * GET /conversations/invites/counts
     */
    public function getInviteCounts(): void
    {
        $oldConfig = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            
            // Convites pendentes (onde o usuário foi convidado)
            $invitesCount = \App\Models\ConversationMention::countPendingForUser($userId);
            
            // Solicitações pendentes (que o usuário pode aprovar)
            $requestsCount = \App\Models\ConversationMention::countPendingRequestsToApprove($userId);
            
            Response::json([
                'success' => true,
                'invites_count' => $invitesCount,
                'requests_count' => $requestsCount,
                'total_count' => $invitesCount + $requestsCount
            ]);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[getInviteCounts] Exception: " . $e->getMessage(), 'conversas.log');
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        } finally {
            $this->restoreAfterJsonResponse($oldConfig);
        }
    }

    /**
     * Obter métricas do agente atual (tempo de resposta e SLA)
     */
    public function getCurrentAgentMetrics(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $userId = \App\Helpers\Auth::id();
            if (!$userId) {
                ob_end_clean();
                Response::json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
                return;
            }
            
            // Buscar métricas do agente atual (hoje)
            $dateFrom = date('Y-m-d') . ' 00:00:00';
            $dateTo = date('Y-m-d H:i:s');
            
            $metrics = \App\Services\DashboardService::getAgentMetrics($userId, $dateFrom, $dateTo);
            
            // Buscar configurações de SLA
            $slaSettings = \App\Services\ConversationSettingsService::getSettings()['sla'] ?? [];
            $slaFirstResponseMinutes = $slaSettings['first_response_time'] ?? 15;
            $slaResponseMinutes = $slaSettings['ongoing_response_time'] ?? $slaFirstResponseMinutes;
            
            $this->restoreAfterJsonResponse($config);
            
            Response::json([
                'success' => true,
                'metrics' => [
                    'avg_first_response_minutes' => $metrics['avg_first_response_minutes'] ?? 0,
                    'avg_response_minutes' => $metrics['avg_response_minutes'] ?? 0,
                    'sla_first_response_rate' => $metrics['sla_first_response_rate'] ?? 0,
                    'sla_response_rate' => $metrics['sla_response_rate'] ?? 0,
                    'sla_first_response_minutes' => $slaFirstResponseMinutes,
                    'sla_response_minutes' => $slaResponseMinutes
                ]
            ]);
        } catch (\Exception $e) {
            $this->restoreAfterJsonResponse($config);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

