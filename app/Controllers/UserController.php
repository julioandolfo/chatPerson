<?php
/**
 * Controller de Usuários
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\UserService;
use App\Models\Role;
use App\Models\Department;
use App\Models\AgentFunnelPermission;
use App\Models\Funnel;

class UserController
{
    /**
     * Listar usuários
     */
    public function index(): void
    {
        Permission::abortIfCannot('users.view');
        
        $filters = [
            'status' => Request::get('status'),
            'role' => Request::get('role'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $users = UserService::list($filters);
            
            // Se for requisição AJAX, retornar JSON
            if (Request::isAjax()) {
                Response::json(['success' => true, 'users' => $users]);
                return;
            }
            
            $roles = Role::all();
            $departments = Department::all();
            
            Response::view('users/index', [
                'users' => $users,
                'roles' => $roles,
                'departments' => $departments,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('users/index', [
                'users' => [],
                'roles' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar usuário específico
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('users.view');
        
        try {
            $user = UserService::get($id);
            if (!$user) {
                Response::notFound('Usuário não encontrado');
                return;
            }
            
            $allRoles = Role::all();
            $allDepartments = Department::all();
            $allFunnels = Funnel::where('status', '=', 'active');
            $funnelPermissions = AgentFunnelPermission::getUserPermissions($id);
            
            // Obter estatísticas de performance se for agente
            $performanceStats = null;
            if ($user['role'] === 'agent' || $user['role'] === 'admin' || $user['role'] === 'supervisor') {
                try {
                    $dateFrom = Request::get('date_from', date('Y-m-01'));
                    $dateTo = Request::get('date_to', date('Y-m-d H:i:s'));
                    $performanceStats = \App\Services\AgentPerformanceService::getPerformanceStats($id, $dateFrom, $dateTo);
                } catch (\Exception $e) {
                    error_log("Erro ao obter estatísticas de performance: " . $e->getMessage());
                }
            }
            
            Response::view('users/show', [
                'user' => $user,
                'allRoles' => $allRoles,
                'allDepartments' => $allDepartments,
                'allFunnels' => $allFunnels,
                'funnelPermissions' => $funnelPermissions,
                'performanceStats' => $performanceStats
            ]);
        } catch (\Exception $e) {
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar usuário
     */
    public function store(): void
    {
        Permission::abortIfCannot('users.create');
        
        try {
            $data = Request::post();
            $userId = UserService::create($data);
            
            // Processar upload de avatar se houver
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                UserService::uploadAvatar($userId, $_FILES['avatar_file']);
            }
            
            Response::json([
                'success' => true,
                'message' => 'Usuário criado com sucesso!',
                'id' => $userId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar usuário
     */
    public function update(int $id): void
    {
        error_log("UserController::update chamado para userId={$id}");
        
        Permission::abortIfCannot('users.edit');
        
        try {
            $data = Request::post();
            error_log("UserController::update data=" . json_encode($data));
            
            // Processar upload de avatar se houver
            if (!empty($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                $data['avatar'] = UserService::uploadAvatar($id, $_FILES['avatar_file']);
            }
            
            if (UserService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Usuário atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar usuário.'
                ], 404);
            }
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar usuário: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar usuário
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('users.delete');
        
        try {
            if (UserService::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Usuário deletado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar usuário.'
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
     * Atribuir role ao usuário
     */
    public function assignRole(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $roleId = Request::post('role_id');
            if (UserService::assignRole($id, $roleId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Role atribuída com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atribuir role.'
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
     * Remover role do usuário
     */
    public function removeRole(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $roleId = Request::post('role_id');
            if (UserService::removeRole($id, $roleId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Role removida com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover role.'
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
     * Atribuir department ao usuário
     */
    public function assignDepartment(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $departmentId = Request::post('department_id');
            if (UserService::assignDepartment($id, $departmentId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Setor atribuído com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atribuir setor.'
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
     * Remover department do usuário
     */
    public function removeDepartment(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $departmentId = Request::post('department_id');
            if (UserService::removeDepartment($id, $departmentId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Setor removido com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover setor.'
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
     * Atribuir permissão de funil/estágio
     */
    public function assignFunnelPermission(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $funnelId = Request::post('funnel_id');
            $stageId = Request::post('stage_id');
            $permissionType = Request::post('permission_type', 'view');
            
            if (AgentFunnelPermission::addPermission($id, $funnelId ?: null, $stageId ?: null, $permissionType)) {
                Response::json([
                    'success' => true,
                    'message' => 'Permissão atribuída com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atribuir permissão.'
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
     * Remover permissão de funil/estágio
     */
    public function removeFunnelPermission(int $id): void
    {
        Permission::abortIfCannot('users.edit');
        
        try {
            $funnelId = Request::post('funnel_id');
            $stageId = Request::post('stage_id');
            $permissionType = Request::post('permission_type', 'view');
            
            if (AgentFunnelPermission::removePermission($id, $funnelId ?: null, $stageId ?: null, $permissionType)) {
                Response::json([
                    'success' => true,
                    'message' => 'Permissão removida com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover permissão.'
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
     * Obter estatísticas de performance (JSON)
     */
    public function getPerformanceStats(int $id): void
    {
        Permission::abortIfCannot('users.view');
        
        try {
            $dateFrom = Request::get('date_from', date('Y-m-01'));
            $dateTo = Request::get('date_to', date('Y-m-d H:i:s'));
            
            $stats = \App\Services\AgentPerformanceService::getPerformanceStats($id, $dateFrom, $dateTo);
            
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
     * Atualizar status de disponibilidade do usuário logado
     */
    public function updateAvailability(): void
    {
        // Desabilitar display de erros para evitar HTML no JSON
        $oldDisplayErrors = ini_get('display_errors');
        ini_set('display_errors', '0');
        
        // Limpar qualquer output buffer anterior
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        try {
            $userId = \App\Helpers\Auth::id();
            if (!$userId) {
                Response::json(['success' => false, 'message' => 'Usuário não autenticado'], 401);
                return;
            }
            
            $data = Request::json();
            $status = $data['status'] ?? null;
            
            if (!$status || !in_array($status, ['online', 'offline', 'away', 'busy'])) {
                Response::json(['success' => false, 'message' => 'Status inválido'], 400);
                return;
            }
            
            if (\App\Models\User::updateAvailabilityStatus($userId, $status)) {
                Response::json([
                    'success' => true,
                    'message' => 'Status atualizado com sucesso',
                    'status' => $status
                ]);
            } else {
                Response::json(['success' => false, 'message' => 'Falha ao atualizar status'], 500);
            }
        } catch (\Exception $e) {
            error_log('UserController::updateAvailability - Erro: ' . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar status: ' . $e->getMessage()
            ], 500);
        } catch (\Throwable $e) {
            error_log('UserController::updateAvailability - Erro fatal: ' . $e->getMessage());
            Response::json([
                'success' => false,
                'message' => 'Erro ao atualizar status'
            ], 500);
        } finally {
            // Restaurar configuração original
            ini_set('display_errors', $oldDisplayErrors);
        }
    }
}
