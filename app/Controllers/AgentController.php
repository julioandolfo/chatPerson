<?php
/**
 * Controller de Agentes
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\UserService;
use App\Models\User;

class AgentController
{
    /**
     * Listar agentes
     */
    public function index(): void
    {
        Permission::abortIfCannot('agents.view');
        
        // Se for requisição AJAX ou formato JSON, retornar JSON
        if (\App\Helpers\Request::isAjax() || Request::get('format') === 'json') {
            try {
                $sql = "SELECT u.id, u.name, u.email, u.status 
                        FROM users u
                        WHERE u.role IN ('super_admin', 'admin', 'supervisor', 'senior_agent', 'agent', 'junior_agent')
                        ORDER BY u.name ASC";
                $agents = \App\Helpers\Database::fetchAll($sql);
                Response::json([
                    'success' => true,
                    'agents' => $agents
                ]);
                return;
            } catch (\Exception $e) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'agents' => []
                ], 500);
                return;
            }
        }
        
        $filters = [
            'status' => Request::get('status', 'active'),
            'role' => Request::get('role', 'agent'),
            'availability_status' => Request::get('availability_status'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            // Buscar apenas agentes (todos os níveis)
            $sql = "SELECT u.*, 
                           GROUP_CONCAT(DISTINCT r.name) as roles_names,
                           GROUP_CONCAT(DISTINCT d.name) as departments_names,
                           COUNT(DISTINCT c.id) as current_conversations_count
                    FROM users u
                    LEFT JOIN user_roles ur ON u.id = ur.user_id
                    LEFT JOIN roles r ON ur.role_id = r.id
                    LEFT JOIN agent_departments ad ON u.id = ad.user_id
                    LEFT JOIN departments d ON ad.department_id = d.id
                    LEFT JOIN conversations c ON u.id = c.agent_id AND c.status IN ('open', 'pending')
                    WHERE u.role IN ('super_admin', 'admin', 'supervisor', 'senior_agent', 'agent', 'junior_agent')";
            
            $params = [];

            if (!empty($filters['status'])) {
                $sql .= " AND u.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['availability_status'])) {
                $sql .= " AND u.availability_status = ?";
                $params[] = $filters['availability_status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
                $search = "%{$filters['search']}%";
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " GROUP BY u.id ORDER BY u.name ASC";

            if (!empty($filters['limit'])) {
                $sql .= " LIMIT " . (int)$filters['limit'];
                if (!empty($filters['offset'])) {
                    $sql .= " OFFSET " . (int)$filters['offset'];
                }
            }

            $agents = \App\Helpers\Database::fetchAll($sql, $params);
            
            // Atualizar current_conversations com valores reais
            foreach ($agents as &$agent) {
                $agent['current_conversations'] = $agent['current_conversations_count'] ?? 0;
            }
            
            $roles = \App\Models\Role::all();
            $departments = \App\Models\Department::all();
            
            Response::view('agents/index', [
                'agents' => $agents,
                'roles' => $roles,
                'departments' => $departments,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('agents/index', [
                'agents' => [],
                'roles' => [],
                'departments' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Mostrar agente específico (redireciona para users/show)
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('agents.view');
        \App\Helpers\Response::redirect('/users/' . $id);
    }

    /**
     * Atualizar status de disponibilidade do agente
     */
    public function updateAvailability(int $id): void
    {
        Permission::abortIfCannot('agents.edit');
        
        try {
            $status = Request::post('availability_status');
            
            if (!in_array($status, ['online', 'offline', 'away', 'busy'])) {
                throw new \InvalidArgumentException('Status de disponibilidade inválido');
            }
            
            if (User::updateAvailabilityStatus($id, $status)) {
                Response::json([
                    'success' => true,
                    'message' => 'Status de disponibilidade atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar status.'
                ], 500);
            }
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Reatribuir conversas de um agente para outros agentes
     */
    public function reassignConversations(int $id): void
    {
        Permission::abortIfCannot('agents.edit');
        
        try {
            $targetAgentIds = Request::post('target_agent_ids');
            $alsoDeactivate = Request::post('also_deactivate', false);
            
            // Validar dados
            if (empty($targetAgentIds) || !is_array($targetAgentIds)) {
                throw new \InvalidArgumentException('Selecione pelo menos um agente para redistribuição');
            }
            
            // Verificar se o agente existe
            $agent = User::find($id);
            if (!$agent) {
                throw new \InvalidArgumentException('Agente não encontrado');
            }
            
            // Converter IDs para inteiros
            $targetAgentIds = array_map('intval', $targetAgentIds);
            
            // Verificar se todos os agentes destino existem e estão ativos
            foreach ($targetAgentIds as $targetId) {
                $targetAgent = User::find($targetId);
                if (!$targetAgent) {
                    throw new \InvalidArgumentException("Agente destino ID {$targetId} não encontrado");
                }
                if ($targetAgent['status'] !== 'active') {
                    throw new \InvalidArgumentException("Agente {$targetAgent['name']} está inativo");
                }
            }
            
            // Buscar TODAS as conversas do agente (abertas, pendentes E fechadas)
            $conversations = \App\Helpers\Database::fetchAll(
                "SELECT id, contact_id, status FROM conversations 
                 WHERE agent_id = ?
                 ORDER BY id ASC",
                [$id]
            );
            
            if (empty($conversations)) {
                Response::json([
                    'success' => true,
                    'message' => 'Nenhuma conversa encontrada para reatribuir.',
                    'conversations_reassigned' => 0
                ]);
                return;
            }
            
            // Buscar contatos onde o agente é o agente principal
            $contactAgents = \App\Helpers\Database::fetchAll(
                "SELECT DISTINCT contact_id FROM contact_agents 
                 WHERE agent_id = ?",
                [$id]
            );
            
            $conversationsReassigned = 0;
            $contactAgentsUpdated = 0;
            
            // Distribuir conversas igualmente entre os agentes
            $numAgents = count($targetAgentIds);
            $agentIndex = 0;
            
            foreach ($conversations as $conversation) {
                $targetAgentId = $targetAgentIds[$agentIndex];
                
                // Reatribuir conversa
                \App\Models\Conversation::update($conversation['id'], [
                    'agent_id' => $targetAgentId,
                    'assigned_at' => date('Y-m-d H:i:s')
                ]);
                
                // Registrar histórico de atribuição
                if (class_exists('\App\Models\ConversationAssignment')) {
                    \App\Models\ConversationAssignment::recordAssignment(
                        $conversation['id'],
                        $targetAgentId,
                        \App\Helpers\Auth::id()
                    );
                }
                
                $conversationsReassigned++;
                
                // Próximo agente (distribuição round-robin)
                $agentIndex = ($agentIndex + 1) % $numAgents;
            }
            
            // Reatribuir agentes de contatos
            // Para cada contato, adicionar os novos agentes (mantendo a redistribuição round-robin)
            $agentIndex = 0;
            foreach ($contactAgents as $contactAgent) {
                $contactId = $contactAgent['contact_id'];
                $targetAgentId = $targetAgentIds[$agentIndex];
                
                // Verificar se já existe
                $exists = \App\Helpers\Database::fetch(
                    "SELECT id FROM contact_agents WHERE contact_id = ? AND agent_id = ?",
                    [$contactId, $targetAgentId]
                );
                
                if (!$exists) {
                    // Adicionar novo agente ao contato
                    \App\Models\ContactAgent::create([
                        'contact_id' => $contactId,
                        'agent_id' => $targetAgentId,
                        'is_primary' => 0,
                        'priority' => 0,
                        'auto_assign_on_reopen' => 1
                    ]);
                    $contactAgentsUpdated++;
                }
                
                // Se o agente antigo era o principal, definir o novo como principal
                $wasPrimary = \App\Helpers\Database::fetch(
                    "SELECT is_primary FROM contact_agents WHERE contact_id = ? AND agent_id = ?",
                    [$contactId, $id]
                );
                
                if ($wasPrimary && $wasPrimary['is_primary'] == 1) {
                    \App\Models\ContactAgent::setPrimaryAgent($contactId, $targetAgentId);
                }
                
                // Próximo agente
                $agentIndex = ($agentIndex + 1) % $numAgents;
            }
            
            // Remover o agente antigo dos contatos
            \App\Helpers\Database::execute(
                "DELETE FROM contact_agents WHERE agent_id = ?",
                [$id]
            );
            
            // Desativar agente se solicitado
            if ($alsoDeactivate) {
                User::update($id, [
                    'status' => 'inactive',
                    'availability_status' => 'offline'
                ]);
            }
            
            // Registrar atividade
            if (class_exists('\App\Services\ActivityService')) {
                \App\Services\ActivityService::log(
                    'agent_conversations_reassigned',
                    "TODAS as conversas ({$conversationsReassigned} total, incluindo histórico) do agente {$agent['name']} foram reatribuídas para " . count($targetAgentIds) . " agente(s)",
                    null,
                    $id,
                    'agent'
                );
            }
            
            Response::json([
                'success' => true,
                'message' => "Reatribuição concluída! {$conversationsReassigned} conversa(s) (incluindo histórico completo) e {$contactAgentsUpdated} registro(s) de agente do contato foram redistribuídos.",
                'conversations_reassigned' => $conversationsReassigned,
                'contact_agents_updated' => $contactAgentsUpdated,
                'agent_deactivated' => $alsoDeactivate
            ]);
            
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao reatribuir conversas: ' . $e->getMessage()
            ], 500);
        }
    }
}
