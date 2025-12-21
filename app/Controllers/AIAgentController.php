<?php
/**
 * Controller AIAgentController
 * Gerenciamento de Agentes de IA
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AIAgentService;
use App\Models\AITool;

class AIAgentController
{
    /**
     * Listar agentes de IA
     */
    public function index(): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $filters = [
            'agent_type' => Request::get('agent_type'),
            'enabled' => Request::get('enabled'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $agents = AIAgentService::list($filters);
            $allTools = AITool::getAllActive();
            
            Response::view('ai-agents/index', [
                'agents' => $agents,
                'allTools' => $allTools,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('ai-agents/index', [
                'agents' => [],
                'allTools' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar agente específico
     */
    public function show(int $id): void
    {
        $isAjax = Request::isAjax() || Request::get('format') === 'json';
        
        // Se for AJAX, limpar qualquer output anterior e desabilitar display de erros
        if ($isAjax) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            ini_set('display_errors', '0');
            error_reporting(0);
        }
        
        // Verificar permissões antes, mas retornar JSON se for AJAX
        if (!Permission::can('ai_agents.view')) {
            if ($isAjax) {
                Response::json([
                    'success' => false,
                    'message' => 'Sem permissão para visualizar agentes de IA'
                ], 403);
                return;
            }
            Permission::abortIfCannot('ai_agents.view');
        }
        
        try {
            $agent = AIAgentService::get($id);
            if (!$agent) {
                if ($isAjax) {
                    Response::json([
                        'success' => false,
                        'message' => 'Agente de IA não encontrado'
                    ], 404);
                    return;
                }
                Response::notFound('Agente de IA não encontrado');
                return;
            }
            
            // Se for requisição AJAX, retornar JSON (sem conversas para evitar erros)
            if ($isAjax) {
                // Obter tools do agente
                $agentTools = AIAgent::getTools($id);
                $allTools = AITool::getAllActive();
                
                Response::json([
                    'success' => true,
                    'agent' => array_merge($agent, [
                        'tools' => $agentTools
                    ]),
                    'allTools' => $allTools
                ]);
                return;
            }
            
            // Para requisições não-AJAX, buscar conversas também
            $allTools = AITool::getAllActive();
            $conversations = [];
            try {
                $conversations = \App\Models\AIConversation::getByAgent($id, 20);
            } catch (\Exception $e) {
                error_log("Erro ao buscar conversas do agente {$id}: " . $e->getMessage());
                // Continuar sem conversas
            }
            
            Response::view('ai-agents/show', [
                'agent' => $agent,
                'allTools' => $allTools,
                'conversations' => $conversations
            ]);
        } catch (\Exception $e) {
            if ($isAjax) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
                return;
            }
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar agente de IA
     */
    public function store(): void
    {
        Permission::abortIfCannot('ai_agents.create');
        
        try {
            $data = Request::post();
            
            // Separar tools dos dados do agente
            $tools = [];
            if (isset($data['tools']) && is_array($data['tools'])) {
                $tools = array_map('intval', $data['tools']);
                unset($data['tools']);
            }
            
            // Criar agente
            $agentId = AIAgentService::create($data);
            
            // Adicionar tools ao agente
            if (!empty($tools)) {
                foreach ($tools as $toolId) {
                    AIAgentService::addTool($agentId, $toolId, [], true);
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Agente de IA criado com sucesso!',
                'id' => $agentId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar agente de IA
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            $data = Request::post();
            
            // Separar tools dos dados do agente
            $tools = [];
            if (isset($data['tools']) && is_array($data['tools'])) {
                $tools = array_map('intval', $data['tools']);
                unset($data['tools']);
            }
            
            // Atualizar agente
            if (!AIAgentService::update($id, $data)) {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar agente de IA'
                ], 500);
                return;
            }
            
            // Atualizar tools do agente
            if (isset($tools)) {
                // Obter tools atuais do agente
                $currentTools = AIAgent::getTools($id);
                $currentToolIds = array_column($currentTools, 'id');
                
                // Remover tools que não estão mais na lista
                foreach ($currentToolIds as $toolId) {
                    if (!in_array($toolId, $tools)) {
                        AIAgentService::removeTool($id, $toolId);
                    }
                }
                
                // Adicionar novas tools
                foreach ($tools as $toolId) {
                    if (!in_array($toolId, $currentToolIds)) {
                        AIAgentService::addTool($id, $toolId, [], true);
                    }
                }
            }
            
            Response::json([
                'success' => true,
                'message' => 'Agente de IA atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Excluir agente de IA
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('ai_agents.delete');
        
        try {
            if (\App\Models\AIAgent::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Agente de IA excluído com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao excluir agente de IA'
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
     * Adicionar tool ao agente
     */
    public function addTool(int $id): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            $toolId = Request::post('tool_id');
            $config = Request::post('config', []);
            $enabled = Request::post('enabled', true);
            
            if (AIAgentService::addTool($id, (int)$toolId, $config, $enabled)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool adicionada ao agente com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao adicionar tool'
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
     * Remover tool do agente
     */
    public function removeTool(int $id, int $toolId): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            if (AIAgentService::removeTool($id, $toolId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool removida do agente com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover tool'
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
     * Obter estatísticas do agente
     */
    public function getStats(int $id): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            $startDate = Request::get('start_date');
            $endDate = Request::get('end_date');
            
            $stats = \App\Models\AIConversation::getAgentStats($id, $startDate, $endDate);
            
            Response::json([
                'success' => true,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

