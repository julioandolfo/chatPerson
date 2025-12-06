<?php
/**
 * Controller de Departments
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;

class DepartmentController
{
    /**
     * Listar departments
     */
    public function index(): void
    {
        Permission::abortIfCannot('departments.view');
        
        // Se for requisição AJAX ou formato JSON, retornar JSON
        if (\App\Helpers\Request::isAjax() || Request::get('format') === 'json') {
            try {
                $departments = DepartmentService::list([]);
                Response::json([
                    'success' => true,
                    'departments' => $departments
                ]);
                return;
            } catch (\Exception $e) {
                Response::json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'departments' => []
                ], 500);
                return;
            }
        }
        
        try {
            $filters = [
                'parent_id' => Request::get('parent_id')
            ];
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            $departments = DepartmentService::list($filters);
            $tree = DepartmentService::getTree();
            
            Response::view('departments/index', [
                'departments' => $departments,
                'tree' => $tree,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('departments/index', [
                'departments' => [],
                'tree' => [],
                'filters' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar department específico
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('departments.view');
        
        try {
            $department = DepartmentService::get($id);
            if (!$department) {
                Response::notFound('Setor não encontrado');
                return;
            }
            
            $allAgents = User::getActiveAgents();
            $availableParents = DepartmentService::getAvailableParents($id);
            $stats = DepartmentService::getStats($id);
            
            Response::view('departments/show', [
                'department' => $department,
                'allAgents' => $allAgents,
                'availableParents' => $availableParents,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            Response::view('errors/404', [
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Criar department
     */
    public function store(): void
    {
        Permission::abortIfCannot('departments.create');
        
        try {
            $data = Request::post();
            $departmentId = DepartmentService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Setor criado com sucesso!',
                'id' => $departmentId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar setor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar department
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('departments.edit');
        
        try {
            $data = Request::post();
            if (DepartmentService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Setor atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar setor.'
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
                'message' => 'Erro ao atualizar setor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar department
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('departments.delete');
        
        try {
            if (DepartmentService::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Setor deletado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar setor.'
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
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Adicionar agente ao setor
     */
    public function addAgent(int $id): void
    {
        Permission::abortIfCannot('departments.assign_agents');
        
        try {
            $userId = Request::post('user_id');
            if (DepartmentService::addAgent($id, $userId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Agente adicionado ao setor com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao adicionar agente.'
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
                'message' => 'Erro ao adicionar agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover agente do setor
     */
    public function removeAgent(int $id): void
    {
        Permission::abortIfCannot('departments.assign_agents');
        
        try {
            $userId = Request::post('user_id');
            if (DepartmentService::removeAgent($id, $userId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Agente removido do setor com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover agente.'
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
                'message' => 'Erro ao remover agente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter dados do setor em JSON (para edição via modal)
     */
    public function getJson(int $id): void
    {
        Permission::abortIfCannot('departments.view');
        
        try {
            $department = DepartmentService::get($id);
            if (!$department) {
                Response::json([
                    'success' => false,
                    'message' => 'Setor não encontrado'
                ], 404);
                return;
            }
            
            Response::json([
                'success' => true,
                'department' => $department
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

