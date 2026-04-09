<?php
/**
 * Controller de Conversas
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Helpers\Request;
use App\Helpers\Auth;
use App\Services\ConversationService;
use App\Services\Api4ComService;
use App\Services\PermissionService;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Models\AgentFunnelPermission;

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
        // Se status for vazio ou 'all', significa "Todas" - não filtrar por status
        // Quando vem de contact_id (histórico do contato), mostrar todas por padrão
        $hasContactFilter = !empty($_GET['contact_id']);
        $statusFilter = $_GET['status'] ?? ($hasContactFilter ? null : 'open');
        if ($statusFilter === '' || $statusFilter === 'all') {
            $statusFilter = null; // Não filtrar por status = mostrar todas
        }
        
        $filters = [
            'status' => $statusFilter,
            'channel' => $_GET['channel'] ?? null,
            'channels' => isset($_GET['channels']) && is_array($_GET['channels']) ? $_GET['channels'] : (!empty($_GET['channel']) ? [$_GET['channel']] : null),
            'search' => $_GET['search'] ?? null,
            'contact_id' => !empty($_GET['contact_id']) ? (int)$_GET['contact_id'] : null,
            'agent_id' => isset($_GET['agent_id']) ? ($_GET['agent_id'] === '0' || $_GET['agent_id'] === 0 ? '0' : ($_GET['agent_id'] === 'participating' ? null : $_GET['agent_id'])) : null,
            'agent_ids' => isset($_GET['agent_ids']) && is_array($_GET['agent_ids']) ? $_GET['agent_ids'] : (!empty($_GET['agent_id']) && $_GET['agent_id'] !== '0' && $_GET['agent_id'] !== 'participating' ? [$_GET['agent_id']] : null),
            'participant_id' => (isset($_GET['agent_id']) && $_GET['agent_id'] === 'participating') ? $userId : null,
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
            'limit' => $_GET['limit'] ?? 70,
            'offset' => $_GET['offset'] ?? 0
        ];

        // Resolver tab_id em condições de filtro da aba avançada
        if (!empty($_GET['tab_id'])) {
            try {
                $tab = \App\Models\UserConversationTab::findTab((int) $_GET['tab_id']);
                if ($tab && (int)$tab['user_id'] === $userId) {
                    $tabFilters = \App\Models\UserConversationTab::getTabFilters($tab);
                    $filters = array_merge($filters, $tabFilters);
                    // Remover tag_id/tag_ids individuais se tab_conditions os contém
                    if (!empty($tabFilters['tab_conditions'])) {
                        unset($filters['tag_id'], $filters['tag_ids']);
                    }
                }
            } catch (\Exception $e) {
                // Ignorar erro - usar filtros normais
            }
        }

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
            if (in_array($key, ['channels', 'tag_ids', 'whatsapp_account_ids', 'agent_ids', 'tab_conditions']) && is_array($value)) {
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
            $whatsappAccounts = \App\Models\IntegrationAccount::getActiveWhatsApp();
            // Obter funis permitidos para o agente (usado no modal de nova conversa)
            $funnelsForNewConversation = [];
            try {
                $allowedFunnels = AgentFunnelPermission::getAllowedFunnelIds($userId);
                $allFunnels = Funnel::all();
                
                if ($allowedFunnels === null) {
                    // Admin/SuperAdmin: todos os funis
                    $funnelsForNewConversation = $allFunnels;
                } elseif (!empty($allowedFunnels)) {
                    foreach ($allFunnels as $funnel) {
                        if (in_array((int)$funnel['id'], $allowedFunnels, true)) {
                            $funnelsForNewConversation[] = $funnel;
                        }
                    }
                } else {
                    // Sem permissões específicas: nenhum funil disponível
                    $funnelsForNewConversation = [];
                }
            } catch (\Exception $e) {
                // Se der erro, apenas não carregar funis (não bloquear a página)
                $funnelsForNewConversation = [];
            }
            
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
            
            // Carregar abas personalizadas do agente
            $userTabs = [];
            try {
                $userTabs = \App\Models\UserConversationTab::getByUserWithCounts($userId);
            } catch (\Exception $e) {
                // Tabela pode não existir ainda - ignorar
                error_log("Erro ao carregar abas do usuário: " . $e->getMessage());
            }

            // Buscar dados do contato se estiver filtrando por contact_id
            $filterContact = null;
            if (!empty($filters['contact_id'])) {
                $filterContact = \App\Models\Contact::find((int)$filters['contact_id']);
            }

            Response::view('conversations/index', [
                'conversations' => $conversations,
                'agents' => $agents,
                'departments' => $departments ?? [],
                'tags' => $tags ?? [],
                'whatsappAccounts' => $whatsappAccounts ?? [],
                'funnelsForNewConversation' => $funnelsForNewConversation ?? [],
                'filters' => $filters,
                'filterContact' => $filterContact,
                'selectedConversation' => $selectedConversation,
                'selectedConversationId' => $selectedConversationId,
                'accessRestricted' => $accessRestricted,
                'accessInfo' => $accessInfo,
                'userTabs' => $userTabs
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
     * Verificar conversas existentes antes de criar nova
     */
    public function checkExistingConversationsBeforeCreate(): void
    {
        try {
            $data = \App\Helpers\Request::post();
            $phone = trim($data['phone'] ?? '');
            
            if (empty($phone)) {
                Response::json(['success' => false, 'has_conversations' => false]);
                return;
            }
            
            // Normalizar telefone
            $phone = preg_replace('/\D/', '', $phone);
            if (!str_starts_with($phone, '55') && strlen($phone) >= 10 && strlen($phone) <= 11) {
                $phone = '55' . $phone;
            }
            
            // Buscar contato usando busca normalizada (considera variantes com/sem 9º dígito)
            $contact = \App\Models\Contact::findByPhoneNormalized($phone);
            
            if (!$contact) {
                Response::json(['success' => true, 'has_conversations' => false]);
                return;
            }
            
            // Buscar conversas abertas do contato (integration_accounts unificado)
            $sql = "SELECT c.*, 
                           ia.name as account_name,
                           ia.phone_number as account_phone,
                           u.name as agent_name
                    FROM conversations c
                    LEFT JOIN integration_accounts ia ON c.integration_account_id = ia.id
                    LEFT JOIN users u ON c.agent_id = u.id
                    WHERE c.contact_id = ? AND c.status = 'open'
                    ORDER BY c.updated_at DESC";
            
            $conversations = \App\Helpers\Database::fetchAll($sql, [$contact['id']]);
            
            if (empty($conversations)) {
                Response::json(['success' => true, 'has_conversations' => false]);
                return;
            }
            
            $convInfo = array_map(function($conv) {
                return [
                    'id' => $conv['id'],
                    'account_name' => $conv['account_name'] ?? 'Desconhecido',
                    'account_phone' => $conv['account_phone'] ?? '',
                    'agent_name' => $conv['agent_name'] ?? 'Sem agente',
                    'status' => $conv['status'],
                    'updated_at' => $conv['updated_at']
                ];
            }, $conversations);
            
            Response::json([
                'success' => true,
                'has_conversations' => true,
                'conversations' => $convInfo
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Templates disponíveis para o usuário logado (filtrados por permissão)
     */
    public function availableTemplates(int $accountId): void
    {
        Permission::abortIfCannot('messages.send.own');

        try {
            $userId = Auth::id();
            $templates = \App\Services\NotificameService::listTemplates($accountId);

            $db = \App\Helpers\Database::getInstance();
            $stmt = $db->prepare("SELECT template_name, allowed_users FROM notificame_template_permissions WHERE integration_account_id = ?");
            $stmt->execute([$accountId]);
            $permRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $permMap = [];
            foreach ($permRows as $row) {
                $allowed = json_decode($row['allowed_users'], true);
                if (!empty($allowed) && is_array($allowed)) {
                    $permMap[$row['template_name']] = $allowed;
                }
            }

            $isSuperAdmin = PermissionService::isSuperAdmin($userId);

            if (!$isSuperAdmin && !empty($permMap)) {
                $templates = array_values(array_filter($templates, function ($tpl) use ($permMap, $userId) {
                    $name = $tpl['name'] ?? '';
                    if (!isset($permMap[$name])) {
                        return true;
                    }
                    return in_array((int)$userId, $permMap[$name], true);
                }));
            }

            Response::json([
                'success' => true,
                'templates' => $templates
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Criar nova conversa com contato e mensagem
     * Qualquer agente autenticado pode criar conversas
     */
    public function newConversation(): void
    {
        try {
            // Aceitar tanto JSON quanto form-data (Request::post já trata JSON)
            $data = \App\Helpers\Request::post();
            
            $channel = trim($data['channel'] ?? 'whatsapp');
            $whatsappAccountId = !empty($data['whatsapp_account_id']) ? (int)$data['whatsapp_account_id'] : null;
            $name = trim($data['name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $message = trim($data['message'] ?? '');
            $funnelId = !empty($data['funnel_id']) ? (int)$data['funnel_id'] : null;
            $stageId = !empty($data['stage_id']) ? (int)$data['stage_id'] : null;
            $useTemplate = !empty($data['use_template']);
            $templateName = trim($data['template_name'] ?? '');
            $templateParams = $data['template_params'] ?? [];
            $templateLanguage = trim($data['template_language'] ?? 'pt_BR');
            $templateBodyText = trim($data['template_body_text'] ?? '');

            if (empty($channel) || empty($name) || empty($phone)) {
                Response::json(['success' => false, 'message' => 'Preencha todos os campos obrigatórios'], 400);
                return;
            }

            if ($useTemplate) {
                if (empty($templateName)) {
                    Response::json(['success' => false, 'message' => 'Selecione um template para enviar'], 400);
                    return;
                }
            } elseif (empty($message)) {
                Response::json(['success' => false, 'message' => 'Preencha a mensagem ou selecione um template'], 400);
                return;
            }
            
            // Validar canal
            $validChannels = [
                'whatsapp', 'whatsapp_official', 'instagram', 'instagram_comment', 'facebook', 'tiktok', 
                'telegram', 'email', 'chat', 'mercadolivre', 'webchat', 
                'olx', 'linkedin', 'google_business', 'youtube'
            ];
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
                
                // Verificar se a conta WhatsApp existe e está ativa (integration_accounts unificado)
                $whatsappAccount = \App\Models\IntegrationAccount::find($whatsappAccountId);
                if (!$whatsappAccount || ($whatsappAccount['status'] ?? '') !== 'active') {
                    Response::json(['success' => false, 'message' => 'Integração WhatsApp inválida ou inativa'], 400);
                    return;
                }
                $accountType = 'integration';
                
                // ✅ CORRIGIDO: Se usuário não selecionou funil/etapa, usar defaults da conta WhatsApp
                if (!$funnelId && !$stageId) {
                    if (!empty($whatsappAccount['default_funnel_id'])) {
                        $funnelId = (int)$whatsappAccount['default_funnel_id'];
                        \App\Helpers\Logger::info("newConversation - Usando funil padrão da conta: {$funnelId}");
                    }
                    if (!empty($whatsappAccount['default_stage_id'])) {
                        $stageId = (int)$whatsappAccount['default_stage_id'];
                        \App\Helpers\Logger::info("newConversation - Usando etapa padrão da conta: {$stageId}");
                    }
                }
                
                // ✅ VERIFICAR LIMITE DE NOVAS CONVERSAS (Anti-Spam)
                $rateLimitCheck = \App\Services\NewConversationRateLimitService::canCreateNewConversation($whatsappAccountId, $accountType);
                if (!$rateLimitCheck['allowed']) {
                    Response::json([
                        'success' => false, 
                        'message' => $rateLimitCheck['message'],
                        'code' => 'rate_limit_exceeded',
                        'remaining' => $rateLimitCheck['remaining'],
                        'reset_in' => $rateLimitCheck['reset_in'],
                        'limit' => $rateLimitCheck['limit'] ?? null,
                        'current' => $rateLimitCheck['current'] ?? null
                    ], 429);
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
            
            // Validar permissão de funil/etapa selecionados
            if ($stageId) {
                // Etapa define o funil, então sincronizar funnelId se não vier
                $stage = FunnelStage::find($stageId);
                if (!$stage) {
                    Response::json(['success' => false, 'message' => 'Etapa selecionada não encontrada'], 400);
                    return;
                }
                
                // Se usuário não puder ver a etapa, bloquear
                if (!AgentFunnelPermission::canViewStage($currentUserId, $stageId)) {
                    Response::json(['success' => false, 'message' => 'Sem permissão para a etapa selecionada'], 403);
                    return;
                }
                
                // Garantir que funnelId corresponda ao da etapa
                $funnelId = $funnelId ?: (int)$stage['funnel_id'];
                if ($funnelId && (int)$stage['funnel_id'] !== $funnelId) {
                    Response::json(['success' => false, 'message' => 'Etapa não pertence ao funil selecionado'], 400);
                    return;
                }
            }
            
            if ($funnelId) {
                $allowedFunnels = AgentFunnelPermission::getAllowedFunnelIds($currentUserId);
                if ($allowedFunnels !== null && !in_array($funnelId, $allowedFunnels, true)) {
                    Response::json(['success' => false, 'message' => 'Sem permissão para o funil selecionado'], 403);
                    return;
                }
            }
            
            // Verificar se já existe conversa ABERTA com esse contato e canal
            $whatsappAccountIdForSearch = ($channel === 'whatsapp') ? $whatsappAccountId : null;
            $existingOpenConversation = \App\Models\Conversation::findOpenByContactAndChannel(
                $contact['id'], 
                $channel, 
                $whatsappAccountIdForSearch
            );
            
            // Se existe conversa ABERTA, verificar atribuição
            if ($existingOpenConversation) {
                $existingAgentId = $existingOpenConversation['agent_id'] ?? null;
                $existingAgentName = $existingOpenConversation['agent_name'] ?? null;
                
                // Verificar se está atribuída a agente de IA (não considerar IA como agente)
                $isAIAssigned = false;
                try {
                    $aiConversation = \App\Models\AIConversation::getByConversationId($existingOpenConversation['id']);
                    if ($aiConversation && $aiConversation['status'] === 'active') {
                        $isAIAssigned = true;
                    }
                } catch (\Exception $e) {
                    // Ignorar erro
                }
                
                // Se está atribuída a outro agente humano (não IA e não é o usuário atual)
                if ($existingAgentId && $existingAgentId != $currentUserId && !$isAIAssigned) {
                    if (!$existingAgentName) {
                        $existingAgent = \App\Models\User::find($existingAgentId);
                        $existingAgentName = $existingAgent ? $existingAgent['name'] : 'Outro agente';
                    }
                    
                    // Retornar informações completas para o frontend mostrar opções
                    Response::json([
                        'success' => false,
                        'code' => 'conversation_exists_other_agent',
                        'message' => "Já existe uma conversa aberta com este contato atribuída ao agente: {$existingAgentName}",
                        'existing_agent_id' => $existingAgentId,
                        'existing_agent_name' => $existingAgentName,
                        'existing_conversation_id' => $existingOpenConversation['id'],
                        'can_request_participation' => true
                    ], 400);
                    return;
                }
                
                // Se não está atribuída a ninguém
                if (!$existingAgentId) {
                    // Conversa aberta não atribuída - retornar info para o frontend
                    Response::json([
                        'success' => false,
                        'code' => 'conversation_exists_unassigned',
                        'message' => 'Já existe uma conversa aberta com este contato (não atribuída).',
                        'existing_conversation_id' => $existingOpenConversation['id'],
                        'can_view' => true
                    ], 400);
                    return;
                }
                
                // Se está atribuída ao usuário atual ou é IA, usar a conversa existente
                $conversationId = $existingOpenConversation['id'];
                
                // ✅ IMPORTANTE: Atualizar a conta de integração se o usuário selecionou uma diferente
                if ($channel === 'whatsapp' && $whatsappAccountId) {
                    $currentAccountId = $existingOpenConversation['integration_account_id'] ?? null;
                    
                    if ($currentAccountId != $whatsappAccountId) {
                        \App\Helpers\Logger::info("newConversation - Atualizando conta de integração: {$currentAccountId} -> {$whatsappAccountId}");
                        \App\Helpers\Logger::unificacao("[CONVERSA] newConversation: Atualizando integration_account_id da conversa #{$conversationId}: {$currentAccountId} → {$whatsappAccountId}");
                        
                        \App\Models\Conversation::update($conversationId, [
                            'integration_account_id' => $whatsappAccountId
                        ]);
                    }
                }
            } else {
                // ✅ NOVO: Não existe conversa ABERTA - permitir criar nova
                // (mesmo que existam conversas fechadas)
                // Criar nova conversa
                $conversationData = [
                    'contact_id' => $contact['id'],
                    'channel' => $channel,
                    'agent_id' => $currentUserId
                ];
                
                if ($funnelId) {
                    $conversationData['funnel_id'] = $funnelId;
                }
                if ($stageId) {
                    $conversationData['stage_id'] = $stageId;
                }
                
                // Adicionar integration_account_id se canal for WhatsApp
                if ($channel === 'whatsapp' && $whatsappAccountId) {
                    $conversationData['integration_account_id'] = $whatsappAccountId;
                    \App\Helpers\Logger::info("newConversation - Criando conversa com integration_account_id=" . $whatsappAccountId);
                    \App\Helpers\Logger::unificacao("[CONVERSA] newConversation: Criando nova conversa com integration_account_id={$whatsappAccountId}, contato={$contact['id']}, canal={$channel}");
                }
                
                // Criar sem executar automações (manual)
                $conversation = \App\Services\ConversationService::create($conversationData, false);
                
                $conversationId = $conversation['id'];
                
                // ✅ REGISTRAR no log de rate limit (apenas para conversas NOVAS)
                if ($channel === 'whatsapp' && $whatsappAccountId && isset($contact['id'])) {
                    $accountType = isset($accountType) ? $accountType : 'whatsapp';
                    \App\Services\NewConversationRateLimitService::logNewConversation(
                        $whatsappAccountId,
                        $accountType,
                        $contact['id'],
                        $conversationId,
                        $currentUserId
                    );
                }
            }
            
            if ($useTemplate && $whatsappAccountId) {
                $whatsappAccount = $whatsappAccount ?? \App\Models\IntegrationAccount::find($whatsappAccountId);
                $provider = $whatsappAccount['provider'] ?? '';

                $bodyPreview = $templateBodyText ?: $templateName;
                if (!empty($templateParams)) {
                    foreach ($templateParams as $i => $value) {
                        $bodyPreview = str_replace('{{' . ($i + 1) . '}}', $value, $bodyPreview);
                    }
                }

                $displayContent = $bodyPreview;
                $headerText = trim($data['header_text'] ?? '');
                $footerText = trim($data['footer_text'] ?? '');
                $buttons = $data['buttons'] ?? [];

                if (!empty($headerText)) {
                    $displayContent = "*{$headerText}*\n\n" . $displayContent;
                }
                if (!empty($footerText)) {
                    $displayContent .= "\n\n_{$footerText}_";
                }
                if (!empty($buttons) && is_array($buttons)) {
                    $displayContent .= "\n";
                    foreach ($buttons as $btn) {
                        $btnType = strtoupper($btn['type'] ?? 'QUICK_REPLY');
                        $btnText = $btn['text'] ?? '';
                        $emoji = $btnType === 'URL' ? '🔗' : ($btnType === 'PHONE_NUMBER' ? '📞' : '🔘');
                        $displayContent .= "\n{$emoji} {$btnText}";
                    }
                }

                $messageData = [
                    'conversation_id' => $conversationId,
                    'sender_id' => $currentUserId ?? 0,
                    'sender_type' => 'agent',
                    'content' => $displayContent,
                    'message_type' => 'text',
                    'status' => 'pending',
                ];
                $messageId = \App\Models\Message::createMessage($messageData);

                if ($provider === 'notificame') {
                    $result = \App\Services\NotificameService::sendTemplate(
                        $whatsappAccountId, $fullPhone, $templateName, $templateParams, $templateLanguage
                    );
                } else {
                    $result = ['success' => false, 'error' => 'Provider não suporta templates neste fluxo'];
                }

                if ($result && ($result['success'] ?? false)) {
                    \App\Models\Message::update($messageId, [
                        'external_id' => $result['message_id'] ?? null,
                        'status' => 'sent',
                    ]);
                } else {
                    \App\Models\Message::update($messageId, [
                        'status' => 'failed',
                        'error_message' => $result['error'] ?? 'Erro ao enviar template',
                    ]);
                }
            } else {
                $messageId = \App\Services\ConversationService::sendMessage(
                    $conversationId,
                    $message,
                    'agent',
                    $currentUserId
                );
            }
            
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
        // Limpar buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        try {
            $currentUserId = \App\Helpers\Auth::id();
            
            // Verificar se conversa existe
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                exit;
            }
            
            // Permissões: aceitar assign.* ou edit.*
            $hasAssignAll = Permission::can('conversations.assign.all') || Permission::can('conversations.edit.all');
            $hasAssignOwn = Permission::can('conversations.assign.own') || Permission::can('conversations.edit.own');
            
            // Se tem .assign/.edit all, pode atribuir qualquer conversa
            if (!$hasAssignAll) {
                // Se tem .assign/.edit own, pode atribuir conversas sem dono ou atribuídas a si mesmo
                if (!$hasAssignOwn) {
                    Response::json([
                        'success' => false,
                        'message' => 'Sem permissão para atribuir conversas'
                    ], 403);
                    exit;
                }
                
                $currentAssignedTo = $conversation['assigned_to'] ?? null;
                if ($currentAssignedTo !== null && (int)$currentAssignedTo !== $currentUserId) {
                    Response::json([
                        'success' => false,
                        'message' => 'Você só pode atribuir conversas não atribuídas ou atribuídas a você'
                    ], 403);
                    exit;
                }
            }
            
            // Ler dados (JSON ou form-data)
            $agentIdRaw = \App\Helpers\Request::post('agent_id');
            if ($agentIdRaw === null || $agentIdRaw === '') {
                Response::json([
                    'success' => false,
                    'message' => 'Agente não informado'
                ], 400);
                exit;
            }
            $agentId = (int) $agentIdRaw;
            
            // Se for 0, remover atribuição
            if ($agentId === 0) {
                $conversation = ConversationService::unassignAgent($id);
                Response::json([
                    'success' => true,
                    'message' => 'Conversa deixada sem atribuição',
                    'conversation' => $conversation
                ]);
                exit;
            }

            // Verificar se agente existe
            $agent = User::find($agentId);
            if (!$agent) {
                Response::json([
                    'success' => false,
                    'message' => 'Agente não encontrado'
                ], 404);
                exit;
            }

            // Atribuir forçadamente (ignora limites) quando é atribuição manual
            $conversation = ConversationService::assignToAgent($id, $agentId, true);
            
            // Processar nota interna para o próximo agente (se fornecida)
            $internalNote = \App\Helpers\Request::post('internal_note');
            if (!empty($internalNote)) {
                try {
                    // Criar mensagem de nota interna
                    $currentUser = \App\Helpers\Auth::user();
                    $agentName = $currentUser['name'] ?? 'Agente';
                    $targetAgent = User::find($agentId);
                    $targetAgentName = $targetAgent['name'] ?? 'Agente';
                    
                    // Formatar a nota com contexto
                    $noteContent = "📋 **Nota de transferência de {$agentName} para {$targetAgentName}:**\n\n{$internalNote}";
                    
                    \App\Models\Message::createMessage([
                        'conversation_id' => $id,
                        'sender_type' => 'agent',
                        'sender_id' => $currentUserId,
                        'content' => $noteContent,
                        'message_type' => 'note', // Nota interna
                        'status' => 'sent'
                    ]);
                    
                    \App\Helpers\Logger::info("Nota interna criada na atribuição: conv={$id}, de={$currentUserId}, para={$agentId}");
                } catch (\Exception $noteError) {
                    \App\Helpers\Logger::error("Erro ao criar nota interna na atribuição: " . $noteError->getMessage());
                    // Não interrompe a atribuição se a nota falhar
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Conversa atribuída com sucesso',
                'conversation' => $conversation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atribuir conversa: ' . $e->getMessage()
            ], 500);
        }
        exit;
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
            // ✅ CORRIGIDO: Verificar permissão correta
            if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all')) {
                throw new \Exception('Você não tem permissão para visualizar conversas');
            }
            
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
     * Reagir a uma mensagem
     */
    public function reactToMessage(int $messageId): void
    {
        Permission::abortIfCannot('conversations.send');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $emoji = $input['emoji'] ?? null;
            
            if (empty($emoji)) {
                Response::json(['success' => false, 'message' => 'Emoji não informado']);
                return;
            }
            
            // Buscar mensagem
            $message = \App\Models\Message::find($messageId);
            if (!$message) {
                Response::json(['success' => false, 'message' => 'Mensagem não encontrada']);
                return;
            }
            
            // Decodificar reações existentes
            $reactions = [];
            if (!empty($message['reactions'])) {
                $reactions = is_string($message['reactions']) 
                    ? json_decode($message['reactions'], true) ?? [] 
                    : $message['reactions'];
            }
            
            $agentId = \App\Helpers\Auth::id();
            $agent = \App\Models\User::find($agentId);
            $agentName = $agent['name'] ?? 'Agente';
            
            // Verificar se o agente já reagiu com o mesmo emoji (toggle: remover)
            $existingIndex = null;
            foreach ($reactions as $idx => $r) {
                if (($r['sender_id'] ?? null) == $agentId && ($r['from'] ?? '') === 'agent') {
                    if (($r['emoji'] ?? '') === $emoji) {
                        $existingIndex = $idx;
                    }
                    break;
                }
            }
            
            if ($existingIndex !== null) {
                // Remover reação (toggle off)
                array_splice($reactions, $existingIndex, 1);
            } else {
                // Remover reação anterior do mesmo agente (só permite 1 por pessoa)
                $reactions = array_values(array_filter($reactions, function($r) use ($agentId) {
                    return !(($r['sender_id'] ?? null) == $agentId && ($r['from'] ?? '') === 'agent');
                }));
                
                // Adicionar nova reação
                $reactions[] = [
                    'emoji' => $emoji,
                    'from' => 'agent',
                    'sender_id' => $agentId,
                    'sender_name' => $agentName,
                    'timestamp' => time()
                ];
            }
            
            // Salvar
            \App\Helpers\Database::execute(
                "UPDATE messages SET reactions = ? WHERE id = ?",
                [json_encode($reactions, JSON_UNESCAPED_UNICODE), $messageId]
            );
            
            // Tentar enviar reação ao WhatsApp (best effort - chega como reply com emoji)
            try {
                $conversation = \App\Models\Conversation::find($message['conversation_id']);
                if ($conversation && $conversation['channel'] === 'whatsapp' && !empty($message['external_id'])) {
                    $accountId = $conversation['integration_account_id'] ?? null;
                    $contact = \App\Models\Contact::find($conversation['contact_id']);
                    
                    if ($accountId && $contact) {
                        $phone = $contact['phone'] ?? '';
                        $account = \App\Models\IntegrationAccount::find($accountId);
                        
                        if ($account && !empty($account['quepasa_token'])) {
                            $apiUrl = rtrim($account['api_url'], '/');
                            $chatId = $contact['whatsapp_id'] ?? ($phone . '@s.whatsapp.net');
                            
                            // Enviar reação via Quepasa API
                            $payload = [
                                'chatid' => $chatId,
                                'text' => $existingIndex !== null ? '' : $emoji,
                                'inreply' => $message['external_id'],
                            ];
                            
                            $ch = curl_init("{$apiUrl}/send");
                            curl_setopt_array($ch, [
                                CURLOPT_POST => true,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_TIMEOUT => 10,
                                CURLOPT_HTTPHEADER => [
                                    'Accept: application/json',
                                    'Content-Type: application/json',
                                    'X-QUEPASA-TOKEN: ' . $account['quepasa_token'],
                                    'X-QUEPASA-TRACKID: ' . ($account['quepasa_trackid'] ?? $account['name'] ?? ''),
                                    'X-QUEPASA-CHATID: ' . $chatId
                                ],
                                CURLOPT_POSTFIELDS => json_encode($payload),
                                CURLOPT_SSL_VERIFYPEER => false
                            ]);
                            
                            $result = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            
                            \App\Helpers\Logger::info("Reaction sent to WhatsApp: messageId={$messageId}, emoji={$emoji}, httpCode={$httpCode}");
                        }
                    }
                }
            } catch (\Exception $e) {
                // Não falhar se o envio ao WhatsApp não funcionar
                \App\Helpers\Logger::info("Reaction WhatsApp send failed (non-critical): " . $e->getMessage());
            }
            
            Response::json([
                'success' => true,
                'reactions' => $reactions
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()]);
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

            // AUTO-ATRIBUIÇÃO: Se conversa não está atribuída e agente está enviando mensagem (não nota)
            // então atribuir automaticamente ao agente
            // ✅ CORREÇÃO: Usar 'agent_id' ao invés de 'assigned_to'
            $assignedTo = $conversation['agent_id'] ?? null;
            $isUnassigned = ($assignedTo === null || $assignedTo === '' || $assignedTo === 0 || $assignedTo === '0');
            if (!$isNote && $isUnassigned) {
                try {
                    // Verificar se o usuário atual é um agente (não é contato/sistema/IA)
                    $user = \App\Models\User::find($userId);
                    if ($user && !empty($user['id'])) {
                        // Atribuir a conversa ao agente que está respondendo
                        ConversationService::assignToAgent($id, $userId, true);
                        
                        // Atualizar a variável local para refletir a atribuição
                        $conversation['agent_id'] = $userId;
                        
                        error_log("[AUTO-ASSIGN] Conversa #{$id} atribuída automaticamente ao agente #{$userId}");
                    }
                } catch (\Exception $e) {
                    // Não bloquear o envio da mensagem se a atribuição falhar
                    error_log("[AUTO-ASSIGN] Falha ao atribuir conversa #{$id} automaticamente: " . $e->getMessage());
                }
            }

            // Determinar tipo de mensagem
            $messageType = $isNote ? 'note' : null;
            
            // Verificar janela de 24h para Cloud API (somente mensagens, não notas)
            if (!$isNote && !($data['skip_window_check'] ?? false)) {
                $iaId = $conversation['integration_account_id'] ?? null;
                if (!empty($conversation['is_merged']) && !empty($conversation['last_customer_account_id'])) {
                    $iaId = (int) $conversation['last_customer_account_id'];
                }
                if ($iaId) {
                    $ia = \App\Models\IntegrationAccount::find($iaId);
                    if ($ia && in_array($ia['provider'] ?? '', ['meta_cloud', 'meta_coex'])) {
                        $viaId = !empty($conversation['is_merged']) ? $iaId : null;
                        if (!\App\Services\WhatsAppCloudService::isWithin24hWindow($id, $viaId)) {
                            Response::json([
                                'success' => false,
                                'outside_24h_window' => true,
                                'message' => 'Fora da janela de 24 horas. Use um template aprovado para iniciar a conversa.',
                            ], 200);
                            return;
                        }
                    }
                }
            }

            // Obter quoted_message_id se houver
            $quotedMessageId = $data['quoted_message_id'] ?? null;
            
            // deferIntegrationSend=true: salva no banco, enfileira envio WhatsApp para background
            $messageId = ConversationService::sendMessage(
                $id, $content, 'agent', $userId, $attachments, $messageType, $quotedMessageId,
                null, null, false, true
            );
            
            if ($messageId) {
                $messages = \App\Models\Message::getMessagesWithSenderDetails($id);
                $createdMessage = null;
                foreach ($messages as $msg) {
                    if ($msg['id'] == $messageId) {
                        $createdMessage = $msg;
                        break;
                    }
                }
                
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
                        'quoted_message_id' => $createdMessage['quoted_message_id'] ?? null,
                        'quoted_sender_name' => $createdMessage['quoted_sender_name'] ?? null,
                        'quoted_text' => $createdMessage['quoted_text'] ?? null,
                        'status' => $createdMessage['status'] ?? 'pending',
                        'delivered_at' => $createdMessage['delivered_at'] ?? null,
                        'read_at' => $createdMessage['read_at'] ?? null,
                        'error_message' => $createdMessage['error_message'] ?? null,
                        'message_type' => $createdMessage['message_type'] ?? null
                    ];
                }
                
                $responseData = json_encode([
                    'success' => true,
                    'message' => $messageData,
                    'message_id' => $messageId
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                // Garantir que o script continue mesmo após desconexão do cliente (Apache mod_php)
                ignore_user_abort(true);
                
                // Enviar resposta HTTP imediatamente sem chamar exit()
                while (ob_get_level() > 0) { ob_end_clean(); }
                http_response_code(200);
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Length: ' . strlen($responseData));
                header('Connection: close');
                echo $responseData;
                flush();
                
                // Encerrar conexão HTTP e processar envio WhatsApp em background
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                
                ConversationService::processBackgroundTasks();
                exit;
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
        // Limpar qualquer output buffer para garantir resposta JSON limpa
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        \App\Helpers\Logger::info("🔀 moveStage INICIADO: conversationId={$id}, userId=" . \App\Helpers\Auth::id(), 'conversas.log');
        \App\Helpers\Logger::info("🔀 Headers: " . json_encode([
            'X-Requested-With' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set',
            'Accept' => $_SERVER['HTTP_ACCEPT'] ?? 'not set',
            'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        ]), 'conversas.log');
        
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $stageId = Request::post('stage_id');
            
            \App\Helpers\Logger::info("🔀 moveStage stage_id recebido: {$stageId}", 'conversas.log');
            
            if (!$stageId) {
                \App\Helpers\Logger::error("❌ moveStage: stage_id não fornecido", 'conversas.log');
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
            \App\Helpers\Logger::info("🔀 moveStage: userId={$userId}, conversationId={$id}, stageId={$stageId}", 'conversas.log');
            \App\Services\FunnelService::moveConversation($id, $stageId, $userId);
            
            Response::json(['success' => true, 'message' => 'Conversa movida com sucesso']);
        } catch (\Exception $e) {
            \App\Helpers\Logger::error("❌ moveStage erro: {$e->getMessage()}", 'conversas.log');
            \App\Helpers\Logger::error($e->getTraceAsString(), 'conversas.log');
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
            error_log("[getNotes] conversationId=$id, userId=$userId");
            
            $notes = \App\Services\ConversationNoteService::list($id, $userId);
            
            error_log("[getNotes] Total de notas retornadas: " . count($notes));
            error_log("[getNotes] Notas: " . json_encode($notes));
            
            Response::json([
                'success' => true,
                'notes' => $notes
            ]);
        } catch (\Exception $e) {
            error_log("[getNotes] ERRO: " . $e->getMessage());
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
                        case 'action_button':
                            $events[] = [
                                'type' => 'action_button',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-bolt',
                                'color' => 'primary',
                                'title' => $activity['description'] ?? 'Botão de ação executado',
                                'description' => null,
                                'user_name' => $activity['user_name'] ?? null
                            ];
                            break;
                        case 'action_button_step':
                            $events[] = [
                                'type' => 'action_button_step',
                                'date' => $activity['created_at'],
                                'icon' => 'ki-bolt',
                                'color' => 'info',
                                'title' => $activity['description'] ?? 'Etapa executada',
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
            
            // Buscar chamadas API4Com
            if (class_exists('\App\Models\Api4ComCall')) {
                $calls = \App\Models\Api4ComCall::getByConversationWithAgent($conversationId);
                foreach ($calls as $call) {
                    $statusLabel = \App\Models\Api4ComCall::getStatusLabel($call['status'] ?? 'unknown');
                    $statusColor = \App\Models\Api4ComCall::getStatusColor($call['status'] ?? 'unknown');
                    $duration = \App\Models\Api4ComCall::formatDuration((int)($call['duration'] ?? 0));
                    
                    $description = "Para: {$call['to_number']}";
                    if (($call['duration'] ?? 0) > 0) {
                        $description .= " • Duração: {$duration}";
                    }
                    if (!empty($call['error_message'])) {
                        $description .= " • Erro: {$call['error_message']}";
                    }
                    
                    $events[] = [
                        'type' => 'phone_call',
                        'date' => $call['created_at'],
                        'icon' => 'ki-phone',
                        'color' => $statusColor,
                        'title' => "Ligação: {$statusLabel}",
                        'description' => $description,
                        'user_name' => $call['agent_name'] ?? null,
                        'call_id' => $call['id'],
                        'call_status' => $call['status'],
                        'call_duration' => (int)($call['duration'] ?? 0),
                        'recording_url' => $call['recording_url'] ?? null
                    ];
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
            // Busca de mensagem individual (para atualizar attachment após download)
            if (isset($_GET['message_id'])) {
                $singleMsgId = (int)$_GET['message_id'];
                $msg = \App\Models\Message::find($singleMsgId);
                if ($msg && ($msg['conversation_id'] ?? null) == $id) {
                    if (!empty($msg['attachments']) && is_string($msg['attachments'])) {
                        $msg['attachments'] = json_decode($msg['attachments'], true) ?? [];
                    }
                    Response::json(['success' => true, 'data' => [$msg]]);
                } else {
                    Response::json(['success' => false, 'message' => 'Mensagem não encontrada'], 404);
                }
                return;
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $beforeId = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
            $lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : null;
            
            \App\Helpers\Logger::info("getMessages: id={$id}, limit={$limit}, beforeId=" . ($beforeId ?? 'null') . ", lastMessageId=" . ($lastMessageId ?? 'null'), 'conversas.log');
            
            if ($limit < 1 || $limit > 100) {
                $limit = 50;
            }
            
            $messages = \App\Models\Message::getMessagesWithSenderDetails($id, $limit, null, $beforeId, $lastMessageId);
            
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
            $logContext = $lastMessageId ? "polling (after ID {$lastMessageId})" : ($beforeId ? "paginação (before ID {$beforeId})" : "carregamento inicial");
            if (!empty($messages)) {
                $firstId = $messages[0]['id'] ?? 'null';
                $lastIdx = count($messages) - 1;
                $lastId = $messages[$lastIdx]['id'] ?? 'null';
                $firstAt = $messages[0]['created_at'] ?? 'null';
                $lastAt = $messages[$lastIdx]['created_at'] ?? 'null';
                \App\Helpers\Logger::info("📤 getMessages [{$logContext}]: Retornando " . count($messages) . " msgs | firstId={$firstId} ({$firstAt}) | lastId={$lastId} ({$lastAt})", 'conversas.log');
            } else {
                \App\Helpers\Logger::info("📤 getMessages [{$logContext}]: Nenhuma mensagem nova encontrada", 'conversas.log');
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
     * Obter análise de performance de uma conversa
     */
    public function getPerformance($id): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $conversationId = (int)$id;
            if ($conversationId <= 0) {
                ob_end_clean();
                Response::json(['success' => false, 'message' => 'ID inválido'], 400);
                return;
            }
            
            // Verificar permissão
            if (!Permission::can('conversations.view.own') && !Permission::can('conversations.view.all') && !Permission::can('agent_performance.view.own')) {
                ob_end_clean();
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para visualizar performance'
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
            if (!Permission::can('conversations.view.all') && !Permission::can('agent_performance.view.team')) {
                if (!Permission::canViewConversation($conversation)) {
                    ob_end_clean();
                    Response::json([
                        'success' => false,
                        'message' => 'Você não tem permissão para ver esta conversa'
                    ], 403);
                    return;
                }
            }
            
            // Buscar análise de performance
            $analysis = \App\Models\AgentPerformanceAnalysis::getByConversation($conversationId);
            
            // Se não tem análise, determinar o motivo
            $pendingReason = null;
            if (!$analysis) {
                $settings = \App\Services\ConversationSettingsService::getSettings();
                $perfSettings = $settings['agent_performance_analysis'] ?? [];
                $enabled = $perfSettings['enabled'] ?? false;
                $analyzeOnClose = $perfSettings['analyze_on_close'] ?? true;
                
                if (!$enabled) {
                    $pendingReason = 'Análise de performance desabilitada';
                } elseif ($analyzeOnClose && $conversation['status'] !== 'closed') {
                    $pendingReason = 'Análise será feita quando a conversa for fechada';
                } elseif ($conversation['status'] === 'closed') {
                    $pendingReason = 'Aguardando processamento da análise';
                } else {
                    $pendingReason = 'Conversa em andamento - análise periódica habilitada';
                }
            }
            
            $this->restoreAfterJsonResponse($config);
            
            Response::json([
                'success' => true,
                'analysis' => $analysis,
                'pending_reason' => $pendingReason
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
        // Limpar TODOS os buffers imediatamente
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilitar erros HTML
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        try {
            // Verificar permissão
            if (!Permission::can('conversations.edit.own') && !Permission::can('conversations.edit.all')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para editar conversas'
                ], 403);
                exit;
            }
            
            // Ler dados JSON
            $userId = (int)\App\Helpers\Request::post('user_id');
            
            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'ID do usuário é obrigatório'
                ], 400);
                exit;
            }
            
            // Verificar se conversação existe
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                exit;
            }
            
            // Verificar se usuário existe
            $user = \App\Models\User::find($userId);
            if (!$user) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
                exit;
            }
            
            // Verificar se já é participante
            $isParticipant = \App\Models\ConversationParticipant::isParticipant($id, $userId);
            if ($isParticipant) {
                Response::json([
                    'success' => false,
                    'message' => 'Este usuário já é participante desta conversa'
                ], 400);
                exit;
            }
            
            $addedBy = \App\Helpers\Auth::id();
            $success = \App\Models\ConversationParticipant::addParticipant($id, $userId, $addedBy);
            
            if ($success) {
                // Invalida cache para que o polling traga dados atualizados
                try {
                    \App\Services\ConversationService::invalidateCache($id);
                } catch (\Exception $e) {
                    // Não bloquear em caso de falha de cache
                }
                
                // Registrar no timeline (sem bloquear em caso de erro)
                try {
                    if (class_exists('\App\Services\ActivityService') && method_exists('\App\Services\ActivityService', 'logParticipantAdded')) {
                        \App\Services\ActivityService::logParticipantAdded($id, $userId, $addedBy);
                    }
                } catch (\Exception $e) {
                    // Ignorar erro de log
                }
                
                // Notificar via WebSocket (sem bloquear)
                try {
                    if (class_exists('\App\Helpers\WebSocket') && method_exists('\App\Helpers\WebSocket', 'notifyConversationUpdated')) {
                        $conversationData = \App\Services\ConversationService::getConversation($id);
                        if ($conversationData) {
                            \App\Helpers\WebSocket::notifyConversationUpdated($id, $conversationData);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar erro de notificação
                }
                
                Response::json([
                    'success' => true,
                    'message' => 'Participante adicionado com sucesso'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Erro ao adicionar participante'
                ], 400);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ], 500);
        }
        exit;
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
            
            while (ob_get_level() > 0) { ob_end_clean(); }
            ini_set('display_errors', '0');
            ini_set('html_errors', '0');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            while (ob_get_level() > 0) { ob_end_clean(); }
            ini_set('display_errors', '0');
            ini_set('html_errors', '0');
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit;
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
        // Limpar buffers e desabilitar erros HTML
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        try {
            // Verificar permissão (usar mesma regra de edição)
            if (!Permission::can('conversations.edit.own') && !Permission::can('conversations.edit.all')) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para editar conversas'
                ], 403);
                exit;
            }
            
            $success = \App\Models\ConversationParticipant::removeParticipant($id, $userId);
            
            if ($success) {
                // Registrar no timeline, mas só se o método existir
                try {
                    if (class_exists('\App\Services\ActivityService') && method_exists('\App\Services\ActivityService', 'logParticipantRemoved')) {
                        $removedBy = \App\Helpers\Auth::id();
                        \App\Services\ActivityService::logParticipantRemoved($id, $userId, $removedBy);
                    }
                } catch (\Exception $e) {
                    // Ignorar erro de log
                }
                
                // Invalida cache para que o polling traga dados atualizados
                try {
                    \App\Services\ConversationService::invalidateCache($id);
                } catch (\Exception $e) {
                    // Não bloquear em caso de falha de cache
                }
                
                // Notificar via WebSocket (opcional, não bloqueia)
                try {
                    if (class_exists('\App\Helpers\WebSocket') && method_exists('\App\Helpers\WebSocket', 'notifyConversationUpdated')) {
                        $conversationData = \App\Services\ConversationService::getConversation($id);
                        if ($conversationData) {
                            \App\Helpers\WebSocket::notifyConversationUpdated($id, $conversationData);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar erro
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
            Response::json([
                'success' => false,
                'message' => 'Erro ao remover participante: ' . $e->getMessage()
            ], 500);
        }
        exit;
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
        // Limpar TODOS os buffers antes de fazer qualquer coisa
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilitar erros
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        try {
            $userId = \App\Helpers\Auth::id();
            
            $invites = \App\Services\ConversationMentionService::getPendingInvites($userId);
            $count = \App\Services\ConversationMentionService::countPending($userId);
            
            // Garantir que não há output buffer ativo
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Enviar headers
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            
            // Enviar JSON e sair
            echo json_encode([
                'success' => true,
                'invites' => $invites,
                'count' => $count
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            exit;
        } catch (\Throwable $e) {
            // Limpar buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            
            exit;
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
        // Garantir que não há output antes
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        // Limpar todos os buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
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
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[requestParticipation] Exception: " . $e->getMessage(), 'conversas.log');
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => 'Erro ao solicitar participação: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Aprovar solicitação de participação
     * POST /conversations/requests/{requestId}/approve
     */
    public function approveRequest(int $requestId): void
    {
        // Garantir que não há output antes
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        // Limpar todos os buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
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
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[approveRequest] Exception: " . $e->getMessage(), 'conversas.log');
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => 'Erro ao aprovar solicitação: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recusar solicitação de participação
     * POST /conversations/requests/{requestId}/reject
     */
    public function rejectRequest(int $requestId): void
    {
        // Garantir que não há output antes
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        // Limpar todos os buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
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
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            \App\Helpers\Log::error("[rejectRequest] Exception: " . $e->getMessage(), 'conversas.log');
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => 'Erro ao recusar solicitação: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obter solicitações de participação pendentes que o usuário pode aprovar
     * GET /conversations/requests/pending
     */
    public function getPendingRequests(): void
    {
        // Limpar TODOS os buffers antes de fazer qualquer coisa
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Desabilitar erros
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        try {
            $userId = \App\Helpers\Auth::id();
            
            $requests = \App\Models\ConversationMention::getPendingRequestsToApprove($userId);
            $count = \App\Models\ConversationMention::countPendingRequestsToApprove($userId);
            
            // Garantir que não há output buffer ativo
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Enviar headers
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            
            // Enviar JSON e sair
            echo json_encode([
                'success' => true,
                'requests' => $requests,
                'count' => $count
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            exit;
        } catch (\Throwable $e) {
            // Limpar buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            
            exit;
        }
    }

    /**
     * Obter contadores de convites e solicitações pendentes
     * GET /conversations/invites/counts
     */
    public function getInviteCounts(): void
    {
        // Garantir que não há output antes
        @ini_set('display_errors', '0');
        @error_reporting(0);
        
        // Limpar todos os buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
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
            
            // Limpar buffers novamente
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
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
    
    /**
     * Obter detalhes de SLA de uma conversa específica
     */
    public function getConversationSLA(): void
    {
        $config = $this->prepareJsonResponse();
        
        try {
            $conversationId = (int)($_GET['id'] ?? 0);
            
            if (!$conversationId) {
                $this->restoreAfterJsonResponse($config);
                Response::json(['success' => false, 'message' => 'ID da conversa não fornecido'], 400);
                return;
            }
            
            $conversation = \App\Models\Conversation::find($conversationId);
            if (!$conversation) {
                $this->restoreAfterJsonResponse($config);
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }
            
            // Obter SLA aplicável para esta conversa
            $slaConfig = \App\Models\SLARule::getSLAForConversation($conversation);
            $settings = \App\Services\ConversationSettingsService::getSettings();
            
            // Obter agente atribuído à conversa
            $assignedAgentId = (int)($conversation['agent_id'] ?? 0);
            
            // ========== REGRA: Verificar se cliente respondeu ao bot ==========
            $clientRespondedToBot = $this->hasClientRespondedToBot($conversationId);
            
            // Buscar períodos de atribuição do agente
            $assignmentPeriods = $assignedAgentId > 0 
                ? $this->getAllAgentAssignmentPeriods($conversationId, $assignedAgentId)
                : [];
            
            // Verificar se já houve primeira resposta do agente ATRIBUÍDO
            if ($assignedAgentId > 0) {
                $firstAgentMessage = \App\Helpers\Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent' AND sender_id = ?",
                    [$conversationId, $assignedAgentId]
                );
            } else {
                $firstAgentMessage = \App\Helpers\Database::fetch(
                    "SELECT MIN(created_at) as first_response 
                     FROM messages 
                     WHERE conversation_id = ? AND sender_type = 'agent'",
                    [$conversationId]
                );
            }
            $hasFirstResponse = !empty($firstAgentMessage['first_response']);
            
            // Definir tipo de SLA
            $slaType = $hasFirstResponse ? 'ongoing' : 'first';
            $slaLabel = $hasFirstResponse ? 'Respostas' : '1ª Resposta';
            $slaMinutes = $hasFirstResponse ? $slaConfig['ongoing_response_time'] : $slaConfig['first_response_time'];
            
            $shouldStart = false;
            $elapsedMinutes = 0;
            $startTime = null;
            $isWithinSla = true;
            
            // Se cliente não respondeu ao bot, SLA não conta
            if (!$clientRespondedToBot) {
                $shouldStart = false;
            } elseif ($slaType === 'ongoing') {
                $delayEnabled = ($settings['sla']['message_delay_enabled'] ?? true);
                $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
                
                if (!$delayEnabled) {
                    $delayMinutes = 0;
                }
                
                // Buscar mensagens para calcular SLA considerando período de atribuição
                $messages = \App\Helpers\Database::fetchAll(
                    "SELECT sender_type, sender_id, created_at
                     FROM messages
                     WHERE conversation_id = ?
                     ORDER BY created_at ASC",
                    [$conversationId]
                );
                
                $lastAgentMessage = null;
                $pendingContactMessage = null;
                
                foreach ($messages as $msg) {
                    if ($msg['sender_type'] === 'agent') {
                        // Só considerar mensagens do agente atribuído
                        if ($assignedAgentId > 0 && (int)$msg['sender_id'] !== $assignedAgentId) {
                            continue;
                        }
                        $lastAgentMessage = $msg;
                        $pendingContactMessage = null;
                        
                    } elseif ($msg['sender_type'] === 'contact' && $lastAgentMessage) {
                        // Verificar período de atribuição
                        if (!empty($assignmentPeriods) && !$this->isMessageInAgentPeriod($msg['created_at'], $assignmentPeriods)) {
                            continue;
                        }
                        
                        // Verificar delay
                        $lastAgentTime = new \DateTime($lastAgentMessage['created_at']);
                        $contactTime = new \DateTime($msg['created_at']);
                        $diffMinutes = ($contactTime->getTimestamp() - $lastAgentTime->getTimestamp()) / 60;
                        
                        if ($diffMinutes < $delayMinutes) {
                            continue;
                        }
                        
                        if (!$pendingContactMessage) {
                            $pendingContactMessage = $msg;
                        }
                    }
                }
                
                if ($pendingContactMessage) {
                    $shouldStart = true;
                    $startTime = new \DateTime($pendingContactMessage['created_at']);
                    
                    $now = new \DateTime();
                    if ($conversation['sla_paused_at']) {
                        $now = new \DateTime($conversation['sla_paused_at']);
                    }
                    
                    $elapsedMinutes = \App\Helpers\WorkingHoursCalculator::calculateMinutes($startTime, $now);
                    $elapsedMinutes -= (int)($conversation['sla_paused_duration'] ?? 0);
                    $elapsedMinutes = max(0, $elapsedMinutes);
                    $isWithinSla = $elapsedMinutes < $slaMinutes;
                }
            } else {
                // SLA de 1ª resposta
                $shouldStart = \App\Services\ConversationSettingsService::shouldStartSLACount($conversationId);
                $elapsedMinutes = \App\Services\ConversationSettingsService::getElapsedSLAMinutes($conversationId);
                $startTime = \App\Services\ConversationSettingsService::getSLAStartTime($conversationId);
                $isWithinSla = \App\Services\ConversationSettingsService::checkFirstResponseSLA($conversationId);
            }
            
            // Buscar histórico de mensagens com tempos
            $messages = \App\Helpers\Database::fetchAll(
                "SELECT id, sender_type, ai_agent_id, created_at, content
                 FROM messages 
                 WHERE conversation_id = ? 
                 ORDER BY created_at ASC",
                [$conversationId]
            );
            
            // Calcular timeline do SLA
            $timeline = [];
            $lastAgentMessage = null;
            $settings = \App\Services\ConversationSettingsService::getSettings();
            $delayEnabled = $settings['sla']['message_delay_enabled'] ?? true;
            $delayMinutes = $settings['sla']['message_delay_minutes'] ?? 1;
            if (!$delayEnabled) {
                $delayMinutes = 0;
            }
            
            foreach ($messages as $msg) {
                $time = new \DateTime($msg['created_at']);
                
                if ($msg['sender_type'] === 'agent') {
                    $lastAgentMessage = $time;
                    $timeline[] = [
                        'type' => 'agent_response',
                        'time' => $msg['created_at'],
                        'is_ai' => !empty($msg['ai_agent_id']),
                        'content_preview' => mb_substr($msg['content'], 0, 100)
                    ];
                } elseif ($msg['sender_type'] === 'contact') {
                    $slaActive = false;
                    $minutesSinceAgent = null;
                    
                    if ($lastAgentMessage) {
                        $diff = $time->getTimestamp() - $lastAgentMessage->getTimestamp();
                        $minutesSinceAgent = $diff / 60;
                        $slaActive = $minutesSinceAgent >= $delayMinutes;
                    }
                    
                    $timeline[] = [
                        'type' => 'contact_message',
                        'time' => $msg['created_at'],
                        'sla_active' => $slaActive,
                        'minutes_since_agent' => $minutesSinceAgent,
                        'content_preview' => mb_substr($msg['content'], 0, 100)
                    ];
                }
            }
            
            // Calcular status atual
            $status = 'ok';
            $percentage = 0;
            
            if ($shouldStart && $slaMinutes > 0) {
                $percentage = ($elapsedMinutes / $slaMinutes) * 100;
                
                if (!$isWithinSla) {
                    $status = 'exceeded';
                } elseif ($percentage >= 80) {
                    $status = 'warning';
                }
            }
            
            $this->restoreAfterJsonResponse($config);
            
            Response::json([
                'success' => true,
                'sla' => [
                    'conversation_id' => $conversationId,
                    'status' => $conversation['status'],
                    'sla_rule' => $slaConfig['rule_name'],
                    'first_response_sla' => $slaConfig['first_response_time'],
                    'ongoing_response_sla' => $slaConfig['ongoing_response_time'],
                    'resolution_sla' => $slaConfig['resolution_time'],
                    'sla_type' => $slaType,
                    'sla_label' => $slaLabel,
                    'current_sla_minutes' => $slaMinutes,
                    'should_start' => $shouldStart,
                    'elapsed_minutes' => $elapsedMinutes,
                    'start_time' => $startTime ? $startTime->format('Y-m-d H:i:s') : null,
                    'is_within_sla' => $isWithinSla,
                    'is_paused' => !empty($conversation['sla_paused_at']),
                    'paused_duration' => (int)($conversation['sla_paused_duration'] ?? 0),
                    'warning_sent' => (bool)($conversation['sla_warning_sent'] ?? 0),
                    'reassignment_count' => (int)($conversation['reassignment_count'] ?? 0),
                    'status_indicator' => $status,
                    'percentage' => min(100, round($percentage, 1)),
                    'timeline' => $timeline,
                    'delay_minutes' => $delayMinutes,
                    'delay_enabled' => $delayEnabled
                ]
            ]);
        } catch (\Exception $e) {
            $this->restoreAfterJsonResponse($config);
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // =========================================================================
    // FUNÇÕES AUXILIARES PARA SLA
    // =========================================================================
    
    /**
     * Verificar se cliente respondeu ao bot
     */
    private function hasClientRespondedToBot(int $conversationId): bool
    {
        $lastAgentMessage = \App\Helpers\Database::fetch(
            "SELECT created_at 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'agent'
             ORDER BY created_at DESC 
             LIMIT 1",
            [$conversationId]
        );
        
        if (!$lastAgentMessage) {
            $hasContact = \App\Helpers\Database::fetch(
                "SELECT 1 FROM messages WHERE conversation_id = ? AND sender_type = 'contact' LIMIT 1",
                [$conversationId]
            );
            return (bool)$hasContact;
        }
        
        $clientAfterAgent = \App\Helpers\Database::fetch(
            "SELECT 1 
             FROM messages 
             WHERE conversation_id = ? 
             AND sender_type = 'contact'
             AND created_at > ?
             LIMIT 1",
            [$conversationId, $lastAgentMessage['created_at']]
        );
        
        return (bool)$clientAfterAgent;
    }
    
    /**
     * Obter todos os períodos de atribuição de um agente
     */
    private function getAllAgentAssignmentPeriods(int $conversationId, int $agentId): array
    {
        $allAssignments = \App\Helpers\Database::fetchAll(
            "SELECT agent_id, assigned_at 
             FROM conversation_assignments 
             WHERE conversation_id = ?
             ORDER BY assigned_at ASC",
            [$conversationId]
        );
        
        if (empty($allAssignments)) {
            return [];
        }
        
        $periods = [];
        $currentPeriodStart = null;
        
        foreach ($allAssignments as $assignment) {
            $isTargetAgent = ((int)$assignment['agent_id'] === $agentId);
            
            if ($isTargetAgent && $currentPeriodStart === null) {
                $currentPeriodStart = $assignment['assigned_at'];
            } elseif (!$isTargetAgent && $currentPeriodStart !== null) {
                $periods[] = [
                    'assigned_at' => $currentPeriodStart,
                    'unassigned_at' => $assignment['assigned_at']
                ];
                $currentPeriodStart = null;
            }
        }
        
        if ($currentPeriodStart !== null) {
            $periods[] = [
                'assigned_at' => $currentPeriodStart,
                'unassigned_at' => null
            ];
        }
        
        return $periods;
    }
    
    /**
     * Verificar se mensagem está dentro do período de atribuição
     */
    private function isMessageInAgentPeriod(string $messageTime, array $periods): bool
    {
        $msgTime = strtotime($messageTime);
        
        foreach ($periods as $period) {
            $start = strtotime($period['assigned_at']);
            $end = $period['unassigned_at'] ? strtotime($period['unassigned_at']) : PHP_INT_MAX;
            
            if ($msgTime >= $start && $msgTime <= $end) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar se há outras conversas abertas do mesmo contato (API)
     */
    public function checkOtherConversations(int $id): void
    {
        // ✅ Não requer permissão especial, apenas autenticação (já verificada pelo middleware)
        // Todos os agentes autenticados podem ver se há outras conversas
        
        try {
            $result = \App\Services\ConversationMergeService::checkMultipleConversations($id);
            
            Response::json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Mesclar conversas (API)
     */
    public function mergeConversations(int $id): void
    {
        // Verificar se usuário pode visualizar a conversa de destino
        // Usamos canViewConversation porque mesclar é consolidar conversas do mesmo contato
        // Se o agente pode ver, significa que está atendendo esse contato
        $conversation = Conversation::find($id);
        if (!$conversation) {
            Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
            return;
        }
        
        $userId = Auth::id();
        
        // Verificar se tem alguma permissão de edição de conversas
        $canEdit = Permission::canAny(['conversations.edit.own', 'conversations.edit.department', 'conversations.edit.all']);
        if (!$canEdit) {
            Response::forbidden('Acesso negado - você não tem permissão para mesclar conversas');
            return;
        }
        
        // Verificar se pode visualizar a conversa de destino
        if (!PermissionService::canViewConversation($userId, $conversation)) {
            Response::forbidden('Acesso negado - você não tem acesso a esta conversa');
            return;
        }
        
        try {
            $data = Request::json();
            $sourceIds = $data['source_conversation_ids'] ?? [];
            
            if (empty($sourceIds)) {
                throw new \Exception('Nenhuma conversa para mesclar');
            }
            
            // Verificar se pode visualizar todas as conversas de origem
            // E se não estão atribuídas a outro agente
            foreach ($sourceIds as $sourceId) {
                $sourceConv = Conversation::find($sourceId);
                if (!$sourceConv) {
                    continue;
                }
                
                if (!PermissionService::canViewConversation($userId, $sourceConv)) {
                    throw new \Exception('Você não tem acesso a uma das conversas selecionadas');
                }
                
                // Verificar se a conversa de origem está atribuída a outro agente
                $sourceAgentId = $sourceConv['agent_id'] ?? null;
                if ($sourceAgentId && $sourceAgentId != $userId) {
                    // Buscar nome do agente para mensagem mais clara
                    $agentName = 'outro agente';
                    $agent = User::find($sourceAgentId);
                    if ($agent) {
                        $agentName = $agent['name'];
                    }
                    throw new \Exception("Não é possível mesclar: a conversa está atribuída a {$agentName}. Peça para ele transferir ou finalize a conversa primeiro.");
                }
            }
            
            $result = \App\Services\ConversationMergeService::merge($id, $sourceIds);
            
            Response::json([
                'success' => true,
                'message' => "Conversas mescladas com sucesso! {$result['messages_moved']} mensagens movidas.",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Obter contas vinculadas a uma conversa (API)
     */
    public function getLinkedAccounts(int $id): void
    {
        Permission::abortIfCannot('conversations.view');
        
        try {
            $accounts = \App\Services\ConversationMergeService::getLinkedAccounts($id);
            
            Response::json([
                'success' => true,
                'data' => $accounts
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Alterar conta de integração da conversa (trocar número de envio)
     */
    public function changeAccount(int $id): void
    {
        try {
            $data = Request::json();
            $newAccountId = $data['account_id'] ?? null;
            
            if (empty($newAccountId)) {
                throw new \Exception('ID da conta não informado');
            }
            
            $conversation = Conversation::find($id);
            if (!$conversation) {
                throw new \Exception('Conversa não encontrada');
            }
            
            // Verificar permissão: pode editar se for admin OU se estiver atribuído/participante à conversa
            $currentUserId = \App\Helpers\Auth::id();
            $isAdmin = Permission::isAdmin() || Permission::isSuperAdmin();
            $isAssigned = !empty($conversation['agent_id']) && ((int)$conversation['agent_id'] === (int)$currentUserId);
            
            // Verificar se é participante da conversa
            $isParticipant = false;
            try {
                $participants = \App\Models\ConversationParticipant::getByConversation($id);
                foreach ($participants as $p) {
                    if ((int)$p['user_id'] === (int)$currentUserId) {
                        $isParticipant = true;
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Ignorar erro
            }
            
            $canEdit = Permission::can('conversations.edit') || Permission::can('conversations.edit.all');
            $canEditOwn = Permission::can('conversations.edit.own') && $isAssigned;
            
            \App\Helpers\Logger::info("changeAccount - userId={$currentUserId}, agent_id={$conversation['agent_id']}, isAdmin=" . ($isAdmin ? 'Y' : 'N') . ", isAssigned=" . ($isAssigned ? 'Y' : 'N') . ", isParticipant=" . ($isParticipant ? 'Y' : 'N') . ", canEdit=" . ($canEdit ? 'Y' : 'N'));
            
            if (!$isAdmin && !$isAssigned && !$isParticipant && !$canEdit && !$canEditOwn) {
                Response::json([
                    'success' => false,
                    'message' => 'Você não tem permissão para alterar o número desta conversa. Apenas o agente atribuído, participantes ou administradores podem fazer isso.'
                ], 403);
                return;
            }
            
            // Verificar se a conta existe (integration_accounts unificado)
            $account = \App\Models\IntegrationAccount::find($newAccountId);
            if (!$account) {
                throw new \Exception('Conta não encontrada');
            }
            
            Conversation::update($id, [
                'integration_account_id' => $newAccountId,
                'last_customer_account_id' => $newAccountId
            ]);
            
            $accountPhone = $account['phone_number'] ?? 'Desconhecido';
            $accountName = $account['name'] ?? '';
            
            \App\Helpers\Logger::info("ConversationController::changeAccount - Conversa {$id} alterada para conta {$newAccountId} ({$accountPhone})");
            \App\Helpers\Logger::unificacao("[CONVERSA] changeAccount: Conversa #{$id} - integration_account_id alterado para {$newAccountId} ({$accountName}, phone={$accountPhone})");
            
            Response::json([
                'success' => true,
                'message' => "Número alterado para {$accountPhone}" . ($accountName ? " ({$accountName})" : ""),
                'data' => [
                    'account_id' => $newAccountId,
                    'phone_number' => $accountPhone,
                    'name' => $accountName
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obter status da fila de mídia de uma conversa
     */
    public function getMediaQueue(int $id): void
    {
        try {
            $result = \App\Services\MediaQueueService::getQueueStatus($id);
            Response::json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retentar download de item na fila
     */
    public function retryMediaQueue(int $id): void
    {
        try {
            $item = \App\Models\MediaQueue::find($id);
            if (!$item) {
                Response::json(['success' => false, 'message' => 'Item não encontrado'], 404);
                return;
            }

            \App\Models\MediaQueue::update($id, [
                'status' => 'queued',
                'next_attempt_at' => date('Y-m-d H:i:s'),
                'error_message' => null,
            ]);

            Response::json(['success' => true, 'message' => 'Download reagendado']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancelar download de item na fila
     */
    public function cancelMediaQueue(int $id): void
    {
        try {
            $item = \App\Models\MediaQueue::find($id);
            if (!$item) {
                Response::json(['success' => false, 'message' => 'Item não encontrado'], 404);
                return;
            }

            \App\Models\MediaQueue::update($id, [
                'status' => 'cancelled',
            ]);

            Response::json(['success' => true, 'message' => 'Download cancelado']);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Estatísticas globais da fila de mídia
     */
    public function getMediaQueueStats(): void
    {
        try {
            $stats = \App\Models\MediaQueue::getStats();
            Response::json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verificar janela de 24h e retornar templates WhatsApp aprovados
     */
    public function checkCloudWindow(int $id): void
    {
        try {
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }

            $integrationAccountId = $conversation['integration_account_id'] ?? null;

            if (!empty($conversation['is_merged']) && !empty($conversation['last_customer_account_id'])) {
                $integrationAccountId = (int) $conversation['last_customer_account_id'];
            }

            if (!$integrationAccountId) {
                Response::json([
                    'success' => true,
                    'is_cloud_api' => false,
                    'within_24h' => true,
                    'templates' => [],
                ]);
                return;
            }

            $account = \App\Models\IntegrationAccount::find($integrationAccountId);
            $provider = $account['provider'] ?? '';
            $channel = $conversation['channel'] ?? '';

            $isCloudApi = in_array($provider, ['meta_cloud', 'meta_coex']);
            $isNotificameWhatsApp = ($provider === 'notificame' && in_array($channel, ['whatsapp', 'whatsapp_official']));

            if (!$account || (!$isCloudApi && !$isNotificameWhatsApp)) {
                Response::json([
                    'success' => true,
                    'is_cloud_api' => false,
                    'within_24h' => true,
                    'templates' => [],
                ]);
                return;
            }

            $viaAccountId = !empty($conversation['is_merged']) ? $integrationAccountId : null;
            $within24h = \App\Services\WhatsAppCloudService::isWithin24hWindow($id, $viaAccountId);

            $templates = [];
            if (!$within24h) {
                if ($isNotificameWhatsApp) {
                    try {
                        $notificameTemplates = \App\Services\NotificameService::listTemplates($integrationAccountId);
                        foreach ($notificameTemplates as $tpl) {
                            $status = $tpl['status'] ?? $tpl['qualityScore'] ?? '';
                            if (strtolower($status) !== 'approved' && strtolower($status) !== 'approved_') {
                                if (!in_array(strtolower($status), ['approved', 'approved_', ''])) {
                                    continue;
                                }
                            }
                            $comps = self::extractNotificameTemplateComponents($tpl);
                            $templates[] = [
                                'id' => $tpl['id'] ?? $tpl['name'] ?? '',
                                'name' => $tpl['name'] ?? '',
                                'display_name' => $tpl['name'] ?? '',
                                'language' => $tpl['language'] ?? 'pt_BR',
                                'category' => $tpl['category'] ?? '',
                                'body_text' => self::extractNotificameTemplateBody($tpl),
                                'header_type' => !empty($comps['header_text']) ? 'TEXT' : '',
                                'header_text' => $comps['header_text'],
                                'footer_text' => $comps['footer_text'],
                                'buttons' => $comps['buttons'],
                                'variables_count' => 0,
                                'source' => 'notificame',
                            ];
                        }
                    } catch (\Exception $e) {
                        \App\Helpers\Logger::error("Erro ao listar templates Notificame: " . $e->getMessage());
                    }
                } else {
                    $phone = \App\Models\WhatsAppPhone::findByIntegrationAccount($integrationAccountId);
                    $wabaId = $phone['waba_id'] ?? ($account['account_id'] ?? null);
                    if ($wabaId) {
                        $approved = \App\Models\WhatsAppTemplate::getApproved($wabaId);
                        foreach ($approved as $tpl) {
                            $templates[] = [
                                'id' => $tpl['id'],
                                'name' => $tpl['name'],
                                'display_name' => $tpl['display_name'] ?: $tpl['name'],
                                'language' => $tpl['language'],
                                'category' => $tpl['category'],
                                'body_text' => $tpl['body_text'],
                                'header_type' => $tpl['header_type'],
                                'header_text' => $tpl['header_text'],
                                'footer_text' => $tpl['footer_text'],
                                'buttons' => \App\Models\WhatsAppTemplate::getButtons($tpl),
                                'variables_count' => \App\Models\WhatsAppTemplate::countVariables($tpl),
                                'source' => 'meta',
                            ];
                        }
                    }
                }
            }

            if ($isNotificameWhatsApp && !empty($templates)) {
                $userId = Auth::id();
                $isSuperAdmin = PermissionService::isSuperAdmin($userId);
                if (!$isSuperAdmin) {
                    $permStmt = \App\Helpers\Database::getInstance()->prepare(
                        "SELECT template_name, allowed_users FROM notificame_template_permissions WHERE integration_account_id = ?"
                    );
                    $permStmt->execute([$integrationAccountId]);
                    $permRows = $permStmt->fetchAll(\PDO::FETCH_ASSOC);
                    $permMap = [];
                    foreach ($permRows as $row) {
                        $allowed = json_decode($row['allowed_users'], true);
                        if (!empty($allowed) && is_array($allowed)) {
                            $permMap[$row['template_name']] = $allowed;
                        }
                    }
                    if (!empty($permMap)) {
                        $templates = array_values(array_filter($templates, function ($tpl) use ($permMap, $userId) {
                            $name = $tpl['name'] ?? '';
                            if (!isset($permMap[$name])) {
                                return true;
                            }
                            return in_array((int)$userId, $permMap[$name], true);
                        }));
                    }
                }
            }

            $windowInfo = null;
            $lastMsg = \App\Helpers\Database::fetch(
                "SELECT created_at FROM messages WHERE conversation_id = ? AND sender_type = 'contact' ORDER BY created_at DESC LIMIT 1",
                [$id]
            );
            if ($lastMsg && !empty($lastMsg['created_at'])) {
                $expiresAt = strtotime($lastMsg['created_at']) + (24 * 60 * 60);
                $remainingSeconds = max(0, $expiresAt - time());
                $windowInfo = [
                    'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                    'expires_at_iso' => date('c', $expiresAt),
                    'remaining_minutes' => max(0, round($remainingSeconds / 60)),
                    'remaining_seconds' => $remainingSeconds,
                ];
            }

            Response::json([
                'success' => true,
                'is_cloud_api' => true,
                'within_24h' => $within24h,
                'window_info' => $windowInfo,
                'templates' => $templates,
                'account_name' => $account['name'] ?? '',
                'provider' => $provider,
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private static function extractNotificameTemplateBody(array $tpl): string
    {
        $components = $tpl['components'] ?? [];
        foreach ($components as $comp) {
            if (($comp['type'] ?? '') === 'BODY') {
                return $comp['text'] ?? '';
            }
        }
        return $tpl['body'] ?? $tpl['text'] ?? $tpl['name'] ?? '';
    }

    private static function extractNotificameTemplateComponents(array $tpl): array
    {
        $header = '';
        $footer = '';
        $buttons = [];
        $components = $tpl['components'] ?? [];

        foreach ($components as $comp) {
            $type = strtoupper($comp['type'] ?? '');
            if ($type === 'HEADER') {
                $header = $comp['text'] ?? '';
            } elseif ($type === 'FOOTER') {
                $footer = $comp['text'] ?? '';
            } elseif ($type === 'BUTTONS') {
                foreach ($comp['buttons'] ?? [] as $btn) {
                    $buttons[] = [
                        'type' => $btn['type'] ?? 'QUICK_REPLY',
                        'text' => $btn['text'] ?? '',
                        'url' => $btn['url'] ?? null,
                        'phone_number' => $btn['phone_number'] ?? null,
                    ];
                }
            }
        }

        return ['header_text' => $header, 'footer_text' => $footer, 'buttons' => $buttons];
    }

    /**
     * Enviar template WhatsApp Cloud API em uma conversa
     */
    public function sendCloudTemplate(int $id): void
    {
        try {
            $conversation = ConversationService::getConversation($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }

            if (!Permission::canSendMessage($conversation)) {
                Response::json(['success' => false, 'message' => 'Sem permissão'], 403);
                return;
            }

            $data = \App\Helpers\Request::post();
            $templateId = $data['template_id'] ?? null;
            $templateName = $data['template_name'] ?? null;
            $parameters = $data['parameters'] ?? [];
            $source = $data['source'] ?? 'meta';

            $integrationAccountId = $conversation['integration_account_id'] ?? null;
            if (!empty($conversation['is_merged']) && !empty($conversation['last_customer_account_id'])) {
                $integrationAccountId = (int) $conversation['last_customer_account_id'];
            }

            if (!$integrationAccountId) {
                throw new \Exception('Conversa sem integração vinculada');
            }

            $account = \App\Models\IntegrationAccount::find($integrationAccountId);
            if (!$account) {
                throw new \Exception('Conta de integração não encontrada');
            }

            $provider = $account['provider'] ?? '';
            $isNotificame = ($provider === 'notificame');
            $isMetaCloud = in_array($provider, ['meta_cloud', 'meta_coex']);

            if (!$isNotificame && !$isMetaCloud) {
                throw new \Exception('Integração não suporta envio de templates');
            }

            $contact = \App\Models\Contact::find($conversation['contact_id']);
            if (!$contact || empty($contact['phone'])) {
                throw new \Exception('Contato sem número de telefone');
            }

            $userId = \App\Helpers\Auth::id();

            $assignedTo = $conversation['agent_id'] ?? null;
            if (($assignedTo === null || $assignedTo === '' || $assignedTo === 0) && $userId) {
                try {
                    ConversationService::assignToAgent($id, $userId, true);
                } catch (\Exception $e) {
                    // Não bloquear envio
                }
            }

            $to = preg_replace('/[^0-9]/', '', $contact['phone']);

            if ($isNotificame) {
                if (empty($templateName)) {
                    throw new \Exception('Nome do template é obrigatório para Notificame');
                }

                $bodyText = $data['body_text'] ?? $templateName;
                if (!empty($parameters)) {
                    foreach ($parameters as $i => $value) {
                        $bodyText = str_replace('{{' . ($i + 1) . '}}', $value, $bodyText);
                    }
                }

                $displayContent = $bodyText;
                $headerText = $data['header_text'] ?? '';
                $footerText = $data['footer_text'] ?? '';
                $buttons = $data['buttons'] ?? [];

                if (!empty($headerText)) {
                    $displayContent = "*{$headerText}*\n\n" . $displayContent;
                }
                if (!empty($footerText)) {
                    $displayContent .= "\n\n_{$footerText}_";
                }
                if (!empty($buttons) && is_array($buttons)) {
                    $displayContent .= "\n";
                    foreach ($buttons as $btn) {
                        $btnType = strtoupper($btn['type'] ?? 'QUICK_REPLY');
                        $btnText = $btn['text'] ?? '';
                        $emoji = $btnType === 'URL' ? '🔗' : ($btnType === 'PHONE_NUMBER' ? '📞' : '🔘');
                        $displayContent .= "\n{$emoji} {$btnText}";
                    }
                }

                $messageData = [
                    'conversation_id' => $id,
                    'sender_id' => $userId ?? 0,
                    'sender_type' => 'agent',
                    'content' => $displayContent,
                    'message_type' => 'text',
                    'status' => 'pending',
                ];
                $messageId = \App\Models\Message::createMessage($messageData);

                $language = $data['language'] ?? 'pt_BR';
                $result = \App\Services\NotificameService::sendTemplate(
                    $integrationAccountId, $to, $templateName, $parameters, $language
                );

                if ($result && ($result['success'] ?? false)) {
                    \App\Models\Message::update($messageId, [
                        'external_id' => $result['message_id'] ?? null,
                        'status' => 'sent',
                    ]);
                } else {
                    \App\Models\Message::update($messageId, [
                        'status' => 'failed',
                        'error_message' => $result['error'] ?? 'Erro ao enviar template',
                    ]);
                }

                $tplDisplayName = $templateName;
            } else {
                $templateId = (int) ($templateId ?? 0);
                if (!$templateId) {
                    throw new \Exception('Template não informado');
                }

                $template = \App\Models\WhatsAppTemplate::find($templateId);
                if (!$template || $template['status'] !== 'APPROVED') {
                    throw new \Exception('Template não encontrado ou não aprovado');
                }

                $bodyText = $template['body_text'] ?? '';
                if (!empty($parameters)) {
                    foreach ($parameters as $i => $value) {
                        $bodyText = str_replace('{{' . ($i + 1) . '}}', $value, $bodyText);
                    }
                }

                $displayContent = $bodyText;
                if (!empty($template['header_text'])) {
                    $displayContent = "*{$template['header_text']}*\n\n" . $displayContent;
                }
                if (!empty($template['footer_text'])) {
                    $displayContent .= "\n\n_{$template['footer_text']}_";
                }

                $messageData = [
                    'conversation_id' => $id,
                    'sender_id' => $userId ?? 0,
                    'sender_type' => 'agent',
                    'content' => $displayContent,
                    'message_type' => 'text',
                    'status' => 'pending',
                ];
                $messageId = \App\Models\Message::createMessage($messageData);

                $options = [
                    'template_name' => $template['name'],
                    'template_language' => $template['language'],
                    'template_parameters' => $parameters,
                ];

                $service = new \App\Services\WhatsAppCloudApiService();
                $result = $service->sendMessage($integrationAccountId, $to, '', $options);

                if ($result && ($result['success'] ?? false)) {
                    \App\Models\Message::update($messageId, [
                        'external_id' => $result['message_id'] ?? null,
                        'status' => 'sent',
                    ]);
                    \App\Models\WhatsAppTemplate::incrementSent($templateId);
                } else {
                    \App\Models\Message::update($messageId, ['status' => 'failed']);
                    \App\Models\WhatsAppTemplate::incrementFailed($templateId);
                }

                $tplDisplayName = $template['display_name'] ?: $template['name'];
            }

            \App\Models\Conversation::update($id, [
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => 'open',
            ]);

            $messages = \App\Models\Message::getMessagesWithSenderDetails($id);
            $createdMessage = null;
            foreach ($messages as $msg) {
                if ($msg['id'] == $messageId) {
                    $createdMessage = $msg;
                    break;
                }
            }

            $messageOut = null;
            if ($createdMessage) {
                $messageOut = [
                    'id' => $createdMessage['id'],
                    'content' => $createdMessage['content'],
                    'direction' => 'outgoing',
                    'type' => 'message',
                    'created_at' => $createdMessage['created_at'],
                    'sender_name' => $createdMessage['sender_name'] ?? 'Você',
                    'sender_type' => $createdMessage['sender_type'],
                    'status' => $createdMessage['status'] ?? 'sent',
                    'message_type' => 'template',
                ];
            }

            Response::json([
                'success' => true,
                'message' => $messageOut,
                'message_id' => $messageId,
                'template_name' => $tplDisplayName ?? '',
            ]);
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna estatísticas de rate limit de mídia para a conversa atual.
     * GET /conversations/{id}/media-rate-stats
     */
    public function getMediaRateStats(int $id): void
    {
        try {
            $userId = \App\Helpers\Auth::id();
            $conversation = \App\Models\Conversation::find($id);
            if (!$conversation) {
                Response::json(['success' => false, 'message' => 'Conversa não encontrada'], 404);
                return;
            }

            // Resolver conta + provider
            $accountId = (int)($conversation['integration_account_id'] ?? 0);
            $provider = null;
            if ($accountId) {
                $integ = \App\Models\IntegrationAccount::find($accountId);
                $provider = $integ['provider'] ?? null;
            } elseif (!empty($conversation['whatsapp_account_id'])) {
                $accountId = (int)$conversation['whatsapp_account_id'];
                $provider = 'quepasa';
            }

            if (!$accountId || !\App\Services\MediaRateLimitService::appliesToProvider($provider)) {
                // Não aplica rate limit para esse provider (Notificame, etc) — UI esconde o badge
                Response::json([
                    'success' => true,
                    'applies' => false,
                    'provider' => $provider,
                ]);
                return;
            }

            $stats = \App\Services\MediaRateLimitService::getStats($accountId, $id, (int)$userId);

            Response::json([
                'success' => true,
                'applies' => true,
                'provider' => $provider,
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

