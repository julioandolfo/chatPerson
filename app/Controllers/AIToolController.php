<?php
/**
 * Controller AIToolController
 * Gerenciamento de Tools de IA
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\AIToolService;
use App\Services\AIToolValidationService;

class AIToolController
{
    /**
     * Listar tools
     */
    public function index(): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        $filters = [
            'tool_type' => Request::get('tool_type'),
            'enabled' => Request::get('enabled'),
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 50),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $tools = AIToolService::list($filters);
            
            Response::view('ai-tools/index', [
                'tools' => $tools,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('ai-tools/index', [
                'tools' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Mostrar tool específica
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $tool = AIToolService::get($id);
            if (!$tool) {
                Response::notFound('Tool não encontrada');
                return;
            }
            
            // Decodificar JSON fields
            if (is_string($tool['function_schema'])) {
                $tool['function_schema'] = json_decode($tool['function_schema'], true);
            }
            if (is_string($tool['config'])) {
                $tool['config'] = json_decode($tool['config'], true);
            }
            
            Response::view('ai-tools/show', [
                'tool' => $tool
            ]);
        } catch (\Exception $e) {
            Response::forbidden($e->getMessage());
        }
    }

    /**
     * Criar tool
     */
    public function store(): void
    {
        Permission::abortIfCannot('ai_tools.create');
        
        try {
            $data = Request::post();
            $toolId = AIToolService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Tool criada com sucesso!',
                'id' => $toolId
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar tool
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('ai_tools.edit');
        
        try {
            $data = Request::post();
            if (AIToolService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar tool'
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
     * Excluir tool
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('ai_tools.delete');
        
        try {
            if (\App\Models\AITool::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tool excluída com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao excluir tool'
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
     * Validar todas as tools
     */
    public function validate(): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $report = AIToolValidationService::generateReport();
            
            Response::json([
                'success' => true,
                'report' => $report
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validar tool específica
     */
    public function validateTool(int $id): void
    {
        Permission::abortIfCannot('ai_tools.view');
        
        try {
            $tool = AIToolService::get($id);
            if (!$tool) {
                Response::json([
                    'success' => false,
                    'message' => 'Tool não encontrada'
                ], 404);
                return;
            }
            
            $validation = AIToolValidationService::validateTool($tool);
            
            Response::json([
                'success' => true,
                'tool_id' => $id,
                'tool_name' => $tool['name'],
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

