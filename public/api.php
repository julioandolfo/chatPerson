<?php
/**
 * API Gateway Standalone
 * 
 * Gateway único que roteia todas as requisições da API.
 * Funciona diretamente via URL, sem depender de .htaccess.
 * 
 * URL Base: https://chat.personizi.com.br/api.php
 * 
 * Exemplos:
 *   GET  /api.php/whatsapp-accounts
 *   POST /api.php/messages/send
 *   GET  /api.php/conversations
 *   GET  /api.php/contacts
 * 
 * Headers:
 *   Authorization: Bearer {token}
 *   Content-Type: application/json
 *   Accept: application/json
 */

// =====================================================
// CONFIGURAÇÃO CORS
// =====================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// =====================================================
// BOOTSTRAP
// =====================================================
$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/bootstrap.php';

use App\Helpers\Database;

// =====================================================
// FUNÇÕES AUXILIARES
// =====================================================

/**
 * Logger global da API
 */
function apiLog($level, $message) {
    $logFile = __DIR__ . '/../storage/logs/api.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $line = "[{$timestamp}] [{$level}] {$message}\n";
    @file_put_contents($logFile, $line, FILE_APPEND);
}

/**
 * Responder JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Resposta de erro
 */
function errorResponse($message, $code, $statusCode = 400, $details = null) {
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ];
    if ($details) {
        $response['error']['details'] = $details;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Resposta de sucesso
 */
function successResponse($data, $message = null, $statusCode = 200) {
    $response = ['success' => true];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message) {
        $response['message'] = $message;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Resposta paginada
 */
function paginatedResponse($items, $total, $page, $perPage) {
    $totalPages = $total > 0 ? ceil($total / $perPage) : 1;
    successResponse([
        'items' => $items,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
}

/**
 * Obter header de autorização (compatível com vários servidores)
 */
function getAuthorizationHeader() {
    $authHeader = null;
    
    // Método 1: $_SERVER['HTTP_AUTHORIZATION']
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    // Método 2: $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (quando há redirect)
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    // Método 3: apache_request_headers()
    elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }
    // Método 4: getallheaders()
    elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }
    }
    
    // Método 5: X-API-Key como fallback
    if (empty($authHeader)) {
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $authHeader = 'Bearer ' . $_SERVER['HTTP_X_API_KEY'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_X_API_KEY'])) {
            $authHeader = 'Bearer ' . $_SERVER['REDIRECT_HTTP_X_API_KEY'];
        }
    }
    
    return $authHeader;
}

/**
 * Validar token de autenticação
 */
function validateToken() {
    $logFile = __DIR__ . '/../storage/logs/api.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $log = function($level, $message) use ($logFile) {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    };
    
    $log('INFO', '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    $log('INFO', '🔐 INICIANDO VALIDAÇÃO DE TOKEN');
    
    $authHeader = getAuthorizationHeader();
    
    $log('DEBUG', 'Authorization Header: ' . ($authHeader ? substr($authHeader, 0, 50) . '...' : 'VAZIO'));
    
    if (empty($authHeader)) {
        $log('ERROR', '❌ Header Authorization não encontrado');
        
        // Debug: mostrar quais headers estão disponíveis
        $availableHeaders = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $availableHeaders[$key] = substr($value, 0, 50);
                $log('DEBUG', "Header disponível: {$key} = {$value}");
            }
        }
        
        errorResponse('Token de autenticação não fornecido', 'UNAUTHORIZED', 401, [
            'available_headers' => $availableHeaders,
            'tip' => 'Verifique se o servidor está configurado para repassar o header Authorization'
        ]);
    }
    
    // Extrair token
    $token = null;
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $log('DEBUG', 'Token extraído via Bearer (length: ' . strlen($token) . ')');
    } elseif (preg_match('/^Token\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        $log('DEBUG', 'Token extraído via Token (length: ' . strlen($token) . ')');
    } else {
        $token = $authHeader;
        $log('DEBUG', 'Token extraído direto (length: ' . strlen($token) . ')');
    }
    
    if (empty($token)) {
        $log('ERROR', '❌ Token vazio após extração');
        errorResponse('Token inválido', 'UNAUTHORIZED', 401);
    }
    
    $log('DEBUG', 'Token: ' . substr($token, 0, 20) . '...' . substr($token, -10));
    
    try {
        $log('INFO', '🔍 Conectando ao banco de dados...');
        $db = Database::getInstance();
        $log('INFO', '✅ Conexão com banco OK');
        
        $tokenHash = hash('sha256', $token);
        $log('DEBUG', 'Token SHA256: ' . substr($tokenHash, 0, 20) . '...' . substr($tokenHash, -10));
        
        // Verificar na tabela api_tokens (com hash)
        $log('INFO', '🔍 Buscando token no banco (COM hash SHA256)...');
        $stmt = $db->prepare("
            SELECT id, user_id, name, permissions, rate_limit, expires_at, is_active
            FROM api_tokens 
            WHERE token = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $apiToken = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($apiToken) {
            $log('INFO', '✅ Token encontrado COM hash (ID: ' . $apiToken['id'] . ', Name: ' . $apiToken['name'] . ')');
        } else {
            $log('WARNING', '⚠️ Token NÃO encontrado com hash, tentando SEM hash...');
            
            // Tentar token sem hash (compatibilidade)
            $stmt->execute([$token]);
            $apiToken = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($apiToken) {
                $log('INFO', '✅ Token encontrado SEM hash (ID: ' . $apiToken['id'] . ', Name: ' . $apiToken['name'] . ')');
            }
        }
        
        if (!$apiToken) {
            $log('ERROR', '❌ Token NÃO encontrado no banco de dados ou está inativo');
            
            // Listar tokens disponíveis (primeiros 5)
            $stmt = $db->prepare("SELECT id, name, is_active, LEFT(token, 20) as token_preview FROM api_tokens ORDER BY created_at DESC LIMIT 5");
            $stmt->execute();
            $availableTokens = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $log('DEBUG', 'Tokens no banco: ' . count($availableTokens));
            foreach ($availableTokens as $t) {
                $status = $t['is_active'] ? 'ATIVO' : 'INATIVO';
                $log('DEBUG', "  - ID {$t['id']}: {$t['name']} [{$status}] (preview: {$t['token_preview']}...)");
            }
            
            errorResponse('Token inválido, expirado ou inativo', 'UNAUTHORIZED', 401);
        }
        
        // Verificar se está ativo
        if (!$apiToken['is_active']) {
            $log('ERROR', '❌ Token está INATIVO (is_active = 0)');
            errorResponse('Token foi desativado', 'UNAUTHORIZED', 401);
        }
        
        // Verificar expiração
        if ($apiToken['expires_at'] && strtotime($apiToken['expires_at']) < time()) {
            $log('ERROR', '❌ Token EXPIROU em: ' . $apiToken['expires_at']);
            errorResponse('Token expirado', 'UNAUTHORIZED', 401);
        }
        
        $log('INFO', '✅ Token válido e ativo');
        $log('INFO', 'User ID: ' . $apiToken['user_id']);
        $log('INFO', 'Token Name: ' . $apiToken['name']);
        
        // Atualizar last_used_at
        $stmt = $db->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$apiToken['id']]);
        $log('DEBUG', 'last_used_at atualizado');
        
        $log('INFO', '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        return $apiToken;
        
    } catch (\Exception $e) {
        $log('ERROR', '❌ EXCEÇÃO: ' . $e->getMessage());
        $log('ERROR', 'Arquivo: ' . $e->getFile());
        $log('ERROR', 'Linha: ' . $e->getLine());
        $log('DEBUG', 'Stack trace: ' . $e->getTraceAsString());
        
        error_log("Erro ao validar token: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        errorResponse('Erro ao validar token', 'SERVER_ERROR', 500, [
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'debug_url' => 'Use /debug-token.php?token=SEU_TOKEN para diagnosticar',
            'view_logs' => 'Use /view-all-logs.php para ver logs completos'
        ]);
    }
}

