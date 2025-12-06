<?php
/**
 * Controller de Roles
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Models\Role;
use App\Models\Permission as PermissionModel;

class RoleController
{
    /**
     * Listar roles
     */
    public function index(): void
    {
        Permission::abortIfCannot('roles.view');
        
        $roles = Role::all();
        Response::view('roles/index', ['roles' => $roles]);
    }

    /**
     * Mostrar role específica
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('roles.view');
        
        $role = Role::find($id);
        if (!$role) {
            Response::notFound('Role não encontrada');
            return;
        }
        
        $role['permissions'] = Role::getPermissions($id);
        $allPermissions = PermissionModel::getAllGroupedByModule();
        
        Response::view('roles/show', [
            'role' => $role,
            'allPermissions' => $allPermissions
        ]);
    }

    /**
     * Criar role
     */
    public function store(): void
    {
        Permission::abortIfCannot('roles.create');
        
        try {
            $data = Request::post();
            $roleId = Role::create($data);
            
            if ($roleId) {
                Response::json(['success' => true, 'message' => 'Role criada com sucesso!', 'id' => $roleId]);
            } else {
                Response::json(['success' => false, 'message' => 'Falha ao criar role.'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Atualizar role
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('roles.edit');
        
        try {
            $data = Request::post();
            if (Role::update($id, $data)) {
                Response::json(['success' => true, 'message' => 'Role atualizada com sucesso!']);
            } else {
                Response::json(['success' => false, 'message' => 'Falha ao atualizar role.'], 404);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Atribuir permissão à role
     */
    public function assignPermission(int $id): void
    {
        Permission::abortIfCannot('roles.edit');
        
        try {
            $permissionId = Request::post('permission_id');
            if (Role::addPermission($id, $permissionId)) {
                Response::json(['success' => true, 'message' => 'Permissão atribuída com sucesso!']);
            } else {
                Response::json(['success' => false, 'message' => 'Falha ao atribuir permissão.'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Remover permissão da role
     */
    public function removePermission(int $id): void
    {
        Permission::abortIfCannot('roles.edit');
        
        try {
            $permissionId = Request::post('permission_id');
            if (Role::removePermission($id, $permissionId)) {
                Response::json(['success' => true, 'message' => 'Permissão removida com sucesso!']);
            } else {
                Response::json(['success' => false, 'message' => 'Falha ao remover permissão.'], 500);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
}

