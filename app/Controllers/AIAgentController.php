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
use App\Models\AIAgent;
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
        
        // Log para debug
        error_log("AIAgentController::show - ID: {$id}, isAjax: " . ($isAjax ? 'true' : 'false'));
        
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
            error_log("AIAgentController::show - Sem permissão");
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
            error_log("AIAgentController::show - Buscando agente {$id}");
            $agent = AIAgentService::get($id);
            
            if (!$agent) {
                error_log("AIAgentController::show - Agente não encontrado");
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
            
            error_log("AIAgentController::show - Agente encontrado: " . ($agent['name'] ?? 'sem nome'));
            
            // Se for requisição AJAX, retornar JSON (sem conversas para evitar erros)
            if ($isAjax) {
                // O agente já vem com tools do AIAgentService::get()
                // Mas vamos pegar allTools também
                try {
                    error_log("AIAgentController::show - Buscando allTools");
                    $allTools = AITool::getAllActive();
                    error_log("AIAgentController::show - allTools encontradas: " . count($allTools));
                } catch (\Exception $e) {
                    error_log("Erro ao buscar allTools: " . $e->getMessage());
                    $allTools = [];
                }
                
                error_log("AIAgentController::show - Retornando JSON");
                Response::json([
                    'success' => true,
                    'agent' => $agent, // Já inclui tools
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

    /**
     * Listar conversas do agente (paginado)
     */
    public function getConversations(int $id): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            $page = (int)(Request::get('page') ?? 1);
            $limit = (int)(Request::get('limit') ?? 20);
            $offset = ($page - 1) * $limit;
            
            $conversations = \App\Models\AIConversation::getByAgent($id, $limit, $offset);
            $total = \App\Models\AIConversation::countByAgent($id);
            $totalPages = ceil($total / $limit);
            
            Response::json([
                'success' => true,
                'conversations' => $conversations,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter histórico completo de uma conversa de IA
     */
    public function getConversationHistory(int $id, int $conversationId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            $history = \App\Models\AIConversation::getHistory($conversationId);
            
            if (!$history) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Verificar se a conversa pertence ao agente
            if ($history['ai_agent_id'] != $id) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não pertence a este agente'
                ], 403);
                return;
            }
            
            Response::json([
                'success' => true,
                'history' => $history
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