/**
 * Obter body JSON
 */
function getJsonBody() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        errorResponse('JSON inválido', 'VALIDATION_ERROR', 422, ['body' => ['JSON mal formatado']]);
    }
    return $input ?: [];
}

// =====================================================
// ROTEAMENTO
// =====================================================

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Extrair path depois de api.php
$scriptName = $_SERVER['SCRIPT_NAME'];
$path = str_replace($scriptName, '', $requestUri);
$path = strtok($path, '?'); // Remover query string
$path = '/' . ltrim($path, '/');
$path = rtrim($path, '/') ?: '/';

// Debug (remover em produção)
// error_log("API Gateway: {$requestMethod} {$path}");

// =====================================================
// DEFINIÇÃO DE ROTAS
// =====================================================

$routes = [
    // ============== AUTENTICAÇÃO ==============
    'POST /auth/login' => 'authLogin',
    'GET /auth/me' => 'authMe',
    
    // ============== WHATSAPP ==============
    'GET /whatsapp-accounts' => 'whatsappAccountsList',
    'GET /whatsapp-accounts/:id' => 'whatsappAccountsShow',
    
    // ============== MENSAGENS ==============
    'POST /messages/send' => 'messagesSend',
    'GET /conversations/:id/messages' => 'messagesListByConversation',
    
    // ============== CONVERSAS ==============
    'GET /conversations' => 'conversationsList',
    'POST /conversations' => 'conversationsCreate',
    'GET /conversations/:id' => 'conversationsShow',
    'PUT /conversations/:id' => 'conversationsUpdate',
    'DELETE /conversations/:id' => 'conversationsDelete',
    'POST /conversations/:id/assign' => 'conversationsAssign',
    'POST /conversations/:id/close' => 'conversationsClose',
    'POST /conversations/:id/reopen' => 'conversationsReopen',
    'POST /conversations/:id/move-stage' => 'conversationsMoveStage',
    
    // ============== CONTATOS ==============
    'GET /contacts' => 'contactsList',
    'POST /contacts' => 'contactsCreate',
    'GET /contacts/:id' => 'contactsShow',
    'PUT /contacts/:id' => 'contactsUpdate',
    'DELETE /contacts/:id' => 'contactsDelete',
    'GET /contacts/:id/conversations' => 'contactsConversations',
    
    // ============== AGENTES ==============
    'GET /agents' => 'agentsList',
    'GET /agents/:id' => 'agentsShow',
    'GET /agents/:id/stats' => 'agentsStats',
    
    // ============== SETORES ==============
    'GET /departments' => 'departmentsList',
    'GET /departments/:id' => 'departmentsShow',
    
    // ============== FUNIS ==============
    'GET /funnels' => 'funnelsList',
    'GET /funnels/:id' => 'funnelsShow',
    'GET /funnels/:id/stages' => 'funnelsStages',
    'GET /funnels/:id/conversations' => 'funnelsConversations',
    
    // ============== TAGS ==============
    'GET /tags' => 'tagsList',
    'POST /tags' => 'tagsCreate',
    'GET /tags/:id' => 'tagsShow',
    'PUT /tags/:id' => 'tagsUpdate',
    'DELETE /tags/:id' => 'tagsDelete',
];

