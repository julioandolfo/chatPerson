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
}
