<?php
/**
 * DepartmentsController - API v1
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Department;

class DepartmentsController
{
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('departments.view');
        
        try {
            $departments = Department::all();
            ApiResponse::success($departments);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar setores', $e);
        }
    }
    
    public function show(string $id): void
    {
        ApiAuthMiddleware::requirePermission('departments.view');
        
        try {
            $department = Department::find((int)$id);
            
            if (!$department) {
                ApiResponse::notFound('Setor não encontrado');
            }
            
            ApiResponse::success($department);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter setor', $e);
        }
    }
}
