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
     * Se user_id não for fornecido, será NULL (template global)
     * Se user_id for fornecido, será um template pessoal do usuário
     */
    public function store(): void
    {
        Permission::abortIfCannot('message_templates.create');
        
        try {
            $data = Request::post();
            
            // Se não foi fornecido user_id, verificar se é template pessoal ou global
            if (!isset($data['user_id'])) {
                // Se for template pessoal (is_personal = true), usar ID do usuário logado
                if (isset($data['is_personal']) && $data['is_personal']) {
                    $data['user_id'] = \App\Helpers\Auth::id();
                } else {
                    $data['user_id'] = null; // Template global
                }
                unset($data['is_personal']); // Remover flag auxiliar
            }
            
            // Se user_id foi fornecido, verificar se o usuário tem permissão
            if (isset($data['user_id']) && $data['user_id'] !== null) {
                $currentUserId = \App\Helpers\Auth::id();
                // Só pode criar template pessoal para si mesmo, a menos que tenha permissão especial
                if ($data['user_id'] != $currentUserId && !Permission::can('message_templates.create.all')) {
                    $data['user_id'] = $currentUserId; // Forçar para o usuário logado
                }
            }
            
            // Definir created_by se não foi fornecido
            if (!isset($data['created_by'])) {
                $data['created_by'] = \App\Helpers\Auth::id();
            }
            
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
     * Obter template específico (para edição)
     */
    public function show(int $id): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $template = MessageTemplateService::get($id);
            if ($template) {
                // Verificar se é template pessoal do usuário ou global
                $userId = \App\Helpers\Auth::id();
                if ($template['user_id'] !== null && $template['user_id'] != $userId && !Permission::can('message_templates.view.all')) {
                    Response::json([
                        'success' => false,
                        'message' => 'Você não tem permissão para ver este template'
                    ], 403);
                    return;
                }
                
                Response::json([
                    'success' => true,
                    'template' => $template
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Template não encontrado'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao obter template: ' . $e->getMessage()
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
            
            // Verificar se é template pessoal do usuário
            $template = MessageTemplateService::get($id);
            if ($template) {
                $userId = \App\Helpers\Auth::id();
                // Se for template pessoal, só o dono pode editar (a menos que tenha permissão especial)
                if ($template['user_id'] !== null && $template['user_id'] != $userId && !Permission::can('message_templates.edit.all')) {
                    Response::json([
                        'success' => false,
                        'message' => 'Você não tem permissão para editar este template'
                    ], 403);
                    return;
                }
            }
            
            // Processar is_personal se fornecido
            if (isset($data['is_personal']) && $data['is_personal']) {
                $data['user_id'] = \App\Helpers\Auth::id();
                unset($data['is_personal']);
            }
            
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
     * Retorna templates pessoais do usuário logado + templates globais
     */
    public function getAvailable(): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $departmentId = Request::get('department_id');
            $channel = Request::get('channel');
            $userId = \App\Helpers\Auth::id(); // Obter ID do usuário logado
            
            $templates = MessageTemplateService::getAvailable(
                $departmentId ? (int)$departmentId : null,
                $channel,
                $userId
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
     * Obter templates pessoais do usuário logado (JSON)
     */
    public function getPersonal(): void
    {
        Permission::abortIfCannot('message_templates.view');
        
        try {
            $userId = \App\Helpers\Auth::id();
            if (!$userId) {
                Response::json([
                    'success' => false,
                    'message' => 'Usuário não autenticado',
                    'templates' => []
                ], 401);
                return;
            }
            
            $templates = MessageTemplateService::getPersonal($userId);
            
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

