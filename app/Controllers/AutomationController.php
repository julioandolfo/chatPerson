<?php
/**
 * Controller de Automações
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AutomationService;
use App\Models\Automation;
use App\Models\AutomationNode;
use App\Models\WhatsAppAccount;
use App\Models\Funnel;
use App\Models\User;

class AutomationController
{
    /**
     * Listar automações
     */
    public function index(): void
    {
        Permission::abortIfCannot('automations.view');
        
        try {
            // Buscar automações com informações de funil/estágio
            $sql = "SELECT a.*, f.name as funnel_name, fs.name as stage_name
                    FROM automations a
                    LEFT JOIN funnels f ON a.funnel_id = f.id
                    LEFT JOIN funnel_stages fs ON a.stage_id = fs.id
                    ORDER BY a.created_at DESC";
            $automations = \App\Helpers\Database::fetchAll($sql);
            
            Response::view('automations/index', ['automations' => $automations]);
        } catch (\Exception $e) {
            Response::view('automations/index', [
                'automations' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar editor de automação
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('automations.view');
        
        try {
            $automation = Automation::findWithNodes($id);
            if (!$automation) {
                Response::notFound('Automação não encontrada');
                return;
            }
            
            $whatsappAccounts = WhatsAppAccount::getActive();
            $allFunnels = Funnel::whereActive();
            $agents = User::getActiveAgents();
            $nodeTypes = AutomationNode::getNodeTypes();
            
            // Obter estágios do funil vinculado (se houver)
            $stages = [];
            if (!empty($automation['funnel_id'])) {
                $stages = Funnel::getStages($automation['funnel_id']);
            }
            
            // Buscar informações de funil/estágio
            if (!empty($automation['funnel_id'])) {
                $funnel = Funnel::find($automation['funnel_id']);
                $automation['funnel_name'] = $funnel['name'] ?? null;
            }
            if (!empty($automation['stage_id'])) {
                $stage = \App\Models\FunnelStage::find($automation['stage_id']);
                $automation['stage_name'] = $stage['name'] ?? null;
            }
            
            Response::view('automations/show', [
                'automation' => $automation,
                'whatsappAccounts' => $whatsappAccounts,
                'funnels' => $allFunnels,
                'stages' => $stages,
                'agents' => $agents,
                'nodeTypes' => $nodeTypes
            ]);
        } catch (\Exception $e) {
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar automação
     */
    public function store(): void
    {
        Permission::abortIfCannot('automations.create');
        
        try {
            $data = Request::post();
            $automationId = AutomationService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Automação criada com sucesso!',
                'id' => $automationId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar automação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar automação
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('automations.edit');
        
        try {
            $data = Request::post();
            if (AutomationService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Automação atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar automação.'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Criar nó
     */
    public function createNode(int $id): void
    {
        Permission::abortIfCannot('automations.edit');
        
        try {
            $data = Request::post();
            $nodeId = AutomationService::createNode($id, $data);
            
            Response::json([
                'success' => true,
                'message' => 'Nó criado com sucesso!',
                'id' => $nodeId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar nó
     */
    public function updateNode(int $id, int $nodeId): void
    {
        Permission::abortIfCannot('automations.edit');
        
        try {
            $data = Request::post();
            if (AutomationService::updateNode($nodeId, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Nó atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar nó.'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar nó
     */
    public function deleteNode(int $id, int $nodeId): void
    {
        Permission::abortIfCannot('automations.edit');
        
        try {
            if (AutomationService::deleteNode($nodeId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Nó deletado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar nó.'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Salvar layout completo (todos os nós de uma vez)
     */
    public function saveLayout(int $id): void
    {
        // Registrar shutdown handler para capturar erros fatais
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                if (ob_get_level()) {
                    ob_clean();
                }
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro fatal no servidor: ' . $error['message'],
                    'file' => $error['file'] ?? '',
                    'line' => $error['line'] ?? 0
                ]);
                exit;
            }
        });
        
        // Suprimir warnings e notices que possam gerar HTML
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        
        // Limpar qualquer output buffer anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Iniciar novo buffer limpo
        ob_start();
        
        Permission::abortIfCannot('automations.edit');
        
        try {
            // Log do conteúdo bruto recebido
            $rawInput = file_get_contents('php://input');
            \App\Helpers\Logger::automation('saveLayout - Raw input recebido: ' . substr($rawInput, 0, 500));
            \App\Helpers\Logger::automation('saveLayout - Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'N/A'));
            
            $nodes = Request::post('nodes', []);
            
            \App\Helpers\Logger::automation('saveLayout - Nós após Request::post: ' . json_encode($nodes));
            \App\Helpers\Logger::automation('saveLayout - Tipo de nodes: ' . gettype($nodes));
            \App\Helpers\Logger::automation('saveLayout - É array? ' . (is_array($nodes) ? 'SIM' : 'NÃO'));
            
            if (!is_array($nodes)) {
                throw new \InvalidArgumentException('Dados inválidos: nodes não é um array');
            }
            
            // Obter nós existentes no banco
            $oldNodes = Automation::getNodes($id);
            $oldNodeIds = array_map('intval', array_column($oldNodes, 'id'));
            $sentNodeIds = [];
            
            \App\Helpers\Logger::automation('saveLayout - Nós antigos no banco: ' . json_encode($oldNodeIds));
            
            // Mapear IDs temporários para IDs reais do banco
            $tempIdToRealId = [];
            
            // Primeiro passo: criar/atualizar nós e criar mapeamento
            foreach ($nodes as $index => $nodeData) {
                try {
                    if (!is_array($nodeData)) {
                        \App\Helpers\Logger::automation("saveLayout - ERRO: Nó {$index} não é array: " . gettype($nodeData));
                        continue;
                    }
                    
                    $tempId = $nodeData['id'] ?? null;
                    
                    // Verificar se tem ID e se existe no banco
                    $nodeId = null;
                    if (isset($nodeData['id'])) {
                        if (is_numeric($nodeData['id'])) {
                            $nodeId = (int)$nodeData['id'];
                        } elseif (is_string($nodeData['id']) && is_numeric($nodeData['id'])) {
                            $nodeId = (int)$nodeData['id'];
                        }
                    }
                    
                    if ($nodeId && in_array($nodeId, $oldNodeIds, true)) {
                        // Atualizar nó existente
                        \App\Helpers\Logger::automation("saveLayout - Atualizando nó existente: {$nodeId}");
                        AutomationService::updateNode($nodeId, [
                            'node_type' => $nodeData['node_type'] ?? 'unknown',
                            'node_data' => $nodeData['node_data'] ?? [],
                            'position_x' => isset($nodeData['position_x']) ? (int)$nodeData['position_x'] : 0,
                            'position_y' => isset($nodeData['position_y']) ? (int)$nodeData['position_y'] : 0
                        ]);
                        $sentNodeIds[] = $nodeId;
                        
                        // Mapear ID temporário para ID real (se for temporário)
                        if ($tempId && (is_string($tempId) && strpos($tempId, 'node_') === 0)) {
                            $tempIdToRealId[$tempId] = $nodeId;
                        }
                    } else {
                        // Criar novo nó
                        \App\Helpers\Logger::automation("saveLayout - Criando novo nó (ID recebido: " . ($nodeData['id'] ?? 'null') . ")");
                        $newNodeId = AutomationService::createNode($id, $nodeData);
                        \App\Helpers\Logger::automation("saveLayout - Novo nó criado com ID: {$newNodeId}");
                        $sentNodeIds[] = $newNodeId;
                        
                        // Mapear ID temporário para ID real
                        if ($tempId) {
                            $tempIdToRealId[$tempId] = $newNodeId;
                        }
                    }
                } catch (\Exception $e) {
                    \App\Helpers\Logger::automation("saveLayout - Erro ao processar nó {$index}: " . $e->getMessage());
                    throw new \Exception("Erro ao processar nó {$index}: " . $e->getMessage());
                }
            }
            
            // Segundo passo: atualizar conexões com IDs reais
            if (!empty($tempIdToRealId)) {
                \App\Helpers\Logger::automation('saveLayout - Mapeamento de IDs: ' . json_encode($tempIdToRealId));
                
                foreach ($sentNodeIds as $realNodeId) {
                    $node = AutomationNode::find($realNodeId);
                    if (!$node) {
                        continue;
                    }
                    
                    // Decodificar node_data se necessário
                    $nodeData = $node['node_data'];
                    if (is_string($nodeData)) {
                        $nodeData = json_decode($nodeData, true);
                    }
                    
                    if (empty($nodeData) || !is_array($nodeData)) {
                        $nodeData = [];
                    }
                    
                    // Verificar se há conexões para atualizar
                    if (empty($nodeData['connections']) || !is_array($nodeData['connections'])) {
                        continue;
                    }
                    
                    $connections = $nodeData['connections'];
                    $updated = false;
                    
                    foreach ($connections as &$connection) {
                        if (isset($connection['target_node_id'])) {
                            $targetId = $connection['target_node_id'];
                            
                            // Se for ID temporário, mapear para ID real
                            if (is_string($targetId) && isset($tempIdToRealId[$targetId])) {
                                $oldId = $targetId;
                                $connection['target_node_id'] = $tempIdToRealId[$targetId];
                                $updated = true;
                                \App\Helpers\Logger::automation("saveLayout - Atualizando conexão no nó {$realNodeId}: {$oldId} -> {$tempIdToRealId[$targetId]}");
                            }
                        }
                    }
                    
                    // Se houve atualização, salvar o nó novamente
                    if ($updated) {
                        $nodeData['connections'] = $connections;
                        AutomationService::updateNode($realNodeId, [
                            'node_data' => $nodeData
                        ]);
                        \App\Helpers\Logger::automation("saveLayout - Conexões atualizadas no nó {$realNodeId}: " . json_encode($connections));
                    }
                }
            }
            
            // Deletar nós que não foram enviados (removidos pelo usuário)
            $nodesToDelete = array_diff($oldNodeIds, $sentNodeIds);
            if (!empty($nodesToDelete)) {
                \App\Helpers\Logger::automation('saveLayout - Deletando nós: ' . json_encode($nodesToDelete));
                foreach ($nodesToDelete as $nodeIdToDelete) {
                    AutomationNode::delete($nodeIdToDelete);
                }
            }
            
            \App\Helpers\Logger::automation('saveLayout - Layout salvo com sucesso. Total de nós: ' . count($sentNodeIds));
            \App\Helpers\Logger::automation('saveLayout - IDs dos nós salvos: ' . json_encode($sentNodeIds));
            
            // Limpar buffer antes de enviar resposta
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            // Enviar resposta JSON pura
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Layout salvo com sucesso!',
                'nodes_count' => count($sentNodeIds)
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        } catch (\Exception $e) {
            // Limpar qualquer output antes de enviar JSON
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            \App\Helpers\Logger::automation('saveLayout - Erro: ' . $e->getMessage());
            \App\Helpers\Logger::automation('saveLayout - Arquivo: ' . $e->getFile() . ' - Linha: ' . $e->getLine());
            \App\Helpers\Logger::automation('saveLayout - Stack trace: ' . $e->getTraceAsString());
            
            // Garantir que apenas JSON seja retornado
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /**
     * Obter logs de execução
     */
    public function getLogs(int $id): void
    {
        Permission::abortIfCannot('automations.view');
        
        try {
            $logs = \App\Models\AutomationExecution::getByAutomation($id, 50);
            $stats = \App\Models\AutomationExecution::getStats($id);
            
            Response::json([
                'success' => true,
                'logs' => $logs,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'logs' => [],
                'stats' => null
            ], 500);
        }
    }
    
    /**
     * Obter variáveis disponíveis (JSON)
     */
    public function getVariables(): void
    {
        Permission::abortIfCannot('automations.view');
        
        $variables = [
            'contact' => [
                'name' => 'Nome do contato',
                'phone' => 'Telefone do contato',
                'email' => 'Email do contato'
            ],
            'agent' => [
                'name' => 'Nome do agente atribuído'
            ],
            'conversation' => [
                'id' => 'ID da conversa',
                'subject' => 'Assunto da conversa'
            ],
            'date' => 'Data atual (dd/mm/yyyy)',
            'time' => 'Hora atual (HH:mm)',
            'datetime' => 'Data e hora (dd/mm/yyyy HH:mm)'
        ];
        
        Response::json([
            'success' => true,
            'variables' => $variables
        ]);
    }
    
    /**
     * Testar automação em modo teste
     */
    public function test(int $id): void
    {
        Permission::abortIfCannot('automations.view');
        
        try {
            $conversationId = Request::get('conversation_id');
            $conversationId = $conversationId ? (int)$conversationId : null;
            
            $testResult = AutomationService::testAutomation($id, $conversationId);
            
            Response::json([
                'success' => true,
                'test_result' => $testResult
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Preview de variáveis em mensagem
     */
    public function previewVariables(): void
    {
        Permission::abortIfCannot('automations.view');
        
        try {
            $message = Request::post('message', '');
            $conversationId = Request::post('conversation_id');
            $conversationId = $conversationId ? (int)$conversationId : null;
            
            if (empty($message)) {
                Response::json([
                    'success' => false,
                    'message' => 'Mensagem não informada'
                ], 400);
                return;
            }
            
            $preview = AutomationService::previewVariables($message, $conversationId);
            
            Response::json([
                'success' => true,
                'preview' => $preview
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
