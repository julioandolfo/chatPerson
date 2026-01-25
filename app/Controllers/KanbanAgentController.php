<?php
/**
 * Controller KanbanAgentController
 * Gerenciamento de Agentes de IA Kanban
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\KanbanAgentService;
use App\Models\AIKanbanAgent;
use App\Models\AIKanbanAgentExecution;
use App\Models\AIKanbanAgentActionLog;
use App\Models\Funnel;
use App\Models\FunnelStage;
use App\Helpers\Validator;

class KanbanAgentController
{
    /**
     * Listar agentes Kanban
     */
    public function index(): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $filters = [
            'agent_type' => Request::get('agent_type'),
            'enabled' => Request::get('enabled'),
            'search' => Request::get('search')
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $agents = AIKanbanAgent::all();
            
            // Aplicar filtros
            if (!empty($filters['agent_type'])) {
                $agents = array_filter($agents, function($a) use ($filters) {
                    return $a['agent_type'] === $filters['agent_type'];
                });
            }
            
            if (isset($filters['enabled'])) {
                $enabled = $filters['enabled'] === '1' || $filters['enabled'] === 'true';
                $agents = array_filter($agents, function($a) use ($enabled) {
                    return (bool)$a['enabled'] === $enabled;
                });
            }
            
            if (!empty($filters['search'])) {
                $search = strtolower($filters['search']);
                $agents = array_filter($agents, function($a) use ($search) {
                    return strpos(strtolower($a['name']), $search) !== false ||
                           strpos(strtolower($a['description'] ?? ''), $search) !== false;
                });
            }
            
            $agents = array_values($agents);
            
            Response::view('kanban-agents/index', [
                'agents' => $agents,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('kanban-agents/index', [
                'agents' => [],
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
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            $agent = AIKanbanAgent::find($id);
            if (!$agent) {
                Response::redirect('/kanban-agents', ['error' => 'Agente não encontrado']);
                return;
            }
            
            $executions = AIKanbanAgent::getExecutions($id, 20);
            $actionLogs = AIKanbanAgent::getActionLogs($id, 50);
            
            // Buscar funis e etapas para exibição
            $funnels = [];
            $stages = [];
            
            if ($agent['target_funnel_ids']) {
                foreach ($agent['target_funnel_ids'] as $funnelId) {
                    $funnel = Funnel::find($funnelId);
                    if ($funnel) {
                        $funnels[] = $funnel;
                    }
                }
            }
            
            if ($agent['target_stage_ids']) {
                foreach ($agent['target_stage_ids'] as $stageId) {
                    $stage = FunnelStage::find($stageId);
                    if ($stage) {
                        $stages[] = $stage;
                    }
                }
            }
            
            Response::view('kanban-agents/show', [
                'agent' => $agent,
                'executions' => $executions,
                'actionLogs' => $actionLogs,
                'funnels' => $funnels,
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            Response::redirect('/kanban-agents', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Criar novo agente
     */
    public function create(): void
    {
        Permission::abortIfCannot('ai_agents.create');
        
        $funnels = Funnel::whereActive();
        $allStages = [];
        
        foreach ($funnels as $funnel) {
            $stages = Funnel::getStages($funnel['id']);
            $allStages[$funnel['id']] = $stages;
        }
        
        Response::view('kanban-agents/create', [
            'funnels' => $funnels,
            'allStages' => $allStages
        ]);
    }

    /**
     * Salvar novo agente
     */
    public function store(): void
    {
        Permission::abortIfCannot('ai_agents.create');
        
        $data = Request::post();
        
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agent_type' => 'required|string|in:kanban_followup,kanban_analyzer,kanban_manager,kanban_custom',
            'prompt' => 'required|string',
            'model' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'execution_type' => 'required|string|in:interval,schedule,manual,instant_client_message,instant_agent_message,instant_any_message',
            'execution_interval_hours' => 'nullable|integer|min:1',
            'max_conversations_per_execution' => 'nullable|integer|min:1|max:1000'
        ]);
        
        if (!empty($errors)) {
            Response::json(['success' => false, 'errors' => $errors], 400);
            return;
        }
        
        try {
            // Processar dados
            $agentData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'agent_type' => $data['agent_type'],
                'prompt' => $data['prompt'],
                'model' => $data['model'] ?? 'gpt-4',
                'temperature' => isset($data['temperature']) ? (float)$data['temperature'] : 0.7,
                'max_tokens' => isset($data['max_tokens']) ? (int)$data['max_tokens'] : 2000,
                'enabled' => isset($data['enabled']) ? (bool)$data['enabled'] : true,
                'execution_type' => $data['execution_type'],
                'execution_interval_hours' => isset($data['execution_interval_hours']) ? (int)$data['execution_interval_hours'] : null,
                'max_conversations_per_execution' => isset($data['max_conversations_per_execution']) ? (int)$data['max_conversations_per_execution'] : 50,
                'target_funnel_ids' => !empty($data['target_funnel_ids']) ? array_map('intval', $data['target_funnel_ids']) : null,
                'target_stage_ids' => !empty($data['target_stage_ids']) ? array_map('intval', $data['target_stage_ids']) : null,
                'execution_schedule' => !empty($data['execution_schedule']) ? json_decode($data['execution_schedule'], true) : null,
                'conditions' => !empty($data['conditions']) ? json_decode($data['conditions'], true) : ['operator' => 'AND', 'conditions' => []],
                'actions' => !empty($data['actions']) ? json_decode($data['actions'], true) : [],
                'settings' => !empty($data['settings']) ? json_decode($data['settings'], true) : null,
                // Campos de cooldown
                'cooldown_hours' => isset($data['cooldown_hours']) ? (int)$data['cooldown_hours'] : 24,
                'allow_reexecution_on_change' => isset($data['allow_reexecution_on_change']) ? true : false
            ];
            
            // Calcular próxima execução se necessário
            if ($agentData['execution_type'] === 'interval' && $agentData['execution_interval_hours']) {
                $agentData['next_execution_at'] = date('Y-m-d H:i:s', strtotime("+{$agentData['execution_interval_hours']} hours"));
            }
            
            $agentId = AIKanbanAgent::create($agentData);
            
            Response::json([
                'success' => true,
                'message' => 'Agente Kanban criado com sucesso!',
                'agent_id' => $agentId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar agente
     */
    public function edit(int $id): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $agent = AIKanbanAgent::find($id);
        if (!$agent) {
            Response::redirect('/kanban-agents', ['error' => 'Agente não encontrado']);
            return;
        }
        
        $funnels = Funnel::whereActive();
        $allStages = [];
        
        foreach ($funnels as $funnel) {
            $stages = Funnel::getStages($funnel['id']);
            $allStages[$funnel['id']] = $stages;
        }
        
        // Garantir que arrays estão inicializados
        if (!is_array($agent['target_funnel_ids'])) {
            $agent['target_funnel_ids'] = [];
        }
        if (!is_array($agent['target_stage_ids'])) {
            $agent['target_stage_ids'] = [];
        }
        
        Response::view('kanban-agents/edit', [
            'agent' => $agent,
            'funnels' => $funnels,
            'allStages' => $allStages
        ]);
    }

    /**
     * Atualizar agente
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        $agent = AIKanbanAgent::find($id);
        if (!$agent) {
            Response::json(['success' => false, 'message' => 'Agente não encontrado'], 404);
            return;
        }
        
        $data = Request::post();
        
        $errors = Validator::validate($data, [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'agent_type' => 'required|string|in:kanban_followup,kanban_analyzer,kanban_manager,kanban_custom',
            'prompt' => 'required|string',
            'model' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_tokens' => 'nullable|integer|min:1',
            'execution_type' => 'required|string|in:interval,schedule,manual,instant_client_message,instant_agent_message,instant_any_message',
            'execution_interval_hours' => 'nullable|integer|min:1',
            'max_conversations_per_execution' => 'nullable|integer|min:1|max:1000'
        ]);
        
        if (!empty($errors)) {
            Response::json(['success' => false, 'errors' => $errors], 400);
            return;
        }
        
        try {
            $updateData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'agent_type' => $data['agent_type'],
                'prompt' => $data['prompt'],
                'model' => $data['model'] ?? 'gpt-4',
                'temperature' => isset($data['temperature']) ? (float)$data['temperature'] : 0.7,
                'max_tokens' => isset($data['max_tokens']) ? (int)$data['max_tokens'] : 2000,
                'enabled' => isset($data['enabled']) ? (bool)$data['enabled'] : true,
                'execution_type' => $data['execution_type'],
                'execution_interval_hours' => isset($data['execution_interval_hours']) ? (int)$data['execution_interval_hours'] : null,
                'max_conversations_per_execution' => isset($data['max_conversations_per_execution']) ? (int)$data['max_conversations_per_execution'] : 50,
                'target_funnel_ids' => !empty($data['target_funnel_ids']) ? array_map('intval', $data['target_funnel_ids']) : null,
                'target_stage_ids' => !empty($data['target_stage_ids']) ? array_map('intval', $data['target_stage_ids']) : null,
                'execution_schedule' => !empty($data['execution_schedule']) ? json_decode($data['execution_schedule'], true) : null,
                'conditions' => !empty($data['conditions']) ? json_decode($data['conditions'], true) : ['operator' => 'AND', 'conditions' => []],
                'actions' => !empty($data['actions']) ? json_decode($data['actions'], true) : [],
                'settings' => !empty($data['settings']) ? json_decode($data['settings'], true) : null,
                // Campos de cooldown
                'cooldown_hours' => isset($data['cooldown_hours']) ? (int)$data['cooldown_hours'] : 24,
                'allow_reexecution_on_change' => isset($data['allow_reexecution_on_change']) ? true : false
            ];
            
            AIKanbanAgent::update($id, $updateData);
            
            Response::json([
                'success' => true,
                'message' => 'Agente Kanban atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar agente
     */
    public function delete(int $id): void
    {
        Permission::abortIfCannot('ai_agents.delete');
        
        $agent = AIKanbanAgent::find($id);
        if (!$agent) {
            Response::json(['success' => false, 'message' => 'Agente não encontrado'], 404);
            return;
        }
        
        try {
            AIKanbanAgent::delete($id);
            
            Response::json([
                'success' => true,
                'message' => 'Agente Kanban deletado com sucesso!'
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao deletar agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Executar agente manualmente
     */
    public function execute(int $id): void
    {
        Permission::abortIfCannot('ai_agents.edit');
        
        try {
            $data = Request::post();
            $force = $data['force'] ?? false;
            
            $trigger = $force ? 'manual_force' : 'manual';
            $result = KanbanAgentService::executeAgent($id, $trigger);
            
            Response::json([
                'success' => true,
                'message' => $result['message'],
                'stats' => $result['stats'] ?? []
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao executar agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Testar condições em uma conversa específica
     */
    public function testConditions(int $id): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        $data = Request::post();
        $conversationId = $data['conversation_id'] ?? null;
        $conditions = $data['conditions'] ?? null;
        
        if (!$conversationId || !$conditions) {
            Response::json([
                'success' => false,
                'message' => 'ID da conversa e condições são obrigatórios'
            ], 400);
            return;
        }
        
        try {
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                Response::json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
                return;
            }
            
            // Simular análise básica
            $analysis = [
                'summary' => 'Análise de teste',
                'score' => 75,
                'sentiment' => 'neutral',
                'urgency' => 'medium',
                'recommendations' => []
            ];
            
            // Avaliar condições usando método público do service
            $result = \App\Services\KanbanAgentService::evaluateConditions($conditions, $conversation, $analysis);
            
            Response::json([
                'success' => true,
                'result' => $result['met'] ?? false,
                'details' => $result['details'] ?? []
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao testar condições: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obter dados do sistema para condições e ações (status, prioridades, canais, funis, etapas, agentes, tags, departamentos)
     */
    public function getSystemData(): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            // Status de conversas
            $conversationStatuses = ['open', 'closed', 'resolved', 'pending', 'spam'];
            
            // Prioridades
            $priorities = ['low', 'normal', 'medium', 'high', 'urgent'];
            
            // Canais disponíveis
            $channels = [
                'whatsapp' => 'WhatsApp',
                'whatsapp_official' => 'WhatsApp Oficial',
                'instagram' => 'Instagram',
                'facebook' => 'Facebook',
                'telegram' => 'Telegram',
                'email' => 'Email',
                'chat' => 'Chat',
                'mercadolivre' => 'Mercado Livre',
                'webchat' => 'WebChat',
                'olx' => 'OLX',
                'linkedin' => 'LinkedIn',
                'google_business' => 'Google Business',
                'youtube' => 'YouTube',
                'tiktok' => 'TikTok'
            ];
            
            // Funis e etapas
            $funnels = Funnel::whereActive();
            $allStages = [];
            foreach ($funnels as $funnel) {
                $stages = Funnel::getStages($funnel['id']);
                $allStages[$funnel['id']] = $stages;
            }
            
            // Agentes
            $agents = \App\Models\User::getAgents();
            
            // Agentes de IA
            $aiAgents = \App\Models\AIAgent::where('enabled', '=', true);
            
            // Tags
            $tags = \App\Services\TagService::getAll();
            
            // Departamentos
            $departments = \App\Services\DepartmentService::list([]);
            
            Response::json([
                'success' => true,
                'data' => [
                    'conversation_statuses' => $conversationStatuses,
                    'priorities' => $priorities,
                    'channels' => $channels,
                    'funnels' => $funnels,
                    'stages' => $allStages,
                    'agents' => $agents,
                    'ai_agents' => $aiAgents,
                    'tags' => $tags,
                    'departments' => $departments
                ]
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obter detalhes de uma execução específica (conversas processadas)
     */
    public function getExecutionDetails(int $executionId): void
    {
        Permission::abortIfCannot('ai_agents.view');
        
        try {
            // Buscar dados da execução
            $execution = \App\Models\AIKanbanAgentExecution::find($executionId);
            
            if (!$execution) {
                Response::json([
                    'success' => false,
                    'message' => 'Execução não encontrada'
                ], 404);
                return;
            }
            
            // Buscar logs de ações desta execução com informações das conversas
            $sql = "SELECT 
                        al.*,
                        c.id as conversation_id,
                        ct.name as contact_name,
                        ct.phone as contact_phone,
                        fs.name as stage_name,
                        f.name as funnel_name
                    FROM ai_kanban_agent_actions_log al
                    LEFT JOIN conversations c ON al.conversation_id = c.id
                    LEFT JOIN contacts ct ON c.contact_id = ct.id
                    LEFT JOIN funnel_stages fs ON c.funnel_stage_id = fs.id
                    LEFT JOIN funnels f ON c.funnel_id = f.id
                    WHERE al.execution_id = ?
                    ORDER BY al.executed_at DESC";
            
            $logs = \App\Helpers\Database::fetchAll($sql, [$executionId]);
            
            // Processar logs para adicionar análise de sentimento
            foreach ($logs as &$log) {
                // Decodificar JSON se necessário
                if (!empty($log['conditions_details']) && is_string($log['conditions_details'])) {
                    $log['conditions_details'] = json_decode($log['conditions_details'], true);
                }
                if (!empty($log['actions_executed']) && is_string($log['actions_executed'])) {
                    $log['actions_executed'] = json_decode($log['actions_executed'], true);
                }
                
                // Extrair sentimento do summary (formato: {"sentiment":"neutral",...})
                $log['analysis_sentiment'] = null;
                if (!empty($log['analysis_summary'])) {
                    // Tentar extrair sentimento do JSON se estiver no formato estruturado
                    if (preg_match('/"sentiment":"(\w+)"/', $log['analysis_summary'], $matches)) {
                        $log['analysis_sentiment'] = $matches[1];
                    }
                }
            }
            
            Response::json([
                'success' => true,
                'execution' => $execution,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()
            ], 500);
        }
    }
}

