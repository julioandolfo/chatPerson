<?php
/**
 * Rotas da API v1
 */

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use Api\Middleware\RateLimitMiddleware;

// Obter caminho da requisição
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Remover query string
$requestUri = strtok($requestUri, '?');

// Remover prefixo /api/v1
$path = preg_replace('#^/api/v1#', '', $requestUri);
$path = rtrim($path, '/') ?: '/';

// Roteamento simples
$routes = [
    // Autenticação (sem auth required)
    'POST /auth/login' => ['Api\V1\Controllers\AuthController', 'login', false],
    'POST /auth/refresh' => ['Api\V1\Controllers\AuthController', 'refresh', false],
    'POST /auth/logout' => ['Api\V1\Controllers\AuthController', 'logout', true],
    'GET /auth/me' => ['Api\V1\Controllers\AuthController', 'me', true],
    
    // Conversas
    'GET /conversations' => ['Api\V1\Controllers\ConversationsController', 'index', true],
    'POST /conversations' => ['Api\V1\Controllers\ConversationsController', 'store', true],
    'GET /conversations/:id' => ['Api\V1\Controllers\ConversationsController', 'show', true],
    'PUT /conversations/:id' => ['Api\V1\Controllers\ConversationsController', 'update', true],
    'DELETE /conversations/:id' => ['Api\V1\Controllers\ConversationsController', 'destroy', true],
    'POST /conversations/:id/assign' => ['Api\V1\Controllers\ConversationsController', 'assign', true],
    'POST /conversations/:id/close' => ['Api\V1\Controllers\ConversationsController', 'close', true],
    'POST /conversations/:id/reopen' => ['Api\V1\Controllers\ConversationsController', 'reopen', true],
    'POST /conversations/:id/move-stage' => ['Api\V1\Controllers\ConversationsController', 'moveStage', true],
    'PUT /conversations/:id/department' => ['Api\V1\Controllers\ConversationsController', 'updateDepartment', true],
    'POST /conversations/:id/tags' => ['Api\V1\Controllers\ConversationsController', 'addTag', true],
    'DELETE /conversations/:id/tags/:tagId' => ['Api\V1\Controllers\ConversationsController', 'removeTag', true],
    
    // Mensagens
    'GET /conversations/:id/messages' => ['Api\V1\Controllers\MessagesController', 'index', true],
    'POST /conversations/:id/messages' => ['Api\V1\Controllers\MessagesController', 'store', true],
    'GET /messages/:id' => ['Api\V1\Controllers\MessagesController', 'show', true],
    
    // Participantes
    'GET /conversations/:id/participants' => ['Api\V1\Controllers\ParticipantsController', 'index', true],
    'POST /conversations/:id/participants' => ['Api\V1\Controllers\ParticipantsController', 'store', true],
    'DELETE /conversations/:id/participants/:userId' => ['Api\V1\Controllers\ParticipantsController', 'destroy', true],
    
    // Contatos
    'GET /contacts' => ['Api\V1\Controllers\ContactsController', 'index', true],
    'POST /contacts' => ['Api\V1\Controllers\ContactsController', 'store', true],
    'GET /contacts/:id' => ['Api\V1\Controllers\ContactsController', 'show', true],
    'PUT /contacts/:id' => ['Api\V1\Controllers\ContactsController', 'update', true],
    'DELETE /contacts/:id' => ['Api\V1\Controllers\ContactsController', 'destroy', true],
    'GET /contacts/:id/conversations' => ['Api\V1\Controllers\ContactsController', 'conversations', true],
    
    // Agentes
    'GET /agents' => ['Api\V1\Controllers\AgentsController', 'index', true],
    'GET /agents/:id' => ['Api\V1\Controllers\AgentsController', 'show', true],
    'GET /agents/:id/stats' => ['Api\V1\Controllers\AgentsController', 'stats', true],
    
    // Setores
    'GET /departments' => ['Api\V1\Controllers\DepartmentsController', 'index', true],
    'GET /departments/:id' => ['Api\V1\Controllers\DepartmentsController', 'show', true],
    
    // Funis
    'GET /funnels' => ['Api\V1\Controllers\FunnelsController', 'index', true],
    'GET /funnels/:id' => ['Api\V1\Controllers\FunnelsController', 'show', true],
    'GET /funnels/:id/stages' => ['Api\V1\Controllers\FunnelsController', 'stages', true],
    'GET /funnels/:id/conversations' => ['Api\V1\Controllers\FunnelsController', 'conversations', true],
    
    // Tags
    'GET /tags' => ['Api\V1\Controllers\TagsController', 'index', true],
    'POST /tags' => ['Api\V1\Controllers\TagsController', 'store', true],
    'GET /tags/:id' => ['Api\V1\Controllers\TagsController', 'show', true],
    'PUT /tags/:id' => ['Api\V1\Controllers\TagsController', 'update', true],
    'DELETE /tags/:id' => ['Api\V1\Controllers\TagsController', 'destroy', true],
    
    // WhatsApp Accounts
    'GET /whatsapp-accounts' => ['Api\V1\Controllers\WhatsAppAccountsController', 'index', true],
    'GET /whatsapp-accounts/:id' => ['Api\V1\Controllers\WhatsAppAccountsController', 'show', true],
    
    // Messages (Enviar mensagem via WhatsApp)
    'POST /messages/send' => ['Api\V1\Controllers\MessagesController', 'send', true],
    'POST /messages/send-template' => ['Api\V1\Controllers\MessagesController', 'sendTemplate', true],
    'GET /templates' => ['Api\V1\Controllers\MessagesController', 'listTemplates', true],

    // Estatísticas / Dashboard
    'GET /stats/overview'          => ['Api\V1\Controllers\StatsController', 'overview',    true],
    'GET /stats/conversations'     => ['Api\V1\Controllers\StatsController', 'conversations', true],
    'GET /stats/agents'            => ['Api\V1\Controllers\StatsController', 'agents',      true],
    'GET /stats/agents/:id'        => ['Api\V1\Controllers\StatsController', 'agentDetail', true],
    'GET /stats/departments'       => ['Api\V1\Controllers\StatsController', 'departments', true],
    'GET /stats/funnels'           => ['Api\V1\Controllers\StatsController', 'funnels',     true],
    'GET /stats/sla'               => ['Api\V1\Controllers\StatsController', 'sla',         true],

    // ══════ App mobile (Chat Privus) ══════

    // Tempo real (polling)
    'GET /realtime/config'  => ['Api\V1\Controllers\RealtimeController', 'config', true],
    'POST /realtime/poll'   => ['Api\V1\Controllers\RealtimeController', 'poll', true],

    // Dispositivos (push notifications)
    'POST /devices'           => ['Api\V1\Controllers\DevicesController', 'store', true],
    'DELETE /devices/:token'  => ['Api\V1\Controllers\DevicesController', 'destroy', true],

    // Notificações in-app
    'GET /notifications'            => ['Api\V1\Controllers\NotificationsController', 'index', true],
    'GET /notifications/unread'     => ['Api\V1\Controllers\NotificationsController', 'unread', true],
    'POST /notifications/read-all'  => ['Api\V1\Controllers\NotificationsController', 'markAllRead', true],
    'POST /notifications/:id/read'  => ['Api\V1\Controllers\NotificationsController', 'markRead', true],

    // Notas internas
    'GET /conversations/:id/notes'  => ['Api\V1\Controllers\NotesController', 'index', true],
    'POST /conversations/:id/notes' => ['Api\V1\Controllers\NotesController', 'store', true],

    // Ações de conversa (paridade com o web)
    'POST /conversations/check-existing'          => ['Api\V1\Controllers\ConversationActionsController', 'checkExisting', true],
    'POST /conversations/:id/mark-read'           => ['Api\V1\Controllers\ConversationActionsController', 'markRead', true],
    'POST /conversations/:id/mark-unread'         => ['Api\V1\Controllers\ConversationActionsController', 'markUnread', true],
    'GET /conversations/:id/cloud-window'         => ['Api\V1\Controllers\ConversationActionsController', 'cloudWindow', true],
    'POST /conversations/:id/send-cloud-template' => ['Api\V1\Controllers\ConversationActionsController', 'sendCloudTemplate', true],

    // Anexos (mídia)
    'GET /attachments/sign' => ['Api\V1\Controllers\AttachmentsController', 'sign', true],
    'GET /attachments/view' => ['Api\V1\Controllers\AttachmentsController', 'view', false], // validado por assinatura HMAC
];

