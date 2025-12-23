<?php
/**
 * Controller AIToolController
 * Gerenciamento de Tools de IA
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AIToolService;
use App\Services\AIToolValidationService;

class AIToolController
{
    /**
     * Listar tools
     */
    public function index(): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        $filters = [
            'tool_type' => Request::get('tool_type'),
            'enabled' => Request::get('enabled'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $tools = AIToolService::list($filters);
            
            // Carregar dados para tools de escalação e funil
            $departments = \App\Models\Department::all();
            $funnels = \App\Models\Funnel::whereActive();
            $agents = \App\Helpers\Database::fetchAll(
                "SELECT id, name, email FROM users WHERE status = 'active' AND role != 'super_admin' ORDER BY name"
            );
            
            Response::view('ai-tools/index', [
                'tools' => $tools,
                'filters' => $filters,
                'departments' => $departments,
                'funnels' => $funnels,
                'agents' => $agents
            ]);
        } catch (\Exception $e) {
            Response::view('ai-tools/index', [
                'tools' => [],
                'filters' => $filters,
                'departments' => [],
                'funnels' => [],
                'agents' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar tool específica
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $tool = AIToolService::get($id);
            if (!$tool) {
                Response::notFound('Tool não encontrada');
                return;
            }
            
            // Decodificar JSON fields
            if (is_string($tool['function_schema'])) {
                $tool['function_schema'] = json_decode($tool['function_schema'], true);
            }
            if (is_string($tool['config'])) {
                $tool['config'] = json_decode($tool['config'], true);
            }
            
            // Carregar dados para tools de escalação e funil
            $departments = \App\Models\Department::all();
            $funnels = \App\Models\Funnel::whereActive();
            $agents = \App\Helpers\Database::fetchAll(
                "SELECT id, name, email FROM users WHERE status = 'active' AND role != 'super_admin' ORDER BY name"
            );
            
            Response::view('ai-tools/show', [
                'tool' => $tool,
                'departments' => $departments,
                'funnels' => $funnels,
                'agents' => $agents
            ]);
        } catch (\Exception $e) {
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar tool
     */
    public function store(): void
    {
        Permission::abortIfCannot('ai_tools.create');
        
        try {
            $data = Request::post();
            $toolId = AIToolService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Tool criada com sucesso!',
                'id' => $toolId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar tool
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('ai_tools.edit');
        
        try {
            $data = Request::post();
            if (AIToolService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar tool'
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
     * Excluir tool
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('ai_tools.delete');
        
        try {
            if (\App\Models\AITool::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool excluída com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao excluir tool'
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
     * Validar todas as tools
     */
    public function validate(): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $report = AIToolValidationService::generateReport();
            
            Response::json([
                'success' => true,
                'report' => $report
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar tool específica
     */
    public function validateTool(int $id): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $tool = AIToolService::get($id);
            if (!$tool) {
                Response::json([
                    'success' => false,
                    'message' => 'Tool não encontrada'
                ], 404);
                return;
            }
            
            $validation = AIToolValidationService::validateTool($tool);
            
            Response::json([
                'success' => true,
                'tool_id' => $id,
                'tool_name' => $tool['name'],
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar webhook N8N
     */
    public function testN8N(int $id): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $tool = AIToolService::get($id);
            if (!$tool) {
                Response::json([
                    'success' => false,
                    'message' => 'Tool não encontrada'
                ], 404);
                return;
            }
            
            if ($tool['tool_type'] !== 'n8n') {
                Response::json([
                    'success' => false,
                    'message' => 'Esta funcionalidade é apenas para tools do tipo N8N'
                ], 400);
                return;
            }
            
            $data = Request::post();
            $webhookId = $data['webhook_id'] ?? null;
            $method = 'POST'; // Sempre POST, igual à execução real
            $requestData = $data['data'] ?? [];
            $queryParams = $data['query_params'] ?? [];
            $headers = $data['headers'] ?? [];
            
            // Simular o formato exato que a IA envia em produção
            // A IA envia os argumentos + metadados da conversa
            $simulatedPayload = [
                // Metadados da conversa (sempre presentes)
                'conversation_id' => $requestData['conversation_id'] ?? 'test-' . time(),
                'session_id' => $requestData['session_id'] ?? $requestData['conversation_id'] ?? 'test-' . time(),
                'thread_id' => $requestData['thread_id'] ?? $requestData['conversation_id'] ?? 'test-' . time(),
            ];
            
            // Adicionar mensagem se fornecida
            if (!empty($requestData['message'])) {
                $simulatedPayload['message'] = $requestData['message'];
            }
            if (!empty($requestData['client_message'])) {
                $simulatedPayload['client_message'] = $requestData['client_message'];
            }
            if (!empty($requestData['text'])) {
                $simulatedPayload['text'] = $requestData['text'];
            }
            
            // Adicionar dados do contato simulado
            $simulatedPayload['contact'] = [
                'name' => 'Cliente Teste',
                'email' => 'teste@exemplo.com',
                'phone' => '11999999999'
            ];
            
            // Mesclar com dados adicionais (argumentos extras da função)
            foreach ($requestData as $key => $value) {
                if (!isset($simulatedPayload[$key])) {
                    $simulatedPayload[$key] = $value;
                }
            }
            
            $requestData = $simulatedPayload;
            
            // Obter configuração da tool
            $config = is_string($tool['config']) 
                ? json_decode($tool['config'], true) 
                : ($tool['config'] ?? []);
            
            $n8nUrl = $config['n8n_url'] ?? null;
            $defaultWebhookId = $config['webhook_id'] ?? null;
            $webhookPath = $config['webhook_path'] ?? '/webhook';
            $apiKey = $config['api_key'] ?? null;
            $timeout = (int)($config['timeout'] ?? 60);
            $customHeaders = $config['custom_headers'] ?? [];
            
            if (!$n8nUrl) {
                Response::json([
                    'success' => false,
                    'message' => 'URL do N8N não configurada na tool'
                ], 400);
                return;
            }
            
            $finalWebhookId = $webhookId ?? $defaultWebhookId;
            if (!$finalWebhookId) {
                Response::json([
                    'success' => false,
                    'message' => 'ID do webhook não fornecido e não configurado na tool'
                ], 400);
                return;
            }
            
            // Construir URL
            if (filter_var($finalWebhookId, FILTER_VALIDATE_URL)) {
                $webhookUrl = $finalWebhookId;
            } else {
                $webhookUrl = rtrim($n8nUrl, '/') . rtrim($webhookPath, '/') . '/' . ltrim($finalWebhookId, '/');
            }
            
            // Adicionar query params se método for GET
            if ($method === 'GET' && !empty($queryParams)) {
                $separator = strpos($webhookUrl, '?') !== false ? '&' : '?';
                $webhookUrl .= $separator . http_build_query($queryParams);
            }
            
            // Preparar headers
            $requestHeaders = [];
            
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $requestHeaders[] = 'Content-Type: application/json';
            }
            
            if ($apiKey) {
                $requestHeaders[] = 'X-N8N-API-KEY: ' . $apiKey;
            }
            
            foreach ($customHeaders as $key => $value) {
                $requestHeaders[] = $key . ': ' . $value;
            }
            
            foreach ($headers as $key => $value) {
                $requestHeaders[] = $key . ': ' . $value;
            }
            
            // Fazer requisição
            $ch = curl_init($webhookUrl);
            
            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5
            ];
            
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($requestData)) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($requestData, JSON_UNESCAPED_UNICODE);
            }
            
            curl_setopt_array($ch, $curlOptions);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $curlInfo = curl_getinfo($ch);
            curl_close($ch);
            
            if ($error) {
                Response::json([
                    'success' => false,
                    'message' => 'Erro de conexão: ' . $error,
                    'url' => $webhookUrl,
                    'method' => $method
                ], 500);
                return;
            }
            
            // Tentar decodificar JSON
            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $responseData = [
                    'raw_response' => $response,
                    'content_type' => 'text/plain'
                ];
            }
            
            Response::json([
                'success' => $httpCode >= 200 && $httpCode < 300,
                'http_code' => $httpCode,
                'url' => $webhookUrl,
                'method' => $method,
                'request' => [
                    'data' => $requestData,
                    'query_params' => $queryParams,
                    'headers' => $requestHeaders
                ],
                'response' => $responseData,
                'curl_info' => [
                    'total_time' => $curlInfo['total_time'] ?? 0,
                    'size_download' => $curlInfo['size_download'] ?? 0
                ]
            ]);
            
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

