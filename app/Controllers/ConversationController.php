<?php
/**
 * Controller de Conversas
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Permission;
use App\Services\ConversationService;
use App\Models\User;

class ConversationController
{
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
            'status' => $_GET['status'] ?? null,
            'channel' => $_GET['channel'] ?? null,
            'channels' => isset($_GET['channels']) && is_array($_GET['channels']) ? $_GET['channels'] : (!empty($_GET['channel']) ? [$_GET['channel']] : null),
            'search' => $_GET['search'] ?? null,
            'agent_id' => $_GET['agent_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
            'tag_id' => $_GET['tag_id'] ?? null,
            'tag_ids' => isset($_GET['tag_ids']) && is_array($_GET['tag_ids']) ? array_map('intval', $_GET['tag_ids']) : (!empty($_GET['tag_id']) ? [(int)$_GET['tag_id']] : null),
            'whatsapp_account_id' => $_GET['whatsapp_account_id'] ?? null,
            'whatsapp_account_ids' => isset($_GET['whatsapp_account_ids']) && is_array($_GET['whatsapp_account_ids']) ? array_map('intval', $_GET['whatsapp_account_ids']) : (!empty($_GET['whatsapp_account_id']) ? [(int)$_GET['whatsapp_account_id']] : null),
            'unanswered' => isset($_GET['unanswered']) && $_GET['unanswered'] === '1' ? true : null,
            'answered' => isset($_GET['answered']) && $_GET['answered'] === '1' ? true : null,
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
            if ($key === 'search') {
                return $value !== null && trim($value) !== ''; // Manter busca mesmo se tiver espaços
            }
            // Manter arrays mesmo se vazios (serão processados depois)
            if (in_array($key, ['channels', 'tag_ids', 'whatsapp_account_ids']) && is_array($value)) {
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
            
            if ($selectedConversationId) {
                // Marcar mensagens como lidas quando a conversa é aberta (mesmo via URL direta)
                $userId = \App\Helpers\Auth::id();
                if ($userId) {
                    try {
                        \App\Models\Message::markAsRead((int)$selectedConversationId, $userId);
                    } catch (\Exception $e) {
                        error_log("Erro ao marcar mensagens como lidas na conversa {$selectedConversationId}: " . $e->getMessage());
                    }
                }
                
                try {
                    // Recarregar conversa para obter unread_count atualizado após marcar como lidas
                    $selectedConversation = ConversationService::getConversation((int)$selectedConversationId);
                } catch (\Exception $e) {
                    // Ignorar erro se conversa não encontrada
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
                'selectedConversationId' => $selectedConversationId
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

            // Verificar permissão
            if (!Permission::canViewConversation($conversation)) {
                Response::forbidden('Você não tem permissão para ver esta conversa.');
                return;
            }

            // Se for requisição AJAX, retornar JSON
            if (\App\Helpers\Request::isAjax() || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // Limpar qualquer output buffer antes de retornar JSON
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Marcar mensagens como lidas quando a conversa é aberta
                $userId = \App\Helpers\Auth::id();
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
                
                Response::json([
                    'success' => true,
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'tags' => $tags
                ]);
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
     * Atribuir conversa a agente
     */
    public function assign(int $id): void
    {
        try {
            $agentId = $_POST['agent_id'] ?? null;
            
            if (!$agentId) {
                throw new \Exception('Agente não informado');
            }

            $conversation = ConversationService::assignToAgent($id, $agentId);
            
            Response::json([
                'success' => true,
                'message' => 'Conversa atribuída com sucesso',
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
                'has_attachments' => isset($_GET['has_attachments']) ? filter_var($_GET['has_attachments'], FILTER_VALIDATE_BOOLEAN) : null
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
            
            // Contar total de mensagens
            $total = \App\Models\Message::countByConversation($id);
            
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
     * Listar participantes de uma conversa
     */
    public function getParticipants(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $participants = \App\Models\ConversationParticipant::getByConversation($id);
            
            Response::json([
                'success' => true,
                'participants' => $participants
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar participante a uma conversa
     */
    public function addParticipant(int $id): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $data = Request::post();
            $userId = (int)($data['user_id'] ?? 0);
            
            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'ID do usuário é obrigatório'
                ], 400);
                return;
            }
            
            $addedBy = \App\Helpers\Auth::id();
            $success = \App\Models\ConversationParticipant::addParticipant($id, $userId, $addedBy);
            
            if ($success) {
                // Invalidar cache da conversa
                ConversationService::invalidateCache($id);
                
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
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remover participante de uma conversa
     */
    public function removeParticipant(int $id, int $userId): void
    {
        Permission::abortIfCannot('conversations.edit.own');
        
        try {
            $success = \App\Models\ConversationParticipant::removeParticipant($id, $userId);
            
            if ($success) {
                // Invalidar cache da conversa
                ConversationService::invalidateCache($id);
                
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
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