// Encontrar rota correspondente
$handler = null;
$params = [];

foreach ($routes as $route => $handlerName) {
    [$routeMethod, $routePath] = explode(' ', $route, 2);
    
    if ($routeMethod !== $requestMethod) {
        continue;
    }
    
    // Converter :param para regex
    $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $routePath);
    $pattern = '#^' . $pattern . '$#';
    
    if (preg_match($pattern, $path, $matches)) {
        $handler = $handlerName;
        
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
    // Mostrar documentação se for raiz
    if ($path === '/' || $path === '') {
        successResponse([
            'version' => 'v1',
            'status' => 'online',
            'documentation' => '/settings/api-tokens/docs',
            'endpoints' => [
                'POST /auth/login' => 'Autenticação',
                'GET /whatsapp-accounts' => 'Listar contas WhatsApp',
                'POST /messages/send' => 'Enviar mensagem WhatsApp',
                'GET /conversations' => 'Listar conversas',
                'GET /contacts' => 'Listar contatos',
                'GET /agents' => 'Listar agentes',
                'GET /departments' => 'Listar setores',
                'GET /funnels' => 'Listar funis',
                'GET /tags' => 'Listar tags'
            ]
        ]);
    }
    
    errorResponse('Endpoint não encontrado', 'NOT_FOUND', 404);
}

// =====================================================
// HANDLERS
// =====================================================

// Autenticação (exceto login)
$noAuthRoutes = ['authLogin'];
if (!in_array($handler, $noAuthRoutes)) {
    $tokenInfo = validateToken();
}

