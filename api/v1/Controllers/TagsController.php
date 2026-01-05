<?php
/**
 * TagsController - API v1
 */

namespace Api\V1\Controllers;

use Api\Helpers\ApiResponse;
use Api\Middleware\ApiAuthMiddleware;
use App\Models\Tag;
use App\Helpers\Validator;

class TagsController
{
    public function index(): void
    {
        ApiAuthMiddleware::requirePermission('tags.view');
        
        try {
            $tags = Tag::all();
            ApiResponse::success($tags);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao listar tags', $e);
        }
    }
    
    public function store(): void
    {
        ApiAuthMiddleware::requirePermission('tags.create');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $errors = Validator::validate($input, [
            'name' => 'required|string|max:255'
        ]);
        
        if (!empty($errors)) {
            ApiResponse::validationError('Dados inválidos', $errors);
        }
        
        try {
            $id = Tag::create($input);
            $tag = Tag::find($id);
            ApiResponse::created($tag, 'Tag criada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    public function show(string $id): void
    {
        ApiAuthMiddleware::requirePermission('tags.view');
        
        try {
            $tag = Tag::find((int)$id);
            
            if (!$tag) {
                ApiResponse::notFound('Tag não encontrada');
            }
            
            ApiResponse::success($tag);
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao obter tag', $e);
        }
    }
    
    public function update(string $id): void
    {
        ApiAuthMiddleware::requirePermission('tags.edit');
        
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        
        try {
            $tag = Tag::find((int)$id);
            
            if (!$tag) {
                ApiResponse::notFound('Tag não encontrada');
            }
            
            Tag::update((int)$id, $input);
            $updated = Tag::find((int)$id);
            
            ApiResponse::success($updated, 'Tag atualizada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::badRequest($e->getMessage());
        }
    }
    
    public function destroy(string $id): void
    {
        ApiAuthMiddleware::requirePermission('tags.delete');
        
        try {
            $tag = Tag::find((int)$id);
            
            if (!$tag) {
                ApiResponse::notFound('Tag não encontrada');
            }
            
            Tag::delete((int)$id);
            ApiResponse::success(null, 'Tag deletada com sucesso');
        } catch (\Exception $e) {
            ApiResponse::serverError('Erro ao deletar tag', $e);
        }
    }
}