// Encontrar rota correspondente
$handler = null;
$params = [];
$requiresAuth = false;

foreach ($routes as $route => $config) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);
    
    // Verificar método
    if ($routeMethod !== $requestMethod) {
        continue;
    }
    
    // Converter :param para regex
    $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $path, $matches)) {
        $handler = [$config[0], $config[1]];
        $requiresAuth = $config[2] ?? true;
        
        // Extrair parâmetros
        foreach ($matches as $key => $value) {
            if (!is_numeric($key)) {
                $params[$key] = $value;
            }
        }
        
        break;
    }
}

// Rota não encontrada
if (!$handler) {
    ApiResponse::notFound('Endpoint não encontrado');
}

// Aplicar middlewares
if ($requiresAuth) {
    ApiAuthMiddleware::handle();
}

// Polling de tempo real fica fora do rate limit (o app chama a cada ~5s)
if ($path !== '/realtime/poll') {
    RateLimitMiddleware::handle();
}

// Executar controller
[$controllerClass, $method] = $handler;

if (!class_exists($controllerClass)) {
    ApiResponse::serverError("Controller não encontrado: {$controllerClass}");
}

$controller = new $controllerClass();

if (!method_exists($controller, $method)) {
    ApiResponse::serverError("Método não encontrado: {$method}");
}

// Passar parâmetros para o método
call_user_func_array([$controller, $method], $params);