$db = Database::getInstance();

try {
    switch ($handler) {
        // ============== AUTENTICAÇÃO ==============
        case 'authLogin':
            $input = getJsonBody();
            
            if (empty($input['email']) || empty($input['password'])) {
                errorResponse('Email e senha são obrigatórios', 'VALIDATION_ERROR', 422);
            }
            
            $stmt = $db->prepare("SELECT id, name, email, password, role_id, is_active FROM users WHERE email = ?");
            $stmt->execute([$input['email']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($input['password'], $user['password'])) {
                errorResponse('Credenciais inválidas', 'UNAUTHORIZED', 401);
            }
            
            if (!$user['is_active']) {
                errorResponse('Usuário desativado', 'FORBIDDEN', 403);
            }
            
            // Gerar token simples
            $token = bin2hex(random_bytes(32));
            
            $stmt = $db->prepare("
                INSERT INTO api_tokens (user_id, name, token, created_at, expires_at)
                VALUES (?, 'API Login', ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
            ");
            $stmt->execute([$user['id'], hash('sha256', $token)]);
            
            unset($user['password']);
            successResponse([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 86400
            ]);
            break;
            
        case 'authMe':
            $stmt = $db->prepare("SELECT id, name, email, role_id, is_active FROM users WHERE id = ?");
            $stmt->execute([$tokenInfo['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            successResponse($user);
            break;
        
        // ============== WHATSAPP ACCOUNTS ==============
        case 'whatsappAccountsList':
            $status = $_GET['status'] ?? null;
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $where = ['1=1'];
            $params = [];
            
            if ($status && in_array($status, ['active', 'inactive', 'disconnected'])) {
                $where[] = 'status = ?';
                $params[] = $status;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM whatsapp_accounts WHERE {$whereClause}");
            $stmt->execute($params);
            $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            $stmt = $db->prepare("
                SELECT id, name, phone_number, provider, api_url, status, 
                       default_funnel_id, default_stage_id, created_at, updated_at
                FROM whatsapp_accounts 
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($params, [$perPage, $offset]));
            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            successResponse([
                'accounts' => $accounts,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $total > 0 ? ceil($total / $perPage) : 1,
                    'has_next' => $page < ceil($total / $perPage),
                    'has_prev' => $page > 1
                ]
            ]);
            break;
            
        case 'whatsappAccountsShow':
            $stmt = $db->prepare("
                SELECT id, name, phone_number, provider, api_url, status,
                       default_funnel_id, default_stage_id, wavoip_enabled,
                       created_at, updated_at
                FROM whatsapp_accounts WHERE id = ?
            ");
            $stmt->execute([$params['id']]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$account) {
                errorResponse('Conta não encontrada', 'NOT_FOUND', 404);
            }
            
            successResponse($account);
            break;
        
        // ============== MESSAGES ==============
        case 'messagesSend':
            apiLog('INFO', '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            apiLog('INFO', '📤 ENVIANDO MENSAGEM WHATSAPP');
            
            $input = getJsonBody();
            apiLog('DEBUG', 'Body recebido: ' . json_encode($input));
            
            $errors = [];
            if (empty($input['to'])) $errors['to'] = ['Campo obrigatório'];
            if (empty($input['from'])) $errors['from'] = ['Campo obrigatório'];
            if (empty($input['message'])) $errors['message'] = ['Campo obrigatório'];
            
            if (!empty($errors)) {
                apiLog('ERROR', '❌ Validação falhou: ' . json_encode($errors));
                errorResponse('Dados inválidos', 'VALIDATION_ERROR', 422, $errors);
            }
            
            $to = preg_replace('/[^0-9]/', '', $input['to']);
            $from = preg_replace('/[^0-9]/', '', $input['from']);
            $message = $input['message'];
            $contactName = $input['contact_name'] ?? '';
            
            apiLog('INFO', "Para: {$to}");
            apiLog('INFO', "De: {$from}");
            apiLog('INFO', "Mensagem: " . substr($message, 0, 50) . '...');
            
            // Buscar conta WhatsApp
            apiLog('INFO', '🔍 Buscando conta WhatsApp...');
            $stmt = $db->prepare("
                SELECT id, name, api_url, provider, quepasa_token, quepasa_user
                FROM whatsapp_accounts 
                WHERE phone_number = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$from]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$account) {
                apiLog('ERROR', "❌ Conta WhatsApp não encontrada para: {$from}");
                errorResponse('Conta WhatsApp não encontrada', 'VALIDATION_ERROR', 422, 
                    ['from' => ["Nenhuma conta ativa para: {$from}"]]);
            }
            
            apiLog('INFO', "✅ Conta encontrada: {$account['name']} (ID: {$account['id']})");
            
            // Buscar ou criar contato
            apiLog('INFO', '🔍 Buscando contato...');
            $stmt = $db->prepare("SELECT id FROM contacts WHERE phone_number = ? LIMIT 1");
            $stmt->execute([$to]);
            $contact = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$contact) {
                apiLog('INFO', '📝 Criando novo contato...');
                $stmt = $db->prepare("INSERT INTO contacts (phone_number, name, channel, created_at, updated_at) VALUES (?, ?, 'whatsapp', NOW(), NOW())");
                $stmt->execute([$to, $contactName ?: $to]);
                $contactId = $db->lastInsertId();
                apiLog('INFO', "✅ Contato criado (ID: {$contactId})");
            } else {
                $contactId = $contact['id'];
                apiLog('INFO', "✅ Contato encontrado (ID: {$contactId})");
            }
            
            // Buscar ou criar conversa
            apiLog('INFO', '🔍 Buscando conversa aberta...');
            $stmt = $db->prepare("
                SELECT id FROM conversations 
                WHERE contact_id = ? AND channel = 'whatsapp' AND status IN ('open', 'pending')
                ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$contactId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                apiLog('INFO', '📝 Criando nova conversa...');
                $stmt = $db->prepare("
                    INSERT INTO conversations (contact_id, channel, status, contact_name, contact_phone, whatsapp_account_id, created_at, updated_at)
                    VALUES (?, 'whatsapp', 'open', ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$contactId, $contactName ?: $to, $to, $account['id']]);
                $conversationId = $db->lastInsertId();
                apiLog('INFO', "✅ Conversa criada (ID: {$conversationId})");
            } else {
                $conversationId = $conversation['id'];
                apiLog('INFO', "✅ Conversa encontrada (ID: {$conversationId})");
            }
            
            // Inserir mensagem
            apiLog('INFO', '📝 Inserindo mensagem no banco...');
            $stmt = $db->prepare("
                INSERT INTO messages (conversation_id, sender_type, content, type, status, created_at)
                VALUES (?, 'agent', ?, 'text', 'sent', NOW())
            ");
            $stmt->execute([$conversationId, $message]);
            $messageId = $db->lastInsertId();
            apiLog('INFO', "✅ Mensagem inserida (ID: {$messageId})");
            
            // Atualizar conversa
            apiLog('DEBUG', 'Atualizando timestamp da conversa...');
            $stmt = $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$conversationId]);
            
            // Enviar via Quepasa
            $messageSent = false;
            if ($account['provider'] === 'quepasa' && !empty($account['api_url'])) {
                apiLog('INFO', '📡 Enviando via Quepasa...');
                apiLog('DEBUG', "Quepasa URL: {$account['api_url']}/send");
                
                $quepasaPayload = ['chatid' => $to . '@s.whatsapp.net', 'text' => $message];
                apiLog('DEBUG', 'Quepasa Payload: ' . json_encode($quepasaPayload));
                
                $ch = curl_init(rtrim($account['api_url'], '/') . '/send');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($quepasaPayload),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'X-QUEPASA-TOKEN: ' . ($account['quepasa_token'] ?? ''),
                        'X-QUEPASA-USER: ' . ($account['quepasa_user'] ?? 'system')
                    ],
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $messageSent = ($httpCode >= 200 && $httpCode < 300);
                
                apiLog('INFO', "📡 Quepasa respondeu: HTTP {$httpCode}");
                apiLog('DEBUG', "Quepasa Response: " . substr($response, 0, 200));
                
                if ($messageSent) {
                    apiLog('INFO', '✅ Mensagem enviada via Quepasa');
                } else {
                    apiLog('WARNING', "⚠️ Quepasa falhou (HTTP {$httpCode})");
                }
            } else {
                apiLog('WARNING', '⚠️ Quepasa não configurado ou provider diferente');
            }
            
            if ($messageSent) {
                $stmt = $db->prepare("UPDATE messages SET status = 'delivered' WHERE id = ?");
                $stmt->execute([$messageId]);
                apiLog('DEBUG', 'Status atualizado para delivered');
            }
            
            apiLog('INFO', '✅ MENSAGEM PROCESSADA COM SUCESSO');
            apiLog('INFO', "Message ID: {$messageId}, Conversation ID: {$conversationId}, Contact ID: {$contactId}");
            apiLog('INFO', '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            
            successResponse([
                'message_id' => (string) $messageId,
                'conversation_id' => (string) $conversationId,
                'contact_id' => (string) $contactId,
                'status' => $messageSent ? 'sent' : 'queued'
            ], 'Mensagem enviada', 201);
            break;
            
        case 'messagesListByConversation':
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM messages WHERE conversation_id = ?");
            $stmt->execute([$params['id']]);
            $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            $stmt = $db->prepare("
                SELECT * FROM messages 
                WHERE conversation_id = ?
                ORDER BY created_at ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$params['id'], $perPage, $offset]);
            $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            paginatedResponse($messages, $total, $page, $perPage);
            break;
        
        // ============== CONVERSAS ==============
        case 'conversationsList':
            $status = $_GET['status'] ?? null;
            $agentId = $_GET['agent_id'] ?? null;
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $where = ['1=1'];
            $queryParams = [];
            
            if ($status) {
                $where[] = 'status = ?';
                $queryParams[] = $status;
            }
            if ($agentId) {
                $where[] = 'agent_id = ?';
                $queryParams[] = $agentId;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM conversations WHERE {$whereClause}");
            $stmt->execute($queryParams);
            $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE {$whereClause}
                ORDER BY updated_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($queryParams, [$perPage, $offset]));
            $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            paginatedResponse($conversations, $total, $page, $perPage);
            break;
            
        case 'conversationsCreate':
            $input = getJsonBody();
            
            if (empty($input['contact_id'])) {
                errorResponse('contact_id é obrigatório', 'VALIDATION_ERROR', 422);
            }
            
            $stmt = $db->prepare("
                INSERT INTO conversations (contact_id, channel, status, agent_id, department_id, funnel_id, stage_id, created_at, updated_at)
                VALUES (?, ?, 'open', ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $input['contact_id'],
                $input['channel'] ?? 'whatsapp',
                $input['agent_id'] ?? null,
                $input['department_id'] ?? null,
                $input['funnel_id'] ?? null,
                $input['stage_id'] ?? null
            ]);
            
            $id = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$id]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Conversa criada', 201);
            break;
            
        case 'conversationsShow':
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$params['id']]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conversation) {
                errorResponse('Conversa não encontrada', 'NOT_FOUND', 404);
            }
            
            successResponse($conversation);
            break;
            
        case 'conversationsUpdate':
            $input = getJsonBody();
            
            $fields = [];
            $values = [];
            
            foreach (['status', 'agent_id', 'department_id', 'funnel_id', 'stage_id'] as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $values[] = $input[$field];
                }
            }
            
            if (empty($fields)) {
                errorResponse('Nenhum campo para atualizar', 'VALIDATION_ERROR', 422);
            }
            
            $fields[] = "updated_at = NOW()";
            $values[] = $params['id'];
            
            $stmt = $db->prepare("UPDATE conversations SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);
            
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$params['id']]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Conversa atualizada');
            break;
            
        case 'conversationsDelete':
            $stmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
            $stmt->execute([$params['id']]);
            successResponse(null, 'Conversa removida', 204);
            break;
            
        case 'conversationsAssign':
            $input = getJsonBody();
            
            if (empty($input['agent_id'])) {
                errorResponse('agent_id é obrigatório', 'VALIDATION_ERROR', 422);
            }
            
            $stmt = $db->prepare("UPDATE conversations SET agent_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$input['agent_id'], $params['id']]);
            
            successResponse(['conversation_id' => $params['id'], 'agent_id' => $input['agent_id']], 'Conversa atribuída');
            break;
            
        case 'conversationsClose':
            $stmt = $db->prepare("UPDATE conversations SET status = 'closed', closed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$params['id']]);
            successResponse(['conversation_id' => $params['id'], 'status' => 'closed'], 'Conversa encerrada');
            break;
            
        case 'conversationsReopen':
            $stmt = $db->prepare("UPDATE conversations SET status = 'open', closed_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$params['id']]);
            successResponse(['conversation_id' => $params['id'], 'status' => 'open'], 'Conversa reaberta');
            break;
            
        case 'conversationsMoveStage':
            $input = getJsonBody();
            
            $stmt = $db->prepare("UPDATE conversations SET funnel_id = ?, stage_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$input['funnel_id'] ?? null, $input['stage_id'] ?? null, $params['id']]);
            
            successResponse(['conversation_id' => $params['id']], 'Conversa movida');
            break;
        
        // ============== CONTATOS ==============
        case 'contactsList':
            $search = $_GET['search'] ?? null;
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(100, max(1, intval($_GET['per_page'] ?? 20)));
            $offset = ($page - 1) * $perPage;
            
            $where = ['1=1'];
            $queryParams = [];
            
            if ($search) {
                $where[] = '(name LIKE ? OR phone_number LIKE ? OR email LIKE ?)';
                $queryParams[] = "%{$search}%";
                $queryParams[] = "%{$search}%";
                $queryParams[] = "%{$search}%";
            }
            
            $whereClause = implode(' AND ', $where);
            
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM contacts WHERE {$whereClause}");
            $stmt->execute($queryParams);
            $total = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
            
            $stmt = $db->prepare("
                SELECT * FROM contacts 
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute(array_merge($queryParams, [$perPage, $offset]));
            
            paginatedResponse($stmt->fetchAll(\PDO::FETCH_ASSOC), $total, $page, $perPage);
            break;
            
        case 'contactsCreate':
            $input = getJsonBody();
            
            $stmt = $db->prepare("
                INSERT INTO contacts (name, phone_number, email, channel, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $input['name'] ?? null,
                $input['phone_number'] ?? null,
                $input['email'] ?? null,
                $input['channel'] ?? 'whatsapp'
            ]);
            
            $id = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
            $stmt->execute([$id]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Contato criado', 201);
            break;
            
        case 'contactsShow':
            $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
            $stmt->execute([$params['id']]);
            $contact = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$contact) {
                errorResponse('Contato não encontrado', 'NOT_FOUND', 404);
            }
            
            successResponse($contact);
            break;
            
        case 'contactsUpdate':
            $input = getJsonBody();
            
            $fields = [];
            $values = [];
            
            foreach (['name', 'phone_number', 'email'] as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $values[] = $input[$field];
                }
            }
            
            if (!empty($fields)) {
                $fields[] = "updated_at = NOW()";
                $values[] = $params['id'];
                
                $stmt = $db->prepare("UPDATE contacts SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($values);
            }
            
            $stmt = $db->prepare("SELECT * FROM contacts WHERE id = ?");
            $stmt->execute([$params['id']]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Contato atualizado');
            break;
            
        case 'contactsDelete':
            $stmt = $db->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$params['id']]);
            successResponse(null, 'Contato removido', 204);
            break;
            
        case 'contactsConversations':
            $stmt = $db->prepare("SELECT * FROM conversations WHERE contact_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$params['id']]);
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
        
        // ============== AGENTES ==============
        case 'agentsList':
            $stmt = $db->prepare("
                SELECT id, name, email, is_active, role_id, created_at
                FROM users 
                WHERE role_id IN (SELECT id FROM roles WHERE slug IN ('agent', 'admin', 'supervisor'))
                ORDER BY name
            ");
            $stmt->execute();
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
            
        case 'agentsShow':
            $stmt = $db->prepare("SELECT id, name, email, is_active, role_id, created_at FROM users WHERE id = ?");
            $stmt->execute([$params['id']]);
            $agent = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$agent) {
                errorResponse('Agente não encontrado', 'NOT_FOUND', 404);
            }
            
            successResponse($agent);
            break;
            
        case 'agentsStats':
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_conversations,
                    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_conversations,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_conversations
                FROM conversations
                WHERE agent_id = ?
            ");
            $stmt->execute([$params['id']]);
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC));
            break;
        
        // ============== SETORES ==============
        case 'departmentsList':
            $stmt = $db->prepare("SELECT * FROM departments ORDER BY name");
            $stmt->execute();
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
            
        case 'departmentsShow':
            $stmt = $db->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$params['id']]);
            $department = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$department) {
                errorResponse('Setor não encontrado', 'NOT_FOUND', 404);
            }
            
            successResponse($department);
            break;
        
        // ============== FUNIS ==============
        case 'funnelsList':
            $stmt = $db->prepare("SELECT * FROM funnels ORDER BY name");
            $stmt->execute();
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
            
        case 'funnelsShow':
            $stmt = $db->prepare("SELECT * FROM funnels WHERE id = ?");
            $stmt->execute([$params['id']]);
            $funnel = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$funnel) {
                errorResponse('Funil não encontrado', 'NOT_FOUND', 404);
            }
            
            successResponse($funnel);
            break;
            
        case 'funnelsStages':
            $stmt = $db->prepare("SELECT * FROM funnel_stages WHERE funnel_id = ? ORDER BY stage_order");
            $stmt->execute([$params['id']]);
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
            
        case 'funnelsConversations':
            $stmt = $db->prepare("SELECT * FROM conversations WHERE funnel_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$params['id']]);
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
        
        // ============== TAGS ==============
        case 'tagsList':
            $stmt = $db->prepare("SELECT * FROM tags ORDER BY name");
            $stmt->execute();
            successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
            break;
            
        case 'tagsCreate':
            $input = getJsonBody();
            
            if (empty($input['name'])) {
                errorResponse('name é obrigatório', 'VALIDATION_ERROR', 422);
            }
            
            $stmt = $db->prepare("INSERT INTO tags (name, color, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$input['name'], $input['color'] ?? '#667eea']);
            
            $id = $db->lastInsertId();
            $stmt = $db->prepare("SELECT * FROM tags WHERE id = ?");
            $stmt->execute([$id]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Tag criada', 201);
            break;
            
        case 'tagsShow':
            $stmt = $db->prepare("SELECT * FROM tags WHERE id = ?");
            $stmt->execute([$params['id']]);
            $tag = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$tag) {
                errorResponse('Tag não encontrada', 'NOT_FOUND', 404);
            }
            
            successResponse($tag);
            break;
            
        case 'tagsUpdate':
            $input = getJsonBody();
            
            $fields = [];
            $values = [];
            
            if (isset($input['name'])) {
                $fields[] = "name = ?";
                $values[] = $input['name'];
            }
            if (isset($input['color'])) {
                $fields[] = "color = ?";
                $values[] = $input['color'];
            }
            
            if (!empty($fields)) {
                $values[] = $params['id'];
                $stmt = $db->prepare("UPDATE tags SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($values);
            }
            
            $stmt = $db->prepare("SELECT * FROM tags WHERE id = ?");
            $stmt->execute([$params['id']]);
            
            successResponse($stmt->fetch(\PDO::FETCH_ASSOC), 'Tag atualizada');
            break;
            
        case 'tagsDelete':
            $stmt = $db->prepare("DELETE FROM tags WHERE id = ?");
            $stmt->execute([$params['id']]);
            successResponse(null, 'Tag removida', 204);
            break;
            
        default:
            errorResponse('Handler não implementado', 'SERVER_ERROR', 500);
    }
    
} catch (\PDOException $e) {
    $logFile = __DIR__ . '/../storage/logs/api.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ❌ EXCEÇÃO PDO (BANCO DE DADOS)\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Mensagem: " . $e->getMessage() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Arquivo: " . $e->getFile() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Linha: " . $e->getLine() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [DEBUG] Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
    
    error_log("API Database Error: " . $e->getMessage());
    errorResponse('Erro no banco de dados', 'SERVER_ERROR', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (\Exception $e) {
    $logFile = __DIR__ . '/../storage/logs/api.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ❌ EXCEÇÃO GENÉRICA\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Mensagem: " . $e->getMessage() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Arquivo: " . $e->getFile() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] Linha: " . $e->getLine() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [DEBUG] Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
    @file_put_contents($logFile, "[{$timestamp}] [ERROR] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n", FILE_APPEND);
    
    error_log("API Error: " . $e->getMessage());
    errorResponse('Erro interno', 'SERVER_ERROR', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
