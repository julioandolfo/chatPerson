<?php
/**
 * Controller de Tags
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\TagService;

class TagController
{
    /**
     * Listar tags
     */
    public function index(): void
    {
        Permission::abortIfCannot('tags.view');
        
        $filters = [
            'search' => Request::get('search'),
            'limit' => Request::get('limit', 100),
            'offset' => Request::get('offset', 0)
        ];
        
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        try {
            $tags = TagService::list($filters);
            
            Response::view('tags/index', [
                'tags' => $tags,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            Response::view('tags/index', [
                'tags' => [],
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Criar tag
     */
    public function store(): void
    {
        Permission::abortIfCannot('tags.create');
        
        try {
            $data = Request::post();
            $tagId = TagService::create($data);
            
            Response::json([
                'success' => true,
                'message' => 'Tag criada com sucesso!',
                'id' => $tagId
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => 'Erro ao criar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar tag
     */
    public function update(int $id): void
    {
        Permission::abortIfCannot('tags.edit');
        
        try {
            $data = Request::post();
            if (TagService::update($id, $data)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag atualizada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao atualizar tag.'
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
                'message' => 'Erro ao atualizar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deletar tag
     */
    public function destroy(int $id): void
    {
        Permission::abortIfCannot('tags.delete');
        
        try {
            if (TagService::delete($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag deletada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao deletar tag.'
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
     * Adicionar tag a conversa
     */
    public function addToConversation(int $conversationId): void
    {
        Permission::abortIfCannot('conversations.edit.tags');
        
        try {
            $tagId = Request::post('tag_id');
            if (TagService::addToConversation($conversationId, $tagId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag adicionada com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao adicionar tag.'
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
                'message' => 'Erro ao adicionar tag: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover tag de conversa
     */
    public function removeFromConversation(int $conversationId): void
    {
        Permission::abortIfCannot('conversations.edit.tags');
        
        try {
            $tagId = Request::post('tag_id');
            if (TagService::removeFromConversation($conversationId, $tagId)) {
                Response::json([
                    'success' => true,
                    'message' => 'Tag removida com sucesso!'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Falha ao remover tag.'
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
     * Obter tags de uma conversa (JSON)
     */
    public function getByConversation(int $conversationId): void
    {
        Permission::abortIfCannot('conversations.view');
        
        try {
            $tags = TagService::getByConversation($conversationId);
            Response::json([
                'success' => true,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'tags' => []
            ], 500);
        }
    }

    /**
     * Obter todas as tags (JSON - para uso em selects)
     */
    public function getAll(): void
    {
        Permission::abortIfCannot('tags.view');
        
        try {
            $tags = TagService::getAll();
            Response::json([
                'success' => true,
                'tags' => $tags
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage(),
                'tags' => []
            ], 500);
        }
    }
}

