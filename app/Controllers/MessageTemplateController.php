<?php
/**
 * Controller de Templates de Mensagens
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\MessageTemplateService;
use App\Models\Department;

class MessageTemplateController
{
    /**
     * Listar templates
     */
    public function index(): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        $filters = [
            'category' => Request::get('category'),
            'department_id' => Request::get('department_id'),
            'channel' => Request::get('channel'),
            'search' => Request::get('search'),
            'is_active' => Request::get('is_active'),
            'limit' => Request::get('limit', 100),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $templates = MessageTemplateService::list($filters);
            $departments = Department::all();
            $categories = self::getCategories();
            
            Response::view('message-templates/index', [
                'templates' => $templates,
                'departments' => $departments,
                'categories' => $categories,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('message-templates/index', [
                'templates' => [],
                'departments' => [],
                'categories' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar template
     */
    public function store(): void
    {
        Permission::abortIfCannot('message_templates.create');
        
        try {
            $data = Request::post();
            $templateId = MessageTemplateService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Template criado com sucesso!',
                'id' => $templateId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar template
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('message_templates.edit');
        
        try {
            $data = Request::post();
            if (MessageTemplateService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Template atualizado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar template.'
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
                'message' => 'Erro ao atualizar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar template
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('message_templates.delete');
        
        try {
            if (MessageTemplateService::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Template deletado com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar template.'
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
     * Obter templates disponíveis (JSON - para uso em selects)
     */
    public function getAvailable(): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $departmentId = Request::get('department_id');
            $channel = Request::get('channel');
            
            $templates = MessageTemplateService::getAvailable(
                $departmentId ? (int)$departmentId : null,
                $channel
            );
            
            Response::json([
                'success' => true,
                'templates' => $templates
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'templates' => []
            ], 500);
        }
    }

    /**
     * Preview de template com variáveis preenchidas (JSON)
     */
    public function preview(int $id): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $conversationId = Request::post('conversation_id');
            $preview = MessageTemplateService::preview($id, $conversationId);
            
            Response::json([
                'success' => true,
                'content' => $preview['content'] ?? '',
                'processed_content' => $preview['processed_content'] ?? '',
                'variables_used' => $preview['variables_used'] ?? [],
                'preview' => $preview
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao gerar preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Processar template com variáveis (JSON)
     */
    public function process(int $id): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $conversationId = Request::post('conversation_id');
            $customVariables = Request::post('variables', []);
            
            // Obter variáveis da conversa se fornecida
            $variables = [];
            if ($conversationId) {
                $variables = MessageTemplateService::getConversationVariables($conversationId);
            }
            
            // Mesclar com variáveis customizadas (customizadas têm prioridade)
            $variables = array_merge($variables, $customVariables);
            
            // Adicionar variáveis de data/hora
            $variables['date'] = date('d/m/Y');
            $variables['time'] = date('H:i');
            $variables['datetime'] = date('d/m/Y H:i');
            
            $processed = MessageTemplateService::process($id, $variables);
            
            Response::json([
                'success' => true,
                'processed_content' => $processed
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao processar template: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter variáveis disponíveis (JSON)
     */
    public function getVariables(): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $variables = MessageTemplateService::getAvailableVariables();
            Response::json([
                'success' => true,
                'variables' => $variables
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'variables' => []
            ], 500);
        }
    }

    /**
     * Obter categorias disponíveis
     */
    private static function getCategories(): array
    {
        $sql = "SELECT DISTINCT category FROM message_templates WHERE category IS NOT NULL ORDER BY category ASC";
        $results = \App\Helpers\Database::fetchAll($sql);
        return array_column($results, 'category');
    }
}

