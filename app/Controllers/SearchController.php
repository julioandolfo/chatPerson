<?php
/**
 * Controller de Busca Global
 */

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Request;
use App\Helpers\Permission;
use App\Services\SearchService;

class SearchController
{
    /**
     * Busca global (conversas, contatos, mensagens)
     */
    public function global(): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $query = Request::get('q', '');
            $type = Request::get('type', 'all'); // all, conversations, contacts, messages
            $filters = [
                'status' => Request::get('status'),
                'channel' => Request::get('channel'),
                'department_id' => Request::get('department_id'),
                'tag_id' => Request::get('tag_id'),
                'date_from' => Request::get('date_from'),
                'date_to' => Request::get('date_to'),
                'agent_id' => Request::get('agent_id'),
                'limit' => Request::get('limit', 50),
                'offset' => Request::get('offset', 0)
            ];
            
            // Remover filtros vazios
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });
            
            if (empty($query) && empty($filters)) {
                Response::json([
                    'success' => false,
                    'message' => 'Termo de busca ou filtros devem ser fornecidos'
                ], 400);
                return;
            }
            
            $results = SearchService::global($query, $type, $filters);
            
            Response::json([
                'success' => true,
                'results' => $results,
                'query' => $query,
                'type' => $type
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Salvar busca (filtros salvos)
     */
    public function save(): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $name = Request::post('name');
            $query = Request::post('query', '');
            $filters = Request::post('filters', []);
            
            if (empty($name)) {
                Response::json([
                    'success' => false,
                    'message' => 'Nome da busca é obrigatório'
                ], 400);
                return;
            }
            
            $savedSearch = SearchService::saveSearch($name, $query, $filters);
            
            Response::json([
                'success' => true,
                'saved_search' => $savedSearch
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Listar buscas salvas
     */
    public function saved(): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            $savedSearches = SearchService::getSavedSearches();
            
            Response::json([
                'success' => true,
                'saved_searches' => $savedSearches
            ]);
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Deletar busca salva
     */
    public function deleteSaved(int $id): void
    {
        Permission::abortIfCannot('conversations.view.own');
        
        try {
            if (SearchService::deleteSavedSearch($id)) {
                Response::json([
                    'success' => true,
                    'message' => 'Busca deletada com sucesso'
                ]);
            } else {
                Response::json([
                    'success' => false,
                    'message' => 'Busca não encontrada'
                ], 404);
            }
        } catch (\Exception $e) {
            Response::json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

