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
            'search' => $_GET['search'] ?? null,
            'agent_id' => $_GET['agent_id'] ?? null,
            'department_id' => $_GET['department_id'] ?? null,
            'tag_id' => $_GET['tag_id'] ?? null,
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

        // Remover filtros vazios (exceto pinned que pode ser false)
        $filters = array_filter($filters, function($value, $key) {
            if ($key === 'pinned') {
                return $value !== null; // Manter pinned mesmo se for false
            }
            if ($key === 'search') {
                return $value !== null && trim($value) !== ''; // Manter busca mesmo se tiver espaços
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
            
            // Modo DEMO: Se não houver conversas ou se demo=on, criar dados de demonstração
            // MAS apenas se não houver filtro de busca ativo
            $demoMode = false;
            $hasSearchFilter = !empty($filters['search']) && trim($filters['search']) !== '';
            
            if (isset($_GET['demo']) && $_GET['demo'] === 'on') {
                $demoMode = true;
                $conversations = self::getDemoConversations();
            } elseif (empty($conversations) && !isset($_GET['demo']) && !$hasSearchFilter) {
                // Só usar modo demo se não houver busca ativa
                $demoMode = true;
                $conversations = self::getDemoConversations();
            }
            
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
            
            // Se houver ID de conversa na URL, carregar para exibir no chat
            $selectedConversationId = $_GET['id'] ?? null;
            $selectedConversation = null;
            
            if ($selectedConversationId) {
                // Marcar mensagens como lidas quando a conversa é aberta (mesmo via URL direta)
                $userId = \App\Helpers\Auth::id();
                if ($userId && !$demoMode) {
                    try {
                        \App\Models\Message::markAsRead((int)$selectedConversationId, $userId);
                    } catch (\Exception $e) {
                        error_log("Erro ao marcar mensagens como lidas na conversa {$selectedConversationId}: " . $e->getMessage());
                    }
                }
                
                try {
                    // Se for modo demo, buscar da lista demo
                    if ($demoMode) {
                        foreach ($conversations as $conv) {
                            if (isset($conv['id']) && $conv['id'] == $selectedConversationId) {
                                $selectedConversation = $conv;
                                // Adicionar mensagens de exemplo para modo demo
                                $selectedConversation['messages'] = self::getDemoMessages((int)$selectedConversationId);
                                break;
                            }
                        }
                    } else {
                        // Recarregar conversa para obter unread_count atualizado após marcar como lidas
                        $selectedConversation = ConversationService::getConversation((int)$selectedConversationId);
                    }
                } catch (\Exception $e) {
                    // Ignorar erro se conversa não encontrada
                }
            }
            
            Response::view('conversations/index', [
                'conversations' => $conversations,
                'agents' => $agents,
                'departments' => $departments ?? [],
                'tags' => $tags ?? [],
                'filters' => $filters,
                'selectedConversation' => $selectedConversation,
                'selectedConversationId' => $selectedConversationId,
                'demoMode' => $demoMode
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
     * Garantir que uma conversa demo existe no banco de dados
     */
    public static function ensureDemoConversationExists(int $id): ?array
    {
        try {
            $demoConversations = self::getDemoConversations();
            $demoData = null;
            
            // Encontrar dados da conversa demo
            foreach ($demoConversations as $demo) {
                if (isset($demo['id']) && $demo['id'] == $id) {
                    $demoData = $demo;
                    break;
                }
            }
            
            if (!$demoData) {
                return null;
            }
            
            // Verificar se contato existe, criar se não existir
            $contact = \App\Models\Contact::findByEmail($demoData['contact_email']);
            if (!$contact) {
                // Criar contato
                $contactId = \App\Models\Contact::create([
                    'name' => $demoData['contact_name'],
                    'email' => $demoData['contact_email'],
                    'phone' => $demoData['contact_phone'],
                    'avatar' => $demoData['contact_avatar']
                ]);
                $contact = \App\Models\Contact::find($contactId);
            }
            
            if (!$contact) {
                return null;
            }
            
            // Verificar se conversa já existe
            $existingConversation = \App\Models\Conversation::find($id);
            if ($existingConversation) {
                return ConversationService::getConversation($id);
            }
            
            // Criar conversa
            $conversationData = [
                'contact_id' => $contact['id'],
                'channel' => $demoData['channel'] ?? 'whatsapp',
                'status' => $demoData['status'] ?? 'open'
            ];
            
            // Se tiver agente atribuído, buscar ID do agente
            if (!empty($demoData['agent_name'])) {
                $agent = \App\Helpers\Database::fetch(
                    "SELECT id FROM users WHERE name = ? LIMIT 1",
                    [$demoData['agent_name']]
                );
                if ($agent) {
                    $conversationData['agent_id'] = $agent['id'];
                }
            }
            
            // Criar conversa
            // Tentar criar com ID específico primeiro
            $db = \App\Helpers\Database::getInstance();
            $createdId = null;
            
            try {
                // Tentar inserir com ID específico
                $stmt = $db->prepare("INSERT INTO conversations (id, contact_id, channel, status, agent_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $id,
                    $contact['id'],
                    $conversationData['channel'],
                    $conversationData['status'],
                    $conversationData['agent_id'] ?? null
                ]);
                $createdId = $id;
            } catch (\PDOException $e) {
                // Se der erro (ID já existe ou AUTO_INCREMENT), criar normalmente
                $createdId = \App\Models\Conversation::create([
                    'contact_id' => $contact['id'],
                    'channel' => $conversationData['channel'],
                    'status' => $conversationData['status'],
                    'agent_id' => $conversationData['agent_id'] ?? null
                ]);
            }
            
            if (!$createdId) {
                throw new \Exception('Falha ao criar conversa demo');
            }
            
            // Usar o ID criado (pode ser diferente do ID demo se não conseguiu inserir com ID específico)
            $finalId = $createdId;
            
            // Criar mensagens demo se houver (usar ID demo original para buscar mensagens)
            $demoMessages = self::getDemoMessages($id);
            if (!empty($demoMessages)) {
                foreach ($demoMessages as $msg) {
                    if ($msg['type'] === 'system') {
                        continue; // Pular mensagens de sistema
                    }
                    
                    $senderType = $msg['direction'] === 'incoming' ? 'contact' : 'agent';
                    $senderId = $senderType === 'contact' ? $contact['id'] : ($conversationData['agent_id'] ?? \App\Helpers\Auth::id());
                    
                    // Criar mensagem usando SQL direto para poder definir created_at
                    $db = \App\Helpers\Database::getInstance();
                    $stmt = $db->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, content, message_type, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $finalId,
                        $senderId,
                        $senderType,
                        $msg['content'],
                        $msg['type'] === 'note' ? 'note' : 'text',
                        'sent',
                        $msg['created_at']
                    ]);
                }
            }
            
            // Retornar conversa criada
            return ConversationService::getConversation($finalId);
            
        } catch (\Exception $e) {
            error_log("Erro ao criar conversa demo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obter conversas de demonstração
     */
    private static function getDemoConversations(): array
    {
        $now = time();
        $demoData = [
            [
                'id' => 1,
                'contact_name' => 'Maria Silva',
                'contact_phone' => '+55 11 98765-4321',
                'contact_email' => 'maria.silva@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'whatsapp',
                'last_message' => 'Olá, preciso de ajuda com meu pedido #12345',
                'last_message_at' => date('Y-m-d H:i:s', $now - 300),
                'unread_count' => 2,
                'agent_name' => 'João Santos',
                'tags' => [
                    ['id' => 1, 'name' => 'VIP', 'color' => '#f1416c'],
                    ['id' => 2, 'name' => 'Urgente', 'color' => '#ffc700']
                ]
            ],
            [
                'id' => 2,
                'contact_name' => 'Carlos Oliveira',
                'contact_phone' => '+55 21 99876-5432',
                'contact_email' => 'carlos.oliveira@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'email',
                'last_message' => 'Gostaria de saber mais sobre os planos disponíveis',
                'last_message_at' => date('Y-m-d H:i:s', $now - 1800),
                'unread_count' => 0,
                'agent_name' => null,
                'tags' => [
                    ['id' => 3, 'name' => 'Novo', 'color' => '#009ef7']
                ]
            ],
            [
                'id' => 3,
                'contact_name' => 'Ana Costa',
                'contact_phone' => '+55 47 91234-5678',
                'contact_email' => 'ana.costa@email.com',
                'contact_avatar' => null,
                'status' => 'resolved',
                'channel' => 'whatsapp',
                'last_message' => 'Obrigada pela ajuda! Problema resolvido.',
                'last_message_at' => date('Y-m-d H:i:s', $now - 7200),
                'unread_count' => 0,
                'agent_name' => 'João Santos',
                'tags' => []
            ],
            [
                'id' => 4,
                'contact_name' => 'Roberto Santos',
                'contact_phone' => '+55 85 98765-4321',
                'contact_email' => 'roberto.santos@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'chat',
                'last_message' => 'Quando meu produto será entregue?',
                'last_message_at' => date('Y-m-d H:i:s', $now - 600),
                'unread_count' => 1,
                'agent_name' => null,
                'tags' => [
                    ['id' => 4, 'name' => 'Follow-up', 'color' => '#50cd89']
                ]
            ],
            [
                'id' => 5,
                'contact_name' => 'Juliana Ferreira',
                'contact_phone' => '+55 11 97654-3210',
                'contact_email' => 'juliana.ferreira@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'whatsapp',
                'last_message' => 'Preciso cancelar minha assinatura',
                'last_message_at' => date('Y-m-d H:i:s', $now - 120),
                'unread_count' => 3,
                'agent_name' => 'Maria Oliveira',
                'tags' => [
                    ['id' => 1, 'name' => 'VIP', 'color' => '#f1416c'],
                    ['id' => 2, 'name' => 'Urgente', 'color' => '#ffc700']
                ]
            ],
            [
                'id' => 6,
                'contact_name' => 'Pedro Almeida',
                'contact_phone' => '+55 48 91234-5678',
                'contact_email' => 'pedro.almeida@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'email',
                'last_message' => 'Gostaria de fazer uma reclamação sobre o atendimento',
                'last_message_at' => date('Y-m-d H:i:s', $now - 3600),
                'unread_count' => 0,
                'agent_name' => null,
                'tags' => [
                    ['id' => 3, 'name' => 'Novo', 'color' => '#009ef7']
                ]
            ],
            [
                'id' => 7,
                'contact_name' => 'Fernanda Lima',
                'contact_phone' => '+55 31 99876-5432',
                'contact_email' => 'fernanda.lima@email.com',
                'contact_avatar' => null,
                'status' => 'closed',
                'channel' => 'whatsapp',
                'last_message' => 'Tudo certo, obrigada!',
                'last_message_at' => date('Y-m-d H:i:s', $now - 86400),
                'unread_count' => 0,
                'agent_name' => 'João Santos',
                'tags' => []
            ],
            [
                'id' => 8,
                'contact_name' => 'Lucas Martins',
                'contact_phone' => '+55 41 98765-4321',
                'contact_email' => 'lucas.martins@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'chat',
                'last_message' => 'Como faço para alterar minha senha?',
                'last_message_at' => date('Y-m-d H:i:s', $now - 900),
                'unread_count' => 0,
                'agent_name' => 'Maria Oliveira',
                'tags' => [
                    ['id' => 4, 'name' => 'Follow-up', 'color' => '#50cd89']
                ]
            ],
            [
                'id' => 9,
                'contact_name' => 'Patricia Souza',
                'contact_phone' => '+55 11 99888-7766',
                'contact_email' => 'patricia.souza@email.com',
                'contact_avatar' => null,
                'status' => 'open',
                'channel' => 'whatsapp',
                'last_message' => 'Gostaria de informações sobre o produto X',
                'last_message_at' => date('Y-m-d H:i:s', $now - 180),
                'unread_count' => 1,
                'agent_name' => null,
                'tags' => [
                    ['id' => 3, 'name' => 'Novo', 'color' => '#009ef7']
                ]
            ]
        ];

        return $demoData;
    }

    /**
     * Obter mensagens de demonstração para uma conversa
     */
    private static function getDemoMessages(int $conversationId): array
    {
        $now = time();
        $messages = [];
        
        // Mensagens diferentes para cada conversa
        $conversationMessages = [
            1 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 3700)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Maria Silva', 'content' => 'Olá, preciso de ajuda com meu pedido #12345', 'created_at' => date('Y-m-d H:i:s', $now - 3600)],
                ['type' => 'system', 'content' => 'Conversa atribuída para João Santos', 'created_at' => date('Y-m-d H:i:s', $now - 3550)],
                ['type' => 'message', 'direction' => 'outgoing', 'sender_name' => 'João Santos', 'content' => 'Olá Maria! Claro, vou verificar seu pedido agora mesmo.', 'created_at' => date('Y-m-d H:i:s', $now - 3500), 'delivered_at' => date('Y-m-d H:i:s', $now - 3490), 'read_at' => date('Y-m-d H:i:s', $now - 3480)],
                ['type' => 'note', 'sender_name' => 'João Santos', 'content' => 'Cliente VIP - dar prioridade no atendimento', 'created_at' => date('Y-m-d H:i:s', $now - 3400)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Maria Silva', 'content' => 'Obrigada! Estou aguardando.', 'created_at' => date('Y-m-d H:i:s', $now - 300)],
            ],
            2 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 1850)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Carlos Oliveira', 'content' => 'Gostaria de saber mais sobre os planos disponíveis', 'created_at' => date('Y-m-d H:i:s', $now - 1800)],
            ],
            3 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 7300)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Ana Costa', 'content' => 'Obrigada pela ajuda! Problema resolvido.', 'created_at' => date('Y-m-d H:i:s', $now - 7200)],
                ['type' => 'message', 'direction' => 'outgoing', 'sender_name' => 'João Santos', 'content' => 'Fico feliz em ajudar! Se precisar de mais alguma coisa, estou à disposição.', 'created_at' => date('Y-m-d H:i:s', $now - 7100), 'read_at' => date('Y-m-d H:i:s', $now - 7000)],
            ],
            4 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 650)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Roberto Santos', 'content' => 'Quando meu produto será entregue?', 'created_at' => date('Y-m-d H:i:s', $now - 600)],
            ],
            5 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 1900)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Juliana Ferreira', 'content' => 'Preciso cancelar minha assinatura', 'created_at' => date('Y-m-d H:i:s', $now - 1800)],
                ['type' => 'message', 'direction' => 'outgoing', 'sender_name' => 'Maria Oliveira', 'content' => 'Entendo sua solicitação. Vou processar o cancelamento para você.', 'created_at' => date('Y-m-d H:i:s', $now - 1700), 'delivered_at' => date('Y-m-d H:i:s', $now - 1690)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Juliana Ferreira', 'content' => 'Obrigada! Quanto tempo leva?', 'created_at' => date('Y-m-d H:i:s', $now - 120)],
            ],
            6 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 3650)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Pedro Almeida', 'content' => 'Gostaria de fazer uma reclamação sobre o atendimento', 'created_at' => date('Y-m-d H:i:s', $now - 3600)],
            ],
            7 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 86500)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Fernanda Lima', 'content' => 'Tudo certo, obrigada!', 'created_at' => date('Y-m-d H:i:s', $now - 86400)],
                ['type' => 'message', 'direction' => 'outgoing', 'sender_name' => 'João Santos', 'content' => 'Que bom! Fico feliz em saber que conseguimos ajudar.', 'created_at' => date('Y-m-d H:i:s', $now - 86300), 'read_at' => date('Y-m-d H:i:s', $now - 86200)],
            ],
            8 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 950)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Lucas Martins', 'content' => 'Como faço para alterar minha senha?', 'created_at' => date('Y-m-d H:i:s', $now - 900)],
                ['type' => 'message', 'direction' => 'outgoing', 'sender_name' => 'Maria Oliveira', 'content' => 'Você pode alterar sua senha nas configurações da conta. Quer que eu te guie?', 'created_at' => date('Y-m-d H:i:s', $now - 800), 'delivered_at' => date('Y-m-d H:i:s', $now - 790)],
            ],
            9 => [
                ['type' => 'system', 'content' => 'Conversa iniciada em ' . date('d/m/Y H:i'), 'created_at' => date('Y-m-d H:i:s', $now - 200)],
                ['type' => 'message', 'direction' => 'incoming', 'sender_name' => 'Patricia Souza', 'content' => 'Olá! Gostaria de informações sobre o produto X', 'created_at' => date('Y-m-d H:i:s', $now - 180)],
            ],
        ];
        
        return $conversationMessages[$conversationId] ?? [];
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
                // Tentar criar conversa demo se o ID for de 1 a 9 (IDs das conversas demo)
                if ($id >= 1 && $id <= 9) {
                    $conversation = self::ensureDemoConversationExists($id);
                }
                
                if (!$conversation) {
                    Response::json(['success' => false, 'message' => 'Conversa não encontrada.'], 404);
                    return;
                }
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
}

